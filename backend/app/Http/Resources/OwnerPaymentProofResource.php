<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Owner payment proof resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 12.2.
 *
 * Spec section 12.2 melarang proof menjadi public URL tanpa otorisasi.
 * Endpoint ini sudah dibatasi `role:OWNER`, dan url yang dikembalikan adalah
 * endpoint private file delivery milik backend sendiri, bukan path filesystem
 * dan bukan `Storage::url()` yang bersifat publik.
 *
 * `url` memakai flag `download=1` karena spec section 12.2 mengizinkan response
 * berupa "authorized file response". Tanpa flag tersebut endpoint ini hanya
 * mengembalikan metadata JSON, sehingga URL yang dikembalikan sebelumnya
 * bersifat self-referential dan tidak dapat dipakai mengambil bytes gambar.
 */
class OwnerPaymentProofResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_id' => (int) $this->order_id,
            'payment_id' => $this->id,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'proof' => [
                'available' => filled($this->proof_image),
                'url' => filled($this->proof_image)
                    ? route('api.v1.owner.orders.payment.proof', ['order' => $this->order_id]).'?download=1'
                    : null,
            ],
        ];
    }
}
