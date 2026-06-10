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
                'paid_entry' => $participant->paid_entry,
                'eliminated' => $participant->eliminated,
                'bets_count' => $participant->bets->count(),
                'missed_count' => $participant->missedMatchesCount(),
            ];
        });

        // Sort active participants first, then eliminated
        $active = $ranked->where('eliminated', false)->sortByDesc(function ($p) {
            return [
                $p['points'],
                $p['exact_scores'],
                $p['group_correct'],
                (int) $p['scorer_correct'],
            ];
        })->values();

        $eliminated = $ranked->where('eliminated', true)->sortByDesc(function ($p) {
            return [
                $p['points'],
                $p['exact_scores'],
                $p['group_correct'],
                (int) $p['scorer_correct'],
            ];
        })->values();

        return $active->concat($eliminated);
    }
}
