<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::min(8)->letters()->numbers()->mixedCase(),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $user = DB::transaction(function () use ($validated): User {
                $customerRole = Role::where('name', 'CUSTOMER')->firstOrFail();

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role_id' => $customerRole->id,
                ]);

                Customer::create([
                    'user_id' => $user->id,
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);

                return $user;
            });

            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $token = $user->createToken(
                name: 'android',
                abilities: ['customer:*'],
                expiresAt: now()->addDays(30)
            );

            return response()->json([
                'data' => [
                    'user' => new UserResource($user->load(['role', 'customer'])),
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                ],
                'message' => 'Registrasi berhasil.',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Registrasi gagal. Silakan coba lagi.',
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::with(['role', 'customer', 'owner', 'courier'])
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            Log::warning('Failed login attempt', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $abilities = match ($user->role->name) {
            'CUSTOMER' => ['customer:*'],
            'OWNER' => ['owner:*'],
            'COURIER' => ['courier:*'],
            default => [],
        };

        $token = $user->createToken(
            name: 'android',
            abilities: $abilities,
            expiresAt: now()->addDays(30)
        );

        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'role' => $user->role->name,
        ]);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ],
            'message' => 'Login berhasil.',
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $tokenId = $token->id;
            $token->delete();

            Log::info('User logged out', [
                'user_id' => $request->user()->id,
                'token_id' => $tokenId,
            ]);
        }

        return response()->json([
            'message' => 'Logout berhasil.',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'customer', 'owner', 'courier']);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
            ],
            'message' => 'Data pengguna berhasil dimuat.',
        ], 200);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $abilities = match ($user->role->name) {
            'CUSTOMER' => ['customer:*'],
            'OWNER' => ['owner:*'],
            'COURIER' => ['courier:*'],
            default => [],
        };

        $token = $user->createToken(
            name: 'android',
            abilities: $abilities,
            expiresAt: now()->addDays(30)
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ],
            'message' => 'Token berhasil diperbarui.',
        ], 200);
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $deletedCount = $user->tokens()->delete();

        Log::info('User logged out from all devices', [
            'user_id' => $user->id,
            'tokens_deleted' => $deletedCount,
        ]);

        return response()->json([
            'message' => 'Logout dari semua perangkat berhasil.',
        ], 200);
    }
}
