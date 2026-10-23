<?php

namespace Tests\Feature;

use App\Models\BuyerEmailVerification;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePortalRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_can_access_buyer_routes(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.account'))
            ->assertOk();
    }

    public function test_unverified_buyer_with_pending_verification_is_redirected(): void
    {
        $buyer = User::factory()->unverified()->create([
            'role' => 'buyer',
            'status' => 'active',
        ]);

        BuyerEmailVerification::query()->create([
            'user_id' => $buyer->id,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($buyer)
            ->get(route('buyer.account'))
            ->assertRedirect(route('buyer.verification.show'))
            ->assertSessionHas('buyer_verification_user_id', $buyer->id);

        $this->assertGuest();
    }

    public function test_seller_routes_keep_their_session_gate(): void
    {
        $this->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.register'));

        $this->withSession(['seller_logged_in' => true])
            ->get(route('seller.dashboard'))
            ->assertForbidden();

        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $seller->id, 'name' => 'Route fixture', 'slug' => 'route-fixture', 'status' => 'approved']);
        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk();
    }

    public function test_active_logistics_user_can_access_logistics_routes(): void
    {
        $logistics = User::factory()->create([
            'role' => 'logistics',
            'status' => 'active',
        ]);

        $this->actingAs($logistics)
            ->get(route('logistics.dashboard'))
            ->assertOk();
    }

    public function test_rider_routes_keep_their_approved_session_gate(): void
    {
        $this->get(route('rider.dashboard'))
            ->assertRedirect(route('rider.login'));

        $this->withSession([
            'rider_logged_in' => true,
            'logged_in_rider_index' => 0,
            'rider_applications' => [
                [
                    'first_name' => 'Riley',
                    'last_name' => 'Rider',
                    'status' => 'approved',
                ],
            ],
        ])
            ->get(route('rider.dashboard'))
            ->assertOk();
    }

    public function test_admin_and_superadmin_routes_remain_role_restricted(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $superadmin = User::factory()->create([
            'role' => 'superadmin',
            'status' => 'active',
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.dashboard'))
            ->assertOk();

        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
        ]);

        $this->actingAs($buyer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->get(route('superadmin.dashboard'))
            ->assertForbidden();
    }
}
