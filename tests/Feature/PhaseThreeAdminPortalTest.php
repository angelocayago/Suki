<?php

namespace Tests\Feature;

use App\Models\AccountStatusEvent;
use App\Models\Application;
use App\Models\LogisticsProvider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\ApplicationDecisionNotification;
use App\Services\Admin\AccountStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PhaseThreeAdminPortalTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => $status,
            'is_suspended' => $status === 'suspended',
        ]);
        $roleModel = Role::firstOrCreate(['name' => $role]);
        $user->roles()->syncWithoutDetaching([$roleModel->id]);

        return $user;
    }

    public function test_admin_portal_rejects_non_admin_users(): void
    {
        $buyer = $this->userWithRole('buyer');

        $this->actingAs($buyer)
            ->get(route('superadmin.dashboard'))
            ->assertForbidden();
    }

    public function test_suspension_requires_a_reason_and_records_an_audit_event(): void
    {
        $admin = $this->userWithRole('admin');
        $buyer = $this->userWithRole('buyer');

        $this->actingAs($admin)
            ->patch(route('superadmin.users.status', $buyer), ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $buyer->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('superadmin.users.status', $buyer), [
                'status' => 'suspended',
                'reason' => 'Repeated marketplace policy violations.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $buyer->id,
            'status' => 'suspended',
            'is_suspended' => true,
        ]);
        $this->assertDatabaseHas('account_status_events', [
            'user_id' => $buyer->id,
            'actor_id' => $admin->id,
            'previous_status' => 'active',
            'new_status' => 'suspended',
            'reason' => 'Repeated marketplace policy violations.',
        ]);
    }

    public function test_seller_and_logistics_profiles_follow_account_restrictions(): void
    {
        $admin = $this->userWithRole('admin');
        $sellerUser = $this->userWithRole('seller');
        $logisticsUser = $this->userWithRole('logistics');

        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Approved Seller',
            'slug' => 'approved-seller-phase-three',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Approved Logistics',
            'slug' => 'approved-logistics-phase-three',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->patch(route('superadmin.sellers.status', $seller), [
            'status' => 'suspended',
            'reason' => 'Seller account review.',
        ])->assertSessionHasErrors('status');

        app(AccountStatusService::class)->suspendSellerForCompliance(
            $sellerUser,
            $admin,
            'Seller account review.',
            ['seller_violation_id' => 999]
        );

        $this->actingAs($admin)->patch(route('superadmin.users.status', $logisticsUser), [
            'status' => 'inactive',
            'reason' => 'Provider account closed.',
        ])->assertRedirect();

        $this->assertSame('suspended', $sellerUser->fresh()->status);
        $this->assertSame('suspended', $seller->fresh()->status);
        $this->assertSame('inactive', $logisticsUser->fresh()->status);
        $this->assertSame('suspended', $provider->fresh()->status);
    }

    public function test_admin_cannot_suspend_their_own_account(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->patch(route('superadmin.users.status', $admin), [
                'status' => 'suspended',
                'reason' => 'Self restriction attempt.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $admin->fresh()->status);
        $this->assertSame(0, AccountStatusEvent::count());
    }

    public function test_generic_status_controls_cannot_approve_pending_accounts(): void
    {
        $admin = $this->userWithRole('admin');
        $pendingBuyer = $this->userWithRole('buyer', 'pending');

        $this->actingAs($admin)
            ->patch(route('superadmin.users.status', $pendingBuyer), ['status' => 'active'])
            ->assertSessionHasErrors('status');

        $this->assertSame('pending', $pendingBuyer->fresh()->status);
    }

    public function test_deactivated_account_can_be_safely_reactivated_with_both_changes_audited(): void
    {
        $admin = $this->userWithRole('admin');
        $buyer = $this->userWithRole('buyer');

        $this->actingAs($admin)->patch(route('superadmin.buyers.status', $buyer), [
            'status' => 'inactive',
            'reason' => 'Buyer requested account deactivation.',
        ])->assertRedirect();

        $this->actingAs($admin)->patch(route('superadmin.buyers.status', $buyer), [
            'status' => 'active',
        ])->assertRedirect();

        $this->assertSame('active', $buyer->fresh()->status);
        $this->assertFalse($buyer->fresh()->is_suspended);
        $this->assertDatabaseHas('account_status_events', [
            'user_id' => $buyer->id,
            'previous_status' => 'active',
            'new_status' => 'inactive',
        ]);
        $this->assertDatabaseHas('account_status_events', [
            'user_id' => $buyer->id,
            'previous_status' => 'inactive',
            'new_status' => 'active',
        ]);
    }

    public function test_admin_approval_synchronizes_profiles_and_preserves_review_history(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $logisticsUser = $this->userWithRole('logistics', 'pending');
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Pending Logistics',
            'slug' => 'pending-logistics-phase-three',
            'status' => 'pending',
        ]);
        $application = Application::create([
            'user_id' => $logisticsUser->id,
            'application_type' => 'logistics',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('superadmin.applications.approve', $application))
            ->assertRedirect();

        $this->assertSame('active', $logisticsUser->fresh()->status);
        $this->assertSame('approved', $provider->fresh()->status);
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'rejection_reason' => null,
        ]);
        $this->assertDatabaseHas('account_status_events', [
            'user_id' => $logisticsUser->id,
            'application_id' => $application->id,
            'source' => 'application_approval',
            'previous_status' => 'pending',
            'new_status' => 'active',
        ]);
        Notification::assertSentTo($logisticsUser, ApplicationDecisionNotification::class);
    }

    public function test_admin_urls_are_canonical_and_legacy_superadmin_urls_remain_available(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $buyer = $this->userWithRole('buyer', 'pending');
        $application = Application::create([
            'user_id' => $buyer->id,
            'application_type' => 'buyer',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin Dashboard');

        $this->actingAs($admin)
            ->get(route('admin.buyers'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('superadmin.dashboard'))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.buyers.approve', $buyer))
            ->assertRedirect(route('admin.applications.show', $application));

        $this->assertSame('active', $buyer->fresh()->status);
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertDatabaseHas('account_status_events', [
            'application_id' => $application->id,
            'source' => 'application_approval',
        ]);
    }

    public function test_application_rejection_records_reason_reviewer_and_account_history(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $sellerUser = $this->userWithRole('seller', 'pending');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Rejected Seller',
            'slug' => 'rejected-seller-phase-three',
            'status' => 'pending',
        ]);
        $application = Application::create([
            'user_id' => $sellerUser->id,
            'application_type' => 'seller',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('superadmin.applications.reject', $application), [
            'rejection_reason' => 'Incomplete documents',
        ])->assertRedirect();

        $this->assertSame('rejected', $sellerUser->fresh()->status);
        $this->assertSame('rejected', $seller->fresh()->status);
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'reviewed_by' => $admin->id,
            'status' => 'rejected',
            'rejection_reason' => 'Incomplete documents',
        ]);
        $this->assertDatabaseHas('account_status_events', [
            'user_id' => $sellerUser->id,
            'application_id' => $application->id,
            'source' => 'application_rejection',
            'new_status' => 'rejected',
        ]);
    }
}
