<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Define your scheduled tasks here. Run `php artisan schedule:work` locally
| or set up a cron job: * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Fetch Panchang data - runs every minute, fetches ONE missing date per run
Schedule::command('panchang:fetch')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/panchang-fetch.log'));
