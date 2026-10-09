<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Courier;
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

class OrderApiTest extends TestCase
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

        Product::create([
            'name' => 'Galon',
            'price' => 15000,
            'availability' => true,
        ]);
        BusinessSetting::query()->updateOrCreate(
            ['id' => BusinessSetting::SINGLETON_ID],
            ['qris_image' => 'qris/active.png'],
        );
    }

    public function test_order_creation_uses_server_side_pricing_and_assigns_order_number(): void
    {
        [$user] = $this->customer();

        $response = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 2]],
            'CASH',
        ));

        $response->assertCreated()
            ->assertJsonPath('data.subtotal_amount', '30000.00')
            ->assertJsonPath('data.delivery_fee', '5000.00')
            ->assertJsonPath('data.total_amount', '35000.00')
            ->assertJsonPath('data.order_status', 'MENUNGGU_PEMBAYARAN')
            ->assertJsonPath('data.items.0.product_name', 'Galon')
            ->assertJsonPath('data.items.0.unit_price', '15000.00')
            ->assertJsonPath('data.items.0.line_total', '30000.00')
            ->assertJsonPath('data.payment.payment_method', 'CASH')
            ->assertJsonPath('data.payment.payment_status', 'PENDING');

        $order = Order::firstOrFail();
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order->order_number);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 35000]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'MENUNGGU_PEMBAYARAN',
            'changed_by_user_id' => $user->id,
        ]);
    }

    public function test_order_creation_never_accepts_client_supplied_amount(): void
    {
        [$user] = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/customer/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1, 'unit_price' => 1, 'line_total' => 1]],
            'delivery_location' => ['latitude' => -6.2, 'longitude' => 106.816666, 'address' => 'Jl. Depot'],
            'payment_method' => 'CASH',
            'subtotal_amount' => 1,
            'total_amount' => 1,
        ])->assertCreated()->assertJsonPath('data.total_amount', '20000.00');

        $this->assertDatabaseMissing('orders', ['total_amount' => 1]);
    }

    public function test_order_creation_without_payment_method_leaves_payment_selection_to_the_payment_endpoint(): void
    {
        [$user] = $this->customer();

        $response = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
        ));

        $response->assertCreated()->assertJsonPath('data.payment', null);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('MENUNGGU_PEMBAYARAN', Order::firstOrFail()->order_status);
    }

    public function test_payment_selection_endpoint_creates_then_updates_a_single_payment_record(): void
    {
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
        ))->assertCreated()->json('data');

        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order['id'].'/payment', ['payment_method' => 'QRIS'])
            ->assertCreated()
            ->assertJsonPath('data.payment.payment_method', 'QRIS')
            ->assertJsonPath('data.payment.payment_status', 'PENDING');

        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order['id'].'/payment', ['payment_method' => 'CASH'])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_method', 'CASH');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'order_status' => 'MENUNGGU_DIPROSES']);
    }

    public function test_qris_payment_selection_is_rejected_when_qris_is_not_configured(): void
    {
        BusinessSetting::query()->update(['qris_image' => null]);
        [$user] = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->assertStatus(409)->assertJsonPath('message', 'Pembayaran QRIS belum tersedia.');
    }

    public function test_qris_proof_upload_transitions_payment_and_is_customer_scoped(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        [$otherUser] = $this->customer('other@example.com');
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');

        $this->actingAs($otherUser)
            ->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
                'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
            ])->assertNotFound();

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'WAITING_VERIFICATION')
            ->assertJsonPath('data.payment.proof.available', true);

        $payment = Payment::firstOrFail();
        $this->assertSame('WAITING_VERIFICATION', $payment->payment_status);
        $this->assertNotNull($payment->proof_image);
        $this->assertNull($payment->verified_by);
        $this->assertNull($payment->verified_at);
        $this->assertDatabaseHas('payment_status_histories', [
            'payment_id' => $payment->id,
            'from_status' => 'PENDING',
            'to_status' => 'WAITING_VERIFICATION',
        ]);
    }

    /**
     * 13_DB section 10.2: upload ulang menulis pada payment record yang sama,
     * bukan membuat record baru. Upload ulang hanya sah setelah owner
     * menolak proof sehingga payment kembali ke `PENDING`
     * (06 section 11.5 mensyaratkan `payment_status = PENDING`).
     */
    public function test_qris_proof_reupload_after_rejection_writes_the_same_payment_record(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        $ownerUser = User::factory()->create(['role_id' => Role::where('name', 'OWNER')->value('id')]);
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->assertCreated()->json('data');
        $paymentId = Payment::firstOrFail()->id;

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->image('first.png', 200, 200),
        ])->assertOk();

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->image('second.png', 200, 200),
        ])->assertStatus(409);

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order['id'].'/payment-verification', [
            'action' => 'REJECT',
        ])->assertOk();

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->image('second.png', 200, 200),
        ])->assertOk()->assertJsonPath('data.payment.payment_status', 'WAITING_VERIFICATION');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'payment_status' => 'WAITING_VERIFICATION']);
    }

    public function test_qris_proof_upload_rejects_cash_payment_and_non_image_payload(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'CASH',
        ))->json('data');

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->image('proof.png', 200, 200),
        ])->assertStatus(409)->assertJsonPath('message', 'Upload bukti hanya untuk pembayaran QRIS.');

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_order_creation_rejects_coordinates_outside_service_area(): void
    {
        [$user] = $this->customer();

        $this->actingAs($user)->postJson('/api/v1/customer/orders', [
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'delivery_location' => ['latitude' => 0, 'longitude' => 0, 'address' => 'Di luar area'],
            'payment_method' => 'CASH',
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_index_filters_by_canonical_order_status_and_is_customer_scoped(): void
    {
        [$user, $customer] = $this->customer();
        [, $otherCustomer] = $this->customer('other@example.com');
        $customer->orders()->create([
            'order_status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
        $customer->orders()->create([
            'order_status' => 'SELESAI',
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now()->subDay(),
        ]);
        $otherCustomer->orders()->create([
            'order_status' => 'SELESAI',
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Lain',
            'placed_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/customer/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.order_status', 'MENUNGGU_PEMBAYARAN');

        $this->actingAs($user)->getJson('/api/v1/customer/orders?order_status=SELESAI')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->getJson('/api/v1/customer/orders?order_status=UNKNOWN')
            ->assertUnprocessable();
    }

    public function test_order_tracking_returns_null_before_assignment_and_resource_shape_after_assignment(): void
    {
        [$user, $customer] = $this->customer();
        $order = $customer->orders()->create([
            'order_status' => 'DITUGASKAN',
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/customer/orders/'.$order->id.'/tracking')
            ->assertOk()
            ->assertJsonPath('data', null);

        [$courierUser] = $this->courier();
        $courier = Courier::create(['user_id' => $courierUser->id]);
        $order->assignments()->create([
            'courier_id' => $courier->id,
            'status' => 'ACTIVE',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/v1/customer/orders/'.$order->id.'/tracking')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'order_id', 'courier_id', 'status', 'assigned_at', 'started_at', 'completed_at', 'locations', 'location']]);
    }

    public function test_cancel_endpoint_allows_cancellation_only_on_menunggu_pembayaran(): void
    {
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');

        // Order dengan status MENUNGGU_PEMBAYARAN dapat dibatalkan
        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order['id'].'/cancel')
            ->assertOk()
            ->assertJsonPath('data.order_status', 'DIBATALKAN');

        // Pembatalan kedua ditolak karena status sudah DIBATALKAN
        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order['id'].'/cancel')
            ->assertStatus(409);
    }

    public function test_cancel_rejected_on_non_menunggu_pembayaran_statuses(): void
    {
        [$user, $customer] = $this->customer();
        $orderData = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');
        $order = Order::findOrFail($orderData['id']);

        // Test pada setiap status selain MENUNGGU_PEMBAYARAN
        $blockedStatuses = [
            'MENUNGGU_DIPROSES',
            'DIPROSES',
            'DITUGASKAN',
            'DALAM_PENGANTARAN',
            'SELESAI',
        ];

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach ($blockedStatuses as $status) {
            $order->update(['order_status' => $status]);

            $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order->id.'/cancel')
                ->assertStatus(409)
                ->assertJsonPath('message', 'Pesanan tidak dapat dibatalkan pada status saat ini.');

            // Status tidak berubah setelah penolakan
            $this->assertSame($status, $order->fresh()->order_status);
        }
    }

    public function test_cancel_requires_ownership_and_returns_404_for_other_customers(): void
    {
        [$user] = $this->customer();
        [$otherUser, $otherCustomer] = $this->customer('other@example.com');

        $orderData = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');
        $order = Order::findOrFail($orderData['id']);

        // Customer lain mencoba membatalkan order -> 404
        $this->actingAs($otherUser)->postJson('/api/v1/customer/orders/'.$order->id.'/cancel')
            ->assertNotFound();
    }

    public function test_cancel_requires_authentication(): void
    {
        [$user, $customer] = $this->customer();
        $product = Product::firstOrFail();
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal_amount' => 15000,
            'delivery_fee' => 5000,
            'total_amount' => 20000,
            'delivery_address' => 'Jl. Test',
            'delivery_latitude' => -6.2,
            'delivery_longitude' => 106.8,
            'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);

        // Tanpa autentikasi -> 401 (Sanctum authentication required)
        $response = $this->postJson('/api/v1/customer/orders/'.$order->id.'/cancel');
        
        $response->assertStatus(401);
    }

    public function test_cancel_does_not_change_status_when_rejected(): void
    {
        [$user, $customer] = $this->customer();
        $orderData = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'CASH',
        ))->json('data');
        $order = Order::findOrFail($orderData['id']);

        // Update status ke DIPROSES (bukan MENUNGGU_PEMBAYARAN)
        $order->update(['order_status' => 'DIPROSES']);
        $originalStatus = $order->fresh()->order_status;

        $this->actingAs($user)->postJson('/api/v1/customer/orders/'.$order->id.'/cancel')
            ->assertStatus(409);

        // Status tetap sama
        $this->assertSame($originalStatus, $order->fresh()->order_status);
    }

    // ------------------------------------------------------------------
    // C-02 — batas ukuran bukti QRIS harus 5 MB dan berasal dari
    // configuration constant (spec 06 §11.2, spec 08 §11.1, §48 keputusan 7,
    // test spec 11 PAY-005 "File > 5 MB rejected").
    //
    // Sebelumnya literal `max:2048` (2 MB) hard-coded di controller, sehingga
    // foto 3 MB yang lolos pre-check Android ditolak backend dengan 422.
    // ------------------------------------------------------------------

    public function test_proof_upload_limit_is_a_configured_five_megabyte_value(): void
    {
        $this->assertSame(5120, config('kyusui.max_kilobytes'));
        $this->assertSame('jpg,jpeg,png', config('kyusui.image_mimes'));
    }

    public function test_proof_above_five_megabytes_is_rejected(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');

        $tooLarge = $this->paddedPngUpload('too-large.png', 6000);
        $this->assertGreaterThan(config('kyusui.max_kilobytes'), $this->uploadedKilobytes($tooLarge));

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => $tooLarge,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('proof');

        $this->assertDatabaseMissing('payments', ['payment_status' => 'WAITING_VERIFICATION']);
    }

    public function test_proof_between_two_and_five_megabytes_is_accepted(): void
    {
        Storage::fake('local');
        [$user] = $this->customer();
        $order = $this->actingAs($user)->postJson('/api/v1/customer/orders', $this->orderPayload(
            [['product_id' => 1, 'quantity' => 1]],
            'QRIS',
        ))->json('data');

        // Sekitar 4.2 MB: ditolak oleh batas lama 2 MB, sah menurut spesifikasi.
        $accepted = $this->paddedPngUpload('accepted.png', 3000);
        $acceptedKilobytes = $this->uploadedKilobytes($accepted);
        $this->assertGreaterThan(2048, $acceptedKilobytes, 'fixture harus melewati batas lama 2 MB');
        $this->assertLessThanOrEqual(config('kyusui.max_kilobytes'), $acceptedKilobytes);

        $this->actingAs($user)->post('/api/v1/customer/orders/'.$order['id'].'/payment/proof', [
            'proof' => $accepted,
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'WAITING_VERIFICATION');

        $this->assertDatabaseHas('payments', ['payment_status' => 'WAITING_VERIFICATION']);
    }

    /**
     * Membuat PNG valid berukuran tertentu.
     *
     * Chunk IEND PNG berada di akhir file dan decoder mengabaikan byte setelahnya,
     * sehingga `getimagesize()` dan deteksi MIME tetap membaca header yang benar.
     * Padding dipakai agar test benar-benar menguji aturan `max:` tanpa bergantung
     * pada kompresi PNG yang tidak stabil.
     */
    private function paddedPngUpload(string $name, int $targetKilobytes): UploadedFile
    {
        $seed = UploadedFile::fake()->image('seed.png', 200, 200);
        $bytes = (string) file_get_contents($seed->getRealPath());

        $target = $targetKilobytes * 1024;
        if (strlen($bytes) < $target) {
            $bytes .= str_repeat("\0", $target - strlen($bytes));
        }

        $path = tempnam(sys_get_temp_dir(), 'kyusui-proof-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function uploadedKilobytes(UploadedFile $file): int
    {
        return (int) ceil($file->getSize() / 1024);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(array $items, ?string $paymentMethod = null): array
    {
        $payload = [
            'items' => $items,
            'delivery_location' => [
                'latitude' => -6.2,
                'longitude' => 106.816666,
                'address' => 'Jl. Depot',
            ],
        ];

        if ($paymentMethod !== null) {
            $payload['payment_method'] = $paymentMethod;
        }

        return $payload;
    }

    /**
     * @return array{0: User, 1: Customer}
     */
    private function customer(string $email = 'customer@example.com'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', 'CUSTOMER')->value('id'),
        ]);

        return [$user, Customer::create(['user_id' => $user->id])];
    }

    /**
     * @return array{0: User}
     */
    private function courier(string $email = 'courier@example.com'): array
    {
        return [User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', 'COURIER')->value('id'),
        ])];
    }
}
