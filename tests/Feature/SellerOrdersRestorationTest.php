<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

/** In-memory fixtures only. No application migrations or production writes. */
class SellerOrdersRestorationTest extends TestCase
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
            'orders'=>'id INTEGER PRIMARY KEY, order_number TEXT, status TEXT, recipient_name TEXT, recipient_phone TEXT, payment_method TEXT, payment_status TEXT, shipping_address TEXT, house_number TEXT, street_address TEXT, barangay TEXT, municipality TEXT, province TEXT, postal_code TEXT, created_at TEXT, updated_at TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_id INTEGER, workflow_status TEXT, status TEXT, subtotal_minor INTEGER, shipping_fee_minor INTEGER, created_at TEXT, updated_at TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, order_id INTEGER, seller_order_id INTEGER, product_name TEXT, variant_name TEXT, variation TEXT, quantity INTEGER, unit_price_minor INTEGER, line_total NUMERIC',
            'shipments'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, workflow_status TEXT, status TEXT, tracking_code TEXT',
            'delivery_events'=>'id INTEGER PRIMARY KEY, shipment_id INTEGER, status TEXT, note TEXT, occurred_at TEXT',
            'voucher_redemptions'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, discount_minor INTEGER',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
        ];
        foreach ($schema as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Seller Fixture','role'=>'seller','status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Owned shop','status'=>'approved'],['id'=>202,'user_id'=>22,'name'=>'Foreign shop','status'=>'approved']]);
    }

    private function order(int $id, int $seller=101, string $status='PLACED', string $date='2026-11-01 04:00:00'): SellerOrder
    {
        DB::table('orders')->insert(['id'=>$id,'order_number'=>'ORDER-'.$id,'status'=>$status,'recipient_name'=>'Buyer '.$id,'recipient_phone'=>'09000000000','street_address'=>'Saved street','payment_method'=>'cod','payment_status'=>'UNPAID']);
        DB::table('seller_orders')->insert(['id'=>$id,'order_id'=>$id,'seller_id'=>$seller,'workflow_status'=>$status,'status'=>strtolower($status),'subtotal_minor'=>10000,'shipping_fee_minor'=>500,'created_at'=>$date,'updated_at'=>$date]);
        DB::table('order_items')->insert(['id'=>$id,'order_id'=>$id,'seller_order_id'=>$id,'product_name'=>($seller===101?'Owned':'FOREIGN').' item '.$id,'variant_name'=>'Blue / Large','variation'=>'{"Color":"Blue"}','quantity'=>2,'unit_price_minor'=>5000,'line_total'=>100]);
        return SellerOrder::findOrFail($id);
    }

    public function test_listing_pagination_ownership_and_query_preservation(): void
    {
        foreach(range(1,18) as $id) $this->order($id);
        $this->order(99,202);
        $this->actingAs($this->seller)->withSession(['orders'=>[['id'=>'POISON']]])->get('/seller/orders?sort=oldest')->assertOk()->assertSee('Owned item 1')->assertDontSee('Owned item 18')->assertDontSee('FOREIGN')->assertDontSee('POISON')->assertSee('sort=oldest&amp;page=2',false)->assertViewHas('paginator',fn($p)=>$p->total()===18&&$p->count()===15)->assertViewHas('summary',fn($s)=>(int)$s->total===18);
        $this->get('/seller/orders?sort=oldest&page=2')->assertOk()->assertSee('Owned item 18')->assertViewHas('paginator',fn($p)=>$p->count()===3);
        $filtered=$this->get('/seller/orders?search=order-&sort=oldest&status=PLACED&shipment_status=NO_SHIPMENT&from=2026-11-01&to=2026-11-01');
        $filtered->assertOk()->assertSee('status=PLACED',false)->assertSee('shipment_status=NO_SHIPMENT',false)->assertSee('from=2026-11-01',false)->assertSee('to=2026-11-01',false)->assertSee('search=order-',false)->assertSee('<details class="orders-advanced">',false)->assertDontSee('<details class="orders-advanced" open',false);
        $this->get($filtered->viewData('paginator')->nextPageUrl())->assertOk()->assertViewHas('paginator',fn($p)=>$p->count()===3&&$p->total()===18);
    }

    public function test_filters_use_database_statuses_and_manila_day_boundaries(): void
    {
        $this->order(1,101,'CONFIRMED','2026-10-31 16:00:00');
        $this->order(2,101,'CONFIRMED','2026-11-01 15:59:59');
        $this->order(3,101,'CONFIRMED','2026-11-01 16:00:00');
        $this->order(4,202,'CONFIRMED');
        DB::table('shipments')->insert(['id'=>1,'seller_order_id'=>1,'workflow_status'=>'CONFIRMED','tracking_code'=>'OWN-TRACK']);
        $this->actingAs($this->seller)->get('/seller/orders?status=CONFIRMED&from=2026-11-01&to=2026-11-01&sort=oldest')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[1,2]);
        $this->get('/seller/orders?shipment_status=CONFIRMED')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[1]);
        $this->get('/seller/orders?shipment_status=NO_SHIPMENT&search=ORDER-2')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[2]);
        $this->get('/seller/orders?search=1')->assertOk()->assertSee('OWN-TRACK')->assertDontSee('FOREIGN');
        $this->get('/seller/orders?status=INVALID')->assertSessionHasErrors('status');
        $this->get('/seller/orders?from=2026-11-02&to=2026-11-01')->assertSessionHasErrors('to');
    }

    public function test_cancelled_shipments_and_combined_filters_then_clear_filters(): void
    {
        $this->order(1);$this->order(2);$this->order(3,202);
        DB::table('orders')->where('id',2)->update(['status'=>'CANCELLED']);
        DB::table('seller_orders')->where('id',2)->update(['status'=>'cancelled']);
        DB::table('shipments')->insert([
            ['id'=>1,'seller_order_id'=>1,'status'=>'unassigned','workflow_status'=>'PLACED'],
            ['id'=>2,'seller_order_id'=>2,'status'=>'cancelled','workflow_status'=>'PLACED'],
            ['id'=>3,'seller_order_id'=>3,'status'=>'cancelled','workflow_status'=>'PLACED'],
        ]);
        $this->actingAs($this->seller)->get('/seller/orders?shipment_status=PLACED')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[1]);
        $filtered=$this->get('/seller/orders?search=order-2&status=CANCELLED&shipment_status=CANCELLED&from=2026-11-01&to=2026-11-01&sort=oldest');
        $filtered->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[2]&&$p[0]['shipment_status']==='Cancelled')
            ->assertSee('Shipment: Cancelled')->assertSee('Sort: Oldest first')->assertSee('More Filters');
        $dom=new \DOMDocument();@$dom->loadHTML($filtered->getContent());$xpath=new \DOMXPath($dom);
        $clear=$xpath->query('//section[@aria-label="Order filters"]//a')->item(0)->getAttribute('href');
        $this->assertSame(route('seller.orders'),$clear);
        $this->get($clear)->assertOk()->assertViewHas('criteria',[])->assertViewHas('paginator',fn($p)=>$p->total()===2&&$p->pluck('id')->all()===[2,1]);
        $this->get('/seller/orders?search=missing-order')->assertOk()->assertSee('No matching orders')->assertViewHas('paginator',fn($p)=>$p->total()===0);
        $this->get('/seller/orders?shipment_status=INVALID')->assertSessionHasErrors('shipment_status');
    }

    public function test_each_canonical_shipment_status_can_be_filtered_independently(): void
    {
        foreach(OrderStatus::cases()as$i=>$status){$id=$i+1;$this->order($id);DB::table('shipments')->insert(['id'=>$id,'seller_order_id'=>$id,'status'=>$status->legacyShipmentStatus(),'workflow_status'=>$status->value]);}
        $this->actingAs($this->seller);
        foreach(OrderStatus::cases()as$i=>$status){$expected=$i+1;$this->get('/seller/orders?status=PLACED&shipment_status='.$status->value)->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[$expected]);}
    }

    public function test_details_isolate_shared_checkout_and_use_saved_snapshots(): void
    {
        $this->order(1);$this->order(2,202);
        DB::table('seller_orders')->where('id',2)->update(['order_id'=>1]);
        DB::table('shipments')->insert(['id'=>1,'seller_order_id'=>1,'workflow_status'=>'PLACED','tracking_code'=>'OWN-TRACK']);
        DB::table('delivery_events')->insert(['id'=>1,'shipment_id'=>1,'status'=>'PLACED','note'=>'Saved owned event','occurred_at'=>'2026-11-01 04:00:00']);
        DB::table('voucher_redemptions')->insert(['id'=>1,'seller_order_id'=>1,'discount_minor'=>1000]);
        $this->actingAs($this->seller)->get('/seller/orders/1?list[search]=ORDER-1&list[page]=2')->assertOk()->assertSee('Owned item 1')->assertDontSee('FOREIGN item')->assertSee('Blue / Large')->assertSee('Saved owned event')->assertSee('Saved street')->assertViewHas('order',fn($o)=>$o['total_minor']===9500&&$o['items']->count()===1)->assertViewHas('backUrl',fn($u)=>str_contains($u,'search=ORDER-1')&&str_contains($u,'page=2'));
        $this->get('/seller/orders/2')->assertForbidden();
        $this->get('/seller/orders/999')->assertNotFound();
        $this->get('/seller/orders/not-a-number')->assertNotFound();
        $this->get('/seller/orders/1?list[redirect]=https://invalid.example')->assertOk()->assertViewHas('backUrl',route('seller.orders'));
    }

    public function test_all_canonical_statuses_and_cancellation_precedence(): void
    {
        foreach(OrderStatus::cases() as $i=>$status) $this->order($i+1,101,$status->value);
        $this->order(100);DB::table('orders')->where('id',100)->update(['status'=>'CANCELLED']);
        $this->actingAs($this->seller)->get('/seller/orders?status=CANCELLED')->assertOk()->assertViewHas('paginator',fn($p)=>$p->count()===1&&$p[0]['next_action']===null&&$p[0]['status']==='CANCELLED');
        foreach(OrderStatus::cases() as $status) $this->get('/seller/orders?status='.$status->value)->assertOk()->assertViewHas('paginator',fn($p)=>$p->count()===1&&$p[0]['status']===$status->value);
    }

    public function test_existing_status_endpoint_still_uses_the_workflow_service(): void
    {
        $owned=$this->order(1);$this->order(2,202);
        $this->mock(OrderWorkflowService::class,function(MockInterface $mock) use($owned){$mock->shouldReceive('transition')->once()->withArgs(fn($order,$actor,$next)=>$order->id===1&&$actor->id===11&&$next===OrderStatus::Confirmed)->andReturn($owned);});
        $this->actingAs($this->seller)->get('/seller/orders/1')->assertOk()->assertSee(route('seller.orders.status',1))->assertSee('name="_token"',false);
        $this->post('/seller/orders/1/status',['status'=>'CONFIRMED'])->assertSessionHasNoErrors();
        $this->post('/seller/orders/2/status',['status'=>'CONFIRMED'])->assertForbidden();
        $this->post('/seller/orders/1/status',['status'=>'DELIVERED'])->assertSessionHasErrors('status');
        $this->assertSame('PLACED',$owned->fresh()->getRawOriginal('workflow_status'));
    }

    public function test_empty_states_and_read_only_rendering(): void
    {
        $this->actingAs($this->seller)->withSession(['orders'=>[['id'=>'POISON']]])->get('/seller/orders')->assertOk()->assertSee('No matching orders')->assertDontSee('POISON');
        $this->get('/seller/orders-legacy')->assertOk()->assertSee('No matching orders')->assertDontSee('POISON');
        $this->order(1);$queries=[];DB::listen(function($event)use(&$queries){$queries[]=$event->sql;});
        $this->get('/seller/orders')->assertOk();$this->get('/seller/orders/1')->assertOk();
        foreach($queries as $sql) $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create|drop)\b/i',$sql);
    }

    public function test_all_three_seller_actions_keep_the_existing_transition_contract(): void
    {
        $this->actingAs($this->seller);
        foreach ([['PLACED','CONFIRMED'],['CONFIRMED','PREPARING'],['PREPARING','READY_FOR_PICKUP']] as $i=>[$current,$next]) {
            $id=$i+1;$owned=$this->order($id,101,$current);
            $this->mock(OrderWorkflowService::class,function(MockInterface $mock)use($owned,$next){$mock->shouldReceive('transition')->once()->withArgs(fn($order,$actor,$status)=>$order->id===$owned->id&&$actor->id===11&&$status===OrderStatus::from($next))->andReturn($owned);});
            $this->get('/seller/orders/'.$id)->assertOk()->assertViewHas('order',fn($o)=>$o['next_action'][0]===$next);
            $this->post('/seller/orders/'.$id.'/status',['status'=>$next])->assertSessionHasNoErrors();
        }
    }

    public function test_existing_seller_access_controls_remain_enforced(): void
    {
        $this->order(1);$this->get('/seller/orders')->assertRedirect(route('login'));
        foreach([['role'=>'buyer'],['status'=>'pending'],['is_suspended'=>true],['verification_required_at'=>'2026-11-01 00:00:00','email_verified_at'=>null]]as$changes){$user=clone $this->seller;$user->forceFill($changes);$this->actingAs($user)->get('/seller/orders')->assertForbidden();}
        DB::table('sellers')->where('id',101)->update(['status'=>'pending']);
        $this->actingAs($this->seller)->get('/seller/orders/1')->assertForbidden();
    }
}
