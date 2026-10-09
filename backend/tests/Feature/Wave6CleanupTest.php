<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\CourierLocation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 5 dan Wave 6.
 *
 * Menguji perbaikan arsitektur, error handling, dan P2/P3 cleanup yang
 * dilakukan setelah Waves 1-4: konsolidasi middleware role, penghapusan
 * alias `/me`, validasi nomor telepon saat registrasi, konsistensi `409`
 * pada konfirmasi tunai, konsistensi `meta` collection, konsistensi
 * `latest_location`, dan invariant proof QRIS pada transisi pembayaran.
 */
class Wave6CleanupTest extends TestCase
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

    public function test_legacy_me_alias_is_gone_and_auth_me_remains_canonical(): void
    {
        // Audit P3-01: `GET /api/v1/me` adalah alias lama yang tidak ada di
        // katalog spec dan tidak dipanggil klien Android.
        $this->getJson('/api/v1/me')->assertNotFound();

        $this->actingAs($this->userWithRole('CUSTOMER'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role.name', 'CUSTOMER');
    }

    public function test_role_middleware_rejects_wrong_role_with_403_and_canonical_envelope(): void
    {
        // Audit P2-11: hanya ada satu middleware role. Penolakan harus lewat
        // renderer exception sehingga bentuknya sama dengan 401/404.
        $this->actingAs($this->userWithRole('CUSTOMER'))
            ->getJson('/api/v1/courier/assignments')
            ->assertForbidden()
            ->assertJson(['message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.'])
            ->assertJsonMissingPath('data');
    }

    public function test_registration_rejects_duplicate_phone_with_422_not_500(): void
    {
        // Audit P2-02: catch-all `\Exception` sebelumnya mengubah konflik
        // keunikan menjadi HTTP 500.
        $payload = [
            'name' => 'Customer Satu',
            'email' => 'satu@example.com',
            'password' => 'RahasiaKuat1',
            'password_confirmation' => 'RahasiaKuat1',
            'phone' => '08123456789',
        ];

        $this->postJson('/api/v1/auth/register', $payload)
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'satu@example.com');

        $this->postJson('/api/v1/auth/register', [
            ...$payload,
            'email' => 'dua@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Data yang dikirim tidak valid.')
            ->assertJsonStructure(['message', 'errors' => ['phone']]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_weak_password_with_the_same_policy_as_profile_update(): void
    {
        // Audit P3-04: `PUT /profile` sebelumnya hanya mewajibkan `min:8`,
        // tidak konsisten dengan `POST /auth/register`.
        $user = $this->userWithRole('CUSTOMER');
        Courier::query()->delete();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'password' => 'alllowercase',
                'password_confirmation' => 'alllowercase',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['password']]);
    }

    public function test_cash_confirmation_on_inactive_assignment_returns_409_not_404(): void
    {
        // Audit P2-16: endpoint order-centric sebelumnya memakai `firstOrFail`
        // sehingga konflik state muncul sebagai 404.
        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $courierUser = $this->userWithRole('COURIER');
        $courier = Courier::create(['user_id' => $courierUser->id]);
        $order = $this->createOrder($customer, 'CASH', 'PENDING', 'DALAM_PENGANTARAN');

        CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        $this->actingAs($courierUser)
            ->postJson("/api/v1/courier/orders/{$order->id}/payment-confirmation")
            ->assertStatus(409)
            ->assertJson(['message' => 'Pembayaran tunai hanya dapat dikonfirmasi saat pengantaran aktif.']);
    }

    public function test_cash_confirmation_on_foreign_order_returns_404(): void
    {
        // Spec 06 section 5.6: keberadaan resource milik kurir lain tidak
        // boleh bocor, jadi tetap 404.
        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $otherCourier = Courier::create(['user_id' => $this->userWithRole('COURIER')->id]);
        $order = $this->createOrder($customer, 'CASH', 'PENDING', 'DALAM_PENGANTARAN');

        CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $otherCourier->id,
            'status' => 'ACTIVE',
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        $intruder = Courier::create(['user_id' => $this->userWithRole('COURIER')->id]);

        $this->actingAs($intruder->user)
            ->postJson("/api/v1/courier/orders/{$order->id}/payment-confirmation")
            ->assertNotFound();
    }

    public function test_assignment_payload_returns_the_newest_location(): void
    {
        // Audit P2-06: `latest_location` memakai `->last()` pada relasi yang
        // tidak berurutan sehingga bisa mengembalikan baris tertua.
        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $courierUser = $this->userWithRole('COURIER');
        $courier = Courier::create(['user_id' => $courierUser->id]);
        $order = $this->createOrder($customer, 'CASH', 'PENDING', 'DALAM_PENGANTARAN');
        $assignment = CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => 'ACTIVE',
            'assigned_at' => now(),
            'started_at' => now(),
        ]);

        CourierLocation::create([
            'courier_assignment_id' => $assignment->id,
            'courier_id' => $courier->id,
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy_meters' => 5,
            'recorded_at' => now()->subHour(),
        ]);
        CourierLocation::create([
            'courier_assignment_id' => $assignment->id,
            'courier_id' => $courier->id,
            'latitude' => -6.9,
            'longitude' => 107.6,
            'accuracy_meters' => 8,
            'recorded_at' => now(),
        ]);

        $this->actingAs($courierUser)
            ->getJson("/api/v1/courier/assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('data.latest_location.latitude', '-6.9000000');
    }

    public function test_collection_meta_contains_only_the_three_canonical_keys(): void
    {
        // Audit P1-10: spec 06 section 4.4 mengunci `meta` pada
        // `current_page`, `per_page`, dan `total`.
        $user = $this->userWithRole('CUSTOMER');
        Customer::create(['user_id' => $user->id]);

        foreach ([
            '/api/v1/customer/orders',
            '/api/v1/customer/payments',
            '/api/v1/products',
        ] as $endpoint) {
            $response = $this->actingAs($user)->getJson($endpoint)->assertOk();

            $this->assertEqualsCanonicalizing(
                ['current_page', 'per_page', 'total'],
                array_keys($response->json('meta')),
                "Endpoint {$endpoint} memakai kunci meta di luar katalog spec."
            );
        }
    }

    public function test_qris_cannot_become_paid_without_proof_image(): void
    {
        // Audit P3-07: tabel transisi mengizinkan `PENDING -> PAID` untuk
        // kedua metode, tetapi QRIS wajib punya bukti transfer.
        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $order = $this->createOrder($customer, 'QRIS', 'PENDING', 'MENUNGGU_PEMBAYARAN');
        $payment = $order->payment;

        $this->expectException(\DomainException::class);

        $payment->transitionTo(PaymentStatus::PAID, $this->userWithRole('OWNER')->id);
    }

    public function test_cash_can_become_paid_without_proof_image(): void
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
            ->postJson("/api/v1/courier/assignments/{$order->assignments()->value('id')}/cash/confirm")
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'PAID');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_status' => 'PAID',
        ]);
    }

    public function test_changing_payment_method_deletes_the_stale_proof_file(): void
    {
        // Audit P2-08: file proof lama menggantung di storage setelah
        // customer mengganti metode pembayaran.
        Storage::fake('local');

        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $order = $this->createOrder($customer, 'QRIS', 'PENDING', 'MENUNGGU_PEMBAYARAN');
        $proofPath = 'qris-proof/original.png';
        Storage::disk('local')->put($proofPath, 'binary-proof');
        $order->payment->update(['proof_image' => $proofPath]);

        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/orders/{$order->id}/payment", ['payment_method' => 'CASH'])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_method', 'CASH');

        Storage::disk('local')->assertMissing($proofPath);
        $this->assertNull($order->payment->fresh()->proof_image);
    }

    public function test_proof_reupload_still_requires_pending_payment_status(): void
    {
        // Spec 06 section 11.5: upload proof hanya sah saat `PENDING`, dan
        // file lama harus tetap utuh ketika request ditolak.
        Storage::fake('local');

        $customer = Customer::create(['user_id' => $this->userWithRole('CUSTOMER')->id]);
        $order = $this->createOrder($customer, 'QRIS', 'WAITING_VERIFICATION', 'MENUNGGU_PEMBAYARAN');
        $proofPath = 'qris-proof/pending.png';
        Storage::disk('local')->put($proofPath, 'binary-proof');
        $order->payment->update(['proof_image' => $proofPath]);

        $this->actingAs($customer->user)
            ->post("/api/v1/customer/orders/{$order->id}/payment/proof", [
                'proof' => UploadedFile::fake()->image('proof.jpg', 400, 400),
            ], ['Accept' => 'application/json'])
            ->assertStatus(409);

        Storage::disk('local')->assertExists($proofPath);
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
    ): Order {
        $order = $customer->orders()->create([
            'order_status' => $orderStatus,
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'amount' => 100,
        ]);

        return $order->fresh('payment');
    }
}
