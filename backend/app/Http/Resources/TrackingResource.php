<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tracking Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.7
 * dan kontrak integrasi Android (`CustomerTrackingDto` + `CourierLocationDto`).
 *
 * Field yang dikembalikan:
 * - `id`, `order_id`, `courier_id`, `status`, `assigned_at`, `started_at`, `completed_at` — metadata assignment
 * - `locations` — array lokasi (terbaru dulu, maksimal 10), setiap item:
 *   {id, assignment_id, courier_id, latitude, longitude, accuracy_meters, recorded_at, idempotency_key}
 * - `location` — alias untuk `locations[0]` (kompatibilitas mundur dengan spec 7.7 / courier location submission response)
 *
 * Catatan:
 * - `courier` object TIDAK dikembalikan; gunakan `courier_id` (flat) sesuai `CustomerTrackingDto`.
 * - `locations` memuat field lengkap `CourierLocationDto` (termasuk id, assignment_id, courier_id, idempotency_key).
 * - `location` (singular) hanya 4 field untuk kompatibilitas dengan courier location submission response (spec 29.1).
 */
class TrackingResource extends JsonResource
{
    /**
     * Maksimal jumlah lokasi yang dikembalikan dalam riwayat.
     * Sesuai kontrak Android yang memuat `latest('recorded_at')->limit(10)`.
     */
    public const MAX_LOCATIONS = 10;

    public function toArray(Request $request): array
    {
        // Load locations jika belum (untuk performa, relasi sebaiknya di-eager-load di controller)
        $locations = $this->whenLoaded('locations', function () {
            return $this->locations
                ->sortByDesc('recorded_at')
                ->take(self::MAX_LOCATIONS)
                ->values()
                ->map(function ($loc) {
                    return [
                        'id' => $loc->id,
                        'assignment_id' => $loc->courier_assignment_id,
                        'courier_id' => $loc->courier_id,
                        'latitude' => $loc->latitude,
                        'longitude' => $loc->longitude,
                        'accuracy_meters' => $loc->accuracy_meters,
                        'recorded_at' => $loc->recorded_at,
                        'idempotency_key' => $loc->idempotency_key ?? null,
                    ];
                })
                ->all();
        }, []);

        $latestLocation = $locations[0] ?? null;

        // `location` (singular) hanya 4 field untuk kompatibilitas dengan
        // courier location submission response (spec 29.1 / section 7.7).
        $latestLocationSimple = $latestLocation ? [
            'latitude' => $latestLocation['latitude'],
            'longitude' => $latestLocation['longitude'],
            'accuracy_meters' => $latestLocation['accuracy_meters'],
            'recorded_at' => $latestLocation['recorded_at'],
        ] : null;

        return [
            // Assignment metadata (kanonik: field nama sama dengan kolom DB)
            'id' => $this->id,
            'order_id' => (int) $this->order_id,
            'courier_id' => (int) $this->courier_id,
            'status' => $this->status,
            'assigned_at' => $this->assigned_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,

            // Location history (terbaru dulu, maksimal 10) - field lengkap untuk CourierLocationDto
            'locations' => $locations,

            // Backward compatibility: single latest location (spec 7.7 / courier location submission)
            'location' => $latestLocationSimple,
        ];
    }
}
