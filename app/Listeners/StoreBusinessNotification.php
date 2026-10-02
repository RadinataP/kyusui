<?php

namespace App\Listeners;

use App\Events\BusinessActionOccurred;
use App\Models\Notification;

class StoreBusinessNotification
{
    public function handle(BusinessActionOccurred $event): void
    {
        Notification::create([
            'user_id' => $event->userId,
            'type' => $event->type,
            'title' => $event->title,
            'body' => $event->body,
            'data' => $event->data,
        ]);
    }
}
