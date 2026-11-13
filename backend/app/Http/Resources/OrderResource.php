
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order Resource.
 *
 * Bentuk mengikuti docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md
 * section 7.4.
 *
 * Payment dan assignment bersifat nested.
 * Payment bernilai null apabila relasi sudah dimuat
 * tetapi belum memiliki record payment.
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'subtotal_amount' => $this->subtotal_amount,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,
            'order_status' => $this->order_status,

            'payment' => $this->whenLoaded(
                'payment',
                fn ($payment) => $payment === null ? null : [
                    'id' => $payment->id,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'amount' => $payment->amount,
                ]
            ),

            'assignment' => $this->whenLoaded(
                'assignments',
                fn ($assignments) => $this->latestAssignmentPayload($assignments)
            ),
        ];
    }

    /**
     * Mengambil assignment terakhir berdasarkan ID terbesar.
     *
     * @param \Illuminate\Database\Eloquent\Collection $assignments
     * @return array<string, mixed>|null
     */
    private function latestAssignmentPayload($assignments): ?array
    {
        $assignment = $assignments->sortByDesc('id')->first();

        if ($assignment === null) {
            return null;
        }

        $courier = $assignment->relationLoaded('courier')
            ? $assignment->courier
            : null;

        $courierUser = $courier !== null && $courier->relationLoaded('user')
            ? $courier->user
            : null;

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'courier' => $courier !== null ? [
                'id' => $courier->id,
                'name' => $courierUser?->name,
            ] : null,
        ];
    }
}
