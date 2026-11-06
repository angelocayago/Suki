<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\CartItem;
use App\Models\Address;
use App\Models\Order;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneVariantCartContractTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\FakesCheckoutLocations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeCheckoutLocations();
    }

    public function test_foreign_and_inactive_variant_ids_are_rejected(): void
    {
        $buyer = $this->buyer();
        [, $product, $variant] = $this->product('First', 10000, 5);
        [, $otherProduct, $foreign] = $this->product('Second', 20000, 5);
        $inactive = ProductVariant::create(['product_id' => $product->id, 'sku' => 'INACTIVE-'.$product->id, 'name' => 'Inactive', 'price_minor' => 9000, 'stock' => 5, 'is_active' => false]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $foreign->id, 'quantity' => 1])->assertNotFound();
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $inactive->id, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertNotSame($product->id, $otherProduct->id);
        $this->assertNotNull($variant);
    }

    public function test_selected_variant_price_and_stock_are_authoritative(): void
    {
        $buyer = $this->buyer();
        [, $product, $default] = $this->product('Options', 10000, 99);
        $selected = ProductVariant::create(['product_id' => $product->id, 'sku' => 'OPTIONS-SMALL', 'name' => 'Small', 'options' => ['Size' => 'S'], 'price_minor' => 27550, 'stock' => 2, 'is_active' => true]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $selected->id, 'quantity' => 8])->assertRedirect();

        $item = CartItem::where('user_id', $buyer->id)->sole();
        $this->assertSame($selected->id, $item->product_variant_id);
        $this->assertSame('275.50', $item->price);
        $this->assertSame(2, $item->quantity);
        $this->assertNotSame($default->id, $item->product_variant_id);
    }

    public function test_two_variants_of_one_product_create_distinct_cart_lines_and_default_add_still_works(): void
    {
        $buyer = $this->buyer();
        [, $product, $default] = $this->product('Two variants', 10000, 5);
        $second = ProductVariant::create(['product_id' => $product->id, 'sku' => 'TWO-SECOND', 'name' => 'Large', 'options' => ['Size' => 'L'], 'price_minor' => 15000, 'stock' => 4, 'is_active' => true]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 1])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_variant_id' => $default->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_variant_id' => $second->id, 'quantity' => 1]);
    }

    public function test_both_variants_render_at_current_prices_and_checkout_persists_exact_lines_once(): void
    {
        $buyer = $this->buyer();
        [, $product, $first] = $this->product('Shared product', 10001, 5);
        $second = $this->secondVariant($product);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $first->id, 'quantity' => 2]);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 1]);
        // Stale cart snapshots and submitted totals cannot change the quote.
        $first->update(['price_minor' => 12501]);
        CartItem::where('user_id', $buyer->id)->update(['price' => 1]);
        $summary = app(CheckoutService::class)->cartSummary($buyer);
        $this->assertCount(2, $summary['cart']);
        $this->assertSame(400.03, $summary['subtotal']);
        $this->assertSame(450.03, $summary['total']);
        $rows = CartItem::where('user_id', $buyer->id)->orderBy('id')->get();
        $cart = $this->get(route('buyer.cart'))->assertOk()->assertSee('Default')->assertSee('Large')->assertSee('125.01')->assertSee('150.01');
        foreach ($rows as $row) {
            $cart->assertSee(route('buyer.cart.remove', $row->id), false);
            $cart->assertSee(route('buyer.cart.increase', $row->id), false);
        }
        $this->get(route('buyer.checkout'))->assertOk()->assertViewHas('cart', $summary['cart'])->assertViewHas('total', $summary['total']);
        $address = Address::create($this->checkoutLocationCodes() + ['user_id' => $buyer->id, 'label' => 'Home', 'recipient' => 'Buyer', 'phone' => '09123456789', 'line1' => 'Street', 'barangay' => 'B', 'city' => 'C', 'province' => 'P']);
        $other = $this->buyer();
        $otherRow = CartItem::create(['user_id' => $other->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'product_variant_id' => $first->id, 'price' => 1, 'quantity' => 1]);
        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod', 'total' => 0])->assertSessionHasNoErrors();
        $order = Order::with('items')->sole();
        $this->assertSame(45003, $order->total_minor);
        $this->assertSame([$first->id => 2, $second->id => 1], $order->items->pluck('quantity', 'product_variant_id')->all());
        $this->assertSame(3, $first->fresh()->stock);
        $this->assertSame(3, $second->fresh()->stock);
        $this->assertDatabaseHas('cart_items', ['id' => $otherRow->id, 'quantity' => 1]);
        $this->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('buyer.order-success', $order->order_number))->assertOk()->assertSee('450.03')
            ->assertViewHas('order', fn ($viewOrder) => count($viewOrder['items']) === 2);
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()
            ->assertViewHas('order', fn ($viewOrder) => count($viewOrder['items']) === 2);
    }

    public function test_each_quantity_action_and_remove_target_only_the_owned_line(): void
    {
        $buyer = $this->buyer();
        [, $product, $first] = $this->product('Mutations', 10000, 5);
        $second = $this->secondVariant($product);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 2]);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 2]);
        $rows = CartItem::where('user_id', $buyer->id)->get()->keyBy('product_variant_id');
        $firstRow = $rows[$first->id];
        $secondRow = $rows[$second->id];
        $this->post(route('buyer.cart.increase', $firstRow->id))->assertSessionHasNoErrors();
        $this->assertSame(3, $firstRow->fresh()->quantity);
        $this->post(route('buyer.cart.decrease', $firstRow->id))->assertSessionHasNoErrors();
        $this->assertSame(2, $firstRow->fresh()->quantity);
        $this->post(route('buyer.cart.update', $firstRow->id), ['quantity' => 4])->assertSessionHasNoErrors();
        $this->assertSame(4, $firstRow->fresh()->quantity);
        $this->assertSame(2, $secondRow->fresh()->quantity);
        foreach ([0, -1, 1.5, 'bad', 6] as $quantity) {
            $this->post(route('buyer.cart.update', $firstRow->id), ['quantity' => $quantity])->assertSessionHasErrors('quantity');
            $this->assertSame(4, $firstRow->fresh()->quantity);
        }
        $this->post(route('buyer.cart.remove', $firstRow->id))->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['id' => $firstRow->id]);
        $this->assertSame(2, $secondRow->fresh()->quantity);
        $this->post(route('buyer.cart.update', $secondRow->id), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('buyer.cart.decrease', $secondRow->id))->assertRedirect();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_ids_require_buyer_ownership_and_slug_requests_do_not_choose_a_row(): void
    {
        $owner = $this->buyer();
        [, $product] = $this->product('Private cart', 10000, 5);
        $this->actingAs($owner)->post(route('buyer.cart.add', $product->slug), ['quantity' => 2]);
        $row = CartItem::sole();
        $this->actingAs($this->buyer());
        foreach (['increase', 'decrease', 'update', 'remove'] as $action) {
            $this->post(route('buyer.cart.'.$action, $row->id), ['quantity' => 1])->assertNotFound();
            $this->post('/cart/'.$action.'/'.$product->slug, ['quantity' => 1])->assertNotFound();
        }
        $this->assertSame(2, $row->fresh()->quantity);
        $this->app['auth']->forgetGuards();
        $this->post(route('buyer.cart.remove', $row->id))->assertRedirect(route('login'));
    }

    public function test_unavailable_variant_blocks_the_entire_checkout_without_substituting_another_variant(): void
    {
        $buyer = $this->buyer();
        [, $product, $first] = $this->product('Unavailable', 10000, 5);
        $second = $this->secondVariant($product);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 1]);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 1]);
        $row = CartItem::where('product_variant_id', $second->id)->sole();
        foreach (['out_of_stock', 'inactive', 'deleted'] as $state) {
            if ($state === 'out_of_stock') $second->update(['stock' => 0]);
            if ($state === 'inactive') $second->update(['stock' => 4, 'is_active' => false]);
            if ($state === 'deleted') { $second->update(['is_active' => true]); $second->delete(); }
            $this->get(route('buyer.cart'))->assertOk()->assertSee('Unavailable:')->assertViewHas('has_unavailable_items', true);
            $this->post(route('buyer.cart.increase', $row->id))->assertSessionHasErrors();
            try {
                app(CheckoutService::class)->checkout($buyer, [], 'cod');
                $this->fail('Unavailable cart should reject checkout.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey('cart', $exception->errors());
            }
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('cart_items', 2);
            $this->assertSame(5, $first->fresh()->stock);
        }
    }

    public function test_add_rejects_invalid_quantity_and_out_of_stock_variant(): void
    {
        $buyer = $this->buyer();
        [, $product, $variant] = $this->product('No stock', 10000, 0);
        foreach ([0, -1, 'bad', 1.5] as $quantity) {
            $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => $quantity])->assertSessionHasErrors('quantity');
        }
        $this->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_wishlist_transfers_remove_only_the_selected_cart_row_and_preserve_siblings(): void
    {
        $buyer = $this->buyer();
        [, $product, $first] = $this->product('Wishlist siblings', 10000, 5);
        $second = $this->secondVariant($product);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 2]);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 2]);
        $row = CartItem::where('product_variant_id', $first->id)->sole();
        $sibling = CartItem::where('product_variant_id', $second->id)->sole();
        $this->actingAs($this->buyer())->post(route('buyer.wishlist.add', $product->slug), ['cart_item_id' => $row->id])->assertNotFound();
        $this->assertDatabaseCount('wishlist_items', 0);
        $this->actingAs($buyer)->post(route('buyer.wishlist.add', $product->slug), ['cart_item_id' => $row->id])->assertRedirect();
        $this->assertDatabaseMissing('cart_items', ['id' => $row->id]);
        $this->assertSame(2, $sibling->fresh()->quantity);
        $this->post(route('buyer.wishlist.move-to-cart', $product->slug))->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $first->id, 'quantity' => 1]);
        $this->assertSame(2, $sibling->fresh()->quantity);
        $this->assertSame($second->id, $sibling->fresh()->product_variant_id);
        // Product-level wishlist saves must not erase any cart variants.
        $this->post(route('buyer.wishlist.add', $product->slug))->assertRedirect();
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_legacy_checkout_urls_use_canonical_prices_and_keep_variant_lines(): void
    {
        $buyer = $this->buyer();
        [, $product, $first] = $this->product('Legacy checkout', 10000, 5);
        $second = $this->secondVariant($product);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 1]);
        $this->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 1]);
        $address = Address::create($this->checkoutLocationCodes() + ['user_id' => $buyer->id, 'label' => 'Home', 'recipient' => 'Buyer', 'phone' => '09123456789', 'line1' => 'Street', 'barangay' => 'B', 'city' => 'C', 'province' => 'P']);
        $this->get(route('buyer.checkout.legacy'))->assertRedirect(route('buyer.checkout'));
        $this->post(route('buyer.checkout.place-order.legacy'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::with('items')->sole();
        $this->assertCount(2, $order->items);
        $this->assertSame(30001, $order->total_minor);
    }

    private function secondVariant(Product $product): ProductVariant
    {
        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'LARGE-'.$product->id, 'name' => 'Large', 'options' => ['Size' => 'L'], 'price_minor' => 15001, 'stock' => 4, 'is_active' => true]);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $buyer->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'buyer'])->id]);
        return $buyer;
    }

    private function product(string $name, int $priceMinor, int $stock): array
    {
        $category = Category::firstOrCreate(['slug' => 'variant-cart'], ['name' => 'Variant cart', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $owner->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'seller'])->id]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => $name.' shop', 'slug' => str($name)->slug()->append('-'.$owner->id), 'category_id' => $category->id, 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug()->append('-'.$seller->id), 'description' => 'Variant cart fixture.', 'is_active' => true, 'moderation_status' => ProductModerationStatus::Approved->value]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'DEFAULT-'.$product->id, 'name' => 'Default', 'price_minor' => $priceMinor, 'stock' => $stock, 'is_active' => true]);
        return [$seller, $product, $variant];
    }
}
