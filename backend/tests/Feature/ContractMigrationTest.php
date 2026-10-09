<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Buktiiri deliberate contract break yang terjadi pada Phase B.
 *
 * Klien Android saat ini memanggil tiga endpoint yang sudah tidak lagi ada di
 * backend. Test ini mengunci status yang dihasilkan supaya perpindahan ke
 * kontrak canonical tidak datang sebagai kejutan di Phase E.
 *
 * Lihat KYUSUI_BACKEND_REPAIR_REPORT.md bagian Android Integration Impact.
 */
class ContractMigrationTest extends TestCase
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

    public function test_customer_order_detail_moved_under_the_customer_prefix(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'CUSTOMER')->value('id'),
        ]);
        $customer = Customer::create(['user_id' => $user->id]);
        $order = $this->createOrder($customer);

        // `CustomerApi.kt:62` masih memanggil `orders/{order}` tanpa prefix.
        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound();

        // Kontrak canonical `docs/06...` section 9.6.
        $this->actingAs($user)
            ->getJson("/api/v1/customer/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_customer_tracking_moved_under_the_customer_prefix(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'CUSTOMER')->value('id'),
        ]);
        $customer = Customer::create(['user_id' => $user->id]);
        $order = $this->createOrder($customer);

        // `CustomerApi.kt:94` masih memanggil `orders/{order}/tracking`.
        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/tracking")
            ->assertNotFound();

        // Kontrak canonical `docs/06...` section 9.7.
        $this->actingAs($user)
            ->getJson("/api/v1/customer/orders/{$order->id}/tracking")
            ->assertOk()
            ->assertJsonStructure(['data', 'message'])
            ->assertJsonPath('data', null);
    }

    public function test_owner_duplicate_payment_routes_are_gone(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'OWNER')->value('id'),
        ]);

        // `POST /owner/payments/{payment}/approve` dan `/reject` adalah
        // duplikat dari `POST /owner/orders/{order}/payment-verification`.
        $this->actingAs($user)
            ->postJson('/api/v1/owner/payments/1/approve')
            ->assertNotFound();

        $this->actingAs($user)
            ->postJson('/api/v1/owner/payments/1/reject')
            ->assertNotFound();
    }

    public function test_qris_image_is_served_from_a_single_deduplicated_route(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'OWNER')->value('id'),
        ]);

        // `owner/qris/image` pernah menjadi duplikat dari
        // `customer/payment/qris/image` dengan handler identik.
        $this->actingAs($user)
            ->getJson('/api/v1/owner/qris/image')
            ->assertNotFound();

        $this->actingAs($user)
            ->get('/api/v1/customer/payment/qris/image')
            ->assertNotFound();

        Storage::fake('local');
        $path = 'qris/static-qris.png';
        Storage::disk('local')->put($path, 'binary-image');
        BusinessSetting::query()->update(['qris_image' => $path]);

        $this->actingAs($user)
            ->get('/api/v1/customer/payment/qris/image')
            ->assertOk();
    }

    private function createOrder(Customer $customer): Order
    {
        return $customer->orders()->create([
            'order_status' => 'MENUNGGU_PEMBAYARAN',
            'subtotal_amount' => 100,
            'delivery_fee' => 0,
            'total_amount' => 100,
            'delivery_address' => 'Jl. Test',
            'placed_at' => now(),
        ]);
    }
}
