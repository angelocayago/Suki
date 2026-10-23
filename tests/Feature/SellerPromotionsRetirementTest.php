<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerVoucher;
use App\Models\User;
use App\Support\SellerCategories;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SellerPromotionsRetirementTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->shop = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Retirement fixture', 'slug' => 'retirement-fixture', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->actingAs($this->shop->owner);
    }

    public function test_legacy_routes_and_view_are_absent_and_old_requests_leave_sessions_untouched(): void
    {
        foreach (['seller.promotions', 'seller.promotions.store', 'seller.promotions.destroy'] as $name) {
            $this->assertFalse(Route::has($name));
        }
        $this->assertFileDoesNotExist(resource_path('views/seller/promotions.blade.php'));
        $session = ['seller_promotions' => ['PROMO-0001' => ['name' => 'Inert old configuration']],
            'seller_products' => ['saved' => ['name' => 'Preserved catalogue projection']], 'cart' => ['buyer-line' => 2], 'unrelated' => 'keep'];
        $this->withSession($session);
        foreach ([['GET', '/seller/promotions'], ['POST', '/seller/promotions'], ['DELETE', '/seller/promotions/PROMO-0001']] as [$method, $url]) {
            $this->call($method, $url, ['name' => 'Must not save', 'value' => 10])->assertNotFound()->assertSessionHasAll($session);
        }
    }

    public function test_voucher_navigation_and_breadcrumb_have_no_legacy_destination(): void
    {
        $response = $this->get(route('seller.vouchers.index'))->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"/seller/promotions")]')->length);
        $this->assertSame(0, $xpath->query('//aside//a[@aria-label="Promotions"]')->length);
        $voucherLinks = $xpath->query('//aside//a[@href="'.route('seller.vouchers.index').'"]');
        $this->assertSame(1, $voucherLinks->length);
        $this->assertSame('page', $voucherLinks->item(0)->getAttribute('aria-current'));
        $this->assertSame('Vouchers', trim($voucherLinks->item(0)->textContent));
        $breadcrumb = $xpath->query('//nav[@aria-label="Breadcrumb"]')->item(0);
        $this->assertSame('Dashboard / Vouchers', trim($breadcrumb->textContent));
        foreach (['seller.orders', 'seller.products', 'seller.inventory', 'seller.sales', 'seller.reports', 'seller.store.profile', 'seller.profile', 'buyer.home'] as $name) {
            $this->assertGreaterThan(0, $xpath->query('//aside//a[@href="'.route($name).'"]')->length, $name);
        }
        $this->assertSame(1, $xpath->query('//aside//form[@action="'.route('logout').'"]')->length);
    }

    public function test_remaining_voucher_pages_and_retired_urls_do_not_mutate_existing_business_records(): void
    {
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id,
            'name' => 'Existing sale product', 'slug' => 'retirement-sale', 'is_active' => true,
            'seller_listing_data' => ['regular_price' => 125, 'sale_price' => 100, 'on_sale' => true]]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'RETIREMENT-SKU', 'price_minor' => 10000, 'stock' => 7, 'is_active' => true]);
        $voucher = SellerVoucher::create(['seller_id' => $this->shop->id, 'name' => 'Saved voucher', 'code' => 'KEEP-SAVED', 'discount_type' => 'percentage',
            'discount_bps' => 1000, 'minimum_minor' => 100, 'cap_minor' => 5000, 'product_scope' => 'selected', 'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(), 'total_limit' => 10, 'per_buyer_limit' => 1, 'status' => 'paused']);
        $voucher->products()->attach($product->id);
        $order = Order::create(['seller_id' => $this->shop->user_id, 'buyer_id' => User::factory()->create()->id,
            'order_number' => 'RETIREMENT-ORDER', 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Snapshot', 'recipient_phone' => '123', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test',
            'street_address' => 'Test', 'total_amount' => 100, 'shipping_fee' => 0]);
        $group = $order->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 10000]);
        $order->items()->create(['seller_order_id' => $group->id, 'seller_id' => $this->shop->user_id, 'product_name' => 'Saved item',
            'product_slug' => $product->slug, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 10000, 'status' => 'pending']);
        $provider = DB::table('logistics_providers')->insertGetId(['user_id' => $this->shop->user_id, 'name' => 'Fixture', 'slug' => 'retirement-provider', 'status' => 'approved']);
        $shipment = DB::table('shipments')->insertGetId(['seller_order_id' => $group->id, 'logistics_provider_id' => $provider, 'tracking_code' => 'RETIREMENT-TRACK', 'status' => 'assigned', 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);
        DB::table('delivery_events')->insert(['shipment_id' => $shipment, 'status' => 'assigned', 'attempt' => 0, 'user_id' => $this->shop->user_id, 'occurred_at' => now()]);
        $tables = ['products', 'product_variants', 'orders', 'order_items', 'seller_orders', 'seller_vouchers', 'seller_voucher_products', 'payments', 'shipments', 'delivery_events', 'sellers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $session = ['seller_promotions' => ['legacy' => 'inert'], 'seller_products' => ['keep' => 'projection'], 'cart' => ['keep' => 1]];
        $this->withSession($session);
        foreach (['index', 'create', 'show', 'edit'] as $action) {
            $this->get(route('seller.vouchers.'.$action, in_array($action, ['show', 'edit']) ? $voucher->id : []))->assertOk()->assertSessionHasAll($session);
        }
        $this->post('/seller/promotions', ['name' => 'Ignored'])->assertNotFound()->assertSessionHasAll($session);
        $this->delete('/seller/promotions/legacy')->assertNotFound()->assertSessionHasAll($session);
        $this->assertSame($before, $snapshot());
    }
}
