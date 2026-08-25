<?php

namespace App\Pipelines\Inventory;

use App\Models\InventoryMovement;
use Closure;

class RecordMovement
{
    public function handle(FeedRowContext $context, Closure $next): FeedRowContext
    {
        InventoryMovement::create([
            'product_id' => $context->product->id,
            'type' => 'in',
            'quantity' => $context->quantityReceived,
            'occurred_at' => $context->receivedAt,
            'source' => 'supplier_feed',
        ]);

        return $next($context);
    }
}
