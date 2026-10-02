<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): array
    {
        return [
            'data' => ['user' => new UserResource($request->user()->load(['role', 'customer', 'owner', 'courier']))],
            'message' => 'Data profil berhasil diambil.',
        ];
    }

    public function update(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'vehicle' => ['sometimes', 'nullable', 'string', 'max:120'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);
        $user = $request->user();
        $user->update(array_filter([
            'name' => $validated['name'] ?? null,
            'password' => $validated['password'] ?? null,
        ], static fn ($value): bool => $value !== null));

        if ($user->customer) {
            $user->customer->update(array_intersect_key($validated, array_flip(['phone', 'address'])));
        }
        if ($user->courier) {
            $user->courier->update(array_intersect_key($validated, array_flip(['phone', 'vehicle'])));
        }

        return ['data' => ['user' => new UserResource($user->fresh()->load(['role', 'customer', 'owner', 'courier']))], 'message' => 'Profil berhasil diperbarui.'];
    }
}
