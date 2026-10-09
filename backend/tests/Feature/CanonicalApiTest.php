<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $roleName) {
            Role::create([
                'name' => $roleName,
                'display_name' => Role::defaultDisplayName($roleName),
            ]);
        }
    }

    public function test_authenticated_user_can_register_and_refresh_a_device_token(): void
    {
        $user = $this->userWithRole('CUSTOMER');
        $payload = ['token' => 'fcm-token-1', 'platform' => 'ANDROID'];

        $this->actingAs($user)->postJson('/api/v1/notifications/device-token', $payload)
            ->assertOk()
            ->assertJsonPath('data.platform', 'ANDROID');
        $this->actingAs($user)->postJson('/api/v1/notifications/device-token', $payload)
            ->assertOk();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'is_active' => true,
        ]);
    }

    public function test_customer_payment_history_is_scoped_and_filterable(): void
    {
        $customerUser = $this->userWithRole('CUSTOMER');
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $otherCustomer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $customerOrder = $this->createOrder($customer, 'QRIS', 'PAID');
        $this->createOrder($otherCustomer, 'CASH', 'PAID');

        $this->actingAs($customerUser)
            ->getJson('/api/v1/customer/payments?payment_method=QRIS')
            ->assertOk()
            ->assertJsonPath('data.0.order_id', $customerOrder->id)
            ->assertJsonPath('data.0.payment_method', 'QRIS')
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($customerUser)
            ->getJson('/api/v1/customer/payments?payment_method=BANK_TRANSFER')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data yang dikirim tidak valid.');
    }

    public function test_payment_history_is_empty_when_customer_has_no_payment_record(): void
    {
        $customerUser = $this->userWithRole('CUSTOMER');
        Customer::create(['user_id' => $customerUser->id]);

        $this->actingAs($customerUser)
            ->getJson('/api/v1/customer/payments')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_courier_can_confirm_cash_using_canonical_order_endpoint(): void
    {
        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $courierUser = $this->userWithRole('COURIER');
        $courier = Courier::create(['user_id' => $courierUser->id]);
        $order = $this->createOrder($customer, 'CASH', 'PENDING', 'DALAM_PENGANTARAN');
        CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => 'ACTIVE',
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        $this->actingAs($courierUser)
            ->postJson('/api/v1/courier/orders/'.$order->id.'/payment-confirmation')
            ->assertOk()
            ->assertJsonPath('data.payment.payment_method', 'CASH')
            ->assertJsonPath('data.payment.payment_status', 'PAID');
    }

    public function test_customer_without_payment_record_sees_null_payment_on_order_resource(): void
    {
        $customerUser = $this->userWithRole('CUSTOMER');
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $order = $this->createOrder($customer, 'CASH', 'PENDING', 'MENUNGGU_PEMBAYARAN', false);

        $this->actingAs($customerUser)
            ->getJson('/api/v1/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.payment', null)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    private function userWithRole(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $roleName)->value('id'),
        ]);
    }

    private function createOrder(
        Customer $customer,
        string $paymentMethod,
        string $paymentStatus,
        string $orderStatus = 'MENUNGGU_PEMBAYARAN',
        bool $withPayment = true,
    ): Order {
        $order = $customer->orders()->create([
            'order_status' => $orderStatus,
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);

        if ($withPayment) {
            Payment::create([
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => 100,
            ]);
        }

        return $order->fresh('payment');
    }
}
