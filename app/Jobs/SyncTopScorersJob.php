<?php

namespace App\Jobs;

use App\Services\FootballApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class SyncTopScorersJob implements ShouldQueue
{
    use Queueable;

    public function handle(FootballApiService $footballApi): void
    {
        $data = $footballApi->getTopScorers();

        Cache::put('mundial.topscorers', $data, now()->addHours(7));
    }
}
