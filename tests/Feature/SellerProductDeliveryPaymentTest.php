<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Support\SellerCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerProductDeliveryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        SellerCategories::populate();
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->shop = Seller::create(['user_id' => $owner->id, 'name' => 'Delivery fixture',
            'slug' => 'delivery-fixture', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($owner);
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Delivery product', 'description' => 'Product details',
            'subcategory' => SellerCategories::children($this->shop, SellerCategories::main($this->shop))->first()->id,
            'price' => 149, 'stock' => 7, 'status' => 'active', 'low_stock_threshold' => 4], $extra);
    }

    private function createProduct(array $extra = []): Product
    {
        $this->post(route('seller.products.store'), $this->payload(array_replace([
            'images' => [UploadedFile::fake()->createWithContent('product.png', file_get_contents(public_path('images/logo.png')))],
        ], $extra)))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        return Product::sole();
    }

    public function test_both_forms_show_informational_cod_without_shipping_or_payment_controls(): void
    {
        $product = $this->createProduct();
        foreach ([route('seller.products.create'), route('seller.products.edit', $product->id)] as $url) {
            $this->get($url)->assertOk()->assertSeeText('Payment Methods')
                ->assertSeeText('Cash on Delivery (COD)')->assertSeeText('Available at checkout')
                ->assertSeeText('Buyers pay when their orders are delivered.')
                ->assertDontSeeText('Shipping Options')->assertDontSeeText('SUKI Rider')
                ->assertDontSee('name="shipping_options', false)->assertDontSee('name="payment_method', false)
                ->assertDontSee('value="logistics_partner"', false)->assertDontSee('value="suki_rider"', false);
        }
    }

    public static function shippingInputs(): array
    {
        return [
            'no fields' => [[]],
            'rider' => [['shipping_options' => ['suki_rider']]],
            'both' => [['shipping_options' => ['suki_rider', 'logistics_partner']]],
            'unknown' => [['shipping_options' => ['forged-provider']]],
            'wrong type' => [['shipping_options' => 'forged-provider']],
            'administrative fields' => [['shipping_method' => 'forged', 'logistics_provider_id' => 9999,
                'rider_id' => 9999, 'tracking_code' => 'FORGED', 'seller_listing_data' => [
                    'shipping_options' => ['suki_rider'], 'payment_method' => 'wallet',
                ]]],
        ];
    }

    #[DataProvider('shippingInputs')]
    public function test_new_products_use_trusted_logistics_metadata_without_booking_or_payment_records(array $extra): void
    {
        $product = $this->createProduct($extra);
        $this->assertSame(['logistics_partner'], $product->seller_listing_data['shipping_options']);
        foreach (['payment_method', 'payment_methods', 'logistics_provider_id', 'rider_id', 'tracking_code', 'shipping_method'] as $key) {
            $this->assertArrayNotHasKey($key, $product->seller_listing_data);
        }
        $this->assertSame('S'.$this->shop->id.'-P'.$product->id, $product->variants->sole()->sku);
        $this->assertSame(7, $product->variants->sole()->stock);
        $this->assertNull($product->seller_listing_data['sale_price']);
        foreach (['orders', 'payments', 'shipments', 'logistics_providers'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public static function historicalShipping(): array
    {
        return ['rider' => [true, ['suki_rider']], 'partner' => [true, ['logistics_partner']],
            'both' => [true, ['suki_rider', 'logistics_partner']], 'empty' => [true, []],
            'null' => [true, null], 'missing' => [false, null], 'unknown legacy' => [true, 'legacy-unmapped']];
    }

    #[DataProvider('historicalShipping')]
    public function test_ordinary_and_forged_edits_preserve_historical_shipping_exactly(bool $present, mixed $shipping): void
    {
        $product = $this->createProduct();
        $metadata = array_replace($product->seller_listing_data, ['custom_legacy_note' => 'Keep this']);
        unset($metadata['shipping_options']);
        if ($present) {
            $metadata['shipping_options'] = $shipping;
        }
        $product->update(['seller_listing_data' => $metadata]);
        $variants = $product->variants->toArray();
        $images = $product->images->toArray();
        foreach ([[], ['shipping_options' => ['suki_rider'], 'shipping_method' => 'forged',
            'logistics_provider_id' => 9999, 'payment_method' => 'cod', 'payment_methods' => ['cod']]] as $extra) {
            $this->put(route('seller.products.update', $product->id), $this->payload($extra))->assertSessionHasNoErrors();
            $fresh = $product->fresh();
            $this->assertSame($metadata, $fresh->seller_listing_data);
            $this->assertSame($variants, $fresh->variants->toArray());
            $this->assertSame($images, $fresh->images->toArray());
        }
        $this->get(route('seller.products.edit', $product->id))->assertOk();
        $this->assertSame($metadata, $product->fresh()->seller_listing_data);
    }

    public static function unsupportedPayments(): array
    {
        return ['wallet' => [['payment_method' => 'wallet'], 'payment_method'], 'gcash seller configuration' => [['payment_method' => 'gcash'], 'payment_method'],
            'array instead of method' => [['payment_method' => ['cod']], 'payment_method'],
            'unsupported list' => [['payment_methods' => ['wallet']], 'payment_methods.0'],
            'extra method' => [['payment_methods' => ['cod', 'gcash']], 'payment_methods']];
    }

    #[DataProvider('unsupportedPayments')]
    public function test_unsupported_seller_payment_configuration_is_rejected_without_persisting_changes(array $extra, string $error): void
    {
        $this->post(route('seller.products.store'), $this->payload($extra + [
            'images' => [UploadedFile::fake()->createWithContent('product.png', file_get_contents(public_path('images/logo.png')))],
        ]))->assertSessionHasErrors($error);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $product = $this->createProduct();
        $before = $product->fresh(['variants', 'images'])->toArray();
        $this->put(route('seller.products.update', $product->id), $this->payload($extra))->assertSessionHasErrors($error);
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
    }

    public function test_cod_submission_does_not_add_product_payment_fields_and_drafts_get_delivery_default(): void
    {
        $product = $this->createProduct(['payment_method' => 'cod', 'payment_methods' => ['cod'], 'on_sale' => 1, 'sale_price' => 99]);
        $this->assertSame(9900, $product->variants->sole()->price_minor);
        $this->assertArrayNotHasKey('payment_method', $product->seller_listing_data);
        $this->assertArrayNotHasKey('payment_methods', $product->seller_listing_data);
        $this->post(route('seller.products.store'), ['submit_action' => 'draft'])->assertSessionHasNoErrors();
        $draft = Product::latest('id')->first();
        $this->assertSame(['logistics_partner'], $draft->seller_listing_data['shipping_options']);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_new_defaults_do_not_bypass_category_ownership_or_approval(): void
    {
        $product = $this->createProduct();
        $before = $product->fresh(['variants', 'images'])->toArray();
        $this->put(route('seller.products.update', $product->id), $this->payload(['subcategory' => 999999]))->assertSessionHasErrors('subcategory');
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        Seller::create(['user_id' => $other->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($other)->put(route('seller.products.update', $product->id), $this->payload())->assertNotFound();
        $this->assertSame($before, $product->fresh(['variants', 'images'])->toArray());
        $this->actingAs($this->shop->owner);
        $this->shop->update(['status' => 'pending']);
        $this->get(route('seller.products.create'))->assertForbidden();
    }

    public function test_existing_buyer_checkout_accepts_cod_as_an_order_choice_without_product_payment_metadata(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
        $buyer->cartItems()->create(['product_slug' => 'checkout-fixture', 'product_name' => 'Checkout fixture',
            'price' => 149, 'quantity' => 1, 'image' => null]);
        $this->actingAs($buyer)->withSession(['buyer_addresses' => [[
            'id' => 1, 'name' => 'Fixture Buyer', 'phone' => '09123456789', 'province' => 'Fixture Province',
            'municipality' => 'Fixture City', 'barangay' => 'Fixture Barangay', 'street' => 'Fixture Street',
        ]]])->post(route('buyer.checkout.place-order'), ['address_id' => 1, 'payment_method' => 'cod'])
            ->assertSessionHasNoErrors()->assertSessionMissing('error');
        $order = Order::sole();
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('UNPAID', $order->payment_status);
        $this->assertSame('suki_logistics', $order->shipping_method);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('shipments', 0);
    }
}
