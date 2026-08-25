<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Simulates a nightly supplier sync — in production this path would be
// wherever the SFTP/API pull for the day's feed lands.
Schedule::command('inventory:import ' . storage_path('app/feeds/sample-supplier-feed.csv'))
    ->daily()
    ->description('Nightly supplier inventory sync');
