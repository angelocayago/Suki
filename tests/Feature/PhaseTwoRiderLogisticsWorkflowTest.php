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
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoRiderLogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backed_rider_and_logistics_flow_reaches_delivered_with_history(): void
    {
        $context = $this->workflowContext();
        extract($context);

        $this->actingAs($pickupUser)
            ->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('STEP5-ORDER');

        $this->actingAs($pickupUser)
            ->post(route('rider.database.pickup.accept', $sellerOrder))
            ->assertSessionHasNoErrors();
        $this->assertSame($pickupRider->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'picked_up'])
            ->assertSessionHasNoErrors();
        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'at_sorting_center'])
            ->assertSessionHasNoErrors();

        $this->actingAs($logisticsUser)
            ->get(route('logistics.parcels'))
            ->assertOk()
            ->assertSee('STEP5-ORDER');

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.orders.receive', $sellerOrder))
            ->assertSessionHasNoErrors();
        $this->assertSame($provider->id, $shipment->fresh()->logistics_provider_id);
        $this->assertSame($provider->id, $sellerOrder->fresh()->logistics_provider_id);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'sorted',
                'sorting_area' => 'Area A',
            ])->assertSessionHasNoErrors();
        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'assigned_to_rider',
                'rider_id' => $deliveryRider->id,
            ])->assertSessionHasNoErrors();

        $this->actingAs($deliveryUser)
            ->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('Buyer Street');

        $this->actingAs($deliveryUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'out_for_delivery'])
            ->assertSessionHasNoErrors();

        $this->actingAs($deliveryUser)
            ->post(route('rider.database.delivery.failed', $sellerOrder), [
                'failure_reason' => 'Customer was unavailable.',
            ])->assertSessionHasNoErrors();

        $sellerOrder->refresh();
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->workflow_status);
        $this->assertSame(1, $shipment->fresh()->attempts);
        $this->assertDatabaseHas('delivery_events', [
            'shipment_id' => $shipment->id,
            'status' => 'DELIVERY_ATTEMPT_FAILED',
            'attempt' => 1,
            'note' => 'Customer was unavailable.',
        ]);

        $this->actingAs($deliveryUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'delivered'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Delivered, $sellerOrder->fresh()->workflow_status);
        $this->assertSame($pickupRider->id, $shipment->fresh()->pickup_rider_id);
        $this->assertSame($deliveryRider->id, $shipment->fresh()->rider_id);
        $this->assertDatabaseHas('delivery_events', ['shipment_id' => $shipment->id, 'status' => 'DELIVERED']);

        app(OrderWorkflowService::class)
            ->transition($sellerOrder, $buyer, OrderStatus::Completed);
        $this->assertSame(OrderStatus::Completed, $sellerOrder->fresh()->workflow_status);
    }

    public function test_actor_permissions_provider_ownership_and_required_steps_are_enforced(): void
    {
        $context = $this->workflowContext();
        extract($context);
        $otherRiderUser = User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'is_suspended' => false,
        ]);
        $rider = Rider::create([
            'user_id' => $otherRiderUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'OTHER-1',
            'is_active' => true,
        ]);

        $this->actingAs($pickupUser)
            ->post(route('rider.database.pickup.accept', $sellerOrder));

        $this->actingAs($otherRiderUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'picked_up'])
            ->assertForbidden();
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'at_sorting_center'])
            ->assertSessionHasErrors('status');

        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'picked_up']);
        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'at_sorting_center']);
        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.orders.receive', $sellerOrder));

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), ['status' => 'sorted'])
            ->assertSessionHasErrors('sorting_area');
        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'out_for_delivery',
            ])->assertSessionHasErrors('status');

        $this->actingAs($pickupUser)
            ->post(route('rider.database.order.status', $sellerOrder), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::AtSortingCenter, $sellerOrder->fresh()->workflow_status);
    }

    public function test_logistics_cannot_receive_another_providers_pickup_or_assign_its_rider(): void
    {
        $context = $this->workflowContext();
        extract($context);
        $otherLogistics = User::factory()->create([
            'role' => 'logistics', 'status' => 'active', 'is_suspended' => false,
        ]);
        $otherProvider = LogisticsProvider::create([
            'user_id' => $otherLogistics->id,
            'name' => 'Other Logistics',
            'slug' => 'other-logistics',
            'status' => 'approved',
        ]);
        $otherDeliveryUser = User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'is_suspended' => false,
        ]);
        $otherDeliveryRider = Rider::create([
            'user_id' => $otherDeliveryUser->id,
            'logistics_provider_id' => $otherProvider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'OTHER-DELIVERY',
            'is_active' => true,
        ]);

        $workflow = app(OrderWorkflowService::class);
        $workflow->acceptPickup($sellerOrder, $pickupRider);
        $workflow->transition($sellerOrder, $pickupUser, OrderStatus::PickedUp);
        $workflow->transition($sellerOrder, $pickupUser, OrderStatus::AtSortingCenter);

        $this->actingAs($otherLogistics)
            ->post(route('logistics.database.orders.receive', $sellerOrder))
            ->assertForbidden();

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.orders.receive', $sellerOrder));
        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'sorted', 'sorting_area' => 'Area B',
            ]);
        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.order.status', $sellerOrder), [
                'status' => 'assigned_to_rider', 'rider_id' => $otherDeliveryRider->id,
            ])->assertForbidden();

        $this->assertSame(OrderStatus::Sorted, $sellerOrder->fresh()->workflow_status);
        $this->assertNull($shipment->fresh()->rider_id);
    }

    public function test_session_fallback_remains_visible_when_no_database_shipments_exist(): void
    {
        $logisticsUser = User::factory()->create([
            'role' => 'logistics', 'status' => 'active', 'is_suspended' => false,
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Fallback Logistics',
            'slug' => 'fallback-logistics',
            'status' => 'approved',
        ]);
        $riderUser = User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'is_suspended' => false,
        ]);
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'FALLBACK-1',
            'is_active' => true,
        ]);
        $legacyOrders = [
            77 => [
                'id' => 77,
                'status' => 'picked_up',
                'pickup_rider_index' => $rider->id,
                'customer_name' => 'Legacy Session Buyer',
                'address' => 'Legacy Session Address',
                'pickup_rider_name' => 'Legacy Rider',
                'shipping_address' => ['name' => 'Legacy Session Buyer'],
            ],
        ];

        $this->actingAs($riderUser)
            ->withSession(['orders' => $legacyOrders, 'logged_in_rider_index' => $rider->id])
            ->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee('Legacy Session Buyer');

        $this->actingAs($logisticsUser)
            ->withSession(['orders' => $legacyOrders])
            ->get(route('logistics.parcels'))
            ->assertOk()
            ->assertSee('Legacy Session Buyer');
    }

    private function workflowContext(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer', 'status' => 'active', 'is_suspended' => false,
        ]);
        $sellerUser = User::factory()->create([
            'role' => 'seller', 'status' => 'active', 'is_suspended' => false,
        ]);
        $logisticsUser = User::factory()->create([
            'role' => 'logistics', 'status' => 'active', 'is_suspended' => false,
        ]);
        $pickupUser = User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'is_suspended' => false,
        ]);
        $deliveryUser = User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'is_suspended' => false,
        ]);

        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Step Five Seller',
            'slug' => 'step-five-seller',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Step Five Logistics',
            'slug' => 'step-five-logistics',
            'status' => 'approved',
        ]);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'PICKUP-5',
            'is_active' => true,
        ]);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'DELIVERY-5',
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'STEP5-ORDER',
            'reference' => 'STEP5-ORDER',
            'buyer_id' => $buyer->id,
            'seller_id' => $sellerUser->id,
            'status' => OrderStatus::ReadyForPickup->value,
            'payment_method' => 'cod',
            'payment_status' => 'UNPAID',
            'subtotal' => 100,
            'shipping_fee' => 10,
            'discount_amount' => 0,
            'total_amount' => 110,
            'total_minor' => 11000,
            'recipient_name' => 'Step Five Buyer',
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'Municipality',
            'barangay' => 'Barangay',
            'street_address' => 'Buyer Street',
            'shipping_address' => ['street_address' => 'Buyer Street'],
            'placed_at' => now(),
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 1000,
            'commission_minor' => 1000,
            'status' => 'ready_to_ship',
            'workflow_status' => OrderStatus::ReadyForPickup,
            'status_changed_at' => now(),
            'ready_for_pickup_at' => now(),
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => null,
            'tracking_code' => 'STEP5-TRACKING',
            'status' => 'unassigned',
            'workflow_status' => OrderStatus::ReadyForPickup,
            'fee_minor' => 1000,
            'cod_amount_minor' => 11000,
            'cod_collected' => false,
            'attempts' => 0,
            'status_changed_at' => now(),
        ]);

        return compact(
            'buyer', 'sellerUser', 'logisticsUser', 'pickupUser', 'deliveryUser',
            'seller', 'provider', 'pickupRider', 'deliveryRider', 'order', 'sellerOrder', 'shipment'
        );
    }
}
