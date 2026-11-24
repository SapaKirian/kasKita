<?php

namespace App\Support;

use App\Models\UserNotification;

class Notifier
{
    /**
     * Membuat notifikasi untuk satu pengguna. Kegagalan tidak boleh
     * menggagalkan proses utama (pembayaran, setoran, dll).
     */
    public static function send(
        ?int $userId,
        string $title,
        string $message,
        string $type,
        ?int $referenceId = null
    ): void {
        if (!$userId) {
            return;
        }

        try {
            UserNotification::create([
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'notification_type' => $type,
                'reference_id' => $referenceId,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
