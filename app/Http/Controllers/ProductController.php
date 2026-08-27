<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    // Public so InventoryQueryAssistant can re-validate an AI-translated `sort` value against
    // the exact same whitelist — the one thing that actually prevents ORDER-BY injection, since
    // column names can't be parameterized.
    public const SORTABLE = ['name', 'sku', 'current_stock', 'unit_price'];

    /**
     * The one place natural-language search (ProductAiSearchController) and manual search both
     * end up — the AI path only ever produces values for these same query params, so it never
     * gets more authority over the database than a human typing a URL already has.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'supplier'])
            ->withCurrentStock();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        if ($supplier = trim((string) $request->input('supplier'))) {
            $query->whereHas('supplier', function ($q) use ($supplier) {
                $q->where('name', 'like', "%{$supplier}%");
            });
        }

        if ($category = trim((string) $request->input('category'))) {
            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', 'like', "%{$category}%");
            });
        }

        // current_stock is a SELECT-list alias from withCurrentStock()'s joined subquery, not a
        // real column — Postgres (production) rejects filtering on it directly, unlike SQLite
        // (local/CI), which tolerates it. Filtering the actual joined column instead, wrapped in
        // the same COALESCE used in the SELECT list, works identically on both.
        if (($minStock = $request->input('min_stock')) !== null && is_numeric($minStock)) {
            $query->where(DB::raw('COALESCE(stock.stock, 0)'), '>=', max(0, (int) $minStock));
        }

        if (($maxStock = $request->input('max_stock')) !== null && is_numeric($maxStock)) {
            $query->where(DB::raw('COALESCE(stock.stock, 0)'), '<=', max(0, (int) $maxStock));
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('COALESCE(stock.stock, 0) <= products.reorder_threshold');
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        }

        $products = $query->paginate(15)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only([
                'search', 'supplier', 'category', 'min_stock', 'max_stock', 'low_stock', 'sort', 'direction',
            ]),
        ]);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'supplier']);

        return Inertia::render('Products/Show', [
            'product' => [
                ...$product->toArray(),
                'current_stock' => $product->currentStock(),
            ],
            'movements' => $product->movements()->orderByDesc('occurred_at')->take(50)->get(),
        ]);
    }
}
