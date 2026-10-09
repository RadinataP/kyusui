<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner pending QRIS verification resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 12.1.
 * Berbeda dengan `PaymentResource` (section 7.5), daftar ini juga membawa
 * `order_number` dan ringkasan customer karena itu konteks kerja owner.
 */
class OwnerPendingPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('order') ? $this->order : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_number' => $order?->order_number,
            'customer' => $order === null || $order->customer === null ? null : [
                'id' => $order->customer->id,
                'name' => $order->customer->user?->name,
            ],
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'amount' => $this->amount,
            'proof' => new PaymentProofResource($this->proof_image),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
