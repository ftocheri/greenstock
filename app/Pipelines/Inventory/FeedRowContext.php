<?php

namespace App\Pipelines\Inventory;

use App\Models\Product;
use Carbon\Carbon;

class FeedRowContext
{
    public ?string $sku = null;

    public ?int $quantityReceived = null;

    public ?float $unitCost = null;

    public ?Carbon $receivedAt = null;

    public ?Product $product = null;

    public bool $skipped = false;

    public ?string $skipReason = null;

    public bool $flaggedLowStock = false;

    public function __construct(public array $raw)
    {
    }

    public function skip(string $reason): static
    {
        $this->skipped = true;
        $this->skipReason = $reason;

        return $this;
    }
}
