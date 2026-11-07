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
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseEightMarketplaceExtrasTest extends TestCase
{
    use \Tests\Support\CartCheckoutIntent;
    use RefreshDatabase;

    public function test_seller_can_save_a_minimum_spend_for_a_shop_voucher(): void
    {
        $sellerUser = $this->user('seller');
        $seller = $this->seller($sellerUser, 'minimum-spend-shop');

        $this->actingAs($sellerUser)
            ->post(route('seller.vouchers.store'), [
                'code' => 'MINIMUM50',
                'discount_type' => 'FIXED',
                'discount_value' => '10.00',
                'minimum_spend' => '50.00',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vouchers', [
            'seller_id' => $seller->id,
            'code' => 'MINIMUM50',
            'minimum_spend_minor' => 5000,
        ]);
    }

    public function test_buyer_voucher_preview_and_checkout_enforce_the_matching_seller_minimum_spend(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->seller($this->user('seller'), 'voucher-shop');
        $product = $this->product($seller, 'voucher-product', 3000);
        $variant = $product->variants()->sole();
        $voucher = Voucher::create([
            'code' => 'SHOP50', 'scope' => 'SELLER', 'seller_id' => $seller->id,
            'discount_type' => 'FIXED', 'discount_minor' => 500,
            'minimum_spend_minor' => 5000, 'is_active' => true,
        ]);
        $this->cart($buyer, $product, $variant, 1);

        $this->actingAs($buyer)
            ->from($this->selectedCartUrl())
            ->post(route('buyer.cart.vouchers.apply'), ['voucher_code' => $voucher->code])
            ->assertSessionHasErrors('voucher_code');
        $this->assertSame([], session('checkout_voucher_codes', []));

        CartItem::query()->where('user_id', $buyer->id)->update(['quantity' => 2]);
        $this->actingAs($buyer)
            ->post(route('buyer.cart.vouchers.apply'), ['voucher_code' => $voucher->code])
            ->assertSessionHasNoErrors();
        $this->get(route('buyer.checkout', $this->cartCheckoutIntent()))
            ->assertOk()
            ->assertSee('Voucher SHOP50')
            ->assertSee('-PHP 5.00', false);

        $address = $this->address($buyer);
        $this->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'] + $this->cartCheckoutIntent())
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('voucher_redemptions', ['voucher_id' => $voucher->id, 'discount_minor' => 500]);
        $this->assertDatabaseHas('orders', ['buyer_id' => $buyer->id, 'total_minor' => 10500]);
    }

    public function test_store_voucher_does_not_discount_another_store_or_shipping_and_cart_uses_canonical_delivery_label(): void
    {
        $buyer = $this->user('buyer');
        $one = $this->seller($this->user('seller'), 'first-shop');
        $two = $this->seller($this->user('seller'), 'second-shop');
        $firstProduct = $this->product($one, 'first-product', 10000);
        $secondProduct = $this->product($two, 'second-product', 20000);
        $this->cart($buyer, $firstProduct, $firstProduct->variants()->sole());
        Voucher::create([
            'code' => 'SECONDONLY', 'scope' => 'SELLER', 'seller_id' => $two->id,
            'discount_type' => 'FIXED', 'discount_minor' => 5000, 'is_active' => true,
        ]);

        $this->actingAs($buyer)
            ->post(route('buyer.cart.vouchers.apply'), ['voucher_code' => 'SECONDONLY'])
            ->assertSessionHasErrors('voucher_code');

        $this->cart($buyer, $secondProduct, $secondProduct->variants()->sole());
        $this->actingAs($buyer)->withSession(['checkout_voucher_codes' => ['SECONDONLY']])
            ->get($this->selectedCartUrl())
            ->assertOk()
            ->assertSee('₱50.00 delivery per participating seller shipment.')
            ->assertSee('Voucher SECONDONLY')
            ->assertSee('−₱50.00', false)
            ->assertDontSee('You qualify for free shipping');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);

        return $user;
    }

    private function seller(User $owner, string $slug): Seller
    {
        return Seller::create([
            'user_id' => $owner->id, 'name' => $slug, 'slug' => $slug,
            'status' => 'approved', 'commission_bps' => 0,
        ]);
    }

    private function product(Seller $seller, string $slug, int $priceMinor): Product
    {
        $category = Category::firstOrCreate(['slug' => 'phase-eight'], ['name' => 'Phase Eight', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id,
            'name' => $slug, 'slug' => $slug, 'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        ProductVariant::create([
            'product_id' => $product->id, 'sku' => strtoupper($slug).'-SKU',
            'name' => 'Default', 'price_minor' => $priceMinor, 'stock' => 10, 'is_active' => true,
        ]);

        return $product;
    }

    private function cart(User $buyer, Product $product, ProductVariant $variant, int $quantity = 1): void
    {
        CartItem::create([
            'user_id' => $buyer->id, 'product_slug' => $product->slug,
            'product_name' => $product->name, 'product_variant_id' => $variant->id,
            'price' => 1, 'quantity' => $quantity,
        ]);
    }

    private function address(User $buyer): Address
    {
        return Address::create([
            'user_id' => $buyer->id, 'label' => 'Home', 'recipient' => $buyer->name,
            'phone' => '09171234567', 'line1' => 'Street', 'barangay' => 'Barangay',
            'city' => 'City', 'province' => 'Province', 'postal_code' => '1000', 'is_default' => true,
        ]);
    }
}
