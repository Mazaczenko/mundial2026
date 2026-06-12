<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Support\Carbon;

class EliminationService
{
    public function checkAll(): void
    {
        $pastCount = $this->pastDeadlineCount();

        Participant::query()
            ->where('eliminated', false)
            ->each(function (Participant $participant) use ($pastCount) {
                if ($this->missedCount($participant, $pastCount) >= 3) {
                    $participant->update(['eliminated' => true]);
                }
            });
    }

    public function checkParticipant(Participant $participant): void
    {
        if ($participant->eliminated) {
            return;
        }

        if ($this->missedCount($participant) >= 3) {
            $participant->update(['eliminated' => true]);
        }
    }

    private function missedCount(Participant $participant, ?int $pastCount = null): int
    {
        $pastCount ??= $this->pastDeadlineCount();

        $bettedCount = $participant->bets()
            ->whereHas('match', fn ($q) => $q->where('kickoff_at', '<=', Carbon::now()->subHour()))
            ->count();

        return $pastCount - $bettedCount;
    }

    private function pastDeadlineCount(): int
    {
        return WorldMatch::where('kickoff_at', '<=', Carbon::now()->subHour())->count();
    }
}
