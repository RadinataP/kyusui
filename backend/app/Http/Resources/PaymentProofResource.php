<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bukti pembayaran QRIS.
 *
 * `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.5 menetapkan bentuk
 * resource proof hanya sebagai penanda ketersediaan:
 *
 * ```json
 * { "available": true }
 * ```
 *
 * URL proof tidak pernah ikut dikembalikan di sini. File proof disimpan pada
 * private disk dan hanya dapat diunduh melalui endpoint berotorisasi
 * `GET /owner/orders/{order}/payment/proof` (spec 06 section 12.2).
 */
class PaymentProofResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available' => filled($this->resource),
        ];
    }
}
