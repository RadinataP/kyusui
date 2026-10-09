<?php

namespace Tests\Feature;

use App\Models\Courier;
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
            Role::create([
                'name' => $name,
                'display_name' => Role::defaultDisplayName($name),
            ]);
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

    public function test_canonical_customer_profile_endpoint_updates_the_same_profile(): void
    {
        $user = $this->user('CUSTOMER', 'canonical-profile@example.com');
        Customer::create(['user_id' => $user->id, 'phone' => '0800000000']);

        $this->actingAs($user)->getJson('/api/v1/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.phone', '0800000000')
            ->assertJsonPath('data.email', $user->email);

        $this->actingAs($user)->putJson('/api/v1/customer/profile', [
            'name' => 'Nama Canonical',
            'phone' => '0899999999',
            'default_address' => 'Jl. Baru No. 9',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Canonical')
            ->assertJsonPath('data.phone', '0899999999')
            ->assertJsonPath('data.default_address', 'Jl. Baru No. 9');
    }

    public function test_customer_profile_endpoint_is_closed_to_other_roles(): void
    {
        $owner = $this->user('OWNER', 'owner-profile@example.com');
        Owner::create(['user_id' => $owner->id]);

        $this->actingAs($owner)->getJson('/api/v1/customer/profile')->assertForbidden();
        $this->actingAs($owner)->putJson('/api/v1/customer/profile', ['phone' => '0800000000'])->assertForbidden();
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
        $otherNotification = Notification::create(['user_id' => $other->id, 'type' => 'TEST', 'title' => 'Rahasia', 'body' => 'Pesan']);

        $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->patchJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notifikasi berhasil ditandai sebagai dibaca.');
        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)->patchJson('/api/v1/notifications/'.$otherNotification->id.'/read')
            ->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_removed_notification_read_post_endpoint_is_gone(): void
    {
        $user = $this->user('CUSTOMER', 'customer@example.com');
        Customer::create(['user_id' => $user->id]);
        $notification = Notification::create(['user_id' => $user->id, 'type' => 'TEST', 'title' => 'Info', 'body' => 'Pesan']);

        $this->actingAs($user)->postJson('/api/v1/notifications/'.$notification->id.'/read')->assertMethodNotAllowed();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_courier_profile_endpoint_remains_available_as_preserved_contract(): void
    {
        $user = $this->user('COURIER', 'courier-profile@example.com');
        Courier::create(['user_id' => $user->id, 'phone' => '0800000000', 'vehicle' => 'Motor']);

        $this->actingAs($user)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.user.profile.vehicle', 'Motor');
        $this->actingAs($user)->putJson('/api/v1/profile', ['vehicle' => 'Mobil'])
            ->assertOk()
            ->assertJsonPath('data.user.profile.vehicle', 'Mobil');
    }

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
        ]);
    }
}
