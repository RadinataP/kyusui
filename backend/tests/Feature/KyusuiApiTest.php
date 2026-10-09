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
            Role::create([
                'name' => $name,
                'display_name' => Role::defaultDisplayName($name),
            ]);
        }
        Product::create(['name' => 'Galon', 'price' => 15000, 'availability' => true]);
    }

    public function test_customer_can_register_and_create_order_with_database_price(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'A',
            'email' => 'a@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);
        $register->assertCreated();
        $token = User::first()->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/customer/orders', [
            'items' => [['product_id' => 1, 'quantity' => 2]],
            'delivery_location' => [
                'latitude' => -6.2,
                'longitude' => 106.816666,
                'address' => 'Jl. Test',
            ],
            'payment_method' => 'CASH',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total_amount', '35000.00')
            ->assertJsonPath('data.order_status', 'MENUNGGU_PEMBAYARAN')
            ->assertJsonPath('data.items.0.product_name', 'Galon');
        $this->assertDatabaseHas('payment_status_histories', ['to_status' => 'PENDING']);
        $this->assertDatabaseHas('orders', ['order_status' => 'MENUNGGU_PEMBAYARAN', 'total_amount' => 35000]);
    }

    public function test_cash_payment_selection_moves_order_to_processing_queue(): void
    {
        [$user] = $this->customer();

        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'delivery_location' => ['latitude' => -6.2, 'longitude' => 106.816666, 'address' => 'Jl. Test'],
        ])->assertCreated()->json('data');

        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order['id'].'/payment', ['payment_method' => 'CASH'])
            ->assertCreated()
            ->assertJsonPath('data.payment.payment_status', 'PENDING');

        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'order_status' => 'MENUNGGU_DIPROSES',
        ]);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $role = Role::where('name', 'CUSTOMER')->first();
        $a = User::factory()->create(['role_id' => $role->id]);
        $b = User::factory()->create(['role_id' => $role->id]);
        $ca = Customer::create(['user_id' => $a->id]);
        Customer::create(['user_id' => $b->id]);
        $order = $ca->orders()->create([
            'order_status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal_amount' => 1,
            'total_amount' => 1,
            'delivery_address' => 'x',
            'placed_at' => now(),
        ]);
        $token = $b->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/customer/orders/'.$order->id)->assertNotFound();
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
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Anda harus login terlebih dahulu.']);
    }

    public function test_invalid_login_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Email atau password salah.']);
    }

    /**
     * @return array{0: User, 1: Customer}
     */
    private function customer(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);

        return [$user, Customer::create(['user_id' => $user->id])];
    }
}
