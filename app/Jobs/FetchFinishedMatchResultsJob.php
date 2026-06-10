<?php

namespace App\Jobs;

use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\FootballApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class FetchFinishedMatchResultsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        FootballApiService $footballApi,
        BetService $betService,
        EliminationService $eliminationService,
    ): void {
        $pendingMatches = WorldMatch::query()->pendingResults()->get();

        if ($pendingMatches->isEmpty()) {
            return;
        }

        $fixtures = $footballApi->getFixturesByDate(Carbon::today()->format('Y-m-d'));

        foreach ($fixtures as $fixture) {
            $apiId = $fixture['id'] ?? null;

            if ($apiId === null || ($fixture['status'] ?? '') !== 'FINISHED') {
                continue;
            }

            $match = WorldMatch::where('api_fixture_id', $apiId)->first();

            if ($match === null) {
                continue;
            }

            $match->update([
                'status'     => 'finished',
                'score_home' => $fixture['score']['fullTime']['home'] ?? null,
                'score_away' => $fixture['score']['fullTime']['away'] ?? null,
            ]);

            $match->refresh()->load('bets');
            $betService->resolveBets($match);
        }

        $eliminationService->checkAll();
    }
}
