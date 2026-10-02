<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        }
        Product::create(['name' => 'Galon', 'price' => 15000, 'availability' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('qris/active.png', 'image-content');
        BusinessSetting::create(['key' => 'qris_image_path', 'value' => 'qris/active.png']);
    }

    public function test_customer_uploads_qris_proof_with_metadata_and_idempotency(): void
    {
        Storage::fake('local');
        [$customerUser, $customer] = $this->customer();
        $order = $this->order($customer, 'QRIS');
        $payload = [
            'idempotency_key' => 'payment-proof-key',
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ];

        $first = $this->actingAs($customerUser)->post('/api/v1/orders/'.$order->id.'/qris-proof', $payload);
        $second = $this->actingAs($customerUser)->postJson('/api/v1/orders/'.$order->id.'/qris-proof', [
            'idempotency_key' => 'payment-proof-key',
        ]);

        $first->assertOk()->assertJsonPath('data.status', 'WAITING_VERIFICATION');
        $second->assertOk()->assertJsonPath('message', 'Bukti pembayaran dengan idempotency key tersebut sudah tersedia.');
        $this->assertDatabaseHas('payments', [
            'id' => $order->payment->id,
            'idempotency_key' => 'payment-proof-key',
            'proof_mime_type' => 'image/png',
        ]);
    }

    public function test_owner_can_approve_qris_payment(): void
    {
        Storage::fake('local');
        [$customerUser, $customer] = $this->customer();
        [$ownerUser] = $this->owner();
        $order = $this->order($customer, 'QRIS');
        $this->actingAs($customerUser)->post('/api/v1/orders/'.$order->id.'/qris-proof', [
            'idempotency_key' => 'verify-key',
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ])->assertOk();

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/payments/'.$order->payment->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'PAID');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'MENUNGGU_DIPROSES']);
    }

    public function test_active_qris_response_does_not_expose_internal_path(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('qris/active.png', 'image-content');
        [$customerUser] = $this->customer();

        $this->actingAs($customerUser)->getJson('/api/v1/payment/qris')
            ->assertOk()
            ->assertJsonStructure(['data' => ['image_url'], 'message'])
            ->assertJsonMissingPath('data.path');

        $imageResponse = $this->actingAs($customerUser)->get('/api/v1/payment/qris/image');
        $imageResponse->assertOk();
        $this->actingAs($customerUser)
            ->withHeader('If-None-Match', $imageResponse->headers->get('ETag'))
            ->get('/api/v1/payment/qris/image')
            ->assertStatus(304);
    }

    private function customer(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $customer = Customer::create(['user_id' => $user->id]);

        return [$user, $customer];
    }

    private function owner(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'OWNER')->value('id')]);

        return [$user];
    }

    private function order(Customer $customer, string $method): Order
    {
        $order = $customer->orders()->create([
            'status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal' => 15000,
            'delivery_fee' => 5000,
            'total' => 20000,
            'delivery_address' => 'Jl. Depot',
        ]);
        $order->items()->create([
            'product_id' => 1,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'status' => 'PENDING',
            'amount' => 20000,
        ]);

        return $order->fresh('payment');
    }
}
