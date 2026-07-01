<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Validation\ValidationException;

class BetService
{
    public function __construct(
        private readonly EliminationService $eliminationService,
        private readonly RankingSnapshotService $snapshotService,
    ) {}

    public function placeBet(Participant $participant, array $data): Bet
    {
        $match = WorldMatch::findOrFail($data['match_id']);

        if (! $match->canBet()) {
            throw ValidationException::withMessages([
                'match_id' => 'Czas na obstawienie tego meczu minął.',
            ]);
        }

        // Group stage matches don't track exact scores
        if (! $match->isKnockout()) {
            $data['predicted_home'] = null;
            $data['predicted_away'] = null;
        }

        return Bet::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'match_id' => $match->id,
            ],
            [
                'prediction_1x2' => $data['prediction_1x2'],
                'predicted_home' => $data['predicted_home'] ?? null,
                'predicted_away' => $data['predicted_away'] ?? null,
            ]
        );
    }

    public function updateBet(Bet $bet, array $data): Bet
    {
        $bet->loadMissing('match');

        if (! $bet->match->canBet()) {
            throw ValidationException::withMessages([
                'match_id' => 'Czas na obstawienie tego meczu minął.',
            ]);
        }

        if (! $bet->match->isKnockout()) {
            $data['predicted_home'] = null;
            $data['predicted_away'] = null;
        }

        $bet->update([
            'prediction_1x2' => $data['prediction_1x2'],
            'predicted_home' => $data['predicted_home'] ?? null,
            'predicted_away' => $data['predicted_away'] ?? null,
        ]);

        return $bet;
    }

    public function resolveBets(WorldMatch $match): void
    {
        $result = $match->result1x2();

        foreach ($match->bets as $bet) {
            $correct1x2 = ($bet->prediction_1x2 === $result);
            $correctScore = $match->isKnockout()
                && $bet->predicted_home !== null
                && $bet->predicted_away !== null
                && (int) $bet->predicted_home === (int) $match->score_home
                && (int) $bet->predicted_away === (int) $match->score_away;

            $bet->is_correct = $correct1x2 || $correctScore;
            $bet->save();
        }

        $this->eliminationService->checkAll();
        $this->snapshotService->takeSnapshot($match);
    }
}
