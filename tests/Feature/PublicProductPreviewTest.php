<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_an_approved_active_product_preview_and_its_details(): void
    {
        $product = $this->product('Public preview', 'A guest-visible product description.');

        $this->get(route('buyer.product', $product->slug))
            ->assertOk()
            ->assertSee('Public preview')
            ->assertSee('A guest-visible product description.')
            ->assertSee('Default')
            ->assertSee('Sign in to purchase or save this item.');
    }

    public function test_guest_cannot_mutate_cart_or_wishlist_from_a_public_product_preview(): void
    {
        $product = $this->product('Protected preview');

        $this->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('login'));
        $this->post(route('buyer.wishlist.toggle', $product->slug))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_active_buyer_can_still_add_a_public_product_to_cart(): void
    {
        $product = $this->product('Buyer preview');
        $buyer = $this->user('buyer');

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'quantity' => 1,
        ]);
    }

    public function test_inactive_pending_or_suspended_seller_products_are_not_publicly_viewable(): void
    {
        $inactive = $this->product('Inactive preview');
        $inactive->update(['is_active' => false]);

        $pending = $this->product('Pending preview');
        $pending->update(['moderation_status' => 'PENDING']);

        $suspended = $this->product('Suspended seller preview');
        $suspended->seller->owner->update(['is_suspended' => true]);

        foreach ([$inactive, $pending, $suspended] as $product) {
            $this->get(route('buyer.product', $product->slug))->assertNotFound();
        }
    }

    private function product(string $name, string $description = 'Product preview description.'): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'public-preview'],
            ['name' => 'Public Preview', 'is_active' => true]
        );
        $seller = $this->seller();
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id),
            'description' => $description,
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PREVIEW-'.$product->id,
            'name' => 'Default',
            'price_minor' => 129900,
            'stock' => 3,
            'is_active' => true,
        ]);

        return $product;
    }

    private function seller(): Seller
    {
        $owner = $this->user('seller');

        return Seller::create([
            'user_id' => $owner->id,
            'name' => 'Preview Store '.$owner->id,
            'slug' => 'preview-store-'.$owner->id,
            'status' => 'approved',
        ]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $user->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['name' => $role])->id,
        ]);

        return $user;
    }
}
