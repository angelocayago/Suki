<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerProductFilterChips;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProductListingUxTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Listing UX', 'slug' => 'listing-ux', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function product(int $stock, array $details = [], ?Seller $shop = null): Product
    {
        $product = Product::create(['seller_id' => ($shop ?? $this->shop)->id, 'category_id' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'name' => 'Product '.$stock, 'slug' => 'ux-'.uniqid(), 'is_active' => true, 'seller_listing_data' => $details]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'UX-'.uniqid(), 'stock' => $stock, 'price_minor' => 10000, 'is_active' => true]);

        return $product;
    }

    private function dom(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new \DOMXPath($dom);
    }

    public function test_default_page_has_collapsed_accessible_panel_and_preserves_all_filter_fields(): void
    {
        $this->product(10);
        $response = $this->get(route('seller.products'))->assertOk()->assertSeeText('Product Management')->assertSeeText('Product Listings')->assertSeeText('Manage Inventory');
        $xpath = $this->dom($response->getContent());
        $this->assertCount(1, $xpath->query('//button[@data-filter-toggle and @type="button" and @aria-expanded="false" and @aria-controls="product-advanced-filters"]'));
        $this->assertCount(1, $xpath->query('//section[@id="product-advanced-filters" and @hidden]'));
        $this->assertCount(1, $xpath->query('//button[@data-filter-close and @type="button"]'));
        $form = $xpath->query('//section[@id="product-advanced-filters"]//form')->item(0);
        $this->assertSame('GET', $form->getAttribute('method'));
        $this->assertSame(route('seller.products'), $form->getAttribute('action'));
        foreach (SellerProductFilterChips::KEYS as $key) {
            $this->assertCount(1, $xpath->query('//section[@id="product-advanced-filters"]//*[@name="'.$key.'"]'));
        }
        $this->assertCount(0, $xpath->query('//a[@data-filter-chip]'));
        $this->assertCount(0, $xpath->query('//*[@data-active-filter-count]'));
    }

    public function test_chips_reflect_real_filters_remove_one_preserve_others_and_drop_page(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $product = $this->product(10);
        }
        $query = ['search' => 'Product', 'stock_status' => 'low-stock', 'subcategory' => $product->category_id, 'sort' => 'stock_asc', 'page' => 2, 'unsupported' => 'discard'];
        $response = $this->get(route('seller.products', $query))->assertOk()->assertSeeText('Showing 16–16 of 16 matching products');
        $xpath = $this->dom($response->getContent());
        $this->assertCount(4, $xpath->query('//a[@data-filter-chip]'));
        $this->assertCount(1, $xpath->query('//button[@data-filter-toggle and @aria-expanded="true"]'));
        $chip = $xpath->query('//a[@data-filter-chip="stock_status"]')->item(0);
        parse_str(parse_url($chip->getAttribute('href'), PHP_URL_QUERY), $retained);
        $this->assertSame('Product', $retained['search']);
        $this->assertSame('stock_asc', $retained['sort']);
        $this->assertEquals($product->category_id, $retained['subcategory']);
        $this->assertArrayNotHasKey('stock_status', $retained);
        $this->assertArrayNotHasKey('page', $retained);
        $this->assertArrayNotHasKey('unsupported', $retained);
        $this->get($chip->getAttribute('href'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->currentPage() === 1 && $p->total() === 16);
        $this->get(route('seller.products'))->assertOk()->assertDontSee('data-filter-chip=', false)->assertDontSee('data-active-filter-count', false);
    }

    public function test_default_values_and_irrelevant_dates_do_not_create_chips(): void
    {
        $response = $this->get(route('seller.products', ['search' => '', 'status' => '', 'sort' => 'name_asc', 'date_range' => 'all', 'date_field' => 'created_at', 'from' => '2026-01-01', 'to' => '2026-01-02']))->assertOk();
        $xpath = $this->dom($response->getContent());
        $this->assertCount(0, $xpath->query('//a[@data-filter-chip]'));
        $this->assertCount(1, $xpath->query('//*[@data-filter-panel and @hidden]'));
    }

    public function test_ranges_are_grouped_and_chip_removal_clears_only_its_criterion(): void
    {
        $query = ['date_range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-31', 'date_field' => 'updated_at', 'min_stock' => '1', 'max_stock' => '10', 'min_price' => '100', 'max_price' => '500', 'status' => 'active'];
        $response = $this->get(route('seller.products', $query))->assertOk();
        $xpath = $this->dom($response->getContent());
        $this->assertCount(5, $xpath->query('//a[@data-filter-chip]'));
        $response->assertSeeText('Date: 2026-01-01 – 2026-01-31')->assertSeeText('Stock: 1 – 10')->assertSeeText('Price: ₱100.00 – ₱500.00');
        foreach (['date_range' => ['date_range', 'from', 'to'], 'stock' => ['min_stock', 'max_stock'], 'price' => ['min_price', 'max_price']] as $key => $removed) {
            $href = $xpath->query('//a[@data-filter-chip="'.$key.'"]')->item(0)->getAttribute('href');
            parse_str(parse_url($href, PHP_URL_QUERY), $retained);
            foreach ($removed as $name) {
                $this->assertArrayNotHasKey($name, $retained);
            }
            $this->assertSame('active', $retained['status']);
        }
    }

    public function test_preset_chip_hides_stale_custom_dates_and_labels_are_escaped(): void
    {
        $this->get(route('seller.products', ['date_range' => 'last30', 'from' => '2026-01-01', 'to' => '2026-01-31', 'search' => '<script>bad</script>']))->assertOk()
            ->assertSeeText('Last 30 Days')->assertDontSee('Date: 2026-01-01')->assertSee('&lt;script&gt;bad&lt;/script&gt;', false)->assertDontSee('<script>bad</script>', false);
    }

    public function test_selection_contains_only_current_page_owned_products(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->product($i);
        }
        $foreignShop = Seller::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'slug' => 'ux-foreign', 'status' => 'approved']);
        $foreign = $this->product(10, [], $foreignShop);
        $response = $this->get(route('seller.products'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 16);
        $xpath = $this->dom($response->getContent());
        $boxes = $xpath->query('//input[@data-product-checkbox]');
        $this->assertCount(30, $boxes); // 15 desktop + matching mobile representations.
        $ids = [];
        foreach ($boxes as $box) {
            $ids[] = $box->getAttribute('value');
        }
        $this->assertCount(15, array_unique($ids));
        $this->assertNotContains('P'.str_pad($foreign->id, 4, '0', STR_PAD_LEFT), $ids);
        $this->assertCount(2, $xpath->query('//input[@data-select-page and @aria-label="Select all products on this page"]'));
        $this->assertCount(0, $xpath->query('//input[@data-product-checkbox and @name]'));
        $response->assertSeeText('Current page only')->assertSeeText('no bulk actions');
    }

    public function test_stock_badges_are_separate_from_listing_status_and_show_variant_alerts(): void
    {
        $this->product(0);
        $this->product(10);
        $this->product(11);
        $custom = $this->product(6, ['low_stock_threshold' => 5]);
        $variable = $this->product(3, ['product_type' => 'variable']);
        $variable->variants()->create(['name' => 'Large', 'sku' => 'UX-LARGE', 'price_minor' => 10000, 'stock' => 20, 'is_active' => true]);
        $variable->variants()->create(['name' => 'Disabled', 'sku' => 'UX-DISABLED', 'price_minor' => 10000, 'stock' => 1000, 'is_active' => false]);
        $response = $this->get(route('seller.products'))->assertOk()->assertSeeText('Aggregate Stock Status')->assertSeeText('Listing Status')->assertSeeText('Last Updated')->assertSeeText('1 low-stock alerts')->assertSeeText('Inactive (excluded)');
        $xpath = $this->dom($response->getContent());
        foreach (['out-of-stock' => 2, 'low-stock' => 2, 'in-stock' => 6] as $status => $count) {
            $this->assertCount($count, $xpath->query('//span[@data-stock-status="'.$status.'"]'));
        }
        $this->assertCount(10, $xpath->query('//span[@data-listing-status="active"]'));
        $response->assertViewHas('products', fn ($rows) => collect($rows)->firstWhere('id', 'P'.str_pad($variable->id, 4, '0', STR_PAD_LEFT))['stock'] === 23);
    }

    public function test_combined_filters_matching_count_summary_scope_and_literal_zero_search(): void
    {
        $matching = $this->product(10);
        $this->product(11);
        $response = $this->get(route('seller.products', ['search' => '0', 'stock_status' => 'low-stock', 'min_price' => 100, 'max_price' => 100, 'min_stock' => 1, 'max_stock' => 10, 'subcategory' => $matching->category_id]))->assertOk()
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1)
            ->assertViewHas('filteredSummary', fn ($s) => $s->count() === 1 && $s->where('status', 'active')->count() === 1)
            ->assertSeeText('1 matching products');
        $this->assertCount(5, $this->dom($response->getContent())->query('//a[@data-filter-chip]'));
    }

    public function test_specific_variant_is_owned_and_has_a_removable_sku_chip(): void
    {
        $product = $this->product(10);
        $variant = $product->variants->first();
        $response = $this->get(route('seller.products', ['variant_id' => $variant->id]))->assertOk()->assertSeeText('Variant: '.$variant->sku)
            ->assertViewHas('paginator', fn ($p) => $p->total() === 1);
        $this->assertCount(1, $this->dom($response->getContent())->query('//a[@data-filter-chip="variant_id"]'));
        $foreignShop = Seller::create(['user_id' => User::factory()->create()->id, 'name' => 'Other variant shop', 'slug' => 'other-variant-shop', 'status' => 'approved']);
        $foreignVariant = $this->product(11, [], $foreignShop)->variants->first();
        $this->getJson(route('seller.products', ['variant_id' => $foreignVariant->id]))->assertUnprocessable()->assertJsonValidationErrors('variant_id');
        $this->getJson(route('seller.products', ['variant_id' => 999999]))->assertUnprocessable()->assertJsonValidationErrors('variant_id');
    }
}
