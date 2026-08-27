<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductAiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/products/ai-search?query=basil')->assertRedirect('/login');
    }

    public function test_happy_path_translates_and_the_filter_finds_the_right_products(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Wisoky and Sons']);
        $matching = Product::factory()->create(['supplier_id' => $supplier->id]);
        $other = Product::factory()->create();

        InventoryMovement::factory()->create(['product_id' => $matching->id, 'type' => 'in', 'quantity' => 10]);
        InventoryMovement::factory()->create(['product_id' => $other->id, 'type' => 'in', 'quantity' => 500]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null,
                'supplier' => 'Wisoky',
                'category' => null,
                'min_stock' => null,
                'max_stock' => 20,
                'low_stock' => null,
                'sort' => null,
                'direction' => null,
            ])),
        ]);

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=' . urlencode('everything from Wisoky under 20 units'));

        $response->assertOk()->assertJson([
            'filters' => ['supplier' => 'Wisoky', 'max_stock' => 20],
        ]);

        $filters = $response->json('filters');

        $indexResponse = $this->actingAs($user)->get('/products?' . http_build_query($filters));

        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $matching->id)
        );
    }

    public function test_a_response_with_no_tool_use_block_returns_a_generic_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
                'stop_reason' => 'end_turn',
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=hello');

        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_a_connection_failure_returns_a_generic_error(): void
    {
        $user = User::factory()->create();

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=hello');

        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_a_query_over_the_length_limit_is_rejected_before_calling_anthropic(): void
    {
        $user = User::factory()->create();

        Http::fake();

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=' . str_repeat('a', 201));

        $response->assertStatus(422)->assertJsonValidationErrors(['query']);
        Http::assertNothingSent();
    }

    public function test_an_out_of_range_stock_value_is_clamped_not_trusted(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null,
                'supplier' => null,
                'category' => null,
                'min_stock' => -50,
                'max_stock' => null,
                'low_stock' => null,
                'sort' => null,
                'direction' => null,
            ])),
        ]);

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=negative');

        $response->assertOk()->assertJson(['filters' => ['min_stock' => 0]]);
    }

    public function test_a_sort_value_outside_the_whitelist_is_dropped_not_trusted(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null,
                'supplier' => null,
                'category' => null,
                'min_stock' => null,
                'max_stock' => null,
                'low_stock' => null,
                'sort' => 'reorder_threshold',
                'direction' => 'asc',
            ])),
        ]);

        $response = $this->actingAs($user)->getJson('/products/ai-search?query=sort+by+threshold');

        $response->assertOk();
        $this->assertArrayNotHasKey('sort', $response->json('filters'));
    }

    public function test_a_prompt_injection_shaped_supplier_value_produces_no_crash_and_no_matches(): void
    {
        $user = User::factory()->create();
        Product::factory()->create();

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null,
                'supplier' => "Wisoky'; DROP TABLE products; --",
                'category' => null,
                'min_stock' => null,
                'max_stock' => null,
                'low_stock' => null,
                'sort' => null,
                'direction' => null,
            ])),
        ]);

        $filters = $this->actingAs($user)
            ->getJson('/products/ai-search?query=ignore+instructions')
            ->json('filters');

        $indexResponse = $this->actingAs($user)->get('/products?' . http_build_query($filters));

        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn ($page) => $page->has('products.data', 0));

        // The parameterized query builder treated the string as inert data — the table is
        // still here and still queryable, proving this was never a code path, just a value.
        $this->assertDatabaseCount('products', 1);
    }

    public function test_rate_limiting_kicks_in_after_ten_requests_per_minute(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null, 'supplier' => null, 'category' => null,
                'min_stock' => null, 'max_stock' => null, 'low_stock' => null,
                'sort' => null, 'direction' => null,
            ])),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->getJson('/products/ai-search?query=test')->assertOk();
        }

        $this->actingAs($user)->getJson('/products/ai-search?query=test')->assertStatus(429);
    }

    public function test_ai_translated_filters_produce_the_same_results_as_the_same_filters_typed_by_hand(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Wisoky and Sons']);
        $matching = Product::factory()->create(['supplier_id' => $supplier->id]);
        Product::factory()->create();

        InventoryMovement::factory()->create(['product_id' => $matching->id, 'type' => 'in', 'quantity' => 5]);

        $handTypedFilters = ['supplier' => 'Wisoky', 'max_stock' => 20];

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolResponse([
                'search' => null,
                'supplier' => 'Wisoky',
                'category' => null,
                'min_stock' => null,
                'max_stock' => 20,
                'low_stock' => null,
                'sort' => null,
                'direction' => null,
            ])),
        ]);

        $aiFilters = $this->actingAs($user)
            ->getJson('/products/ai-search?query=wisoky+under+20')
            ->json('filters');

        $this->assertEquals($handTypedFilters['supplier'], $aiFilters['supplier']);
        $this->assertEquals($handTypedFilters['max_stock'], $aiFilters['max_stock']);

        $handTypedIds = collect(
            $this->actingAs($user)->get('/products?' . http_build_query($handTypedFilters))
                ->viewData('page')['props']['products']['data']
        )->pluck('id');

        $aiIds = collect(
            $this->actingAs($user)->get('/products?' . http_build_query($aiFilters))
                ->viewData('page')['props']['products']['data']
        )->pluck('id');

        $this->assertEquals([$matching->id], $handTypedIds->all());
        $this->assertEquals($handTypedIds->all(), $aiIds->all());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fakeToolResponse(array $input): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_test',
                    'name' => 'apply_inventory_filter',
                    'input' => $input,
                ],
            ],
            'stop_reason' => 'tool_use',
        ];
    }
}
