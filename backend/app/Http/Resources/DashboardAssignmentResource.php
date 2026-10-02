<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'courier_name' => $this->courier?->user?->name,
            'order' => $this->whenLoaded('order', fn (): ?array => $this->order ? [
                'id' => $this->order->id,
                'total_amount' => $this->order->total,
                'customer_name' => $this->order->customer?->user?->name,
                'delivery_address' => $this->order->delivery_address,
            ] : null),
        ];
    }
}
