<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->whenLoaded('role', fn ($role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn ($customer): array => [
                'id' => $customer->id,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ]),
            'owner' => $this->whenLoaded('owner', fn ($owner): array => [
                'id' => $owner->id,
            ]),
            'courier' => $this->whenLoaded('courier', fn ($courier): array => [
                'id' => $courier->id,
                'phone' => $courier->phone,
                'vehicle' => $courier->vehicle,
            ]),
            'profile' => $this->when(
                $this->relationLoaded('role') && $this->role !== null,
                fn () => match ($this->role->name) {
                    'CUSTOMER' => $this->relationLoaded('customer') && $this->customer ? [
                        'id' => $this->customer->id,
                        'phone' => $this->customer->phone,
                        'address' => $this->customer->address,
                    ] : null,
                    'OWNER' => $this->relationLoaded('owner') && $this->owner ? ['id' => $this->owner->id] : null,
                    'COURIER' => $this->relationLoaded('courier') && $this->courier ? [
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
}
