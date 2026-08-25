<?php

namespace App\Pipelines\Inventory;

use Closure;

/**
 * Supplier feeds are notoriously inconsistent about SKU formatting — stray
 * whitespace, a vendor-specific prefix, lowercase codes. This stage is the
 * one place that knows about those quirks so the rest of the pipeline can
 * assume a clean, canonical SKU.
 */
class NormalizeSku
{
    public function handle(FeedRowContext $context, Closure $next): FeedRowContext
    {
        $sku = trim((string) $context->raw['sku']);
        $sku = preg_replace('/^VND[:\-]/i', '', $sku);
        $sku = strtoupper(preg_replace('/\s+/', '', $sku));

        if ($sku === '') {
            return $context->skip('sku empty after normalization');
        }

        $context->sku = $sku;

        return $next($context);
    }
}
