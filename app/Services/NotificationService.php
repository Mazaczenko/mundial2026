<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Participant;

class NotificationService
{
    public function __construct(private readonly WebPushService $push) {}

    /**
     * Create in-app notifications + optional push for specific participants.
     *
     * @param  int[]  $participantIds
     * @param  array<string, mixed>  $meta
     */
    public function notify(
        array $participantIds,
        string $type,
        string $title,
        string $body,
        string $url = '/bets',
        array $meta = [],
        bool $push = true,
    ): void {
        foreach ($participantIds as $id) {
            AppNotification::create([
                'participant_id' => $id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'meta' => $meta,
            ]);
        }

        if ($push && ! empty($participantIds)) {
            $this->push->sendToParticipants($participantIds, $title, $body, $url);
        }
    }

    /**
     * Notify all active (non-eliminated) participants.
     *
     * @param  array<string, mixed>  $meta
     */
    public function notifyAll(
        string $type,
        string $title,
        string $body,
        string $url = '/bets',
        array $meta = [],
        bool $push = true,
    ): void {
        $ids = Participant::where('eliminated', false)->pluck('id')->all();
        $this->notify($ids, $type, $title, $body, $url, $meta, $push);
    }
}
