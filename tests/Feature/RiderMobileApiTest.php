<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderCodRemittance;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DeliveryProofFile;
use Tests\TestCase;

/**
 * Rider mobile API (/api/rider/*): token scoping, assignment ownership,
 * token-bound single-use scan verification, server-side COD and parity with
 * the web Rider rules.
 */
class RiderMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_login_issues_rider_scoped_token_that_cannot_reach_buyer_routes(): void
    {
        extract($this->context());
        $login = $this->postJson('/api/login', ['login' => $deliveryUser->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('user.role', 'rider');
        $token = $login->json('token');
        $this->assertSame(['rider'], $deliveryUser->tokens()->first()->abilities);
        $this->assertNotNull($deliveryUser->tokens()->first()->expires_at);

        $this->api($token, 'GET', '/api/user')->assertOk()->assertJsonPath('role', 'rider');
        $this->api($token, 'GET', '/api/rider/dashboard')->assertOk()->assertJsonPath('success', true);
        $this->api($token, 'GET', '/api/buyer/cart')->assertForbidden();
        $this->api($token, 'GET', '/api/v1/buyer/orders')->assertForbidden();
    }

    public function test_buyer_tokens_and_v1_login_cannot_reach_rider_services(): void
    {
        extract($this->context());
        $buyer = $sellerOrder->order->buyer;
        $buyerToken = $this->postJson('/api/login', ['login' => $buyer->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.role', 'buyer')->json('token');
        $this->api($buyerToken, 'GET', '/api/rider/deliveries')->assertForbidden();
        // Legacy wildcard tokens of a buyer account are still not a rider.
        $this->api($buyer->createToken('legacy')->plainTextToken, 'GET', '/api/rider/deliveries')->assertForbidden();
        // A wildcard token of a rider account is not accepted for Rider services either.
        $this->api($deliveryUser->createToken('legacy')->plainTextToken, 'GET', '/api/rider/deliveries')->assertForbidden();

        $this->postJson('/api/v1/login', ['login' => $deliveryUser->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_ineligible_rider_cannot_sign_in_or_use_an_existing_token(): void
    {
        extract($this->context());
        $token = $this->riderToken($deliveryUser);
        $provider->update(['status' => 'suspended']);

        $this->postJson('/api/login', ['login' => $deliveryUser->email, 'password' => 'password'])->assertForbidden();
        $this->api($token, 'GET', '/api/rider/deliveries')->assertForbidden();
    }

    public function test_rider_sees_only_own_assignments_and_cannot_act_on_foreign_parcels(): void
    {
        extract($this->context());
        [$otherUser] = $this->rider($provider, 'other');
        $mine = $this->api($this->riderToken($deliveryUser), 'GET', '/api/rider/deliveries')->assertOk()->json('data.orders');
        $this->assertSame([$sellerOrder->id], array_column($mine, 'id'));
        $this->assertSame(15000, $mine[0]['cod_amount_minor']);

        $otherToken = $this->riderToken($otherUser);
        $this->assertSame([], $this->api($otherToken, 'GET', '/api/rider/deliveries')->json('data.orders'));
        $this->api($otherToken, 'POST', "/api/rider/erp-orders/{$sellerOrder->id}/accept-delivery")->assertForbidden();
        $this->api($otherToken, 'POST', '/api/rider/shipment-scanner/resolve', ['tracking_code' => $shipment->tracking_code])->assertNotFound();
        $this->api($otherToken, 'POST', "/api/rider/shipment-scanner/{$shipment->id}/action", ['action' => 'accept_delivery'])->assertNotFound();
        $this->assertNull($shipment->fresh()->delivery_accepted_at);
    }

    public function test_scanner_actions_require_a_fresh_exact_single_use_token_bound_verification(): void
    {
        extract($this->context());
        $token = $this->riderToken($deliveryUser);
        $actionUri = "/api/rider/shipment-scanner/{$shipment->id}/action";

        // No scan at all.
        $this->api($token, 'POST', $actionUri, ['action' => 'accept_delivery'])->assertStatus(422)->assertJsonValidationErrors('action');

        // A tampered signed payload is rejected.
        $payload = app(ShipmentQrService::class)->payload($shipment);
        $this->api($token, 'POST', '/api/rider/shipment-scanner/resolve', ['payload' => substr($payload, 0, -1).(str_ends_with($payload, 'a') ? 'b' : 'a')])->assertStatus(422);

        $resolved = $this->api($token, 'POST', '/api/rider/shipment-scanner/resolve', ['payload' => $payload])->assertOk();
        $resolved->assertJsonPath('data.allowed_action', 'accept_delivery')->assertJsonPath('data.mode', 'last_mile_dispatch');

        // The verification belongs to the token that scanned, not to the account.
        $secondDevice = $this->riderToken($deliveryUser);
        $this->api($secondDevice, 'POST', $actionUri, ['action' => 'accept_delivery'])->assertStatus(422);

        // An action other than the one the state machine allows is refused and changes nothing.
        $this->api($token, 'POST', $actionUri, ['action' => 'begin_delivery'])->assertStatus(422);
        $this->assertNull($shipment->fresh()->delivery_accepted_at);

        $this->api($token, 'POST', $actionUri, ['action' => 'accept_delivery'])->assertOk()->assertJsonPath('data.allowed_action', null);
        $this->assertNotNull($shipment->fresh()->delivery_accepted_at);

        // Replay of the consumed verification is rejected; a new scan is required.
        $this->api($token, 'POST', $actionUri, ['action' => 'begin_delivery'])->assertStatus(422);
        $this->assertSame(OrderStatus::AssignedToRider, $sellerOrder->fresh()->workflow_status);

        $this->api($token, 'POST', '/api/rider/shipment-scanner/resolve', ['tracking_code' => $shipment->tracking_code])
            ->assertOk()->assertJsonPath('data.allowed_action', 'begin_delivery');
        $this->api($token, 'POST', $actionUri, ['action' => 'begin_delivery'])->assertOk();
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->fresh()->workflow_status);
    }

    public function test_status_endpoint_never_bypasses_the_scanner(): void
    {
        extract($this->context());
        $token = $this->riderToken($deliveryUser);
        foreach (['PICKED_UP', 'AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'] as $status) {
            $this->api($token, 'POST', "/api/rider/erp-orders/{$sellerOrder->id}/status", ['status' => $status])
                ->assertStatus(422)->assertJsonValidationErrors('status');
        }
        $this->assertSame(OrderStatus::AssignedToRider, $sellerOrder->fresh()->workflow_status);
    }

    public function test_delivery_proof_records_server_side_cod_and_is_idempotent(): void
    {
        Storage::fake('delivery_proofs');
        extract($this->context(OrderStatus::OutForDelivery));
        $shipment->update(['delivery_accepted_at' => now()]);
        $token = $this->riderToken($deliveryUser);
        $uri = "/api/rider/erp-orders/{$sellerOrder->id}/delivery-proof";

        // COD must be explicitly confirmed for a COD parcel.
        $this->api($token, 'POST', $uri, ['received_by' => 'Buyer', 'proof_photo' => DeliveryProofFile::image()])->assertStatus(422);

        [$otherUser] = $this->rider($provider, 'intruder');
        $this->api($this->riderToken($otherUser), 'POST', $uri, ['received_by' => 'Buyer', 'cod_collected' => '1', 'proof_photo' => DeliveryProofFile::image()])->assertForbidden();

        $this->api($token, 'POST', $uri, [
            'received_by' => 'Buyer', 'cod_collected' => '1', 'proof_photo' => DeliveryProofFile::image(),
            // Browser/mobile financial values are ignored.
            'cod_amount_minor' => 1, 'cod_collected_minor' => 1, 'expected_minor' => 1,
        ])->assertOk()->assertJsonPath('data.order.status', 'delivered');

        $this->assertSame(15000, (int) $shipment->fresh()->cod_collected_minor);
        $this->assertSame(15000, (int) RiderCodRemittance::query()->where('shipment_id', $shipment->id)->value('expected_minor'));

        $this->api($token, 'POST', $uri, ['received_by' => 'Buyer', 'cod_collected' => '1', 'proof_photo' => DeliveryProofFile::image()])->assertOk();
        $this->assertSame(1, RiderCodRemittance::query()->where('shipment_id', $shipment->id)->count());

        $performance = $this->api($token, 'GET', '/api/rider/earnings')->assertOk()->json('data.performance');
        $this->assertSame(1, $performance['completedDeliveries']);
        $this->assertSame(15000, $performance['codCollectedMinor']);

        // Web rider performance uses the same user-id based delivered_by.
        $this->actingAs($deliveryUser)->get(route('rider.earnings'))->assertOk()
            ->assertViewHas('performance', fn (array $p) => $p['completedDeliveries'] === 1 && (int) $p['codCollectedMinor'] === 15000);

        $remittance = RiderCodRemittance::query()->where('shipment_id', $shipment->id)->firstOrFail();
        $this->api($this->riderToken($otherUser), 'POST', "/api/rider/cod-remittances/{$remittance->id}", ['reference' => 'X'])->assertNotFound();
        $this->api($token, 'POST', "/api/rider/cod-remittances/{$remittance->id}", ['reference' => 'HUB-CASH-1'])
            ->assertOk()->assertJsonPath('data.remittance.status', 'SUBMITTED')->assertJsonPath('data.remittance.submitted_minor', 15000);
        $this->api($token, 'POST', "/api/rider/cod-remittances/{$remittance->id}", ['reference' => 'HUB-CASH-2', 'amount_minor' => 1])->assertOk();
        $this->assertSame('HUB-CASH-1', $remittance->fresh()->reference);
    }

    public function test_cod_review_accepts_both_team_vocabularies_and_rejects_arbitrary_statuses(): void
    {
        Storage::fake('delivery_proofs');
        extract($this->context(OrderStatus::OutForDelivery));
        $shipment->update(['delivery_accepted_at' => now()]);
        $token = $this->riderToken($deliveryUser);
        $this->api($token, 'POST', "/api/rider/erp-orders/{$sellerOrder->id}/delivery-proof", ['received_by' => 'Buyer', 'cod_collected' => '1', 'proof_photo' => DeliveryProofFile::image()])->assertOk();
        $remittance = RiderCodRemittance::query()->where('shipment_id', $shipment->id)->firstOrFail();
        $service = app(\App\Services\CodRemittanceService::class);

        // Original call shape: (remittance, actor, reference).
        $service->submitRider($remittance, $deliveryUser, 'LEGACY-REF');
        $this->assertSame(['SUBMITTED', 15000, 'LEGACY-REF'], [$remittance->fresh()->status, (int) $remittance->fresh()->submitted_minor, $remittance->fresh()->reference]);

        $owner = $provider->owner ?? User::findOrFail($provider->user_id);
        try {
            $service->reviewRider($remittance, $owner, 'PAID', null);
            $this->fail('An arbitrary review status must be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
        }
        $this->assertSame('SUBMITTED', $remittance->fresh()->status);

        $service->reviewRider($remittance, $owner, 'ACCEPTED', null);
        $this->assertSame(['ACCEPTED', 15000], [$remittance->fresh()->status, (int) $remittance->fresh()->accepted_minor]);
        // Idempotent across vocabularies.
        $service->reviewRider($remittance, $owner, 'VERIFIED', null);
        $this->assertSame('ACCEPTED', $remittance->fresh()->status);
    }

    public function test_profile_changes_cannot_bypass_verified_rider_details_and_password_change_revokes_other_tokens(): void
    {
        extract($this->context());
        $token = $this->riderToken($deliveryUser);
        $other = $this->riderToken($deliveryUser);
        $rider = Rider::query()->where('user_id', $deliveryUser->id)->firstOrFail();

        $this->api($token, 'PATCH', '/api/rider/profile', ['first_name' => 'New', 'last_name' => 'Name', 'plate_number' => 'FAKE-1'])
            ->assertStatus(422)->assertJsonValidationErrors('plate_number');
        $this->api($token, 'PATCH', '/api/rider/profile', ['first_name' => 'New', 'last_name' => 'Name', 'phone' => '09990000000'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->api($token, 'PATCH', '/api/rider/profile', ['first_name' => 'New', 'last_name' => 'Name', 'plate_number' => $rider->plate_no])
            ->assertOk()->assertJsonPath('data.profile.first_name', 'New');

        $this->api($token, 'POST', '/api/rider/profile/password', ['current_password' => 'password', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])->assertOk();
        $this->api($other, 'GET', '/api/rider/profile')->assertUnauthorized();
        $this->api($token, 'GET', '/api/rider/profile')->assertOk();
    }

    private function api(string $token, string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();

        // call() extracts UploadedFile values from $data into the request files.
        return $this->call($method, $uri, $data, [], [], $this->transformHeadersToServerVars([
            'Authorization' => 'Bearer '.$token, 'Accept' => 'application/json',
        ]));
    }

    private function riderToken(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/login', ['login' => $user->email, 'password' => 'password'])->assertOk()->json('token');
    }

    private function context(OrderStatus $status = OrderStatus::AssignedToRider): array
    {
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $this->role($logisticsUser, 'logistics');
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => 'Mobile Provider', 'slug' => 'mobile-provider-'.uniqid(), 'status' => 'approved']);
        [, , $pickupRider] = $this->rider($provider, 'pickup');
        [$deliveryUser, , $deliveryRider] = $this->rider($provider, 'delivery');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $this->role($buyer, 'buyer'); $this->role($sellerUser, 'seller');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Mobile Store', 'slug' => 'mobile-store-'.uniqid(), 'status' => 'approved']);
        $pickup = Address::create(['user_id' => $sellerUser->id, 'label' => 'Pickup', 'recipient' => 'Store', 'phone' => '0912', 'line1' => 'Street', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'is_default' => true]);
        $seller->update(['pickup_address_id' => $pickup->id]);
        $order = Order::create(['order_number' => 'MOB-'.uniqid(), 'reference' => 'MOB-'.uniqid(), 'buyer_id' => $buyer->id, 'seller_id' => $sellerUser->id, 'status' => $status->value, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'total_minor' => 15000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'municipality' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => $status->legacySellerStatus(), 'workflow_status' => $status]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'pickup_rider_id' => $pickupRider->id, 'rider_id' => $deliveryRider->id, 'tracking_code' => 'SUKI-MOB-'.strtoupper(uniqid()), 'status' => $status->legacyShipmentStatus(), 'workflow_status' => $status, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0, 'pickup_accepted_at' => now()]);
        $shipment->assignments()->create(['logistics_provider_id' => $provider->id, 'assignment_type' => 'PICKUP', 'status' => 'COMPLETED', 'rider_id' => $pickupRider->id, 'assigned_by' => $logisticsUser->id, 'responded_at' => now()]);
        $shipment->assignments()->create(['logistics_provider_id' => $provider->id, 'assignment_type' => 'DELIVERY', 'status' => $status === OrderStatus::AssignedToRider ? 'ASSIGNED' : 'ACCEPTED', 'rider_id' => $deliveryRider->id, 'assigned_by' => $logisticsUser->id, 'responded_at' => $status === OrderStatus::AssignedToRider ? null : now()]);

        return compact('provider', 'deliveryUser', 'deliveryRider', 'sellerOrder', 'shipment');
    }

    private function rider(LogisticsProvider $provider, string $key): array
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false]);
        $this->role($user, 'rider');
        $rider = Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => strtoupper($key).'-'.uniqid(), 'is_active' => true, 'availability' => 'ONLINE', 'approved_at' => now()]);

        return [$user, $provider, $rider];
    }

    private function role(User $user, string $role): void
    {
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);
    }
}
