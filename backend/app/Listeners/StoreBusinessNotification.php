<?php

namespace App\Listeners;

use App\Events\BusinessActionOccurred;
use App\Models\Notification;
use App\Services\FcmPushNotificationService;
use App\Services\NotificationService;

class StoreBusinessNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly FcmPushNotificationService $fcmPushNotificationService,
    ) {}

    public function handle(BusinessActionOccurred $event): void
    {
        $existingNotification = Notification::query()
            ->where('user_id', $event->userId)
            ->where('type', $event->type)
            ->when(
                isset($event->data['order_id']),
                fn ($query) => $query->whereJsonContains('data->order_id', $event->data['order_id']),
            )
            ->when(
                isset($event->data['assignment_id']),
                fn ($query) => $query->whereJsonContains('data->assignment_id', $event->data['assignment_id']),
            )
            ->when(
                isset($event->data['payment_id']),
                fn ($query) => $query->whereJsonContains('data->payment_id', $event->data['payment_id']),
            )
            ->exists();

        if ($existingNotification) {
            return;
        }

        $notification = $this->notificationService->sendToUser(
            userId: $event->userId,
            type: $event->type,
            title: $event->title,
            body: $event->body,
            data: $event->data,
        );

        $this->fcmPushNotificationService->send($notification);
    }
}
