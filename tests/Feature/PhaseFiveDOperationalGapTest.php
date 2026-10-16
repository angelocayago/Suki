<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\DeliveryEvent;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveDOperationalGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_change_only_own_availability_without_losing_existing_work(): void
    {
        extract($this->context());
        $other = $this->rider($provider, 'Other Rider');

        $this->actingAs($deliveryUser)->post(route('rider.availability.update'), [
            'availability' => 'OFFLINE', 'rider_id' => $other->id,
        ])->assertSessionHas('success');

        $this->assertSame('OFFLINE', $deliveryRider->fresh()->availability);
        $this->assertSame('ONLINE', $other->fresh()->availability);
        $this->assertSame($deliveryRider->id, $shipment->fresh()->rider_id);
    }

    public function test_inactive_suspended_and_unactivated_riders_cannot_become_online(): void
    {
        extract($this->context());
        $deliveryRider->update(['is_active' => false]);
        $this->actingAs($deliveryUser)->post(route('rider.availability.update'), ['availability' => 'ONLINE'])->assertForbidden();

        $deliveryRider->update(['is_active' => true]);
        $deliveryUser->update(['is_suspended' => true]);
        $this->actingAs($deliveryUser)->post(route('rider.availability.update'), ['availability' => 'ONLINE'])->assertForbidden();

        $deliveryUser->update(['is_suspended' => false, 'verification_required_at' => now(), 'phone_verified_at' => null]);
        $this->actingAs($deliveryUser)->post(route('rider.availability.update'), ['availability' => 'ONLINE'])->assertForbidden();
    }

    public function test_assigned_delivery_rider_can_acknowledge_once_but_cannot_decline_after_acceptance(): void
    {
        extract($this->context());

        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.accept', $sellerOrder))->assertSessionHas('success');
        $this->assertNotNull($shipment->fresh()->delivery_accepted_at);
        $this->assertDatabaseCount('delivery_events', 1);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'DELIVERY_ACCEPTED']);

        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.accept', $sellerOrder))->assertSessionHas('success');
        $this->assertSame(1, DeliveryEvent::query()->where('shipment_id', $shipment->id)->where('status', 'DELIVERY_ACCEPTED')->count());
        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.decline', $sellerOrder))->assertForbidden();
    }

    public function test_pre_execution_delivery_decline_requeues_only_that_shipment_and_logistics_can_reassign(): void
    {
        extract($this->context());
        $replacement = $this->rider($provider, 'Replacement Rider');

        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.decline', $sellerOrder))->assertSessionHas('success');
        $this->assertNull($shipment->fresh()->rider_id);
        $this->assertSame($provider->id, $shipment->fresh()->logistics_provider_id);
        $this->assertSame(OrderStatus::AssignedToRider, $sellerOrder->fresh()->workflow_status);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'DELIVERY_DECLINED']);

        $this->actingAs($logisticsUser)->post(route('logistics.database.delivery-rider.reassign', $sellerOrder), ['rider_id' => $replacement->id])->assertSessionHas('success');
        $this->assertSame($replacement->id, $shipment->fresh()->rider_id);
        $this->assertNull($shipment->fresh()->delivery_accepted_at);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'DELIVERY_RIDER_REASSIGNED']);
    }

    public function test_unrelated_or_offline_rider_cannot_accept_or_be_reassigned_and_decline_after_dispatch_is_blocked(): void
    {
        extract($this->context());
        $other = $this->rider($provider, 'Other Rider');

        $this->actingAs($other->user)->post(route('rider.database.delivery.accept', $sellerOrder))->assertForbidden();
        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.decline', $sellerOrder))->assertSessionHas('success');
        $other->update(['availability' => 'OFFLINE']);
        $this->actingAs($logisticsUser)->post(route('logistics.database.delivery-rider.reassign', $sellerOrder), ['rider_id' => $other->id])->assertSessionHasErrors('rider_id');

        $shipment->update(['rider_id' => $deliveryRider->id, 'workflow_status' => OrderStatus::OutForDelivery]);
        $sellerOrder->update(['workflow_status' => OrderStatus::OutForDelivery]);
        $this->actingAs($deliveryUser)->post(route('rider.database.delivery.decline', $sellerOrder))->assertForbidden();
    }

    public function test_tracking_lookup_and_rider_delivery_history_are_provider_and_rider_scoped(): void
    {
        extract($this->context());
        $this->actingAs($logisticsUser)->get(route('logistics.parcels.lookup', ['tracking_code' => $shipment->tracking_code]))
            ->assertOk()->assertSee($shipment->tracking_code)->assertSee($order->order_number);

        $otherLogisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        LogisticsProvider::create(['user_id' => $otherLogisticsUser->id, 'name' => 'Other Provider', 'slug' => 'other-'.uniqid(), 'status' => 'approved']);
        $this->actingAs($otherLogisticsUser)->get(route('logistics.parcels.lookup', ['tracking_code' => $shipment->tracking_code]))
            ->assertOk()->assertSee('No parcel from your logistics provider matches');

        $other = $this->rider($provider, 'Other Rider');
        $this->actingAs($deliveryUser)->get(route('rider.deliveries'))->assertOk()->assertSee($order->order_number)->assertSee('Buyer Street');
        $this->actingAs($other->user)->get(route('rider.deliveries'))->assertOk()->assertDontSee($order->order_number)->assertDontSee('Buyer Street');
    }

    private function context(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $deliveryUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Operational Seller', 'slug' => 'operational-'.uniqid(), 'status' => 'approved']);
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => 'Operational Provider', 'slug' => 'operational-provider-'.uniqid(), 'status' => 'approved']);
        $deliveryRider = Rider::create(['user_id' => $deliveryUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'DELIVERY-'.uniqid(), 'is_active' => true, 'approved_at' => now(), 'availability' => 'ONLINE']);
        $order = Order::create(['order_number' => 'OP-'.uniqid(), 'reference' => 'OP-'.uniqid(), 'buyer_id' => $buyer->id, 'status' => OrderStatus::AssignedToRider->value, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'total_minor' => 15000, 'recipient_name' => 'Buyer', 'recipient_phone' => '09123456789', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Buyer Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => 'shipped', 'workflow_status' => OrderStatus::AssignedToRider]);
        $shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id, 'rider_id' => $deliveryRider->id, 'tracking_code' => 'OP-TRACK-'.uniqid(), 'status' => 'assigned', 'workflow_status' => OrderStatus::AssignedToRider, 'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0]);

        return compact('buyer', 'sellerUser', 'logisticsUser', 'deliveryUser', 'seller', 'provider', 'deliveryRider', 'order', 'sellerOrder', 'shipment');
    }

    private function rider(LogisticsProvider $provider, string $name): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'name' => $name, 'status' => 'active']);

        return Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'RIDER-'.uniqid(), 'is_active' => true, 'approved_at' => now(), 'availability' => 'ONLINE']);
    }
}
