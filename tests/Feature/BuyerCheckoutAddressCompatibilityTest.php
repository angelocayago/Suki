<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, LogisticsHub, LogisticsProvider, Order, Product, ProductVariant, Rider, RiderServiceArea, Seller, User, Voucher};
use App\Services\{LogisticsHubEligibilityService, RiderServiceAreaEligibilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\{FakesCheckoutLocations, IsolatedStorageTestCase};

class BuyerCheckoutAddressCompatibilityTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, FakesCheckoutLocations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory required.');
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
        Storage::fake('public');
        $this->fakeCheckoutLocations();
    }

    private function fixture(bool $provinceLess = false): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Address Shop', 'slug' => 'address-shop', 'status' => 'approved', 'commission_bps' => 1000]);
        $category = Category::create(['name' => 'Address Fixtures', 'slug' => 'address-fixtures', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Address Product', 'slug' => 'address-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'ADDRESS-SKU', 'name' => 'Standard', 'price_minor' => 12500, 'stock' => 5, 'is_active' => true]);
        CartItem::create(['user_id' => $buyer->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'product_variant_id' => $variant->id, 'price' => 1, 'quantity' => 2]);
        $address = Address::create($this->checkoutLocationCodes($provinceLess) + ['user_id' => $buyer->id, 'label' => 'Home',
            'recipient' => 'Test Buyer', 'phone' => '09123456789', 'line1' => '17 Test Street',
            'region' => $provinceLess ? 'National Capital Region' : 'Region A', 'province' => $provinceLess ? null : 'Province A',
            'city' => $provinceLess ? 'NCR City' : 'City A', 'barangay' => $provinceLess ? 'NCR Barangay' : 'Barangay A', 'is_default' => true]);
        return compact('buyer', 'address', 'variant', 'product');
    }

    public static function addressTypes(): array { return [[false], [true]]; }

    #[DataProvider('addressTypes')]
    public function test_cod_checkout_preserves_ordinary_and_province_less_snapshots_totals_vouchers_and_inventory(bool $provinceLess): void
    {
        extract($this->fixture($provinceLess));
        $voucher = Voucher::create(['code' => 'ADDRESS10', 'scope' => 'PLATFORM', 'discount_type' => 'FIXED', 'discount_minor' => 1000, 'minimum_spend_minor' => 0, 'used_count' => 0, 'is_active' => true]);
        $this->actingAs($buyer)->get(route('buyer.checkout'))->assertOk()->assertViewHas('hasEligibleAddress', true)
            ->assertViewHas('defaultAddress', fn ($value) => $value['id'] === $address->id);
        $this->withSession(['checkout_voucher_codes' => ['ADDRESS10']])->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame($address->province, $order->province);
        foreach ($this->checkoutLocationCodes($provinceLess) as $field => $value) {
            $this->assertSame($value, $order->$field);
            $this->assertSame($value, $order->shipping_address[$field]);
        }
        $this->assertSame($address->province, $order->shipping_address['province']);
        $this->assertSame('17 Test Street', $order->street_address);
        $this->assertSame('PLACED', $order->status);
        $this->assertSame('UNPAID', $order->payment_status);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('250.00', $order->subtotal);
        $this->assertSame('50.00', $order->shipping_fee);
        $this->assertSame('10.00', $order->discount_amount);
        $this->assertSame(29000, $order->total_minor);
        $this->assertSame(29000, $order->sellerOrders->sole()->shipment->cod_amount_minor);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(0, $buyer->cartItems()->count());
        $this->assertDatabaseHas('voucher_redemptions', ['order_id' => $order->id, 'discount_minor' => 1000]);
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertSee('17 Test Street')->assertSee($address->city)->assertSee($address->barangay);

        // The exact snapshot remains compatible with existing delivery guards.
        $provider = LogisticsProvider::create(['user_id' => User::factory()->create(['role' => 'logistics'])->id, 'name' => 'Test Provider', 'slug' => 'address-provider', 'status' => 'approved']);
        $hub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'name' => 'Address Hub', 'region' => $address->region, 'city' => $address->city, 'barangay' => $address->barangay, 'region_code' => $order->region_code, 'city_municipality_code' => $order->city_municipality_code, 'barangay_code' => $order->barangay_code, 'is_active' => true]);
        $rider = Rider::create(['user_id' => User::factory()->create(['role' => 'rider'])->id, 'logistics_provider_id' => $provider->id, 'logistics_hub_id' => $hub->id, 'vehicle_type' => 'motorcycle', 'plate_no' => 'TEST-ADDR', 'is_active' => true]);
        RiderServiceArea::create(['rider_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'region_code' => $order->region_code, 'province_code' => $order->province_code, 'city_municipality_code' => $order->city_municipality_code, 'barangay_code' => $order->barangay_code, 'area_key' => RiderServiceArea::key($order->region_code, $order->province_code, $order->city_municipality_code, $order->barangay_code), 'is_active' => true]);
        app(RiderServiceAreaEligibilityService::class)->delivery($order->sellerOrders->sole(), $provider, $rider);
        app(LogisticsHubEligibilityService::class)->delivery($order->sellerOrders->sole(), $provider, $rider);
    }

    public function test_legacy_address_is_explained_rejected_without_writes_then_existing_editor_can_repair_it(): void
    {
        extract($this->fixture());
        $address->update(['region_code' => null, 'province_code' => null, 'city_municipality_code' => null, 'barangay_code' => null]);
        $this->actingAs($buyer)->get(route('buyer.checkout'))->assertOk()->assertSee('Update this address in Manage addresses')
            ->assertSee(route('buyer.addresses'), false)->assertViewHas('hasEligibleAddress', false);
        Http::assertNothingSent();
        $this->from(route('buyer.checkout'))->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0); $this->assertSame(5, $variant->fresh()->stock); $this->assertSame(1, $buyer->cartItems()->count());
        $this->get(route('buyer.addresses'))->assertOk()->assertSee('Edit');
        $this->patch(route('buyer.addresses.update', $address->id), ['name' => 'Test Buyer', 'phone' => '09123456789', 'street' => '17 Test Street', 'label' => 'Home', 'region' => '13', 'province' => null, 'municipality' => '130001', 'barangay' => '130001001'])->assertSessionHasNoErrors();
        $this->assertNull($address->fresh()->province);
        $this->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $this->assertNull(Order::sole()->province);
    }

    public function test_valid_address_is_selected_even_when_the_default_needs_updating(): void
    {
        extract($this->fixture(true));
        $legacy = $address->replicate(); $legacy->region_code = null; $legacy->save();
        $address->update(['is_default' => false]);
        $this->actingAs($buyer)->get(route('buyer.checkout'))->assertViewHas('hasEligibleAddress', true)
            ->assertViewHas('defaultAddress', fn ($value) => $value['id'] === $address->id);
    }

    public static function badLocations(): array
    {
        return [[['region_code' => null]], [['barangay_code' => 'old-label']], [['province_code' => null]], [['barangay_code' => '010101999']], [['city_municipality_code' => '130001']]];
    }

    #[DataProvider('badLocations')]
    public function test_missing_malformed_retired_and_mismatched_codes_never_create_orders(array $changes): void
    {
        extract($this->fixture()); $address->update($changes);
        $this->actingAs($buyer)->from(route('buyer.checkout'))->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0); $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('voucher_redemptions', 0); $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertSame(5, $variant->fresh()->stock); $this->assertSame(1, $buyer->cartItems()->count());
    }

    public function test_provider_outage_has_retry_feedback_and_does_not_treat_valid_address_as_missing(): void
    {
        extract($this->fixture(true));
        $this->fakeCheckoutLocations(['https://psgc.cloud/api/v2/regions' => Http::response([], 503)]);
        $this->actingAs($buyer)->from(route('buyer.checkout'))->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors(['address_id' => 'Delivery locations could not be verified right now. Please try again shortly. Your cart has not changed.']);
        $this->assertDatabaseCount('orders', 0); $this->assertSame(5, $variant->fresh()->stock);
        $this->fakeCheckoutLocations();
        $this->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
    }

    public function test_other_buyers_address_cannot_be_selected(): void
    {
        extract($this->fixture());
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->post(route('buyer.checkout.place-order'), ['address_id' => $address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0); Http::assertNothingSent();
    }

}
