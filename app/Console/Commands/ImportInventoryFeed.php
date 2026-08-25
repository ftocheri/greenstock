<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInventoryFeedJob;
use Illuminate\Console\Command;

class ImportInventoryFeed extends Command
{
    protected $signature = 'inventory:import {path : Path to the supplier feed CSV}';

    protected $description = 'Import a supplier inventory feed through the pipeline (App\Pipelines\Inventory)';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Dispatching import job for {$path}...");

        ProcessInventoryFeedJob::dispatch($path, basename($path));

        $this->info('Done. See the inventory_import_logs table or the dashboard for the run summary.');

        return self::SUCCESS;
    }
}
