<?php

namespace App\Jobs;

use App\Models\Participant;
use App\Models\WorldMatch;
use App\Notifications\MatchReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendMatchRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $matches = WorldMatch::query()
            ->where('status', 'scheduled')
            ->where('reminder_sent', false)
            ->whereBetween('kickoff_at', [
                Carbon::now()->addMinutes(58),
                Carbon::now()->addMinutes(62),
            ])
            ->get();

        foreach ($matches as $match) {
            $participants = Participant::query()
                ->where('eliminated', false)
                ->where('sms_notifications', true)
                ->whereNotNull('phone')
                ->whereDoesntHave('bets', fn ($q) => $q->where('match_id', $match->id))
                ->get();

            foreach ($participants as $participant) {
                $participant->notify(new MatchReminderNotification($match));
            }

            $match->update(['reminder_sent' => true]);
        }
    }
}
