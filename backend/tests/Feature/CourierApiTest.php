<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        }
    }

    public function test_courier_only_sees_own_assignments(): void
    {
        [$courierUser, $courier] = $this->courier();
        [, $otherCourier] = $this->courier('other@example.com');
        $own = $this->assignment($courier, 'CASH');
        $other = $this->assignment($otherCourier, 'CASH');

        $response = $this->actingAs($courierUser)->getJson('/api/v1/courier/assignments');

        $response->assertOk()->assertJsonPath('message', 'Data pengantaran berhasil diambil.');
        $this->assertCount(1, $response->json('data'));
        $this->actingAs($courierUser)->getJson('/api/v1/courier/assignments/'.$other->id)->assertForbidden();
        $this->assertNotSame($own->id, $other->id);
    }

    public function test_courier_can_start_assigned_delivery_and_order_transitions(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ASSIGNED, OrderStatus::DITUGASKAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('message', 'Pengantaran berhasil dimulai.')
            ->assertJsonPath('data.status', AssignmentStatus::ACTIVE->value);

        $this->assertDatabaseHas('orders', ['id' => $assignment->order_id, 'status' => OrderStatus::DALAM_PENGANTARAN->value]);
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $assignment->order_id, 'from_status' => OrderStatus::DITUGASKAN->value, 'to_status' => OrderStatus::DALAM_PENGANTARAN->value]);
    }

    public function test_start_rejects_invalid_assignment_state(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/start')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pengantaran tidak dapat dimulai karena status pengantaran tidak sesuai.']);
    }

    public function test_location_requires_active_assignment_and_uses_authenticated_courier(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ASSIGNED, OrderStatus::DITUGASKAN);
        $payload = ['latitude' => -6.2, 'longitude' => 106.8, 'accuracy_meters' => 5];

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', $payload)
            ->assertStatus(409)
            ->assertJson(['message' => 'Lokasi belum dapat diperbarui karena pengantaran belum aktif.']);

        $assignment->update(['status' => AssignmentStatus::ACTIVE]);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Lokasi berhasil diperbarui.');
        $this->assertDatabaseHas('courier_locations', ['assignment_id' => $assignment->id, 'courier_id' => $courier->id]);
    }

    public function test_cash_confirmation_requires_cash_pending_payment_and_active_delivery(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'QRIS', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pembayaran ini bukan pembayaran tunai.']);

        $assignment->order->payment->update(['method' => 'CASH']);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertOk()
            ->assertJsonPath('message', 'Pembayaran tunai berhasil dikonfirmasi.');
        $this->assertSame('PAID', $assignment->order->payment->fresh()->status);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pembayaran sudah lunas.']);
    }

    public function test_complete_requires_paid_payment_and_rejects_other_courier(): void
    {
        [$user, $courier] = $this->courier();
        [$otherUser, $otherCourier] = $this->courier('second@example.com');
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($otherUser)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pengantaran tidak dapat diselesaikan karena pembayaran belum lunas.']);

        $assignment->order->payment->update(['status' => 'PAID']);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')
            ->assertOk()
            ->assertJsonPath('message', 'Pengantaran berhasil diselesaikan.');
        $this->assertDatabaseHas('courier_assignments', ['id' => $assignment->id, 'status' => AssignmentStatus::COMPLETED->value]);
        $this->assertDatabaseHas('orders', ['id' => $assignment->order_id, 'status' => OrderStatus::SELESAI->value]);
        $this->assertNotNull($otherCourier->id);
    }

    private function courier(string $email = 'courier@example.com'): array
    {
        $role = Role::where('name', 'COURIER')->firstOrFail();
        $user = User::factory()->create(['email' => $email, 'role_id' => $role->id]);

        return [$user, Courier::create(['user_id' => $user->id])];
    }

    private function assignment(Courier $courier, string $method, AssignmentStatus $status = AssignmentStatus::ASSIGNED, OrderStatus $orderStatus = OrderStatus::DITUGASKAN): CourierAssignment
    {
        $customerRole = Role::where('name', 'CUSTOMER')->firstOrFail();
        $customerUser = User::factory()->create(['role_id' => $customerRole->id]);
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $order = Order::create(['customer_id' => $customer->id, 'status' => $orderStatus->value, 'subtotal' => 100, 'total' => 100, 'delivery_address' => 'Jl. Test']);
        Payment::create(['order_id' => $order->id, 'method' => $method, 'status' => 'PENDING']);

        return CourierAssignment::create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => $status, 'assigned_at' => now()]);
    }
}
