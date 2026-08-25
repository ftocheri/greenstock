<?php

namespace App\Jobs;

use App\Models\InventoryImportLog;
use App\Pipelines\Inventory\FeedRowContext;
use App\Pipelines\Inventory\FlagLowStock;
use App\Pipelines\Inventory\NormalizeSku;
use App\Pipelines\Inventory\RecordMovement;
use App\Pipelines\Inventory\UpsertProduct;
use App\Pipelines\Inventory\ValidateRow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInventoryFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const STAGES = [
        ValidateRow::class,
        NormalizeSku::class,
        UpsertProduct::class,
        RecordMovement::class,
        FlagLowStock::class,
    ];

    public function __construct(
        public string $path,
        public string $filename,
    ) {
    }

    public function handle(): void
    {
        $handle = fopen($this->path, 'r');
        $header = fgetcsv($handle);

        $processed = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $raw = array_combine($header, $row);

            $context = app(Pipeline::class)
                ->send(new FeedRowContext($raw))
                ->through(self::STAGES)
                ->thenReturn();

            if ($context->skipped) {
                $skipped++;
                Log::warning("Inventory feed row skipped: {$context->skipReason}", ['row' => $raw]);
            } else {
                $processed++;
            }
        }

        fclose($handle);

        InventoryImportLog::create([
            'filename' => $this->filename,
            'rows_processed' => $processed,
            'rows_skipped' => $skipped,
            'ran_at' => now(),
        ]);
    }
}
