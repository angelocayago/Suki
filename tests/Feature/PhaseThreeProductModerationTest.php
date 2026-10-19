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

class PhaseThreeProductModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_seller_product_is_pending_and_not_visible_to_buyers(): void
    {
        $admin = $this->userWithRole('admin');
        [$sellerUser, $seller] = $this->seller('pending-product-seller');
        $category = $this->category();
        $seller->update(['category_id' => $category->id]);

        $this->actingAs($sellerUser)->post(route('seller.products.store'), $this->payload($category, [
            'name' => 'Pending Marketplace Product',
            'sku' => 'PENDING-001',
        ]))->assertRedirect(route('seller.products'));

        $product = $seller->products()->sole();
        $this->assertSame(ProductModerationStatus::Pending, $product->moderation_status);
        $this->assertTrue($product->is_active);
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $this->assertDatabaseHas('product_moderation_events', [
            'product_id' => $product->id,
            'previous_status' => null,
            'new_status' => 'PENDING',
            'source' => 'seller_submission',
        ]);

        $this->get(route('buyer.shop'))
            ->assertOk()
            ->assertDontSee('Pending Marketplace Product');
    }

    public function test_admin_assigns_category_and_approves_pending_product_with_history(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('approved-product-seller');
        $category = $this->category();
        $product = $this->product($seller, $category, 'Admin Approved Product');

        $this->actingAs($admin)
            ->get(route('superadmin.products.index'))
            ->assertOk()
            ->assertSee('Admin Approved Product');
        $this->actingAs($admin)
            ->get(route('superadmin.products.show', $product))
            ->assertOk()
            ->assertSee('Product submitted for moderation.')
            ->assertSee('MOD-'.$product->id);

        $this->actingAs($admin)
            ->put(route('superadmin.sellers.allowed-categories.update', $seller), [
                'categories' => [$category->id],
            ])
            ->assertRedirect();

        $this->assertSame($category->id, $seller->fresh()->category_id);
        $this->assertTrue($seller->fresh()->allowedCategories->contains($category));

        $this->actingAs($admin)
            ->post(route('superadmin.products.approve', $product))
            ->assertRedirect()
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertSame(ProductModerationStatus::Approved, $product->moderation_status);
        $this->assertSame($admin->id, $product->moderated_by);
        $this->assertNotNull($product->moderated_at);
        $this->assertSame(1, $seller->owner->unreadNotifications()->count());
        $this->assertDatabaseHas('product_moderation_events', [
            'product_id' => $product->id,
            'actor_id' => $admin->id,
            'previous_status' => 'PENDING',
            'new_status' => 'APPROVED',
        ]);

        $this->get(route('buyer.shop'))->assertSee('Admin Approved Product');
    }

    public function test_product_cannot_be_approved_outside_sellers_allowed_categories(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('category-protected-seller');
        $product = $this->product($seller, $this->category(), 'Category Protected Product');

        $this->actingAs($admin)
            ->from(route('superadmin.products.show', $product))
            ->post(route('superadmin.products.approve', $product))
            ->assertRedirect(route('superadmin.products.show', $product))
            ->assertSessionHasErrors('category');

        $this->assertSame(ProductModerationStatus::Pending, $product->fresh()->moderation_status);
    }

    public function test_rejection_and_hiding_require_reasons_and_valid_transitions(): void
    {
        $admin = $this->userWithRole('admin');
        [, $seller] = $this->seller('decision-seller');
        $category = $this->category();
        $seller->update(['category_id' => $category->id]);
        $seller->allowedCategories()->attach($category, ['assigned_by' => $admin->id]);
        $pending = $this->product($seller, $category, 'Rejected Product');

        $this->actingAs($admin)
            ->post(route('superadmin.products.reject', $pending), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('superadmin.products.reject', $pending), ['reason' => 'Prohibited product claim.'])
            ->assertSessionHas('success');
        $this->assertSame(ProductModerationStatus::Rejected, $pending->fresh()->moderation_status);

        $approved = $this->product($seller, $category, 'Hidden Product');
        $this->actingAs($admin)->post(route('superadmin.products.approve', $approved));

        $this->actingAs($admin)
            ->post(route('superadmin.products.hide', $approved), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->post(route('superadmin.products.hide', $approved), ['reason' => 'Listing requires investigation.'])
            ->assertSessionHas('success');
        $this->assertSame(ProductModerationStatus::Hidden, $approved->fresh()->moderation_status);
        $this->get(route('buyer.shop'))->assertDontSee('Hidden Product');

        $this->actingAs($admin)
            ->post(route('superadmin.products.restore', $approved))
            ->assertSessionHas('success');
        $this->assertSame(ProductModerationStatus::Approved, $approved->fresh()->moderation_status);
        $this->get(route('buyer.shop'))->assertSee('Hidden Product');
    }

    public function test_material_edit_returns_product_to_pending_but_stock_adjustment_does_not(): void
    {
        $admin = $this->userWithRole('admin');
        [$sellerUser, $seller] = $this->seller('editing-seller');
        $category = $this->category();
        $seller->update(['category_id' => $category->id]);
        $seller->allowedCategories()->attach($category, ['assigned_by' => $admin->id]);
        $product = $this->product($seller, $category, 'Original Product Name');
        $variant = $product->variants()->sole();

        $this->actingAs($admin)->post(route('superadmin.products.approve', $product));

        $this->actingAs($sellerUser)
            ->post(route('seller.inventory.increase', $variant))
            ->assertRedirect();
        $this->assertSame(ProductModerationStatus::Approved, $product->fresh()->moderation_status);

        $this->actingAs($sellerUser)->put(
            route('seller.products.update', $product),
            $this->payload($category, [
                'name' => 'Materially Updated Product',
                'sku' => $variant->sku,
                'stock' => $variant->fresh()->stock,
            ])
        )->assertRedirect(route('seller.products'));

        $this->assertSame(ProductModerationStatus::Pending, $product->fresh()->moderation_status);
        $this->assertDatabaseHas('product_moderation_events', [
            'product_id' => $product->id,
            'previous_status' => 'APPROVED',
            'new_status' => 'PENDING',
            'source' => 'seller_material_edit',
        ]);
        $this->get(route('buyer.shop'))->assertDontSee('Materially Updated Product');
    }

    public function test_rejected_product_returns_to_pending_after_seller_correction(): void
    {
        $admin = $this->userWithRole('admin');
        [$sellerUser, $seller] = $this->seller('correction-seller');
        $category = $this->category();
        $product = $this->product($seller, $category, 'Rejected Then Corrected');
        $variant = $product->variants()->sole();

        $this->actingAs($admin)->post(route('superadmin.products.reject', $product), [
            'reason' => 'Description needs correction.',
        ]);

        $this->actingAs($sellerUser)->put(route('seller.products.update', $product), $this->payload($category, [
            'name' => $product->name,
            'description' => 'Corrected and complete product description.',
            'sku' => $variant->sku,
        ]))->assertRedirect(route('seller.products'));

        $product->refresh();
        $this->assertSame(ProductModerationStatus::Pending, $product->moderation_status);
        $this->assertNull($product->moderation_reason);
        $this->assertNull($product->moderated_by);
    }

    public function test_non_admin_cannot_access_or_mutate_product_moderation(): void
    {
        [$sellerUser, $seller] = $this->seller('unauthorized-moderation-seller');
        $product = $this->product($seller, $this->category(), 'Protected Moderation Product');

        $this->actingAs($sellerUser)
            ->get(route('superadmin.products.index'))
            ->assertForbidden();
        $this->actingAs($sellerUser)
            ->post(route('superadmin.products.approve', $product))
            ->assertForbidden();
        $this->actingAs($sellerUser)
            ->put(route('superadmin.sellers.allowed-categories.update', $seller), [
                'categories' => [$product->category_id],
            ])
            ->assertForbidden();

        $this->assertSame(ProductModerationStatus::Pending, $product->fresh()->moderation_status);
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

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['slug' => 'moderation-category'],
            ['name' => 'Moderation Category', 'is_active' => true]
        );
    }

    private function product(Seller $seller, Category $category, string $name): Product
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id)->toString(),
            'description' => 'Product submitted for moderation.',
            'is_active' => true,
            'moderation_status' => ProductModerationStatus::Pending->value,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'MOD-'.$product->id,
            'name' => 'Default',
            'price_minor' => 15000,
            'stock' => 10,
            'is_active' => true,
        ]);

        return $product;
    }

    private function payload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Moderated Product',
            'category_id' => $category->id,
            'description' => 'Product description for moderation testing.',
            'price' => '150.00',
            'stock' => 10,
            'sku' => 'MODERATED-DEFAULT',
            'status' => 'active',
        ], $overrides);
    }
}
