<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\SellerOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** In-memory fixtures only. No application migrations or production writes. */
class SellerSalesFiltersTest extends TestCase
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
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_id INTEGER, workflow_status TEXT, status TEXT, subtotal_minor INTEGER, shipping_fee_minor INTEGER, created_at TEXT, updated_at TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_order_id INTEGER, product_id INTEGER, product_name TEXT, variant_name TEXT, variation TEXT, quantity INTEGER, unit_price_minor INTEGER, line_total NUMERIC',
            'shipments'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, workflow_status TEXT, status TEXT, tracking_code TEXT, cod_collected INTEGER DEFAULT 0',
            'delivery_events'=>'id INTEGER PRIMARY KEY, shipment_id INTEGER, status TEXT, note TEXT, occurred_at TEXT',
            'voucher_redemptions'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, discount_minor INTEGER',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, name TEXT, deleted_at TEXT',
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

    private function sale(int $id, string $status='PLACED', int $amount=10000, string $date='2026-11-01 04:00:00', int $seller=101, ?int $parent=null): void
    {
        $parent ??= $id;
        if (!DB::table('orders')->where('id',$parent)->exists()) {
            DB::table('orders')->insert(['id'=>$parent,'order_number'=>'SALE-'.$parent,'reference'=>'REF-'.$parent,'status'=>$status,'recipient_name'=>'Customer '.$parent,'payment_method'=>'cod','payment_status'=>'UNPAID']);
        }
        DB::table('seller_orders')->insert(['id'=>$id,'order_id'=>$parent,'seller_id'=>$seller,'workflow_status'=>$status,'status'=>OrderStatus::tryFrom($status)?->legacySellerStatus() ?? 'cancelled','subtotal_minor'=>$amount-500,'shipping_fee_minor'=>500,'created_at'=>$date,'updated_at'=>$date]);
        DB::table('order_items')->insert(['id'=>$id*10,'order_id'=>$parent,'seller_order_id'=>$id,'product_id'=>$seller===101?1:2,'product_name'=>$seller===101?'Blue Widget':'FOREIGN SECRET','quantity'=>2,'unit_price_minor'=>1000]);
        DB::table('shipments')->insert(['id'=>$id,'seller_order_id'=>$id,'workflow_status'=>$status,'status'=>OrderStatus::tryFrom($status)?->legacyShipmentStatus() ?? 'cancelled','cod_collected'=>0]);
    }

    private function listing(array $query=[])
    {
        return $this->actingAs($this->seller)->get(route('seller.sales',$query))->assertOk();
    }

    private function ids(array $query=[]): array
    {
        return $this->listing($query+['sort'=>'oldest'])->viewData('paginator')->pluck('id')->all();
    }

    public function test_default_rendering_is_collapsed_real_owned_and_uses_the_manifest(): void
    {
        $this->sale(1,'COMPLETED'); $this->sale(2,'PLACED'); $this->sale(99,'COMPLETED',900000,seller:202);
        $response=$this->actingAs($this->seller)->withSession(['orders'=>[['id'=>'POISON','status'=>'completed','total'=>999999]]])->get(route('seller.sales'));
        $response->assertOk()->assertSee('Completed Sales')->assertSee('Pending Sales')->assertSee('Average Order Value')->assertSee('Cancelled / Failed')
            ->assertSee('Recent Sales')->assertSee('1 completed order(s)')->assertSee('2 matching')->assertSee('SALE-1')->assertSee('SALE-2')->assertDontSee('SALE-99')->assertDontSee('POISON')->assertDontSee('FOREIGN SECRET')
            ->assertSee('aria-expanded="false"',false)->assertSee('tabindex="-1" hidden',false)->assertDontSee('<span class="sales-filter-badge">',false)
            ->assertDontSee('name="payment_method"',false)->assertDontSee('value="refunded"',false);
        $this->assertSame([], $response->viewData('criteria'));
        $this->assertSame([], $response->viewData('chips'));
        $this->assertSame(2,$response->viewData('paginator')->total());
        if ($dir=getenv('SELLER_SALES_CHECK_DIR')) file_put_contents($dir.'/sales.html',$response->getContent());
    }

    public function test_manila_today_and_inclusive_seven_and_thirty_day_presets(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 00:30:00','Asia/Manila'));
        foreach(['2026-10-31 15:59:59','2026-10-31 16:00:00','2026-11-01 15:59:59','2026-11-01 16:00:00','2026-10-25 16:00:00','2026-10-25 15:59:59','2026-10-02 16:00:00','2026-10-02 15:59:59'] as $i=>$date) $this->sale($i+1,'COMPLETED',date:$date);
        $this->assertSame([2,3],$this->ids(['date'=>'today']));
        $this->assertSame([5,1,2,3],$this->ids(['date'=>'7d']));
        $this->assertSame([7,6,5,1,2,3],$this->ids(['date'=>'30d']));
        $this->assertSame([1],$this->ids(['date'=>'custom','from'=>'2026-10-31','to'=>'2026-10-31']));
    }

    public function test_invalid_ranges_and_unsupported_values_are_rejected(): void
    {
        foreach([
            [['date'=>'custom'],'from'],
            [['date'=>'custom','from'=>'2026-02-30','to'=>'2026-11-01'],'from'],
            [['date'=>'custom','from'=>'2026-11-02','to'=>'2026-11-01'],'to'],
            [['min'=>'10.01','max'=>'10.00'],'max'], [['min'=>'-1'],'min'], [['min'=>'1.001'],'min'],
            [['min'=>'1e3'],'min'], [['status'=>'fake'],'status'], [['payment'=>'refunded'],'payment'],
            [['fulfillment'=>'fake'],'fulfillment'], [['voucher'=>'fake'],'voucher'], [['sort'=>'sql'],'sort'],
            [['product'=>2],'product'], [['page'=>0],'page'],
        ] as [$query,$error]) $this->actingAs($this->seller)->from(route('seller.sales'))->get(route('seller.sales',$query))->assertSessionHasErrors($error);
        $this->assertSame([], $this->listing(['date'=>'any','from'=>'junk','to'=>'junk','payment_method'=>'cod','unknown'=>'ignored'])->viewData('criteria'));
    }

    public function test_status_groups_match_canonical_states_and_cancellation_wins(): void
    {
        foreach(['DELIVERED','COMPLETED','PLACED','RTS_INITIATED','RTS_RETURNING','RTS_COMPLETED','DELIVERY_FAILED','CANCELLED'] as $i=>$status) $this->sale($i+1,$status);
        $this->sale(9,'COMPLETED'); DB::table('orders')->where('id',9)->update(['status'=>'CANCELLED']);
        foreach(['completed'=>[1,2],'pending'=>[3,4,5],'returned'=>[6],'failed'=>[7],'cancelled'=>[8,9]] as $status=>$ids) $this->assertSame($ids,$this->ids(['status'=>$status]));
        $summary=$this->listing(['status'=>'completed'])->viewData('summary');
        $this->assertSame(['completed_minor'=>20000,'completed_count'=>2,'pending_minor'=>0,'cancelled_count'=>0,'average_minor'=>10000],$summary);
    }

    public function test_payments_use_recorded_evidence_and_this_shops_cod_collection(): void
    {
        $this->sale(1,'PLACED'); $this->sale(2,'COMPLETED'); $this->sale(3,'PLACED'); $this->sale(4,'DELIVERED');
        DB::table('orders')->where('id',1)->update(['payment_status'=>'PAID']);
        DB::table('orders')->where('id',3)->update(['payment_status'=>null]);
        DB::table('shipments')->where('seller_order_id',4)->update(['cod_collected'=>1]);
        $this->sale(99,'DELIVERED',seller:202,parent:2); DB::table('shipments')->where('seller_order_id',99)->update(['cod_collected'=>1]);
        $this->assertSame([1,4],$this->ids(['payment'=>'paid']));
        $this->assertSame([2],$this->ids(['payment'=>'unpaid']));
        // Completed workflow alone is not payment evidence; sibling COD is not ours.
        $this->assertSame([2],$this->ids(['status'=>'completed','payment'=>'unpaid']));
    }

    public function test_fulfillment_uses_shipment_states_and_handles_not_yet_created_shipments(): void
    {
        foreach(['PLACED','READY_FOR_PICKUP','PICKED_UP','OUT_FOR_DELIVERY','DELIVERED','RTS_COMPLETED','RTS_RETURNING','DELIVERY_FAILED'] as $i=>$status) $this->sale($i+1,$status);
        DB::table('shipments')->where('seller_order_id',1)->delete();
        $this->sale(9,'PLACED'); DB::table('orders')->where('id',9)->update(['status'=>'CANCELLED']);
        foreach(['to_ship'=>[1,2],'shipped'=>[3,4],'delivered'=>[5],'returned'=>[6]] as $status=>$ids) $this->assertSame($ids,$this->ids(['fulfillment'=>$status]));
    }

    public function test_amounts_vouchers_products_and_summaries_are_not_multiplied_by_items(): void
    {
        $this->sale(1,'COMPLETED',11001); $this->sale(2,'DELIVERED',10000); $this->sale(3,'PLACED',12345); $this->sale(4,'DELIVERY_FAILED',5000);
        DB::table('voucher_redemptions')->insert([['id'=>1,'seller_order_id'=>1,'discount_minor'=>500],['id'=>2,'seller_order_id'=>1,'discount_minor'=>500]]);
        DB::table('order_items')->insert(['id'=>11,'seller_order_id'=>1,'product_id'=>3,'product_name'=>'Red Widget','quantity'=>4,'unit_price_minor'=>10]);
        $summary=$this->listing()->viewData('summary');
        $this->assertSame(['completed_minor'=>20001,'completed_count'=>2,'pending_minor'=>12345,'cancelled_count'=>1,'average_minor'=>10001],$summary);
        $this->assertSame([1],$this->ids(['min'=>'100.01','max'=>'100.01']));
        $this->assertSame([1],$this->ids(['voucher'=>'with']));
        $this->assertSame([2,3,4],$this->ids(['voucher'=>'without']));
        $this->assertSame([1],$this->ids(['product'=>3]));
        $this->assertSame(10001,$this->listing(['product'=>3])->viewData('summary')['completed_minor']);
        $this->assertSame('1,234.56',\App\Support\SellerSalesFilters::money(123456));
    }

    public function test_keyword_search_matches_order_customer_product_snapshots_and_literal_wildcards(): void
    {
        $this->sale(1); $this->sale(2); $this->sale(99,seller:202,parent:1);
        DB::table('users')->insert(['id'=>33,'name'=>'Ada Lovelace','first_name'=>'Ada','last_name'=>'Lovelace']);
        DB::table('orders')->where('id',2)->update(['buyer_id'=>33]);
        DB::table('order_items')->where('id',10)->update(['product_name'=>'100% Widget']);
        foreach(['sale-1'=>[1],'REF-2'=>[2],'customer 1'=>[1],'ada lovelace'=>[2],'100%'=>[1],'FOREIGN SECRET'=>[],'2'=>[2]] as $term=>$ids) $this->assertSame($ids,$this->ids(['search'=>(string)$term]));
        $this->assertSame([2],$this->ids(['search'=>'blue widget']));
    }

    public function test_product_lookup_is_searchable_bounded_and_seller_owned(): void
    {
        DB::table('products')->where('id',3)->update(['deleted_at'=>'2026-10-01']);
        $this->actingAs($this->seller)->getJson(route('seller.sales.products',['q'=>'widget']))->assertOk()->assertJsonCount(2)->assertJsonFragment(['id'=>1,'name'=>'Blue Widget'])->assertJsonMissing(['id'=>2]);
        $this->getJson(route('seller.sales.products',['q'=>'RED']))->assertJsonCount(1)->assertJsonFragment(['id'=>3,'name'=>'Red Widget']);
        foreach(range(10,30) as $id) DB::table('products')->insert(['id'=>$id,'seller_id'=>101,'name'=>'More '.$id]);
        $this->getJson(route('seller.sales.products'))->assertJsonCount(15);
    }

    public function test_sorting_is_stable_and_summary_is_not_limited_to_the_page(): void
    {
        foreach(range(1,18) as $id) $this->sale($id,'COMPLETED',$id*100);
        $this->assertSame(range(18,4),$this->listing()->viewData('paginator')->pluck('id')->all());
        $this->assertSame(range(1,15),$this->ids(['sort'=>'oldest']));
        $this->assertSame(range(18,4),$this->listing(['sort'=>'highest'])->viewData('paginator')->pluck('id')->all());
        $this->assertSame(range(1,15),$this->ids(['sort'=>'lowest']));
        $this->sale(19,'COMPLETED',100);
        $this->assertSame([19,1],array_slice($this->ids(['sort'=>'lowest']),0,2));
        $this->assertSame(17200,$this->listing()->viewData('summary')['completed_minor']);
        $this->assertSame(19,$this->listing()->viewData('summary')['completed_count']);
    }

    public function test_combined_filters_chips_removal_clear_and_pagination_preserve_the_scope(): void
    {
        foreach(range(1,18) as $id) {
            $this->sale($id,'COMPLETED');
            DB::table('orders')->where('id',$id)->update(['payment_status'=>'PAID']);
            DB::table('voucher_redemptions')->insert(['id'=>$id,'seller_order_id'=>$id,'discount_minor'=>100]);
        }
        $query=['date'=>'custom','from'=>'2026-11-01','to'=>'2026-11-01','status'=>'completed','payment'=>'paid','fulfillment'=>'delivered','min'=>'99','max'=>'99','product'=>1,'voucher'=>'with','search'=>'widget','sort'=>'oldest'];
        $response=$this->listing($query)->assertSee('<span class="sales-filter-badge">9</span>',false)->assertSee('Remove filter: Amount: 99.00 PHP',false);
        $this->assertCount(9,$response->viewData('chips'));
        $this->assertSame(178200,$response->viewData('summary')['completed_minor']);
        $next=$response->viewData('paginator')->nextPageUrl(); parse_str(parse_url($next,PHP_URL_QUERY),$nextQuery);
        foreach($query as $key=>$value) $this->assertSame((string)$value,(string)$nextQuery[$key]);
        $this->get($next)->assertOk()->assertViewHas('paginator',fn($p)=>$p->count()===3);
        foreach($response->viewData('chips') as $group=>$chip) {
            parse_str(parse_url($chip['url'],PHP_URL_QUERY) ?? '',$remaining);
            $removed=match($group){'amount'=>['min','max'],'date'=>['date','from','to'],default=>[$group]};
            foreach($removed as $key) $this->assertArrayNotHasKey($key,$remaining);
            foreach(array_diff(array_keys($query),$removed) as $key) $this->assertSame((string)$query[$key],(string)$remaining[$key]);
            $this->assertArrayNotHasKey('page',$remaining);
        }
        $this->assertSame([],$this->listing()->viewData('chips'));
        $this->assertSame(18,$this->listing()->viewData('paginator')->total());
        $empty=$this->listing(['search'=>'nothing matches'])->assertSee('No sales match your filters.')->assertSee('Clear Filters');
        $this->assertSame(['completed_minor'=>0,'completed_count'=>0,'pending_minor'=>0,'cancelled_count'=>0,'average_minor'=>0],$empty->viewData('summary'));
    }

    public function test_sales_routes_require_the_same_canonical_seller_guards_as_orders(): void
    {
        foreach(['seller.sales','seller.sales.products'] as $name) {
            $route=app('router')->getRoutes()->getByName($name);
            foreach(['web','auth','not_suspended','approved','role:seller'] as $guard) $this->assertContains($guard,$route->gatherMiddleware());
        }
        $this->get(route('seller.sales'))->assertRedirect(route('login'));
        $this->actingAs($this->seller); DB::table('sellers')->where('id',101)->update(['status'=>'pending']);
        $this->get(route('seller.sales'))->assertForbidden();
    }
}
