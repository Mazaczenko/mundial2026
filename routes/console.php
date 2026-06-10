<?php

use App\Jobs\CheckEliminationsJob;
use App\Jobs\FetchFinishedMatchResultsJob;
use App\Jobs\SendMatchRemindersJob;
use App\Jobs\SyncStandingsJob;
use App\Jobs\SyncTopScorersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new FetchFinishedMatchResultsJob)->everyFifteenMinutes();
Schedule::job(new SyncStandingsJob)->hourly();
Schedule::job(new SyncTopScorersJob)->twiceDaily(8, 20);
Schedule::job(new SendMatchRemindersJob)->everyMinute();
Schedule::job(new CheckEliminationsJob)->dailyAt('23:00');
