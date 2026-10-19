<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoBCheckoutLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_catalog_checkout_uses_database_price_and_creates_one_seller_order_per_seller(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->address($buyer);
        $first = $this->catalog('first', 12500, 5);
        $second = $this->catalog('second', 7500, 4);
        $this->cart($buyer, $first['product'], $first['variant'], 2, 1.00);
        $this->cart($buyer, $second['product'], $second['variant'], 1, 1.00);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
            'total' => 0,
            'seller_id' => 999999,
        ])->assertSessionHasNoErrors();

        $order = Order::with('sellerOrders.items', 'items')->sole();
        $this->assertSame('PLACED', $order->status);
        $this->assertNull($order->seller_id);
        $this->assertSame(42500, $order->total_minor);
        $this->assertSame('100.00', $order->shipping_fee);
        $this->assertCount(2, $order->sellerOrders);
        $this->assertSame(3, $order->items->sum('quantity'));
        $this->assertSame(3, $first['variant']->fresh()->stock);
        $this->assertSame(3, $second['variant']->fresh()->stock);
        $this->assertSame(0, $buyer->cartItems()->count());
        $this->assertSame(1, $buyer->unreadNotifications()->count());
        foreach ($order->sellerOrders as $sellerOrder) {
            $this->assertSame(1, $sellerOrder->seller->owner->unreadNotifications()->count());
        }
    }

    public function test_pending_rejected_and_archived_products_cannot_be_checked_out_and_leave_stock_unchanged(): void
    {
        foreach (['PENDING', 'REJECTED'] as $status) {
            $buyer = $this->user('buyer');
            $item = $this->catalog(strtolower($status), 5000, 2, $status);
            $this->cart($buyer, $item['product'], $item['variant']);

            $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
                'address_id' => $this->address($buyer)->id,
                'payment_method' => 'cod',
            ])->assertSessionHasErrors('cart');

            $this->assertSame(2, $item['variant']->fresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }

        $buyer = $this->user('buyer');
        $item = $this->catalog('archived', 5000, 2);
        $item['product']->delete();
        $this->cart($buyer, $item['product'], $item['variant']);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $this->address($buyer)->id,
            'payment_method' => 'cod',
        ])->assertSessionHasErrors('cart');
        $this->assertSame(2, $item['variant']->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_order_records_and_stock(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalog('limited', 5000, 1);
        $this->cart($buyer, $item['product'], $item['variant'], 2);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $this->address($buyer)->id,
            'payment_method' => 'cod',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $item['variant']->fresh()->stock);
    }

    public function test_only_the_cart_owner_can_checkout_their_cart(): void
    {
        $owner = $this->user('buyer');
        $other = $this->user('buyer');
        $item = $this->catalog('owned-cart', 5000, 3);
        $this->cart($owner, $item['product'], $item['variant']);

        $this->actingAs($other)->post(route('buyer.checkout.place-order'), [
            'address_id' => $this->address($other)->id,
            'payment_method' => 'cod',
        ])->assertSessionHasErrors('cart');

        $this->assertSame(3, $item['variant']->fresh()->stock);
        $this->assertSame(1, $owner->cartItems()->count());
    }

    public function test_cart_and_checkout_render_the_authoritative_variant_price(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalog('display-price', 12500, 3);
        $this->cart($buyer, $item['product'], $item['variant'], 1, 1.00);

        $this->actingAs($buyer)->get(route('buyer.cart'))
            ->assertOk()
            ->assertSee('125.00')
            ->assertSee('Merchandise Subtotal')
            ->assertSee('Shipping')
            ->assertSee('175.00')
            ->assertDontSee('1.00');

        $this->actingAs($buyer)->get(route('buyer.checkout'))
            ->assertOk()
            ->assertSee('125.00')
            ->assertSee('Total Shipping')
            ->assertSee('COD Grand Total')
            ->assertSee('175.00')
            ->assertSee('Delivery Address');
    }

    public function test_cart_and_checkout_warn_when_a_cart_item_becomes_unavailable(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalog('unavailable-display', 5000, 3, 'PENDING');
        $this->cart($buyer, $item['product'], $item['variant']);

        $this->actingAs($buyer)->get(route('buyer.cart'))
            ->assertOk()
            ->assertSee('Unavailable: this item can no longer be checked out.');

        $this->actingAs($buyer)->get(route('buyer.checkout'))
            ->assertOk()
            ->assertSee('This item is no longer available in the marketplace.')
            ->assertSee('disabled', false);
    }

    public function test_gcash_is_rejected_by_canonical_and_legacy_checkout_endpoints(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->address($buyer);
        $item = $this->catalog('cod-only', 5000, 3);
        $this->cart($buyer, $item['product'], $item['variant']);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'gcash',
        ])->assertSessionHasErrors('payment_method');

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order.legacy'), [
            'address_id' => $address->id,
            'payment_method' => 'gcash',
        ])->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $item['variant']->fresh()->stock);
    }

    public function test_buyer_cancellation_restores_stock_once_and_blocks_seller_processing(): void
    {
        $buyer = $this->user('buyer');
        $item = $this->catalog('cancellable', 5000, 5);
        $this->cart($buyer, $item['product'], $item['variant'], 2);
        $this->checkout($buyer);
        $order = Order::with('sellerOrders')->sole();
        $sellerOrder = $order->sellerOrders->sole();

        $this->assertSame(3, $item['variant']->fresh()->stock);
        $this->actingAs($buyer)->post(route('buyer.order.cancel', $order->order_number), [
            'cancel_reason' => 'changed_mind',
        ])->assertSessionHasNoErrors();

        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame('cancelled', $sellerOrder->fresh()->status);
        $this->assertSame(5, $item['variant']->fresh()->stock);

        $this->actingAs($buyer)->post(route('buyer.order.cancel', $order->order_number), [
            'cancel_reason' => 'changed_mind',
        ])->assertSessionHasNoErrors();
        $this->assertSame(5, $item['variant']->fresh()->stock);

        $this->actingAs($item['seller']->owner)->post(route('seller.orders.status', $sellerOrder), [
            'status' => 'confirmed',
        ])->assertSessionHasErrors('order');
    }

    public function test_buyer_cannot_cancel_another_buyers_or_already_processing_order(): void
    {
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $item = $this->catalog('private-cancel', 5000, 4);
        $this->cart($buyer, $item['product'], $item['variant']);
        $this->checkout($buyer);
        $order = Order::with('sellerOrders')->sole();

        $this->actingAs($other)->post(route('buyer.order.cancel', $order->order_number), [
            'cancel_reason' => 'changed_mind',
        ])->assertNotFound();

        $this->actingAs($item['seller']->owner)->post(route('seller.orders.status', $order->sellerOrders->sole()), [
            'status' => 'confirmed',
        ])->assertSessionHasNoErrors();

        $this->actingAs($buyer)->post(route('buyer.order.cancel', $order->order_number), [
            'cancel_reason' => 'changed_mind',
        ])->assertSessionHasErrors('order');
        $this->assertSame(3, $item['variant']->fresh()->stock);
    }

    private function checkout(User $buyer): void
    {
        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $this->address($buyer)->id,
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);

        return $user;
    }

    private function catalog(string $slug, int $price, int $stock, string $moderation = 'APPROVED'): array
    {
        $owner = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $owner->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug.'-seller',
            'status' => 'approved',
            'commission_bps' => 1000,
        ]);
        $category = Category::firstOrCreate(['slug' => 'checkout-category'], ['name' => 'Checkout', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug.'-product',
            'description' => 'Checkout fixture product.',
            'is_active' => true,
            'moderation_status' => $moderation,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug).'-SKU',
            'name' => 'Default',
            'price_minor' => $price,
            'stock' => $stock,
            'is_active' => true,
        ]);

        return compact('seller', 'product', 'variant');
    }

    private function cart(User $buyer, Product $product, ProductVariant $variant, int $quantity = 1, float $price = 1): void
    {
        CartItem::create([
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'product_variant_id' => $variant->id,
            'price' => $price,
            'quantity' => $quantity,
        ]);
    }

    private function address(User $buyer): Address
    {
        return Address::firstOrCreate(['user_id' => $buyer->id], [
            'label' => 'Home', 'recipient' => $buyer->name, 'phone' => '09123456789',
            'line1' => '123 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Test City',
            'province' => 'Test Province', 'postal_code' => '4000', 'is_default' => true,
        ]);
    }
}
