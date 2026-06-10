<?php

namespace App\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(
        private readonly SmsService $smsService,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone;

        if (empty($phone)) {
            return;
        }

        /** @var string $message */
        $message = $notification->toSms($notifiable);

        $this->smsService->send($phone, $message);
    }
}
