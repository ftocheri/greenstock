<?php

namespace App\Pipelines\Inventory;

use Carbon\Carbon;
use Closure;

/**
 * Confirms the row has the shape we can work with at all. Deliberately does
 * not try to interpret or clean the values yet — that's NormalizeSku's job.
 */
class ValidateRow
{
    public function handle(FeedRowContext $context, Closure $next): FeedRowContext
    {
        $sku = trim((string) ($context->raw['sku'] ?? ''));
        $quantity = $context->raw['quantity_received'] ?? null;
        $cost = $context->raw['unit_cost'] ?? null;
        $receivedAt = $context->raw['received_at'] ?? null;

        if ($sku === '') {
            return $context->skip('missing sku');
        }

        if (! is_numeric($quantity) || (int) $quantity <= 0) {
            return $context->skip('invalid quantity_received');
        }

        if (! is_numeric($cost) || (float) $cost < 0) {
            return $context->skip('invalid unit_cost');
        }

        try {
            $context->receivedAt = Carbon::parse($receivedAt);
        } catch (\Throwable) {
            return $context->skip('invalid received_at');
        }

        $context->quantityReceived = (int) $quantity;
        $context->unitCost = (float) $cost;

        return $next($context);
    }
}
