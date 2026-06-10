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
        // football-data.org: scorers array, each: { player: { name, ... }, goals, ... }
        $data = $footballApi->getTopScorers();

        // Normalise to the shape Participant::scorerCorrect() expects: [0]['player']['name']
        $normalized = array_map(fn ($s) => [
            'player' => ['name' => $s['player']['name'] ?? ''],
            'goals'  => $s['goals'] ?? 0,
        ], $data);

        Cache::put('mundial.topscorers', $normalized, now()->addHours(7));
    }
}
