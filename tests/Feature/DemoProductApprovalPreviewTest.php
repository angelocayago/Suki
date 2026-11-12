<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\{Category, Product, Seller, User};
use App\Services\{ProductCatalogService, SellerCategoryService};
use App\Support\{DemoProductBatch, DemoProductImages};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Tests\TestCase;

class DemoProductApprovalPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Seller $seller;
    private array $rows;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated SQLite bootstrap required.');
        }
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
        Storage::fake('public');
        $owner = User::factory()->create(['id' => 41, 'role' => 'seller', 'status' => 'active',
            'is_suspended' => false, 'email_verified_at' => now(), 'verification_required_at' => now()]);
        Category::forceCreate(['id' => 17, 'name' => 'Others', 'slug' => 'others', 'is_active' => true]);
        Category::forceCreate(['id' => 29, 'parent_id' => 17, 'name' => 'Miscellaneous', 'slug' => 'miscellaneous', 'is_active' => true]);
        $this->seller = Seller::forceCreate(['id' => 3, 'user_id' => $owner->id, 'name' => 'Lahat Meron',
            'slug' => 'lahat-meron', 'category_id' => 17, 'status' => 'approved']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'is_suspended' => false,
            'email_verified_at' => now(), 'verification_required_at' => now()]);
        $this->rows = app(DemoProductBatch::class)->dataset(29);
        // Synthetic SQLite fixtures use existing catalog creation, never the importer.
        foreach ($this->rows as $row) {
            $product = app(ProductCatalogService::class)->create($this->seller, $row['data']);
            $product->images()->create(['path' => DemoProductImages::path($row['key']), 'position' => 1]);
            $product->update(['legacy_reference' => $row['reference']]);
        }
    }

    private function snapshot(): array
    {
        return [
            DB::table('products')->orderBy('id')->get()->toJson(),
            DB::table('product_variants')->orderBy('id')->get()->toJson(),
            DB::table('product_images')->orderBy('id')->get()->toJson(),
            DB::table('product_moderation_events')->orderBy('id')->get()->toJson(),
            DB::table('notification_outbox')->orderBy('id')->get()->toJson(),
        ];
    }

    private function preview()
    {
        return $this->actingAs($this->admin)->get(route('admin.products.demo.preview'));
    }

    public function test_complete_preview_renders_without_writes_or_per_product_queries_and_keeps_the_hash_contract(): void
    {
        $before = $this->snapshot();
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $response = $this->preview()->assertOk()->assertSee('60 pending approval')
            ->assertSee('102 variants')->assertSee('APPROVE LAHAT MERON DEMO');
        $httpQueries = $queries;
        $this->assertLessThanOrEqual(30, count($httpQueries), 'The whole synthetic GET must have a bounded query count.');
        foreach ($this->rows as $row) {
            $response->assertSee($row['reference'])->assertSee($row['data']['name']);
        }
        $plan = $response->viewData('plan');
        $this->assertNull($response->viewData('issue'));
        $this->assertSame([3], $plan['products']->pluck('seller_id')->unique()->values()->all());
        $this->assertSame([29], $plan['products']->pluck('category_id')->unique()->values()->all());
        $this->assertSame(102, $plan['products']->sum(fn ($p) => $p->variants->count()));
        $this->assertSame(60, $plan['products']->sum(fn ($p) => $p->images->count()));
        $this->assertSame(0, $plan['approved']);

        // Preserve the original review-hash input contract, independent of SQL batching.
        $identity = [];
        foreach ($this->rows as $row) {
            $product = $plan['products']->get($row['reference']);
            $identity[] = [$row['key'], $product->id, $product->images->first()->path];
        }
        $expected = hash('sha256', json_encode([$this->seller->user_id, 29, $identity,
            hash_file('sha256', database_path('demo-products/lahat-meron-v1.json')),
            hash_file('sha256', database_path('demo-products/images/manifest.json'))], JSON_THROW_ON_ERROR));
        $this->assertSame($expected, $plan['hash']);

        $queries = [];
        $again = app(DemoProductBatch::class)->approvalPlan(app(SellerCategoryService::class));
        $coreQueries = $queries;
        $this->assertLessThanOrEqual(16, count($coreQueries), 'Validation must batch the fixed 60 products.');
        $this->assertSame($expected, $again['hash']);
        $locked = DB::transaction(fn () => app(DemoProductBatch::class)->approvalPlan(app(SellerCategoryService::class), true));
        $this->assertSame($expected, $locked['hash']);
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseCount('product_moderation_events', 60);
        $this->assertSame(0, Product::where('moderation_status', ProductModerationStatus::Approved)->count());
        fwrite(STDOUT, 'Preview query counts: isolated HTTP='.count($httpQueries).'; core='.count($coreQueries).PHP_EOL);
    }

    public function test_preview_remains_restricted_to_eligible_admins(): void
    {
        $before = $this->snapshot();
        $this->get(route('admin.products.demo.preview'))->assertRedirect(route('login'));
        foreach (['buyer', 'seller'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
            $this->actingAs($user)->get(route('admin.products.demo.preview'))->assertForbidden();
        }
        $this->admin->update(['is_suspended' => true]);
        $this->preview()->assertForbidden();
        $this->admin->update(['is_suspended' => false, 'email_verified_at' => null]);
        $this->preview()->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_foreign_sellers_product_with_the_same_reference_is_excluded_and_untouched(): void
    {
        $other = Seller::forceCreate(['user_id' => $this->seller->user_id, 'name' => 'Foreign Store',
            'slug' => 'foreign-store', 'category_id' => 17, 'status' => 'approved']);
        $foreign = Product::create(['seller_id' => $other->id, 'category_id' => 29, 'name' => 'Foreign product',
            'slug' => 'foreign-product', 'legacy_reference' => $this->rows[0]['reference'], 'moderation_status' => 'PENDING']);
        $before = $this->snapshot();
        $response = $this->preview()->assertOk()->assertDontSee('Foreign product');
        $this->assertSame(60, $response->viewData('plan')['products']->count());
        $this->assertFalse($response->viewData('plan')['products']->contains('id', $foreign->id));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_modified_products_prices_options_and_stocks_block_preview_without_writes(): void
    {
        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        foreach ([[$product, 'name', 'Modified demo'], [$variant, 'price_minor', $variant->price_minor + 1],
            [$variant, 'stock', $variant->stock + 1], [$variant, 'options', ['Unexpected' => 'Choice']]] as [$model, $key, $value]) {
            $original = $model->{$key};
            $model->update([$key => $value]);
            $before = $this->snapshot();
            $response = $this->preview()->assertOk()->assertDontSee('id="demo-approval-submit"', false);
            $this->assertNull($response->viewData('plan'));
            $this->assertNotEmpty($response->viewData('issue'));
            $this->assertSame($before, $this->snapshot());
            $model->update([$key => $original]);
        }
    }

    public function test_global_sku_ownership_and_deleted_variants_are_still_checked(): void
    {
        $product = Product::firstOrFail();
        $variant = $product->variants()->firstOrFail();
        $other = Seller::forceCreate(['user_id' => $this->seller->user_id, 'name' => 'Other', 'slug' => 'other',
            'category_id' => 17, 'status' => 'approved']);
        $foreign = Product::create(['seller_id' => $other->id, 'category_id' => 29, 'name' => 'Foreign', 'slug' => 'foreign']);
        $variant->update(['product_id' => $foreign->id]);
        $before = $this->snapshot();
        $response = $this->preview()->assertOk()->assertSee('conflicts with demo provenance');
        $this->assertNull($response->viewData('plan'));
        $this->assertSame($before, $this->snapshot());
        $variant->update(['product_id' => $product->id]);
        $variant->delete();
        $before = $this->snapshot();
        $response = $this->preview()->assertOk();
        $this->assertNull($response->viewData('plan'));
        $this->assertNotEmpty($response->viewData('issue'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_rejected_hidden_archived_and_missing_products_block_the_complete_batch(): void
    {
        $product = Product::firstOrFail();
        foreach (['REJECTED', 'HIDDEN'] as $status) {
            $product->update(['moderation_status' => $status]);
            $before = $this->snapshot();
            $response = $this->preview()->assertOk();
            $this->assertNull($response->viewData('plan'));
            $this->assertNotEmpty($response->viewData('issue'));
            $this->assertSame($before, $this->snapshot());
        }
        $product->update(['moderation_status' => 'PENDING']);
        $product->delete();
        $response = $this->preview()->assertOk();
        $this->assertNull($response->viewData('plan'));
        $product->restore();
        $product->update(['legacy_reference' => null]);
        $before = $this->snapshot();
        $response = $this->preview()->assertOk()->assertSee('complete 60-product demo batch');
        $this->assertNull($response->viewData('plan'));
        $this->assertSame($before, $this->snapshot());
    }
}
