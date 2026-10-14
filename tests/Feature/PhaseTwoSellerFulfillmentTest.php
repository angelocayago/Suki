<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoSellerFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_orders_page_only_displays_the_authenticated_store_orders(): void
    {
        [$sellerUser, $seller] = $this->seller('visible-store');
        [, $otherSeller] = $this->seller('hidden-store');
        $ownOrder = $this->sellerOrder($seller, 'OWN-ORDER', 'Visible Product');
        $this->sellerOrder($otherSeller, 'OTHER-ORDER', 'Hidden Product');

        $response = $this->actingAs($sellerUser)->get(route('seller.orders'));

        $response->assertOk();
        $response->assertSee('Visible Product');
        $response->assertDontSee('Hidden Product');
        $response->assertSee(route('seller.orders.status', $ownOrder));
    }

    public function test_seller_dashboard_uses_database_orders_and_inventory(): void
    {
        [$sellerUser, $seller] = $this->seller('dashboard-store');
        $this->sellerOrder($seller, 'DASHBOARD-ORDER', 'Dashboard Order Product');
        $this->product($seller, 'Dashboard Inventory Product', 'DASHBOARD-SKU', 4);

        $response = $this->actingAs($sellerUser)->get(route('seller.dashboard'));

        $response->assertOk();
        $response->assertSee('DASHBOARD-ORDER');
        $response->assertSee('Dashboard Inventory Product');
    }

    public function test_seller_can_complete_only_the_three_seller_fulfillment_transitions(): void
    {
        [$sellerUser, $seller] = $this->seller('workflow-store');
        $sellerOrder = $this->sellerOrder($seller, 'FLOW-ORDER', 'Workflow Product');

        $this->actingAs($sellerUser)->post(route('seller.orders.status', $sellerOrder), [
            'status' => 'confirmed',
        ])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($sellerUser)->post(route('seller.orders.status', $sellerOrder), [
            'status' => 'preparing',
        ])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Preparing, $sellerOrder->fresh()->workflow_status);

        $this->actingAs($sellerUser)->post(route('seller.orders.status', $sellerOrder), [
            'status' => 'ready_for_pickup',
        ])->assertSessionHasNoErrors();

        $sellerOrder->refresh();
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->workflow_status);
        $this->assertNotNull($sellerOrder->confirmed_at);
        $this->assertNotNull($sellerOrder->preparing_at);
        $this->assertNotNull($sellerOrder->ready_for_pickup_at);
        $this->assertSame(OrderStatus::ReadyForPickup, $sellerOrder->shipment->workflow_status);
        $this->assertSame('READY_FOR_PICKUP', $sellerOrder->order->status);
        $this->assertNull($sellerOrder->shipment->logistics_provider_id);
        $this->assertNull($sellerOrder->shipment->pickup_rider_id);
        $this->assertNull($sellerOrder->shipment->rider_id);
    }

    public function test_seller_cannot_update_another_stores_seller_order(): void
    {
        [$owner, $seller] = $this->seller('owner-store');
        [$otherSeller] = $this->seller('other-store');
        $sellerOrder = $this->sellerOrder($seller, 'PRIVATE-ORDER', 'Private Product');

        $this->actingAs($otherSeller)->post(route('seller.orders.status', $sellerOrder), [
            'status' => 'confirmed',
        ])->assertForbidden();

        $this->assertSame(OrderStatus::Placed, $sellerOrder->fresh()->workflow_status);
        $this->assertNotNull($owner);
    }

    public function test_seller_endpoint_rejects_non_seller_statuses(): void
    {
        [$sellerUser, $seller] = $this->seller('restricted-store');
        $sellerOrder = $this->sellerOrder($seller, 'RESTRICTED-ORDER', 'Restricted Product');

        $this->actingAs($sellerUser)
            ->from(route('seller.orders'))
            ->post(route('seller.orders.status', $sellerOrder), [
                'status' => 'picked_up',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Placed, $sellerOrder->fresh()->workflow_status);
    }

    public function test_inventory_page_reads_only_the_authenticated_sellers_variants(): void
    {
        [$sellerUser, $seller] = $this->seller('inventory-store');
        [, $otherSeller] = $this->seller('other-inventory-store');
        $this->product($seller, 'Visible Inventory', 'VISIBLE-SKU', 7);
        $this->product($otherSeller, 'Hidden Inventory', 'HIDDEN-SKU', 12);

        $response = $this->actingAs($sellerUser)->get(route('seller.inventory'));

        $response->assertOk();
        $response->assertSee('VISIBLE-SKU');
        $response->assertDontSee('HIDDEN-SKU');
        $response->assertSee('disabled', false);
    }

    public function test_session_orders_remain_available_when_the_seller_has_no_database_orders(): void
    {
        [$sellerUser] = $this->seller('legacy-store');

        $response = $this->actingAs($sellerUser)
            ->withSession([
                'orders' => [
                    99 => [
                        'id' => 99,
                        'status' => 'placed',
                        'shipping_address' => ['name' => 'Legacy Buyer', 'phone' => '09000000000'],
                        'items' => [['name' => 'Legacy Session Item', 'quantity' => 1]],
                        'total' => 100,
                        'created_at' => now()->toDateTimeString(),
                    ],
                ],
            ])
            ->get(route('seller.orders'));

        $response->assertOk();
        $response->assertSee('Legacy Session Item');
        $response->assertSee(route('order.status.update', 99));
    }

    private function seller(string $slug): array
    {
        $user = User::factory()->create([
            'role' => 'seller',
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $seller = Seller::create([
            'user_id' => $user->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'status' => 'approved',
            'commission_bps' => 1000,
        ]);

        return [$user, $seller];
    }

    private function sellerOrder(Seller $seller, string $reference, string $productName): SellerOrder
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $order = Order::create([
            'order_number' => $reference,
            'reference' => $reference,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->user_id,
            'status' => 'PLACED',
            'payment_method' => 'cod',
            'payment_status' => 'UNPAID',
            'subtotal' => 100,
            'shipping_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 100,
            'total_minor' => 10000,
            'recipient_name' => 'Test Buyer',
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'Municipality',
            'barangay' => 'Barangay',
            'street_address' => 'Street',
            'shipping_address' => ['recipient_name' => 'Test Buyer'],
            'placed_at' => now(),
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed,
            'status_changed_at' => now(),
        ]);
        $order->items()->create([
            'seller_id' => $seller->user_id,
            'seller_order_id' => $sellerOrder->id,
            'product_slug' => str($productName)->slug()->toString(),
            'product_name' => $productName,
            'unit_price' => 100,
            'unit_price_minor' => 10000,
            'quantity' => 1,
            'line_total' => 100,
        ]);
        Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => null,
            'tracking_code' => 'TRACK-'.$sellerOrder->id,
            'status' => 'unassigned',
            'workflow_status' => OrderStatus::Placed,
            'fee_minor' => 0,
            'cod_amount_minor' => 10000,
            'cod_collected' => false,
            'attempts' => 0,
            'status_changed_at' => now(),
        ]);

        return $sellerOrder;
    }

    private function product(Seller $seller, string $name, string $sku, int $stock): ProductVariant
    {
        $category = Category::firstOrCreate(
            ['slug' => 'seller-inventory'],
            ['name' => 'Seller Inventory', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_active' => true,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => 'Default',
            'price_minor' => 2500,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }
}
