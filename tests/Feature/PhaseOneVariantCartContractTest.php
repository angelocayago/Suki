<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneVariantCartContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_and_inactive_variant_ids_are_rejected(): void
    {
        $buyer = $this->buyer();
        [, $product, $variant] = $this->product('First', 10000, 5);
        [, $otherProduct, $foreign] = $this->product('Second', 20000, 5);
        $inactive = ProductVariant::create(['product_id' => $product->id, 'sku' => 'INACTIVE-'.$product->id, 'name' => 'Inactive', 'price_minor' => 9000, 'stock' => 5, 'is_active' => false]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $foreign->id, 'quantity' => 1])->assertNotFound();
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $inactive->id, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertNotSame($product->id, $otherProduct->id);
        $this->assertNotNull($variant);
    }

    public function test_selected_variant_price_and_stock_are_authoritative(): void
    {
        $buyer = $this->buyer();
        [, $product, $default] = $this->product('Options', 10000, 99);
        $selected = ProductVariant::create(['product_id' => $product->id, 'sku' => 'OPTIONS-SMALL', 'name' => 'Small', 'options' => ['Size' => 'S'], 'price_minor' => 27550, 'stock' => 2, 'is_active' => true]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $selected->id, 'quantity' => 8])->assertRedirect();

        $item = CartItem::where('user_id', $buyer->id)->sole();
        $this->assertSame($selected->id, $item->product_variant_id);
        $this->assertSame('275.50', $item->price);
        $this->assertSame(2, $item->quantity);
        $this->assertNotSame($default->id, $item->product_variant_id);
    }

    public function test_two_variants_of_one_product_create_distinct_cart_lines_and_default_add_still_works(): void
    {
        $buyer = $this->buyer();
        [, $product, $default] = $this->product('Two variants', 10000, 5);
        $second = ProductVariant::create(['product_id' => $product->id, 'sku' => 'TWO-SECOND', 'name' => 'Large', 'options' => ['Size' => 'L'], 'price_minor' => 15000, 'stock' => 4, 'is_active' => true]);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['variant_id' => $second->id, 'quantity' => 1])->assertRedirect();

        $this->assertDatabaseCount('cart_items', 2);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_variant_id' => $default->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_variant_id' => $second->id, 'quantity' => 1]);
    }

    private function buyer(): User
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $buyer->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'buyer'])->id]);
        return $buyer;
    }

    private function product(string $name, int $priceMinor, int $stock): array
    {
        $category = Category::firstOrCreate(['slug' => 'variant-cart'], ['name' => 'Variant cart', 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $owner->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'seller'])->id]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => $name.' shop', 'slug' => str($name)->slug()->append('-'.$owner->id), 'category_id' => $category->id, 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug()->append('-'.$seller->id), 'description' => 'Variant cart fixture.', 'is_active' => true, 'moderation_status' => ProductModerationStatus::Approved->value]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'DEFAULT-'.$product->id, 'name' => 'Default', 'price_minor' => $priceMinor, 'stock' => $stock, 'is_active' => true]);
        return [$seller, $product, $variant];
    }
}
