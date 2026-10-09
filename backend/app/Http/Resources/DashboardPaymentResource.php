<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baris dashboard untuk owner.
 *
 * `amount` memakai nama canonical dari `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md`
 * section 7.5 karena baris ini adalah payment, bukan order (audit P3-10).
 */
class DashboardPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'amount' => $this->amount,
            'order' => $this->relationLoaded('order') && $this->order !== null ? [
                'id' => $this->order->id,
                'total_amount' => $this->order->total_amount,
                'customer_name' => $this->order->customer?->user?->name,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
