<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): array
    {
        $notifications = $request->user()->appNotifications()->latest('id')->paginate(20);

        return [
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
            'message' => 'Data notifikasi berhasil diambil.',
        ];
    }

    public function read(Request $request, int $notification): array
    {
        $item = $request->user()->appNotifications()->findOrFail($notification);
        $item->update(['read_at' => now()]);

        return ['data' => new NotificationResource($item->fresh()), 'message' => 'Notifikasi berhasil dibaca.'];
    }
}
