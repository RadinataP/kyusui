<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.3.
 * Kolom database `availability` dipetakan ke field canonical `is_available`.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'is_available' => (bool) $this->availability,
        ];
    }
}
