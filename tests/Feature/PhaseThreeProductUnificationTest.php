<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseThreeProductUnificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_creation_persists_product_variant_and_images(): void
    {
        Storage::fake('public');
        [$user, $seller] = $this->seller('database-seller');
        $category = $this->category('Electronics');
        $seller->update(['category_id' => $category->id]);

        $this->actingAs($user)->post(route('seller.products.store'), [
            'name' => 'Database Headphones',
            'category_id' => $category->id,
            'brand' => 'SUKI Audio',
            'description' => 'Database-backed headphones.',
            'price' => '899.50',
            'stock' => 12,
            'sku' => 'DB-HEADPHONES-001',
            'status' => 'active',
            'weight' => '0.50',
            'length' => '20.5',
            'width' => '15.0',
            'height' => '8.5',
            'variation_name' => ['Color'],
            'variation_value' => ['Black'],
            'shipping_options' => ['suki_rider'],
            'images' => [UploadedFile::fake()->create('headphones.jpg', 100, 'image/jpeg')],
        ])->assertRedirect(route('seller.products'));

        $product = Product::where('seller_id', $seller->id)->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('SUKI Audio', $product->brand);
        $this->assertSame(['suki_rider'], $product->shipping_options);
        $this->assertSame(89950, $variant->price_minor);
        $this->assertSame(500, $variant->weight_grams);
        $this->assertSame(205, $variant->length_mm);
        $this->assertSame(['Color' => 'Black'], $variant->options);
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);
        $this->assertEmpty(session('seller_products', []));
    }

    public function test_seller_can_update_only_products_owned_by_their_store(): void
    {
        [$owner, $seller] = $this->seller('owner-seller');
        [$otherUser] = $this->seller('other-seller');
        $category = $this->category('Fashion');
        $seller->update(['category_id' => $category->id]);
        $product = $this->product($seller, $category, 'Owned Product', 'OWNED-001');

        $payload = [
            'name' => 'Updated Owned Product',
            'category_id' => $category->id,
            'description' => 'Updated description.',
            'price' => '150.00',
            'stock' => 8,
            'sku' => 'OWNED-001',
            'status' => 'active',
        ];

        $this->actingAs($otherUser)
            ->put(route('seller.products.update', $product), $payload)
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('seller.products.update', $product), $payload)
            ->assertRedirect(route('seller.products'));

        $this->assertSame('Updated Owned Product', $product->fresh()->name);
        $this->assertSame(15000, $product->variants()->first()->price_minor);
    }

    public function test_database_catalog_is_primary_on_buyer_pages(): void
    {
        [, $seller] = $this->seller('marketplace-seller');
        $category = $this->category('Home & Living');
        $visible = $this->product($seller, $category, 'Database Marketplace Product', 'MARKET-001');
        $hidden = $this->product($seller, $category, 'Inactive Database Product', 'MARKET-002', false);

        $this->get(route('buyer.home'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee('Minimalist Shoulder Bag')
            ->assertDontSee($hidden->name);

        $this->get(route('buyer.shop'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);

        $this->get(route('buyer.product', $visible->slug))
            ->assertOk()
            ->assertSee($visible->name);
    }

    public function test_database_product_cart_entry_links_the_variant_for_phase_two_checkout(): void
    {
        [, $seller] = $this->seller('cart-seller');
        $buyer = $this->userWithRole('buyer');
        $category = $this->category('Groceries');
        $product = $this->product($seller, $category, 'Database Cart Product', 'CART-001');
        $variant = $product->variants()->firstOrFail();

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_legacy_session_products_remain_read_only_fallback_without_import(): void
    {
        [$user, $seller] = $this->seller('fallback-seller');
        $legacyProduct = [
            'P0001' => [
                'id' => 'P0001',
                'name' => 'Legacy Session Product',
                'category' => 'Others',
                'price' => 100,
                'stock' => 2,
                'status' => 'active',
            ],
        ];

        $this->actingAs($user)
            ->withSession(['seller_products' => $legacyProduct])
            ->get(route('seller.products'))
            ->assertOk()
            ->assertSee('Legacy Session Product');

        $this->assertSame(0, Product::where('seller_id', $seller->id)->count());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $roleModel = Role::firstOrCreate(['name' => $role]);
        $user->roles()->syncWithoutDetaching([$roleModel->id]);

        return $user;
    }

    private function seller(string $slug): array
    {
        $user = $this->userWithRole('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'status' => 'approved',
        ]);

        return [$user, $seller];
    }

    private function category(string $name): Category
    {
        return Category::firstOrCreate(
            ['slug' => str($name)->slug()->toString()],
            ['name' => $name, 'is_active' => true]
        );
    }

    private function product(
        Seller $seller,
        Category $category,
        string $name,
        string $sku,
        bool $active = true
    ): Product {
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => 'Database marketplace fixture.',
            'is_active' => $active,
            'moderation_status' => 'APPROVED',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => 'Default',
            'price_minor' => 12500,
            'stock' => 10,
            'is_active' => $active,
        ]);

        return $product;
    }
}
