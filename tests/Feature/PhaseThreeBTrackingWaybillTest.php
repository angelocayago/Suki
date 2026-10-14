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

class PhaseThreeBTrackingWaybillTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_rider_can_publish_current_location_and_buyer_can_read_own_tracking(): void
    {
        extract($this->context());
        $this->actingAs($riderUser)->postJson(route('rider.shipments.location.store', $shipment), ['latitude' => 14.5995, 'longitude' => 120.9842])
            ->assertOk()->assertJsonPath('latitude', 14.5995);
        $this->assertSame($rider->id, $shipment->fresh()->location_rider_id);

        $this->actingAs($buyer)->getJson(route('buyer.seller-order.tracking-data', [$order, $sellerOrder]))
            ->assertOk()->assertJsonPath('longitude', 120.9842);
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order, $sellerOrder]))->assertOk()->assertSee('Live delivery tracking');
    }

    public function test_tracking_is_private_and_rejects_invalid_or_terminal_updates(): void
    {
        extract($this->context());
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $otherRiderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        Rider::create(['user_id' => $otherRiderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'OTHER-TRACK', 'is_active' => true]);

        $this->getJson(route('buyer.seller-order.tracking-data', [$order, $sellerOrder]))->assertUnauthorized();
        $this->actingAs($other)->getJson(route('buyer.seller-order.tracking-data', [$order, $sellerOrder]))->assertNotFound();
        $this->actingAs($otherRiderUser)->postJson(route('rider.shipments.location.store', $shipment), ['latitude' => 14, 'longitude' => 120])->assertForbidden();
        $this->actingAs($riderUser)->postJson(route('rider.shipments.location.store', $shipment), ['latitude' => 91, 'longitude' => 120])->assertJsonValidationErrors('latitude');

        $shipment->update(['workflow_status' => OrderStatus::Delivered]);
        $this->actingAs($riderUser)->postJson(route('rider.shipments.location.store', $shipment), ['latitude' => 14, 'longitude' => 120])->assertJsonValidationErrors('shipment');
    }

    public function test_waybill_is_scoped_to_related_participants_and_assignment_event_is_preserved(): void
    {
        extract($this->context());
        $this->actingAs($buyer)->get(route('shipments.waybill', $shipment))->assertOk()->assertSee($shipment->tracking_code);
        $outsider = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($outsider)->get(route('shipments.waybill', $shipment))->assertForbidden();
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'ASSIGNED_TO_RIDER']);
    }

    private function context(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Tracking Seller', 'slug' => 'tracking-seller', 'status' => 'approved']);
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => 'Tracking Logistics', 'slug' => 'tracking-logistics', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'TRACK-001', 'is_active' => true]);
        $order = Order::create(['order_number' => 'TRACK-ORDER', 'reference' => 'TRACK-ORDER', 'buyer_id' => $buyer->id, 'status' => 'ASSIGNED_TO_RIDER', 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 0, 'discount_amount' => 0, 'total_amount' => 100, 'total_minor' => 10000, 'recipient_name' => 'Buyer', 'recipient_phone' => '0912', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'shipped', 'workflow_status' => OrderStatus::AssignedToRider]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'rider_id' => $rider->id, 'tracking_code' => 'TRACK-001', 'status' => 'assigned', 'workflow_status' => OrderStatus::AssignedToRider, 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);
        $shipment->events()->create(['status' => 'ASSIGNED_TO_RIDER', 'attempt' => 0, 'user_id' => $logisticsUser->id, 'note' => 'Assigned to delivery rider.', 'occurred_at' => now()]);
        return compact('buyer', 'sellerUser', 'logisticsUser', 'riderUser', 'seller', 'provider', 'rider', 'order', 'sellerOrder', 'shipment');
    }
}
