<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerOrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('public');
        User::factory()->create();
        $this->shop = $this->shop();
        $this->actingAs($this->shop->owner);
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(4, 0));
    }

    private function shop(): Seller
    {
        return Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Details shop', 'slug' => 'details-'.uniqid(), 'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function order(array $data = []): Order
    {
        return Order::create($data + ['seller_id' => $this->shop->user_id, 'buyer_id' => User::factory()->create()->id,
            'order_number' => 'DETAIL-'.++$this->sequence, 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Saved recipient', 'recipient_phone' => 'PHONE-SECRET', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test',
            'street_address' => 'ADDRESS-SECRET', 'delivery_notes' => 'INSTRUCTION-SECRET', 'total_amount' => 987654.32, 'shipping_fee' => 777]);
    }

    private function item(Order $order, array $data = []): OrderItem
    {
        return $order->items()->create($data + ['seller_id' => $this->shop->user_id, 'product_slug' => 'same-slug', 'product_name' => 'Saved purchased item',
            'unit_price' => 25.50, 'quantity' => 2, 'line_total' => 51]);
    }

    private function group(Order $order, ?Seller $shop = null)
    {
        return $order->sellerOrders()->create(['seller_id' => ($shop ?? $this->shop)->id, 'status' => 'completed', 'subtotal_minor' => 999999]);
    }

    private function url(Order $order, array $parameters = []): string
    {
        return route('seller.orders.show', ['order' => $order->id] + $parameters);
    }

    public function test_owned_normalized_details_minimize_data_and_isolate_shared_parent_items_and_amounts(): void
    {
        $foreign = $this->shop();
        $order = $this->order(['seller_id' => null, 'reference' => 'SAVED-REFERENCE', 'status' => 'CANCELLED']);
        $own = $this->group($order);
        $other = $this->group($order, $foreign);
        $line = $this->item($order, ['seller_order_id' => $own->id, 'unit_price_minor' => 1234, 'unit_price' => 999, 'line_total' => 24.68, 'variant_name' => 'Saved Green', 'variation' => ['Color' => 'Green']]);
        $this->item($order, ['seller_order_id' => $other->id, 'product_name' => 'FOREIGN-ITEM-SECRET', 'unit_price' => 4567.89]);
        $this->get($this->url($order))->assertOk()->assertViewIs('seller.order-details')->assertSee('SAVED-REFERENCE')->assertSee('Saved Green')->assertSee('Color: Green')
            ->assertSee('₱24.68')->assertSee('₱12.34')->assertSee('UNPAID')->assertSee('Parent order status: CANCELLED')->assertSee('Source: Seller order')->assertSee('Saved recipient')
            ->assertDontSee('FOREIGN-ITEM-SECRET')->assertDontSee('4,567.89')->assertDontSee('987,654.32')->assertDontSee('PHONE-SECRET')->assertDontSee('ADDRESS-SECRET')->assertDontSee('INSTRUCTION-SECRET')
            ->assertViewHas('segment', fn ($s) => $s->gross_amount == 24.68 && $s->units === 2 && $s->seller_order_id === $own->id)
            ->assertViewHas('purchasedItems', fn ($items) => $items->pluck('id')->all() === [$line->id]);
        $this->actingAs($foreign->owner)->get($this->url($order))->assertOk()->assertSee('FOREIGN-ITEM-SECRET')->assertDontSee('Saved Green')->assertDontSee('₱24.68');
    }

    public function test_legacy_snapshots_decimal_prices_and_unknown_status_are_displayed_without_current_catalogue(): void
    {
        $order = $this->order(['status' => 'CUSTOM_SAVED_STATE', 'payment_method' => null, 'payment_status' => '']);
        $this->item($order);
        $this->get($this->url($order))->assertOk()->assertSee('DETAIL-1')->assertSee('₱51.00')->assertSee('₱25.50')->assertSee('Custom Saved State')
            ->assertSee('Source: Parent order')->assertSee('Not recorded')->assertSee('SKU at purchase: Not recorded')->assertSee('Image unavailable')
            ->assertSee('Oct 23, 2026')->assertSee('12:00 PM')->assertSee('PHT');
    }

    public function test_foreign_normalized_and_legacy_orders_return_the_same_404_as_missing_records(): void
    {
        $foreign = $this->shop();
        $legacy = $this->order(['seller_id' => $foreign->user_id]);
        $this->item($legacy, ['seller_id' => $foreign->user_id]);
        $normalized = $this->order(['seller_id' => null]);
        $group = $this->group($normalized, $foreign);
        $this->item($normalized, ['seller_order_id' => $group->id]);
        foreach ([$legacy->id, $normalized->id, 999999] as $id) {
            $this->get(route('seller.orders.show', ['order' => $id, 'seller_id' => $foreign->id]))->assertNotFound()->assertDontSee('Saved purchased item');
        }
    }

    public function test_null_owners_inconsistent_parents_and_partial_normalization_do_not_fall_back(): void
    {
        $null = $this->order(['seller_id' => null]);
        $this->item($null, ['seller_id' => null]);
        $base = $this->order();
        $group = $this->group($base);
        $wrong = $this->order();
        $this->item($wrong, ['seller_order_id' => $group->id]);
        $partial = $this->order();
        $this->group($partial, $this->shop());
        $this->item($partial);
        $mismatch = $this->order(['seller_id' => $this->shop()->user_id]);
        $this->item($mismatch);
        foreach ([$null, $base, $wrong, $partial, $mismatch] as $order) {
            $this->get($this->url($order))->assertNotFound();
        }
    }

    public function test_normalized_precedence_keeps_only_authorized_lines_and_reconciles_existing_readers(): void
    {
        $order = $this->order();
        $this->item($order, ['product_name' => 'LEFTOVER-SECRET']);
        $group = $this->group($order);
        $this->item($order, ['seller_order_id' => $group->id, 'unit_price_minor' => 1500, 'line_total' => 30]);
        $this->get($this->url($order))->assertOk()->assertDontSee('LEFTOVER-SECRET')->assertViewHas('segment', fn ($s) => $s->gross_amount == 30 && $s->item_count === 1);
        $this->get(route('seller.orders'))->assertOk()->assertViewHas('summary', fn ($s) => $s->gross == 30);
        $this->get(route('seller.sales'))->assertOk()->assertViewHas('grossCompleted', 30.0);
        $this->get(route('seller.reports'))->assertOk()->assertViewHas('grossSales', 30.0);
    }

    public function test_edited_inactive_deleted_catalogue_does_not_replace_saved_values_or_merge_slugs(): void
    {
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'CURRENT-NAME-SECRET', 'slug' => 'current-slug', 'is_active' => false]);
        $variant = $product->variants()->create(['name' => 'Current variant', 'sku' => 'CURRENT-SKU-SECRET', 'price_minor' => 999999, 'stock' => 10, 'is_active' => false]);
        $order = $this->order();
        $a = $this->item($order, ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => 'Snapshot A']);
        $b = $this->item($order, ['product_name' => 'Snapshot B', 'unit_price_minor' => 0, 'line_total' => 0]);
        $variant->delete();
        $product->delete();
        $this->get($this->url($order))->assertOk()->assertSee('Snapshot A')->assertSee('Snapshot B')->assertSee('₱0.00')->assertDontSee('CURRENT-NAME-SECRET')->assertDontSee('CURRENT-SKU-SECRET')
            ->assertViewHas('segment', fn ($s) => $s->item_count === 2 && $s->gross_amount == 51)
            ->assertViewHas('purchasedItems', fn ($items) => $items->pluck('id')->all() === [$a->id, $b->id]);
    }

    public static function unsafeImages(): array
    {
        return [[null], ['products/missing.png'], ['../../.env'], ['products/%2e%2e/private.png'], ['C:\\private\\file.png'], ['javascript:alert(1)'], ['https://outside.example/private.png']];
    }

    #[DataProvider('unsafeImages')]
    public function test_missing_and_unsafe_images_use_neutral_fallback(?string $image): void
    {
        $order = $this->order();
        $this->item($order, compact('image'));
        $this->get($this->url($order))->assertOk()->assertSee('Image unavailable')->assertViewHas('purchasedItems', fn ($items) => $items->first()->image_url === null);
    }

    public function test_existing_saved_product_image_uses_local_public_storage_url(): void
    {
        Storage::disk('public')->put('products/saved.png', file_get_contents(public_path('images/logo.png')));
        $order = $this->order();
        $this->item($order, ['image' => 'https://old-host.example/storage/products/saved.png']);
        $this->get($this->url($order))->assertOk()->assertSee(asset('storage/products/saved.png'), false)->assertDontSee('old-host.example');
    }

    public static function invalidIdentifiers(): array
    {
        return [['0'], ['-1'], ['abc'], ['1.5'], ['01'], ['9223372036854775808'], ['999999999999999999999999']];
    }

    #[DataProvider('invalidIdentifiers')]
    public function test_invalid_identifiers_return_404(string $identifier): void
    {
        $this->item($this->order());
        $this->get(route('seller.orders.show', ['order' => $identifier]))->assertNotFound();
    }

    public function test_listing_link_preserves_validated_filters_and_page_and_keeps_preview(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->item($this->order());
        }
        $query = ['search' => 'Saved', 'order_status' => 'placed', 'payment_method' => 'cod', 'sort' => 'oldest', 'date_range' => 'today', 'page' => 2];
        $listing = $this->get(route('seller.orders', $query))->assertOk()->assertSee('View Details')->assertSee('Read-only seller order preview');
        $dom = new \DOMDocument;
        @$dom->loadHTML($listing->getContent());
        $links = (new \DOMXPath($dom))->query('//a[contains(@href,"/seller/orders/") and contains(.,"View Details")]');
        $this->assertSame(1, $links->length);
        $this->get($links->item(0)->getAttribute('href'))->assertOk()->assertViewHas('backUrl', route('seller.orders', $query))->assertSee('Back to Orders')->assertSee('Breadcrumb');
    }

    public function test_return_navigation_never_uses_external_urls_or_unvalidated_nested_values(): void
    {
        $order = $this->order();
        $this->item($order);
        foreach ([['return_url' => 'https://evil.example'], ['list' => '//evil.example'], ['list' => ['sort' => ['bad']]], ['list' => ['date_range' => 'custom', 'from' => '2026-10-24', 'to' => '2026-10-23']]] as $parameters) {
            $this->get($this->url($order, $parameters))->assertOk()->assertViewHas('backUrl', route('seller.orders'))->assertDontSee('evil.example');
        }
        $this->get($this->url($order, ['list' => ['sort' => 'oldest', 'return_url' => 'https://evil.example', 'seller_id' => 999]]))->assertOk()
            ->assertViewHas('backUrl', route('seller.orders', ['sort' => 'oldest']))->assertDontSee('evil.example');
    }

    public function test_snapshot_text_is_escaped_and_recorded_line_discrepancies_are_not_repaired(): void
    {
        $order = $this->order(['reference' => '<script>reference</script>']);
        $line = $this->item($order, ['product_name' => '<script>item</script>', 'variation' => ['Color' => '<img onerror=alert(1)>'], 'unit_price_minor' => 1000, 'line_total' => 99]);
        $this->get($this->url($order))->assertOk()->assertDontSee('<script>reference</script>', false)->assertDontSee('<script>item</script>', false)->assertDontSee('<img onerror', false)->assertSee('&lt;script&gt;', false)
            ->assertSee('Recorded line total: ₱99.00')->assertSee('₱20.00');
        $this->assertSame('99.00', $line->fresh()->line_total);
    }

    public function test_guests_session_flags_and_other_roles_cannot_open_details(): void
    {
        $order = $this->order();
        $this->item($order);
        auth()->logout();
        $this->get($this->url($order))->assertRedirect(route('seller.register'));
        $this->withSession(['seller_logged_in' => true])->get($this->url($order))->assertForbidden();
        foreach (['buyer', 'admin', 'rider', 'logistics'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'status' => 'active']))->get($this->url($order))->assertForbidden();
        }
    }

    public function test_pending_is_local_only_and_ineligible_or_ambiguous_shops_are_denied(): void
    {
        $order = $this->order();
        $this->item($order);
        $this->shop->update(['status' => 'pending']);
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->app->detectEnvironment(fn () => $environment);
            $this->get($this->url($order))->assertForbidden();
        }
        $this->app->detectEnvironment(fn () => 'local');
        $this->get($this->url($order))->assertOk();
        $this->assertSame('pending', $this->shop->fresh()->status);
        foreach (['rejected', 'suspended'] as $status) {
            $this->shop->update(compact('status'));
            $this->get($this->url($order))->assertForbidden();
        }
        $this->shop->update(['status' => 'approved']);
        $user = $this->shop->owner;
        $user->update(['status' => 'inactive']);
        $this->get($this->url($order))->assertForbidden();
        $user->update(['status' => 'active', 'is_suspended' => true]);
        $this->get($this->url($order))->assertForbidden();
        $user->update(['is_suspended' => false]);
        Seller::create(['user_id' => $user->id, 'name' => 'Second', 'slug' => 'second', 'status' => 'approved']);
        $this->get($this->url($order))->assertStatus(409);
    }

    public function test_details_is_read_only_even_with_existing_stock_payment_shipment_and_events(): void
    {
        $order = $this->order();
        $group = $this->group($order);
        $this->item($order, ['seller_order_id' => $group->id]);
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Stock', 'slug' => 'stock', 'is_active' => true]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'DETAIL-READONLY', 'price_minor' => 100, 'stock' => 10, 'is_active' => true]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 5100, 'status' => 'pending', 'provider_ref' => 'PROVIDER-SECRET']);
        $provider = DB::table('logistics_providers')->insertGetId(['user_id' => $this->shop->user_id, 'name' => 'Fixture provider', 'slug' => 'details-provider', 'status' => 'approved']);
        $shipment = DB::table('shipments')->insertGetId(['seller_order_id' => $group->id, 'logistics_provider_id' => $provider, 'tracking_code' => 'TRACK-SECRET', 'status' => 'assigned', 'fee_minor' => 100, 'cod_amount_minor' => 5100, 'cod_collected' => false, 'attempts' => 0]);
        DB::table('delivery_events')->insert(['shipment_id' => $shipment, 'status' => 'assigned', 'attempt' => 0, 'user_id' => $this->shop->user_id, 'occurred_at' => now()]);
        $tables = ['orders', 'order_items', 'seller_orders', 'products', 'product_variants', 'payments', 'shipments', 'delivery_events', 'sellers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $response = $this->get($this->url($order))->assertOk()->assertDontSee('PROVIDER-SECRET')->assertDontSee('TRACK-SECRET');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//main//form')->length);
        $this->assertSame($before, $snapshot());
        $this->post($this->url($order))->assertStatus(405);
    }
}
