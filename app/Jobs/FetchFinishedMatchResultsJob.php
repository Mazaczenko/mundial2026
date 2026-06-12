<?php

namespace App\Jobs;

use App\Models\MatchGoal;
use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\EspnApiService;
use App\Services\FootballApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class FetchFinishedMatchResultsJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        FootballApiService $footballApi,
        EspnApiService $espnApi,
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

            $this->syncGoals($espnApi, $match);

            $match->refresh()->load('bets');
            $betService->resolveBets($match);
        }

        $eliminationService->checkAll();
    }

    public function syncGoals(EspnApiService $espnApi, WorldMatch $match): void
    {
        $date    = $match->kickoff_at->format('Y-m-d');
        $eventId = $espnApi->findEventId($date, $match->home_team, $match->away_team);

        if ($eventId === null) {
            return;
        }

        $goals = $espnApi->getGoals($eventId, $match->home_team, $match->away_team);

        if (empty($goals)) {
            return;
        }

        $match->goals()->delete();

        foreach ($goals as $goal) {
            MatchGoal::create([
                'world_match_id' => $match->id,
                'player_name'    => $goal['player_name'],
                'team_side'      => $goal['team_side'],
                'minute'         => $goal['minute'],
                'own_goal'       => $goal['own_goal'],
            ]);
        }
    }
}
