<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_erp_flow_is_enforced_by_actor_and_assignment(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $pickupUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $deliveryUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);

        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Workflow Seller',
            'slug' => 'workflow-seller',
            'status' => 'approved',
        ]);

        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Workflow Logistics',
            'slug' => 'workflow-logistics',
            'status' => 'approved',
        ]);

        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'PICKUP-1',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'DELIVERY-1',
            'is_active' => true,
        ]);

        $order = $this->makeOrder($buyer);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 500,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
        ]);

        $workflow = app(OrderWorkflowService::class);
        $workflow->transition($sellerOrder, $sellerUser, OrderStatus::Confirmed);
        $workflow->transition($sellerOrder, $sellerUser, OrderStatus::Preparing);
        $workflow->transition($sellerOrder, $sellerUser, OrderStatus::ReadyForPickup);
        \App\Models\Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => null,
            'tracking_code' => 'UNIT-WORKFLOW-PICKUP',
            'status' => OrderStatus::ReadyForPickup->legacyShipmentStatus(),
            'workflow_status' => OrderStatus::ReadyForPickup,
            'fee_minor' => 500,
            'cod_amount_minor' => 10500,
            'cod_collected' => false,
            'attempts' => 0,
        ]);
        $workflow->assignPickupRider($sellerOrder, $provider, $logisticsUser, $pickupRider);
        $workflow->acceptPickup($sellerOrder, $pickupRider);
        $workflow->transition($sellerOrder, $pickupUser, OrderStatus::PickedUp);
        $workflow->transition($sellerOrder, $pickupUser, OrderStatus::AtSortingCenter);

        $this->assertSame($provider->id, $sellerOrder->fresh()->shipment->logistics_provider_id);
        $this->assertSame($provider->id, $sellerOrder->fresh()->logistics_provider_id);

        $workflow->transition($sellerOrder, $logisticsUser, OrderStatus::Sorted, [
            'sorting_area' => 'North Zone',
        ]);
        $workflow->transition($sellerOrder, $logisticsUser, OrderStatus::AssignedToRider, [
            'rider' => $deliveryRider,
        ]);
        $workflow->transition($sellerOrder, $deliveryUser, OrderStatus::OutForDelivery);
        \Illuminate\Support\Facades\Storage::fake('delivery_proofs');
        app(\App\Services\DeliveryCompletionService::class)->deliver($sellerOrder, $deliveryUser, [
            'proof_photo' => \Tests\Support\DeliveryProofFile::image(),
            'received_by' => 'Buyer', 'cod_collected' => '1',
        ]);
        $workflow->transition($sellerOrder, $buyer, OrderStatus::Completed);

        $this->assertSame(OrderStatus::Completed, $sellerOrder->fresh()->workflow_status);
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertSame($pickupRider->id, $sellerOrder->fresh()->shipment->pickup_rider_id);
        $this->assertSame($deliveryRider->id, $sellerOrder->fresh()->shipment->rider_id);
        $this->assertSame('North Zone', $sellerOrder->fresh()->shipment->sorting_area);
    }

    public function test_a_seller_cannot_update_another_sellers_order(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create([
            'user_id' => $owner->id,
            'name' => 'Owner Store',
            'slug' => 'owner-store',
            'status' => 'approved',
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $this->makeOrder($buyer)->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
        ]);

        $this->expectException(AuthorizationException::class);

        app(OrderWorkflowService::class)
            ->transition($sellerOrder, $otherSeller, OrderStatus::Confirmed);
    }

    public function test_parent_order_uses_the_least_advanced_seller_order_status(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $firstOwner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $secondOwner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $order = $this->makeOrder($buyer);

        $firstSeller = Seller::create([
            'user_id' => $firstOwner->id,
            'name' => 'First Store',
            'slug' => 'first-store',
            'status' => 'approved',
        ]);
        $secondSeller = Seller::create([
            'user_id' => $secondOwner->id,
            'name' => 'Second Store',
            'slug' => 'second-store',
            'status' => 'approved',
        ]);

        $firstParcel = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $firstSeller->id,
            'subtotal_minor' => 5000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 500,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
        ]);
        $secondParcel = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $secondSeller->id,
            'subtotal_minor' => 5000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 500,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
        ]);

        $workflow = app(OrderWorkflowService::class);
        $workflow->transition($firstParcel, $firstOwner, OrderStatus::Confirmed);
        $this->assertSame('PLACED', $order->fresh()->status);

        $workflow->transition($secondParcel, $secondOwner, OrderStatus::Confirmed);
        $this->assertSame('CONFIRMED', $order->fresh()->status);

        $workflow->transition($firstParcel, $firstOwner, OrderStatus::Preparing);
        $this->assertSame('CONFIRMED', $order->fresh()->status);
    }

    public function test_statuses_cannot_be_skipped(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Sequential Store',
            'slug' => 'sequential-store',
            'status' => 'approved',
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $this->makeOrder($buyer)->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(OrderWorkflowService::class)
            ->transition($sellerOrder, $sellerUser, OrderStatus::Preparing);
    }

    public function test_rider_from_another_provider_cannot_claim_an_existing_shipment(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $firstLogistics = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $secondLogistics = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Provider Store',
            'slug' => 'provider-store',
            'status' => 'approved',
        ]);
        $firstProvider = LogisticsProvider::create([
            'user_id' => $firstLogistics->id,
            'name' => 'First Provider',
            'slug' => 'first-provider',
            'status' => 'approved',
        ]);
        $secondProvider = LogisticsProvider::create([
            'user_id' => $secondLogistics->id,
            'name' => 'Second Provider',
            'slug' => 'second-provider',
            'status' => 'approved',
        ]);
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'logistics_provider_id' => $secondProvider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'OTHER-1',
            'is_active' => true,
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $this->makeOrder($buyer)->id,
            'seller_id' => $seller->id,
            'logistics_provider_id' => $firstProvider->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 1000,
            'status' => 'ready_to_ship',
            'workflow_status' => OrderStatus::ReadyForPickup,
        ]);

        $this->expectException(AuthorizationException::class);

        app(OrderWorkflowService::class)->acceptPickup($sellerOrder, $rider);
    }

    private function makeOrder(User $buyer): Order
    {
        return Order::create([
            'order_number' => 'ORDER-'.fake()->unique()->numerify('#####'),
            'reference' => 'ORDER-'.fake()->unique()->numerify('#####'),
            'buyer_id' => $buyer->id,
            'status' => 'PLACED',
            'payment_method' => 'cod',
            'payment_status' => 'UNPAID',
            'subtotal' => 100,
            'shipping_fee' => 5,
            'discount_amount' => 0,
            'total_amount' => 105,
            'total_minor' => 10500,
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'Municipality',
            'barangay' => 'Barangay',
            'street_address' => 'Street',
            'shipping_address' => ['street_address' => 'Street'],
            'placed_at' => now(),
        ]);
    }
}
