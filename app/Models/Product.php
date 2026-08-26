<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
     * Joins the movement ledger and exposes it as a `current_stock` column,
     * so callers that need it on a whole listing (not just one product) don't
     * pay for an N+1 currentStock() call per row.
     */
    public function scopeWithCurrentStock(Builder $query): Builder
    {
        $stockSubquery = DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity WHEN type = 'out' THEN -quantity ELSE quantity END) as stock")
            ->groupBy('product_id');

        return $query
            ->leftJoinSub($stockSubquery, 'stock', 'stock.product_id', '=', 'products.id')
            ->select('products.*', DB::raw('COALESCE(stock.stock, 0) as current_stock'));
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
