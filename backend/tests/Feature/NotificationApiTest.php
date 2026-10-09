<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Courier;
use App\Models\CourierAssignment;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kontrak payload notification.
 *
 * Spec 10 §19 mewajibkan kunci kontrak umum pada payload setiap event, dan
 * tabel event-specific field menentukan field tambahan per event. Spec 13 §37
 * mengulang kunci minimum yang sama sebagai kontrak integrasi tertinggi.
 *
 * Phase C mencatat belum ada test yang memeriksa isi payload notifikasi,
 * sehingga kunci yang diwajibkan spesifikasi lolos tanpa terdeteksi.
 */
class NotificationApiTest extends TestCase
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
    }

    /**
     * Kunci kontrak umum pada spec 10 §19 dan spec 13 §37.
     *
     * @return list<string>
     */
    private const COMMON_FIELDS = [
        'schema_version',
        'notification_id',
        'type',
        'resource_type',
        'resource_id',
        'order_id',
        'target_route',
        'created_at',
    ];

    /**
     * @return array<string, array{resource_type: string, resource_id: string}>
     */
    private const EVENT_RESOURCE = [
        'PAYMENT_QRIS_PROOF_UPLOADED' => ['resource_type' => 'PAYMENT', 'resource_id' => 'payment_id'],
        'PAYMENT_QRIS_APPROVED' => ['resource_type' => 'PAYMENT', 'resource_id' => 'payment_id'],
        'PAYMENT_QRIS_REJECTED' => ['resource_type' => 'PAYMENT', 'resource_id' => 'payment_id'],
        'PAYMENT_CASH_CONFIRMED' => ['resource_type' => 'PAYMENT', 'resource_id' => 'payment_id'],
        'ORDER_CREATED' => ['resource_type' => 'ORDER', 'resource_id' => 'order_id'],
        'ORDER_PROCESSED' => ['resource_type' => 'ORDER', 'resource_id' => 'order_id'],
        'COURIER_ASSIGNED' => ['resource_type' => 'DELIVERY_ASSIGNMENT', 'resource_id' => 'assignment_id'],
        'DELIVERY_STARTED' => ['resource_type' => 'ORDER', 'resource_id' => 'order_id'],
        'TRACKING_AVAILABLE' => ['resource_type' => 'DELIVERY_ASSIGNMENT', 'resource_id' => 'assignment_id'],
        'ORDER_COMPLETED' => ['resource_type' => 'ORDER', 'resource_id' => 'order_id'],
    ];

    public function test_every_notification_event_carries_the_full_common_payload_contract(): void
    {
        $service = app(NotificationService::class);
        $user = $this->user('CUSTOMER', 'payload@example.com');
        Customer::create(['user_id' => $user->id]);

        foreach (self::EVENT_RESOURCE as $type => $expected) {
            $notification = $service->sendToUser(
                userId: $user->id,
                type: $type,
                title: 'Judul',
                body: 'Isi',
                data: ['order_id' => 77, 'payment_id' => 88, 'assignment_id' => 99],
            );

            $data = $notification->fresh()->data;

            foreach (self::COMMON_FIELDS as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $data,
                    "Payload event {$type} wajib memuat kunci kontrak {$field}.",
                );
            }

            $this->assertSame(1, $data['schema_version'], "Event {$type}.");
            $this->assertSame((string) $notification->id, $data['notification_id'], "Event {$type}.");
            $this->assertSame($type, $data['type'], "Event {$type}.");
            $this->assertSame($expected['resource_type'], $data['resource_type'], "Event {$type}.");
            $this->assertSame(
                (string) $data[$expected['resource_id']],
                (string) $data['resource_id'],
                "resource_id event {$type} harus menunjuk pada {$expected['resource_id']}.",
            );
            $this->assertNotNull($data['created_at'], "Event {$type}.");

            // Kunci yang dipakai idempotensi harus tetap utuh dan bertipe sama,
            // karena `StoreBusinessNotification` membandingkannya lewat
            // `whereJsonContains` (spec 10 §28).
            $this->assertSame(77, $data['order_id'], "Event {$type}.");
            $this->assertSame(88, $data['payment_id'], "Event {$type}.");
            $this->assertSame(99, $data['assignment_id'], "Event {$type}.");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function targetRouteProvider(): array
    {
        return [
            'proof uploaded ke owner' => ['PAYMENT_QRIS_PROOF_UPLOADED', 'OWNER', 'owner/payment-verification/77'],
            'qris approved ke customer' => ['PAYMENT_QRIS_APPROVED', 'CUSTOMER', 'customer/orders/77/payment'],
            'qris rejected ke customer' => ['PAYMENT_QRIS_REJECTED', 'CUSTOMER', 'customer/orders/77/payment'],
            'cash confirmed ke customer' => ['PAYMENT_CASH_CONFIRMED', 'CUSTOMER', 'customer/orders/77/payment'],
            'order created ke customer' => ['ORDER_CREATED', 'CUSTOMER', 'customer/orders/77'],
            'order created ke owner' => ['ORDER_CREATED', 'OWNER', 'owner/orders/77'],
            'order processed ke customer' => ['ORDER_PROCESSED', 'CUSTOMER', 'customer/orders/77'],
            'courier assigned ke courier' => ['COURIER_ASSIGNED', 'COURIER', 'courier/deliveries/77'],
            'courier assigned ke customer' => ['COURIER_ASSIGNED', 'CUSTOMER', 'customer/orders/77'],
            'delivery started ke customer' => ['DELIVERY_STARTED', 'CUSTOMER', 'customer/orders/77/tracking'],
            'tracking available ke customer' => ['TRACKING_AVAILABLE', 'CUSTOMER', 'customer/orders/77/tracking'],
            'order completed ke customer' => ['ORDER_COMPLETED', 'CUSTOMER', 'customer/orders/77'],
            'order completed ke owner' => ['ORDER_COMPLETED', 'OWNER', 'owner/orders/77'],
            'order completed ke courier' => ['ORDER_COMPLETED', 'COURIER', 'courier/deliveries/77'],
        ];
    }

    #[DataProvider('targetRouteProvider')]
    public function test_target_route_follows_the_specification_for_each_recipient_role(
        string $type,
        string $role,
        string $expectedRoute,
    ): void {
        $user = $this->user($role, strtolower($role).'-'.md5($type.$role).'@example.com');
        Customer::create(['user_id' => $user->id]);

        $notification = app(NotificationService::class)->sendToUser(
            userId: $user->id,
            type: $type,
            title: 'Judul',
            body: 'Isi',
            data: ['order_id' => 77, 'payment_id' => 88, 'assignment_id' => 99],
        );

        $this->assertSame($expectedRoute, $notification->fresh()->data['target_route']);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function eventSpecificFieldProvider(): array
    {
        return [
            'order created' => ['ORDER_CREATED', ['order_status']],
            'order processed' => ['ORDER_PROCESSED', ['order_status']],
            'proof uploaded' => ['PAYMENT_QRIS_PROOF_UPLOADED', ['payment_method', 'payment_status']],
            'qris approved' => ['PAYMENT_QRIS_APPROVED', ['payment_method', 'payment_status']],
            'qris rejected' => ['PAYMENT_QRIS_REJECTED', ['payment_method', 'payment_status']],
            'cash confirmed' => ['PAYMENT_CASH_CONFIRMED', ['payment_method', 'payment_status']],
            'courier assigned' => ['COURIER_ASSIGNED', ['assignment_id']],
            'delivery started' => ['DELIVERY_STARTED', ['assignment_id', 'order_status']],
            'tracking available' => ['TRACKING_AVAILABLE', ['assignment_id']],
            'order completed' => ['ORDER_COMPLETED', ['order_status']],
        ];
    }

    #[DataProvider('eventSpecificFieldProvider')]
    /**
     * @param  list<string>  $fields
     */
    public function test_event_specific_fields_are_required_by_the_specification(string $type, array $fields): void
    {
        $user = $this->user('CUSTOMER', 'specific-'.md5($type).'@example.com');
        Customer::create(['user_id' => $user->id]);

        $notification = app(NotificationService::class)->sendToUser(
            userId: $user->id,
            type: $type,
            title: 'Judul',
            body: 'Isi',
            data: ['order_id' => 77, 'payment_id' => 88, 'assignment_id' => 99, 'order_status' => 'DIPROSES'],
        );

        foreach ($fields as $field) {
            $this->assertArrayHasKey(
                $field,
                $notification->fresh()->data,
                "Event {$type} wajib membawa field {$field} (spec 10 §19).",
            );
        }
    }

    public function test_payload_never_carries_secrets_or_proof_paths(): void
    {
        $user = $this->user('CUSTOMER', 'secrets@example.com');
        Customer::create(['user_id' => $user->id]);

        $notification = app(NotificationService::class)->sendToUser(
            userId: $user->id,
            type: 'ORDER_CREATED',
            title: 'Judul',
            body: 'Isi',
            data: ['order_id' => 77],
        );

        $serialized = (string) json_encode($notification->fresh()->data);

        foreach (['password', 'password_hash', 'api_token', 'access_token', 'secret', 'private_key', 'proof_image', 'payment-proofs/'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_qris_proof_upload_emits_owner_payload_with_verification_route(): void
    {
        [$customerUser, $order] = $this->customerWithOrder('QRIS', PaymentStatus::PENDING);
        $ownerUser = $this->ownerUser();

        $this->actingAs($customerUser)->post(
            '/api/v1/customer/orders/'.$order->id.'/payment/proof',
            ['proof' => UploadedFile::fake()->image('proof.png', 200, 200)],
        )->assertOk();

        $data = $this->payloadFor($ownerUser, 'PAYMENT_QRIS_PROOF_UPLOADED');

        $this->assertSame(1, $data['schema_version']);
        $this->assertSame('PAYMENT', $data['resource_type']);
        $this->assertSame((string) $data['payment_id'], (string) $data['resource_id']);
        $this->assertSame('QRIS', $data['payment_method']);
        $this->assertSame(PaymentStatus::WAITING_VERIFICATION->value, $data['payment_status']);
        $this->assertSame('owner/payment-verification/'.$order->id, $data['target_route']);
    }

    public function test_owner_approval_emits_customer_payload_with_payment_route(): void
    {
        [$customerUser, $order] = $this->customerWithOrder('QRIS', PaymentStatus::WAITING_VERIFICATION);
        $ownerUser = $this->ownerUser();
        $this->storeProof($order->payment);

        $this->actingAs($ownerUser)->postJson(
            '/api/v1/owner/orders/'.$order->id.'/payment-verification',
            ['action' => 'APPROVE'],
        )->assertOk();

        $data = $this->payloadFor($customerUser, 'PAYMENT_QRIS_APPROVED');

        $this->assertSame('PAYMENT', $data['resource_type']);
        $this->assertSame('QRIS', $data['payment_method']);
        $this->assertSame(PaymentStatus::PAID->value, $data['payment_status']);
        $this->assertSame('customer/orders/'.$order->id.'/payment', $data['target_route']);
    }

    public function test_owner_rejection_emits_customer_payload_with_pending_payment_status(): void
    {
        [$customerUser, $order] = $this->customerWithOrder('QRIS', PaymentStatus::WAITING_VERIFICATION);
        $ownerUser = $this->ownerUser();
        $this->storeProof($order->payment);

        $this->actingAs($ownerUser)->postJson(
            '/api/v1/owner/orders/'.$order->id.'/payment-verification',
            ['action' => 'REJECT', 'note' => 'Bukti tidak terbaca.'],
        )->assertOk();

        $data = $this->payloadFor($customerUser, 'PAYMENT_QRIS_REJECTED');

        $this->assertSame('PAYMENT', $data['resource_type']);
        $this->assertSame(PaymentStatus::PENDING->value, $data['payment_status']);
        $this->assertSame('customer/orders/'.$order->id.'/payment', $data['target_route']);
    }

    public function test_courier_cash_confirmation_emits_customer_payload_with_payment_route(): void
    {
        [$customerUser, $order] = $this->customerWithOrder('CASH', PaymentStatus::PENDING);
        [$courierUser] = $this->courierFor($order);

        $this->actingAs($courierUser)->postJson(
            '/api/v1/courier/orders/'.$order->id.'/payment-confirmation',
        )->assertOk();

        $data = $this->payloadFor($customerUser, 'PAYMENT_CASH_CONFIRMED');

        $this->assertSame('PAYMENT', $data['resource_type']);
        $this->assertSame('CASH', $data['payment_method']);
        $this->assertSame(PaymentStatus::PAID->value, $data['payment_status']);
        $this->assertSame('customer/orders/'.$order->id.'/payment', $data['target_route']);
    }

    public function test_delivery_started_emits_customer_payload_with_order_status(): void
    {
        [$customerUser, $order] = $this->customerWithOrder('CASH', PaymentStatus::PENDING);
        $order->update(['order_status' => OrderStatus::DITUGASKAN->value]);
        $this->courierFor($order, AssignmentStatus::ASSIGNED);

        $assignment = CourierAssignment::query()->where('order_id', $order->id)->firstOrFail();
        $courierUser = User::query()->whereKey($assignment->courier->user_id)->firstOrFail();

        $this->actingAs($courierUser)->postJson(
            '/api/v1/courier/assignments/'.$assignment->id.'/start',
        )->assertOk();

        $data = $this->payloadFor($customerUser, 'DELIVERY_STARTED');

        $this->assertSame('ORDER', $data['resource_type']);
        $this->assertSame(OrderStatus::DALAM_PENGANTARAN->value, $data['order_status']);
        $this->assertSame($assignment->id, $data['assignment_id']);
        $this->assertSame('customer/orders/'.$order->id.'/tracking', $data['target_route']);
    }

    public function test_notification_ownership_is_still_enforced_after_payload_change(): void
    {
        $recipient = $this->user('CUSTOMER', 'recipient@example.com');
        $other = $this->user('CUSTOMER', 'recipient-other@example.com');
        Customer::create(['user_id' => $recipient->id]);
        Customer::create(['user_id' => $other->id]);

        $notification = app(NotificationService::class)->sendToUser(
            userId: $recipient->id,
            type: 'ORDER_CREATED',
            title: 'Judul',
            body: 'Isi',
            data: ['order_id' => 77],
        );

        $this->actingAs($recipient)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->actingAs($other)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($other)
            ->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(User $recipient, string $type): array
    {
        return Notification::query()
            ->where('user_id', $recipient->id)
            ->where('type', $type)
            ->firstOrFail()
            ->data;
    }

    private function storeProof(Payment $payment): void
    {
        $seed = UploadedFile::fake()->image('seed.png', 200, 200);
        Storage::disk('local')->put($payment->proof_image, (string) file_get_contents($seed->getRealPath()));
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function customerWithOrder(string $paymentMethod, PaymentStatus $paymentStatus): array
    {
        $user = $this->user('CUSTOMER', 'customer-'.$paymentMethod.'-'.$paymentStatus->value.'@example.com');
        $customer = Customer::create(['user_id' => $user->id, 'phone' => '0800000000']);
        $order = $customer->orders()->create([
            'order_status' => OrderStatus::MENUNGGU_PEMBAYARAN->value,
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus->value,
            'amount' => 100,
            'proof_image' => $paymentMethod === 'QRIS' ? 'payment-proofs/order-'.$order->id.'/proof.png' : null,
        ]);

        return [$user, $order];
    }

    private function ownerUser(): User
    {
        $owner = $this->user('OWNER', 'owner-notification@example.com');
        Owner::create(['user_id' => $owner->id]);

        return $owner;
    }

    /**
     * @return array{0: User}
     */
    private function courierFor(Order $order, AssignmentStatus $status = AssignmentStatus::ACTIVE): array
    {
        $user = $this->user('COURIER', 'courier-notification@example.com');
        $courier = Courier::create(['user_id' => $user->id, 'phone' => '0800000000', 'vehicle' => 'Motor']);

        CourierAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'status' => $status->value,
            'assigned_at' => now(),
            'started_at' => $status === AssignmentStatus::ACTIVE ? now() : null,
        ]);

        if ($status === AssignmentStatus::ACTIVE) {
            $order->update(['order_status' => OrderStatus::DALAM_PENGANTARAN->value]);
        }

        return [$user];
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
        ]);
    }
}
