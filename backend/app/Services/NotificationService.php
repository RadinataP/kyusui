<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendToUser(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        Log::info('Notification created.', [
            'notification_id' => $notification->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        return $notification;
    }

    public function sendToRole(
        string $role,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): int
    {
        $userIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', $role))
            ->pluck('id');

        foreach ($userIds as $userId) {
            $this->sendToUser((int) $userId, $type, $title, $body, $data);
        }

        return $userIds->count();
    }
}
