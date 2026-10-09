<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'courier_name' => $this->courier?->user?->name,
            'order' => $this->relationLoaded('order') && $this->order !== null ? [
                'id' => $this->order->id,
                'total_amount' => $this->order->total_amount,
                'customer_name' => $this->order->customer?->user?->name,
                'delivery_address' => $this->order->delivery_address,
            ] : null,
        ];
    }
}
