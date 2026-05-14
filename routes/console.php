<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh TCGPlayer storefront rating/feedback once a day at 6:00 AM server time.
Schedule::command('seller-stats:refresh')->dailyAt('06:00');

// Purge import files older than 90 days every Sunday at 3:00 AM server time.
Schedule::command('files:purge')->weeklyOn(0, '03:00');

// Nightly PostgreSQL backup to DO Spaces at 2:00 AM server time.
Schedule::command('db:backup')->dailyAt('02:00');
