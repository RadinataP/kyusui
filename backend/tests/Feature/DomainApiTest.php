<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['CUSTOMER', 'OWNER', 'COURIER'] as $name) {
            Role::create(['name' => $name]);
        }
    }

    public function test_customer_dashboard_and_profile_are_scoped_to_authenticated_customer(): void
    {
        $user = $this->user('CUSTOMER', 'customer@example.com');
        Customer::create(['user_id' => $user->id, 'phone' => '0800000000']);
        Notification::create(['user_id' => $user->id, 'type' => 'TEST', 'title' => 'Info', 'body' => 'Pesan']);

        $this->actingAs($user)->getJson('/api/v1/dashboard/customer')
            ->assertOk()
            ->assertJsonPath('message', 'Data dashboard customer berhasil diambil.')
            ->assertJsonPath('data.unread_notifications', 1);
        $this->actingAs($user)->putJson('/api/v1/profile', ['name' => 'Nama Baru', 'phone' => '0811111111'])
            ->assertOk()
            ->assertJsonPath('data.user.profile.phone', '0811111111');
    }

    public function test_owner_can_manage_products_but_customer_cannot(): void
    {
        $owner = $this->user('OWNER', 'owner@example.com');
        Owner::create(['user_id' => $owner->id]);
        $customer = $this->user('CUSTOMER', 'customer@example.com');
        Customer::create(['user_id' => $customer->id]);

        $this->actingAs($customer)->postJson('/api/v1/owner/products', ['name' => 'Galon', 'price' => 15000])
            ->assertForbidden();
        $response = $this->actingAs($owner)->postJson('/api/v1/owner/products', ['name' => 'Galon', 'price' => 15000]);
        $response->assertCreated()->assertJsonPath('message', 'Produk berhasil dibuat.');
        $product = Product::firstOrFail();
        $this->actingAs($owner)->deleteJson('/api/v1/owner/products/'.$product->id)
            ->assertOk()
            ->assertJson(['message' => 'Produk berhasil dinonaktifkan.']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'availability' => 0]);
    }

    public function test_notifications_are_isolated_and_can_be_marked_read(): void
    {
        $user = $this->user('CUSTOMER', 'customer@example.com');
        $other = $this->user('CUSTOMER', 'other@example.com');
        Customer::create(['user_id' => $user->id]);
        Customer::create(['user_id' => $other->id]);
        $notification = Notification::create(['user_id' => $user->id, 'type' => 'TEST', 'title' => 'Info', 'body' => 'Pesan']);
        Notification::create(['user_id' => $other->id, 'type' => 'TEST', 'title' => 'Rahasia', 'body' => 'Pesan']);

        $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notifikasi berhasil ditandai sebagai dibaca.');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
        ]);
    }
}
