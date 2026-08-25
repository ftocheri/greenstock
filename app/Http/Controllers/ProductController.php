<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    private const SORTABLE = ['name', 'sku', 'current_stock', 'unit_price'];

    public function index(Request $request)
    {
        $stockSubquery = DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity WHEN type = 'out' THEN -quantity ELSE quantity END) as stock")
            ->groupBy('product_id');

        $query = Product::query()
            ->with(['category', 'supplier'])
            ->leftJoinSub($stockSubquery, 'stock', 'stock.product_id', '=', 'products.id')
            ->select('products.*', DB::raw('COALESCE(stock.stock, 0) as current_stock'));

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, self::SORTABLE, true)) {
            $query->orderBy($sort, $direction);
        }

        $products = $query->paginate(15)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'sort', 'direction']),
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
