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
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'delivery_address' => $this->delivery_address,
            'payment' => $this->payment ? [
                'method' => $this->payment->method,
                'status' => $this->payment->status,
            ] : null,
            'items' => $this->items->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'name' => $item->product?->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
            ])->values(),
            'assignment' => $this->assignments->sortByDesc('id')->first()?->only(['id', 'status']),
            'created_at' => $this->created_at,
        ];
    }
}
