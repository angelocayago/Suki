<?php

namespace Tests\Feature;

use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Enums\SellerViolationStatus;
use App\Models\AccountStatusEvent;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModerationEvent;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerComplianceEvent;
use App\Models\SellerViolation;
use App\Models\User;
use App\Services\Admin\SellerComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PhaseThreeSellerComplianceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_product_related_violation_with_separate_history(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('compliance-seller');
        $product = $this->product($seller, 'Compliance Product');

        $violation = $this->service()->createViolation(
            $seller,
            $admin,
            SellerViolationCategory::ProhibitedProduct,
            SellerViolationSeverity::High,
            'Product violates marketplace policy.',
            'The description and images require compliance review.',
            $product
        );

        $this->assertMatchesRegularExpression('/^SC-\d{8}-[A-Z0-9]{10}$/', $violation->reference);
        $this->assertSame($seller->id, $violation->seller_id);
        $this->assertSame($product->id, $violation->product_id);
        $this->assertSame($admin->id, $violation->created_by);
        $this->assertSame(SellerViolationCategory::ProhibitedProduct, $violation->category);
        $this->assertSame(SellerViolationSeverity::High, $violation->severity);
        $this->assertSame(SellerViolationStatus::Open, $violation->status);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'action' => 'VIOLATION_CREATED',
            'new_status' => 'OPEN',
            'new_severity' => 'HIGH',
        ]);
        $this->assertSame(0, ProductModerationEvent::count());
        $this->assertSame(0, AccountStatusEvent::count());
        $this->assertSame('active', $seller->owner->fresh()->status);
        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_all_supported_categories_and_severities_are_persisted(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('enum-compliance-seller');

        foreach (SellerViolationCategory::cases() as $index => $category) {
            $severity = SellerViolationSeverity::cases()[$index % count(SellerViolationSeverity::cases())];
            $violation = $this->service()->createViolation(
                $seller,
                $admin,
                $category,
                $severity,
                'Compliance reason '.$index
            );

            $this->assertSame($category, $violation->category);
            $this->assertSame($severity, $violation->severity);
        }

        $this->assertSame(count(SellerViolationCategory::cases()), SellerViolation::count());
    }

    public function test_related_product_must_belong_to_selected_seller(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('selected-seller');
        [, $otherSeller] = $this->seller('other-seller');
        $otherProduct = $this->product($otherSeller, 'Other Seller Product');

        try {
            $this->service()->createViolation(
                $seller,
                $admin,
                SellerViolationCategory::CategoryMismatch,
                SellerViolationSeverity::Medium,
                'Invalid ownership fixture.',
                null,
                $otherProduct
            );
            $this->fail('A cross-seller product should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }

        $this->assertSame(0, SellerViolation::count());
        $this->assertSame(0, SellerComplianceEvent::count());
    }

    public function test_admin_changes_severity_and_history_preserves_both_values(): void
    {
        [$admin, , $violation] = $this->fixture('severity-case', SellerViolationSeverity::Low);

        $updated = $this->service()->changeSeverity(
            $violation,
            $admin,
            SellerViolationSeverity::Critical,
            'Escalated after additional review.'
        );

        $this->assertSame(SellerViolationSeverity::Critical, $updated->severity);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'action' => 'SEVERITY_CHANGED',
            'previous_severity' => 'LOW',
            'new_severity' => 'CRITICAL',
            'reason' => 'Escalated after additional review.',
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->changeSeverity($updated, $admin, SellerViolationSeverity::Critical);
    }

    public function test_warning_is_recorded_and_only_seller_owner_can_acknowledge_it(): void
    {
        [$admin, $seller, $violation] = $this->fixture('warning-case');
        $warning = $this->service()->addWarning(
            $violation,
            $admin,
            'Correct the listing before resubmission.'
        );

        $this->assertFalse($warning->isAcknowledged());
        $this->assertSame($seller->id, $warning->seller_id);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'action' => 'WARNING_ISSUED',
        ]);

        $outsider = $this->userWithRole('seller');
        try {
            $this->service()->acknowledgeWarning($warning, $outsider);
            $this->fail('A different seller should not acknowledge the warning.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $acknowledged = $this->service()->acknowledgeWarning($warning, $seller->owner);
        $this->assertTrue($acknowledged->isAcknowledged());
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'actor_id' => $seller->user_id,
            'action' => 'WARNING_ACKNOWLEDGED',
        ]);
    }

    public function test_admin_can_resolve_or_dismiss_open_cases_with_resolution_information(): void
    {
        [$admin, , $resolvedCase] = $this->fixture('resolved-case');
        [, , $dismissedCase] = $this->fixture('dismissed-case');

        $resolved = $this->service()->resolve($resolvedCase, $admin, 'Seller corrected the product.');
        $dismissed = $this->service()->dismiss($dismissedCase, $admin, 'No policy breach was found.');

        $this->assertSame(SellerViolationStatus::Resolved, $resolved->status);
        $this->assertSame($admin->id, $resolved->resolved_by);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Seller corrected the product.', $resolved->resolution_notes);
        $this->assertSame(SellerViolationStatus::Dismissed, $dismissed->status);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $resolved->id,
            'action' => 'CASE_RESOLVED',
            'previous_status' => 'OPEN',
            'new_status' => 'RESOLVED',
        ]);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $dismissed->id,
            'action' => 'CASE_DISMISSED',
            'previous_status' => 'OPEN',
            'new_status' => 'DISMISSED',
        ]);
    }

    public function test_closed_cases_reject_new_warnings_severity_changes_and_second_resolution(): void
    {
        [$admin, , $violation] = $this->fixture('closed-case');
        $resolved = $this->service()->resolve($violation, $admin, 'Case complete.');

        foreach ([
            fn () => $this->service()->addWarning($resolved, $admin, 'Late warning.'),
            fn () => $this->service()->changeSeverity($resolved, $admin, SellerViolationSeverity::High),
            fn () => $this->service()->dismiss($resolved, $admin, 'Late dismissal.'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Closed compliance cases must be immutable through the service.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }

        $this->assertSame(2, $resolved->events()->count());
    }

    public function test_non_admin_cannot_create_or_manage_compliance_cases(): void
    {
        [$sellerUser, $seller] = $this->seller('unauthorized-compliance-seller');

        try {
            $this->service()->createViolation(
                $seller,
                $sellerUser,
                SellerViolationCategory::Other,
                SellerViolationSeverity::Low,
                'Unauthorized case.'
            );
            $this->fail('A seller must not create an administrator compliance case.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame(0, SellerViolation::count());
    }

    private function fixture(
        string $slug,
        SellerViolationSeverity $severity = SellerViolationSeverity::Medium
    ): array {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller($slug);
        $violation = $this->service()->createViolation(
            $seller,
            $admin,
            SellerViolationCategory::Other,
            $severity,
            'Compliance fixture reason.'
        );

        return [$admin, $seller, $violation];
    }

    private function service(): SellerComplianceService
    {
        return app(SellerComplianceService::class);
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

    private function seller(string $slug): array
    {
        $user = $this->userWithRole('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'status' => 'approved',
        ]);

        return [$user, $seller];
    }

    private function product(Seller $seller, string $name): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'compliance-category'],
            ['name' => 'Compliance Category', 'is_active' => true]
        );

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id)->toString(),
            'description' => 'Compliance product fixture.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
    }
}
