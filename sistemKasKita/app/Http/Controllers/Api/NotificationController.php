<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /notifications
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $items = UserNotification::where('user_id', $userId)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'message' => 'Notifikasi berhasil diambil.',
            'data' => [
                'unread' => UserNotification::where('user_id', $userId)->where('is_read', false)->count(),
                'items' => $items,
            ],
        ]);
    }

    // PATCH /notifications/{id}/read
    public function markRead(Request $request, int $id)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    // PATCH /notifications/read-all
    public function markAllRead(Request $request)
    {
        UserNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
