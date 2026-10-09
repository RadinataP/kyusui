<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
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

    public function test_owner_dashboard_returns_business_metrics_and_dedicated_resources(): void
    {
        [, $customer] = $this->customer();
        [$ownerUser] = $this->owner();
        [, $courier] = $this->courier();
        $completedOrder = $this->order($customer, OrderStatus::SELESAI);
        $activeAssignment = CourierAssignment::create([
            'order_id' => $completedOrder->id,
            'courier_id' => $courier->id,
            'status' => AssignmentStatus::ACTIVE->value,
            'assigned_at' => now(),
        ]);
        $pendingOrder = $this->order($customer, OrderStatus::MENUNGGU_PEMBAYARAN);
        $pendingOrder->payment->update([
            'payment_method' => 'QRIS',
            'payment_status' => 'WAITING_VERIFICATION',
            'proof_image' => 'payment-proofs/order-'.$pendingOrder->id.'/proof.png',
        ]);

        $response = $this->actingAs($ownerUser)->getJson('/api/v1/dashboard/owner');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_orders', 2)
            ->assertJsonPath('data.summary.completed_orders', 1)
            ->assertJsonPath('data.summary.today_revenue', 20000)
            ->assertJsonPath('data.summary.waiting_qris_verification', 1)
            ->assertJsonPath('data.summary.active_deliveries', 1)
            ->assertJsonPath('data.pending_qris_payments.0.id', $pendingOrder->payment->id)
            ->assertJsonPath('data.active_deliveries_list.0.id', $activeAssignment->id);
    }

    public function test_dashboard_payment_resource_uses_canonical_amount_key(): void
    {
        [, $customer] = $this->customer();
        [$ownerUser] = $this->owner();
        $order = $this->order($customer, OrderStatus::MENUNGGU_PEMBAYARAN);
        $order->payment->update([
            'payment_method' => 'QRIS',
            'payment_status' => 'WAITING_VERIFICATION',
            'proof_image' => 'payment-proofs/order-'.$order->id.'/proof.png',
        ]);

        $this->actingAs($ownerUser)->getJson('/api/v1/dashboard/owner')
            ->assertOk()
            ->assertJsonPath('data.pending_qris_payments.0.amount', '20000.00')
            ->assertJsonPath('data.pending_qris_payments.0.payment_status', 'WAITING_VERIFICATION')
            ->assertJsonMissingPath('data.pending_qris_payments.0.total_amount');
    }

    public function test_customer_and_courier_dashboards_return_their_business_metrics(): void
    {
        [$customerUser, $customer] = $this->customer();
        [$courierUser, $courier] = $this->courier();
        $order = $this->order($customer, OrderStatus::SELESAI);
        CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => AssignmentStatus::COMPLETED->value,
            'assigned_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($customerUser)->getJson('/api/v1/dashboard/customer')
            ->assertOk()
            ->assertJsonPath('data.summary.total_completed_orders', 1)
            ->assertJsonPath('data.summary.total_spent', 20000)
            ->assertJsonPath('data.summary.favorite_product.name', 'Galon');
        $this->actingAs($courierUser)->getJson('/api/v1/dashboard/courier')
            ->assertOk()
            ->assertJsonPath('data.summary.today_deliveries', 1)
            ->assertJsonPath('data.summary.completed_deliveries', 1);
    }

    private function customer(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $customer = Customer::create(['user_id' => $user->id]);

        return [$user, $customer];
    }

    private function owner(): array
    {
        return [User::factory()->create(['role_id' => Role::where('name', 'OWNER')->value('id')])];
    }

    private function courier(): array
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'COURIER')->value('id')]);
        $courier = Courier::create(['user_id' => $user->id]);

        return [$user, $courier];
    }

    private function order(Customer $customer, OrderStatus $status): Order
    {
        $order = $customer->orders()->create([
            'order_status' => $status->value,
            'subtotal_amount' => 15000,
            'delivery_fee' => 5000,
            'total_amount' => 20000,
            'delivery_address' => 'Jl. Depot',
            'placed_at' => now(),
            'completed_at' => $status === OrderStatus::SELESAI ? now() : null,
        ]);
        $order->items()->create([
            'product_id' => 1,
            'product_name' => 'Galon',
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);
        $order->payment()->create([
            'payment_method' => 'CASH',
            'payment_status' => 'PENDING',
            'amount' => 20000,
        ]);

        return $order->fresh(['payment', 'assignments']);
    }
}
