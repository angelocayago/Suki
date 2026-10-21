<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFiveMarketplacePartTwoTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_cart_persists_for_its_buyer_and_rejects_hidden_products(): void
    {
        [$seller, $product] = $this->visibleProduct('Cart product');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug), ['quantity' => 2])->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_slug' => $product->slug, 'quantity' => 2]);
        $this->actingAs($other)->post(route('buyer.cart.remove', $product->slug))->assertRedirect();
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_slug' => $product->slug]);
        $this->actingAs($buyer)->get(route('buyer.cart'))->assertOk()->assertSee('Cart product');

        $product->update(['moderation_status' => ProductModerationStatus::Pending->value]);
        $this->actingAs($buyer)->post(route('buyer.cart.add', $product->slug))->assertNotFound();
    }

    public function test_wishlist_is_buyer_scoped_and_cannot_move_hidden_product_to_cart(): void
    {
        [, $product] = $this->visibleProduct('Wish product');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');

        $this->actingAs($buyer)->post(route('buyer.wishlist.add', $product->slug))->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.wishlist.add', $product->slug))->assertRedirect();
        $this->assertDatabaseCount('wishlist_items', 1);
        $this->actingAs($other)->post(route('buyer.wishlist.remove', $product->slug))->assertRedirect();
        $this->assertDatabaseCount('wishlist_items', 1);

        $product->update(['moderation_status' => ProductModerationStatus::Rejected->value]);
        $this->actingAs($buyer)->post(route('buyer.wishlist.move-to-cart', $product->slug))->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_product_detail_uses_visible_database_product_and_hides_rejected_products(): void
    {
        [, $product] = $this->visibleProduct('Detail product');
        $this->get(route('buyer.product', $product->slug))
            ->assertOk()
            ->assertSee('Detail product')
            ->assertSee(route('buyer.messages.context.product.store', $product->id));

        $product->update(['moderation_status' => ProductModerationStatus::Rejected->value]);
        $this->get(route('buyer.product', $product->slug))->assertNotFound();
    }

    public function test_followed_stores_page_is_buyer_scoped_and_excludes_suspended_stores(): void
    {
        [$seller] = $this->visibleProduct('Follow product');
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $this->actingAs($buyer)->post(route('buyer.stores.follow', $seller))->assertRedirect();

        $this->actingAs($buyer)->get(route('buyer.stores.following'))->assertOk()->assertSee($seller->name);
        $this->actingAs($other)->get(route('buyer.stores.following'))->assertOk()->assertDontSee($seller->name);
        $seller->owner->update(['is_suspended' => true]);
        $this->actingAs($buyer)->get(route('buyer.stores.following'))->assertOk()->assertDontSee($seller->name);
    }

    public function test_buyer_address_deletion_reassigns_default_without_cross_buyer_access(): void
    {
        $buyer = $this->user('buyer');
        $this->fakePsgc();
        $payload = ['name' => 'Buyer', 'phone' => '09171234567', 'region' => '01', 'province' => '0101', 'municipality' => '010101', 'barangay' => '010101001', 'street' => 'One', 'postal_code' => '4026', 'label' => 'Home'];
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload)->assertRedirect();
        $first = Address::where('user_id', $buyer->id)->sole();
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload + ['label' => 'Office'])->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.addresses.delete', $first))->assertRedirect();
        $this->assertSame(1, Address::where('user_id', $buyer->id)->where('is_default', true)->count());
    }

    public function test_approved_seller_can_update_only_public_store_fields_and_not_category(): void
    {
        Storage::fake('public');
        [$seller] = $this->visibleProduct('Profile product');
        $originalCategory = $seller->category_id;
        $otherCategory = Category::create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $this->actingAs($seller->owner)->post(route('seller.store.profile.update'), [
            'store_name' => 'Updated Store',
            'phone' => '09171234567',
            'description' => 'Public shop description',
            'category_id' => $otherCategory->id,
            'profile_photo' => UploadedFile::fake()->createWithContent(
                'logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL0ZwAAAABJRU5ErkJggg==')
            ),
        ])->assertRedirect();

        $seller->refresh();
        $this->assertSame('Updated Store', $seller->name);
        $this->assertSame($originalCategory, $seller->category_id);
        $this->assertNotNull($seller->logo_path);
        Storage::disk('public')->assertExists($seller->logo_path);
    }

    private function visibleProduct(string $name): array
    {
        $category = Category::firstOrCreate(['slug' => 'market'], ['name' => 'Market', 'is_active' => true]);
        $owner = $this->user('seller');
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Store '.$owner->id, 'slug' => 'store-'.$owner->id, 'status' => 'approved', 'category_id' => $category->id]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug().'-'.$seller->id, 'is_active' => true, 'moderation_status' => ProductModerationStatus::Approved->value]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.$product->id, 'name' => 'Default', 'price_minor' => 1500, 'stock' => 5, 'is_active' => true]);

        return [$seller, $product];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);

        return $user;
    }

    private function fakePsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '01', 'name' => 'Region A']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
        ]);
    }
}
