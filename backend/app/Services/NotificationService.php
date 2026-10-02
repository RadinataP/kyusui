<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyDeliveryStarted(Order $order): void
    {
        $this->logDeliveryEvent('Delivery started notification queued.', $order);
    }

    public function notifyTrackingAvailable(Order $order): void
    {
        $this->logDeliveryEvent('Tracking available notification queued.', $order);
    }

    public function notifyOrderCompleted(Order $order): void
    {
        $this->logDeliveryEvent('Order completed notification queued.', $order);
    }

    private function logDeliveryEvent(string $message, Order $order): void
    {
        Log::info($message, ['order_id' => $order->id, 'customer_id' => $order->customer_id]);
    }
}
