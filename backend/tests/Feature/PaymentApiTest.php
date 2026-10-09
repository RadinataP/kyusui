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
            Role::create([
                'name' => $name,
                'display_name' => Role::defaultDisplayName($name),
            ]);
        }
        Product::create(['name' => 'Galon', 'price' => 15000, 'availability' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('qris/active.png', 'image-content');
        BusinessSetting::query()->updateOrCreate(
            ['id' => BusinessSetting::SINGLETON_ID],
            ['qris_image' => 'qris/active.png'],
        );
    }

    public function test_customer_uploads_qris_proof_with_metadata_on_the_existing_payment_record(): void
    {
        [$customerUser, $customer] = $this->customer();
        $order = $this->order($customer, 'QRIS');
        $paymentId = $order->payment->id;

        $this->actingAs($customerUser)->post('/api/v1/customer/orders/'.$order->id.'/payment/proof', [
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'WAITING_VERIFICATION')
            ->assertJsonPath('data.payment.proof.available', true);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'payment_status' => 'WAITING_VERIFICATION',
            'proof_mime_type' => 'image/png',
        ]);
        $this->assertNotNull(Payment::findOrFail($paymentId)->proof_checksum);
        $this->assertNotNull(Payment::findOrFail($paymentId)->proof_size);
    }

    public function test_customer_can_read_the_payment_record_of_their_own_order(): void
    {
        [$customerUser, $customer] = $this->customer();
        $order = $this->order($customer, 'QRIS');

        $this->actingAs($customerUser)->getJson('/api/v1/customer/orders/'.$order->id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.id', $order->payment->id)
            ->assertJsonPath('data.payment_method', 'QRIS')
            ->assertJsonPath('data.payment_status', 'PENDING');
    }

    public function test_owner_can_approve_qris_payment(): void
    {
        [$customerUser, $customer] = $this->customer();
        [$ownerUser] = $this->owner();
        $order = $this->order($customer, 'QRIS');
        $this->actingAs($customerUser)->post('/api/v1/customer/orders/'.$order->id.'/payment/proof', [
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ])->assertOk();

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/payment-verification', [
            'action' => 'APPROVE',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'PAID');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => 'MENUNGGU_DIPROSES']);
    }

    public function test_active_qris_response_does_not_expose_internal_path(): void
    {
        [$customerUser] = $this->customer();

        $this->actingAs($customerUser)->getJson('/api/v1/customer/payment/qris')
            ->assertOk()
            ->assertJsonStructure(['data' => ['qris_image', 'updated_at'], 'message'])
            ->assertJsonPath('data.qris_image', url('/api/v1/customer/payment/qris/image'))
            ->assertJsonMissingPath('data.path');

        $imageResponse = $this->actingAs($customerUser)->get('/api/v1/customer/payment/qris/image');
        $imageResponse->assertOk();
        $this->actingAs($customerUser)
            ->withHeader('If-None-Match', $imageResponse->headers->get('ETag'))
            ->get('/api/v1/customer/payment/qris/image')
            ->assertStatus(304);
    }

    public function test_customer_qris_endpoints_return_404_when_qris_is_not_configured(): void
    {
        BusinessSetting::query()->update(['qris_image' => null]);
        [$customerUser] = $this->customer();

        $this->actingAs($customerUser)->getJson('/api/v1/customer/payment/qris')->assertNotFound();
        $this->actingAs($customerUser)->get('/api/v1/customer/payment/qris/image')->assertNotFound();
    }

    public function test_qris_image_endpoint_is_closed_to_unauthenticated_and_courier_role(): void
    {
        $this->get('/api/v1/customer/payment/qris/image')->assertUnauthorized();

        $courierUser = User::factory()->create(['role_id' => Role::where('name', 'COURIER')->value('id')]);
        $this->actingAs($courierUser)->get('/api/v1/customer/payment/qris/image')->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Customer}
     */
    private function customer(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $customer = Customer::create(['user_id' => $user->id]);

        return [$user, $customer];
    }

    /**
     * @return array{0: User}
     */
    private function owner(): array
    {
        return [User::factory()->create(['role_id' => Role::where('name', 'OWNER')->value('id')])];
    }

    private function order(Customer $customer, string $method): Order
    {
        $order = $customer->orders()->create([
            'order_status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal_amount' => 15000,
            'delivery_fee' => 5000,
            'total_amount' => 20000,
            'delivery_address' => 'Jl. Depot',
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => 1,
            'product_name' => 'Galon',
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'payment_status' => 'PENDING',
            'amount' => 20000,
        ]);

        return $order->fresh('payment');
    }
}
