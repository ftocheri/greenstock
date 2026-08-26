<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
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

        // Low stock first by default — that's what an admin managing inventory needs to see.
        $products = $query->orderBy('current_stock')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Inventory/Index', [
            'products' => $products,
            'filters' => $request->only(['search']),
        ]);
    }

    public function adjust(Request $request, Product $product)
    {
        $validated = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => $validated['delta'],
            'occurred_at' => now(),
            'source' => 'admin:'.$request->user()->name,
        ]);

        $newStock = $product->currentStock();

        return back()->with(
            'success',
            "Adjusted {$product->sku} by ".($validated['delta'] > 0 ? '+' : '').$validated['delta'].". New stock: {$newStock}."
        );
    }
}
