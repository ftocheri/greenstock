<?php

namespace App\Services;

use App\Exceptions\InventoryQueryTranslationException;
use App\Http\Controllers\ProductController;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates a natural-language inventory query into the same filter shape
 * ProductController::index() already accepts from the manual search box — supplier, category,
 * min/max stock, low_stock, sort, direction. The model never touches the database and never
 * gets more authority than a human typing those same query params into the URL bar: every
 * value returned here still flows through ProductController's existing parameterized query
 * builder and the same SORTABLE whitelist.
 */
class InventoryQueryAssistant
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const MAX_STOCK_VALUE = 1_000_000;

    private const RETRYABLE_ERROR_TYPES = ['rate_limit_error', 'overloaded_error'];

    /**
     * @return array<string, mixed> Only the keys the model actually populated, ready to merge
     *                              straight into the products.index query params.
     *
     * @throws InventoryQueryTranslationException
     */
    public function translate(string $query): array
    {
        $response = $this->callAnthropic($query);

        $toolInput = $this->extractToolInput($response);

        return $this->validate($toolInput);
    }

    private function callAnthropic(string $query, bool $isRetry = false): array
    {
        $apiKey = config('services.anthropic.key');

        if (! $apiKey) {
            Log::error('InventoryQueryAssistant: ANTHROPIC_API_KEY is not configured — the AI search feature is silently unavailable.');

            throw new InventoryQueryTranslationException('Anthropic API key is not configured.');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                    'content-type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => 512,
                    'system' => $this->systemPrompt(),
                    'messages' => [
                        ['role' => 'user', 'content' => $query],
                    ],
                    'tools' => [$this->toolDefinition()],
                    'tool_choice' => ['type' => 'tool', 'name' => 'apply_inventory_filter'],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('InventoryQueryAssistant: connection error calling Anthropic.', ['message' => $e->getMessage()]);

            throw new InventoryQueryTranslationException('Could not reach the AI service.', previous: $e);
        }

        if ($response->successful()) {
            return $response->json();
        }

        $errorType = $response->json('error.type');

        // rate_limit_error and overloaded_error are Anthropic's own retryable-error signals
        // (the latter especially is often transient) — one short backoff, then give up.
        if (! $isRetry && in_array($errorType, self::RETRYABLE_ERROR_TYPES, true)) {
            usleep(300_000);

            return $this->callAnthropic($query, isRetry: true);
        }

        // Auth/config errors mean the feature is silently broken for every user, not just this
        // one query — worth its own distinct, loud log line rather than blending into "model
        // returned garbage."
        if (in_array($errorType, ['authentication_error', 'permission_error'], true)) {
            Log::error('InventoryQueryAssistant: Anthropic rejected the API key.', ['type' => $errorType]);
        } else {
            Log::warning('InventoryQueryAssistant: Anthropic returned an error.', [
                'status' => $response->status(),
                'type' => $errorType,
            ]);
        }

        throw new InventoryQueryTranslationException("Anthropic API error: {$errorType}");
    }

    private function extractToolInput(array $response): array
    {
        // stop_reason can be "refusal" or "max_tokens" with no tool_use block at all, independent
        // of anything schema-related — has to be handled regardless of the strict-schema setup.
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'apply_inventory_filter') {
                return is_array($block['input'] ?? null) ? $block['input'] : [];
            }
        }

        Log::warning('InventoryQueryAssistant: no apply_inventory_filter tool_use block in the response.', [
            'stop_reason' => $response['stop_reason'] ?? null,
        ]);

        throw new InventoryQueryTranslationException('The AI service did not return a usable filter.');
    }

    /**
     * Defense in depth even though the tool schema is `strict: true` on Anthropic's side:
     * strict mode enforces types/enums/required-ness, but JSON Schema `minimum`/`maximum` are
     * not enforced under strict mode — nothing stops a `min_stock: -50` coming back. `sort`
     * re-checks the *exact* whitelist ProductController::index() itself checks, since that's
     * the thing that actually prevents ORDER-BY injection.
     *
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        $filters = [];

        foreach (['search', 'supplier', 'category'] as $field) {
            $value = $input[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $filters[$field] = mb_substr(trim($value), 0, 200);
            }
        }

        foreach (['min_stock', 'max_stock'] as $field) {
            $value = $input[$field] ?? null;
            if (is_int($value) || (is_numeric($value) && (int) $value == $value)) {
                $filters[$field] = max(0, min(self::MAX_STOCK_VALUE, (int) $value));
            }
        }

        if (($input['low_stock'] ?? null) === true) {
            $filters['low_stock'] = true;
        }

        $sort = $input['sort'] ?? null;
        if (is_string($sort) && in_array($sort, ProductController::SORTABLE, true)) {
            $filters['sort'] = $sort;
        }

        $direction = $input['direction'] ?? null;
        if (in_array($direction, ['asc', 'desc'], true)) {
            $filters['direction'] = $direction;
        }

        return $filters;
    }

    private function toolDefinition(): array
    {
        return [
            'name' => 'apply_inventory_filter',
            'description' => 'Apply a structured filter to the product inventory listing based on the user\'s natural-language request.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'search' => [
                        'type' => ['string', 'null'],
                        'description' => 'Substring to match against product name or SKU.',
                    ],
                    'supplier' => [
                        'type' => ['string', 'null'],
                        'description' => 'Substring to match against supplier name.',
                    ],
                    'category' => [
                        'type' => ['string', 'null'],
                        'description' => 'Substring to match against category name.',
                    ],
                    'min_stock' => [
                        'type' => ['integer', 'null'],
                        'description' => 'Minimum current stock, inclusive.',
                    ],
                    'max_stock' => [
                        'type' => ['integer', 'null'],
                        'description' => 'Maximum current stock, inclusive.',
                    ],
                    'low_stock' => [
                        'type' => ['boolean', 'null'],
                        'description' => 'True only if the user specifically asked for items at or below their reorder threshold.',
                    ],
                    'sort' => [
                        'type' => ['string', 'null'],
                        'enum' => [...ProductController::SORTABLE, null],
                    ],
                    'direction' => [
                        'type' => ['string', 'null'],
                        'enum' => ['asc', 'desc', null],
                    ],
                ],
                'required' => ['search', 'supplier', 'category', 'min_stock', 'max_stock', 'low_stock', 'sort', 'direction'],
                'additionalProperties' => false,
            ],
        ];
    }

    private function systemPrompt(): string
    {
        $suppliers = $this->groundedNames(Supplier::class, 'ai-search:suppliers');
        $categories = $this->groundedNames(Category::class, 'ai-search:categories');

        return <<<PROMPT
            You translate a plant nursery inventory manager's natural-language request into the
            apply_inventory_filter tool. Only set a field when the user's request actually implies
            it — leave everything else null. Match supplier and category names against the real
            options below rather than guessing; if nothing matches closely, leave that field null
            rather than inventing a name.

            Known suppliers: {$suppliers}
            Known categories: {$categories}
            PROMPT;
    }

    private function groundedNames(string $modelClass, string $cacheKey): string
    {
        $names = Cache::remember($cacheKey, now()->addMinutes(10), fn () => $modelClass::query()->pluck('name'));

        return $names->isEmpty() ? '(none yet)' : $names->implode(', ');
    }
}
