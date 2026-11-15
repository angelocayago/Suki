<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersionedBuyerCatalogMediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated API tests only.');
        }
        parent::setUp();
        Storage::fake('public');
    }

    public function test_public_catalog_supports_mobile_pagination_filters_sorting_and_safe_visibility(): void
    {
        $seller = $this->seller('catalog-public');
        $category = $this->category('catalog-public');
        for ($i = 1; $i <= 25; $i++) $this->product($seller, $category, 'Catalog Item '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), $i * 1000);
        $otherCategory = $this->category('catalog-other');
        $other = $this->product($seller, $otherCategory, 'Filtered Item', 99000);
        $inactive = $this->product($seller, $category, 'Inactive Item', 5000, ['is_active' => false]);
        $pending = $this->product($seller, $category, 'Pending Item', 5000, ['moderation_status' => 'PENDING']);
        $suspended = $this->seller('catalog-suspended', ['owner_suspended' => true]);
        $this->product($suspended, $category, 'Suspended Seller Item', 5000);

        $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('data.per_page', 24)->assertJsonPath('data.total', 26)
            ->assertJsonCount(24, 'data.data');
        $this->getJson('/api/v1/products?q=Filtered&category='.$otherCategory->slug.'&seller='.$seller->slug.'&sort=price_desc')
            ->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $other->id)
            ->assertJsonPath('data.data.0.category_id', $otherCategory->id)
            ->assertJsonPath('data.data.0.is_available', true);
        $this->getJson('/api/v1/products?sort=price_desc&per_page=2')->assertOk()
            ->assertJsonPath('data.data.0.id', $other->id);
        $this->getJson('/api/v1/products?per_page=101')->assertUnprocessable();
        $response = $this->getJson('/api/v1/products')->assertOk();
        foreach ([$inactive->name, $pending->name, 'Suspended Seller Item'] as $hidden) $response->assertDontSee($hidden);
    }

    public function test_product_detail_accepts_slug_or_numeric_id_and_returns_mobile_fields(): void
    {
        $product = $this->product($this->seller('detail'), $this->category('detail'), 'Detail Item', 12500, [], [12500, 13500]);

        foreach ([$product->slug, (string) $product->id] as $identifier) {
            $this->getJson('/api/v1/products/'.$identifier)->assertOk()
                ->assertJsonPath('data.id', $product->id)
                ->assertJsonPath('data.category_id', $product->category_id)
                ->assertJsonPath('data.category_name', 'Category detail')
                ->assertJsonPath('data.image_url', '/storage/products/'.$product->id.'/photo.webp')
                ->assertJsonPath('data.variants.1.price', 135)
                ->assertJsonPath('data.is_available', true);
        }

        $hidden = $this->product($this->seller('hidden-detail'), $this->category('hidden-detail'), 'Hidden detail', 10000, ['moderation_status' => 'PENDING']);
        $this->getJson('/api/v1/products/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/v1/products/no-such-product')->assertNotFound();
    }

    public function test_product_media_proxy_serves_only_visible_product_image_records(): void
    {
        $product = $this->product($this->seller('media'), $this->category('media'), 'Media Item', 10000);
        $path = 'products/'.$product->id.'/photo.webp';

        $this->get('/api/v1/media/products/'.$product->id.'/photo.webp')->assertOk()
            ->assertHeader('cache-control', 'max-age=3600, public');
        $this->get('/api/v1/media/products/'.$product->id.'/missing.webp')->assertNotFound();
        $this->get('/api/v1/media/products/../private/return-evidence.webp')->assertNotFound();
        Storage::disk('public')->put('return-evidence/private.webp', 'private evidence');
        $this->get('/api/v1/media/products/../return-evidence/private.webp')->assertNotFound();

        $product->update(['is_active' => false]);
        $this->get('/api/v1/media/products/'.$product->id.'/photo.webp')->assertNotFound();
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    private function seller(string $slug, array $options = []): Seller
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => $options['owner_suspended'] ?? false]);

        return Seller::create(['user_id' => $owner->id, 'name' => 'Seller '.$slug, 'slug' => 'seller-'.$slug, 'status' => 'approved']);
    }

    private function category(string $slug): Category
    {
        return Category::create(['name' => 'Category '.$slug, 'slug' => 'category-'.$slug, 'is_active' => true]);
    }

    private function product(Seller $seller, Category $category, string $name, int $price, array $changes = [], array $prices = []): Product
    {
        $product = Product::create($changes + [
            'seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $name,
            'slug' => str($name)->slug().'-'.Product::withTrashed()->count(), 'description' => 'Catalog fixture',
            'is_active' => true, 'moderation_status' => 'APPROVED',
        ]);
        foreach (($prices ?: [$price]) as $index => $variantPrice) {
            $product->variants()->create(['sku' => 'V1-CATALOG-'.$product->id.'-'.$index, 'name' => 'Option '.$index, 'price_minor' => $variantPrice, 'stock' => 5, 'is_active' => true]);
        }
        $path = 'products/'.$product->id.'/photo.webp';
        Storage::disk('public')->put($path, 'fixture image');
        $product->images()->create(['path' => $path, 'position' => 1]);

        return $product->fresh();
    }
}
