<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Models\AccountStatusEvent;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerComplianceEvent;
use App\Models\SellerOrder;
use App\Models\SellerViolation;
use App\Models\User;
use App\Services\Admin\SellerComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PhaseThreeSellerComplianceSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reason_confirmation_and_open_case_are_required(): void
    {
        [$admin, , , $violation] = $this->fixture('required-fields');

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'confirmation' => '1',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'reason' => 'Confirmed compliance violation.',
        ])->assertSessionHasErrors('confirmation');

        app(SellerComplianceService::class)->resolve($violation, $admin, 'Case closed before suspension.');

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'reason' => 'Late suspension attempt.',
            'confirmation' => '1',
        ])->assertSessionHasErrors('status');
    }

    public function test_only_an_active_admin_can_suspend_from_compliance(): void
    {
        [, $sellerUser, , $violation] = $this->fixture('authorization');

        $this->actingAs($sellerUser)
            ->post(route('superadmin.compliance.suspend-seller', $violation), [
                'reason' => 'Unauthorized.',
                'confirmation' => '1',
            ])
            ->assertForbidden();

        $suspendedAdmin = $this->userWithRole('admin', 'suspended');
        try {
            app(SellerComplianceService::class)->suspendSeller(
                $violation,
                $suspendedAdmin,
                'Unauthorized suspended administrator.',
                true
            );
            $this->fail('A suspended administrator must not suspend a seller.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('active', $sellerUser->fresh()->status);
    }

    public function test_all_pre_pickup_statuses_block_suspension_without_changing_orders(): void
    {
        [$admin, $sellerUser, $seller, $violation] = $this->fixture('blocking-orders');
        $statuses = [
            OrderStatus::Placed,
            OrderStatus::Confirmed,
            OrderStatus::Preparing,
            OrderStatus::ReadyForPickup,
        ];
        $orders = collect($statuses)->map(fn ($status) => $this->sellerOrder($seller, $status));

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'reason' => 'Suspension must wait for pickup.',
            'confirmation' => '1',
        ])->assertSessionHasErrors('active_orders');

        $this->assertSame('active', $sellerUser->fresh()->status);
        foreach ($orders as $index => $sellerOrder) {
            $this->assertSame($statuses[$index], $sellerOrder->fresh()->workflow_status);
        }
        $this->assertSame(0, AccountStatusEvent::count());
        $this->assertSame(0, SellerComplianceEvent::where('action', 'SELLER_SUSPENDED')->count());
    }

    public function test_rider_and_logistics_stages_do_not_block_and_order_statuses_remain_unchanged(): void
    {
        [$admin, $sellerUser, $seller, $violation] = $this->fixture('downstream-orders');
        $statuses = [
            OrderStatus::PickedUp,
            OrderStatus::AtSortingCenter,
            OrderStatus::Sorted,
            OrderStatus::AssignedToRider,
            OrderStatus::OutForDelivery,
            OrderStatus::Delivered,
            OrderStatus::Completed,
        ];
        $orders = collect($statuses)->map(fn ($status) => $this->sellerOrder($seller, $status));

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'reason' => 'Critical marketplace compliance violation.',
            'confirmation' => '1',
        ])->assertRedirect();

        $this->assertSame('suspended', $sellerUser->fresh()->status);
        foreach ($orders as $index => $sellerOrder) {
            $this->assertSame($statuses[$index], $sellerOrder->fresh()->workflow_status);
        }
    }

    public function test_multi_store_and_related_profile_impact_is_shown_and_audited(): void
    {
        [$admin, $sellerUser, $seller, $violation] = $this->fixture('multi-store-primary');
        $secondStore = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Multi Store Secondary',
            'slug' => 'multi-store-secondary',
            'status' => 'approved',
        ]);
        $logisticsRole = Role::firstOrCreate(['name' => 'logistics']);
        $sellerUser->roles()->syncWithoutDetaching([$logisticsRole->id]);
        $provider = LogisticsProvider::create([
            'user_id' => $sellerUser->id,
            'name' => 'Related Logistics Profile',
            'slug' => 'related-logistics-profile',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)->get(route('superadmin.compliance.show', $violation))
            ->assertOk()
            ->assertSeeText($seller->name)
            ->assertSeeText($secondStore->name)
            ->assertSeeText('Seller')
            ->assertSeeText('Logistics')
            ->assertSeeText($provider->name);

        $this->actingAs($admin)->post(route('superadmin.compliance.suspend-seller', $violation), [
            'reason' => 'Account-level compliance suspension.',
            'confirmation' => '1',
        ])->assertRedirect();

        $accountEvent = AccountStatusEvent::where('source', 'seller_compliance')->firstOrFail();
        $complianceEvent = SellerComplianceEvent::where('action', 'SELLER_SUSPENDED')->firstOrFail();

        $this->assertSame('suspended', $sellerUser->fresh()->status);
        $this->assertSame('suspended', $seller->fresh()->status);
        $this->assertSame('suspended', $secondStore->fresh()->status);
        $this->assertSame('suspended', $provider->fresh()->status);
        $this->assertSame($accountEvent->id, $complianceEvent->account_status_event_id);
        $this->assertSame($violation->id, $accountEvent->metadata['seller_violation_id']);
        $this->assertEqualsCanonicalizing(
            [$seller->id, $secondStore->id],
            $accountEvent->metadata['affected_seller_ids']
        );
    }

    public function test_generic_admin_routes_cannot_bypass_compliance_but_reactivation_still_works(): void
    {
        [$admin, $sellerUser, $seller, $violation] = $this->fixture('generic-bypass');

        $this->actingAs($admin)->patch(route('superadmin.sellers.status', $seller), [
            'status' => 'suspended',
            'reason' => 'Generic seller suspension.',
        ])->assertSessionHasErrors('status');
        $this->actingAs($admin)->patch(route('superadmin.users.status', $sellerUser), [
            'status' => 'suspended',
            'reason' => 'Generic user suspension.',
        ])->assertSessionHasErrors('status');
        $this->assertSame('active', $sellerUser->fresh()->status);

        app(SellerComplianceService::class)->suspendSeller(
            $violation,
            $admin,
            'Compliance-controlled suspension.',
            true
        );

        $this->actingAs($admin)->patch(route('superadmin.sellers.status', $seller), [
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame('active', $sellerUser->fresh()->status);
        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_duplicate_suspension_is_rejected_and_does_not_duplicate_events(): void
    {
        [$admin, , , $violation] = $this->fixture('duplicate-suspension');
        $service = app(SellerComplianceService::class);
        $service->suspendSeller($violation, $admin, 'Initial suspension.', true);

        try {
            $service->suspendSeller($violation, $admin, 'Duplicate suspension.', true);
            $this->fail('Duplicate suspension should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(1, AccountStatusEvent::where('source', 'seller_compliance')->count());
        $this->assertSame(1, SellerComplianceEvent::where('action', 'SELLER_SUSPENDED')->count());
    }

    private function fixture(string $slug): array
    {
        $admin = $this->userWithRole('admin');
        $sellerUser = $this->userWithRole('seller');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'status' => 'approved',
        ]);
        $violation = app(SellerComplianceService::class)->createViolation(
            $seller,
            $admin,
            SellerViolationCategory::Other,
            SellerViolationSeverity::High,
            'Seller compliance suspension fixture.'
        );

        return [$admin, $sellerUser, $seller, $violation];
    }

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

    private function sellerOrder(Seller $seller, OrderStatus $status): SellerOrder
    {
        $buyer = $this->userWithRole('buyer');
        $number = 'SUSP-'.fake()->unique()->numerify('######');
        $order = Order::create([
            'order_number' => $number,
            'reference' => $number,
            'buyer_id' => $buyer->id,
            'status' => $status->value,
            'payment_method' => 'cod',
            'payment_status' => 'UNPAID',
            'subtotal' => 100,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'total_minor' => 10000,
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'Municipality',
            'barangay' => 'Barangay',
            'street_address' => 'Street',
            'shipping_address' => ['street_address' => 'Street'],
            'placed_at' => now(),
        ]);

        return SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 1000,
            'status' => $status->legacySellerStatus(),
            'workflow_status' => $status->value,
            'status_changed_at' => now(),
        ]);
    }
}
