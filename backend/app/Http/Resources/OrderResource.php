<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.4.
 * Nama kolom database sudah sama dengan nama field canonical `order_number`,
 * `subtotal_amount`, `total_amount`, dan `order_status`
 * (`docs/05_KYUSUI_DATABASE_SCHEMA_REBUILT.md` section 11).
 *
 * Payment dan assignment bersifat nested. Nilai payment boleh `null` bila
 * payment record belum dibuat; customer memilih metode melalui
 * `POST /customer/orders/{order}/payment` (06 section 11.2 dan section 30).
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payment = $this->relationLoaded('payment') ? $this->payment : null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal_amount' => $this->subtotal_amount,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,
            'order_status' => $this->order_status,
            'payment' => $payment === null ? null : [
                'id' => $payment->id,
                'payment_method' => $payment->payment_method,
                'payment_status' => $payment->payment_status,
                'amount' => $payment->amount,
            ],
            'assignment' => $this->whenLoaded('assignments', fn (): ?array => $this->latestAssignmentPayload()),
        ];
    }

    /**
     * Assignment yang ditampilkan selalu assignment terakhir, mengikuti urutan
     * operasional `assignments DESC LIMIT 1` yang dipakai dashboard owner.
     *
     * @return array<string, mixed>|null
     */
    private function latestAssignmentPayload(): ?array
    {
        $assignment = $this->assignments->sortByDesc('id')->first();

        if ($assignment === null) {
            return null;
        }

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'courier' => $assignment->relationLoaded('courier') && $assignment->courier !== null
                ? [
                    'id' => $assignment->courier->id,
                    'name' => $assignment->courier->user?->name,
                ]
                : null,
        ];
    }
}
