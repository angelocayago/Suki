<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCatalogueFilter;
use App\Support\SellerCategories;
use App\Support\SellerDateFilter;
use App\Support\SellerProducts;
use App\Support\SellerReports;
use App\Support\SellerStock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerStockFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private int $category;

    protected function setUp(): void
    {
        parent::setUp();
        SellerCategories::populate();
        $user = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $user->id, 'name' => 'Stock shop', 'slug' => 'stock-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->category = SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id;
        $this->actingAs($user);
        CarbonImmutable::setTestNow('2026-01-01 04:00:00 Asia/Manila');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function product(int $stock, array $details = [], ?Seller $seller = null): Product
    {
        $p = Product::create(['seller_id' => ($seller ?? $this->shop)->id, 'category_id' => $this->category, 'name' => 'Pet shirt '.$stock, 'slug' => 'shirt-'.uniqid(), 'is_active' => true, 'seller_listing_data' => $details]);
        $p->variants()->create(['name' => 'Test variant', 'sku' => 'SKU-'.uniqid(), 'price_minor' => 12050, 'stock' => $stock, 'is_active' => true]);

        return $p;
    }

    public static function boundaries(): array
    {
        return [[0, 'out-of-stock'], [1, 'low-stock'], [9, 'low-stock'], [10, 'low-stock'], [11, 'in-stock'], [20, 'in-stock']];
    }

    #[DataProvider('boundaries')]
    public function test_boundaries_agree_in_listing_query_and_both_pages(int $quantity, string $status): void
    {
        $p = $this->product($quantity);
        $this->assertSame($status, SellerProducts::listing($p)['stock_status']);
        foreach (['seller.products', 'seller.inventory'] as $route) {
            $this->get(route($route, ['stock_status' => $status]))->assertOk()->assertViewHas('paginator', fn ($page) => $page->total() === 1)->assertSee('Apply Filters')->assertSee('Reset Filters');
            $other = $status === 'low-stock' ? 'out-of-stock' : 'low-stock';
            $this->get(route($route, ['stock_status' => $other]))->assertOk()->assertViewHas('paginator', fn ($page) => $page->total() === 0);
        }
    }

    public function test_custom_threshold_and_inactive_variant_aggregation(): void
    {
        $p = $this->product(7, ['low_stock_threshold' => 5, 'product_type' => 'variable']);
        $p->variants()->create(['name' => 'Test variant', 'sku' => 'LOW', 'stock' => 3, 'price_minor' => 10000, 'is_active' => true]);
        $p->variants()->create(['name' => 'Test variant', 'sku' => 'HIGH', 'stock' => 50, 'price_minor' => 15000, 'is_active' => true]);
        $p->variants()->create(['name' => 'Test variant', 'sku' => 'DISABLED', 'stock' => 999, 'price_minor' => 1, 'is_active' => false]);
        $row = SellerProducts::listing($p->fresh());
        $this->assertSame(60, $row['stock']);
        $this->assertSame('in-stock', $row['stock_status']);
        $this->assertSame(1, $row['low_stock_variants']);
        $this->assertEquals(100, $row['price']);
        $this->get(route('seller.inventory'))->assertOk()->assertSee('LOW')->assertSee('Inactive (excluded)');
        $p->variants()->where('is_active', true)->update(['stock' => 0]);
        $this->assertSame('out-of-stock', SellerProducts::listing($p->fresh())['stock_status']);
        $this->assertSame(0, SellerProducts::listing($p->fresh())['stock']);
    }

    public function test_new_default_and_saved_custom_thresholds_are_preserved(): void
    {
        $this->post(route('seller.products.store'), ['submit_action' => 'draft', 'name' => 'New draft', 'stock' => 10])->assertSessionHasNoErrors();
        $this->assertSame(10, Product::first()->seller_listing_data['low_stock_threshold']);
        $p = $this->product(6, ['low_stock_threshold' => 5]);
        $this->assertSame('in-stock', SellerProducts::listing($p)['stock_status']);
        $this->assertSame([$p->id], SellerCatalogueFilter::query($this->shop, new Request(['stock_status' => 'in-stock']))->pluck('id')->all());
        $this->assertSame('in-stock', SellerStock::status(1, 0));
        $this->assertSame('out-of-stock', SellerStock::status(0, 100));
        $this->assertSame(5, $p->fresh()->seller_listing_data['low_stock_threshold']);
    }

    public function test_inactive_simple_product_edits_show_stored_quantity_separately(): void
    {
        $p = $this->product(25);
        $p->update(['is_active' => false]);
        $p->variants()->update(['is_active' => false]);
        $row = SellerProducts::listing($p->fresh());
        $this->assertSame(0, $row['stock']);
        $this->assertSame(25, $row['editable_stock']);
        $this->assertSame(120.50, $row['editable_price']);
        $this->get(route('seller.products.edit', $p->id))->assertOk()->assertViewHas('product', fn ($row) => $row['editable_stock'] === 25);
        $this->put(route('seller.products.update', $p->id), ['name' => $p->name, 'description' => 'Details', 'stock' => 25, 'price' => 120.50, 'status' => 'inactive', 'subcategory' => $this->category])->assertSessionHasNoErrors();
        $this->assertSame(25, $p->variants->first()->fresh()->stock);
    }

    public function test_combined_filters_sorting_and_authoritative_prices(): void
    {
        $a = $this->product(10, ['product_type' => 'variable', 'price' => 999999, 'stock' => 999999]);
        $a->variants()->create(['name' => 'Test variant', 'sku' => 'SEARCH-SKU', 'options' => ['Size' => 'Tiny'], 'price_minor' => 2500, 'stock' => 0, 'is_active' => true]);
        $this->product(20);
        $filters = ['search' => 'Tiny', 'stock_status' => 'low-stock', 'subcategory' => $this->category, 'category' => SellerCategories::main($this->shop)->id, 'status' => 'active', 'min_stock' => 1, 'max_stock' => 10, 'min_price' => 24, 'max_price' => 26, 'product_type' => 'variable', 'sort' => 'stock_asc'];
        $this->assertSame([$a->id], SellerCatalogueFilter::query($this->shop, new Request($filters))->pluck('id')->all());
        foreach (['seller.products', 'seller.inventory'] as $route) {
            $this->get(route($route, $filters))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
            $this->get(route($route))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 2);
        }
        $this->assertSame([$a->id], SellerCatalogueFilter::query($this->shop, new Request(['search' => 'SEARCH-SKU']))->pluck('id')->all());
        $this->assertSame([10, 20], SellerCatalogueFilter::query($this->shop, new Request(['sort' => 'stock_asc']))->pluck('sellable_stock')->all());
        $this->assertSame([20, 10], SellerCatalogueFilter::query($this->shop, new Request(['sort' => 'stock_desc']))->pluck('sellable_stock')->all());
    }

    public function test_stock_updates_preserve_identity_and_seller_isolation(): void
    {
        $p = $this->product(10);
        $v = $p->variants->first();
        $this->post(route('seller.inventory.increase', $p->id))->assertRedirect();
        $this->assertSame(11, $v->fresh()->stock);
        $this->assertSame($v->sku, $v->fresh()->sku);
        $this->get(route('seller.products', ['stock_status' => 'in-stock']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
        $other = Seller::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'slug' => 'foreign', 'status' => 'approved']);
        $foreign = $this->product(2, [], $other);
        $this->post(route('seller.inventory.increase', $foreign->id))->assertNotFound();
        $this->get(route('seller.inventory', ['search' => $foreign->variants->first()->sku]))->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        $this->assertSame(1, SellerCatalogueFilter::query($this->shop, new Request(['seller_id' => $other->id]))->count());
    }

    public function test_variant_stock_endpoint_preserves_siblings_skus_and_order_references(): void
    {
        $p = $this->product(10, ['product_type' => 'variable']);
        $variant = $p->variants->first();
        $sibling = $p->variants()->create(['name' => 'Large', 'sku' => 'LARGE-STABLE', 'stock' => 50, 'price_minor' => 15000, 'is_active' => true, 'options' => ['Size' => 'Large']]);
        $order = $this->sale($p, 'PLACED', '2026-01-01 10:00:00');
        $order->items()->update(['product_variant_id' => $variant->id]);
        $this->post(route('seller.inventory.increase', $p->id), ['variant_id' => $variant->id])->assertSessionHasNoErrors();
        $this->assertSame(11, $variant->fresh()->stock);
        $this->assertSame(50, $sibling->fresh()->stock);
        $this->assertSame($variant->sku, $variant->fresh()->sku);
        $this->assertSame($variant->id, $order->items->first()->product_variant_id);
        $this->assertSame(61, SellerProducts::listing($p->fresh())['stock']);
        $foreignVariant = $this->product(20)->variants->first();
        $this->post(route('seller.inventory.increase', $p->id), ['variant_id' => $foreignVariant->id])->assertNotFound();
        $this->post(route('seller.inventory.increase', $p->id), ['variant_id' => '1.5'])->assertSessionHasErrors('variant_id');
        $this->assertSame(20, $foreignVariant->fresh()->stock);
    }

    public function test_zero_stock_cannot_be_decremented_below_zero(): void
    {
        $p = $this->product(0);
        $this->post(route('seller.inventory.decrease', $p->id))->assertSessionHasErrors('stock');
        $this->assertSame(0, $p->variants->first()->fresh()->stock);
    }

    public function test_pagination_preserves_filters_and_summary_covers_all_matches(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->product(10);
        }
        $this->get(route('seller.inventory', ['stock_status' => 'low-stock', 'sort' => 'updated']))->assertOk()
            ->assertViewHas('paginator', fn ($p) => $p->total() === 17 && $p->count() === 15 && str_contains($p->url(2), 'stock_status=low-stock'))
            ->assertViewHas('filteredSummary', fn ($s) => $s->count() === 17);
    }

    public static function presets(): array
    {
        return [['today', '2025-12-31 16:00:00', '2026-01-01 16:00:00'], ['yesterday', '2025-12-30 16:00:00', '2025-12-31 16:00:00'],
            ['last7', '2025-12-25 16:00:00', '2026-01-01 16:00:00'], ['last30', '2025-12-02 16:00:00', '2026-01-01 16:00:00'],
            ['week', '2025-12-28 16:00:00', '2026-01-04 16:00:00'], ['last_week', '2025-12-21 16:00:00', '2025-12-28 16:00:00'],
            ['month', '2025-12-31 16:00:00', '2026-01-31 16:00:00'], ['last_month', '2025-11-30 16:00:00', '2025-12-31 16:00:00'],
            ['custom', '2025-12-31 16:00:00', '2026-01-02 16:00:00']];
    }

    #[DataProvider('presets')]
    public function test_manila_date_presets_and_calendar_boundaries(string $preset, string $start, string $end): void
    {
        $this->assertSame([$start, $end], SellerDateFilter::bounds(new Request(['date_range' => $preset, 'from' => '2026-01-01', 'to' => '2026-01-02'])));
        $this->assertNull(SellerDateFilter::bounds(new Request(['date_range' => 'all'])));
        foreach ([$start, CarbonImmutable::parse($end)->subSecond()->toDateTimeString(), $end, CarbonImmutable::parse($start)->subSecond()->toDateTimeString()] as $date) {
            $p = $this->product(10);
            $p->timestamps = false;
            $p->forceFill(['created_at' => $date])->save();
        }
        $this->assertSame(2, SellerCatalogueFilter::query($this->shop, new Request(['date_range' => $preset, 'from' => '2026-01-01', 'to' => '2026-01-02', 'stock_status' => 'low-stock']))->count());
    }

    public function test_combined_date_stock_category_and_inclusive_end_date(): void
    {
        $p = $this->product(10);
        $p->timestamps = false;
        $p->forceFill(['created_at' => '2026-01-01 15:59:59', 'updated_at' => '2026-01-02 16:00:00'])->save();
        $q = ['date_range' => 'today', 'stock_status' => 'low-stock', 'subcategory' => $this->category];
        foreach (['seller.products', 'seller.inventory'] as $route) {
            $this->get(route($route, $q))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
            $this->get(route($route, $q + ['date_field' => 'updated_at']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        }
        $p->forceFill(['created_at' => '2026-01-01 16:00:00'])->save();
        $this->get(route('seller.inventory', $q))->assertViewHas('paginator', fn ($p) => $p->total() === 0);
    }

    public static function invalidFilters(): array
    {
        return [[['date_range' => 'invalid'], 'date_range'], [['date_range' => 'custom', 'from' => '2026-02-30', 'to' => '2026-03-01'], 'from'],
            [['date_range' => 'custom', 'from' => '2026-01-02', 'to' => '2026-01-01'], 'to'], [['sort' => ['bad']], 'sort'],
            [['stock_status' => 'invalid'], 'stock_status'], [['min_stock' => 5, 'max_stock' => 2], 'max_stock'], [['min_price' => 20, 'max_price' => 1], 'max_price']];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_validation_errors(array $filters, string $field): void
    {
        $this->getJson(route('seller.inventory', $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    private function sale(Product $p, string $status, string $date, int $qty = 2): Order
    {
        $o = Order::create(['seller_id' => $this->shop->user_id, 'buyer_id' => User::factory()->create()->id, 'order_number' => 'ORDER-'.uniqid(), 'status' => $status, 'payment_status' => 'UNPAID', 'payment_method' => 'cod', 'recipient_name' => 'Buyer', 'recipient_phone' => '09000000000', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test']);
        $o->timestamps = false;
        $o->forceFill(['created_at' => $date])->save();
        $o->items()->create(['seller_id' => $this->shop->user_id, 'product_id' => $p->id, 'product_slug' => $p->slug, 'product_name' => $p->name, 'unit_price' => 12.50, 'quantity' => $qty, 'line_total' => 12.50 * $qty]);

        return $o;
    }

    public function test_report_filters_totals_charts_and_items_share_saved_data(): void
    {
        $p = $this->product(10);
        $this->sale($p, 'COMPLETED', '2026-01-01 15:59:59');
        $this->sale($p, 'PLACED', '2026-01-01 10:00:00');
        $this->sale($p, 'COMPLETED', '2026-01-01 16:00:00');
        $other = $this->product(20);
        $this->sale($other, 'COMPLETED', '2026-01-01 11:00:00');
        $filters = ['date_range' => 'today', 'product_id' => $p->id, 'subcategory' => $this->category, 'category' => SellerCategories::main($this->shop)->id, 'order_status' => 'completed', 'payment_method' => 'cod'];
        $data = SellerReports::data($this->shop, new Request($filters));
        $this->assertSame(1, $data['totalOrders']);
        $this->assertSame(1, $data['completedOrders']);
        $this->assertEquals(25, $data['grossSales']);
        $this->assertEquals(2, $data['unitsSold']);
        $this->assertEquals(25, $data['chart']->sum('gross'));
        $this->assertEquals(25, $data['breakdown']->sum('gross'));
        $this->assertSame(1, $data['paginator']->total());
        $this->withSession(['seller_orders' => [['total_amount' => 999999]]])->get(route('seller.reports', $filters))->assertOk()->assertSee('₱25.00')->assertDontSee('999,999')->assertSee('do not imply payment');
        $this->getJson(route('seller.reports', ['date_range' => 'custom', 'from' => 'bad', 'to' => 'bad']))->assertUnprocessable();
    }

    public function test_reports_isolate_other_seller_and_untrusted_session_rows(): void
    {
        $other = Seller::create(['user_id' => User::factory()->create()->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved']);
        $p = $this->product(1, [], $other);
        $o = $this->sale($p, 'COMPLETED', '2026-01-01 10:00:00');
        $o->update(['seller_id' => $other->user_id]);
        $o->items()->update(['seller_id' => $other->user_id]);
        $this->withSession(['seller_orders' => [['status' => 'completed', 'total_amount' => 999999]]])->get(route('seller.reports'))->assertOk()->assertViewHas('totalOrders', 0)->assertViewHas('grossSales', 0);
        $this->getJson(route('seller.reports', ['product_id' => $p->id]))->assertUnprocessable();
    }

    public function test_normalized_seller_orders_use_shop_identity_and_minor_prices(): void
    {
        $p = $this->product(10);
        $o = $this->sale($p, 'PLACED', '2026-01-01 10:00:00');
        $o->update(['seller_id' => null]);
        $sellerOrder = $o->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 5000]);
        $o->items()->update(['seller_order_id' => $sellerOrder->id, 'seller_id' => null, 'unit_price_minor' => 2500]);
        $data = SellerReports::data($this->shop, new Request(['date_range' => 'today', 'order_status' => 'completed', 'payment_method' => 'cod']));
        $this->assertSame(1, $data['completedOrders']);
        $this->assertEquals(50, $data['grossSales']);
        $this->assertEquals(50, $data['chart']->sum('gross'));
        $other = Seller::create(['user_id' => User::factory()->create()->id, 'name' => 'Another', 'slug' => 'another', 'status' => 'approved']);
        $sellerOrder->update(['seller_id' => $other->id]);
        $this->assertSame(0, SellerReports::data($this->shop, new Request([]))['totalOrders']);
    }
}
