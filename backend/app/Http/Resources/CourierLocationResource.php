<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Courier location resource.
 *
 * Bentuk mengikuti `docs/09_KYUSUI_TRACKING_SPECIFICATION.md` section 29.1
 * (`Response concept`), yang juga dipakai untuk `location` pada
 * `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.7.
 *
 * Kolom `courier_assignment_id` dan `courier_id` sengaja tidak dikembalikan.
 * Kedua kolom adalah foreign key internal yang tidak dibutuhkan klien; Android
 * membaca `assignment_id` dari `TrackingResource`, bukan dari location sample.
 * Sebelum resource ini, endpoint mengembalikan model Eloquent mentah sehingga
 * kedua kolom tersebut ikut terekspos.
 */
class CourierLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracy_meters,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
