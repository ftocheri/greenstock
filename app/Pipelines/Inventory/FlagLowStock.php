<?php

namespace App\Pipelines\Inventory;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Runs last, after the restock has been recorded. A product can still be
 * below its reorder threshold even after a delivery — this is the hook
 * where a real system would fire a notification to purchasing.
 */
class FlagLowStock
{
    public function handle(FeedRowContext $context, Closure $next): FeedRowContext
    {
        if ($context->product->isLowStock()) {
            $context->flaggedLowStock = true;

            Log::warning("Product {$context->sku} still below reorder threshold after restock.");
        }

        return $next($context);
    }
}
