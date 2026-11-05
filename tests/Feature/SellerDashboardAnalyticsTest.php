<?php

namespace Tests\Feature;

use App\Models\{Category, Order, OrderItem, Product, ProductVariant, Seller, SellerOrder, User, Voucher, VoucherRedemption};
use App\Support\{SellerDashboard, SellerReports};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Tests\Support\IsolatedStorageTestCase;

class SellerDashboardAnalyticsTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    private Seller $seller;
    private Seller $foreign;
    private User $buyer;
    private Product $product;
    private int $sequence = 0;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Only isolated in-memory SQLite is allowed.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName()); $this->assertSame(':memory:', DB::connection()->getDatabaseName()); $this->assertStoragePathIsIsolated();
        Storage::fake('public'); Mail::fake(); Notification::fake(); Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00', 'Asia/Manila'));
        $this->seller = Seller::create(['user_id'=>User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false])->id,'name'=>'Owned Shop','slug'=>'owned-shop','status'=>'approved']);
        $this->foreign = Seller::create(['user_id'=>User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false])->id,'name'=>'Foreign Shop','slug'=>'foreign-shop','status'=>'approved']);
        $this->buyer = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->product = Product::create(['seller_id'=>$this->seller->id,'category_id'=>Category::create(['name'=>'Test','slug'=>'test','is_active'=>true])->id,'name'=>'Owned Product','slug'=>'owned-product','is_active'=>true,'moderation_status'=>'APPROVED']);
        ProductVariant::create(['product_id'=>$this->product->id,'sku'=>'BASE','name'=>'Default','price_minor'=>10001,'stock'=>40,'is_active'=>true]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(); parent::tearDown();
    }

    private function sale(string $date='2026-11-05 16:00:00', string $status='COMPLETED', ?Seller $seller=null, ?Order $parent=null): SellerOrder
    {
        $seller ??= $this->seller; $this->sequence++;
        $parent ??= Order::create(['buyer_id'=>$this->buyer->id,'order_number'=>'DASH-'.$this->sequence,'status'=>$status,'payment_method'=>'cod','payment_status'=>'UNPAID','subtotal'=>1,'shipping_fee'=>50,'discount_amount'=>0,'total_amount'=>1,'total_minor'=>100,'recipient_name'=>'Saved Recipient','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test','shipping_address'=>[]]);
        $workflow=$status==='CANCELLED'?'PLACED':$status;
        $so=SellerOrder::create(['order_id'=>$parent->id,'seller_id'=>$seller->id,'workflow_status'=>$workflow,'status'=>$status==='CANCELLED'?'cancelled':\App\Enums\OrderStatus::from($workflow)->legacySellerStatus(),'subtotal_minor'=>999999,'shipping_fee_minor'=>5000,'completed_at'=>$status==='COMPLETED'?$date:null]);
        OrderItem::create(['order_id'=>$parent->id,'seller_order_id'=>$so->id,'seller_id'=>$seller->user_id,'product_id'=>$seller->id===$this->seller->id?$this->product->id:null,'product_slug'=>'historical-'.$this->sequence,'product_name'=>$seller->id===$this->seller->id?'Purchased snapshot':'FOREIGN SECRET','quantity'=>2,'unit_price'=>1,'unit_price_minor'=>10001,'commission_minor'=>901,'line_total'=>1]);
        return $so;
    }

    public function test_dashboard_uses_canonical_completed_money_and_owned_kpis_and_direct_order_links(): void
    {
        $so=$this->sale(); $this->sale(status:'DELIVERED'); $this->sale(status:'CANCELLED');
        $this->sale(seller:$this->foreign,parent:$so->order);
        foreach(['SELLER'=>1234,'PLATFORM'=>500] as $scope=>$amount) {
            $v=Voucher::create(['code'=>'DASH-'.$scope,'scope'=>$scope,'seller_id'=>$scope==='SELLER'?$this->seller->id:null,'discount_type'=>'FIXED','discount_minor'=>$amount,'is_active'=>true]);
            VoucherRedemption::create(['voucher_id'=>$v->id,'order_id'=>$so->order_id,'seller_order_id'=>$so->id,'buyer_id'=>$this->buyer->id,'code_snapshot'=>$v->code,'scope_snapshot'=>$scope,'seller_id_snapshot'=>$scope==='SELLER'?$this->seller->id:null,'discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>$amount,'discount_minor'=>$amount]);
        }
        $before=[]; foreach(['orders','seller_orders','order_items','voucher_redemptions','product_variants'] as $table)$before[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        $response=$this->actingAs($this->seller->owner)->get(route('seller.dashboard'))->assertOk()->assertSee('Sales Performance')->assertSee('Completed Sales Breakdown')->assertSee('₱200.02')->assertDontSee('FOREIGN SECRET')->assertSee(route('seller.orders.show',$so),false);
        $this->assertSame(1,$response->viewData('productCount')); $this->assertSame(3,$response->viewData('orderCount')); $this->assertSame(1,$response->viewData('customerCount'));
        $this->assertSame(['raw_sales_minor'=>20002,'commission_minor'=>901,'seller_funded_discount_minor'=>1234,'platform_funded_discount_minor'=>500,'net_sales_minor'=>17867],$response->viewData('completedSales'));
        $this->assertSame(20002,$response->viewData('salesSeries')['7d']['total_minor']);
        $reference=app(\App\Services\ReportService::class)->seller($this->seller,null,null);
        $this->assertSame($reference['summary']['raw_minor'],$response->viewData('completedSales')['raw_sales_minor']);
        $this->assertSame($reference['summary']['net_minor'],$response->viewData('completedSales')['net_sales_minor']);
        foreach($before as $table=>$snapshot)$this->assertSame($snapshot,DB::table($table)->orderBy('id')->get()->toJson());
    }

    public function test_daily_and_monthly_buckets_use_exact_manila_boundaries_and_zero_fill(): void
    {
        foreach(['2026-11-05 15:59:59','2026-11-05 16:00:00','2026-11-06 16:00:00','2026-10-07 16:00:00','2025-11-30 16:00:00','2025-11-30 15:59:59'] as $date)$this->sale($date);
        $data=app(SellerReports::class)->dashboardSeries($this->seller);
        $this->assertCount(7,$data['7d']['points']); $this->assertCount(30,$data['30d']['points']); $this->assertCount(12,$data['12m']['points']);
        $this->assertSame('2026-10-31',$data['7d']['from']); $this->assertSame('2026-10-08',$data['30d']['from']); $this->assertSame('2025-12-01',$data['12m']['from']);
        $this->assertSame(20002,$data['7d']['points'][5]['raw_minor']); $this->assertSame(20002,$data['7d']['points'][6]['raw_minor']);
        $this->assertSame(40004,$data['7d']['total_minor']); $this->assertSame(60006,$data['30d']['total_minor']); $this->assertSame(80008,$data['12m']['total_minor']);
        $this->assertSame(20002,$data['12m']['points'][0]['raw_minor']); $this->assertSame('month',$data['12m']['granularity']);
        $this->assertSame(0,$data['7d']['points'][0]['raw_minor']);
        DB::enableQueryLog(); app(SellerReports::class)->dashboardSeries($this->seller); $queries=DB::getQueryLog(); DB::disableQueryLog();
        $this->assertCount(2,$queries);
    }

    public function test_empty_dashboard_has_real_zero_states_and_accessible_range_controls(): void
    {
        $response=$this->actingAs($this->seller->owner)->get(route('seller.dashboard'))->assertOk()->assertSee('No completed merchandise sales in this range.')->assertSee('No orders yet.')->assertSee('Last 7 days')->assertSee('Last 30 days')->assertSee('Last 12 months')->assertSee('View exact sales data')->assertSee('aria-pressed="true"',false)->assertSee('data-sales-range="12m"',false)->assertSee('Asia/Manila');
        $this->assertSame(0,$response->viewData('orderCount')); $this->assertSame(0,$response->viewData('grossCompleted'));
        foreach($response->viewData('salesSeries') as $series)$this->assertSame(0,$series['total_minor']);
        $css=file_get_contents(resource_path('views/seller/dashboard-partials/style.blade.php'));
        $this->assertStringContainsString('html[data-theme=dark]',$css); $this->assertStringContainsString('@media(max-width:700px)',$css); $this->assertStringContainsString('focus-visible',$css);
        $this->assertSame(1,substr_count($response->getContent(),'>Add Product<'));
    }

    public function test_status_counts_include_unknown_states_and_links_use_supported_filters(): void
    {
        foreach(['COMPLETED','DELIVERED','PREPARING','CANCELLED','DELIVERY_FAILED','RTS_COMPLETED'] as $status)$this->sale(status:$status);
        $unknown=$this->sale(status:'PLACED'); DB::table('seller_orders')->where('id',$unknown->id)->update(['workflow_status'=>'UNRECOGNIZED']);
        $response=$this->actingAs($this->seller->owner)->get(route('seller.dashboard'))->assertOk()->assertSee('Unknown workflow')->assertSee('Cancelled / Failed / RTS');
        $this->assertSame(7,$response->viewData('orderCount')); $this->assertSame(1,$response->viewData('statusCounts')['unknown']);
        foreach(['COMPLETED','DELIVERED','PREPARING','CANCELLED','DELIVERY_FAILED','RTS_COMPLETED'] as $status)$response->assertSee(route('seller.orders',['status'=>$status]),false);
        $response->assertDontSee('status=UNRECOGNIZED',false);
    }

    public function test_inventory_alerts_use_active_variant_stock_and_existing_thumbnail_fallback(): void
    {
        ProductVariant::create(['product_id'=>$this->product->id,'sku'=>'LOW','name'=>'Flavor / Pack of 6','price_minor'=>100,'stock'=>5,'is_active'=>true,'image_path'=>'variants/low.jpg']);
        ProductVariant::create(['product_id'=>$this->product->id,'sku'=>'OUT','name'=>'Other flavor','price_minor'=>100,'stock'=>0,'is_active'=>true]);
        ProductVariant::create(['product_id'=>$this->product->id,'sku'=>'INACTIVE','name'=>'Inactive variant','price_minor'=>100,'stock'=>0,'is_active'=>false]);
        Storage::disk('public')->put('variants/low.jpg','isolated image fixture');
        $response=$this->actingAs($this->seller->owner)->get(route('seller.dashboard'))->assertOk()->assertSee('Flavor / Pack of 6')->assertSee('5 left')->assertSee('Out of stock')->assertDontSee('Inactive variant')->assertSee('variants/low.jpg');
        $this->assertSame(1,$response->viewData('lowStockProducts')); $this->assertSame(2,$response->viewData('lowStockVariants'));
        $this->assertSame(45,$response->viewData('lowStockList')[0]['stock']);
    }

    public function test_recent_orders_and_top_products_are_bounded_read_only_and_seller_scoped(): void
    {
        for($i=0;$i<8;$i++)$this->sale(); $this->sale(seller:$this->foreign);
        DB::enableQueryLog();
        try {
            $response=$this->actingAs($this->seller->owner)->get(route('seller.dashboard'))->assertOk()->assertDontSee('FOREIGN SECRET');
            $this->assertCount(5,$response->viewData('recentOrders')); $this->assertLessThanOrEqual(5,$response->viewData('topProducts')->count());
            foreach(DB::getQueryLog() as $query)$this->assertMatchesRegularExpression('/^select\b/i',ltrim($query['query']));
            $this->assertLessThan(40,count(DB::getQueryLog()));
        } finally {DB::disableQueryLog(); DB::flushQueryLog();}
        $this->actingAs($this->buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->assertDatabaseCount('seller_orders',9);
    }
}
