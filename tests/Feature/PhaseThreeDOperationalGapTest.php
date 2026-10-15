<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeDOperationalGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_can_assign_and_reassign_an_eligible_pickup_rider_with_history(): void
    {
        extract($this->pickupContext());
        $replacementUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $replacement = Rider::create(['user_id' => $replacementUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'PICKUP-2', 'is_active' => true, 'approved_at' => now()]);

        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])
            ->assertSessionHas('success');
        $this->assertSame($pickupRider->id, $shipment->fresh()->pickup_rider_id);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'PICKUP_RIDER_ASSIGNED']);

        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $replacement->id])
            ->assertSessionHas('success');
        $this->assertSame($replacement->id, $shipment->fresh()->pickup_rider_id);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'PICKUP_RIDER_REASSIGNED']);

        $eventCount = $shipment->events()->whereIn('status', ['PICKUP_RIDER_ASSIGNED', 'PICKUP_RIDER_REASSIGNED'])->count();
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $replacement->id])->assertSessionHas('success');
        $this->assertSame($eventCount, $shipment->events()->whereIn('status', ['PICKUP_RIDER_ASSIGNED', 'PICKUP_RIDER_REASSIGNED'])->count());
    }

    public function test_wrong_provider_inactive_and_post_acceptance_pickup_assignments_are_blocked(): void
    {
        extract($this->pickupContext());
        $wrongProviderUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $wrongProvider = LogisticsProvider::create(['user_id' => $wrongProviderUser->id, 'name' => 'Other', 'slug' => 'other-provider', 'status' => 'approved']);
        $wrongRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $wrongRider = Rider::create(['user_id' => $wrongRiderUser->id, 'logistics_provider_id' => $wrongProvider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'OTHER-1', 'is_active' => true]);
        $inactiveUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $inactive = Rider::create(['user_id' => $inactiveUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'INACTIVE-1', 'is_active' => false]);
        $unapprovedUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $unapproved = Rider::create(['user_id' => $unapprovedUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'UNAPPROVED-1', 'is_active' => true]);
        $suspendedUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => true]);
        $suspended = Rider::create(['user_id' => $suspendedUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'SUSPENDED-1', 'is_active' => true, 'approved_at' => now()]);

        $this->actingAs($buyer)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertForbidden();
        $this->actingAs($sellerUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertForbidden();
        $this->actingAs($pickupRiderUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertForbidden();
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $wrongRider->id])->assertNotFound();
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $inactive->id])->assertSessionHasErrors('pickup_rider_id');
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $unapproved->id])->assertSessionHasErrors('pickup_rider_id');
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $suspended->id])->assertSessionHasErrors('pickup_rider_id');

        $shipment->update(['pickup_rider_id' => $pickupRider->id, 'pickup_accepted_at' => now()]);
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertSessionHasErrors('order');
    }

    public function test_assigned_pickup_rider_can_decline_but_another_rider_cannot(): void
    {
        extract($this->pickupContext());
        $shipment->update(['pickup_rider_id' => $pickupRider->id]);
        $otherUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Rider::create(['user_id' => $otherUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'PICKUP-OTHER', 'is_active' => true, 'approved_at' => now()]);

        $this->actingAs($otherUser)->post(route('rider.database.pickup.decline', $sellerOrder))->assertForbidden();
        $this->actingAs($pickupRiderUser)->post(route('rider.database.pickup.decline', $sellerOrder))->assertSessionHas('success');
        $this->assertNull($shipment->fresh()->pickup_rider_id);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'PICKUP_DECLINED']);
    }

    public function test_assigned_rider_accepts_but_unassigned_rider_cannot_self_assign_and_pickup_blocks_reassignment(): void
    {
        extract($this->pickupContext());
        $otherUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Rider::create(['user_id' => $otherUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'NO-SELF-ASSIGN', 'is_active' => true, 'approved_at' => now()]);

        $this->actingAs($otherUser)->post(route('rider.database.pickup.accept', $sellerOrder))->assertSessionHasErrors('order');
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertSessionHas('success');
        $this->actingAs($pickupRiderUser)->post(route('rider.database.pickup.accept', $sellerOrder))->assertSessionHas('success');
        $this->assertNotNull($shipment->fresh()->pickup_accepted_at);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'PICKUP_ACCEPTED']);

        $this->actingAs($pickupRiderUser)->post(route('rider.database.order.status', $sellerOrder), ['status' => 'PICKED_UP'])->assertSessionHas('success');
        $this->actingAs($logisticsUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $pickupRider->id])->assertSessionHasErrors('order');
        $this->assertSame(OrderStatus::PickedUp, $sellerOrder->fresh()->workflow_status);
    }

    public function test_only_assigned_delivery_rider_can_record_valid_issue_without_mutating_workflow(): void
    {
        extract($this->deliveryContext());
        $outsider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Rider::create(['user_id' => $outsider->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'OUTSIDER-ISSUE', 'is_active' => true, 'approved_at' => now()]);

        $this->actingAs($outsider)->post(route('rider.database.delivery.issue', $sellerOrder), ['issue_category' => 'buyer_unreachable'])->assertForbidden();
        $this->actingAs($deliveryRiderUser)->post(route('rider.database.delivery.issue', $sellerOrder), ['issue_category' => 'wrong_or_incomplete_address', 'issue_details' => 'House number is missing'])->assertSessionHas('success');
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'DELIVERY_ISSUE_REPORTED']);
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->fresh()->workflow_status);
    }

    public function test_waybill_renders_local_qr_barcode_and_rider_navigation_control_without_sensitive_payload(): void
    {
        extract($this->deliveryContext());
        $this->actingAs($buyer)->get(route('shipments.waybill', $shipment))
            ->assertOk()->assertSee($shipment->tracking_code)->assertSee('Tracking QR code')->assertSee('Scan to identify this shipment')
            ->assertSee('data-tracking-payload="'.$shipment->tracking_code.'"', false);
        $this->actingAs($deliveryRiderUser)->get(route('rider.deliveries'))
            ->assertOk()->assertSee('Copy address for navigation')->assertSee('Message Support');
    }

    private function pickupContext(): array
    {
        return $this->context(OrderStatus::ReadyForPickup);
    }

    private function deliveryContext(): array
    {
        return $this->context(OrderStatus::OutForDelivery);
    }

    private function context(OrderStatus $status): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $pickupRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $deliveryRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => '3D Seller', 'slug' => 'phase-3d-seller-'.uniqid(), 'status' => 'approved']);
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => '3D Logistics', 'slug' => 'phase-3d-provider-'.uniqid(), 'status' => 'approved']);
        $pickupRider = Rider::create(['user_id' => $pickupRiderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'PICKUP-1', 'is_active' => true, 'approved_at' => now()]);
        $deliveryRider = Rider::create(['user_id' => $deliveryRiderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'DELIVERY-1', 'is_active' => true, 'approved_at' => now()]);
        $order = Order::create(['order_number' => '3D-'.uniqid(), 'reference' => '3D-'.uniqid(), 'buyer_id' => $buyer->id, 'status' => $status->value, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 0, 'discount_amount' => 0, 'total_amount' => 100, 'total_minor' => 10000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => $status->legacySellerStatus(), 'workflow_status' => $status]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'rider_id' => $status === OrderStatus::OutForDelivery ? $deliveryRider->id : null, 'tracking_code' => '3D-TRACK-'.uniqid(), 'status' => $status->legacyShipmentStatus(), 'workflow_status' => $status, 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);

        return compact('buyer', 'sellerUser', 'logisticsUser', 'pickupRiderUser', 'deliveryRiderUser', 'seller', 'provider', 'pickupRider', 'deliveryRider', 'order', 'sellerOrder', 'shipment');
    }
}
