<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer Profile Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.2.
 * Kolom database `customers.address` dipetakan ke field canonical
 * `default_address`.
 */
class CustomerProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $user?->name,
            'phone' => $this->phone,
            'email' => $user?->email,
            'default_address' => $this->address,
        ];
    }
}
