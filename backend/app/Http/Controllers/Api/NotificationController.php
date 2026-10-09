<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceTokenResource;
use App\Http\Resources\NotificationResource;
use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'max:20'],
        ]);

        $deviceToken = DB::transaction(function () use ($request, $validated): DeviceToken {
            $deviceToken = DeviceToken::query()
                ->where('token', $validated['token'])
                ->lockForUpdate()
                ->first();

            if ($deviceToken === null) {
                $deviceToken = DeviceToken::create([
                    'user_id' => $request->user()->id,
                    'token' => $validated['token'],
                    'platform' => strtolower($validated['platform']),
                    'is_active' => true,
                ]);
            } else {
                abort_unless(
                    $deviceToken->user_id === $request->user()->id || $deviceToken->is_active === false,
                    409,
                    'Device token sedang digunakan oleh akun lain.',
                );
                $deviceToken->update([
                    'user_id' => $request->user()->id,
                    'platform' => strtolower($validated['platform']),
                    'is_active' => true,
                ]);
            }

            Log::info('FCM device token registered.', [
                'user_id' => $request->user()->id,
                'device_token_id' => $deviceToken->id,
                'platform' => $deviceToken->platform,
            ]);

            return $deviceToken->fresh();
        });

        return response()->json([
            'data' => new DeviceTokenResource($deviceToken),
            'message' => 'Device token berhasil didaftarkan.',
        ], 200);
    }

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

        // Audit P1-10: `meta` collection dipatok pada tiga key canonical
        // (`current_page`, `per_page`, `total`) sesuai spec 06 section 4.4.
        // `last_page` dan `unread_count` tidak lagi dikirim; lihat
        // KYUSUI_BACKEND_REPAIR_REPORT.md bagian Android Integration Impact.
        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
            'message' => 'Data notifikasi berhasil diambil.',
        ], 200);
    }

    /**
     * Mark a specific notification as read.
     *
     * Canonical contract `PATCH /notifications/{notification}/read`
     * (spec 06 section 17 dan section 30). Backend dan Android sebelumnya
     * memakai POST; ini coordinated contract migration, bukan backend bug.
     */
    public function read(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        // Spec 06 section 5.6: notifikasi milik user lain harus 404, bukan 403,
        // supaya keberadaan notifikasi tidak bisa ditebak.
        abort_unless($notification->user_id === $user->id, 404);

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
