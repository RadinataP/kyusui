<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role?->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role ? ['name' => $this->role->name] : null,
            'profile' => match ($role) {
                'CUSTOMER' => $this->customer ? [
                    'id' => $this->customer->id,
                    'phone' => $this->customer->phone,
                    'address' => $this->customer->address,
                ] : null,
                'OWNER' => $this->owner ? ['id' => $this->owner->id] : null,
                'COURIER' => $this->courier ? [
                    'id' => $this->courier->id,
                    'phone' => $this->courier->phone,
                    'vehicle' => $this->courier->vehicle,
                ] : null,
                default => null,
            },
        ];
    }
}
