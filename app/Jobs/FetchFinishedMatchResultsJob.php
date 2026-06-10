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

        $finishedStatuses = ['FT', 'AET', 'PEN'];

        foreach ($fixtures as $fixture) {
            $apiFixtureId = $fixture['fixture']['id'] ?? null;
            $shortStatus = $fixture['fixture']['status']['short'] ?? null;

            if ($apiFixtureId === null) {
                continue;
            }

            $match = WorldMatch::query()
                ->where('api_fixture_id', $apiFixtureId)
                ->first();

            if ($match === null) {
                continue;
            }

            if (in_array($shortStatus, $finishedStatuses, strict: true)) {
                $scoreHome = $fixture['score']['fulltime']['home'] ?? null;
                $scoreAway = $fixture['score']['fulltime']['away'] ?? null;

                $match->update([
                    'status' => 'finished',
                    'score_home' => $scoreHome,
                    'score_away' => $scoreAway,
                ]);

                $match->refresh();
                $match->load('bets');

                $betService->resolveBets($match);
            }
        }

        $eliminationService->checkAll();
    }
}
