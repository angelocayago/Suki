<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\LogisticsHub;
use App\Models\LogisticsSortingZone;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CartCheckoutIntent;
use Tests\Support\FakesCheckoutLocations;
use Tests\Support\IsolatedStorageTestCase;

class SystemFunctionalStabilizationTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, CartCheckoutIntent, FakesCheckoutLocations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeCheckoutLocations();
    }

    public function test_seeded_accounts_catalog_and_portal_pages_are_browser_ready(): void
    {
        $this->seedAcceptanceAccounts();

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $seller = User::where('email', 'seller@test.com')->sole();
        $admin = User::where('email', 'admin@test.com')->sole();
        $rider = User::where('email', 'rider@test.com')->sole();
        $logistics = User::where('email', 'logistics@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();

        $this->assertNotNull($buyer->addresses()->where('is_default', true)->first());

        $this->actingAs($buyer)->get(route('buyer.home'))->assertOk()->assertSee($product->name);
        $this->get(route('buyer.shop'))->assertOk()->assertSee($product->name);
        $this->get(route('buyer.product', $product->slug))->assertOk()->assertSee($product->name);

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('buyer.product', $product->slug));
        $this->get(route('buyer.checkout', $this->cartCheckoutIntent($buyer)))->assertOk();
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('superadmin.dashboard'))->assertOk();
        $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk();
        $this->actingAs($logistics)->get(route('logistics.dashboard'))->assertOk();
    }

    public function test_seeded_roles_cannot_cross_portal_boundaries(): void
    {
        $this->seedAcceptanceAccounts();

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $seller = User::where('email', 'seller@test.com')->sole();
        $rider = User::where('email', 'rider@test.com')->sole();
        $logistics = User::where('email', 'logistics@test.com')->sole();

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('superadmin.dashboard'))->assertForbidden();
        $this->actingAs($rider)->get(route('logistics.dashboard'))->assertForbidden();
        $this->actingAs($logistics)->get(route('rider.dashboard'))->assertForbidden();
    }

    public function test_seeded_accounts_complete_the_database_erp_lifecycle_consistently(): void
    {
        $this->seedAcceptanceAccounts();

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $sellerUser = User::where('email', 'seller@test.com')->sole();
        $pickupUser = User::where('email', 'rider@test.com')->sole();
        $deliveryUser = User::where('email', 'delivery.rider@test.com')->sole();
        $logisticsUser = User::where('email', 'logistics@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();
        $address = $buyer->addresses()->where('is_default', true)->sole();

        $this->actingAs($buyer)
            ->from(route('buyer.product', $product->slug))
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('buyer.product', $product->slug));

        $this->post(route('buyer.checkout.place-order'), array_merge([
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ], $this->cartCheckoutIntent($buyer)))->assertSessionHasNoErrors();

        $order = $buyer->buyerOrders()->with('sellerOrders.shipment')->sole();
        $sellerOrder = $order->sellerOrders->sole();

        // This lifecycle fixture has matching barangay service areas but must also
        // assign the riders to an active provider hub covering that barangay.
        $pickupAddress = $sellerUser->sellers()->sole()->pickupAddress;
        $provider = $logisticsUser->logisticsProvider()->sole();
        $hub = LogisticsHub::create([
            'logistics_provider_id' => $provider->id,
            'name' => 'Acceptance Barangay Hub',
            'region' => $pickupAddress->region,
            'region_code' => $pickupAddress->region_code,
            'province' => $pickupAddress->province,
            'province_code' => $pickupAddress->province_code,
            'city' => $pickupAddress->city,
            'city_municipality_code' => $pickupAddress->city_municipality_code,
            'barangay' => $pickupAddress->barangay,
            'barangay_code' => $pickupAddress->barangay_code,
            'is_active' => true,
        ]);
        $mainHub = LogisticsHub::create([
            'logistics_provider_id' => $provider->id, 'name' => 'Acceptance Main Sorting Center',
            'region' => $pickupAddress->region, 'region_code' => $pickupAddress->region_code,
            'province' => $pickupAddress->province, 'province_code' => $pickupAddress->province_code,
            'city' => $pickupAddress->city, 'city_municipality_code' => $pickupAddress->city_municipality_code,
            'barangay' => 'Sorting Center', 'barangay_code' => '010000002', 'is_active' => true,
        ]);
        $provider->update(['main_sorting_hub_id' => $mainHub->id]);
        $pickupUser->rider->update(['logistics_hub_id' => $hub->id]);
        $deliveryUser->rider->update(['logistics_hub_id' => $hub->id]);
        $zone = LogisticsSortingZone::create(['logistics_provider_id' => $provider->id, 'logistics_hub_id' => $hub->id, 'zone_code' => 'AC-01', 'name' => 'Acceptance Area', 'is_active' => true, 'priority' => 100]);
        $zone->areas()->create(['region_code' => $order->region_code, 'province_code' => $order->province_code, 'city_municipality_code' => $order->city_municipality_code, 'barangay_code' => $order->barangay_code, 'area_key' => implode(':', [$order->region_code, $order->province_code, $order->city_municipality_code, $order->barangay_code])]);

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::ReadyForPickup] as $status) {
            $this->actingAs($sellerUser)
                ->post(route('seller.orders.status', $sellerOrder), ['status' => $status->value])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.pickup-rider.assign', $sellerOrder), [
                'pickup_rider_id' => $pickupUser->rider->id,
            ])->assertSessionHasNoErrors();
        $this->actingAs($pickupUser)
            ->post(route('rider.database.pickup.accept', $sellerOrder))
            ->assertSessionHasNoErrors();
        foreach ([['confirm_pickup', $pickupUser, 'pickup'], ['arrive_sorting', $pickupUser, 'sorting_center_arrival']] as [$action, $riderUser, $mode]) {
            $this->actingAs($riderUser)
                ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $sellerOrder->shipment->tracking_code, 'mode' => $mode])
                ->assertRedirect(route('rider.shipment-scanner'));
            $this->actingAs($riderUser)
                ->post(route('rider.shipment-scanner.action', $sellerOrder->shipment), ['action' => $action])
                ->assertRedirect(route('rider.shipment-scanner'));
        }

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.orders.receive', $sellerOrder))
            ->assertSessionHasErrors('shipment');
        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $sellerOrder->shipment->tracking_code, 'mode' => 'sort'])
            ->assertRedirect(route('logistics.shipment-scanner'));
        $this->actingAs($logisticsUser)
            ->post(route('logistics.shipment-scanner.action', $sellerOrder->shipment), [
                'action' => 'mark_sorted',
                'sorting_zone_id' => $zone->id,
            ])->assertRedirect(route('logistics.shipment-scanner'));
        $deliveryUser->rider->update(['logistics_hub_id' => $provider->main_sorting_hub_id]);
        $this->actingAs($logisticsUser)->post(route('logistics.database.hub-transfer.assign', $sellerOrder), ['rider_id' => $deliveryUser->rider->id])->assertSessionHasNoErrors();
        foreach ([['accept_hub_transfer', 'rider.shipment-scanner'], ['dispatch_hub', 'rider.shipment-scanner']] as [$transferAction, $scannerRoute]) {
            $this->actingAs($deliveryUser)->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $sellerOrder->shipment->tracking_code, 'mode' => 'hub_transfer_dispatch'])->assertRedirect(route('rider.shipment-scanner'));
            $this->post(route('rider.shipment-scanner.action', $sellerOrder->shipment), ['action' => $transferAction])->assertRedirect(route('rider.shipment-scanner'));
        }
        $this->actingAs($logisticsUser)->post(route('logistics.shipment-scanner.resolve'), ['tracking_code' => $sellerOrder->shipment->tracking_code, 'mode' => 'hub_arrival', 'hub_id' => $hub->id])->assertRedirect(route('logistics.shipment-scanner'));
        $this->post(route('logistics.shipment-scanner.action', $sellerOrder->shipment), ['action' => 'arrive_hub'])->assertRedirect(route('logistics.shipment-scanner'));
        $deliveryUser->rider->update(['logistics_hub_id' => $hub->id]);
        $this->post(route('logistics.database.order.status', $sellerOrder), [
            'status' => OrderStatus::AssignedToRider->value,
            'rider_id' => $deliveryUser->rider->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($deliveryUser)
            ->post(route('rider.database.delivery.accept', $sellerOrder))
            ->assertSessionHasNoErrors();

        $this->actingAs($deliveryUser)
            ->post(route('rider.shipment-scanner.resolve'), ['tracking_code' => $sellerOrder->shipment->tracking_code, 'mode' => 'last_mile_dispatch'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->actingAs($deliveryUser)
            ->post(route('rider.shipment-scanner.action', $sellerOrder->shipment), ['action' => 'begin_delivery'])
            ->assertRedirect(route('rider.shipment-scanner'));
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::OutForDelivery, $sellerOrder->shipment->fresh()->workflow_status);

        $this->actingAs($deliveryUser)
            ->post(route('rider.database.order.status', $sellerOrder), [
                'status' => OrderStatus::Delivered->value,
                'proof_photo' => \Tests\Support\DeliveryProofFile::image(),
                'received_by' => 'Buyer', 'cod_collected' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($buyer)
            ->post(route('order.status.update', $order->order_number), ['status' => 'completed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Completed, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::Completed->value, $order->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $sellerOrder->shipment->fresh()->workflow_status);
    }

    public function test_seeded_admin_can_render_existing_management_areas(): void
    {
        $this->seedAcceptanceAccounts();
        $admin = User::where('email', 'admin@test.com')->sole();

        $this->actingAs($admin)->get(route('superadmin.users'))->assertOk();
        $this->get(route('superadmin.applications'))->assertOk();
        $this->get(route('superadmin.sellers'))->assertOk();
        $this->get(route('superadmin.products.index'))->assertOk();
        $this->get(route('superadmin.compliance.index'))->assertOk();
        $this->get(route('superadmin.complaints.index'))->assertOk();
    }

    public function test_seeded_buyer_and_seller_can_message_from_product_and_seller_order_contexts(): void
    {
        $this->seedAcceptanceAccounts();

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $sellerUser = User::where('email', 'seller@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();
        $address = $buyer->addresses()->where('is_default', true)->sole();

        $this->actingAs($buyer)
            ->post(route('buyer.messages.context.product.store', $product))
            ->assertRedirect();

        $productConversation = Conversation::where('product_id', $product->id)->sole();
        $this->post(route('buyer.messages.store', $productConversation), [
            'body' => 'Is this acceptance product available?',
        ])->assertRedirect();

        $this->actingAs($sellerUser)
            ->get(route('seller.messages.show', $productConversation))
            ->assertOk()
            ->assertSee('Is this acceptance product available?');
        $this->post(route('seller.messages.store', $productConversation), [
            'body' => 'Yes, it is available.',
        ])->assertRedirect();

        $this->assertSame(2, $productConversation->participants()->count());
        $this->assertSame(2, $productConversation->messages()->count());

        $this->actingAs($buyer)
            ->get(route('buyer.messages.show', $productConversation))
            ->assertOk()
            ->assertSee('Yes, it is available.');

        $this->post(route('buyer.cart.add', $product->slug), ['quantity' => 1]);
        $this->post(route('buyer.checkout.place-order'), array_merge([
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ], $this->cartCheckoutIntent($buyer)))->assertSessionHasNoErrors();

        $sellerOrder = $buyer->buyerOrders()->sole()->sellerOrders()->sole();

        $this->post(route('buyer.messages.context.seller-order.store', $sellerOrder))
            ->assertRedirect();

        $orderConversation = Conversation::where('seller_order_id', $sellerOrder->id)->sole();
        $this->assertNotSame($productConversation->id, $orderConversation->id);
        $this->assertSame(2, $orderConversation->participants()->count());

        $this->actingAs($sellerUser)
            ->post(route('seller.messages.context.seller-order.store', $sellerOrder))
            ->assertRedirect(route('seller.messages.show', $orderConversation));

        $this->assertSame(2, Conversation::count());
        $this->assertDatabaseCount('conversation_participants', 4);
        $this->assertDatabaseCount('messages', 2);
    }

    private function seedAcceptanceAccounts(): void
    {
        $this->seed(TestAccountsSeeder::class);
        $product = Product::where('slug', 'acceptance-product')->sole();
        Storage::disk('public')->put('products/acceptance-product.webp', 'acceptance product fixture image');
        $product->images()->firstOrCreate(
            ['path' => 'products/acceptance-product.webp'],
            ['position' => 1],
        );
    }
}
