<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoASellerProductEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_create_products_in_assigned_root_and_descendant_categories(): void
    {
        [$root, , $leaf] = $this->categoryTree();
        [$user, $seller] = $this->seller('tree-seller', $root);

        $this->actingAs($user)
            ->post(route('seller.products.store'), $this->payload($root, ['name' => 'Root Product', 'sku' => 'ROOT-001']))
            ->assertRedirect(route('seller.products'));

        $this->actingAs($user)
            ->post(route('seller.products.store'), $this->payload($leaf, [
                'name' => 'Leaf Product',
                'sku' => 'LEAF-001',
                'variants' => [[
                    'name' => 'Large',
                    'sku' => 'LEAF-LARGE',
                    'price' => '175.00',
                    'stock' => 6,
                    'status' => 'active',
                ]],
            ]))
            ->assertRedirect(route('seller.products'));

        $rootProduct = Product::where('name', 'Root Product')->firstOrFail();
        $leafProduct = Product::where('name', 'Leaf Product')->firstOrFail();

        $this->assertSame($seller->id, $rootProduct->seller_id);
        $this->assertSame($root->id, $rootProduct->category_id);
        $this->assertSame($leaf->id, $leafProduct->category_id);
        $this->assertSame(ProductModerationStatus::Pending, $leafProduct->moderation_status);
        $this->assertCount(2, $leafProduct->variants);
    }

    public function test_seller_cannot_create_outside_tree_or_without_an_assigned_category(): void
    {
        [$root] = $this->categoryTree();
        $outside = $this->category('Electronics');
        [$user, $seller] = $this->seller('restricted-seller', $root);

        // The compatibility pivot must not override sellers.category_id.
        $seller->allowedCategories()->attach($outside);

        $this->actingAs($user)
            ->post(route('seller.products.store'), $this->payload($outside))
            ->assertSessionHasErrors('category_id');

        [$unassignedUser] = $this->seller('unassigned-seller');
        $this->actingAs($unassignedUser)
            ->post(route('seller.products.store'), $this->payload($root, ['sku' => 'NO-CATEGORY']))
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_seller_edit_is_owned_category_scoped_and_cannot_change_ownership(): void
    {
        [$root, $child] = $this->categoryTree();
        $outside = $this->category('Home');
        [$owner, $seller] = $this->seller('owner', $root);
        [$otherUser, $otherSeller] = $this->seller('other-owner', $outside);
        $product = $this->product($seller, $root, 'Owned Product', 'OWNED-SKU');

        $this->actingAs($owner)
            ->put(route('seller.products.update', $product), $this->payload($child, [
                'name' => 'Updated Product',
                'sku' => 'OWNED-SKU',
                'seller_id' => $otherSeller->id,
            ]))
            ->assertRedirect(route('seller.products'));

        $this->assertSame($seller->id, $product->fresh()->seller_id);
        $this->assertSame($child->id, $product->fresh()->category_id);

        $this->actingAs($owner)
            ->put(route('seller.products.update', $product), $this->payload($outside, ['sku' => 'OWNED-SKU']))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($otherUser)
            ->put(route('seller.products.update', $product), $this->payload($outside, ['sku' => 'OWNED-SKU']))
            ->assertNotFound();
    }

    public function test_legacy_product_can_keep_current_category_but_cannot_move_to_an_unrelated_one(): void
    {
        $legacyCategory = $this->category('Legacy');
        $otherCategory = $this->category('Other');
        [$user, $seller] = $this->seller('legacy-owner');
        $product = $this->product($seller, $legacyCategory, 'Legacy Product', 'LEGACY-SKU');

        $this->actingAs($user)
            ->put(route('seller.products.update', $product), $this->payload($legacyCategory, [
                'name' => 'Legacy Product Updated',
                'sku' => 'LEGACY-SKU',
            ]))
            ->assertRedirect(route('seller.products'));

        $this->actingAs($user)
            ->put(route('seller.products.update', $product), $this->payload($otherCategory, ['sku' => 'LEGACY-SKU']))
            ->assertSessionHasErrors('category_id');
    }

    public function test_admin_moderation_uses_authoritative_category_tree(): void
    {
        [$root, $child] = $this->categoryTree();
        $outside = $this->category('Electronics');
        [, $seller] = $this->seller('moderated-seller', $root);
        $admin = $this->userWithRole('admin');
        $allowed = $this->product($seller, $child, 'Allowed Pending', 'ALLOW-001', ProductModerationStatus::Pending);
        $invalid = $this->product($seller, $outside, 'Invalid Pending', 'INVALID-001', ProductModerationStatus::Pending);
        $seller->allowedCategories()->attach($outside);

        $this->actingAs($admin)
            ->post(route('admin.products.approve', $allowed))
            ->assertRedirect();
        $this->assertSame(ProductModerationStatus::Approved, $allowed->fresh()->moderation_status);

        $this->actingAs($admin)
            ->post(route('admin.products.reject', $invalid), ['reason' => 'Outside assigned category.'])
            ->assertRedirect();
        $this->assertSame(ProductModerationStatus::Rejected, $invalid->fresh()->moderation_status);

        $invalid->refresh()->update(['moderation_status' => ProductModerationStatus::Pending]);
        $this->actingAs($admin)
            ->post(route('admin.products.approve', $invalid))
            ->assertSessionHasErrors('category');
        $this->assertSame(ProductModerationStatus::Pending, $invalid->fresh()->moderation_status);
    }

    public function test_seller_archives_only_owned_products_with_soft_deletes(): void
    {
        [$root] = $this->categoryTree();
        [$owner, $seller] = $this->seller('archive-owner', $root);
        [$otherUser] = $this->seller('archive-other', $root);
        $product = $this->product($seller, $root, 'Archive Product', 'ARCHIVE-001');

        $this->actingAs($otherUser)
            ->delete(route('seller.products.archive', $product))
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete(route('seller.products.archive', $product))
            ->assertRedirect(route('seller.products'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertFalse((bool) Product::withTrashed()->findOrFail($product->id)->is_active);
        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id]);
    }

    public function test_manual_inventory_is_owned_non_negative_and_low_stock_boundary_is_visible(): void
    {
        [$root] = $this->categoryTree();
        [$owner, $seller] = $this->seller('inventory-owner', $root);
        [$otherUser] = $this->seller('inventory-other', $root);
        $lowProduct = $this->product($seller, $root, 'Low Product', 'LOW-010', stock: 10);
        $normalProduct = $this->product($seller, $root, 'Normal Product', 'NORMAL-011', stock: 11);
        $variant = $lowProduct->variants()->firstOrFail();

        $this->actingAs($owner)
            ->put(route('seller.inventory.set', $variant), ['stock' => 7])
            ->assertRedirect();
        $this->assertSame(7, $variant->fresh()->stock);

        $this->actingAs($otherUser)
            ->put(route('seller.inventory.set', $variant), ['stock' => 5])
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('seller.inventory.set', $variant), ['stock' => -1])
            ->assertSessionHasErrors('stock');
        $this->assertSame(7, $variant->fresh()->stock);

        $variant->refresh()->update(['stock' => 10]);
        $this->actingAs($owner)
            ->get(route('seller.products'))
            ->assertOk()
            ->assertSee('10 left')
            ->assertSee('11 in stock');

        $this->assertSame(11, $normalProduct->variants()->firstOrFail()->stock);
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Product',
            'category_id' => $category->id,
            'description' => 'A valid product description.',
            'price' => '125.00',
            'stock' => 10,
            'sku' => 'TEST-'.strtoupper(substr(md5($category->id.microtime()), 0, 8)),
            'status' => 'active',
        ], $overrides);
    }

    private function categoryTree(): array
    {
        $root = $this->category('Apparel');
        $child = $this->category('Women', $root);
        $leaf = $this->category('Tops', $child);

        return [$root, $child, $leaf];
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::create([
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.str()->random(6))->toString(),
            'is_active' => true,
        ]);
    }

    private function seller(string $slug, ?Category $category = null): array
    {
        $user = $this->userWithRole('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'category_id' => $category?->id,
            'status' => 'approved',
        ]);

        return [$user, $seller];
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

    private function product(
        Seller $seller,
        Category $category,
        string $name,
        string $sku,
        ProductModerationStatus $moderation = ProductModerationStatus::Approved,
        int $stock = 10
    ): Product {
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.str()->random(6))->toString(),
            'description' => 'Product fixture.',
            'is_active' => true,
            'moderation_status' => $moderation->value,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => 'Default',
            'price_minor' => 12500,
            'stock' => $stock,
            'is_active' => true,
        ]);

        return $product;
    }
}
