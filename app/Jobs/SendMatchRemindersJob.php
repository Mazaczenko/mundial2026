<?php

namespace App\Jobs;

use App\Models\Participant;
use App\Models\WorldMatch;
use App\Notifications\MatchReminderNotification;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class SendMatchRemindersJob implements ShouldQueue
{
    use Queueable;

    public function handle(NotificationService $notificationService): void
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
                ->where('email_notifications', true)
                ->whereNotNull('email')
                ->whereDoesntHave('bets', fn ($q) => $q->where('match_id', $match->id))
                ->get();

            foreach ($participants as $participant) {
                $participant->notify(new MatchReminderNotification($match, $participant));
            }

            $notificationService->notify(
                $participants->pluck('id')->all(),
                'reminder',
                '⏰ Mecz za godzinę!',
                "{$match->home_team} – {$match->away_team}",
                '/bets',
                ['match_id' => $match->id],
            );

            $match->update(['reminder_sent' => true]);
        }
    }
}
