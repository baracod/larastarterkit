<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Shared cache must be identical in the web and scheduler containers.
\Illuminate\Support\Facades\Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::put('starter:scheduler:heartbeat', now()->timestamp, 600);
})->name('starter-scheduler-heartbeat')->everyMinute();

\Illuminate\Support\Facades\Schedule::command('horizon:snapshot --no-interaction')->everyFiveMinutes();
