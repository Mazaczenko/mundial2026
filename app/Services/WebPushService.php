<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $this->webPush->setReuseVAPIDHeaders(true);
        $this->webPush->setDefaultOptions(['TTL' => 3600]);
    }

    /**
     * Send a push notification to all subscribers.
     */
    public function sendToAll(string $title, string $body, string $url = '/bets'): void
    {
        $subscriptions = PushSubscription::all();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode(compact('title', 'body', 'url'));

        foreach ($subscriptions as $sub) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'keys'            => [
                        'p256dh' => $sub->public_key,
                        'auth'   => $sub->auth_token,
                    ],
                ]),
                $payload,
            );
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('WebPush failed', [
                    'endpoint' => $report->getEndpoint(),
                    'reason'   => $report->getReason(),
                ]);
            }
        }
    }

    /**
     * Send to a specific participant's subscriptions.
     *
     * @param  int[]  $participantIds
     */
    public function sendToParticipants(array $participantIds, string $title, string $body, string $url = '/bets'): void
    {
        $subscriptions = PushSubscription::whereIn('participant_id', $participantIds)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode(compact('title', 'body', 'url'));

        foreach ($subscriptions as $sub) {
            $this->webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => [
                        'p256dh' => $sub->public_key,
                        'auth'   => $sub->auth_token,
                    ],
                ]),
                $payload,
            );
        }

        foreach ($this->webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
