<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\WorldMatch;

class EliminationService
{
    public function checkAll(): void
    {
        $finishedCount = WorldMatch::query()->finished()->count();

        Participant::query()
            ->where('eliminated', false)
            ->each(function (Participant $participant) use ($finishedCount) {
                $bettedCount = $participant->bets()
                    ->whereHas('match', fn ($q) => $q->finished())
                    ->count();

                $missed = $finishedCount - $bettedCount;

                if ($missed >= 3) {
                    $participant->update(['eliminated' => true]);
                }
            });
    }

    public function checkParticipant(Participant $participant): void
    {
        if ($participant->eliminated) {
            return;
        }

        $finishedCount = WorldMatch::query()->finished()->count();

        $bettedCount = $participant->bets()
            ->whereHas('match', fn ($q) => $q->finished())
            ->count();

        $missed = $finishedCount - $bettedCount;

        if ($missed >= 3) {
            $participant->update(['eliminated' => true]);
        }
    }
}
