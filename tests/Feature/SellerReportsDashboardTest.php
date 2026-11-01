<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\SellerOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** In-memory fixtures only. No application migrations or production writes. */
class SellerReportsDashboardTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'Refuse fixture writes outside memory.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $schema = [
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, email TEXT, password TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, status TEXT, created_at TEXT, updated_at TEXT',
            'orders'=>'id INTEGER PRIMARY KEY, order_number TEXT, reference TEXT, buyer_id INTEGER, status TEXT, recipient_name TEXT, recipient_phone TEXT, payment_method TEXT, payment_status TEXT, shipping_address TEXT, house_number TEXT, street_address TEXT, barangay TEXT, municipality TEXT, province TEXT, postal_code TEXT, created_at TEXT, updated_at TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_id INTEGER, workflow_status TEXT, status TEXT, subtotal_minor INTEGER, shipping_fee_minor INTEGER, completed_at TEXT, created_at TEXT, updated_at TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_order_id INTEGER, product_id INTEGER, product_slug TEXT, image TEXT, commission_minor INTEGER, product_name TEXT, variant_name TEXT, variation TEXT, quantity INTEGER, unit_price_minor INTEGER, line_total NUMERIC',
            'shipments'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, workflow_status TEXT, status TEXT, tracking_code TEXT, cod_collected INTEGER DEFAULT 0',
            'delivery_events'=>'id INTEGER PRIMARY KEY, shipment_id INTEGER, status TEXT, note TEXT, occurred_at TEXT',
            'voucher_redemptions'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, scope_snapshot TEXT, discount_minor INTEGER',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, name TEXT, deleted_at TEXT',
            'seller_wallet_entries'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, payout_id INTEGER, type TEXT, status TEXT, amount_minor INTEGER',
            'payouts'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, status TEXT, amount_minor INTEGER',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
        ];
        foreach ($schema as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        DB::table('products')->insert([['id'=>1,'seller_id'=>101,'name'=>'Blue Widget'],['id'=>2,'seller_id'=>202,'name'=>'Foreign Widget'],['id'=>3,'seller_id'=>101,'name'=>'Red Widget']]);
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Seller Fixture','role'=>'seller','status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Owned shop','status'=>'approved'],['id'=>202,'user_id'=>22,'name'=>'Foreign shop','status'=>'approved']]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function sale(int $id, string $status='COMPLETED', string $date='2026-11-01 04:00:00', int $seller=101, int $units=2, int $price=4000): void
    {
        DB::table('orders')->insert(['id'=>$id,'order_number'=>'REPORT-'.$id,'status'=>$status]);
        DB::table('seller_orders')->insert(['id'=>$id,'order_id'=>$id,'seller_id'=>$seller,'workflow_status'=>$status,'status'=>OrderStatus::tryFrom($status)?->legacySellerStatus() ?? 'cancelled','subtotal_minor'=>$units*$price,'shipping_fee_minor'=>500,'completed_at'=>$status==='COMPLETED'?$date:null,'created_at'=>'2026-01-01 00:00:00','updated_at'=>$date]);
        DB::table('order_items')->insert(['id'=>$id*10,'order_id'=>$id,'seller_order_id'=>$id,'product_id'=>$seller===101?1:2,'product_slug'=>'snapshot-'.$id,'product_name'=>$seller===101?'Historical Blue Widget':'FOREIGN SECRET','quantity'=>$units,'unit_price_minor'=>$price,'commission_minor'=>1000,'image'=>'products/historical-blue.webp']);
    }

    private function report(array $query=[])
    {
        return $this->actingAs($this->seller)->get(route('seller.reports',$query))->assertOk();
    }

    public function test_dashboard_is_real_owned_empty_and_has_no_sample_charts(): void
    {
        $this->sale(99,seller:202);
        $response=$this->actingAs($this->seller)->withSession(['orders'=>[['status'=>'completed','total'=>999999]],'seller_products'=>[['name'=>'POISON']]])->get(route('seller.reports'));
        $response->assertOk()->assertSee('Sales Reports')->assertSee('Raw Completed Sales')->assertSee('Product Commission')->assertSee('Seller-Funded Discounts')->assertSee('Net Sales')
            ->assertSee('No sales data available for this period.')->assertSee('Current Wallet Overview')->assertSee('aria-expanded="false"',false)->assertDontSee('POISON')->assertDontSee('FOREIGN SECRET')
            ->assertDontSee('<polyline',false)->assertDontSee('reports-badge">1',false);
        $data=$response->viewData('data');
        $this->assertSame(0,$data['summary']['raw_minor']); $this->assertSame(0,$data['summary']['net_minor']);
        $this->assertSame(0,$data['completed_orders']); $this->assertSame(0,$data['units_sold']);
        $this->assertSame([],$data['sales_chart']['points']);
        $this->assertSame(0,array_sum(array_column($data['status_chart'],'count')));
    }

    public function test_financial_query_adapter_matches_unchanged_report_service_and_snapshots(): void
    {
        $this->sale(1); $this->sale(2,'DELIVERED'); $this->sale(3); $this->sale(99,seller:202);
        DB::table('seller_orders')->where('id',3)->update(['status'=>'cancelled']);
        DB::table('voucher_redemptions')->insert([['id'=>1,'seller_order_id'=>1,'scope_snapshot'=>'SELLER','discount_minor'=>1500],['id'=>2,'seller_order_id'=>1,'scope_snapshot'=>'PLATFORM','discount_minor'=>500],['id'=>3,'seller_order_id'=>99,'scope_snapshot'=>'SELLER','discount_minor'=>99999]]);
        // Multiple items and voucher rows cannot multiply sales or commissions.
        DB::table('order_items')->insert(['id'=>11,'order_id'=>1,'seller_order_id'=>1,'product_id'=>3,'product_name'=>'Historical Red Widget','quantity'=>1,'unit_price_minor'=>2000,'commission_minor'=>100]);
        DB::table('products')->where('id',1)->update(['name'=>'Edited Name','deleted_at'=>'2026-11-01']);
        $data=$this->report()->viewData('data');
        $reference=app(\App\Services\ReportService::class)->seller(\App\Models\Seller::find(101),null,null);
        $this->assertSame($reference['summary'],$data['summary']);
        foreach(['completed_orders','cancelled_orders','units_sold'] as $key) $this->assertSame($reference[$key],$data[$key]);
        $this->assertSame(['raw_minor'=>10000,'commission_minor'=>1100,'seller_discount_minor'=>1500,'platform_discount_minor'=>500,'shipping_minor'=>500,'net_minor'=>7400],$data['summary']);
        $this->assertSame(10000,array_sum(array_column($data['sales_chart']['points'],'raw_minor')));
        $this->assertSame('Historical Blue Widget',$data['products'][0]->name);
        $this->report()->assertSee('products/historical-blue.webp')->assertDontSee('Edited Name')->assertDontSee('FOREIGN SECRET');
        $counts=array_column($data['status_chart'],'count','key');
        $this->assertSame(1,$counts['completed']); $this->assertSame(1,$counts['pending']); $this->assertSame(1,$counts['cancelled']);
    }

    public function test_manila_day_boundaries_include_fractional_end_of_day_and_use_completion_dates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 00:30:00','Asia/Manila'));
        foreach(['2026-10-31 15:59:59','2026-10-31 16:00:00','2026-11-01 15:59:59.999999','2026-11-01 16:00:00','2026-10-25 16:00:00','2026-10-25 15:59:59','2026-10-02 16:00:00','2026-10-02 15:59:59'] as $i=>$date) $this->sale($i+1,date:$date);
        foreach(['today'=>2,'7d'=>4,'30d'=>6] as $preset=>$count) {
            $data=$this->report(['date'=>$preset])->viewData('data');
            $this->assertSame($count,$data['completed_orders']);
            $this->assertSame($count*8000,$data['summary']['raw_minor']);
            $this->assertSame($count*8000,array_sum(array_column($data['sales_chart']['points'],'raw_minor')));
        }
        $today=$this->report(['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-01'])->viewData('data');
        $this->assertSame(2,$today['completed_orders']);
        $this->assertSame('Nov 01, 2026',$today['sales_chart']['points'][0]['label']);
    }

    public function test_invalid_periods_are_rejected_and_legacy_date_links_remain_compatible(): void
    {
        foreach([
            [['date'=>'custom'],'from'], [['date'=>'custom','from'=>'2026-02-30','to'=>'2026-11-01'],'from'],
            [['date'=>'custom','from'=>'2026-11-02','to'=>'2026-11-01'],'to'], [['date'=>'fake'],'date'], [['page'=>0],'page'],
        ] as [$query,$error]) $this->actingAs($this->seller)->from(route('seller.reports'))->get(route('seller.reports',$query))->assertSessionHasErrors($error);
        $this->sale(1,date:'2026-10-31 16:00:00'); $this->sale(2,date:'2026-11-02 00:00:00');
        $this->assertSame(1,$this->report(['from'=>'2026-11-01','to'=>'2026-11-01'])->viewData('data')['completed_orders']);
        $this->assertSame(1,$this->report(['from'=>'2026-11-02'])->viewData('data')['completed_orders']);
        $this->report(['date'=>'any','from'=>'junk','to'=>'junk','product'=>2])->assertViewHas('period',fn($p)=>$p['query']===[]);
    }

    public function test_series_has_only_real_sales_and_zero_calendar_gaps_and_adapts_granularity(): void
    {
        $this->sale(1,date:'2026-11-01 04:00:00'); $this->sale(2,date:'2026-11-03 04:00:00');
        $chart=$this->report(['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-03'])->viewData('data')['sales_chart'];
        $this->assertSame([8000,0,8000],array_column($chart['points'],'raw_minor'));
        foreach([['2026-01-01','2026-12-31','week'],['2024-01-01','2026-12-31','month'],['1900-01-01','2026-12-31','3-year']] as [$from,$to,$granularity]) {
            $data=$this->report(['date'=>'custom','from'=>$from,'to'=>$to])->viewData('data');
            $this->assertSame($granularity,$data['sales_chart']['granularity']);
            $this->assertLessThanOrEqual(63,count($data['sales_chart']['points']));
            $this->assertSame($data['summary']['raw_minor'],array_sum(array_column($data['sales_chart']['points'],'raw_minor')));
        }
    }

    public function test_undated_completed_orders_are_not_assigned_fake_chart_dates(): void
    {
        $this->sale(1); DB::table('seller_orders')->where('id',1)->update(['completed_at'=>null]);
        $response=$this->report()->assertSee('no recorded completion date')->assertDontSee('<polyline',false);
        $data=$response->viewData('data');
        $this->assertSame(8000,$data['summary']['raw_minor']); $this->assertSame([],$data['sales_chart']['points']);
        $this->assertSame(0,$this->report(['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-01'])->viewData('data')['completed_orders']);
    }

    public function test_order_status_chart_is_disjoint_and_preserves_operational_date_contract(): void
    {
        foreach(['COMPLETED','DELIVERED','PLACED','CANCELLED','DELIVERY_FAILED','RTS_COMPLETED','RTS_RETURNING','RTS_INITIATED'] as $i=>$status) $this->sale($i+1,$status);
        // Historical completion stays in this period even if the record changes later.
        DB::table('seller_orders')->where('id',1)->update(['updated_at'=>'2026-12-01']);
        $data=$this->report(['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-01'])->viewData('data');
        $this->assertSame(['completed'=>1,'pending'=>2,'cancelled'=>1,'returned'=>1,'failed'=>1,'returning'=>2],array_column($data['status_chart'],'count','key'));
        $this->assertSame(8,array_sum(array_column($data['status_chart'],'count')));
        $next=$this->report(['date'=>'custom','from'=>'2026-12-01','to'=>'2026-12-01'])->viewData('data');
        $this->assertSame(0,array_sum(array_column($next['status_chart'],'count')));
    }

    public function test_current_wallet_is_real_ledger_data_and_does_not_follow_report_dates(): void
    {
        DB::table('payouts')->insert(['id'=>51,'seller_id'=>101,'status'=>'PAID','amount_minor'=>5000]);
        DB::table('seller_wallet_entries')->insert([
            ['id'=>1,'seller_id'=>101,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>15000],
            ['id'=>2,'seller_id'=>101,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-1000],
            ['id'=>3,'seller_id'=>101,'type'=>'PAYOUT_RESERVATION','status'=>'RESERVED','amount_minor'=>2000],
            ['id'=>4,'seller_id'=>101,'type'=>'SALE_EARNING','status'=>'HELD','amount_minor'=>3000],
            ['id'=>5,'seller_id'=>101,'type'=>'PAYOUT_COMPLETED','status'=>'POSTED','amount_minor'=>5000],
            ['id'=>6,'seller_id'=>202,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>999999],
        ]);
        DB::table('seller_wallet_entries')->where('id',5)->update(['payout_id'=>51]);
        $all=$this->report()->viewData('data')['wallet'];
        $filtered=$this->report(['date'=>'custom','from'=>'2000-01-01','to'=>'2000-01-01'])->viewData('data')['wallet'];
        $this->assertSame($all,$filtered); $this->assertSame(7000,$all['available_minor']);
        $this->assertSame(3000,$all['held_minor']); $this->assertSame(2000,$all['reserved_minor']); $this->assertSame(5000,$all['paid_out_minor']);
        $this->assertSame(6,DB::table('seller_wallet_entries')->count());
    }

    public function test_paid_payout_changes_wallet_without_changing_historical_sales_or_export(): void
    {
        $this->sale(1);
        DB::table('seller_wallet_entries')->insert(['id'=>1,'seller_id'=>101,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>7000]);
        $before=$this->report()->viewData('data');
        $csv=$this->get(route('seller.reports',['export'=>1]))->assertOk()->getContent();
        DB::table('payouts')->insert(['id'=>1,'seller_id'=>101,'status'=>'PAID','amount_minor'=>2000]);
        DB::table('seller_wallet_entries')->insert(['id'=>2,'seller_id'=>101,'payout_id'=>1,'type'=>'PAYOUT_COMPLETED','status'=>'POSTED','amount_minor'=>2000]);
        $after=$this->report()->viewData('data');
        $this->assertSame(7000,$before['wallet']['available_minor']);
        $this->assertSame(5000,$after['wallet']['available_minor']);
        $this->assertSame(2000,$after['wallet']['paid_out_minor']);
        $this->assertSame(7000,$after['summary']['net_minor']);
        foreach (['summary','sales_chart','status_chart','completed_orders','units_sold'] as $key) $this->assertSame($before[$key],$after[$key]);
        $this->assertSame($csv,$this->get(route('seller.reports',['export'=>1]))->assertOk()->getContent());
    }

    public function test_product_rankings_preserve_snapshots_stable_ties_and_paginated_period_links(): void
    {
        foreach(range(1,12) as $id) {
            $this->sale($id); DB::table('order_items')->where('id',$id*10)->update(['product_id'=>$id+10,'product_name'=>'Snapshot '.$id]);
        }
        $response=$this->report(['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-01']);
        $data=$response->viewData('data');
        $this->assertSame(12,$data['products']->total()); $this->assertSame(10,$data['products']->count());
        $this->assertSame('Snapshot 1',$data['products'][0]->name);
        $next=$data['products']->nextPageUrl();
        foreach(['date=custom','from=2026-11-01','to=2026-11-01','page=2'] as $value) $this->assertStringContainsString($value,$next);
        $this->get($next)->assertOk()->assertViewHas('data',fn($d)=>$d['products']->count()===2 && $d['summary']['raw_minor']===96000);
        $response->assertSee('reports-badge">1',false)->assertSee('Remove date filter:')->assertSee(route('seller.reports'));
        $this->assertSame([],$this->report()->viewData('period')['query']);
    }

    public function test_csv_exports_every_ranked_product_in_period_with_unchanged_columns_and_safety(): void
    {
        foreach(range(1,12) as $id) { $this->sale($id); DB::table('order_items')->where('id',$id*10)->update(['product_id'=>$id+10,'product_name'=>$id===1?'=FORMULA':'Snapshot '.$id]); }
        $this->sale(50,date:'2026-12-01'); $this->sale(99,seller:202);
        $response=$this->actingAs($this->seller)->get(route('seller.reports',['from'=>'2026-11-01','to'=>'2026-11-01','export'=>1,'page'=>2]));
        $response->assertOk()->assertHeader('Content-Type','text/csv; charset=UTF-8')->assertHeader('Content-Disposition','attachment; filename="seller-sales-report.csv"')->assertHeader('X-Content-Type-Options','nosniff');
        $rows=array_map('str_getcsv',explode("\n",trim($response->getContent())));
        $this->assertSame(['name','units','raw_minor'],$rows[0]); $this->assertCount(13,$rows);
        $this->assertSame(["'=FORMULA",'2','8000'],$rows[1]);
        $this->assertStringNotContainsString('FOREIGN',$response->getContent());
        $this->assertStringNotContainsString('Historical Blue',$response->getContent());
    }

    public function test_populated_charts_render_accessible_svg_and_existing_assets_without_new_entries(): void
    {
        $this->sale(1);
        $response=$this->report()->assertSee('<polyline',false)->assertSee('Raw Completed Sales in PHP')->assertSee('View chart data')->assertSee('data-report-point',false)->assertSee('PHP 80.00');
        if ($dir=getenv('SELLER_REPORT_CHECK_DIR')) file_put_contents($dir.'/reports.html',$response->getContent());
        $this->assertSame('80.00',\App\Support\SellerReports::money(8000));
    }

    public function test_report_and_export_are_authenticated_and_seller_scoped(): void
    {
        $route=app('router')->getRoutes()->getByName('seller.reports');
        $this->assertSame('seller/reports',$route->uri());
        foreach(['web','auth','not_suspended','approved','role:seller'] as $guard) $this->assertContains($guard,$route->gatherMiddleware());
        $this->get(route('seller.reports'))->assertRedirect(route('login'));
        $this->sale(1); $this->sale(99,seller:202);
        $this->assertSame(1,$this->report(['seller_id'=>202])->viewData('data')['completed_orders']);
        DB::table('sellers')->where('id',101)->update(['status'=>'pending']);
        $this->get(route('seller.reports',['export'=>1]))->assertNotFound();
    }

    public function test_cards_chart_ranking_csv_and_wallet_respond_to_actual_record_changes(): void
    {
        $this->sale(1);
        $this->assertSame(8000,$this->report()->viewData('data')['summary']['raw_minor']);
        DB::table('order_items')->where('id',10)->update(['quantity'=>3,'unit_price_minor'=>3501,'commission_minor'=>231]);
        DB::table('voucher_redemptions')->insert(['id'=>1,'seller_order_id'=>1,'scope_snapshot'=>'SELLER','discount_minor'=>222]);
        DB::table('seller_wallet_entries')->insert(['id'=>1,'seller_id'=>101,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>744]);
        $data=$this->report()->viewData('data');
        $this->assertSame(10503,$data['summary']['raw_minor']); $this->assertSame(10050,$data['summary']['net_minor']);
        $this->assertSame(10503,array_sum(array_column($data['sales_chart']['points'],'raw_minor')));
        $this->assertSame(3,$data['units_sold']); $this->assertSame(10503,(int)$data['products'][0]->raw_minor);
        $this->assertSame(744,$data['wallet']['available_minor']);
        $csv=$this->get(route('seller.reports',['export'=>1]))->assertOk()->getContent();
        $this->assertSame(['Historical Blue Widget','3','10503'],str_getcsv(explode("\n",trim($csv))[1]));
    }

    public function test_dashboard_and_csv_issue_only_read_queries_with_bounded_page_and_chart_results(): void
    {
        foreach(range(1,30) as $id) { $this->sale($id); DB::table('order_items')->where('id',$id*10)->update(['product_id'=>$id+10]); }
        DB::enableQueryLog();
        try {
            $data=$this->report()->viewData('data');
            $this->assertSame(10,$data['products']->count());
            $this->assertSame(30,$data['products']->total());
            $this->get(route('seller.reports',['export'=>1]))->assertOk();
            foreach(DB::getQueryLog() as $query) $this->assertMatchesRegularExpression('/^select\b/i',ltrim($query['query']));
            $this->assertLessThan(40,count(DB::getQueryLog()));
        } finally { DB::disableQueryLog(); DB::flushQueryLog(); }
    }
}
