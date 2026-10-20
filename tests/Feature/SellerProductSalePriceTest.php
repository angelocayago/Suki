<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerProductSalePriceTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Pricing shop',
            'slug' => 'pricing-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Pricing product', 'description' => 'Product details',
            'subcategory' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'price' => '149.00', 'stock' => 7, 'status' => 'active', 'low_stock_threshold' => 4,
            'brand' => 'Test brand', 'has_variations' => 1, 'variation_name' => ['Color'],
            'variation_value' => ['Green'], 'shipping_options' => ['suki_rider'],
            'images' => [UploadedFile::fake()->createWithContent('product.png', file_get_contents(public_path('images/logo.png')))]], $extra);
    }

    private function createProduct(array $extra = []): Product
    {
        $this->post(route('seller.products.store'), $this->payload($extra))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return Product::sole();
    }

    private function assertPricing(Product $product, ?float $sale): void
    {
        $product->refresh();
        $this->assertEquals(149, $product->seller_listing_data['regular_price']);
        $this->assertSame($sale !== null, $product->seller_listing_data['on_sale']);
        $this->assertEquals($sale, $product->seller_listing_data['sale_price']);
        if ($sale === null) {
            $this->assertNull($product->seller_listing_data['sale_price']);
        }
        $this->assertSame((int) round(($sale ?? 149) * 100), $product->variants()->sole()->price_minor);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public static function disabledSales(): array
    {
        return ['omitted' => [[]], 'blank' => [['on_sale' => '0', 'sale_price' => '']],
            'stale number' => [['on_sale' => '0', 'sale_price' => '99']],
            'stale invalid' => [['on_sale' => false, 'sale_price' => 'invalid']],
            'omitted with stale data' => [['sale_price' => '200']]];
    }

    #[DataProvider('disabledSales')]
    public function test_create_and_edit_ignore_disabled_sale_data(array $extra): void
    {
        $product = $this->createProduct($extra);
        $this->assertPricing($product, null);
        $this->put(route('seller.products.update', $product->id), $this->payload($extra))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        $this->assertPricing($product, null);
    }

    public static function invalidSales(): array
    {
        return ['non numeric' => ['abc'], 'equal' => ['149'], 'higher' => ['200'],
            'negative' => ['-1'], 'zero' => ['0'], 'blank' => [''], 'missing' => [null],
            'excess precision' => ['98.999']];
    }

    #[DataProvider('invalidSales')]
    public function test_enabled_invalid_sales_cannot_create_or_change_product_data(?string $sale): void
    {
        $extra = ['on_sale' => 1, 'sale_price' => $sale];
        $this->post(route('seller.products.store'), $this->payload($extra))->assertSessionHasErrors('sale_price');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $product = $this->createProduct(['on_sale' => 1, 'sale_price' => '99']);
        $before = $product->fresh(['variants', 'images'])->toArray();
        $files = Storage::disk('public')->allFiles();
        $this->from(route('seller.products.edit', $product->id))
            ->put(route('seller.products.update', $product->id), $this->payload($extra + ['name' => 'Attempted edit']))
            ->assertSessionHasErrors('sale_price')->assertSessionHasInput('on_sale', 1)
            ->assertSessionHasInput('name', 'Attempted edit');
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_create_enable_update_and_remove_sale_preserve_identity_and_other_data(): void
    {
        $product = $this->createProduct();
        $variant = $product->variants()->sole();
        $images = $product->images->toArray();
        $metadata = $product->seller_listing_data;
        foreach ([99.0, 89.75, null] as $sale) {
            $this->put(route('seller.products.update', $product->id), $this->payload([
                'on_sale' => $sale !== null ? 1 : 0, 'sale_price' => $sale ?? '89.75',
                'sku' => $variant->sku,
            ]))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
            $this->assertPricing($product, $sale);
            $this->assertSame($variant->id, $product->variants()->sole()->id);
            $this->assertSame($images, $product->fresh()->images->toArray());
            $this->assertSame($this->shop->id, $product->seller_id);
            $this->assertSame(7, $product->variants()->sole()->stock);
            foreach (['low_stock_threshold', 'brand', 'variation_names', 'variation_values', 'shipping_options'] as $field) {
                $this->assertSame($metadata[$field], $product->seller_listing_data[$field]);
            }
        }
    }

    public function test_valid_sale_create_and_both_listing_layouts_use_real_prices(): void
    {
        $product = $this->createProduct(['on_sale' => 1, 'sale_price' => '99.50']);
        $this->assertPricing($product, 99.50);
        $response = $this->get(route('seller.products'))->assertOk()->assertSeeText('₱149.00')->assertSeeText('₱99.50');
        $this->assertSame(2, substr_count($response->getContent(), 'line-through'));
        $this->put(route('seller.products.update', $product->id), $this->payload(['on_sale' => 0]))->assertSessionHasNoErrors();
        $this->get(route('seller.products'))->assertOk()->assertSeeText('₱149.00')->assertDontSee('line-through');
    }

    public function test_edit_form_restores_regular_sale_and_old_checkbox_state(): void
    {
        $product = $this->createProduct(['on_sale' => 1, 'sale_price' => 99]);
        $url = route('seller.products.edit', $product->id);
        $response = $this->get($url)->assertOk()->assertSee('value="99"', false);
        $this->assertMatchesRegularExpression('/id="price"\s+name="price"\s+value="149"/s', $response->getContent());
        $this->assertMatchesRegularExpression('/id="on_sale"[^>]*value="1"\s+checked/s', $response->getContent());
        $this->from($url)->put(route('seller.products.update', $product->id), $this->payload([
            'name' => '', 'on_sale' => 0, 'sale_price' => 99,
        ]))->assertSessionHasErrors('name')->assertSessionDoesntHaveErrors('sale_price');
        $response = $this->get($url)->assertOk();
        $this->assertMatchesRegularExpression('/id="on_sale"[^>]*value="1"\s+class=/s', $response->getContent());
        $this->assertPricing($product, 99);
    }

    public function test_sale_flag_alone_does_not_display_invalid_or_inconsistent_discounts(): void
    {
        $product = $this->createProduct();
        foreach ([0, -1, 149, 200, 99] as $sale) {
            $product->update(['seller_listing_data' => array_replace($product->seller_listing_data,
                ['on_sale' => true, 'sale_price' => $sale])]);
            $this->assertFalse(SellerProducts::listing($product->fresh(['variants', 'images', 'category']))['on_sale']);
            $this->get(route('seller.products'))->assertOk()->assertDontSee('line-through');
        }
    }

    public function test_sale_requests_preserve_ownership_and_category_restrictions(): void
    {
        $product = $this->createProduct(['seller_id' => 9999]);
        $this->assertSame($this->shop->id, $product->seller_id);
        $foreignChild = Category::whereNotNull('parent_id')->where('parent_id', '!=', SellerCategories::main($this->shop)->id)->firstOrFail();
        $this->post(route('seller.products.store'), $this->payload(['subcategory' => $foreignChild->id, 'on_sale' => 1, 'sale_price' => 99]))
            ->assertSessionHasErrors('subcategory');
        $this->put(route('seller.products.update', $product->id), $this->payload(['subcategory' => $foreignChild->id, 'on_sale' => 1, 'sale_price' => 99]))
            ->assertSessionHasErrors('subcategory');
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $owner->id, 'name' => 'Other shop', 'slug' => 'other-shop',
            'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner)->put(route('seller.products.update', $product->id), $this->payload(['on_sale' => 1, 'sale_price' => 99]))->assertNotFound();
        $this->assertPricing($product, null);
    }

    public function test_enabled_sale_requires_valid_regular_price_and_drafts_follow_same_sale_rules(): void
    {
        foreach (['', 'invalid', -1] as $price) {
            $this->post(route('seller.products.store'), $this->payload(['price' => $price, 'on_sale' => 1, 'sale_price' => 99]))
                ->assertSessionHasErrors('price');
        }
        $this->post(route('seller.products.store'), ['submit_action' => 'draft', 'on_sale' => 1])
            ->assertSessionHasErrors(['price', 'sale_price']);
        $this->post(route('seller.products.store'), $this->payload(['on_sale' => 'invalid']))->assertSessionHasErrors('on_sale');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_all_route_definitions_match_the_pre_fix_snapshot(): void
    {
        Artisan::call('route:list', ['--json' => true]);
        $routes = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $actual = array_map(fn (array $route) => array_intersect_key($route,
            array_flip(['method', 'uri', 'name', 'action', 'middleware'])), $routes);
        $expected = json_decode(file_get_contents(base_path('tests/Fixtures/seller-product-routes.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals($expected, $actual);
    }
}
