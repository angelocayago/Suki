<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_grouped_fulfillment_records_without_a_provider(): void
    {
        $buyer = $this->user('buyer');
        $first = $this->catalogItem('First Store', 'first-store', 'first-product', 5000, 10);
        $second = $this->catalogItem('Second Store', 'second-store', 'second-product', 7500, 5);
        $address = $this->address($buyer);

        $this->cartItem($buyer, $first['product'], $first['variant'], 2);
        $this->cartItem($buyer, $second['product'], $second['variant'], 1);

        $response = $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ]);

        $order = $buyer->buyerOrders()->with('sellerOrders.shipment', 'items')->sole();

        $response->assertRedirect(route('buyer.order-success', $order->order_number));
        $this->assertSame('PLACED', $order->status);
        $this->assertNull($order->seller_id);
        $this->assertSame(17500, $order->total_minor);
        $this->assertSame($address->recipient, $order->shipping_address['recipient_name']);
        $this->assertCount(2, $order->sellerOrders);
        $this->assertCount(2, $order->items);
        $this->assertSame(0, $buyer->cartItems()->count());
        $this->assertSame(8, $first['variant']->fresh()->stock);
        $this->assertSame(4, $second['variant']->fresh()->stock);

        foreach ($order->sellerOrders as $sellerOrder) {
            $this->assertSame(OrderStatus::Placed, $sellerOrder->workflow_status);
            $this->assertNotNull($sellerOrder->shipment);
            $this->assertSame(OrderStatus::Placed, $sellerOrder->shipment->workflow_status);
            $this->assertNull($sellerOrder->logistics_provider_id);
            $this->assertNull($sellerOrder->shipment->logistics_provider_id);
            $this->assertNull($sellerOrder->shipment->pickup_rider_id);
            $this->assertNull($sellerOrder->shipment->rider_id);
        }

        foreach ($order->items as $item) {
            $this->assertNotNull($item->seller_order_id);
            $this->assertNotNull($item->product_id);
            $this->assertNotNull($item->product_variant_id);
        }
    }

    public function test_insufficient_inventory_rolls_back_the_entire_checkout(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalogItem('Stock Store', 'stock-store', 'limited-product', 2500, 1);
        $address = $this->address($buyer);
        $this->cartItem($buyer, $item['product'], $item['variant'], 2);

        $response = $this->actingAs($buyer)->from(route('buyer.checkout'))->post(
            route('buyer.checkout.place-order'),
            ['address_id' => $address->id, 'payment_method' => 'cod']
        );

        $response->assertRedirect(route('buyer.checkout'));
        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $item['variant']->fresh()->stock);
        $this->assertSame(1, $buyer->cartItems()->count());
    }

    public function test_legacy_slug_cart_item_resolves_a_single_database_variant(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalogItem('Legacy Store', 'legacy-store', 'legacy-product', 3200, 3);
        $address = $this->address($buyer);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_slug' => $item['product']->slug,
            'product_name' => $item['product']->name,
            'price' => 1,
            'quantity' => 1,
        ]);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'gcash',
        ])->assertSessionHasNoErrors();

        $orderItem = $buyer->buyerOrders()->sole()->items()->sole();
        $this->assertSame($item['variant']->id, $orderItem->product_variant_id);
        $this->assertSame(3200, $orderItem->unit_price_minor);
        $this->assertSame('32.00', $orderItem->unit_price);
        $this->assertSame(2, $item['variant']->fresh()->stock);
        $this->assertSame(0, $orderItem->sellerOrder->shipment->cod_amount_minor);
    }

    public function test_session_address_fallback_is_saved_as_an_order_snapshot(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalogItem('Address Store', 'address-store', 'address-product', 1800, 2);
        $this->cartItem($buyer, $item['product'], $item['variant'], 1);

        $sessionAddress = [
            'id' => 77,
            'name' => 'Session Recipient',
            'phone' => '09999999999',
            'province' => 'Session Province',
            'municipality' => 'Session City',
            'barangay' => 'Session Barangay',
            'street' => '77 Session Street',
            'house_number' => '77',
            'postal_code' => '4077',
            'label' => 'Session Home',
            'is_default' => true,
        ];

        $this->actingAs($buyer)
            ->withSession(['buyer_addresses' => [$sessionAddress]])
            ->post(route('buyer.checkout.place-order'), [
                'address_id' => 77,
                'payment_method' => 'cod',
            ])
            ->assertSessionHasNoErrors();

        $order = $buyer->buyerOrders()->sole();
        $this->assertSame('Session Recipient', $order->recipient_name);
        $this->assertSame('77 Session Street', $order->street_address);
        $this->assertSame('Session City', $order->shipping_address['municipality']);
        $this->assertSame('Session Home', $order->shipping_address['address_label']);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
    }

    private function catalogItem(
        string $sellerName,
        string $sellerSlug,
        string $productSlug,
        int $priceMinor,
        int $stock
    ): array {
        $owner = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $owner->id,
            'name' => $sellerName,
            'slug' => $sellerSlug,
            'status' => 'approved',
            'commission_bps' => 1000,
        ]);
        $category = Category::firstOrCreate(
            ['slug' => 'test-category'],
            ['name' => 'Test Category', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($productSlug)->headline()->toString(),
            'slug' => $productSlug,
            'is_active' => true,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($productSlug).'-DEFAULT',
            'name' => 'Default',
            'price_minor' => $priceMinor,
            'stock' => $stock,
            'is_active' => true,
        ]);

        return compact('seller', 'product', 'variant');
    }

    private function cartItem(
        User $buyer,
        Product $product,
        ProductVariant $variant,
        int $quantity
    ): CartItem {
        return CartItem::create([
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'price' => $variant->price_minor / 100,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }

    private function address(User $buyer): Address
    {
        return Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home',
            'recipient' => $buyer->name,
            'phone' => '09123456789',
            'line1' => '123 Test Street',
            'barangay' => 'Test Barangay',
            'city' => 'Test City',
            'province' => 'Test Province',
            'postal_code' => '4000',
            'is_default' => true,
        ]);
    }
}
