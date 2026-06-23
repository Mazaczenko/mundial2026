<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RankingService
{
    public function getRanking(): Collection
    {
        return $this->buildRanking(includeEliminated: true);
    }

    public function getFullRanking(): Collection
    {
        return $this->buildRanking(includeEliminated: true);
    }

    private function buildRanking(bool $includeEliminated): Collection
    {
        $pastMatchCount = WorldMatch::where('kickoff_at', '<=', Carbon::now()->subHour())->count();
        $now = Carbon::now();

        $participants = Participant::query()
            ->with(['bets.match', 'tiebreakerPick'])
            ->get();

        $ranked = $participants->map(function (Participant $participant) use ($pastMatchCount, $now) {
            $allBets = $participant->bets;
            $finishedBets = $allBets->filter(fn ($b) => $b->match?->status === 'finished');
            $correctFinished = $finishedBets->where('is_correct', true);

            $exactScores = $correctFinished->filter(
                fn ($b) => $b->predicted_home !== null
                    && $b->predicted_away !== null
                    && (int) $b->predicted_home === (int) $b->match->score_home
                    && (int) $b->predicted_away === (int) $b->match->score_away
            )->count();

            $groupCorrect = $correctFinished->filter(fn ($b) => $b->match?->stage === 'group')->count();
            $points = $correctFinished->count() + $exactScores;

            $pastBetsCount = $allBets->filter(
                fn ($b) => $b->match?->kickoff_at?->lte($now->copy()->subHour())
            )->count();

            return [
                'id' => $participant->id,
                'name' => $participant->name,
                'points' => $points,
                'exact_scores' => $exactScores,
                'group_correct' => $groupCorrect,
                'scorer_correct' => $participant->scorerCorrect(),
                'top_scorer' => $participant->tiebreakerPick?->top_scorer_name,
                'paid_entry' => $participant->paid_entry,
                'eliminated' => $participant->eliminated,
                'bets_count' => $allBets->count(),
                'missed_count' => $pastMatchCount - $pastBetsCount,
            ];
        });

        $sortFn = function ($a, $b) {
            foreach (['points', 'bets_count', 'exact_scores', 'group_correct'] as $key) {
                if ($b[$key] !== $a[$key]) {
                    return $b[$key] <=> $a[$key];
                }
            }
            if ($b['scorer_correct'] !== $a['scorer_correct']) {
                return (int) $b['scorer_correct'] <=> (int) $a['scorer_correct'];
            }

            return strcoll($a['name'], $b['name']);
        };

        $active = $ranked->where('eliminated', false)
            ->values()->sort($sortFn)->values();

        $eliminated = $ranked->where('eliminated', true)
            ->values()->sort($sortFn)->values();

        return $active->concat($eliminated);
    }
}
