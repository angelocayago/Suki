<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerDateFilter;
use App\Support\SellerSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerUnifiedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        SellerCategories::populate();
        $user = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $user->id, 'name' => 'Unified filter fixture', 'slug' => 'unified-filters', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($user);
        $this->product = $this->product('Filter product');
    }

    private function product(string $name): Product
    {
        $p = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'name' => $name, 'slug' => 'filter-product-'.uniqid(), 'is_active' => true]);
        $p->variants()->create(['name' => 'Default', 'sku' => 'UNIFIED-'.uniqid(), 'price_minor' => 10000, 'stock' => 10, 'is_active' => true]);

        return $p;
    }

    private function order(string $status = 'COMPLETED', int $qty = 2, float $price = 25, ?Product $p = null, string $created = '2026-10-23 03:00:00'): Order
    {
        $p ??= $this->product;
        $order = Order::create(['seller_id' => $this->shop->user_id, 'buyer_id' => User::factory()->create()->id,
            'order_number' => 'UF-'.uniqid(), 'status' => $status, 'payment_status' => 'UNPAID', 'payment_method' => 'cod',
            'recipient_name' => 'Test buyer', 'recipient_phone' => '09000000000', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test',
            'shipping_fee' => 999, 'total_amount' => 99999]);
        $order->timestamps = false;
        $order->forceFill(['created_at' => $created])->save();
        $order->items()->create(['seller_id' => $this->shop->user_id, 'product_slug' => $p->slug, 'product_id' => $p->id, 'product_name' => $p->name, 'unit_price' => $price, 'quantity' => $qty, 'line_total' => $price * $qty]);

        return $order;
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($dom);
    }

    public static function pages(): array
    {
        return [['seller.products'], ['seller.inventory'], ['seller.reports'], ['seller.sales']];
    }

    #[DataProvider('pages')]
    public function test_shared_panels_are_collapsed_accessible_and_have_correct_date_presets(string $route): void
    {
        $response = $this->get(route($route))->assertOk()->assertSee('data-seller-filters', false)->assertSeeText('Filters')->assertSeeText('Apply Filters')->assertSeeText('Reset Filters');
        $xpath = $this->xpath($response->getContent());
        $button = $xpath->query('//button[@data-filter-toggle]')->item(0);
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('false', $button->getAttribute('aria-expanded'));
        $this->assertCount(1, $xpath->query('//section[@data-filter-panel and @hidden and @id="'.$button->getAttribute('aria-controls').'"]'));
        $this->assertCount(0, $xpath->query('//a[@data-filter-chip]'));
        $this->assertCount(10, $xpath->query('//select[@name="date_range"]/option'));
        $this->assertGreaterThanOrEqual(1, $xpath->query('//noscript')->count());
    }

    #[DataProvider('pages')]
    public function test_validation_feedback_opens_shared_panels(string $route): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['from' => 'Choose a valid custom date.']));
        $response = $this->withSession(['errors' => $errors])->get(route($route))->assertOk()->assertSeeText('Choose a valid custom date.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('true', $xpath->query('//button[@data-filter-toggle]')->item(0)->getAttribute('aria-expanded'));
        $this->assertCount(0, $xpath->query('//section[@data-filter-panel and @hidden]'));
    }

    public static function chipPages(): array
    {
        return [['seller.inventory', ['stock_status' => 'low-stock', 'sort' => 'stock_asc'], 'stock_status'],
            ['seller.reports', ['order_status' => 'completed', 'sort' => 'units_desc'], 'order_status'],
            ['seller.sales', ['order_status' => 'completed', 'sort' => 'oldest'], 'order_status']];
    }

    #[DataProvider('chipPages')]
    public function test_chips_retain_other_filters_remove_page_and_reset_cleanly(string $route, array $extra, string $remove): void
    {
        $this->order();
        $query = $extra + ['date_range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31', 'page' => 2, 'seller_id' => 9999, 'unknown' => 'discard'];
        $response = $this->get(route($route, $query))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertCount(3, $xpath->query('//a[@data-filter-chip]'));
        $this->assertSame('true', $xpath->query('//button[@data-filter-toggle]')->item(0)->getAttribute('aria-expanded'));
        $href = $xpath->query('//a[@data-filter-chip="'.$remove.'"]')->item(0)->getAttribute('href');
        parse_str(parse_url($href, PHP_URL_QUERY), $retained);
        foreach ([$remove, 'page', 'seller_id', 'unknown'] as $key) {
            $this->assertArrayNotHasKey($key, $retained);
        }
        $this->assertSame('custom', $retained['date_range']);
        $this->assertSame($extra['sort'], $retained['sort']);
        $this->get($href)->assertOk()->assertViewHas('paginator', fn ($p) => $p->currentPage() === 1);
        $this->get(route($route))->assertOk()->assertDontSee('data-filter-chip=', false)->assertDontSee('data-active-filter-count', false);
    }

    public function test_reports_chips_default_sort_grouped_dates_and_component_consistency(): void
    {
        $this->order();
        $other = $this->product('Second product');
        $this->order('COMPLETED', 1, 500, $other);
        $q = ['date_range' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-23', 'product_id' => $this->product->id, 'order_status' => 'completed', 'payment_method' => 'cod', 'sort' => 'sales_desc'];
        $response = $this->get(route('seller.reports', $q))->assertOk()->assertViewHas('grossSales', 50.0)
            ->assertViewHas('chart', fn ($chart) => $chart->sum('gross') == 50)->assertViewHas('breakdown', fn ($rows) => $rows->sum('gross') == 50)
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1);
        $xpath = $this->xpath($response->getContent());
        $this->assertCount(4, $xpath->query('//a[@data-filter-chip]'));
        $this->assertCount(0, $xpath->query('//a[@data-filter-chip="sort"]'));
        $href = $xpath->query('//a[@data-filter-chip="product_id"]')->item(0)->getAttribute('href');
        $this->get($href)->assertOk()->assertViewHas('grossSales', 550.0)->assertSeeText('Date: 2026-10-23 – 2026-10-23');
    }

    public function test_sales_uses_persisted_items_not_session_totals_shipping_or_payment_assumptions(): void
    {
        $completed = $this->order('COMPLETED'); // 50 gross, unpaid.
        $completed->payments()->create(['method' => 'cod', 'amount_minor' => 999999, 'status' => 'pending']);
        $this->order('DELIVERED', 1, 100);
        $this->order('PLACED', 3, 10);
        $this->order('CANCELLED', 1, 200);
        $response = $this->withSession(['orders' => [['total' => 777777, 'status' => 'completed']], 'seller_orders' => [['total_amount' => 777777]]])->get(route('seller.sales'))->assertOk()
            ->assertViewHas('grossCompleted', 150.0)->assertViewHas('pendingValue', 30.0)->assertViewHas('averageCompleted', 75.0)
            ->assertViewHas('cancelledCount', 1)->assertViewHas('completedCount', 2)->assertViewHas('paginator', fn ($p) => $p->total() === 4)
            ->assertSeeText('Gross Completed Sales')->assertSeeText('Pending Item Value')->assertSeeText('Completed/delivered orders do not prove payment')
            ->assertSeeText('payouts are unavailable')->assertDontSee('777,777')->assertDontSee('99,999');
        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('order_items', 4);
    }

    public function test_sales_combined_product_status_method_and_inclusive_manila_date_boundaries(): void
    {
        $inside = $this->order('DELIVERED', 2, 25, null, '2026-10-23 15:59:59'); // Manila Oct 23 end.
        $this->order('DELIVERED', 2, 300, null, '2026-10-23 16:00:00'); // Manila Oct 24.
        $this->order('PLACED', 1, 700);
        $other = $this->product('Not selected');
        $this->order('DELIVERED', 1, 1000, $other);
        $q = ['date_range' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-23', 'order_status' => 'delivered', 'product_id' => $this->product->id, 'payment_method' => 'cod', 'category' => SellerCategories::main($this->shop)->id, 'subcategory' => $this->product->category_id];
        $before = $inside->fresh()->toArray();
        $this->get(route('seller.sales', $q))->assertOk()->assertViewHas('grossCompleted', 50.0)->assertViewHas('pendingValue', 0.0)
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1 && $p->first()->order_id === $inside->id);
        $this->assertSame($before, $inside->fresh()->toArray());
        $this->assertSame(10, $this->product->variants->first()->fresh()->stock);
    }

    public function test_normalized_ownership_minor_prices_and_legacy_precedence_prevent_double_counting(): void
    {
        $order = $this->order();
        $sellerOrder = $order->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 5000]);
        $order->items()->create(['seller_id' => null, 'seller_order_id' => $sellerOrder->id, 'product_id' => $this->product->id,
            'product_slug' => $this->product->slug, 'product_name' => 'Normalized item', 'unit_price' => 999, 'unit_price_minor' => 2500, 'quantity' => 2, 'line_total' => 1998]);
        $order->update(['seller_id' => null]);
        $this->get(route('seller.sales'))->assertOk()->assertViewHas('grossCompleted', 50.0)->assertViewHas('paginator', fn ($p) => $p->total() === 1 && (int) $p->first()->item_count === 1);
        $order->update(['seller_id' => $this->shop->user_id]);
        $this->get(route('seller.sales'))->assertOk()->assertViewHas('grossCompleted', 50.0);
        $this->get(route('seller.reports'))->assertOk()->assertViewHas('grossSales', 50.0)->assertViewHas('paginator', fn ($p) => $p->total() === 1);
    }

    public function test_sales_seller_isolation_query_ids_cannot_select_foreign_records(): void
    {
        $this->order();
        $foreignOwner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $foreign = Seller::create(['user_id' => $foreignOwner->id, 'name' => 'Foreign filter shop', 'slug' => 'foreign-filter-shop', 'status' => 'approved']);
        $foreignOrder = $this->order('COMPLETED', 1, 9999);
        $foreignOrder->update(['seller_id' => $foreignOwner->id, 'payment_method' => 'foreign-method']);
        $foreignOrder->items()->update(['seller_id' => $foreignOwner->id]);
        $foreignProduct = $this->product('Foreign product');
        $foreignProduct->update(['seller_id' => $foreign->id]);
        $this->get(route('seller.sales', ['seller_id' => $foreign->id]))->assertOk()->assertViewHas('grossCompleted', 50.0)->assertDontSee($foreignOrder->order_number)->assertDontSee('foreign-method');
        $this->getJson(route('seller.sales', ['product_id' => $foreignProduct->id]))->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->getJson(route('seller.sales', ['payment_method' => 'foreign-method']))->assertUnprocessable()->assertJsonValidationErrors('payment_method');
    }

    public function test_sales_sorting_is_database_backed_stable_and_paginated_with_filters(): void
    {
        for ($i = 0; $i < 23; $i++) {
            $this->order('COMPLETED', 1, 10 + $i);
        }
        $response = $this->get(route('seller.sales', ['order_status' => 'completed', 'sort' => 'amount_desc']))->assertOk()
            ->assertViewHas('grossCompleted', 483.0)->assertViewHas('paginator', fn ($p) => $p->total() === 23 && $p->count() === 20 && $p->first()->gross_amount == 32 && str_contains($p->url(2), 'sort=amount_desc'));
        $data = SellerSales::data($this->shop, new Request(['sort' => 'oldest']));
        $this->assertSame(1, $data['paginator']->first()->order_id);
        $this->get(route('seller.sales', ['sort' => 'amount_asc', 'page' => 2]))->assertOk()->assertViewHas('paginator', fn ($p) => $p->count() === 3 && $p->first()->gross_amount == 30);
    }

    public function test_empty_and_invalid_filters_and_existing_seller_approval_guards(): void
    {
        $this->get(route('seller.sales'))->assertOk()->assertSeeText('No matching saved transactions')->assertViewHas('grossCompleted', 0.0)->assertViewHas('averageCompleted', null)->assertDontSee('₱Unavailable');
        foreach (['seller.inventory', 'seller.reports', 'seller.sales'] as $route) {
            $this->getJson(route($route, ['date_range' => 'custom', 'from' => '2026-10-24', 'to' => '2026-10-23']))->assertUnprocessable();
            $this->getJson(route($route, ['date_range' => 'custom', 'from' => 'not-a-date', 'to' => '2026-10-23']))->assertUnprocessable();
        }
        $this->getJson(route('seller.sales', ['sort' => 'gross_amount desc; DROP TABLE orders']))->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->shop->update(['status' => 'pending']);
        $this->get(route('seller.sales'))->assertForbidden();
        $this->app->detectEnvironment(fn () => 'local');
        $this->get(route('seller.sales'))->assertOk();
    }

    public function test_sales_date_presets_use_the_shared_server_boundaries(): void
    {
        foreach (array_keys(SellerDateFilter::PRESETS) as $preset) {
            $q = ['date_range' => $preset, 'from' => '2026-10-23', 'to' => '2026-10-23'];
            $this->get(route('seller.sales', $q))->assertOk();
        }
        $this->assertDatabaseCount('orders', 0);
    }
}
