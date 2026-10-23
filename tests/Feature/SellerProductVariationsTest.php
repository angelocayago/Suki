<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerProductVariationsTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    public function test_create_form_has_ordered_optional_options_and_preserves_existing_controls(): void
    {
        $response = $this->get(route('seller.products.create'))->assertOk()
            ->assertViewIs('seller.products-create')
            ->assertSee('data-add-product-page="reference-v2"', false)
            ->assertSee('class="mx-auto max-w-3xl"', false)
            ->assertSee('data-photo-upload', false)->assertSee('data-photo-preview-grid', false)
            ->assertSeeText('Click to upload product photos')
            ->assertSeeText('Generated automatically after saving')
            ->assertSeeText('Product Actions')->assertSeeText('Save as Draft')
            ->assertDontSeeText('Before publishing')->assertDontSeeText('Listing Settings')
            ->assertSeeTextInOrder(['Basic Information', 'Product Name', 'Description', 'Main Category / Line of Business',
                'Pricing & Stock', 'Options & Variants', 'Product Photos', 'Sale pricing', 'Payment Methods'])
            ->assertSeeText('+ Add Option')->assertSeeText('Generate Variant Combinations')
            ->assertSeeText('Cash on Delivery (COD)')->assertSeeText('5 MB each')
            ->assertSee('id="productType" name="product_type" value="simple"', false)
            ->assertDontSee('<select id="productType"', false);
        $response->assertSee('id="category" type="text" readonly', false);
        $response->assertSee(app(Vite::class)->asset('resources/js/app.js'), false);
        $response->assertSee(app(Vite::class)->asset('resources/css/app.css'), false);
        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[@id="productForm"]')->item(0);
        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame(route('seller.products.store'), $form->getAttribute('action'));
        $this->assertSame('multipart/form-data', $form->getAttribute('enctype'));
        $this->assertCount(1, $xpath->query('//form[@id="productForm"]//input[@name="_token"]'));
        $this->assertCount(1, $xpath->query('//button[@data-add-group]'));
        $this->assertCount(1, $xpath->query('//button[@data-photo-upload]'));
        $this->assertCount(1, $xpath->query('//input[@id="images" and @hidden]'));
        $this->assertCount(1, $xpath->query('//input[@id="price" and @required and not(@disabled)]'));
        $this->assertCount(1, $xpath->query('//input[@id="stock" and @required and not(@disabled)]'));
        preg_match('/<script type="application\/json" data-variant-state>(.*?)<\/script>/s', $html, $match);
        $state = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $state['groups']);
        $this->assertSame([], $state['rows']);
        foreach (SellerCategories::children($this->shop, SellerCategories::main($this->shop)) as $child) {
            $response->assertSeeText($child->name);
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_create_validation_restores_option_groups_and_rows_without_trusting_skus(): void
    {
        $payload = $this->payload();
        $payload['variant_rows'][0]['price'] = 'invalid';
        $payload['variant_rows'][0]['sku'] = 'UNTRUSTED-CLIENT-SKU';
        $url = route('seller.products.create');
        $this->from($url)->post(route('seller.products.store'), $payload)->assertSessionHasErrors('variant_rows.0.price');
        $response = $this->get($url)->assertOk()->assertDontSee('UNTRUSTED-CLIENT-SKU');
        preg_match('/<script type="application\/json" data-variant-state>(.*?)<\/script>/s', $response->getContent(), $match);
        $state = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($payload['option_groups'], $state['groups']);
        $this->assertSame('invalid', $state['rows'][0]['price']);
        $this->assertSame(0, $state['rows'][0]['stock']);
        $this->assertArrayNotHasKey('sku', $state['rows'][0]);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_client_simple_price_and_stock_do_not_override_variant_summaries(): void
    {
        $product = $this->product(['price' => '999999', 'stock' => 999999]);
        $listing = SellerProducts::listing($product);
        $this->assertEquals(100.50, $listing['price']);
        $this->assertSame(6, $listing['stock']);
        $payload = $this->editPayload($product) + ['price' => '888888', 'stock' => 888888];
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $this->assertSame(6, SellerProducts::listing($product->fresh())['stock']);
        $this->assertDatabaseCount('product_variants', 4);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Variants fixture', 'slug' => 'variants-fixture',
            'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function optionGroups(): array
    {
        return [['name' => 'Size', 'values' => ['Small', 'Large']], ['name' => 'Color', 'values' => ['Black', 'White']]];
    }

    private function rows(array $groups): array
    {
        $selections = [[]];
        foreach ($groups as $group) {
            $next = [];
            foreach ($selections as $options) {
                foreach ($group['values'] as $value) {
                    $next[] = $options + [$group['name'] => $value];
                }
            }
            $selections = $next;
        }

        return array_map(fn ($options, $i) => ['options' => $options, 'price' => 100.50 + $i, 'stock' => $i, 'is_active' => 1], $selections, array_keys($selections));
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Variant product', 'description' => 'Details', 'product_type' => 'variable',
            'subcategory' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'option_groups' => $this->optionGroups(), 'variant_rows' => $this->rows($this->optionGroups()), 'status' => 'active',
            'images' => [UploadedFile::fake()->createWithContent('photo.png', file_get_contents(public_path('images/logo.png')))]], $extra);
    }

    private function product(array $extra = []): Product
    {
        $this->post(route('seller.products.store'), $this->payload($extra))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return Product::sole();
    }

    private function editPayload(Product $product): array
    {
        $listing = SellerProducts::listing($product->fresh());
        $rows = array_map(fn ($row) => array_diff_key($row, ['available' => true]), $listing['variant_rows']);

        return ['name' => $product->name, 'description' => $product->description, 'product_type' => 'variable',
            'subcategory' => $product->category_id, 'status' => 'active', 'option_groups' => $listing['option_groups'], 'variant_rows' => $rows];
    }

    public function test_two_dimensions_have_exact_combinations_prices_stock_and_no_extra_default_variant(): void
    {
        $product = $this->product();
        $variants = $product->variants;
        $this->assertSame([['Size' => 'Small', 'Color' => 'Black'], ['Size' => 'Small', 'Color' => 'White'],
            ['Size' => 'Large', 'Color' => 'Black'], ['Size' => 'Large', 'Color' => 'White']], $variants->pluck('options')->all());
        $this->assertSame([10050, 10150, 10250, 10350], $variants->pluck('price_minor')->all());
        $this->assertSame([0, 1, 2, 3], $variants->pluck('stock')->all());
        $this->assertSame('S'.$this->shop->id.'-P'.$product->id, $variants->first()->sku);
        $this->assertCount(4, $variants->pluck('sku')->unique());
        foreach ($variants->skip(1) as $variant) {
            $this->assertMatchesRegularExpression('/^S'.$this->shop->id.'-P'.$product->id.'-V[A-Z0-9]{10}$/', $variant->sku);
        }
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertNull($product->seller_listing_data['regular_price']);
        $this->assertNull($product->seller_listing_data['sale_price']);
        $this->assertSame(['logistics_partner'], $product->seller_listing_data['shipping_options']);
        $listing = SellerProducts::listing($product);
        $this->assertEquals(100.50, $listing['price']);
        $this->assertEquals(103.50, $listing['price_max']);
        $this->assertSame(6, $listing['stock']);
        $this->assertFalse($listing['variant_rows'][0]['available']);
        foreach ([route('seller.products'), route('seller.inventory')] as $url) {
            $this->get($url)->assertOk()->assertSeeText('₱100.50')->assertSeeText('₱103.50');
        }
        $this->get(route('seller.products.edit', $product->id))->assertOk()->assertSeeText('Cash on Delivery (COD)')->assertSee($variants[1]->sku);
        $owner = $this->shop->owner;
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('seller.products.edit', $product->id))->assertOk()->assertSee($variants[1]->sku);
        $this->assertDatabaseCount('product_variants', 4);
    }

    public function test_one_dimension_even_with_one_combination_is_a_variable_product(): void
    {
        $groups = [['name' => 'Material [type]', 'values' => ['Cotton & silk']]];
        $row = ['selections' => ['Cotton & silk'], 'price' => '1.25', 'stock' => 0, 'is_active' => 1];
        $product = $this->product(['option_groups' => $groups, 'variant_rows' => [$row]]);
        $this->assertSame(['Material [type]' => 'Cotton & silk'], $product->variants->sole()->options);
        $this->assertTrue(SellerProducts::listing($product)['is_variable']);
        $this->post(route('seller.inventory.increase', $product->id))->assertStatus(409);
        $this->assertSame(0, $product->variants->sole()->fresh()->stock);
    }

    public function test_price_stock_and_status_edits_preserve_other_variant_identity_and_images(): void
    {
        $product = $this->product();
        $before = $product->variants->toArray();
        $images = $product->images->toArray();
        $payload = $this->editPayload($product);
        $payload['variant_rows'][0]['price'] = '88.25';
        $payload['variant_rows'][1]['stock'] = 20;
        $payload['variant_rows'][2]['is_active'] = 0;
        $payload['variant_rows'][3]['sku'] = ['forged'];
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $fresh = $product->fresh();
        $this->assertSame(array_column($before, 'id'), $fresh->variants->pluck('id')->all());
        $this->assertSame(array_column($before, 'sku'), $fresh->variants->pluck('sku')->all());
        $this->assertSame([8825, 10150, 10250, 10350], $fresh->variants->pluck('price_minor')->all());
        $this->assertSame([0, 20, 2, 3], $fresh->variants->pluck('stock')->all());
        $this->assertSame($images, $fresh->images->toArray());
        $this->assertSame(23, SellerProducts::listing($fresh)['stock']);
        $this->get(route('seller.inventory'))->assertOk()->assertSeeText('23 available');
        $this->assertDatabaseCount('product_variants', 4);
    }

    public function test_reordering_and_adding_values_preserve_combinations_and_skus(): void
    {
        $product = $this->product();
        $before = $product->variants->mapWithKeys(fn ($v) => [$v->name => [$v->id, $v->sku, $v->stock]])->all();
        $payload = $this->editPayload($product);
        $payload['option_groups'] = [['name' => 'Color', 'values' => ['White', 'Black']], ['name' => 'Size', 'values' => ['Large', 'Small', 'Medium']]];
        $oldRows = $payload['variant_rows'];
        $payload['variant_rows'] = $this->rows($payload['option_groups']);
        foreach ($payload['variant_rows'] as &$row) {
            foreach ($oldRows as $old) {
                if ($old['options'] == $row['options']) {
                    $row = $old;
                }
            }
        }
        unset($row);
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        foreach ($product->fresh()->variants->take(4) as $variant) {
            $oldName = $variant->options['Size'].' / '.$variant->options['Color'];
            $this->assertSame($before[$oldName], [$variant->id, $variant->sku, $variant->stock]);
        }
        $this->assertDatabaseCount('product_variants', 6);
        $this->put(route('seller.products.update', $product->id), $this->editPayload($product))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_variants', 6);
    }

    public static function invalidInput(): array
    {
        return ['empty name' => ['option_groups.0.name', ''], 'duplicate group' => ['option_groups.1.name', ' size '],
            'empty value' => ['option_groups.0.values.1', '  '], 'duplicate value' => ['option_groups.0.values.1', ' small '],
            'long label' => ['option_groups.0.name', str_repeat('a', 51)],
            'invalid price' => ['variant_rows.0.price', 'abc'], 'zero price' => ['variant_rows.0.price', 0],
            'negative price' => ['variant_rows.0.price', -1], 'precision' => ['variant_rows.0.price', '1.999'],
            'negative stock' => ['variant_rows.0.stock', -1], 'fraction stock' => ['variant_rows.0.stock', 1.5],
            'sale unsupported' => ['variant_rows.0.sale_price', 50], 'global sale unsupported' => ['on_sale', 1],
            'foreign variant' => ['variant_rows.0.id', 9999]];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_variations_never_partially_save(array|string $path, mixed $value): void
    {
        $data = $this->payload();
        data_set($data, $path, $value);
        $this->post(route('seller.products.store'), $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $product = $this->product();
        $before = $product->fresh(['variants', 'images'])->toArray();
        $edit = $this->editPayload($product);
        data_set($edit, $path, $value);
        $this->put(route('seller.products.update', $product->id), $edit)->assertSessionHasErrors();
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
    }

    public function test_duplicate_missing_and_excessive_combinations_are_rejected(): void
    {
        foreach (['duplicate', 'missing', 'excessive'] as $case) {
            $data = $this->payload();
            if ($case === 'duplicate') {
                $data['variant_rows'][1] = $data['variant_rows'][0];
            } elseif ($case === 'missing') {
                array_pop($data['variant_rows']);
            } else {
                $data['option_groups'] = [['name' => 'A', 'values' => array_map('strval', range(1, 8))], ['name' => 'B', 'values' => array_map('strval', range(1, 8))]];
            }
            $this->post(route('seller.products.store'), $data)->assertSessionHasErrors();
            $this->assertDatabaseCount('products', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_saved_type_labels_and_option_groups_cannot_be_destructively_changed(): void
    {
        $product = $this->product();
        foreach (['simple', 'rename', 'remove value', 'remove group'] as $action) {
            $payload = $this->editPayload($product);
            if ($action === 'simple') {
                $payload['product_type'] = 'simple';
            }
            if ($action === 'rename') {
                $payload['option_groups'][0]['name'] = 'Different';
            }
            if ($action === 'remove value') {
                array_pop($payload['option_groups'][0]['values']);
            }
            if ($action === 'remove group') {
                array_pop($payload['option_groups']);
            }
            $before = $product->fresh(['variants', 'images'])->toArray();
            $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasErrors();
            $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
        }
    }

    public function test_foreign_and_remapped_variant_ids_are_rejected(): void
    {
        $product = $this->product();
        $otherProduct = $this->shop->products()->create(['name' => 'Other', 'slug' => 'other', 'category_id' => $product->category_id]);
        $otherVariant = $otherProduct->variants()->create(['sku' => 'FOREIGN', 'name' => 'Other', 'stock' => 1, 'price_minor' => 100]);
        foreach ([$otherVariant->id, $product->variants[1]->id] as $id) {
            $payload = $this->editPayload($product);
            $payload['variant_rows'][0]['id'] = $id;
            $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasErrors('variant_rows');
        }
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $other->id, 'name' => 'Other shop', 'slug' => 'other-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($other)->put(route('seller.products.update', $product->id), $this->editPayload($product))->assertNotFound();
    }

    public function test_deactivation_keeps_order_cart_references_stock_and_identity(): void
    {
        $product = $this->product();
        $variant = $product->variants[1];
        $buyer = User::factory()->create(['role' => 'buyer']);
        $order = Order::create(['buyer_id' => $buyer->id, 'order_number' => 'FIXTURE-ORDER', 'recipient_name' => 'Fixture',
            'recipient_phone' => '09123456789', 'province' => 'Fixture', 'municipality' => 'Fixture', 'barangay' => 'Fixture', 'street_address' => 'Fixture']);
        $item = $order->items()->create(['product_slug' => $product->slug, 'product_name' => $product->name, 'unit_price' => 101.5,
            'quantity' => 1, 'line_total' => 101.5, 'product_id' => $product->id, 'product_variant_id' => $variant->id]);
        $cart = $buyer->cartItems()->create(['product_slug' => $product->slug, 'product_name' => $product->name, 'price' => 101.5,
            'quantity' => 1, 'product_variant_id' => $variant->id]);
        $payload = $this->editPayload($product);
        $payload['variant_rows'][1]['is_active'] = 0;
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $this->assertSame($variant->id, $item->fresh()->product_variant_id);
        $this->assertSame($variant->id, $cart->fresh()->product_variant_id);
        $this->assertFalse($variant->fresh()->is_active);
        $this->assertSame($variant->sku, $variant->fresh()->sku);
        $this->assertSame($variant->stock, $variant->fresh()->stock);
        $this->assertNull($variant->fresh()->deleted_at);
        $this->assertDatabaseCount('product_variants', 4);
    }

    public function test_all_inactive_variants_have_no_fabricated_price_or_available_stock(): void
    {
        $product = $this->product();
        $payload = $this->editPayload($product);
        foreach ($payload['variant_rows'] as &$row) {
            $row['is_active'] = 0;
        }
        unset($row);
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $listing = SellerProducts::listing($product->fresh());
        $this->assertNull($listing['price']);
        $this->assertSame(0, $listing['stock']);
        $this->get(route('seller.products'))->assertOk()->assertSeeText('No active variations');
    }

    public function test_database_failure_rolls_back_every_variant_and_new_upload(): void
    {
        DB::statement("CREATE TRIGGER reject_variable BEFORE INSERT ON product_variants WHEN NEW.name = 'Large / Black' BEGIN SELECT RAISE(ABORT, 'variant fixture failure'); END;");
        $this->post(route('seller.products.store'), $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_validation_failure_restores_entries_but_displays_only_trusted_saved_skus(): void
    {
        $product = $this->product();
        $payload = $this->editPayload($product);
        $payload['variant_rows'][0]['price'] = 'invalid-price';
        $payload['variant_rows'][0]['sku'] = 'FORGED-SKU';
        $url = route('seller.products.edit', $product->id);
        $this->from($url)->put(route('seller.products.update', $product->id), $payload)->assertSessionHasErrors('variant_rows.0.price');
        $this->get($url)->assertOk()->assertSee('invalid-price')->assertDontSee('FORGED-SKU')->assertSee($product->variants->first()->sku);
        $payload['variant_rows'][0]['id'] = ['malformed'];
        $this->from($url)->put(route('seller.products.update', $product->id), $payload)->assertSessionHasErrors('variant_rows.0.id');
        $this->get($url)->assertOk();
    }

    public function test_simple_products_cannot_be_converted_and_legacy_flags_do_not_create_options(): void
    {
        $this->post(route('seller.products.store'), ['submit_action' => 'draft', 'has_variations' => 1,
            'variation_name' => ['Color'], 'variation_value' => ['Green']])->assertSessionHasNoErrors();
        $product = Product::sole();
        $variant = $product->variants->sole();
        $this->assertSame('simple', SellerProducts::listing($product)['product_type']);
        $this->assertNull($variant->options);
        $this->put(route('seller.products.update', $product->id), $this->payload())->assertSessionHasErrors('product_type');
        $this->assertSame($variant->sku, $variant->fresh()->sku);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_three_groups_and_case_normalization_have_stable_backend_combinations(): void
    {
        $groups = $this->optionGroups();
        $groups[] = ['name' => 'Material', 'values' => ['Cotton', 'Silk']];
        $rows = $this->rows($groups);
        $rows[0]['options']['Size'] = ' small ';
        $product = $this->product(['option_groups' => $groups, 'variant_rows' => $rows]);
        $this->assertCount(8, $product->variants);
        $this->assertSame(['Size' => 'Small', 'Color' => 'Black', 'Material' => 'Cotton'], $product->variants->first()->options);
    }

    public function test_group_and_value_limits_are_enforced_before_combination_generation(): void
    {
        foreach ([array_fill(0, 4, ['name' => 'A', 'values' => ['x']]), [['name' => 'A', 'values' => array_map('strval', range(1, 11))]]] as $groups) {
            $this->post(route('seller.products.store'), $this->payload(['option_groups' => $groups]))->assertSessionHasErrors();
            $this->assertDatabaseCount('products', 0);
        }
    }

    public function test_variable_products_keep_image_replacement_and_removal_with_stable_variant_skus(): void
    {
        $product = $this->product();
        $skus = $product->variants->pluck('sku')->all();
        $image = $product->images->sole();
        $payload = $this->editPayload($product);
        $payload['replacement_images'] = [$image->id => UploadedFile::fake()->createWithContent('replacement.png', file_get_contents(public_path('images/logo.png')))];
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $this->assertNotSame($image->path, $image->fresh()->path);
        Storage::disk('public')->assertExists($image->fresh()->path);
        $payload = $this->editPayload($product);
        $payload['remove_image_ids'] = [$image->id];
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame($skus, $product->fresh()->variants->pluck('sku')->all());
    }

    public function test_variable_products_preserve_category_and_local_pending_access_restrictions(): void
    {
        $product = $this->product();
        $payload = $this->editPayload($product);
        $payload['subcategory'] = Category::whereNotNull('parent_id')->where('parent_id', '!=', SellerCategories::main($this->shop)->id)->firstOrFail()->id;
        $this->put(route('seller.products.update', $product->id), $payload)->assertSessionHasErrors('subcategory');
        $this->shop->update(['status' => 'pending']);
        $url = route('seller.products.edit', $product->id);
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->app->instance('env', $environment);
            $this->get($url)->assertForbidden();
        }
        $this->app->instance('env', 'local');
        $this->get($url)->assertOk();
        $this->put(route('seller.products.update', $product->id), $this->editPayload($product))->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->shop->fresh()->status);
        auth()->user()->update(['is_suspended' => true]);
        $this->get($url)->assertForbidden();
    }
}
