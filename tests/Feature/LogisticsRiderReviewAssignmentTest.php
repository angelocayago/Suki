<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{Address, Application, LogisticsHub, LogisticsProvider, Order, Rider, RiderServiceArea, Seller, SellerOrder, Shipment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Notification, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class LogisticsRiderReviewAssignmentTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    private User $actor;
    private LogisticsProvider $provider;
    private LogisticsProvider $other;
    private Application $application;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Memory-only fixtures required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
        Storage::fake('local'); Storage::fake('public'); Mail::fake(); Notification::fake();
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit', 'adminsAfterCommit')->andReturnNull();
        $this->actor = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $this->provider = LogisticsProvider::create(['user_id' => $this->actor->id, 'name' => 'Own Provider', 'slug' => 'own-provider', 'status' => 'approved']);
        $this->other = LogisticsProvider::create(['user_id' => User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false])->id, 'name' => 'Other Provider', 'slug' => 'other-provider', 'status' => 'approved']);
        $applicant = User::factory()->create(['role' => 'rider', 'status' => 'pending', 'first_name' => 'Pending', 'last_name' => 'Applicant']);
        Storage::disk('local')->put('application-documents/riders/government-ids/fixture.pdf', '%PDF-1.4 fixture');
        $this->application = Application::forceCreate(['id' => 41, 'user_id' => $applicant->id, 'application_type' => 'courier', 'status' => 'pending',
            'application_data' => ['vehicle_type' => 'Motorcycle', 'plate_number' => 'TEST-41', 'license_number' => 'LICENSE-41', 'address' => 'Applicant Street'],
            'documents' => ['government_id' => 'application-documents/riders/government-ids/fixture.pdf']]);
        $this->actingAs($this->actor);
    }

    public function test_database_application_id_survives_list_normalization_and_review_opens_correct_application(): void
    {
        $url = route('logistics.riders.review', 41);
        $this->get(route('logistics.riders'))->assertOk()->assertSee($url, false)->assertDontSee('/logistics/riders/0/review', false);
        $this->get($url)->assertOk()->assertSee('Pending')->assertSee('Applicant Street')->assertSee('LICENSE-41')
            ->assertSee(route('logistics.riders.approve', 41), false)->assertSee(route('logistics.riders.disapprove', 41), false)
            ->assertSee(route('logistics.riders.documents.show', [41, 'government_id']), false);
        $this->assertSame('pending', $this->application->fresh()->status);
        $this->assertDatabaseCount('riders', 0);
    }

    public function test_review_documents_use_private_storage_and_provider_authorization(): void
    {
        $url = route('logistics.riders.documents.show', [41, 'government_id']);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('logistics.riders.documents.show', [41, 'unknown']))->assertNotFound();
        $this->application->update(['application_data' => ['logistics_provider_id' => $this->other->id]]);
        $this->get($url)->assertNotFound();
    }

    public function test_foreign_provider_associations_and_explicit_application_targets_are_inaccessible(): void
    {
        $this->application->update(['application_data' => ['logistics_provider_id' => $this->other->id]]);
        $this->assertForeignApplicationBlocked();
        $this->application->update(['application_data' => []]);
        Rider::create(['user_id' => $this->application->user_id, 'logistics_provider_id' => $this->other->id, 'vehicle_type' => 'Bike', 'plate_no' => 'FOREIGN', 'is_active' => false]);
        $this->assertForeignApplicationBlocked();
        $this->actingAs(User::findOrFail($this->other->user_id))->get(route('logistics.riders.review', 41))->assertOk();
    }

    private function assertForeignApplicationBlocked(): void
    {
        $this->get(route('logistics.riders'))->assertOk()->assertDontSee(route('logistics.riders.review', 41), false);
        $this->get(route('logistics.riders.review', 41))->assertNotFound();
        $this->post(route('logistics.riders.approve', 41))->assertNotFound();
        $this->post(route('logistics.riders.disapprove', 41), ['rejection_reason' => 'Not ours'])->assertNotFound();
        $this->assertSame('pending', $this->application->fresh()->status);
    }

    public function test_invalid_and_missing_application_ids_and_non_courier_applications_are_safe(): void
    {
        foreach (['0', '999', 'invalid', '-1', '999999999999999999999999999999'] as $id) {
            $this->get(route('logistics.riders.review', $id))->assertNotFound();
            $this->post(route('logistics.riders.approve', $id))->assertNotFound();
            $this->post(route('logistics.riders.disapprove', $id))->assertNotFound();
        }
        $this->application->update(['application_type' => 'seller']);
        $this->get(route('logistics.riders.review', 41))->assertNotFound();
    }

    public function test_approval_preserves_activation_and_verification_and_cannot_be_taken_over(): void
    {
        $this->post(route('logistics.riders.approve', 41))->assertRedirect(route('logistics.riders'));
        $rider = Rider::where('user_id', $this->application->user_id)->sole();
        $this->assertSame($this->provider->id, $rider->logistics_provider_id);
        $this->assertFalse($rider->is_active);
        $this->assertNotNull($rider->approved_at);
        $this->assertSame('pending', $rider->user->status);
        $this->assertNotNull($rider->user->verification_required_at);
        $this->assertSame('approved', $this->application->fresh()->status);
        Mail::assertSent(\App\Mail\RiderActivationMail::class);
        $this->assertDatabaseCount('rider_activation_tokens', 1);
        $this->actingAs(User::findOrFail($this->other->user_id))->get(route('logistics.riders.review', 41))->assertNotFound();
        $this->post(route('logistics.riders.approve', 41))->assertNotFound();
        $this->actingAs($this->actor)->post(route('logistics.riders.approve', 41))->assertNotFound();
        $this->assertDatabaseCount('riders', 1);
    }

    public function test_disapproval_validates_reason_persists_status_and_updates_management_counts(): void
    {
        $this->from(route('logistics.riders.review', 41))->post(route('logistics.riders.disapprove', 41), ['rejection_reason' => str_repeat('x', 501)])->assertSessionHasErrors('rejection_reason');
        $this->post(route('logistics.riders.disapprove', 41), ['rejection_reason' => 'Missing requirement'])->assertRedirect(route('logistics.riders'));
        $this->assertSame('rejected', $this->application->fresh()->status);
        $this->assertSame('Missing requirement', $this->application->fresh()->rejection_reason);
        $this->assertSame('rejected', $this->application->user->fresh()->status);
        $this->get(route('logistics.riders'))->assertOk()->assertViewHas('applications', fn ($apps) => $apps[41]['status'] === 'disapproved');
        $this->actingAs(User::findOrFail($this->other->user_id))->get(route('logistics.riders.review', 41))->assertNotFound();
    }

    public function test_unapproved_inactive_unverified_and_wrong_role_accounts_cannot_decide(): void
    {
        $this->provider->update(['status' => 'pending']);
        $this->post(route('logistics.riders.approve', 41))->assertForbidden();
        $this->provider->update(['status' => 'approved']);
        $this->actor->update(['status' => 'pending']);
        $this->actingAs($this->actor)->post(route('logistics.riders.approve', 41))->assertForbidden();
        $this->actor->update(['status' => 'active', 'verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null]);
        $this->actingAs($this->actor)->post(route('logistics.riders.approve', 41))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->post(route('logistics.riders.approve', 41))->assertForbidden();
        $this->assertSame('pending', $this->application->fresh()->status);
    }

    private function rider(): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false]);
        $hub = LogisticsHub::create(['logistics_provider_id' => $this->provider->id, 'name' => 'Pickup Hub', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'is_active' => true]);
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $this->provider->id, 'logistics_hub_id' => $hub->id, 'vehicle_type' => 'Bike', 'plate_no' => 'PICKUP', 'is_active' => true, 'availability' => 'ONLINE', 'approved_at' => now()]);
        RiderServiceArea::create(['rider_id' => $rider->id, 'logistics_provider_id' => $this->provider->id, 'region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => null, 'area_key' => RiderServiceArea::key('01', '0101', '010101', null), 'is_active' => true]);
        return $rider;
    }

    private function parcel(): array
    {
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $address = Address::create(['user_id' => $sellerUser->id, 'label' => 'Pickup', 'recipient' => 'Seller', 'phone' => '09123456789', 'line1' => 'Street', 'city' => 'City', 'province' => 'Province', 'barangay' => 'Barangay', 'region_code' => '01', 'city_municipality_code' => '010101', 'barangay_code' => '010101001', 'is_default' => true]);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Pickup Seller', 'slug' => 'pickup-seller', 'status' => 'approved', 'pickup_address_id' => $address->id]);
        $order = Order::create(['order_number' => 'PICKUP-TEST', 'buyer_id' => User::factory()->create()->id, 'status' => 'READY_FOR_PICKUP', 'subtotal' => 100, 'shipping_fee' => 50, 'total_amount' => 150, 'recipient_name' => 'Buyer', 'recipient_phone' => '09123456789', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street']);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $this->provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => 'ready_to_ship', 'workflow_status' => OrderStatus::ReadyForPickup]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $this->provider->id, 'tracking_code' => 'PICKUP-TRACK', 'status' => 'unassigned', 'workflow_status' => OrderStatus::ReadyForPickup, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0]);
        return [$sellerOrder, $shipment];
    }

    public static function exclusions(): array
    {
        return array_map(fn ($case) => [$case], ['pending', 'inactive', 'suspended', 'offline', 'unverified', 'foreign', 'no-area', 'wrong-area', 'inactive-area', 'no-hub', 'inactive-hub', 'wrong-hub']);
    }

    #[DataProvider('exclusions')]
    public function test_ineligible_riders_are_hidden_and_forged_assignment_is_rejected(string $case): void
    {
        $rider = $this->rider(); [$sellerOrder, $shipment] = $this->parcel();
        match ($case) {
            'pending' => $rider->update(['approved_at' => null, 'is_active' => false]),
            'inactive' => $rider->update(['is_active' => false]),
            'suspended' => $rider->user->update(['is_suspended' => true]),
            'offline' => $rider->update(['availability' => 'OFFLINE']),
            'unverified' => $rider->user->update(['verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null]),
            'foreign' => $rider->update(['logistics_provider_id' => $this->other->id]),
            'no-area' => $rider->serviceAreas()->delete(),
            'wrong-area' => $rider->serviceAreas()->update(['city_municipality_code' => 'OTHER']),
            'inactive-area' => $rider->serviceAreas()->update(['is_active' => false]),
            'no-hub' => $rider->update(['logistics_hub_id' => null]),
            'inactive-hub' => $rider->logisticsHub->update(['is_active' => false]),
            'wrong-hub' => $rider->logisticsHub->update(['barangay_code' => 'OTHER']),
        };
        $this->get(route('logistics.assignments'))->assertOk()->assertSee('No eligible pickup riders.')->assertDontSee('name="pickup_rider_id"', false);
        $this->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $rider->id])->assertStatus($case === 'foreign' ? 404 : 302);
        $this->assertNull($shipment->fresh()->pickup_rider_id);
        $this->assertSame('PICKUP-TRACK', $shipment->fresh()->tracking_code);
        $this->assertDatabaseCount('delivery_events', 0);
    }

    public function test_eligible_rider_is_selectable_and_assignment_preserves_tracking_and_acceptance_flow(): void
    {
        $rider = $this->rider(); [$sellerOrder, $shipment] = $this->parcel();
        $this->get(route('logistics.assignments'))->assertOk()->assertSee('value="'.$rider->id.'"', false)->assertSee('name="pickup_rider_id"', false);
        $this->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $rider->id])->assertSessionHasNoErrors();
        $this->assertSame($rider->id, $shipment->fresh()->pickup_rider_id);
        $this->assertNull($shipment->fresh()->pickup_accepted_at);
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->fresh()->workflow_status);
        $this->assertSame('PICKUP-TRACK', $shipment->fresh()->tracking_code);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'PICKUP_RIDER_ASSIGNED']);
    }

    public function test_pickup_confirmation_preserves_route_csrf_identifiers_and_displays_errors_without_writes(): void
    {
        $rider = $this->rider(); [$sellerOrder, $shipment] = $this->parcel();
        $before = $shipment->fresh()->getRawOriginal();
        $this->withSession(['_token' => 'test-csrf', 'success' => 'Previous assignment saved.'])->get(route('logistics.assignments'))
            ->assertOk()->assertSee('Confirm Pickup Assignment')->assertSee('data-pickup-assignment', false)
            ->assertSee('data-order="PICKUP-TEST"', false)->assertSee('data-pickup-location="Street, Barangay, City, Province"', false)
            ->assertSee(route('logistics.database.pickup-rider.assign', $sellerOrder), false)
            ->assertSee('name="_token"', false)->assertSee('test-csrf')->assertSee('Previous assignment saved.')
            ->assertDontSee("return confirm('Assign this pickup rider?')", false);
        $this->assertSame($before, $shipment->fresh()->getRawOriginal());
        $this->assertDatabaseCount('delivery_events', 0);
        $url = route('logistics.database.pickup-rider.assign', $sellerOrder);
        $this->from(route('logistics.assignments'))->post($url, ['pickup_rider_id' => 999999])->assertNotFound();
        $sellerOrder->update(['workflow_status' => OrderStatus::Placed]);
        $this->from(route('logistics.assignments'))->post($url, ['pickup_rider_id' => $rider->id])->assertSessionHasErrors('order');
        $this->get(route('logistics.assignments'))->assertOk()->assertSee('The assignment could not be saved.')->assertSee('Only an active ready-for-pickup parcel may be assigned.');
        $this->assertNull($shipment->fresh()->pickup_rider_id);
        $this->assertDatabaseCount('delivery_events', 0);
    }
}
