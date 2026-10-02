<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $customerRole = Role::where('name', 'CUSTOMER')->firstOrFail();
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role_id' => $customerRole->id,
            ]);
            Customer::create(['user_id' => $user->id]);

            return $user;
        });

        return response()->json([
            'data' => ['user' => new UserResource($user->load(['role', 'customer']))],
            'message' => 'Registrasi berhasil.',
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::with(['role', 'customer', 'owner', 'courier'])
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        return [
            'data' => [
                'user' => new UserResource($user),
                'token' => $user->createToken('android')->plainTextToken,
            ],
            'message' => 'Login berhasil.',
        ];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return [
            'data' => ['user' => new UserResource($request->user()->load(['role', 'customer', 'owner', 'courier']))],
            'message' => 'Data pengguna berhasil dimuat.',
        ];
    }
}
