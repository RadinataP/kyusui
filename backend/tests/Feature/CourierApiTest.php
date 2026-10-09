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
            Role::create([
                'name' => $name,
                'display_name' => Role::defaultDisplayName($name),
            ]);
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
        $this->actingAs($courierUser)->getJson('/api/v1/courier/assignments/'.$own->id)->assertOk();
        $this->actingAs($courierUser)->getJson('/api/v1/courier/assignments/'.$other->id)->assertNotFound();
        $this->assertNotSame($own->id, $other->id);
    }

    public function test_assignment_payload_keeps_preserved_non_spec_key_names(): void
    {
        [$courierUser, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH');

        $response = $this->actingAs($courierUser)->getJson('/api/v1/courier/assignments/'.$assignment->id);

        $response->assertOk()
            ->assertJsonPath('data.order.status', OrderStatus::DITUGASKAN->value)
            ->assertJsonPath('data.order.subtotal', '100.00')
            ->assertJsonPath('data.order.total', '100.00')
            ->assertJsonPath('data.order.payment.method', 'CASH')
            ->assertJsonPath('data.order.payment.status', 'PENDING')
            ->assertJsonMissingPath('data.order.order_status')
            ->assertJsonMissingPath('data.order.total_amount');
    }

    public function test_courier_can_start_assigned_delivery_and_order_transitions(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ASSIGNED, OrderStatus::DITUGASKAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/start')
            ->assertOk()
            ->assertJsonPath('message', 'Pengantaran berhasil dimulai.')
            ->assertJsonPath('data.status', AssignmentStatus::ACTIVE->value);

        $this->assertDatabaseHas('orders', ['id' => $assignment->order_id, 'order_status' => OrderStatus::DALAM_PENGANTARAN->value]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $assignment->order_id,
            'from_status' => OrderStatus::DITUGASKAN->value,
            'to_status' => OrderStatus::DALAM_PENGANTARAN->value,
            'changed_by_user_id' => $user->id,
        ]);
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
        $assignment->order->update(['order_status' => OrderStatus::DALAM_PENGANTARAN->value]);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Lokasi berhasil diperbarui.');
        $this->assertDatabaseHas('courier_locations', [
            'courier_assignment_id' => $assignment->id,
            'courier_id' => $courier->id,
        ]);
    }

    // ------------------------------------------------------------------
    // C-11 — location sample harus memakai bentuk `data.location` sesuai
    // spec 09 §29.1 dan tidak boleh membocorkan foreign key internal.
    // ------------------------------------------------------------------

    public function test_location_response_uses_documented_shape_without_internal_foreign_keys(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', [
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy_meters' => 5,
            ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'accepted',
                    'location' => ['latitude', 'longitude', 'accuracy_meters', 'recorded_at'],
                ],
                'message',
            ]);

        $this->assertTrue($response->json('data.accepted'));

        $location = $response->json('data.location');
        $this->assertEqualsWithDelta(-6.2, (float) $location['latitude'], 0.0001);
        $this->assertEqualsWithDelta(106.8, (float) $location['longitude'], 0.0001);
        $this->assertNotNull($location['recorded_at']);

        // Foreign key internal tidak boleh ikut terekspos.
        $serialized = (string) json_encode($response->json());
        $this->assertStringNotContainsString('courier_assignment_id', $serialized);
        $this->assertStringNotContainsString('courier_id', $serialized);
        $this->assertArrayNotHasKey('id', $location);
    }

    public function test_location_response_matches_the_customer_tracking_location_shape(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $submitted = $this->actingAs($user)
            ->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', [
                'latitude' => -6.2,
                'longitude' => 106.8,
                'accuracy_meters' => 5,
            ])
            ->assertOk();

        $customerUser = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        Customer::query()->whereKey($assignment->order->customer_id)->update(['user_id' => $customerUser->id]);

        $tracked = $this->actingAs($customerUser)
            ->getJson('/api/v1/customer/orders/'.$assignment->order_id.'/tracking')
            ->assertOk();

        // Bentuk yang sama dipakai di spec 09 §29.1 dan spec 06 §7.7, jadi
        // klien tidak perlu dua bentuk location yang berbeda.
        $this->assertSame(
            array_keys($submitted->json('data.location')),
            array_keys($tracked->json('data.location')),
        );
    }

    public function test_cash_confirmation_requires_cash_pending_payment_and_active_delivery(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'QRIS', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pembayaran ini bukan pembayaran tunai.']);

        $assignment->order->payment->update(['payment_method' => 'CASH']);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertOk()
            ->assertJsonPath('message', 'Pembayaran tunai berhasil dikonfirmasi.');
        $this->assertSame('PAID', $assignment->order->payment->fresh()->payment_status);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pembayaran sudah lunas.']);
    }

    public function test_cash_confirmation_records_payment_status_history_with_actor(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertOk();

        $this->assertDatabaseHas('payment_status_histories', [
            'payment_id' => $assignment->order->payment->id,
            'from_status' => 'PENDING',
            'to_status' => 'PAID',
            'changed_by' => $user->id,
        ]);
    }

    public function test_complete_requires_paid_payment_and_rejects_other_courier(): void
    {
        [$user, $courier] = $this->courier();
        [$otherUser, $otherCourier] = $this->courier('second@example.com');
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        $this->actingAs($otherUser)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')->assertNotFound();
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')
            ->assertStatus(409)
            ->assertJson(['message' => 'Pengantaran tidak dapat diselesaikan karena pembayaran belum lunas.']);

        $assignment->order->payment->update(['payment_status' => 'PAID', 'verified_at' => now()]);
        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/complete')
            ->assertOk()
            ->assertJsonPath('message', 'Pengantaran berhasil diselesaikan.');
        $this->assertDatabaseHas('courier_assignments', ['id' => $assignment->id, 'status' => AssignmentStatus::COMPLETED->value]);
        $this->assertDatabaseHas('orders', [
            'id' => $assignment->order_id,
            'order_status' => OrderStatus::SELESAI->value,
        ]);
        $this->assertNotNull($otherCourier->id);
    }

    // ------------------------------------------------------------------
    // C-06 — konfirmasi CASH wajib memakai bentuk `data.payment` sesuai
    // spec 06 §14.2, bukan data datar.
    //
    // Spec 06 §14.2 Success:
    //   data.payment.{id, order_id, payment_method, payment_status, amount,
    //                  verified_by, verified_at}
    //
    // Kedua endpoint CASH yang masih hidup wajib mengembalikan bentuk yang
    // sama karena keduanya memanggil satu implementasi yang sama. CONFLICT-003
    // (endpoint mana yang canonical) belum memiliki ruling, jadi keduanya
    // tetap hidup dan tidak ada yang dihapus di phase ini.
    // ------------------------------------------------------------------

    public function test_cash_confirmation_returns_canonical_nested_payment_payload(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);
        $payment = $assignment->order->payment;

        $response = $this->actingAs($user)
            ->postJson('/api/v1/courier/assignments/'.$assignment->id.'/cash/confirm')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'payment' => [
                        'id',
                        'order_id',
                        'payment_method',
                        'payment_status',
                        'amount',
                        'verified_by',
                        'verified_at',
                    ],
                ],
                'message',
            ]);

        $payload = $response->json('data.payment');

        $this->assertSame($payment->id, $payload['id']);
        $this->assertSame($assignment->order_id, $payload['order_id']);
        $this->assertSame('CASH', $payload['payment_method']);
        $this->assertSame('PAID', $payload['payment_status']);
        $this->assertNotNull($payload['amount']);
        $this->assertSame($user->id, $payload['verified_by']);
        $this->assertNotNull($payload['verified_at']);

        // Bentuk lama tidak boleh masih tersedia di root `data`.
        $this->assertArrayNotHasKey('id', $response->json('data'));
        $this->assertArrayNotHasKey('payment_status', $response->json('data'));
        $this->assertArrayNotHasKey('payment_method', $response->json('data'));
    }

    public function test_both_live_cash_endpoints_return_the_same_canonical_payload(): void
    {
        [$user, $courier] = $this->courier();

        $first = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);
        $assignmentEndpoint = $this->actingAs($user)
            ->postJson('/api/v1/courier/assignments/'.$first->id.'/cash/confirm')
            ->assertOk();

        $second = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);
        $orderEndpoint = $this->actingAs($user)
            ->postJson('/api/v1/courier/orders/'.$second->order_id.'/payment-confirmation')
            ->assertOk();

        $this->assertSame(
            array_keys($assignmentEndpoint->json('data.payment')),
            array_keys($orderEndpoint->json('data.payment')),
        );
        $this->assertSame('PAID', $orderEndpoint->json('data.payment.payment_status'));
        $this->assertSame($user->id, $orderEndpoint->json('data.payment.verified_by'));
    }

    // ------------------------------------------------------------------
    // C-07 — active assignment conflict.
    //
    // Yang dikunci spesifikasi hanya "tidak terdapat active assignment
    // conflict" (spec 06 §10.4) dan "maximum one active assignment" untuk satu
    // order (spec 05 §Business Invariant, diperkuat UNIQUE(order_id) pada spec
    // 13_DB). Tidak ada spesifikasi yang menetapkan courier-side exclusivity
    // maupun kapan sebuah assignment membuat kurir
    // tidak tersedia — spec 06 §10.4 mendelegasikannya ke "domain rule" yang
    // tidak pernah didefinisikan. Karena itu C-07 DEFERRED.
    //
    // Test ini mengunci bagian yang memang ditegaskan spesifikasi: satu kurir
    // tidak boleh menjalankan dua pengantaran aktif bersamaan.
    // ------------------------------------------------------------------

    public function test_courier_cannot_start_a_second_concurrent_active_delivery(): void
    {
        [$user, $courier] = $this->courier();
        $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);
        $second = $this->assignment($courier, 'CASH', AssignmentStatus::ASSIGNED, OrderStatus::DITUGASKAN);

        $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$second->id.'/start')
            ->assertStatus(409)
            ->assertJson(['message' => 'Kurir sedang mengantarkan pesanan lain.']);

        // Tidak ada state yang berubah pada assignment maupun order kedua.
        $this->assertDatabaseHas('courier_assignments', [
            'id' => $second->id,
            'status' => AssignmentStatus::ASSIGNED->value,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $second->order_id,
            'order_status' => OrderStatus::DITUGASKAN->value,
        ]);
    }

    /**
     * Rate limiting pada location submission.
     *
     * Throttle: 120 req/menit per courier (berdasarkan throttle:120,1 di routes/api.php).
     * Client-side interval = 30s (COURIER_SUBMISSION_INTERVAL_MS), jadi 120/menit
     * memberi headroom 4x untuk retry/jitter. Test ini memverifikasi request di bawah limit = OK.
     */
    public function test_location_endpoint_respects_rate_limit_under_threshold(): void
    {
        [$user, $courier] = $this->courier();
        $assignment = $this->assignment($courier, 'CASH', AssignmentStatus::ACTIVE, OrderStatus::DALAM_PENGANTARAN);

        // Kirim 5 request dengan recorded_at yang terdistribusi 10 detik
        // untuk menghindari validasi kecepatan 150 km/h
        $baseTime = now()->subMinutes(5);
        for ($i = 0; $i < 5; $i++) {
            $recordedAt = $baseTime->copy()->addSeconds($i * 10)->toIso8601String();
            $this->actingAs($user)->postJson('/api/v1/courier/assignments/'.$assignment->id.'/location', [
                'latitude' => -6.2 + ($i * 0.0001),
                'longitude' => 106.8 + ($i * 0.0001),
                'accuracy_meters' => 5,
                'recorded_at' => $recordedAt,
            ])->assertOk();
        }

        $this->assertDatabaseCount('courier_locations', 5);
    }

    /**
     * Verifikasi route location memiliki middleware throttle.
     * Test fungsional 429 dihilangkan karena menguji Laravel built-in throttle
     * yang sudah teruji di framework. Yang penting: route terdaftar dengan throttle.
     */
    public function test_location_route_has_throttle_middleware(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('api.v1.courier.assignments.location');
        $this->assertNotNull($route, 'Route location harus terdaftar');

        $middlewares = $route->gatherMiddleware();
        $this->assertContains('throttle:120,1', $middlewares, 'Route location harus punya throttle:120,1');
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
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => $orderStatus->value,
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'payment_status' => 'PENDING',
            'amount' => 100,
        ]);

        return CourierAssignment::create(['order_id' => $order->id, 'courier_id' => $courier->id, 'status' => $status, 'assigned_at' => now()]);
    }
}
