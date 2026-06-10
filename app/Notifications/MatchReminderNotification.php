<?php

namespace App\Notifications;

use App\Models\WorldMatch;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class MatchReminderNotification extends Notification
{
    public function __construct(
        private readonly WorldMatch $match,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): string
    {
        $appUrl = config('app.url');

        $kickoffWarsaw = Carbon::parse($this->match->kickoff_at)
            ->setTimezone('Europe/Warsaw');

        $time = $kickoffWarsaw->format('H:i');
        $home = $this->match->home_team;
        $away = $this->match->away_team;

        return "⚽ Mundial: {$home} vs {$away} dziś o {$time}.\nMasz jeszcze 1h na obstawienie!\n{$appUrl}/bets";
    }
}
