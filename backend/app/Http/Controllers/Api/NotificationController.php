<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validasi opsional untuk filter
        $validated = $request->validate([
            'is_read' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = $user->appNotifications()->latest('created_at');

        // Filter berdasarkan status baca
        if (isset($validated['is_read'])) {
            if ($validated['is_read']) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        $notifications = $query->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->appNotifications()->whereNull('read_at')->count(), // Bonus UX
            ],
            'message' => 'Data notifikasi berhasil diambil.',
        ], 200);
    }

    /**
     * Mark a specific notification as read.
     */
    public function read(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Defense in Depth: Pastikan notifikasi milik user yang login
        abort_unless(
            $notification->user_id === $user->id,
            403,
            'Anda tidak memiliki akses ke notifikasi ini.'
        );

        // Hanya update jika belum dibaca
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);

            Log::info('Notification marked as read', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'data' => new NotificationResource($notification->fresh()),
            'message' => 'Notifikasi berhasil ditandai sebagai dibaca.',
        ], 200);
    }

    /**
     * Mark all user's notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $updatedCount = $user->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($updatedCount > 0) {
            Log::info('All notifications marked as read', [
                'user_id' => $user->id,
                'count' => $updatedCount,
            ]);
        }

        return response()->json([
            'data' => null,
            'message' => 'Semua notifikasi berhasil ditandai sebagai dibaca.',
        ], 200);
    }
}
