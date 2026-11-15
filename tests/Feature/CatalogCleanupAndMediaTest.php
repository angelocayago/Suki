<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\CatalogCleanupAudit;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\Admin\ProductModerationService;
use App\Support\DemoProductBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogCleanupAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private Seller $seller;
    private Category $category;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Disposable SQLite memory bootstrap required.');
        }
        parent::setUp();
        Storage::fake('public');
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $root = Category::create(['name' => 'Home', 'slug' => 'home', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Home goods', 'slug' => 'home-goods', 'parent_id' => $root->id, 'is_active' => true]);
        $this->seller = Seller::create(['user_id' => $owner->id, 'name' => 'Catalog Store', 'slug' => 'catalog-store', 'category_id' => $root->id, 'status' => 'approved']);
    }

    public function test_public_catalog_excludes_missing_or_unsafe_media_and_never_generates_a_placeholder(): void
    {
        $missing = $this->product('Missing image');
        $missing->images()->create(['path' => 'products/missing.webp', 'position' => 1]);
        $unsafe = $this->product('Unsafe image');
        $unsafe->images()->create(['path' => 'https://untrusted.example.invalid/product.webp', 'position' => 1]);
        $valid = $this->product('Valid image');
        Storage::disk('public')->put('products/valid.webp', 'valid image fixture');
        $valid->images()->create(['path' => 'products/valid.webp', 'position' => 1]);

        $items = app(ProductCatalogService::class)->marketplaceProducts();

        $this->assertArrayHasKey((string) $valid->slug, $items);
        $this->assertArrayNotHasKey((string) $missing->slug, $items);
        $this->assertArrayNotHasKey((string) $unsafe->slug, $items);
        $this->assertStringNotContainsString('placehold.co', $items[(string) $valid->slug]['image']);
        $this->assertNull(app(ProductCatalogService::class)->marketplaceProduct((string) $missing->slug));
    }

    public function test_demo_cleanup_is_dry_run_first_then_normalizes_known_provenance_idempotently(): void
    {
        $row = collect(app(DemoProductBatch::class)->dataset($this->category->id))->firstWhere('reference', 'suki-demo:lm001');
        $product = $this->product('Demo '.$row['data']['name'], [
            'legacy_reference' => $row['reference'],
            'description' => '[SUKI-DEMO:lm001] '.$row['data']['description'].' Sample catalog entry for demonstrations.',
        ]);
        Storage::disk('public')->put('products/demo-cleanup.webp', 'valid image fixture');
        $product->images()->create(['path' => 'products/demo-cleanup.webp', 'position' => 1]);

        $this->artisan('catalog:cleanup-demo-content')
            ->expectsOutputToContain('DRY RUN: no products')
            ->assertSuccessful();
        $this->assertSame('Demo '.$row['data']['name'], $product->fresh()->name);
        $this->assertDatabaseCount('catalog_cleanup_audits', 0);

        $this->artisan('catalog:cleanup-demo-content', ['--apply' => true])->assertSuccessful();
        $product->refresh();
        $this->assertSame($row['data']['name'], $product->name);
        $this->assertSame($row['data']['description'], $product->description);
        $this->assertTrue($product->is_active);
        $this->assertDatabaseCount('catalog_cleanup_audits', 1);
        $this->assertSame('normalize', CatalogCleanupAudit::sole()->action);

        $this->artisan('catalog:cleanup-demo-content', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('catalog_cleanup_audits', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'legacy_reference' => 'suki-demo:lm001']);
    }

    public function test_cleanup_unpublishes_known_demo_provenance_without_deleting_the_historical_product(): void
    {
        $product = $this->product('Demo unavailable item', ['legacy_reference' => 'suki-demo:lm002']);
        $product->images()->create(['path' => 'products/does-not-exist.webp', 'position' => 1]);

        $this->artisan('catalog:cleanup-demo-content', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false, 'legacy_reference' => 'suki-demo:lm002']);
        $this->assertDatabaseHas('catalog_cleanup_audits', ['product_id' => $product->id, 'action' => 'unpublish']);
    }

    public function test_retained_dataset_keeps_demo_provenance_out_of_customer_facing_text(): void
    {
        foreach (app(DemoProductBatch::class)->dataset($this->category->id) as $row) {
            $this->assertStringStartsWith('suki-demo:', $row['reference']);
            $this->assertDoesNotMatchRegularExpression('/\b(?:demo|sample|test|lorem\s+ipsum)\b/i', $row['data']['name']);
            $this->assertDoesNotMatchRegularExpression('/\b(?:demo|sample|test|lorem\s+ipsum)\b/i', $row['data']['description']);
        }
    }

    public function test_moderation_refuses_publication_until_a_usable_local_image_exists(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false]);
        $product = $this->product('Needs image', ['moderation_status' => ProductModerationStatus::Pending->value]);

        try {
            app(ProductModerationService::class)->approve($product, $admin);
            $this->fail('Approval without product media was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('images', $exception->errors());
        }

        Storage::disk('public')->put('products/approval.webp', 'valid image fixture');
        $product->images()->create(['path' => 'products/approval.webp', 'position' => 1]);
        app(ProductModerationService::class)->approve($product->fresh(), $admin);
        $this->assertSame(ProductModerationStatus::Approved, $product->fresh()->moderation_status);
    }

    private function product(string $name, array $attributes = []): Product
    {
        $product = Product::create($attributes + [
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.Product::count()),
            'description' => 'Customer-facing description.',
            'is_active' => true,
            'moderation_status' => ProductModerationStatus::Approved->value,
        ]);
        $product->variants()->create(['sku' => 'CAT-'.$product->id, 'name' => 'Default', 'price_minor' => 10000, 'stock' => 3, 'is_active' => true]);
        return $product;
    }
}
