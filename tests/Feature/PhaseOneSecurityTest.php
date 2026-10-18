<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Category;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\ApplicationDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseOneSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => $status,
            'is_suspended' => false,
        ]);
        $roleModel = Role::firstOrCreate(['name' => $role]);
        $user->roles()->syncWithoutDetaching([$roleModel->id]);

        return $user;
    }

    public function test_pending_account_cannot_log_in(): void
    {
        $user = $this->userWithRole('buyer', 'pending');

        $response = $this->post(route('login.submit'), [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_role_cannot_access_logistics_portal(): void
    {
        $buyer = $this->userWithRole('buyer');

        $this->actingAs($buyer)
            ->get(route('logistics.dashboard'))
            ->assertForbidden();
    }

    public function test_seller_registration_creates_pending_persistent_application(): void
    {
        Storage::fake('seller_documents');
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '01', 'name' => 'Region A']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
        ]);
        $category = Category::query()->create([
            'name' => 'Fashion',
            'slug' => 'fashion',
            'position' => 1,
            'is_active' => true,
        ]);

        $response = $this->post(route('seller.register.submit'), [
            'first_name' => 'Sela',
            'last_name' => 'Merchant',
            'middle_initial' => 'M',
            'sex' => 'Female',
            'birthday' => '1995-05-15',
            'phone' => '09170000001',
            'email' => 'seller-applicant@example.com',
            'region' => '01',
            'province' => '0101',
            'municipality' => '010101',
            'barangay' => '010101001',
            'address' => '10 Market Street',
            'business_name' => 'Sela Store',
            'category_id' => $category->id,
            'seller_type' => 'business',
            'tin' => '123-456-789-000',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 100, 'application/pdf'),
            'password' => 'SellerPass1!',
            'password_confirmation' => 'SellerPass1!',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.contact.show'));
        $user = User::where('email', 'seller-applicant@example.com')->firstOrFail();

        $this->assertSame('pending', $user->status);
        $this->assertDatabaseHas('sellers', ['user_id' => $user->id, 'status' => 'pending']);
        $this->assertDatabaseHas('applications', [
            'user_id' => $user->id,
            'application_type' => 'seller',
            'status' => 'pending',
        ]);
    }

    public function test_admin_approval_activates_seller_and_records_reviewer(): void
    {
        Notification::fake();
        $admin = $this->userWithRole('admin');
        $sellerUser = $this->userWithRole('seller', 'pending');
        Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Pending Shop',
            'slug' => 'pending-shop',
            'status' => 'pending',
        ]);
        $application = Application::create([
            'user_id' => $sellerUser->id,
            'application_type' => 'seller',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('superadmin.applications.approve', $application))
            ->assertRedirect();

        $this->assertSame('active', $sellerUser->fresh()->status);
        $this->assertSame('approved', $sellerUser->sellers()->first()->status);
        $this->assertSame($admin->id, $application->fresh()->reviewed_by);
        Notification::assertSentTo($sellerUser, ApplicationDecisionNotification::class);
    }

    public function test_admin_can_open_the_protected_application_queue(): void
    {
        $admin = $this->userWithRole('admin');
        $applicant = $this->userWithRole('buyer', 'pending');
        Application::create([
            'user_id' => $applicant->id,
            'application_type' => 'buyer',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('superadmin.applications'))
            ->assertOk();
    }

    public function test_suspended_account_is_removed_from_an_approved_portal_session(): void
    {
        $logistics = $this->userWithRole('logistics');
        $logistics->update(['is_suspended' => true]);

        $this->actingAs($logistics)
            ->get(route('logistics.dashboard'))
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_buyer_password_change_verifies_and_persists_the_credential(): void
    {
        $buyer = $this->userWithRole('buyer');

        $this->actingAs($buyer)
            ->post(route('buyer.account.password'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('buyer.account'));

        $this->assertTrue(Hash::check('new-password-123', $buyer->fresh()->password));
    }

    public function test_logistics_approval_creates_active_rider_for_its_provider(): void
    {
        Notification::fake();
        $logisticsUser = $this->userWithRole('logistics');
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Area Logistics',
            'slug' => 'area-logistics',
            'status' => 'approved',
        ]);
        $riderUser = $this->userWithRole('rider', 'pending');
        $application = Application::create([
            'user_id' => $riderUser->id,
            'application_type' => 'courier',
            'status' => 'pending',
            'application_data' => [
                'vehicle_type' => 'Motorcycle',
                'plate_number' => 'ABC-1234',
            ],
        ]);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.riders.approve', $application->id))
            ->assertRedirect(route('logistics.riders'));

        $this->assertSame('pending', $riderUser->fresh()->status);
        $this->assertDatabaseHas('riders', [
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'plate_no' => 'ABC-1234',
            'is_active' => false,
        ]);
        Notification::assertSentTo($riderUser, ApplicationDecisionNotification::class);
    }

    public function test_order_status_endpoint_enforces_owner_role_and_next_transition(): void
    {
        $seller = $this->userWithRole('seller');
        $buyer = $this->userWithRole('buyer');
        $otherBuyer = $this->userWithRole('buyer');
        $order = Order::create([
            'order_number' => 'SKI-SECURITY-001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'PLACED',
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09170000002',
            'province' => 'Laguna',
            'municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion',
            'street_address' => '20 Main Street',
        ]);

        $this->actingAs($otherBuyer)
            ->post(route('order.status.update', $order->order_number), ['status' => 'completed'])
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('order.status.update', $order->order_number), ['status' => 'confirmed'])
            ->assertRedirect();

        $this->assertSame('CONFIRMED', $order->fresh()->status);
    }
}
