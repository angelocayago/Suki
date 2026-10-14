<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Database\Seeders\TestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationLogoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_seller_login_and_logout_flow(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $this->post(route('login.submit'), [
            'login' => 'seller@test.com',
            'password' => 'password',
        ])->assertRedirect(route('seller.dashboard'));

        $this->get(route('seller.dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
    }

    public function test_seeded_admin_login_and_logout_flow(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $this->post(route('admin.login.submit'), [
            'login' => 'admin@test.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('superadmin.dashboard'));
        $this->get(route('superadmin.dashboard'))->assertOk();
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->get(route('superadmin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_seeded_buyer_login_and_logout_flow(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $this->post(route('login.submit'), [
            'login' => 'buyer@test.com',
            'password' => 'password',
        ])->assertRedirect(route('buyer.home'));

        $this->post(route('logout'))->assertRedirect(route('buyer.home'));
        $this->assertGuest();
    }

    public function test_seeded_rider_login_and_logout_flow(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $this->post(route('rider.login.submit'), [
            'login' => 'rider@test.com',
            'password' => 'password',
        ])->assertRedirect(route('rider.dashboard'));

        $this->get(route('rider.dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('rider.dashboard'))->assertRedirect(route('login'));
    }

    public function test_seeded_logistics_login_and_logout_flow(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $this->post(route('login.submit'), [
            'login' => 'logistics@test.com',
            'password' => 'password',
        ])->assertRedirect(route('logistics.dashboard'));

        $this->get(route('logistics.dashboard'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('logistics.dashboard'))->assertRedirect(route('login'));
    }

    public function test_seller_logout_invalidates_all_role_sessions_and_requires_login_again(): void
    {
        $sellerUser = $this->userWithRole('seller');
        Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Logout Seller',
            'slug' => 'logout-seller',
            'status' => 'approved',
        ]);

        $this->actingAs($sellerUser)
            ->withSession([
                'seller_logged_in' => true,
                'buyer_logged_in' => true,
                'buyer_profile' => ['name' => 'stale buyer'],
            ])
            ->get(route('seller.dashboard'))
            ->assertOk();

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $response->assertSessionMissing('seller_logged_in');
        $response->assertSessionMissing('buyer_logged_in');
        $response->assertSessionMissing('buyer_profile');

        $this->get(route('seller.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_logout_uses_admin_login_and_cannot_reenter_admin_portal(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['buyer_logged_in' => true, 'seller_logged_in' => true])
            ->get(route('superadmin.dashboard'))
            ->assertOk();

        $response = $this->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $response->assertSessionMissing('buyer_logged_in');
        $response->assertSessionMissing('seller_logged_in');
        $this->get(route('superadmin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_shared_logout_also_routes_admin_to_admin_login(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_buyer_logout_keeps_public_marketplace_redirect_and_clears_authentication(): void
    {
        $buyer = $this->userWithRole('buyer');

        $response = $this->actingAs($buyer)
            ->withSession(['buyer_logged_in' => true, 'buyer_profile' => ['name' => 'Buyer']])
            ->post(route('logout'));

        $response->assertRedirect(route('buyer.home'));
        $this->assertGuest();
        $response->assertSessionMissing('buyer_logged_in');
        $response->assertSessionMissing('buyer_profile');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $roleModel = Role::firstOrCreate(['name' => $role]);
        $user->roles()->syncWithoutDetaching([$roleModel->id]);

        return $user;
    }
}
