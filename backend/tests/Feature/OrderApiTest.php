<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        }

        Product::create([
            'name' => 'Galon',
            'price' => 15000,
            'availability' => true,
        ]);
        BusinessSetting::create(['key' => 'qris_image_path', 'value' => 'qris/active.png']);
    }

    public function test_order_creation_is_idempotent_and_syncs_delivery_fee_and_payment_amount(): void
    {
        [$user] = $this->customer();
        $payload = [
            'items' => [['product_id' => 1, 'quantity' => 2]],
            'delivery_address' => 'Jl. Depot',
            'delivery_latitude' => -6.2,
            'delivery_longitude' => 106.816666,
            'payment_method' => 'CASH',
            'idempotency_key' => 'order-key-1',
        ];

        $first = $this->actingAs($user)->postJson('/api/v1/orders', $payload);
        $second = $this->actingAs($user)->postJson('/api/v1/orders', $payload);

        $first->assertCreated()->assertJsonPath('data.total', '35000.00');
        $second->assertOk()->assertJsonPath('message', 'Pesanan idempotensi sudah tersedia.');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('payments', ['amount' => 35000]);
    }

    public function test_qris_proof_upload_and_order_cancellation_are_customer_scoped(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'delivery_address' => 'Jl. Depot',
            'delivery_latitude' => -6.2,
            'delivery_longitude' => 106.816666,
            'payment_method' => 'QRIS',
        ])->json('data');

        $upload = $this->actingAs($user)->postJson('/api/v1/orders/'.$order['id'].'/qris-proof', [
            'idempotency_key' => 'proof-key-1',
            'proof_image' => UploadedFile::fake()->image('proof.png', 200, 200),
        ]);

        $upload->assertOk()->assertJsonPath('data.status', 'WAITING_VERIFICATION');
        $this->assertDatabaseHas('payments', ['order_id' => $order['id'], 'status' => 'WAITING_VERIFICATION']);

        $this->actingAs($user)->postJson('/api/v1/orders/'.$order['id'].'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'DIBATALKAN');
    }

    public function test_order_creation_rejects_coordinates_outside_service_area(): void
    {
        [$user] = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'delivery_address' => 'Di luar area',
            'delivery_latitude' => 0,
            'delivery_longitude' => 0,
            'payment_method' => 'CASH',
        ])->assertStatus(422);
    }

    private function customer(): array
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'CUSTOMER')->value('id'),
        ]);
        $customer = Customer::create(['user_id' => $user->id]);

        return [$user, $customer];
    }
}
