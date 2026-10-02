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
            'method' => $this->method,
            'status' => $this->status,
            'amount' => $this->amount,
            'proof_path' => $this->proof_path ? asset('storage/'.$this->proof_path) : null,
            'order' => $this->whenLoaded('order', fn (): ?array => $this->order ? [
                'id' => $this->order->id,
                'total' => $this->order->total,
                'customer_name' => $this->order->customer?->user?->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
