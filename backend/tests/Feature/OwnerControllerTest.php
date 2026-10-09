<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\BusinessSetting;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create([
                'name' => $name,
                'display_name' => Role::defaultDisplayName($name),
            ]);
        }

        BusinessSetting::query()->updateOrCreate(
            ['id' => BusinessSetting::SINGLETON_ID],
            ['qris_image' => 'qris/active.png'],
        );
    }

    public function test_reject_resets_qris_proof_metadata_and_removes_file(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $payment = $order->payment;
        Storage::disk('local')->put($payment->proof_image, 'proof');

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/payment-verification', [
            'action' => 'REJECT',
            'note' => 'Bukti tidak terbaca.',
        ])->assertOk()->assertJsonPath('data.payment.payment_status', 'PENDING');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'PENDING',
            'proof_image' => null,
            'proof_size' => null,
            'proof_mime_type' => null,
            'proof_checksum' => null,
            'verified_by' => null,
            'verified_at' => null,
        ]);
        Storage::disk('local')->assertMissing($payment->proof_image);
        $this->assertDatabaseHas('payment_status_histories', [
            'payment_id' => $payment->id,
            'from_status' => 'WAITING_VERIFICATION',
            'to_status' => 'PENDING',
            'changed_by' => $ownerUser->id,
        ]);
    }

    public function test_approve_marks_payment_paid_and_moves_order_to_processing_queue(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $payment = $order->payment;
        Storage::disk('local')->put($payment->proof_image, 'proof');

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/payment-verification', [
            'action' => 'APPROVE',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.payment_status', 'PAID')
            ->assertJsonPath('data.payment.verified_by', $ownerUser->id);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'PAID',
            'verified_by' => $ownerUser->id,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => OrderStatus::MENUNGGU_DIPROSES->value]);
    }

    public function test_approve_rejects_order_that_is_no_longer_waiting_for_payment(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::DIPROSES);
        Storage::disk('local')->put($order->payment->proof_image, 'proof');

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/payment-verification', [
            'action' => 'APPROVE',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Status pesanan tidak valid untuk verifikasi pembayaran.');
    }

    public function test_payment_verification_rejects_missing_proof_file(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/payment-verification', [
            'action' => 'APPROVE',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'File bukti pembayaran tidak ditemukan di server.');
    }

    public function test_pending_verification_list_is_scoped_to_waiting_verification_qris(): void
    {
        [$ownerUser] = $this->owner();
        $waiting = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $pending = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $pending->payment->update(['payment_status' => 'PENDING']);
        $cash = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $cash->payment->update(['payment_method' => 'CASH']);

        $this->actingAs($ownerUser)->getJson('/api/v1/owner/payments/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $waiting->payment->id)
            ->assertJsonPath('data.0.order_number', $waiting->order_number)
            ->assertJsonPath('data.0.payment_method', 'QRIS')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_owner_payment_proof_endpoint_returns_private_url_and_404_without_file(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);

        $this->actingAs($ownerUser)->getJson('/api/v1/owner/orders/'.$order->id.'/payment/proof')
            ->assertStatus(404);

        Storage::disk('local')->put($order->payment->proof_image, 'proof');
        $this->actingAs($ownerUser)->getJson('/api/v1/owner/orders/'.$order->id.'/payment/proof')
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.payment_id', $order->payment->id)
            ->assertJsonPath('data.proof.available', true)
            ->assertJsonMissingPath('data.proof.path');
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

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$waitingOrder->id.'/assignment', [
            'courier_id' => $courier->id,
        ])->assertStatus(409);
    }

    public function test_assign_moves_order_to_assigned_and_records_history(): void
    {
        [$ownerUser] = $this->owner();
        [, $courier] = $this->courier();
        $order = $this->order(OrderStatus::DIPROSES);

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/orders/'.$order->id.'/assignment', [
            'courier_id' => $courier->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.order_status', OrderStatus::DITUGASKAN->value)
            ->assertJsonPath('data.assignment.status', AssignmentStatus::ASSIGNED->value);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => OrderStatus::DITUGASKAN->value]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::DIPROSES->value,
            'to_status' => OrderStatus::DITUGASKAN->value,
            'changed_by_user_id' => $ownerUser->id,
        ]);
    }

    public function test_owner_status_endpoint_only_accepts_the_owner_owned_transition(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_DIPROSES);

        $this->actingAs($ownerUser)->patchJson('/api/v1/owner/orders/'.$order->id.'/status', [
            'status' => OrderStatus::SELESAI->value,
        ])->assertStatus(409)->assertJsonPath('message', 'Transisi status tersebut bukan tanggung jawab owner.');

        $this->actingAs($ownerUser)->patchJson('/api/v1/owner/orders/'.$order->id.'/status', [
            'status' => OrderStatus::DIPROSES->value,
            'note' => 'Sedang dikemas.',
        ])
            ->assertOk()
            ->assertJsonPath('data.order_status', OrderStatus::DIPROSES->value);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_status' => OrderStatus::DIPROSES->value]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::DIPROSES->value,
            'note' => 'Sedang dikemas.',
        ]);

        $this->actingAs($ownerUser)->patchJson('/api/v1/owner/orders/'.$order->id.'/status', [
            'status' => OrderStatus::DIPROSES->value,
        ])->assertStatus(409);
    }

    public function test_owner_qris_endpoint_returns_null_when_not_configured(): void
    {
        BusinessSetting::query()->update(['qris_image' => null]);
        [$ownerUser] = $this->owner();

        $this->actingAs($ownerUser)->getJson('/api/v1/owner/payment/qris')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_owner_can_replace_qris_image_and_audit_actor_is_recorded(): void
    {
        Storage::disk('local')->put('qris/active.png', 'old-image');
        [$ownerUser] = $this->owner();

        $this->actingAs($ownerUser)->put('/api/v1/owner/payment/qris', [
            'qris_image' => UploadedFile::fake()->image('qris.png', 400, 400),
        ])->assertOk()->assertJsonPath('data.qris_image', url('/api/v1/customer/payment/qris/image'));

        $this->assertDatabaseHas('business_settings', [
            'id' => BusinessSetting::SINGLETON_ID,
            'updated_by' => $ownerUser->id,
        ]);
        Storage::disk('local')->assertMissing('qris/active.png');

        $this->actingAs($ownerUser)->put('/api/v1/owner/payment/qris', [
            'qris_image' => UploadedFile::fake()->create('qris.pdf', 10, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_removed_privileged_payment_id_endpoints_no_longer_exist(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $paymentId = $order->payment->id;

        $this->actingAs($ownerUser)->postJson('/api/v1/owner/payments/'.$paymentId.'/approve')->assertNotFound();
        $this->actingAs($ownerUser)->postJson('/api/v1/owner/payments/'.$paymentId.'/reject')->assertNotFound();
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'payment_status' => 'WAITING_VERIFICATION']);
    }

    // ------------------------------------------------------------------
    // C-01 — QRIS proof image harus benar-benar dapat diambil sebagai bytes.
    //
    // Spec 06 §12.2: response boleh berupa "payment proof resource atau
    // authorized file response sesuai implementation storage".
    // Spec 08 §40: "API->>S: Read private proof" lalu "API-->>OA: Authorized
    // proof view". Spec 08 PAY-009 "Owner can view authorized proof".
    // Spec 08 SEC-PAY-005 private proof access blocked for unauthorized users.
    //
    // Test lama hanya memeriksa `data.proof.available === true` dan tidak pernah
    // mendereference `data.proof.url`, sehingga URL self-referential lolos.
    // ------------------------------------------------------------------

    public function test_owner_can_download_qris_proof_image_bytes(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $payment = $order->payment;
        $bytes = $this->storeProofBytes($payment->proof_image);

        $response = $this->actingAs($ownerUser)
            ->get('/api/v1/owner/orders/'.$order->id.'/payment/proof?download=1');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame($bytes, $response->streamedContent());
        // Private proof: tidak boleh membocorkan path filesystem maupun disk.
        $this->assertStringNotContainsString($payment->proof_image, $response->streamedContent());
        // Bukti QRIS tidak boleh boleh disimpan shared cache.
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);

        // Metadata tetap utuh setelah file dikirim (spec 06 §12.2).
        $this->actingAs($ownerUser)
            ->getJson('/api/v1/owner/orders/'.$order->id.'/payment/proof')
            ->assertOk()
            ->assertJsonPath('data.proof.available', true)
            ->assertJsonMissingPath('data.proof.path');
    }

    public function test_owner_proof_url_returned_in_metadata_is_retrievable_and_returns_image_bytes(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $bytes = $this->storeProofBytes($order->payment->proof_image);

        $metadata = $this->actingAs($ownerUser)
            ->getJson('/api/v1/owner/orders/'.$order->id.'/payment/proof')
            ->assertOk()
            ->assertJsonPath('data.proof.available', true)
            ->assertJsonStructure(['data' => ['proof' => ['available', 'url']]]);

        $url = $metadata->json('data.proof.url');
        $this->assertIsString($url);
        // URL harus benar-benar dapat mengembalikan bytes, bukan metadata JSON.
        $this->assertStringContainsString('download=1', $url);

        $path = (string) parse_url($url, PHP_URL_PATH);
        $response = $this->actingAs($ownerUser)->get($path.'?download=1');
        $response->assertOk();
        $this->assertSame($bytes, $response->streamedContent());
    }

    public function test_owner_proof_image_download_is_authorized(): void
    {
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $this->storeProofBytes($order->payment->proof_image);
        $url = '/api/v1/owner/orders/'.$order->id.'/payment/proof?download=1';

        // Tidak terautentikasi → 401.
        $this->get($url)->assertUnauthorized();

        // Customer dan Courier bukan owner scope → 403 (spec 06 §24).
        $customerUser = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        Customer::create(['user_id' => $customerUser->id]);
        $this->actingAs($customerUser)->get($url)->assertForbidden();

        [$courierUser] = $this->courier();
        $this->actingAs($courierUser)->get($url)->assertForbidden();
    }

    public function test_owner_proof_image_download_handles_missing_file_and_invalid_resource(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);

        // Baris DB menyebut proof ada, tetapi file tidak ada di disk.
        $this->actingAs($ownerUser)
            ->get('/api/v1/owner/orders/'.$order->id.'/payment/proof?download=1')
            ->assertNotFound();

        // Order tidak ada.
        $this->actingAs($ownerUser)
            ->get('/api/v1/owner/orders/999999/payment/proof?download=1')
            ->assertNotFound();

        // Order tanpa payment.
        $emptyCustomerUser = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $emptyCustomer = Customer::create(['user_id' => $emptyCustomerUser->id]);
        $emptyOrder = $emptyCustomer->orders()->create([
            'order_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
        $this->actingAs($ownerUser)
            ->get('/api/v1/owner/orders/'.$emptyOrder->id.'/payment/proof?download=1')
            ->assertNotFound();
    }

    public function test_owner_proof_image_download_ignores_non_image_content_type(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $payment = $order->payment;
        $bytes = $this->storeProofBytes($payment->proof_image);

        // Client tidak boleh memaksa content type; nilai tersimpan di luar
        // allowlist JPG/JPEG/PNG (spec 08 §11.1) harus diabaikan.
        $payment->forceFill(['proof_mime_type' => 'text/html'])->save();

        $response = $this->actingAs($ownerUser)
            ->get('/api/v1/owner/orders/'.$order->id.'/payment/proof?download=1');
        $response->assertOk();
        $this->assertNotSame('text/html', $response->headers->get('Content-Type'));
        $this->assertSame($bytes, $response->streamedContent());
    }

    public function test_owner_proof_image_download_supports_conditional_get(): void
    {
        [$ownerUser] = $this->owner();
        $order = $this->order(OrderStatus::MENUNGGU_PEMBAYARAN);
        $this->storeProofBytes($order->payment->proof_image);
        $url = '/api/v1/owner/orders/'.$order->id.'/payment/proof?download=1';

        $first = $this->actingAs($ownerUser)->get($url);
        $first->assertOk();
        $this->actingAs($ownerUser)
            ->withHeader('If-None-Match', $first->headers->get('ETag'))
            ->get($url)
            ->assertStatus(304);
    }

    /**
     * Menulis byte PNG asli ke disk private `local` dan mengembalikan byte itu,
     * sehingga test dapat membandingkan isi stream secara persis.
     */
    private function storeProofBytes(string $path): string
    {
        $bytes = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true
        );
        Storage::disk('local')->put($path, $bytes);

        return $bytes;
    }

    /**
     * @return array{0: User}
     */
    private function owner(): array
    {
        return [User::factory()->create(['role_id' => Role::where('name', 'OWNER')->value('id')])];
    }

    /**
     * @return array{0: User, 1: Courier}
     */
    private function courier(string $email = 'courier@example.com'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', 'COURIER')->value('id'),
        ]);

        return [$user, Courier::create(['user_id' => $user->id])];
    }

    private function order(OrderStatus $status): Order
    {
        $customerUser = User::factory()->create(['role_id' => Role::where('name', 'CUSTOMER')->value('id')]);
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $order = $customer->orders()->create([
            'order_status' => $status->value,
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'QRIS',
            'payment_status' => 'WAITING_VERIFICATION',
            'amount' => 100,
            'proof_image' => 'payment-proofs/order-'.$order->id.'/proof.png',
            'proof_size' => 5,
            'proof_mime_type' => 'image/png',
            'proof_checksum' => 'checksum',
        ]);

        return $order->fresh('payment');
    }
}
