<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function __construct(
        private readonly string $password,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🏆 Dołączyłeś do typowania Mundial 2026!')
            ->greeting('Hej ' . $notifiable->name . '!')
            ->line('Zostałeś dodany do prywatnej ligi typowania Mistrzostw Świata 2026.')
            ->line('**Twoje hasło:** `' . $this->password . '`')
            ->action('Zaloguj się i zacznij typować', url('/login'))
            ->line('Powodzenia! ⚽');
    }
}
