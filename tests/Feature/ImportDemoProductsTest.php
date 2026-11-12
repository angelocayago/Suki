<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\SellerCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ImportDemoProductsTest extends TestCase
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
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Http::preventStrayRequests(); Mail::fake(); Notification::fake();
        $owner = User::factory()->create(['id' => 41, 'role' => 'seller', 'status' => 'active', 'is_suspended' => false, 'verification_required_at' => now(), 'email_verified_at' => now()]);
        $root = Category::forceCreate(['id' => 17, 'name' => 'Others', 'slug' => 'others', 'is_active' => true]);
        $this->category = Category::forceCreate(['id' => 29, 'parent_id' => $root->id, 'name' => 'Miscellaneous', 'slug' => 'others--miscellaneous', 'is_active' => true]);
        $this->seller = Seller::forceCreate(['id' => 3, 'user_id' => $owner->id, 'name' => 'Lahat Meron', 'slug' => 'synthetic-lahat-meron', 'category_id' => $root->id, 'status' => 'approved']);
    }

    private function importOptions(bool $execute = false, array $override = []): array
    {
        return array_merge([
            '--seller-id' => '3', '--expect-store' => 'Lahat Meron', '--connection' => 'sqlite',
            '--expect-environment' => 'testing', '--confirm-database' => ':memory:',
        ], $execute ? ['--execute' => true, '--expect-owner-id' => '41'] : [], $override);
    }

    public function test_default_preview_has_no_persistence_side_effects(): void
    {
        $this->artisan('demo:products:import', $this->importOptions())->expectsOutputToContain('DRY RUN')->expectsOutputToContain('owner users.id=41')->expectsOutputToContain('category_id=29')->assertSuccessful();
        foreach (['products', 'product_variants', 'product_images', 'product_moderation_events', 'notification_outbox'] as $table) $this->assertDatabaseCount($table, 0);
        $this->assertSame('approved', $this->seller->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_import_creates_exact_distribution_and_reuses_catalog_inventory_and_pending_moderation(): void
    {
        $this->artisan('demo:products:import', $this->importOptions(true))->expectsOutputToContain('COMMITTED: 60 products inserted; 0 existing demo products skipped.')->assertSuccessful();
        $this->assertDatabaseCount('products', 60);
        $this->assertDatabaseCount('product_variants', 102);
        $this->assertDatabaseCount('product_moderation_events', 60);
        $this->assertDatabaseCount('product_images', 0);
        $this->assertSame([3], Product::distinct()->pluck('seller_id')->all());
        $this->assertSame([29], Product::distinct()->pluck('category_id')->all());
        $this->assertTrue(app(SellerCategoryService::class)->allows($this->seller, 29));
        $this->assertSame([36, 12, 6, 6], array_values(Product::withCount('variants')->get()->countBy('variants_count')->sortKeys()->all()));
        $items = json_decode(file_get_contents(database_path('demo-products/lahat-meron-v1.json')), true);
        foreach ($items as $item) {
            $product = $this->seller->products()->where('legacy_reference', 'suki-demo:'.$item['key'])->with('variants')->firstOrFail();
            $this->assertSame(ProductModerationStatus::Pending, $product->moderation_status);
            $this->assertTrue($product->is_active);
            foreach ($product->variants->sortBy('id')->values() as $i => $variant) {
                $expected = $item['variants'][$i];
                $this->assertSame($expected['price_minor'], $variant->price_minor);
                $this->assertSame($expected['stock'], $variant->stock);
                $this->assertSame($expected['options'], $variant->options ?? []);
                $this->assertSame('DEMO-LM-'.strtoupper($item['key']).'-'.($i + 1), $variant->sku);
                $this->assertTrue($variant->is_active);
                if (count($item['variants']) === 1) $this->assertSame('Default', $variant->name);
                else $this->assertSame(implode(' / ', array_values($expected['options'])), $variant->name);
            }
            $sellerItem = app(ProductCatalogService::class)->sellerItem($product->load('images', 'category'));
            $this->assertSame($item['variants'][0]['stock'], $sellerItem['stock']);
            $this->assertEquals($item['variants'][0]['price_minor'] / 100, $sellerItem['price']);
        }
        // Pending products remain hidden, without changing the approval policy.
        $this->assertSame(0, app(ProductCatalogService::class)->marketplacePage([])->total());
        // Synthetic approval here only tests existing catalog compatibility, not importer behavior.
        $product = Product::firstOrFail();
        $product->update(['moderation_status' => ProductModerationStatus::Approved]);
        $page = app(ProductCatalogService::class)->marketplacePage(['seller' => $this->seller->slug, 'categories' => [29], 'sort' => 'price_asc', 'in_stock' => '1']);
        $this->assertSame(1, $page->total());
        $this->assertSame($product->id, $page->items()[0]['id']);
        $this->assertSame('approved', $this->seller->fresh()->status);
        $this->assertSame('active', $this->seller->owner->fresh()->status);
    }

    public function test_rerun_preserves_existing_demo_edits_and_archived_rows(): void
    {
        $this->artisan('demo:products:import', $this->importOptions(true))->assertSuccessful();
        $product = Product::firstOrFail(); $variant = $product->variants()->firstOrFail();
        $variant->update(['stock' => 2, 'price_minor' => 12345]);
        $product->update(['name' => 'Seller edited demo']); $product->delete();
        $before = DB::table('products')->orderBy('id')->get()->toJson();
        $variantBefore = DB::table('product_variants')->orderBy('id')->get()->toJson();
        $this->artisan('demo:products:import', $this->importOptions(true))->expectsOutputToContain('COMMITTED: 0 products inserted; 60 existing demo products skipped.')->assertSuccessful();
        $this->assertSame($before, DB::table('products')->orderBy('id')->get()->toJson());
        $this->assertSame($variantBefore, DB::table('product_variants')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('product_moderation_events', 60);
    }

    public function test_foreign_seller_and_non_demo_records_are_never_changed(): void
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $foreign = Seller::forceCreate(['user_id' => $owner->id, 'name' => 'Another Store', 'slug' => 'another-store', 'category_id' => 17, 'status' => 'approved']);
        foreach ([$this->seller, $foreign] as $seller) {
            $product = Product::create(['seller_id' => $seller->id, 'category_id' => 29, 'name' => 'Existing real item', 'slug' => 'real-'.$seller->id, 'is_active' => true]);
            $product->variants()->create(['name' => 'Default', 'sku' => 'REAL-'.$seller->id, 'stock' => 7, 'price_minor' => 12300, 'is_active' => true]);
        }
        $before = DB::table('products')->orderBy('id')->get()->toArray();
        $variants = DB::table('product_variants')->orderBy('id')->get()->toArray();
        $this->artisan('demo:products:import', $this->importOptions(true))->assertSuccessful();
        foreach ($before as $row) $this->assertEquals($row, DB::table('products')->find($row->id));
        foreach ($variants as $row) $this->assertEquals($row, DB::table('product_variants')->find($row->id));
        $this->assertSame(1, $foreign->products()->count());
        $this->assertSame(61, $this->seller->products()->count());
    }

    public function test_preflight_refuses_non_demo_sku_and_reference_collisions(): void
    {
        $product = Product::create(['seller_id' => 3, 'category_id' => 29, 'name' => 'Existing item', 'slug' => 'existing-item', 'legacy_reference' => 'suki-demo:lm001']);
        $this->artisan('demo:products:import', $this->importOptions(true))->expectsOutputToContain('conflicts with demo provenance')->assertFailed();
        $product->update(['legacy_reference' => null]);
        $product->variants()->create(['sku' => 'DEMO-LM-LM001-1', 'name' => 'Default', 'price_minor' => 100, 'stock' => 2]);
        $this->artisan('demo:products:import', $this->importOptions(true))->expectsOutputToContain('SKU is already assigned')->assertFailed();
        $this->assertDatabaseCount('products', 1); $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_mid_batch_failure_rolls_back_every_created_row_and_moderation_intent(): void
    {
        User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false]);
        $real = app(ProductCatalogService::class); $calls = 0;
        $this->mock(ProductCatalogService::class)->shouldReceive('create')->andReturnUsing(function ($seller, $data) use ($real, &$calls) {
            if (++$calls === 3) throw new \RuntimeException('Synthetic private failure detail');
            return $real->create($seller, $data);
        });
        $this->artisan('demo:products:import', $this->importOptions(true))->expectsOutputToContain('Import failed.')->assertFailed();
        $this->assertSame(3, $calls);
        foreach (['products', 'product_variants', 'product_moderation_events', 'notification_outbox'] as $table) $this->assertDatabaseCount($table, 0);
    }

    public function test_wrong_target_owner_and_database_confirmations_are_refused(): void
    {
        foreach ([['--seller-id' => '41'], ['--seller-id' => '4'], ['--expect-store' => 'Other Store'], ['--expect-owner-id' => '3'], ['--expect-environment' => 'local'], ['--confirm-database' => '/wrong/database'], ['--connection' => 'missing']] as $override) {
            $this->artisan('demo:products:import', $this->importOptions(true, $override))->assertFailed();
            $this->assertDatabaseCount('products', 0);
        }
        $options = $this->importOptions(true); unset($options['--expect-owner-id']);
        $this->artisan('demo:products:import', $options)->expectsOutputToContain('requires --expect-owner-id')->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_remote_drivers_urls_and_production_are_rejected_before_queries(): void
    {
        config(['database.connections.refused' => ['driver' => 'pgsql', 'host' => 'must-never-connect.invalid', 'database' => 'forbidden']]);
        DB::connection()->enableQueryLog(); DB::connection()->flushQueryLog();
        $this->artisan('demo:products:import', $this->importOptions(true, ['--connection' => 'refused']))->expectsOutputToContain('PostgreSQL/Supabase')->assertFailed();
        $this->assertSame([], DB::connection()->getQueryLog());
        config(['database.connections.url_connection' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => 'postgres://must-never-connect.invalid/forbidden']]);
        $this->artisan('demo:products:import', $this->importOptions(true, ['--connection' => 'url_connection']))->assertFailed();
        $this->assertSame([], DB::connection()->getQueryLog());
        $this->app['env'] = 'production';
        try { $this->artisan('demo:products:import', $this->importOptions(true, ['--expect-environment' => 'production']))->assertFailed(); }
        finally { $this->app['env'] = 'testing'; }
        $this->assertSame([], DB::connection()->getQueryLog());
        $this->assertSame('sqlite', DB::getDefaultConnection());
    }

    public function test_seller_and_owner_eligibility_are_rechecked(): void
    {
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $this->seller->update(['status' => $status]);
            $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        }
        $this->seller->update(['status' => 'approved', 'name' => 'Renamed Store']);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $this->seller->update(['name' => 'Lahat Meron']);
        foreach ([['status' => 'pending'], ['is_suspended' => true], ['email_verified_at' => null], ['role' => 'buyer']] as $changes) {
            $this->seller->owner->update(array_merge(['role' => 'seller', 'status' => 'active', 'is_suspended' => false, 'email_verified_at' => now()], $changes));
            $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_missing_inactive_ambiguous_or_foreign_categories_fail(): void
    {
        $root = $this->seller->category;
        $root->update(['name' => 'Home']);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $root->update(['name' => 'Others', 'is_active' => false]);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $root->update(['is_active' => true]); $this->category->update(['is_active' => false]);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $this->category->update(['is_active' => true]);
        $duplicate = Category::forceCreate(['name' => 'Miscellaneous', 'slug' => 'ambiguous', 'parent_id' => 17, 'is_active' => true]);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $duplicate->delete(); $this->category->update(['parent_id' => null]);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_a_missing_target_seller_or_owner_is_refused(): void
    {
        $this->seller->delete();
        $this->artisan('demo:products:import', $this->importOptions(true))->assertFailed();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_existing_product_moderation_notification_intents_are_preserved_once(): void
    {
        User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false]);
        $this->artisan('demo:products:import', $this->importOptions())->assertSuccessful();
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertSuccessful();
        $this->assertDatabaseCount('notification_outbox', 60);
        $this->artisan('demo:products:import', $this->importOptions(true))->assertSuccessful();
        $this->assertDatabaseCount('notification_outbox', 60);
    }
}
