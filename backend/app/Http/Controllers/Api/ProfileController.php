<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * `GET /customer/profile` — spec 06 section 9.1 dan 7.2.
     */
    public function customerProfile(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 404, 'Profil customer tidak ditemukan.');

        return response()->json([
            'data' => new CustomerProfileResource($customer->load('user:id,name,email')),
            'message' => 'Data profil berhasil diambil.',
        ], 200);
    }

    /**
     * `PUT /customer/profile` — spec 06 section 9.2.
     */
    public function updateCustomerProfile(Request $request): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null, 404, 'Profil customer tidak ditemukan.');
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'default_address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user->update(array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
        ], static fn ($value): bool => $value !== null));

        $customer->update(array_filter([
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['default_address'] ?? null,
        ], static fn ($value): bool => $value !== null));

        return response()->json([
            'data' => new CustomerProfileResource(Customer::query()->findOrFail($customer->id)->load('user:id,name,email')),
            'message' => 'Profil berhasil diperbarui.',
        ], 200);
    }

    /**
     * Preserved endpoint: `GET /profile` untuk OWNER dan COURIER.
     *
     * Tidak ada di katalog spec 06 section 30, tetapi specification hanya
     * mendefinisikan profile untuk CUSTOMER (section 9.1 dan 9.2) dan endpoint
     * ini masih dipakai untuk mengelola profil owner dan courier.
     */
    public function show(Request $request): array
    {
        return [
            'data' => ['user' => new UserResource($request->user()->load(['role', 'customer', 'owner', 'courier']))],
            'message' => 'Data profil berhasil diambil.',
        ];
    }

    /**
     * Preserved endpoint: `PUT /profile` untuk OWNER dan COURIER.
     */
    public function update(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'vehicle' => ['sometimes', 'nullable', 'string', 'max:120'],
            'password' => ['sometimes', 'confirmed', Password::min(8)->letters()->numbers()->mixedCase()],
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
