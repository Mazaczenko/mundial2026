<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\WorldMatch;
use Illuminate\Support\Carbon;

class EliminationService
{
    public function checkAll(): void
    {
        // eliminacje wyłączone
    }

    public function checkParticipant(Participant $participant): void
    {
        // eliminacje wyłączone
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
