<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\LogisticsHub;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseSixShipmentScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_logistics_can_resolve_its_signed_shipment_qr(): void
    {
        extract($this->context());

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertOk()
            ->assertSee($shipment->tracking_code)
            ->assertSee('Shipment Scanner')
            ->assertSee('id="start-camera"', false)
            ->assertSee('id="stop-camera"', false)
            ->assertSee('id="camera-status"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_logistics_cannot_resolve_another_providers_shipment(): void
    {
        extract($this->context());
        [, $otherProvider] = $this->provider('other');
        $shipment->update(['logistics_provider_id' => $otherProvider->id]);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $this->payload($shipment->fresh())])
            ->assertForbidden();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $shipment->sellerOrder), [
                'status' => 'sorted',
                'sorting_area' => 'Wrong Provider',
            ])
            ->assertForbidden();
        $this->assertSame(OrderStatus::AssignedToRider, $shipment->sellerOrder->fresh()->workflow_status);
    }

    public function test_assigned_rider_can_resolve_only_their_shipment(): void
    {
        extract($this->context());
        [, , $otherRider] = $this->rider($provider, 'other');

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertOk()
            ->assertSee($shipment->tracking_code);

        $this->actingAs($otherRider->user)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertForbidden();
    }

    public function test_unassigned_rider_is_rejected_from_resolving_a_shipment(): void
    {
        extract($this->context());
        $shipment->update(['pickup_rider_id' => null, 'rider_id' => null]);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment->fresh())])
            ->assertForbidden();
    }

    public function test_invalid_or_tampered_qr_payload_is_rejected_safely(): void
    {
        extract($this->context());
        $payload = $this->payload($shipment);
        $tampered = substr($payload, 0, -1).($payload[-1] === 'a' ? 'b' : 'a');

        $this->actingAs($logisticsUser)
            ->from(route('logistics.shipment-scanner'))
            ->post(route('logistics.shipment-scanner.resolve'), ['payload' => $tampered])
            ->assertRedirect(route('logistics.shipment-scanner'))
            ->assertSessionHasErrors('payload');
    }

    public function test_pickup_scans_use_the_existing_acceptance_and_transition_workflow(): void
    {
        extract($this->context(OrderStatus::ReadyForPickup));

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'accept_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertNotNull($shipment->fresh()->pickup_accepted_at);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'confirm_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::PickedUp, $sellerOrder->fresh()->workflow_status);
    }

    public function test_invalid_pickup_status_scan_is_rejected(): void
    {
        extract($this->context(OrderStatus::PickedUp));

        $this->actingAs($pickupUser)
            ->from(route('rider.shipment-scanner'))
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'accept_pickup'])
            ->assertRedirect(route('rider.shipment-scanner'))
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::PickedUp, $sellerOrder->fresh()->workflow_status);
    }

    public function test_sorting_scans_receive_then_transition_the_existing_workflow_to_sorted(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_area' => 'Hub sorting'])
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertOk();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'receive_sorting'])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'RECEIVED_AT_SORTING_CENTER']);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertOk()
            ->assertSee('Confirm Sorting')
            ->assertSee('SCAN-')
            ->assertSee('Scanner Store')
            ->assertSee('Barangay, City, Province')
            ->assertSee('Pickup rider');

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_area' => 'Hub sorting'])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->assertSame(OrderStatus::Sorted, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::Sorted, $shipment->fresh()->workflow_status);
        $this->assertNotNull($sellerOrder->fresh()->sorted_at);
        $this->assertSame($provider->id, $shipment->fresh()->logistics_provider_id);
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_wrong_resolved_shipment_cannot_be_used_to_sort_a_different_parcel(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));
        $otherOrderRecord = $sellerOrder->order->replicate();
        $otherOrderRecord->order_number = 'SCAN-OTHER-'.uniqid();
        $otherOrderRecord->reference = 'SCAN-OTHER-'.uniqid();
        $otherOrderRecord->save();
        $otherOrder = $sellerOrder->replicate();
        $otherOrder->order_id = $otherOrderRecord->id;
        $otherOrder->save();
        $otherShipment = $shipment->replicate();
        $otherShipment->seller_order_id = $otherOrder->id;
        $otherShipment->tracking_code = 'SUKI-OTHER-'.strtoupper(uniqid());
        $otherShipment->save();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $otherShipment->tracking_code])
            ->assertOk()
            ->assertSee($otherShipment->tracking_code);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'mark_sorted', 'sorting_area' => 'Hub sorting'])
            ->assertSessionHasErrors('action');

        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::AtSortingCenter, $otherOrder->fresh()->workflow_status);
    }

    public function test_direct_forged_sort_post_is_rejected_without_scanner_verification(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'sorted',
                'sorting_area' => 'Forged Area',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
        $this->assertNull($sellerOrder->fresh()->sorted_at);
        $this->assertSame(OrderStatus::AtSortingCenter, $shipment->fresh()->workflow_status);
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_duplicate_sorting_receipt_scan_is_idempotent_without_duplicate_events(): void
    {
        extract($this->context(OrderStatus::AtSortingCenter));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $shipment->tracking_code])
            ->assertOk();
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'receive_sorting'])->assertRedirect();
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.action', $shipment), ['action' => 'receive_sorting'])->assertRedirect();

        $this->assertSame(1, $shipment->events()->where('status', 'RECEIVED_AT_SORTING_CENTER')->count());
    }

    public function test_scanner_cannot_bypass_delivery_proof_or_cod_completion_rules(): void
    {
        extract($this->context(OrderStatus::OutForDelivery));

        $this->actingAs($deliveryUser)
            ->from(route('rider.shipment-scanner'))
            ->post(route('rider.shipment-scanner.action', $shipment), ['action' => 'delivered'])
            ->assertRedirect(route('rider.shipment-scanner'))
            ->assertSessionHasErrors('action');
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->fresh()->workflow_status);
        $this->assertFalse($shipment->fresh()->cod_collected);
        $this->assertNull($shipment->fresh()->proof_path);
    }

    public function test_inactive_hub_rider_cannot_operate_a_scanner_action(): void
    {
        extract($this->context());
        $hub = LogisticsHub::create([
            'logistics_provider_id' => $provider->id, 'name' => 'Inactive hub',
            'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101',
            'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001',
            'is_active' => false,
        ]);
        $pickupRider->update(['logistics_hub_id' => $hub->id]);

        $this->actingAs($pickupUser)
            ->post(route('rider.shipment-scanner.resolve'), ['payload' => $this->payload($shipment)])
            ->assertForbidden();
    }

    private function context(OrderStatus $status = OrderStatus::AssignedToRider): array
    {
        [$logisticsUser, $provider] = $this->provider('scanner');
        [$pickupUser, , $pickupRider] = $this->rider($provider, 'pickup');
        [$deliveryUser, , $deliveryRider] = $this->rider($provider, 'delivery');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $this->role($buyer, 'buyer'); $this->role($sellerUser, 'seller');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Scanner Store', 'slug' => 'scanner-store-'.uniqid(), 'status' => 'approved']);
        $pickup = Address::create(['user_id' => $sellerUser->id, 'label' => 'Pickup', 'recipient' => 'Store', 'phone' => '0912', 'line1' => 'Street', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'city' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'is_default' => true]);
        $seller->update(['pickup_address_id' => $pickup->id]);
        $order = Order::create(['order_number' => 'SCAN-'.uniqid(), 'reference' => 'SCAN-'.uniqid(), 'buyer_id' => $buyer->id, 'seller_id' => $sellerUser->id, 'status' => $status->value, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'total_minor' => 15000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'region' => 'Region', 'region_code' => '01', 'province' => 'Province', 'province_code' => '0101', 'municipality' => 'City', 'city_municipality_code' => '010101', 'barangay' => 'Barangay', 'barangay_code' => '010101001', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => $status->legacySellerStatus(), 'workflow_status' => $status]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'pickup_rider_id' => $pickupRider->id, 'rider_id' => $status === OrderStatus::AtSortingCenter ? null : $deliveryRider->id, 'tracking_code' => 'SUKI-SCAN-'.strtoupper(uniqid()), 'status' => $status->legacyShipmentStatus(), 'workflow_status' => $status, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0]);

        return compact('logisticsUser', 'provider', 'pickupUser', 'pickupRider', 'deliveryUser', 'deliveryRider', 'sellerOrder', 'shipment');
    }

    private function provider(string $key): array
    {
        $user = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $this->role($user, 'logistics');
        return [$user, LogisticsProvider::create(['user_id' => $user->id, 'name' => $key, 'slug' => $key.'-'.uniqid(), 'status' => 'approved'])];
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

    private function payload(Shipment $shipment): string
    {
        return app(ShipmentQrService::class)->payload($shipment);
    }
}
