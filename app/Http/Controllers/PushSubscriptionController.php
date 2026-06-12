<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'          => 'required|string',
            'keys.p256dh'       => 'required|string',
            'keys.auth'         => 'required|string',
        ]);

        /** @var Participant $user */
        $user = Auth::user();

        PushSubscription::updateOrCreate(
            [
                'participant_id' => $user->id,
                'endpoint_hash'  => hash('sha256', $data['endpoint']),
            ],
            [
                'endpoint'    => $data['endpoint'],
                'public_key'  => $data['keys']['p256dh'],
                'auth_token'  => $data['keys']['auth'],
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => 'required|string']);

        /** @var Participant $user */
        $user = Auth::user();

        PushSubscription::where('participant_id', $user->id)
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function vapidPublicKey(): JsonResponse
    {
        return response()->json(['key' => config('services.vapid.public_key')]);
    }
}
