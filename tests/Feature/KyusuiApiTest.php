<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KyusuiApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        } Product::create(['name' => 'Galon', 'price' => 15000, 'availability' => true]);
    }

    public function test_customer_can_register_and_create_order_with_database_price(): void
    {
        $register = $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'password', 'password_confirmation' => 'password']);
        $register->assertCreated();
        $token = $register->json('data.user.id') ? User::first()->createToken('test')->plainTextToken : '';
        $response = $this->withToken($token)->postJson('/api/v1/orders', ['items' => [['product_id' => 1, 'quantity' => 2]], 'delivery_address' => 'Jl. Test', 'payment_method' => 'CASH']);
        $response->assertOk()->assertJsonPath('data.total', '30000.00');
        $response->assertJsonPath('data.status', 'MENUNGGU_DIPROSES');
        $this->assertDatabaseHas('payment_status_histories', ['to_status' => 'PENDING']);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $role = Role::where('name', 'CUSTOMER')->first();
        $a = User::factory()->create(['role_id' => $role->id]);
        $b = User::factory()->create(['role_id' => $role->id]);
        $ca = Customer::create(['user_id' => $a->id]);
        $cb = Customer::create(['user_id' => $b->id]);
        $order = $ca->orders()->create(['status' => 'MENUNGGU_PEMBAYARAN', 'subtotal' => 1, 'total' => 1, 'delivery_address' => 'x']);
        $token = $b->createToken('test')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/orders/'.$order->id)->assertForbidden();
    }

    public function test_validation_errors_use_the_standard_indonesian_response(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('message', 'Data yang dikirim tidak valid.')
            ->assertJsonPath('errors.email.0', 'email wajib diisi.');
    }

    public function test_unauthenticated_api_request_uses_the_standard_response(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Anda harus login terlebih dahulu.']);
    }

    public function test_invalid_login_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Email atau password salah.']);
    }
}
