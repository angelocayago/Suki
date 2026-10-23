<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SellerProductSkuTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
        $this->shop = $this->shop('first');
        $this->actingAs($this->shop->owner);
    }

    private function shop(string $slug): Seller
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return Seller::create(['user_id' => $owner->id, 'name' => 'SKU fixture', 'slug' => $slug,
            'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('product.png', file_get_contents(public_path('images/logo.png')));
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'SKU product', 'description' => 'Product details',
            'subcategory' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'price' => 149, 'stock' => 7, 'status' => 'active', 'low_stock_threshold' => 4], $extra);
    }

    private function createProduct(array $extra = []): Product
    {
        $this->post(route('seller.products.store'), $this->payload(['images' => [$this->image()]] + $extra))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return $this->shop->products()->latest('id')->firstOrFail();
    }

    public function test_creation_ignores_submitted_sku_and_persists_one_default_variant(): void
    {
        $product = $this->createProduct(['sku' => ['malicious'], 'seller_id' => 9999, 'id' => 9999]);
        $variant = $product->variants->sole();
        $expected = 'S'.$this->shop->id.'-P'.$product->id;
        $this->assertSame($expected, $variant->sku);
        $this->assertSame($expected, SellerProducts::listing($product)['sku']);
        $this->assertSame($this->shop->id, $product->seller_id);
        $this->assertArrayNotHasKey('sku', $product->seller_listing_data);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 1);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('seller.products'))->assertOk()->assertSeeText($expected);
        }
        $this->get(route('seller.inventory'))->assertOk()->assertSeeText($expected);
        $owner = $this->shop->owner;
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('seller.products'))->assertOk()->assertSeeText($expected);
    }

    public function test_multiple_products_and_sellers_have_distinct_automatically_assigned_skus(): void
    {
        $first = $this->createProduct();
        $second = $this->createProduct();
        $other = $this->shop('second');
        $this->actingAs($other->owner);
        $this->post(route('seller.products.store'), $this->payload(['images' => [$this->image()]]))->assertSessionHasNoErrors();
        $skus = ProductVariant::pluck('sku');
        $this->assertCount(3, $skus->unique());
        $this->assertNotSame($first->variants->sole()->sku, $second->variants->sole()->sku);
        $this->assertSame('S'.$other->id.'-P'.$other->products->sole()->id, $other->products->sole()->variants->sole()->sku);
        $this->assertDatabaseCount('product_variants', 3);
    }

    public function test_draft_gets_a_persisted_sku_that_survives_completion(): void
    {
        $this->post(route('seller.products.store'), ['submit_action' => 'draft'])->assertSessionHasNoErrors();
        $product = Product::sole();
        $variant = $product->variants->sole();
        $this->assertSame('S'.$this->shop->id.'-P'.$product->id, $variant->sku);
        $this->put(route('seller.products.update', $product->id), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($variant->sku, $variant->fresh()->sku);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_edits_and_individual_image_replacement_preserve_sku_and_variant_identity(): void
    {
        $product = $this->createProduct();
        $variant = $product->variants->sole();
        $children = SellerCategories::children($this->shop, SellerCategories::main($this->shop));
        $this->put(route('seller.products.update', $product->id), $this->payload([
            'name' => 'New name', 'subcategory' => $children[1]->id, 'description' => 'New description',
            'price' => 200, 'on_sale' => 1, 'sale_price' => 175, 'stock' => 12, 'status' => 'inactive',
            'sku' => 'ATTEMPTED-CHANGE', 'seller_id' => 9999,
            'replacement_images' => [$product->images->sole()->id => $this->image()],
        ]))->assertSessionHasNoErrors();
        $fresh = $product->fresh();
        $saved = $fresh->variants->sole();
        $this->assertSame($variant->id, $saved->id);
        $this->assertSame($variant->sku, $saved->sku);
        $this->assertSame(17500, $saved->price_minor);
        $this->assertSame(12, $saved->stock);
        $this->assertSame(4, $fresh->seller_listing_data['low_stock_threshold']);
        $this->assertNotSame($product->images->sole()->path, $fresh->images->sole()->path);
        $this->assertSame($children[1]->id, $fresh->category_id);
        $this->put(route('seller.products.update', $product->id), $this->payload(['sku' => ['invalid']]))->assertSessionHasNoErrors();
        $this->assertSame($variant->sku, $saved->fresh()->sku);
        $this->assertNull($product->fresh()->seller_listing_data['sale_price']);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_legacy_skus_remain_saved_displayed_and_searchable_in_both_layouts(): void
    {
        foreach (['001', '005', 'SP-P3'] as $legacy) {
            $product = $this->createProduct();
            $variant = $product->variants->sole();
            $variant->update(['sku' => $legacy]);
            $this->put(route('seller.products.update', $product->id), $this->payload(['sku' => 'OVERWRITE']))->assertSessionHasNoErrors();
            $this->assertSame($legacy, $variant->fresh()->sku);
            $this->get(route('seller.products'))->assertOk()->assertSeeText($legacy)
                ->assertViewHas('products', fn ($rows) => collect($rows)->contains('sku', $legacy));
            $this->get(route('seller.products', ['search' => $legacy]))->assertOk()->assertSeeText($legacy)
                ->assertViewHas('paginator', fn ($paginator) => $paginator->total() === 1);
            $this->get(route('seller.inventory'))->assertOk()->assertSeeText($legacy);
        }
        $this->assertDatabaseCount('product_variants', 3);
    }

    public function test_global_unique_index_handles_legacy_and_soft_deleted_collisions(): void
    {
        $legacy = $this->createProduct();
        $collision = 'S'.$this->shop->id.'-P'.($legacy->id + 1);
        $variant = $legacy->variants->sole();
        $variant->update(['sku' => $collision]);
        $variant->delete();
        $product = $this->createProduct();
        $sku = $product->variants->sole()->sku;
        $this->assertMatchesRegularExpression('/^'.preg_quote($collision, '/').'\-[A-Z0-9]{8}$/', $sku);
        $this->assertSame($collision, ProductVariant::withTrashed()->findOrFail($variant->id)->sku);
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_collision_retry_is_bounded_and_failure_rolls_back_product_variant_and_upload(): void
    {
        $legacy = $this->createProduct();
        $base = 'S'.$this->shop->id.'-P'.($legacy->id + 1);
        $legacy->variants->sole()->update(['sku' => $base]);
        $legacy->variants()->create(['sku' => $base.'-AAAAAAAA', 'name' => 'Reserved legacy', 'price_minor' => 1, 'stock' => 0]);
        $files = Storage::disk('public')->allFiles();
        $attempts = 0;
        $dispatcher = ProductVariant::getEventDispatcher();
        ProductVariant::setEventDispatcher(clone $dispatcher);
        ProductVariant::creating(function () use (&$attempts) {
            $attempts++;
        });
        Str::createRandomStringsUsing(fn () => 'AAAAAAAA');
        try {
            $this->post(route('seller.products.store'), $this->payload(['images' => [$this->image()]]))
                ->assertSessionHasErrors('sku')->assertSessionMissing('success');
        } finally {
            Str::createRandomStringsNormally();
            ProductVariant::setEventDispatcher($dispatcher);
        }
        $this->assertSame(5, $attempts);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_sku_ui_shows_only_assigned_values_and_failed_forms_do_not_echo_a_fabricated_sku(): void
    {
        $this->get(route('seller.products.create'))->assertOk()->assertSeeText('Generated automatically after saving')
            ->assertDontSee('name="sku"', false)->assertDontSee('SKU-0001');
        $product = $this->createProduct();
        $this->from(route('seller.products.edit', $product->id))->put(route('seller.products.update', $product->id),
            $this->payload(['name' => '', 'sku' => 'FORGED-SKU']))->assertSessionHasErrors('name');
        $response = $this->get(route('seller.products.edit', $product->id))->assertOk()->assertDontSee('FORGED-SKU');
        $this->assertMatchesRegularExpression('/id="sku"\s+value="'.preg_quote($product->variants->sole()->sku, '/').'"\s+readonly/', $response->getContent());
        $response->assertDontSee('name="sku"', false);
        $this->post(route('seller.products.store'), $this->payload(['name' => '', 'sku' => 'FORGED-SKU']))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_foreign_products_and_categories_remain_protected(): void
    {
        $product = $this->createProduct();
        $before = $product->fresh(['variants', 'images'])->toArray();
        $other = $this->shop('other');
        $other->update(['business_category' => "Women's Apparel"]);
        $foreignCategory = SellerCategories::children($other, SellerCategories::main($other))->first()->id;
        $this->put(route('seller.products.update', $product->id), $this->payload(['subcategory' => $foreignCategory]))->assertSessionHasErrors('subcategory');
        $this->actingAs($other->owner)->put(route('seller.products.update', $product->id), $this->payload(['sku' => 'HACK']))->assertNotFound();
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
    }

    public function test_product_with_missing_active_variant_is_reported_instead_of_silently_receiving_another_sku(): void
    {
        $product = $this->createProduct();
        $product->variants->sole()->delete();
        $this->put(route('seller.products.update', $product->id), $this->payload())->assertSessionHasErrors('sku');
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertCount(0, $product->fresh()->variants);
    }

    public static function allocationModes(): array
    {
        return ['default' => [false], 'additional variant' => [true]];
    }

    #[DataProvider('allocationModes')]
    public function test_simultaneous_processes_allocate_distinct_skus_on_an_isolated_database(bool $additional): void
    {
        $directory = sys_get_temp_dir().'/suki-sku-race-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $database = $directory.'/sku.sqlite';
        $gate = $directory.'/start';
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER NOT NULL)');
        $pdo->exec('CREATE TABLE product_variants (id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, sku VARCHAR(255) NOT NULL UNIQUE, name TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        $processes = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/Fixtures/create-sku-concurrent.php'), $database, $gate, $directory.'/ready-'.$i, $additional ? 'additional' : 'default'], base_path());
                $process->setTimeout(20)->start();
                $processes[] = $process;
            }
            foreach ($processes as $index => $process) {
                $ready = $directory.'/ready-'.$index;
                $deadline = microtime(true) + 10;
                while (! is_file($ready) && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                    clearstatcache(true, $ready);
                }
                $this->assertFileExists($ready, $process->getErrorOutput().$process->getOutput());
            }
            file_put_contents($gate, 'start');
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
            }
            $skus = $pdo->query('SELECT sku FROM product_variants ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
            if ($additional) {
                $this->assertCount(2, array_unique($skus));
                foreach ($skus as $sku) {
                    $this->assertMatchesRegularExpression('/^S123-P[12]-V[A-Z0-9]{10}$/', $sku);
                }
            } else {
                $this->assertSame(['S123-P1', 'S123-P2'], $skus);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            $pdo = null;
            foreach ([$gate, $database, $database.'-journal', $directory.'/ready-0', $directory.'/ready-1'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }
}
