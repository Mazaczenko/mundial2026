<?php

namespace App\Notifications;

use App\Models\WorldMatch;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class MatchReminderNotification extends Notification
{
    public function __construct(
        private readonly WorldMatch $match,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $kickoff = Carbon::parse($this->match->kickoff_at)
            ->setTimezone('Europe/Warsaw')
            ->format('H:i');

        return (new MailMessage)
            ->subject("⚽ {$this->match->home_team} vs {$this->match->away_team} — obstawiasz?")
            ->greeting('Hej ' . $notifiable->name . '!')
            ->line("Mecz **{$this->match->home_team}** vs **{$this->match->away_team}** zaczyna się dziś o **{$kickoff}**.")
            ->line('Masz jeszcze 1 godzinę na obstawienie!')
            ->action('Obstaw teraz', url('/bets'))
            ->line('Mundial 2026 🏆');
    }
}
