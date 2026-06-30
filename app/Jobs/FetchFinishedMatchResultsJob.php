<?php

namespace App\Jobs;

use App\Models\Participant;
use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\EspnApiService;
use App\Services\FootballApiService;
use App\Services\NotificationService;
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
        NotificationService $notificationService,
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
                'status' => 'finished',
                'score_home' => $fixture['score']['fullTime']['home'] ?? null,
                'score_away' => $fixture['score']['fullTime']['away'] ?? null,
                'result_type' => match ($fixture['score']['duration'] ?? 'REGULAR') {
                    'EXTRA_TIME' => 'AET',
                    'PENALTY_SHOOTOUT' => 'PEN',
                    default => 'FT',
                },
                'score_home_et' => $fixture['score']['extraTime']['home'] ?? null,
                'score_away_et' => $fixture['score']['extraTime']['away'] ?? null,
                'score_home_pen' => $fixture['score']['penalties']['home'] ?? null,
                'score_away_pen' => $fixture['score']['penalties']['away'] ?? null,
            ]);

            $this->syncGoals($espnApi, $match);
            $this->syncCards($espnApi, $match);
            $this->syncMatchStats($espnApi, $match);
            $this->syncLineup($espnApi, $match);

            $match->refresh()->load('bets');
            $betService->resolveBets($match);

            $this->sendResultNotifications($notificationService, $match);
        }

        $eliminationService->checkAll();
    }

    public function syncGoals(EspnApiService $espnApi, WorldMatch $match): void
    {
        $espnApi->syncGoalsForMatch($match);
    }

    public function syncCards(EspnApiService $espnApi, WorldMatch $match): void
    {
        $espnApi->syncCardsForMatch($match);
    }

    public function syncMatchStats(EspnApiService $espnApi, WorldMatch $match): void
    {
        $espnApi->syncMatchStatsForMatch($match);
    }

    private function syncLineup(EspnApiService $espnApi, WorldMatch $match): void
    {
        $espnApi->syncLineupForMatch($match);
    }

    private function sendResultNotifications(NotificationService $notifService, WorldMatch $match): void
    {
        $match->refresh()->load('bets.participant');

        $home = $match->score_home ?? 0;
        $away = $match->score_away ?? 0;
        $scoreStr = "{$home}:{$away}";

        foreach ($match->bets as $bet) {
            if ($bet->participant === null || $bet->participant->eliminated) {
                continue;
            }

            $correct = (bool) $bet->is_correct;
            $icon = $correct ? '✅' : '❌';
            $title = "{$match->home_team} {$scoreStr} {$match->away_team}";
            $body = "{$icon} Twój typ: {$bet->prediction_1x2}".($correct ? ' (+1 pkt)' : '');

            $notifService->notify(
                [$bet->participant_id],
                'result',
                $title,
                $body,
                '/results',
                ['match_id' => $match->id, 'correct' => $correct],
            );
        }

        $bettedIds = $match->bets->pluck('participant_id')->all();
        $missed = Participant::where('eliminated', false)
            ->whereNotIn('id', $bettedIds)
            ->pluck('id')
            ->all();

        if (! empty($missed)) {
            $title = "{$match->home_team} {$scoreStr} {$match->away_team}";
            $notifService->notify(
                $missed,
                'result',
                $title,
                '❕ Nie obstawiłeś tego meczu',
                '/results',
                ['match_id' => $match->id],
                push: true,
            );
        }
    }
}
