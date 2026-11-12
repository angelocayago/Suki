<?php

namespace Tests\Feature;

use App\Models\{CartItem, Category, Product, ProductVariant, Seller, User, WishlistItem};
use App\Services\ProductCatalogService;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Tests\Support\{BuyerPurchasesSchema, IsolatedStorageTestCase};

class BuyerActionFeedbackTest extends IsolatedStorageTestCase
{
    use \Tests\Support\CartCheckoutIntent;
    private User $buyer;
    private Product $product;
    private ProductVariant $variant;
    private Seller $seller;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp(); $this->assertStoragePathIsIsolated(); BuyerPurchasesSchema::create();
        foreach (['product_id', 'seller_id', 'buyer_id', 'seller_order_id', 'order_item_id', 'rider_id', 'rating'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' INTEGER');
        foreach (['kind', 'eligibility_key', 'body', 'seller_response', 'hidden_at', 'edited'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' TEXT');
        DB::statement('CREATE TABLE cart_items (id INTEGER PRIMARY KEY)');
        foreach (array_unique([...(new CartItem())->getFillable(), 'created_at', 'updated_at']) as $column) DB::statement('ALTER TABLE cart_items ADD COLUMN '.$column.' '.(str_ends_with($column, '_id') || in_array($column, ['quantity', 'selected']) ? 'INTEGER' : 'TEXT'));
        DB::statement('CREATE UNIQUE INDEX feedback_cart_variant ON cart_items(user_id, product_variant_id)');
        Http::preventStrayRequests(); Storage::fake('public');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $this->seller = Seller::create(['user_id' => $owner->id, 'name' => 'Feedback shop', 'slug' => 'feedback-shop', 'status' => 'approved']);
        $category = Category::create(['name' => 'Feedback', 'slug' => 'feedback', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $this->seller->id, 'category_id' => $category->id, 'name' => 'Feedback product', 'slug' => 'feedback-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $this->variant = $this->product->variants()->create(['name' => 'Default', 'sku' => 'FEEDBACK', 'stock' => 5, 'price_minor' => 10000, 'is_active' => true]);
    }

    private function add(array $data = []) { return $this->actingAs($this->buyer)->postJson(route('buyer.cart.add', $this->product->slug), $data + ['variant_id' => $this->variant->id]); }
    private function save(): WishlistItem { return WishlistItem::create(['user_id' => $this->buyer->id, 'product_slug' => $this->product->slug, 'product_name' => 'Saved name', 'price' => '1.00', 'image' => 'saved.jpg']); }

    public function test_invalid_quantities_have_exact_json_and_redirect_feedback_without_writes(): void
    {
        foreach (['' => 'Please enter a quantity.', '0' => 'Please enter a quantity of at least 1.', '-1' => 'Please enter a quantity of at least 1.', '1.5' => 'Quantity must be a whole number.', 'abc' => 'Quantity must be a whole number.'] as $value => $message) {
            $this->add(['quantity' => (string) $value])->assertStatus(422)->assertJsonPath('success', false)->assertJsonPath('code', 'invalid_quantity')->assertJsonPath('message', $message)->assertJsonPath('errors.quantity.0', $message);
            $this->from(route('buyer.product', $this->product->slug))->post(route('buyer.cart.add', $this->product->slug), ['quantity' => (string) $value])->assertRedirect(route('buyer.product', $this->product->slug))->assertSessionHasErrors(['quantity' => $message])->assertSessionHasInput('quantity', (string) $value);
        }
        $this->add(['quantity' => 6])->assertStatus(409)->assertJsonPath('message', 'Only 5 available. Please lower your quantity.');
        $this->assertDatabaseCount('cart_items', 0);
        $this->get(route('buyer.product', $this->product->slug))->assertOk()->assertSee('data-buyer-quantity', false)->assertSee('inputmode="numeric"', false);
    }

    public function test_merged_quantities_are_rejected_without_silent_clamping(): void
    {
        $this->add(['quantity' => 2])->assertOk();
        $this->add(['quantity' => 4])->assertStatus(409)->assertJsonPath('message', 'Your cart already has 2 of this item. Only 3 more can be added.');
        $this->assertSame(2, CartItem::sole()->quantity);
        $this->add(['quantity' => 3])->assertOk();
        $this->add(['quantity' => 1])->assertStatus(409)->assertJsonPath('message', 'Your cart already contains all 5 available items.');
        $this->assertSame(5, CartItem::sole()->quantity);
    }

    public function test_add_rereads_variant_inside_transaction_after_buyer_lock(): void
    {
        app(ProductCatalogService::class)->marketplaceProduct($this->product->slug);
        $armed = true; $readInside = false; $variantId = $this->variant->id;
        DB::listen(function ($query) use (&$armed, &$readInside, $variantId) {
            if ($armed && DB::transactionLevel() > 0 && str_contains($query->sql, 'from "users"')) { $armed = false; DB::table('product_variants')->where('id', $variantId)->update(['stock' => 0]); }
            if (!$armed && DB::transactionLevel() > 0 && str_contains($query->sql, 'from "product_variants"')) $readInside = true;
        });
        $this->add(['quantity' => 1])->assertStatus(409)->assertJsonPath('code', 'out_of_stock')->assertJsonPath('message', 'The selected variation is out of stock.');
        $this->assertFalse($armed); $this->assertTrue($readInside); $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_unavailable_entities_are_friendly_but_foreign_or_nonexistent_ids_stay_404(): void
    {
        $this->variant->update(['stock' => 0]); $this->add(['quantity' => 1])->assertStatus(409)->assertJsonPath('code', 'out_of_stock');
        $this->variant->update(['stock' => 5, 'is_active' => false]); $this->add(['quantity' => 1])->assertStatus(422)->assertJsonPath('message', 'This product is no longer available.');
        $this->variant->update(['is_active' => true]);
        $this->product->update(['is_active' => false]); $this->add(['quantity' => 1])->assertStatus(422)->assertJsonPath('code', 'unavailable');
        $this->product->update(['is_active' => true, 'moderation_status' => 'PENDING']); $this->add(['quantity' => 1])->assertStatus(422);
        $this->product->update(['moderation_status' => 'APPROVED']);
        $this->seller->update(['status' => 'rejected']); $this->add(['quantity' => 1])->assertStatus(422)->assertJsonPath('message', 'This shop is currently unavailable.');
        $this->seller->update(['status' => 'approved']);
        $this->variant->delete(); $this->add(['quantity' => 1])->assertStatus(422)->assertJsonPath('code', 'unavailable');
        $this->postJson(route('buyer.cart.add', 'missing-product'), ['quantity' => 1])->assertNotFound();
        $this->postJson(route('buyer.cart.add', $this->product->slug), ['variant_id' => 999999, 'quantity' => 1])->assertNotFound();
        $this->product->delete(); $this->add(['quantity' => 1])->assertStatus(422);
    }

    public function test_guest_role_and_owned_cart_protection_keep_stable_shapes(): void
    {
        $this->postJson(route('buyer.cart.add', $this->product->slug), ['quantity' => 1])->assertStatus(401)->assertJsonStructure(['success', 'message', 'errors', 'code'])->assertJsonPath('code', 'auth_required');
        $this->post(route('buyer.wishlist.toggle', $this->product->slug))->assertRedirect(route('login'))->assertSessionHas('error', 'Please log in to save items to your wishlist.');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->postJson(route('buyer.cart.add', $this->product->slug), ['quantity' => 1])->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->add(['quantity' => 1])->assertOk(); $row = CartItem::sole();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->postJson(route('buyer.cart.update', $row->id), ['quantity' => 'bad'])->assertNotFound();
        $this->postJson(route('buyer.wishlist.add', $this->product->slug), ['cart_item_id' => $row->id])->assertNotFound();
        $this->assertSame(1, $row->fresh()->quantity);
    }

    public function test_cart_stock_drop_has_specific_warning_and_rejected_update_keeps_quantity(): void
    {
        $this->add(['quantity' => 4])->assertOk(); $row = CartItem::sole(); $this->cartCheckoutIntent(); $this->variant->update(['stock' => 2]);
        $this->get($this->selectedCartUrl())->assertOk()->assertSee('Please fix 1 selected item before checkout:')->assertSee('Only 2 available; your cart contains 4. Please lower the quantity or remove this item.')->assertSee('aria-disabled="true"', false)->assertSee('value="4"', false);
        $this->postJson(route('buyer.cart.update', $row->id), ['quantity' => 3])->assertStatus(409)->assertJsonPath('errors.quantity.0', 'Only 2 available. Please lower your quantity.');
        $this->assertSame(4, $row->fresh()->quantity);
        $this->from($this->selectedCartUrl())->post(route('buyer.cart.update', $row->id), ['quantity' => 3])->assertRedirect(route('buyer.cart'))->assertSessionHas('cart_error_line', (string) $row->id);
        $this->get($this->selectedCartUrl())->assertSee('value="4"', false)->assertSee('Only 2 available. Please lower your quantity.');
        $this->postJson(route('buyer.cart.update', $row->id), ['quantity' => 2])->assertOk(); $this->assertSame(2, $row->fresh()->quantity);
        $this->product->update(['is_active' => false]); $this->get($this->selectedCartUrl())->assertSee('This product is no longer available.'); $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_wishlist_moves_use_current_price_and_delete_only_after_success(): void
    {
        $saved = $this->save(); $this->variant->update(['price_minor' => 27550]);
        $this->actingAs($this->buyer)->postJson(route('buyer.wishlist.move-to-cart', $this->product->slug))->assertOk();
        $this->assertDatabaseMissing('wishlist_items', ['id' => $saved->id]); $this->assertSame('275.50', CartItem::sole()->price);
        $saved = $this->save(); $this->variant->update(['stock' => 1]);
        $this->postJson(route('buyer.wishlist.move-to-cart', $this->product->slug))->assertStatus(409);
        $this->assertDatabaseHas('wishlist_items', ['id' => $saved->id]); $this->assertSame(1, CartItem::sole()->quantity);
        $this->product->variants()->create(['name' => 'Other', 'sku' => 'OTHER', 'price_minor' => 30000, 'stock' => 2, 'is_active' => true]);
        $this->post(route('buyer.wishlist.move-to-cart', $this->product->slug))->assertRedirect(route('buyer.product', $this->product->slug));
        $this->assertDatabaseHas('wishlist_items', ['id' => $saved->id]); $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_wishlist_duplicate_missing_and_unavailable_toggle_feedback(): void
    {
        $this->actingAs($this->buyer)->postJson(route('buyer.wishlist.add', $this->product->slug))->assertOk();
        $this->postJson(route('buyer.wishlist.add', $this->product->slug))->assertOk()->assertJsonPath('message', 'Already in your wishlist.'); $this->assertDatabaseCount('wishlist_items', 1);
        $this->variant->update(['stock' => 0]);
        $this->get(route('buyer.wishlist'))->assertOk()->assertSee('This item is out of stock.')->assertSee('disabled', false);
        $this->product->update(['is_active' => false]);
        $this->get(route('buyer.wishlist'))->assertSee('This product is no longer available.');
        $this->postJson(route('buyer.wishlist.toggle', $this->product->slug))->assertOk(); $this->assertDatabaseCount('wishlist_items', 0);
        $this->postJson(route('buyer.wishlist.move-to-cart', $this->product->slug))->assertStatus(422)->assertJsonPath('message', 'This item is no longer in your wishlist.');
    }
    public function test_cart_updates_validate_each_input_and_unauthenticated_json_keeps_its_shape(): void
    {
        $this->postJson(route('buyer.cart.update', 999), ['quantity' => 1])->assertStatus(401)->assertJsonPath('code', 'auth_required');
        $this->add(['quantity' => 2])->assertOk(); $row = CartItem::sole();
        foreach (['', 0, -1, '1.5', 'abc', 6] as $quantity) {
            $json = $this->postJson(route('buyer.cart.update', $row->id), compact('quantity'));
            $json->assertStatus($quantity === 6 ? 409 : 422)->assertJsonStructure(['success', 'message', 'errors', 'code']);
            $this->post(route('buyer.cart.update', $row->id), compact('quantity'))->assertRedirect()->assertSessionHasErrors('quantity');
            $this->assertSame(2, $row->fresh()->quantity);
        }
    }

    public function test_hidden_product_action_redirects_to_visible_feedback_instead_of_a_product_404(): void
    {
        $this->product->update(['is_active' => false]);
        $this->actingAs($this->buyer)->from(route('buyer.product', $this->product->slug))->post(route('buyer.cart.add', $this->product->slug), ['quantity' => 1])
            ->assertRedirect(route('buyer.shop'))->assertSessionHasErrors(['product' => 'This product is no longer available.']);
        $this->get(route('buyer.shop'))->assertOk()->assertSee('This product is no longer available.');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_existing_api_and_buy_again_quantity_contract_is_preserved(): void
    {
        $this->add(['quantity' => 2])->assertOk(); $row = CartItem::sole();
        try {
            DB::transaction(fn () => app(\App\Services\CheckoutService::class)->updateCartQuantity($row, 6));
            $this->fail('The existing shared writer must reject insufficient stock.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame(['Choose a quantity between 1 and the available stock (5).'], $error->errors()['quantity']);
        }
        $this->assertSame(2, $row->fresh()->quantity);
    }

    public function test_session_expiry_has_a_friendly_json_response(): void
    {
        $route = $this->app['router']->getRoutes()->getByName('buyer.cart.add');
        $route->middleware(ExpiredBuyerFeedbackSession::class);
        $this->actingAs($this->buyer)->postJson(route('buyer.cart.add', $this->product->slug), ['quantity' => 1])->assertStatus(419)
            ->assertJsonPath('message', 'Your session expired. Please refresh the page or log in again.')->assertJsonPath('code', 'session_expired');
    }

    public function test_unexpected_server_error_never_exposes_raw_details(): void
    {
        $armed = true;
        DB::listen(function ($query) use (&$armed) { if ($armed && DB::transactionLevel() > 0 && str_contains($query->sql, 'from "product_variants"')) { $armed = false; throw new \RuntimeException('Private database failure detail'); } });
        $this->add(['quantity' => 1])->assertStatus(500)->assertJsonPath('message', 'Something went wrong. Please try again.')->assertJsonPath('code', 'server_error')->assertDontSee('Private database failure detail');
        $this->assertDatabaseCount('cart_items', 0);
    }

}


class ExpiredBuyerFeedbackSession
{
    public function handle($request, $next) { throw new \Illuminate\Session\TokenMismatchException('Private expired token detail'); }
}
