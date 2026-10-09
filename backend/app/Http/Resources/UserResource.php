<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Resource.
 *
 * Bentuk mengikuti `docs/06_KYUSUI_API_SPECIFICATION_REBUILT.md` section 7.1.
 * `phone`, `status`, dan `role.display_name` ditambahkan untuk menutup audit
 * P3-12.
 *
 * `phone` dibaca dari profile per role (`customers.phone` / `couriers.phone`)
 * karena 05 section 6 menempatkan `users.phone` sebagai kolom opsional yang
 * belum dipakai; lihat catatan pada migration
 * `2026_10_03_000100_add_role_display_name_and_user_status`.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone(),
            'status' => $this->status,
            'role' => $this->whenLoaded('role', fn ($role): ?array => $role === null ? null : [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ]),
            'customer' => $this->whenLoaded('customer', fn ($customer): ?array => $customer === null ? null : [
                'id' => $customer->id,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ]),
            'owner' => $this->whenLoaded('owner', fn ($owner): ?array => $owner === null ? null : [
                'id' => $owner->id,
            ]),
            'courier' => $this->whenLoaded('courier', fn ($courier): ?array => $courier === null ? null : [
                'id' => $courier->id,
                'phone' => $courier->phone,
                'vehicle' => $courier->vehicle,
            ]),
            'profile' => $this->when(
                $this->relationLoaded('role') && $this->role !== null,
                fn (): ?array => match ($this->role->name) {
                    'CUSTOMER' => $this->relationLoaded('customer') && $this->customer !== null ? [
                        'id' => $this->customer->id,
                        'phone' => $this->customer->phone,
                        'address' => $this->customer->address,
                    ] : null,
                    'OWNER' => $this->relationLoaded('owner') && $this->owner !== null ? ['id' => $this->owner->id] : null,
                    'COURIER' => $this->relationLoaded('courier') && $this->courier !== null ? [
                        'id' => $this->courier->id,
                        'phone' => $this->courier->phone,
                        'vehicle' => $this->courier->vehicle,
                    ] : null,
                    default => null,
                },
            ),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Nomor kontak mengikuti profile per role, bukan `users.phone`.
     */
    private function phone(): ?string
    {
        return $this->relationLoaded('customer') && $this->customer !== null
            ? $this->customer->phone
            : ($this->relationLoaded('courier') && $this->courier !== null ? $this->courier->phone : null);
    }
}
