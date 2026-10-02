<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        }
    }

    public function test_reject_resets_qris_idempotency_and_metadata(): void
    {
        Storage::fake('local');
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $payment = $order->payment;
        Storage::disk('local')->put($payment->proof_path, 'proof');

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/payments/'.$payment->id.'/reject')
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'PENDING',
            'proof_path' => null,
            'idempotency_key' => null,
            'proof_size' => null,
            'proof_mime_type' => null,
            'proof_checksum' => null,
        ]);
        Storage::disk('local')->assertMissing('payment-proofs/order-'.$order->id.'/proof.png');
    }

    public function test_approve_rejects_order_that_is_no_longer_waiting_for_payment(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::DIPROSES);
        $order->payment->update(['status' => 'WAITING_VERIFICATION']);

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/payments/'.$order->payment->id.'/approve')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Status pesanan tidak valid untuk verifikasi pembayaran.');
    }

    public function test_assign_rejects_courier_with_an_active_assignment(): void
    {
        [$ownerUser] = $this->owner();
        [, $courier] = $this->courier();
        $activeOrder = $this->order(OrderStatus::DALAM_PENGANTARAN);
        CourierAssignment::create([
            'order_id' => $activeOrder->id,
            'courier_id' => $courier->id,
            'status' => AssignmentStatus::ACTIVE->value,
            'assigned_at' => now(),
        ]);
        $waitingOrder = $this->order(OrderStatus::DIPROSES);

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$waitingOrder->id.'/assign-courier', [
            'courier_id' => $courier->id,
        ])->assertStatus(409);
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

    private function order(OrderStatus $status): Order
    {
        $customerUser = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $order = $customer->orders()->create([
            'status' => $status->value,
            'subtotal' => 100,
            'delivery_fee' => 0,
            'total' => 100,
            'delivery_address' => 'Jl. Test',
        ]);
        $order->payment()->create([
            'method' => 'QRIS',
            'status' => 'WAITING_VERIFICATION',
            'amount' => 100,
            'proof_path' => 'payment-proofs/order-'.$order->id.'/proof.png',
            'idempotency_key' => 'proof-'.$order->id,
            'proof_size' => 5,
            'proof_mime_type' => 'image/png',
            'proof_checksum' => 'checksum',
        ]);

        return $order->fresh('payment');
    }
}
