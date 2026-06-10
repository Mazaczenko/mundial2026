<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function __construct(
        private readonly string $pin,
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

        return "🏆 Hej! Dołączyłeś do typowania Mundial 2026.\nZaloguj się: {$appUrl}\nTwój PIN: {$this->pin}";
    }
}
