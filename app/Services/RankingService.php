<?php

namespace App\Services;

use App\Models\Participant;
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
        $participants = Participant::query()
            ->with(['bets.match', 'tiebreakerPick'])
            ->get();

        $ranked = $participants->map(function (Participant $participant) {
            return [
                'id' => $participant->id,
                'name' => $participant->name,
                'points' => $participant->pointsTotal(),
                'exact_scores' => $participant->exactScoreCount(),
                'group_correct' => $participant->groupCorrectCount(),
                'scorer_correct' => $participant->scorerCorrect(),
                'top_scorer' => $participant->tiebreakerPick?->top_scorer_name,
                'paid_entry' => $participant->paid_entry,
                'eliminated' => $participant->eliminated,
                'bets_count' => $participant->bets->count(),
                'missed_count' => $participant->missedMatchesCount(),
            ];
        });

        $sortFn = function ($a, $b) {
            foreach (['points', 'bets_count', 'exact_scores', 'group_correct'] as $key) {
                if ($b[$key] !== $a[$key]) return $b[$key] <=> $a[$key];
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
