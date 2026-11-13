<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PresentationImportRecord;
use App\Models\Product;
use App\Models\TransactionReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresentationMarketplaceDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $root = Category::create(['name' => 'Home and Living', 'slug' => 'home-living', 'is_active' => true, 'position' => 1]);
        Category::create(['name' => 'Kitchen and Storage', 'slug' => 'kitchen-storage', 'parent_id' => $root->id, 'is_active' => true, 'position' => 1]);
    }

    private function commandOptions(bool $execute = false, array $extra = []): array
    {
        return array_replace([
            '--connection' => 'sqlite', '--expect-environment' => 'testing', '--confirm-database' => ':memory:',
        ], $execute ? ['--execute' => true, '--confirm-execution' => 'GENERATE FICTIONAL PRESENTATION DATA'] : [], $extra);
    }

    public function test_dry_run_has_no_writes(): void
    {
        $this->artisan('presentation:marketplace-data', $this->commandOptions())
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('presentation_import_records', 0);
    }

    public function test_shared_and_production_targets_are_refused_before_writes(): void
    {
        config(['database.connections.refused' => ['driver' => 'pgsql', 'host' => 'must-never-connect.invalid', 'database' => 'forbidden']]);
        $this->artisan('presentation:marketplace-data', $this->commandOptions(true, ['--connection' => 'refused']))->assertFailed();
        $this->app['env'] = 'production';
        try {
            $this->artisan('presentation:marketplace-data', $this->commandOptions(true, ['--expect-environment' => 'production']))->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('presentation_import_records', 0);
    }

    public function test_execute_is_idempotent_and_creates_publishable_fictional_catalog_data(): void
    {
        $this->artisan('presentation:marketplace-data', $this->commandOptions(true, ['--sellers' => '2', '--products-per-seller' => '2', '--without-history' => true]))->assertSuccessful();
        $before = PresentationImportRecord::count();
        $this->artisan('presentation:marketplace-data', $this->commandOptions(true, ['--sellers' => '2', '--products-per-seller' => '2', '--without-history' => true]))->assertSuccessful();
        $this->assertSame($before, PresentationImportRecord::count());
        $this->assertCount(4, Product::where('legacy_reference', null)->get());
        $this->assertSame(0, Product::query()->whereRaw("LOWER(name) LIKE '%demo%' OR LOWER(name) LIKE '%sample%' OR LOWER(name) LIKE '%test%'")->count());
        $this->assertTrue(Product::query()->with('images')->get()->every(fn (Product $product) => $product->images->isNotEmpty()));
        $this->assertSame(0, Product::query()->whereHas('variants', fn ($variants) => $variants->where('stock', '<', 0))->count());
        $this->assertGreaterThanOrEqual(2, User::where('role', 'seller')->count());
        $this->assertDatabaseCount('transaction_reviews', 0);
    }

    public function test_history_generation_uses_workflow_states_and_never_creates_orphans(): void
    {
        $this->artisan('presentation:marketplace-data', $this->commandOptions(true, ['--sellers' => '3', '--products-per-seller' => '1']))->assertSuccessful();
        $this->assertSame(3, \App\Models\Order::count());
        $this->assertSame(0, \App\Models\Shipment::whereDoesntHave('sellerOrder')->count());
        $this->assertSame(0, \App\Models\OrderItem::whereDoesntHave('order')->count());
        $this->assertSame(1, \App\Models\SellerOrder::where('workflow_status', 'READY_FOR_PICKUP')->count());
        $this->assertSame(1, \App\Models\SellerOrder::where('workflow_status', 'PICKED_UP')->count());
        $this->assertSame(1, \App\Models\SellerOrder::where('workflow_status', 'COMPLETED')->count());
        $this->assertSame(1, TransactionReview::count());
        $reviewed = TransactionReview::sole();
        $card = app(\App\Services\ProductCatalogService::class)->marketplaceProduct(Product::findOrFail($reviewed->product_id)->slug);
        $this->assertSame(5.0, $card['rating']);
        $this->assertSame(1, $card['ratings']);
    }
}
