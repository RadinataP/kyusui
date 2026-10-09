<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris dashboard untuk customer, owner, dan courier.
 *
 * `line_total` memakai nama canonical dari spec 06 section 7.4 (audit P3-11).
 *
 * Key baris `status` sengaja dipertahankan sebagai `status`, bukan
 * `order_status`, karena dashboard bukan bagian dari katalog spec 06 section 30
 * dan klien aktif sudah memparsing key tersebut. Dokumentasi deviations
 * dicatat di laporan Phase B.
 */
class DashboardOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->order_status,
            'subtotal_amount' => $this->subtotal_amount,
            'total_amount' => $this->total_amount,
            'delivery_address' => $this->delivery_address,
            'payment' => $this->relationLoaded('payment') && $this->payment !== null ? [
                'payment_method' => $this->payment->payment_method,
                'payment_status' => $this->payment->payment_status,
            ] : null,
            'items' => $this->relationLoaded('items')
                ? $this->items->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ])->values()
                : [],
            'assignment' => $this->relationLoaded('assignments')
                ? $this->assignments->sortByDesc('id')->first()?->only(['id', 'status'])
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
