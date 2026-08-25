<?php

namespace App\Http\Controllers;

use App\Models\InventoryImportLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totalSkus = Product::count();
        $lowStockCount = $this->lowStockCount();

        $monthly = $this->monthlyMovementTotals();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalSkus' => $totalSkus,
                'lowStockCount' => $lowStockCount,
            ],
            'chart' => [
                'labels' => $monthly->keys()->values(),
                'received' => $monthly->pluck('in')->values(),
                'sold' => $monthly->pluck('out')->values(),
            ],
            'recentImports' => InventoryImportLog::latest('ran_at')->take(5)->get(),
        ]);
    }

    private function lowStockCount(): int
    {
        $stockSubquery = DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity WHEN type = 'out' THEN -quantity ELSE quantity END) as stock")
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoinSub($stockSubquery, 'stock', 'stock.product_id', '=', 'products.id')
            ->whereRaw('COALESCE(stock.stock, 0) <= products.reorder_threshold')
            ->count();
    }

    private function monthlyMovementTotals()
    {
        return InventoryMovement::query()
            ->where('occurred_at', '>=', now()->subMonths(6)->startOfMonth())
            ->get(['type', 'quantity', 'occurred_at'])
            ->groupBy(fn ($movement) => $movement->occurred_at->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group) => [
                'in' => (int) $group->where('type', 'in')->sum('quantity'),
                'out' => (int) $group->where('type', 'out')->sum('quantity'),
            ]);
    }
}
