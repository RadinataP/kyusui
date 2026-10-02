<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'subtotal_amount' => $this->subtotal,
            'total_amount' => $this->total,
            'delivery_address' => $this->delivery_address,
            'payment' => $this->payment ? [
                'payment_method' => $this->payment->method,
                'payment_status' => $this->payment->status,
            ] : null,
            'items' => $this->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total_amount' => $item->line_total,
            ])->values(),
            'assignment' => $this->assignments->sortByDesc('id')->first()?->only(['id', 'status']),
            'created_at' => $this->created_at,
        ];
    }
}
