<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeInventoryUnificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_increase_and_decrease_only_the_selected_variant(): void
    {
        [$user, $seller] = $this->seller('inventory-owner');
        $product = $this->product($seller, 'Inventory Product');
        $first = $this->variant($product, 'INV-FIRST', 5, 'Small');
        $second = $this->variant($product, 'INV-SECOND', 9, 'Large');

        $this->actingAs($user)
            ->post(route('seller.inventory.increase', $second->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(5, $first->fresh()->stock);
        $this->assertSame(10, $second->fresh()->stock);

        $this->actingAs($user)
            ->post(route('seller.inventory.decrease', $second->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(5, $first->fresh()->stock);
        $this->assertSame(9, $second->fresh()->stock);
    }

    public function test_stock_cannot_be_decreased_below_zero(): void
    {
        [$user, $seller] = $this->seller('zero-stock-owner');
        $variant = $this->variant($this->product($seller, 'Zero Stock Product'), 'ZERO-STOCK', 0);

        $this->actingAs($user)
            ->from(route('seller.inventory'))
            ->post(route('seller.inventory.decrease', $variant->id))
            ->assertRedirect(route('seller.inventory'))
            ->assertSessionHasErrors('stock');

        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_seller_cannot_modify_another_sellers_variant(): void
    {
        [$owner, $seller] = $this->seller('first-inventory-owner');
        [$otherOwner, $otherSeller] = $this->seller('second-inventory-owner');
        $variant = $this->variant($this->product($seller, 'Protected Product'), 'PROTECTED', 4);

        $this->actingAs($otherOwner)
            ->post(route('seller.inventory.increase', $variant->id))
            ->assertNotFound();

        $this->assertSame(4, $variant->fresh()->stock);

        $this->actingAs($owner)
            ->post(route('seller.inventory.increase', $variant->id))
            ->assertRedirect();

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(0, $otherSeller->products()->count());
    }

    public function test_suspended_and_unapproved_sellers_cannot_adjust_inventory(): void
    {
        [$suspendedUser, $suspendedSeller] = $this->seller('suspended-inventory-owner');
        $suspendedUser->update(['status' => 'suspended', 'is_suspended' => true]);
        $suspendedVariant = $this->variant(
            $this->product($suspendedSeller, 'Suspended Product'),
            'SUSPENDED-STOCK',
            3
        );

        $this->actingAs($suspendedUser)
            ->post(route('seller.inventory.increase', $suspendedVariant->id))
            ->assertForbidden();
        $this->assertSame(3, $suspendedVariant->fresh()->stock);

        [$pendingUser, $pendingSeller] = $this->seller('pending-inventory-owner', 'pending');
        $pendingVariant = $this->variant(
            $this->product($pendingSeller, 'Pending Product'),
            'PENDING-STOCK',
            6
        );

        $this->actingAs($pendingUser)
            ->post(route('seller.inventory.increase', $pendingVariant->id))
            ->assertNotFound();
        $this->assertSame(6, $pendingVariant->fresh()->stock);
    }

    public function test_product_update_sets_stock_on_the_owned_database_variant(): void
    {
        [$user, $seller] = $this->seller('product-update-owner');
        $category = $this->category();
        $product = $this->product($seller, 'Editable Product', $category);
        $variant = $this->variant($product, 'EDITABLE-STOCK', 2);

        $this->actingAs($user)->put(route('seller.products.update', $product->id), [
            'name' => 'Editable Product',
            'category_id' => $category->id,
            'description' => 'Updated through the product form.',
            'price' => '125.00',
            'stock' => 17,
            'sku' => $variant->sku,
            'status' => 'active',
        ])->assertRedirect(route('seller.products'));

        $this->assertSame(17, $variant->fresh()->stock);
    }

    public function test_checkout_uses_stock_after_serialized_seller_adjustments(): void
    {
        [$sellerUser, $seller] = $this->seller('checkout-inventory-owner');
        $product = $this->product($seller, 'Checkout Inventory Product');
        $variant = $this->variant($product, 'CHECKOUT-STOCK', 1);

        $this->actingAs($sellerUser)->post(route('seller.inventory.increase', $variant->id));
        $this->actingAs($sellerUser)->post(route('seller.inventory.increase', $variant->id));
        $this->assertSame(3, $variant->fresh()->stock);

        $buyer = $this->userWithRole('buyer');
        $address = $this->address($buyer);
        CartItem::create([
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'product_variant_id' => $variant->id,
            'price' => $variant->price_minor / 100,
            'quantity' => 2,
        ]);

        $this->actingAs($buyer)->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertDatabaseHas('order_items', [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_session_inventory_remains_a_read_only_separate_fallback(): void
    {
        [$user, $seller] = $this->seller('legacy-inventory-owner');
        $legacy = [
            'P0001' => [
                'id' => 'P0001',
                'name' => 'Legacy Inventory Product',
                'stock' => 7,
                'price' => 50,
                'status' => 'active',
            ],
        ];

        $this->actingAs($user)
            ->withSession(['seller_products' => $legacy])
            ->get(route('seller.inventory'))
            ->assertOk()
            ->assertSee('Legacy Inventory Product');

        $this->actingAs($user)
            ->withSession(['seller_products' => $legacy])
            ->post(route('seller.inventory.increase', 'P0001'))
            ->assertNotFound();

        $this->assertSame(7, session('seller_products.P0001.stock'));
        $this->assertSame(0, $seller->products()->count());
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

    private function seller(string $slug, string $status = 'approved'): array
    {
        $user = $this->userWithRole('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'status' => $status,
            'commission_bps' => 1000,
        ]);

        return [$user, $seller];
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['slug' => 'inventory-category'],
            ['name' => 'Inventory Category', 'is_active' => true]
        );
    }

    private function product(Seller $seller, string $name, ?Category $category = null): Product
    {
        return Product::create([
            'seller_id' => $seller->id,
            'category_id' => ($category ?? $this->category())->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id)->toString(),
            'description' => 'Inventory test product.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
    }

    private function variant(Product $product, string $sku, int $stock, string $name = 'Default'): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => $name,
            'price_minor' => 12500,
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    private function address(User $buyer): Address
    {
        return Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home',
            'recipient' => $buyer->name,
            'phone' => '09123456789',
            'line1' => '123 Inventory Street',
            'barangay' => 'Inventory Barangay',
            'city' => 'Inventory City',
            'province' => 'Inventory Province',
            'postal_code' => '4000',
            'is_default' => true,
        ]);
    }
}
