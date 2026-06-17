<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function index(): JsonResponse
    {
        $participantId = Auth::id();

        $notifications = AppNotification::where('participant_id', $participantId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'type', 'title', 'body', 'url', 'read_at', 'created_at']);

        $unreadCount = AppNotification::where('participant_id', $participantId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function readAll(): JsonResponse
    {
        AppNotification::where('participant_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markRead(AppNotification $notification): JsonResponse
    {
        if ($notification->participant_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
