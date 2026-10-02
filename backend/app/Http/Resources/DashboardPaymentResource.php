<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method' => $this->method,
            'payment_status' => $this->status,
            'total_amount' => $this->amount,
            'order' => $this->whenLoaded('order', fn (): ?array => $this->order ? [
                'id' => $this->order->id,
                'total_amount' => $this->order->total,
                'customer_name' => $this->order->customer?->user?->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
