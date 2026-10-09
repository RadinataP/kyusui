<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Order Resource untuk owner.
 *
 * Spec 06 section 7.4 menjadi bentuk dasar, lalu section 10.2 menambahkan
 * konteks yang dibutuhkan owner: customer, lokasi pengantaran, dan assignment
 * beserta courier dan waktunya.
 */
class OwnerOrderResource extends OrderResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'customer' => $this->whenLoaded('customer', fn () => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->user?->name,
                'phone' => $this->customer->phone,
            ]),
            'delivery_location' => [
                'latitude' => $this->delivery_latitude === null ? null : (float) $this->delivery_latitude,
                'longitude' => $this->delivery_longitude === null ? null : (float) $this->delivery_longitude,
                'address' => $this->delivery_address,
            ],
            'assignment' => $this->whenLoaded('assignments', fn () => $this->latestOwnerAssignmentPayload()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestOwnerAssignmentPayload(): ?array
    {
        $assignment = $this->assignments->sortByDesc('id')->first();

        if ($assignment === null) {
            return null;
        }

        return [
            'id' => $assignment->id,
            'courier_id' => $assignment->courier_id,
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at,
            'started_at' => $assignment->started_at,
            'completed_at' => $assignment->completed_at,
            'courier' => $assignment->relationLoaded('courier') && $assignment->courier !== null
                ? [
                    'id' => $assignment->courier->id,
                    'name' => $assignment->courier->user?->name,
                ]
                : null,
        ];
    }
}
