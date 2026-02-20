<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Clean inactive games every minute
Schedule::command('games:clean-inactive')->everyMinute();

// Check turn timeouts every 10 seconds
Schedule::command('games:check-timeouts')->everyTenSeconds();
