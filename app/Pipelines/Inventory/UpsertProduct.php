<?php

namespace App\Pipelines\Inventory;

use App\Models\Product;
use Closure;

/**
 * Deliberately does NOT create a product for an unrecognized SKU — a
 * restock feed should only ever move stock for items already in the
 * catalog. An unknown SKU is treated as a data problem to flag, not a new
 * product to silently create.
 */
class UpsertProduct
{
    public function handle(FeedRowContext $context, Closure $next): FeedRowContext
    {
        $product = Product::where('sku', $context->sku)->first();

        if (! $product) {
            return $context->skip("unknown sku: {$context->sku}");
        }

        $context->product = $product;

        return $next($context);
    }
}
