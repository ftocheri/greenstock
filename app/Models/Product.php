<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku', 'name', 'category_id', 'supplier_id',
        'unit_price', 'reorder_threshold', 'description',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Current stock is derived from the movement ledger rather than stored,
     * so it can never drift out of sync with the history that produced it.
     */
    public function currentStock(): int
    {
        return (int) $this->movements()
            ->selectRaw("COALESCE(SUM(CASE
                WHEN type = 'in' THEN quantity
                WHEN type = 'out' THEN -quantity
                ELSE quantity
            END), 0) as stock")
            ->value('stock');
    }

    public function isLowStock(): bool
    {
        return $this->currentStock() <= $this->reorder_threshold;
    }
}
