<?php

namespace Tests\Feature;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Owner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
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

    public function test_public_registration_always_creates_customer_and_profile(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer Baru',
            'email' => 'customer@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'role' => 'OWNER',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Registrasi berhasil.')
            ->assertJsonPath('data.user.role.name', 'CUSTOMER')
            ->assertJsonPath('data.user.status', 'ACTIVE')
            ->assertJsonMissingPath('data.user.password');
        $this->assertDatabaseHas('customers', ['user_id' => $response->json('data.user.id')]);
        $this->assertDatabaseMissing('owners', ['user_id' => $response->json('data.user.id')]);
        $this->assertTrue(Hash::check('Password1', User::firstOrFail()->password));
    }

    public function test_registration_validation_rejects_duplicate_email_and_mismatched_password(): void
    {
        User::factory()->create([
            'email' => 'duplicate@example.com',
            'role_id' => Role::where('name', 'CUSTOMER')->value('id'),
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer',
            'email' => 'duplicate@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Different1',
        ])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }

    public function test_customer_owner_and_courier_can_login_with_database_role(): void
    {
        $customer = $this->userWithRole('CUSTOMER', 'customer@example.com');
        $owner = $this->userWithRole('OWNER', 'owner@example.com');
        $courier = $this->userWithRole('COURIER', 'courier@example.com');
        Customer::create(['user_id' => $customer->id]);
        Owner::create(['user_id' => $owner->id]);
        Courier::create(['user_id' => $courier->id]);

        foreach ([[$customer, 'CUSTOMER'], [$owner, 'OWNER'], [$courier, 'COURIER']] as [$user, $role]) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk()
                ->assertJsonPath('data.user.role.name', $role)
                ->assertJsonPath('data.user.role.display_name', Role::defaultDisplayName($role))
                ->assertJsonPath('message', 'Login berhasil.')
                ->assertJsonStructure(['data' => ['user', 'token']]);
        }
    }

    public function test_seeded_owner_can_login_and_access_owner_dashboard(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@berkah.test',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Owner Berkah')
            ->assertJsonPath('data.user.role.name', 'OWNER');

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/dashboard/owner')
            ->assertOk()
            ->assertJsonPath('message', 'Data dashboard owner berhasil diambil.');

        $this->assertDatabaseHas('owners', [
            'user_id' => User::where('email', 'owner@berkah.test')->value('id'),
        ]);
    }

    public function test_me_returns_authenticated_user_role_and_profile(): void
    {
        $user = $this->userWithRole('CUSTOMER', 'me@example.com');
        Customer::create(['user_id' => $user->id, 'phone' => '0800000000']);

        $this->actingAs($user)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.role.name', 'CUSTOMER')
            ->assertJsonPath('data.user.profile.phone', '0800000000')
            ->assertJsonPath('message', 'Data pengguna berhasil dimuat.')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->userWithRole('CUSTOMER', 'logout@example.com');
        Customer::create(['user_id' => $user->id]);
        $token = $user->createToken('android');
        $otherToken = $user->createToken('other-device');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_role_middleware_blocks_cross_role_endpoints(): void
    {
        $customer = $this->userWithRole('CUSTOMER', 'role@example.com');
        Customer::create(['user_id' => $customer->id]);
        $token = $customer->createToken('android')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/owner/orders')
            ->assertForbidden()
            ->assertJson(['message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.']);
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/courier/assignments')
            ->assertForbidden()
            ->assertJson(['message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini.']);
    }

    private function userWithRole(string $roleName, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'role_id' => Role::where('name', $roleName)->firstOrFail()->id,
        ]);
    }
}
