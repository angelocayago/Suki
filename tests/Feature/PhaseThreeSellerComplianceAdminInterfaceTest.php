<?php

namespace Tests\Feature;

use App\Enums\SellerViolationCategory;
use App\Enums\SellerViolationSeverity;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerViolation;
use App\Models\User;
use App\Services\Admin\SellerComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseThreeSellerComplianceAdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_roles_can_access_the_compliance_portal(): void
    {
        $admin = $this->userWithRole('admin');
        $superadmin = $this->userWithRole('superadmin');
        $seller = $this->userWithRole('seller');

        $this->actingAs($admin)->get(route('superadmin.compliance.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('superadmin.compliance.index'))->assertOk();
        $this->actingAs($seller)->get(route('superadmin.compliance.index'))->assertForbidden();
    }

    public function test_admin_can_create_and_filter_a_violation_without_changing_account_status(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('portal-seller');
        $product = $this->product($seller, 'Portal Product');

        $response = $this->actingAs($admin)->post(route('superadmin.compliance.store'), [
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'category' => SellerViolationCategory::ProhibitedProduct->value,
            'severity' => SellerViolationSeverity::High->value,
            'reason' => 'Listing contains a prohibited product.',
            'details' => 'Administrator review details.',
        ]);

        $violation = SellerViolation::firstOrFail();
        $response->assertRedirect(route('superadmin.compliance.show', $violation));
        $this->assertSame('active', $seller->owner->fresh()->status);
        $this->assertSame('approved', $seller->fresh()->status);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'action' => 'VIOLATION_CREATED',
        ]);

        $this->actingAs($admin)->get(route('superadmin.compliance.index', [
            'seller_id' => $seller->id,
            'severity' => 'HIGH',
            'status' => 'OPEN',
            'category' => 'PROHIBITED_PRODUCT',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]))->assertOk()->assertSeeText($violation->reference);
    }

    public function test_admin_actions_use_the_service_and_closed_cases_reject_changes(): void
    {
        [$admin, , $violation] = $this->fixture('action-seller');

        $this->actingAs($admin)->patch(route('superadmin.compliance.severity', $violation), [
            'severity' => 'CRITICAL',
            'reason' => 'Escalated after review.',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('superadmin.compliance.warnings.store', $violation), [
            'message' => 'Remove the violating content.',
        ])->assertRedirect();

        $this->assertDatabaseHas('seller_violations', ['id' => $violation->id, 'severity' => 'CRITICAL']);
        $this->assertDatabaseHas('seller_warnings', ['seller_violation_id' => $violation->id]);

        $this->actingAs($admin)->post(route('superadmin.compliance.resolve', $violation), [
            'resolution_notes' => 'Seller corrected the issue.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('superadmin.compliance.warnings.store', $violation), [
            'message' => 'Late warning.',
        ])->assertSessionHasErrors('status');

        $this->assertSame(4, $violation->events()->count());
    }

    public function test_admin_can_dismiss_a_case_and_view_complete_history(): void
    {
        [$admin, , $violation] = $this->fixture('history-seller');

        $this->actingAs($admin)->post(route('superadmin.compliance.dismiss', $violation), [
            'resolution_notes' => 'Review found no marketplace violation.',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('superadmin.compliance.show', $violation))
            ->assertOk()
            ->assertSeeText('Violation Created')
            ->assertSeeText('Case Dismissed')
            ->assertSeeText('Review found no marketplace violation.');
    }

    public function test_evidence_is_validated_stored_privately_and_added_to_history(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        [$admin, , $violation] = $this->fixture('evidence-seller');

        $this->actingAs($admin)->post(route('superadmin.compliance.evidence.store', $violation), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            'description' => 'Supplier documentation.',
        ])->assertRedirect();

        $evidence = $violation->evidence()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->path);
        Storage::disk('public')->assertMissing($evidence->path);
        $this->assertSame('proof.pdf', $evidence->original_name);
        $this->assertSame('application/pdf', $evidence->mime_type);
        $this->assertSame(64, strlen($evidence->checksum));
        $this->assertStringNotContainsString('proof.pdf', $evidence->path);
        $this->assertDatabaseHas('seller_compliance_events', [
            'seller_violation_id' => $violation->id,
            'action' => 'EVIDENCE_ATTACHED',
        ]);
    }

    public function test_unsafe_and_oversized_evidence_is_rejected(): void
    {
        Storage::fake('local');
        [$admin, , $violation] = $this->fixture('unsafe-evidence-seller');

        $this->actingAs($admin)->post(route('superadmin.compliance.evidence.store', $violation), [
            'evidence' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('evidence');

        $this->actingAs($admin)->post(route('superadmin.compliance.evidence.store', $violation), [
            'evidence' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('evidence');

        $this->assertSame(0, $violation->evidence()->count());
        $this->assertSame(1, $violation->events()->count());
    }

    public function test_evidence_download_is_admin_only_and_scoped_to_its_case(): void
    {
        Storage::fake('local');
        [$admin, , $violation] = $this->fixture('download-evidence-seller');
        [, , $otherViolation] = $this->fixture('other-evidence-seller');

        $this->actingAs($admin)->post(route('superadmin.compliance.evidence.store', $violation), [
            'evidence' => UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg'),
        ]);
        $evidence = $violation->evidence()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('superadmin.compliance.evidence.show', [$violation, $evidence]))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($admin)
            ->get(route('superadmin.compliance.evidence.download', [$violation, $evidence]))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($admin)
            ->get(route('superadmin.compliance.evidence.show', [$otherViolation, $evidence]))
            ->assertNotFound();

        $sellerUser = $this->userWithRole('seller');
        $this->actingAs($sellerUser)
            ->get(route('superadmin.compliance.evidence.show', [$violation, $evidence]))
            ->assertForbidden();
    }

    public function test_closed_cases_reject_evidence_uploads_and_do_not_leave_files(): void
    {
        Storage::fake('local');
        [$admin, , $violation] = $this->fixture('closed-evidence-seller');
        app(SellerComplianceService::class)->resolve($violation, $admin, 'Case completed.');

        $this->actingAs($admin)->post(route('superadmin.compliance.evidence.store', $violation), [
            'evidence' => UploadedFile::fake()->create('late.pdf', 20, 'application/pdf'),
        ])->assertSessionHasErrors('status');

        $this->assertSame(0, $violation->evidence()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function fixture(string $slug): array
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller($slug);
        $violation = app(SellerComplianceService::class)->createViolation(
            $seller,
            $admin,
            SellerViolationCategory::Other,
            SellerViolationSeverity::Medium,
            'Compliance interface fixture.'
        );

        return [$admin, $seller, $violation];
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
        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'admin-compliance-category'],
            ['name' => 'Admin Compliance Category', 'is_active' => true]
        );

        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id)->toString(),
            'description' => 'Compliance admin product fixture.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
    }
}
