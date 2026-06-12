<?php

namespace App\Jobs;

use App\Models\WorldMatch;
use App\Services\BetService;
use App\Services\EliminationService;
use App\Services\EspnApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FetchLiveMatchScoresJob implements ShouldQueue
{
    use Queueable;

    public function handle(EspnApiService $espnApi, BetService $betService, EliminationService $eliminationService): void
    {
        $liveWindow = WorldMatch::where('status', '!=', 'finished')
            ->where('kickoff_at', '<=', Carbon::now()->addMinutes(5))
            ->where('kickoff_at', '>=', Carbon::now()->subMinutes(120))
            ->get();

        if ($liveWindow->isEmpty()) {
            return;
        }

        $today = Carbon::now()->format('Y-m-d');
        $espnDate = str_replace('-', '', $today);

        // Bust cache so we always get fresh live data
        Cache::forget("espn.scoreboard.{$espnDate}");

        $events = collect($espnApi->getEventsByDate($today));

        foreach ($liveWindow as $match) {
            $eventId = $match->espn_event_id ?? $this->resolveEventId($espnApi, $match, $today);

            if ($eventId === null) {
                continue;
            }

            if (! $match->espn_event_id) {
                $match->update(['espn_event_id' => $eventId]);
            }

            $event = $events->firstWhere('id', $eventId);

            if ($event === null) {
                continue;
            }

            $competitors = $event['competitions'][0]['competitors'] ?? [];
            $home = collect($competitors)->firstWhere('homeAway', 'home');
            $away = collect($competitors)->firstWhere('homeAway', 'away');
            $espnStatus = $event['status']['type']['name'] ?? '';

            $newStatus = match (true) {
                in_array($espnStatus, ['STATUS_FINAL', 'STATUS_FULL_TIME']) => 'finished',
                in_array($espnStatus, ['STATUS_IN_PROGRESS', 'STATUS_HALFTIME', 'STATUS_END_PERIOD']) => 'in_play',
                default => $match->status,
            };

            $scoreHome = $home !== null ? (int) ($home['score'] ?? 0) : $match->score_home;
            $scoreAway = $away !== null ? (int) ($away['score'] ?? 0) : $match->score_away;

            $match->update([
                'status'     => $newStatus,
                'score_home' => $scoreHome,
                'score_away' => $scoreAway,
            ]);

            if ($newStatus === 'finished' && $match->status !== 'finished') {
                (new FetchFinishedMatchResultsJob)->syncGoals($espnApi, $match);
                $match->refresh()->load('bets');
                $betService->resolveBets($match);
            }
        }

        if ($liveWindow->where('status', '!=', 'finished')->isNotEmpty()) {
            $eliminationService->checkAll();
        }
    }

    private function resolveEventId(EspnApiService $espnApi, WorldMatch $match, string $date): ?string
    {
        return $espnApi->findEventId($date, $match->home_team, $match->away_team);
    }
}
