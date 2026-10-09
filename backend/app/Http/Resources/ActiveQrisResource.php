<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Active QRIS Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.6
 * dan section 13.1.
 *
 * Spec section 7.6 membolehkan response memakai authorized temporary URL atau
 * mekanisme private file delivery yang ditentukan backend. Backend ini memakai
 * private file delivery pada disk `local` melalui endpoint berotorisasi
 * `GET /customer/payment/qris/image`, sehingga path filesystem tidak pernah
 * bocor ke client.
 *
 * `updated_by` hanya dikembalikan untuk OWNER sesuai spec section 13.1.
 */
class ActiveQrisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

        $payload = [
            'qris_image' => $setting->hasActiveQris() ? route('customer.qris.image') : null,
            'updated_at' => $setting->updated_at,
        ];

        if ($request->user()?->role?->name === 'OWNER') {
            $payload['updated_by'] = $setting->updated_by;
        }

        return $payload;
    }
}
