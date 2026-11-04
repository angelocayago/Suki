<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, Order, OrderItem, Product, ProductImage, ProductVariant, Seller, SellerOrder, Shipment, User};
use App\Services\CheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyerBuyAgainTest extends TestCase
{
    private User $buyer;
    private Product $product;
    private ProductVariant $variant;
    private Order $previous;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated memory fixtures required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStringStartsWith(strtolower(str_replace('\\', '/', sys_get_temp_dir())).'/', strtolower(str_replace('\\', '/', storage_path())));
        // Disposable schema only: no application migrations, seeders, or shared storage.
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, first_name TEXT, last_name TEXT, middle_initial TEXT, email_verified_at TEXT, phone_verified_at TEXT, verification_required_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE sellers (id INTEGER PRIMARY KEY, user_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE return_refund_requests (id INTEGER PRIMARY KEY, order_item_id INTEGER, status TEXT)');
        DB::statement('CREATE TABLE wishlist_items (id INTEGER PRIMARY KEY, user_id INTEGER, product_slug TEXT)');
        DB::statement('CREATE TABLE transaction_reviews (id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT)');
        \Tests\Support\ShopVoucherSchema::create();
        foreach ([Order::class, OrderItem::class, SellerOrder::class, Shipment::class, CartItem::class, Address::class, Product::class, ProductVariant::class, ProductImage::class, Category::class] as $class) {
            $model = new $class(); $table = $model->getTable();
            DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
            foreach (array_unique([...$model->getFillable(), 'created_at', 'updated_at', ...(in_array($class, [Product::class, ProductVariant::class]) ? ['deleted_at'] : [])]) as $column) {
                $type = str_ends_with($column, '_id') || str_ends_with($column, '_minor') || in_array($column, ['quantity', 'selected', 'stock', 'is_active', 'is_default'], true) ? 'INTEGER' : 'TEXT';
                DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);
            }
        }
        DB::statement('CREATE UNIQUE INDEX cart_items_user_variant_unique ON cart_items(user_id, product_variant_id)');
        foreach ([1 => 'buyer', 2 => 'seller', 3 => 'buyer', 4 => 'rider'] as $id => $role) User::forceCreate(['id' => $id, 'name' => 'User '.$id, 'role' => $role, 'status' => 'active']);
        Seller::forceCreate(['id' => 10, 'user_id' => 2, 'name' => 'Current Shop', 'slug' => 'current-shop', 'status' => 'approved']);
        $category = Category::create(['name' => 'Everyday', 'slug' => 'everyday', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => 10, 'category_id' => $category->id, 'name' => 'Current Product', 'slug' => 'current-product', 'description' => 'Fixture', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $this->variant = $this->variant('Beef', 14900, 10);
        $this->previous = Order::create(['order_number' => 'OLD-ORDER', 'buyer_id' => 1, 'status' => 'COMPLETED', 'subtotal' => 2, 'shipping_fee' => 0, 'total_amount' => 2]);
        $this->item($this->variant);
        $this->buyer = User::findOrFail(1);
        Storage::fake('public');
        $this->assertStringStartsWith(strtolower(str_replace('\\', '/', sys_get_temp_dir())).'/', strtolower(str_replace('\\', '/', Storage::disk('public')->path(''))));
        $this->actingAs($this->buyer);
    }

    private function variant(string $flavor, int $price = 9900, int $stock = 10): ProductVariant
    {
        return $this->product->variants()->create(['sku' => 'SKU-'.$flavor, 'name' => $flavor, 'options' => ['Flavor' => $flavor, 'Pack Size' => 'Six'], 'price_minor' => $price, 'stock' => $stock, 'is_active' => true]);
    }

    private function item(ProductVariant $variant, int $quantity = 2): OrderItem
    {
        return $this->previous->items()->create(['seller_id' => 2, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
            'product_slug' => $this->product->slug, 'product_name' => 'Old Product', 'unit_price' => 1, 'unit_price_minor' => 100,
            'image' => 'old-image.png', 'variation' => $variant->options, 'quantity' => $quantity, 'line_total' => $quantity]);
    }

    private function cart(ProductVariant $variant, int $quantity): CartItem
    {
        return CartItem::create(['user_id' => 1, 'product_variant_id' => $variant->id, 'product_slug' => $this->product->slug,
            'product_name' => 'Old Cart name', 'price' => 1, 'quantity' => $quantity]);
    }

    private function reorder()
    {
        return $this->post(route('buyer.order.buy-again', $this->previous->order_number));
    }

    public function test_exact_custom_variant_uses_current_price_name_and_preserves_history_sessions_and_stock(): void
    {
        $before = DB::table('order_items')->get()->toJson();
        $orderBefore = $this->previous->fresh()->getRawOriginal();
        $states = ['fixture-token' => ['variant_id' => 123, 'quantity' => 5, 'voucher_codes' => ['DIRECT']]];
        $this->withSession(['buy_now_selections' => $states, 'checkout_voucher_codes' => ['CART']]);
        $this->reorder()->assertRedirect(route('buyer.cart'))->assertSessionHas('reorder_result.added_units', 2)
            ->assertSessionHas('buy_now_selections', $states)->assertSessionHas('checkout_voucher_codes', ['CART']);
        $row = CartItem::sole();
        $this->assertSame($this->variant->id, $row->product_variant_id);
        $this->assertSame('149.00', $row->price);
        $this->assertSame('Current Product', $row->product_name);
        $summary = app(CheckoutService::class)->cartSummary($this->buyer);
        $this->assertSame(['Flavor' => 'Beef', 'Pack Size' => 'Six'], $summary['cart'][$row->id]['variation']);
        $this->assertSame(14900, $summary['cart'][$row->id]['price_minor']);
        $this->assertSame($before, DB::table('order_items')->get()->toJson());
        $this->assertSame($orderBefore, $this->previous->fresh()->getRawOriginal());
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('voucher_redemptions', 0);
    }

    public function test_multiple_variants_merge_only_identical_rows_and_preserve_siblings(): void
    {
        $second = $this->variant('Chicken'); $third = $this->variant('Tuna');
        $same = $this->cart($this->variant, 3); $sibling = $this->cart($third, 4);
        $before = $sibling->fresh()->getRawOriginal();
        $this->item($second, 3);
        $this->reorder()->assertSessionHas('reorder_result.added_lines', 2)->assertSessionHas('reorder_result.added_units', 5);
        $this->assertSame(5, $same->fresh()->quantity);
        $this->assertSame($before, $sibling->fresh()->getRawOriginal());
        $this->assertSame(3, CartItem::where('product_variant_id', $second->id)->sole()->quantity);
        $this->assertDatabaseCount('cart_items', 3);
    }

    public function test_partial_capacity_is_explicit_and_full_cart_quantity_is_never_decreased(): void
    {
        $this->variant->update(['stock' => 4]); $row = $this->cart($this->variant, 3);
        $this->reorder()->assertSessionHas('reorder_result.items.0.status', 'adjusted')->assertSessionHas('reorder_result.added_units', 1);
        $this->assertSame(4, $row->fresh()->quantity);
        $this->reorder()->assertSessionHas('reorder_result.added_units', 0);
        $this->assertSame(4, $row->fresh()->quantity);
        $this->variant->update(['stock' => 1]);
        $this->reorder()->assertSessionHas('reorder_result.added_units', 0);
        $this->assertSame(4, $row->fresh()->quantity);
    }

    public static function unavailableCases(): array
    {
        return array_map(fn ($case) => [$case], ['missing-id', 'missing-product-id', 'missing-variant', 'archived-variant', 'inactive-variant', 'zero-stock',
            'changed-options', 'invalid-options', 'wrong-product', 'archived-product', 'deleted-product', 'inactive-product', 'unapproved-product', 'inactive-category', 'ineligible-seller', 'suspended-seller', 'invalid-quantity', 'invalid-price']);
    }

    #[DataProvider('unavailableCases')]
    public function test_unavailable_or_changed_identity_is_skipped_without_default_substitution(string $case): void
    {
        $this->variant('Default replacement');
        $item = $this->previous->items()->sole();
        match ($case) {
            'missing-id' => $item->update(['product_variant_id' => null]),
            'missing-product-id' => $item->update(['product_id' => null]),
            'missing-variant' => $this->variant->forceDelete(),
            'archived-variant' => $this->variant->delete(),
            'inactive-variant' => $this->variant->update(['is_active' => false]),
            'zero-stock' => $this->variant->update(['stock' => 0]),
            'changed-options' => $this->variant->update(['options' => ['Flavor' => 'Different', 'Pack Size' => 'Six']]),
            'invalid-options' => $item->update(['variation' => ['Flavor' => ['invalid']]]),
            'wrong-product' => $item->update(['product_id' => 999]),
            'archived-product' => $this->product->delete(),
            'deleted-product' => $this->product->forceDelete(),
            'inactive-product' => $this->product->update(['is_active' => false]),
            'unapproved-product' => $this->product->update(['moderation_status' => 'PENDING']),
            'inactive-category' => $this->product->category->update(['is_active' => false]),
            'ineligible-seller' => $this->product->seller->update(['status' => 'pending']),
            'suspended-seller' => User::findOrFail(2)->update(['is_suspended' => true]),
            'invalid-quantity' => $item->update(['quantity' => 0]),
            'invalid-price' => $this->variant->update(['price_minor' => -1]),
        };
        $this->reorder()->assertRedirect(route('buyer.cart'))->assertSessionHas('reorder_result.added_units', 0)->assertSessionMissing('success');
        $this->assertDatabaseCount('cart_items', 0);
        $this->get(route('buyer.cart'))->assertOk()->assertSee('No items were added to your Cart.')->assertDontSee('Items from your previous order have been added');
    }

    public function test_partial_success_feedback_escapes_names_and_links_only_public_products(): void
    {
        $unavailable = $this->variant('Unavailable'); $this->item($unavailable); $unavailable->delete();
        $this->product->update(['name' => '<script>alert(1)</script>']);
        $this->reorder()->assertSessionHas('reorder_result.added_lines', 1)->assertSessionHas('reorder_result.items.1.status', 'skipped');
        $this->get(route('buyer.cart'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('1 skipped')->assertSee('View product and select options')->assertSee(route('buyer.product', $this->product->slug), false);
        $this->product->update(['is_active' => false]);
        $this->reorder()->assertSessionHas('reorder_result', fn ($result) => $result['items'][0]['url'] === null && $result['items'][1]['url'] === null);
        $this->get(route('buyer.cart'))->assertDontSee('View product and select options');
    }

    public function test_database_write_error_rolls_back_all_additions_and_existing_updates(): void
    {
        $second = $this->variant('Second'); $this->item($second);
        $row = $this->cart($this->variant, 1); $before = $row->fresh()->getRawOriginal();
        DB::unprepared("CREATE TRIGGER fail_cart_insert BEFORE INSERT ON cart_items WHEN NEW.product_variant_id = {$second->id} BEGIN SELECT RAISE(ABORT, 'fixture write failure'); END");
        $this->reorder()->assertRedirect(route('buyer.cart'))->assertSessionHas('error')->assertSessionMissing('reorder_result');
        $this->assertSame($before, $row->fresh()->getRawOriginal());
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_ownership_status_and_canonical_middleware_are_enforced(): void
    {
        $this->actingAs(User::findOrFail(3))->reorder()->assertNotFound();
        $this->actingAs($this->buyer);
        foreach (['PLACED', 'CONFIRMED', 'DELIVERED'] as $status) {
            $this->previous->update(['status' => $status]); $this->reorder()->assertStatus(422);
        }
        foreach (['CANCELLED', 'RETURNED', 'COMPLETED'] as $status) {
            $this->previous->update(['status' => $status]); $this->reorder()->assertRedirect(route('buyer.cart'));
        }
        $route = app('router')->getRoutes()->getByName('buyer.order.buy-again');
        $this->assertSame(['POST'], $route->methods());
        foreach (['web', 'auth', 'not_suspended', 'approved', 'role:buyer'] as $middleware) $this->assertContains($middleware, $route->gatherMiddleware());
    }

    public function test_guest_other_roles_unverified_and_suspended_buyers_are_rejected(): void
    {
        $this->actingAs(User::findOrFail(4))->reorder()->assertForbidden();
        $this->buyer->update(['verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null]);
        $this->actingAs($this->buyer)->reorder()->assertForbidden();
        $this->buyer->update(['verification_required_at' => null, 'is_suspended' => true]);
        $this->actingAs($this->buyer)->reorder()->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->reorder()->assertRedirect(route('login'));
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_case_spacing_and_group_order_changes_do_not_change_combination_identity(): void
    {
        $this->variant->update(['options' => ['pack size' => ' six ', 'flavor' => 'BEEF']]);
        $this->reorder()->assertSessionHas('reorder_result.added_units', 2);
        $this->assertSame($this->variant->id, CartItem::sole()->product_variant_id);
    }

    public function test_selected_image_is_current_and_missing_variant_image_uses_valid_product_fallback(): void
    {
        Storage::disk('public')->put('variants/current.png', 'fixture image');
        Storage::disk('public')->put('products/current.png', 'fixture image');
        $this->variant->update(['image_path' => 'variants/current.png']);
        $this->product->images()->create(['path' => 'products/current.png', 'position' => 0]);
        $this->reorder();
        $row = CartItem::sole();
        $service = app(CheckoutService::class);
        $this->assertSame(Storage::disk('public')->url('variants/current.png'), $service->cartSummary($this->buyer)['cart'][$row->id]['image']);
        Storage::disk('public')->delete('variants/current.png');
        $this->assertSame(Storage::disk('public')->url('products/current.png'), $service->cartSummary($this->buyer)['cart'][$row->id]['image']);
        Storage::disk('public')->delete('products/current.png');
        $this->assertNull($service->cartSummary($this->buyer)['cart'][$row->id]['image']);
    }

    public function test_order_history_labels_and_existing_buy_again_form_are_preserved(): void
    {
        $this->get(route('buyer.my-orders'))->assertOk()->assertSee('Completed');
        $this->previous->update(['status' => 'RETURNED']);
        $this->get(route('buyer.my-orders'))->assertOk()->assertSee('Returned');
        $this->get(route('buyer.order-details', $this->previous->order_number))->assertOk()->assertSee('Buy Again')
            ->assertSee(route('buyer.order.buy-again', $this->previous->order_number), false)->assertSee('name="_token"', false);
    }

    public function test_unique_insert_conflict_retries_the_whole_reorder_without_double_incrementing(): void
    {
        $second = $this->variant('Second'); $this->item($second);
        $row = $this->cart($this->variant, 1);
        $attempts = 0;
        $event = 'eloquent.creating: '.CartItem::class;
        \Illuminate\Support\Facades\Event::listen($event, function (CartItem $item) use ($second, &$attempts) {
            if ($item->product_variant_id === $second->id && ++$attempts === 1) {
                throw new \Illuminate\Database\UniqueConstraintViolationException('sqlite', 'insert into cart_items', [], new \PDOException('fixture insert conflict'));
            }
        });
        try {
            $this->reorder()->assertSessionHas('reorder_result.added_units', 4);
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertSame(2, $attempts);
        $this->assertSame(3, $row->fresh()->quantity);
        $this->assertSame(2, CartItem::where('product_variant_id', $second->id)->sole()->quantity);
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_multiple_products_checkout_uses_current_prices_exact_variants_and_canonical_fulfillment(): void
    {
        $other = $this->product->replicate();
        $other->fill(['name' => 'Other Product', 'slug' => 'other-product'])->save();
        $variant = $other->variants()->create(['sku' => 'OTHER', 'name' => 'Extended', 'options' => ['Storage' => '256 GB', 'Capacity' => 'Extended'], 'price_minor' => 7500, 'stock' => 5, 'is_active' => true]);
        $this->item($variant, 1)->update(['product_slug' => $other->slug]);
        $this->reorder()->assertSessionHas('reorder_result.added_lines', 2);
        $this->variant->update(['price_minor' => 15900]);
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit', 'adminsAfterCommit')->andReturnNull();
        $order = app(CheckoutService::class)->checkout($this->buyer, ['recipient_name' => 'Buyer', 'recipient_phone' => '09123456789',
            'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street'], 'cod');
        $this->assertSame(44300, $order->total_minor);
        $this->assertSame([$this->variant->id, $variant->id], $order->items->pluck('product_variant_id')->all());
        $this->assertSame([15900, 7500], $order->items->pluck('unit_price_minor')->all());
        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(1, $order->sellerOrders()->count());
        $this->assertDatabaseCount('shipments', 1);
        $this->assertSame('COMPLETED', $this->previous->fresh()->status);
    }
}
