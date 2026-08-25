<?php

namespace Tests\Feature;

use App\Models\InventoryImportLog;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_movement_for_a_valid_row(): void
    {
        $product = Product::factory()->create(['sku' => 'AB-1234', 'reorder_threshold' => 10]);

        $path = $this->writeCsv([
            ['sku', 'quantity_received', 'unit_cost', 'received_at'],
            ['AB-1234', '50', '2.50', now()->toDateString()],
        ]);

        Artisan::call('inventory:import', ['path' => $path]);

        $this->assertSame(1, InventoryMovement::where('product_id', $product->id)->where('type', 'in')->count());
        $this->assertSame(50, $product->currentStock());

        $log = InventoryImportLog::first();
        $this->assertSame(1, $log->rows_processed);
        $this->assertSame(0, $log->rows_skipped);
    }

    public function test_it_skips_invalid_and_unknown_rows(): void
    {
        Product::factory()->create(['sku' => 'KNOWN-1']);

        $path = $this->writeCsv([
            ['sku', 'quantity_received', 'unit_cost', 'received_at'],
            ['', '10', '1.00', now()->toDateString()],
            ['UNKNOWN-9', '10', '1.00', now()->toDateString()],
            ['KNOWN-1', '-5', '1.00', now()->toDateString()],
            ['KNOWN-1', '10', '1.00', 'not-a-date'],
        ]);

        Artisan::call('inventory:import', ['path' => $path]);

        $this->assertSame(0, InventoryMovement::count());

        $log = InventoryImportLog::first();
        $this->assertSame(0, $log->rows_processed);
        $this->assertSame(4, $log->rows_skipped);
    }

    public function test_it_normalizes_vendor_prefixed_skus(): void
    {
        $product = Product::factory()->create(['sku' => 'CD-5678']);

        $path = $this->writeCsv([
            ['sku', 'quantity_received', 'unit_cost', 'received_at'],
            ['VND:cd-5678', '30', '1.20', now()->toDateString()],
        ]);

        Artisan::call('inventory:import', ['path' => $path]);

        $this->assertSame(30, $product->currentStock());
    }

    private function writeCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'feed').'.csv';
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
