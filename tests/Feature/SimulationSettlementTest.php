<?php

namespace Tests\Feature;

use App\Models\{BuyerRefundFunding, CartItem, Category, DeliveryEvent, LogisticsCodRemittanceItem, LogisticsProvider, Product, ProductVariant, Rider, RiderCodRemittance, Seller, User};
use App\Services\{BuyerRefundFundingService, CodRemittanceService, ReturnRefundService, SellerWalletService, SimulationSettlementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class SimulationSettlementTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION')!=='sqlite' || getenv('DB_DATABASE')!==':memory:' || getenv('DB_URL')) throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp(); $this->assertStoragePathIsIsolated();
        Http::preventStrayRequests(); Mail::fake(); Notification::fake(); Storage::fake('local'); Storage::fake('public');
        config(['simulation_settlement.enabled'=>true,'simulation_settlement.max_scenario_minor'=>100000,
            'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false,
            'suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>false,'suki_wallet.checkout_enabled'=>false]);
    }

    public function fixture(bool $discounted=false, bool $issue=true, bool $twoLines=false, int $commissionBps=1000): array
    {
        $buyer=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $admin=User::factory()->create(['role'=>'admin','status'=>'active']);
        $sellerUser=User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller=Seller::create(['user_id'=>$sellerUser->id,'name'=>'Simulation only','slug'=>'simulation-only','status'=>'approved']);
        $category=Category::create(['name'=>'Synthetic','slug'=>'synthetic','is_active'=>true]);
        $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Synthetic purchase','slug'=>'synthetic-purchase','is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>$commissionBps]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'name'=>'Option','sku'=>'SIM-ONLY','price_minor'=>10000,'stock'=>10,'is_active'=>true]);
        CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>$twoLines?1:2,'price'=>100]);
        if ($twoLines) {
            $second=ProductVariant::create(['product_id'=>$product->id,'name'=>'Other option','sku'=>'SIM-OTHER','price_minor'=>10000,'stock'=>10,'is_active'=>true]);
            CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$second->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>1,'price'=>100]);
        }
        $codes=[];
        if ($discounted) foreach (['SELLER'=>101,'PLATFORM'=>201] as $scope=>$amount) $codes[]=\App\Models\Voucher::create(['code'=>'SIM-'.$scope,'scope'=>$scope,'seller_id'=>$scope==='SELLER'?$seller->id:null,'discount_type'=>'FIXED','discount_minor'=>$amount,'is_active'=>true])->code;
        $address=['recipient_name'=>'Synthetic Buyer','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test'];
        $service=app(SimulationSettlementService::class);
        $book=$service->createScenario($admin,$buyer,$seller,$address,'synthetic-scenario-0001',$codes);
        $order=\App\Models\Order::findOrFail($book->order_id); $so=$order->sellerOrders()->sole(); $shipment=$so->shipment; $item=$so->items()->orderBy('id')->firstOrFail();
        $provider=LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics','status'=>'active'])->id,'name'=>'Synthetic provider','slug'=>'synthetic-provider','status'=>'approved']);
        $rider=Rider::create(['user_id'=>User::factory()->create(['role'=>'rider','status'=>'active'])->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'TEST','is_active'=>true]);
        $so->update(['workflow_status'=>'DELIVERED','status'=>'delivered','delivered_at'=>now(),'logistics_provider_id'=>$provider->id]);
        $shipment->update(['workflow_status'=>'DELIVERED','status'=>'delivered','logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'proof_path'=>'private/synthetic.jpg','proof_delivered_at'=>now(),'delivered_by'=>$rider->user_id,'cod_collected'=>true,'cod_collected_minor'=>$shipment->cod_amount_minor,'cod_collected_at'=>now()]);
        app(\App\Services\RiderShippingFeeService::class)->creditDelivered($shipment->fresh());
        DeliveryEvent::create(['shipment_id'=>$shipment->id,'status'=>'COD_COLLECTED','attempt'=>0,'user_id'=>$rider->user_id,'occurred_at'=>now()]);
        $order->update(['status'=>'DELIVERED','payment_status'=>'PAID']);
        $cod=app(CodRemittanceService::class); $cod->createLiability($shipment->fresh()); $receipt=RiderCodRemittance::sole();
        $providerUser=User::findOrFail($provider->user_id);
        $cod->submitRider($receipt,$rider->user,'synthetic-only'); $cod->reviewRider($receipt,$providerUser,'ACCEPTED',null);
        $batch=$cod->submitLogistics($providerUser,[$receipt->id],'synthetic-only'); $cod->reviewLogistics($batch,$admin,'ACCEPTED',null);
        $so->update(['workflow_status'=>'COMPLETED','status'=>'completed']);
        $shipment->update(['workflow_status'=>'COMPLETED','status'=>'delivered']);
        app(SellerWalletService::class)->creditCompleted($so->fresh());
        $upstream=LogisticsCodRemittanceItem::sole();
        if ($issue) $book=$service->issue($book->id,$upstream,$admin);
        return compact('buyer','admin','sellerUser','seller','product','variant','order','so','shipment','item','book','upstream','address');
    }

    private function invalid(callable $callback): void
    {
        try { $callback(); $this->fail('Unsafe operation accepted.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    private function book(array $f): object { return DB::table('simulation_settlements')->find($f['book']->id); }

    private function approve(array $f): \App\Models\ReturnRefundRequest
    {
        $r=app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],['type'=>'REFUND','quantity'=>2,'reason'=>'Damaged item','description'=>'Synthetic damaged item','confirmed'=>1]);
        return app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Synthetic purchase reviewed');
    }

    public function test_exact_discounted_source_is_conserved_and_reverse_shipping_is_explicitly_no_charge(): void
    {
        $f=$this->fixture(true); $s=$this->book($f);
        $this->assertSame(24698,(int)$s->issued_minor);
        $this->assertSame(17899,(int)$s->seller_available_minor);
        $this->assertSame(1799,(int)$s->platform_available_minor);
        $this->assertSame(5000,(int)$s->shipping_protected_minor);
        $this->assertSame(0,(int)$s->reverse_shipping_minor);
        $this->assertSame('SELLER',json_decode($s->quote,true)['reverse_shipping_payer']);
        $again=app(SimulationSettlementService::class)->issue($s->id,$f['upstream'],$f['admin']);
        $this->assertSame($s->id,$again->id); $this->assertDatabaseCount('simulation_settlement_events',1);
        $this->assertDatabaseHas('custody_sources',['state'=>'UNRECONCILED','allocatable_minor'=>0]);
        $this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_native_payout_reserve_approve_pay_consumes_once_and_protects_shipping(): void
    {
        $f=$this->fixture(); $wallet=app(SellerWalletService::class);
        $p=$wallet->request($f['seller'],9000);
        $this->assertSame(9000,(int)$this->book($f)->seller_available_minor);
        $this->assertSame(9000,(int)$this->book($f)->payout_reserved_minor);
        $wallet->review($p,$f['admin'],'APPROVED');
        $this->invalid(fn()=>$wallet->markPaid($p,$f['admin'],'bank','not-a-real-transfer'));
        $wallet->markPaid($p,$f['admin'],'manual','SIMULATION ONLY');
        $this->assertSame(9000,(int)$this->book($f)->consumed_minor);
        $this->assertSame(0,(int)$this->book($f)->payout_reserved_minor);
        $this->assertSame(9000,$wallet->summary($f['seller'])['available_minor']);
        $this->invalid(fn()=>$wallet->markPaid($p,$f['admin'],'manual','SIMULATION ONLY'));
        $this->assertSame(9000,(int)$this->book($f)->consumed_minor);
        $this->assertSame(5000,(int)$this->book($f)->shipping_protected_minor);
    }

    public function test_refund_cannot_spend_reserved_payout_and_rejection_reclassifies_only_available_claims(): void
    {
        $f=$this->fixture(true); $wallet=app(SellerWalletService::class);
        $p=$wallet->request($f['seller'],10000);
        $r=$this->approve($f);
        $this->assertSame(BuyerRefundFunding::AWAITING,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertSame(9698,(int)$this->book($f)->refund_reserved_minor);
        $this->assertSame(10000,(int)$this->book($f)->payout_reserved_minor);
        $this->assertSame(-10000,$wallet->summary($f['seller'])['available_minor']);
        $this->invalid(fn()=>$wallet->request($f['seller'],1));
        $wallet->review($p,$f['admin'],'REJECTED','Synthetic obligation takes precedence');
        $this->assertSame(BuyerRefundFunding::READY,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertSame(19698,(int)$this->book($f)->refund_reserved_minor);
        $this->assertSame(0,(int)$this->book($f)->seller_available_minor);
        $this->assertSame(5000,(int)$this->book($f)->shipping_protected_minor);
        $this->assertDatabaseCount('buyer_wallet_entries',0);
        $this->assertDatabaseCount('seller_wallet_entries',3);
        $this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));
        $this->assertFalse(config('returns.earning_release_enabled'));
    }

    public function test_refund_after_paid_payout_remains_partial_without_recovery_money(): void
    {
        $f=$this->fixture(); $wallet=app(SellerWalletService::class);
        $p=$wallet->request($f['seller'],9000); $wallet->review($p,$f['admin'],'APPROVED'); $wallet->markPaid($p,$f['admin'],'manual','SIMULATION ONLY');
        $r=$this->approve($f); $s=$this->book($f);
        $this->assertSame(9000,(int)$s->consumed_minor); $this->assertSame(11000,(int)$s->refund_reserved_minor);
        $this->assertSame(BuyerRefundFunding::AWAITING,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertSame(-9000,$wallet->summary($f['seller'])['available_minor']);
        $this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_gate_cap_history_and_unauthorized_issuance_fail_closed_with_atomic_rollback(): void
    {
        $f=$this->fixture(false,false); $service=app(SimulationSettlementService::class);
        config(['simulation_settlement.enabled'=>false]);
        $this->invalid(fn()=>$service->issue($f['book']->id,$f['upstream'],$f['admin']));
        $this->invalid(fn()=>app(SellerWalletService::class)->request($f['seller'],1));
        $this->assertSame(0,(int)$this->book($f)->issued_minor);
        config(['simulation_settlement.enabled'=>true]);
        try { $service->issue($f['book']->id,$f['upstream'],$f['buyer']); $this->fail('Buyer issued funds'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $this->invalid(fn()=>$service->createScenario($f['admin'],$f['buyer'],$f['seller'],$f['address'],'another-scenario-0002'));
        $this->assertDatabaseCount('simulation_settlements',1);
        try { DB::transaction(function () use ($service,$f) { $service->issue($f['book']->id,$f['upstream'],$f['admin']); throw new \RuntimeException('Injected rollback'); }); }
        catch (\RuntimeException $e) { $this->assertSame('Injected rollback',$e->getMessage()); }
        $this->assertSame(0,(int)$this->book($f)->issued_minor); $this->assertDatabaseCount('custody_sources',0);
    }

    public function test_uncoordinated_payout_and_unbalanced_source_writes_are_rejected_by_database(): void
    {
        $f=$this->fixture();
        foreach ([fn()=>\App\Models\Payout::create(['seller_id'=>$f['seller']->id,'amount_minor'=>1,'status'=>'PENDING']),
            fn()=>DB::table('simulation_settlements')->where('id',$f['book']->id)->increment('seller_available_minor')] as $write) {
            try { DB::transaction($write); $this->fail('Uncoordinated money accepted'); }
            catch (\Illuminate\Database\QueryException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $this->assertDatabaseCount('payouts',0); $this->assertSame(18000,(int)$this->book($f)->seller_available_minor);
    }

    public function test_policy_is_immutable_and_no_arbitrary_amount_can_be_issued(): void
    {
        $f=$this->fixture(false,false); $service=app(SimulationSettlementService::class);
        foreach (['reverse_shipping_minor'=>1,'cap_minor'=>99999,'quote_hash'=>str_repeat('0',64)] as $key=>$value) {
            try { DB::transaction(fn()=>DB::table('simulation_settlements')->where('id',$f['book']->id)->update([$key=>$value])); $this->fail('Frozen policy changed'); }
            catch (\Illuminate\Database\QueryException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
        $f['item']->update(['platform_funded_discount_minor'=>9999]);
        $this->invalid(fn()=>$service->issue($f['book']->id,$f['upstream'],$f['admin']));
        $this->assertSame(0,(int)$this->book($f)->issued_minor);
        $this->assertDatabaseCount('custody_sources',0);
        $this->assertSame(3,(new \ReflectionMethod($service,'issue'))->getNumberOfParameters());
    }

    public function test_cap_failure_rolls_back_native_checkout_and_inventory(): void
    {
        // Roll back a complete synthetic setup to obtain its untouched Cart and Seller.
        DB::beginTransaction(); $this->fixture(false,false); DB::rollBack();
        config(['simulation_settlement.max_scenario_minor'=>24999]);
        $this->invalid(fn()=>$this->fixture(false,false));
        $this->assertDatabaseCount('orders',0); $this->assertDatabaseCount('simulation_settlements',0);
        $this->assertSame(10,(int)ProductVariant::sole()->stock); $this->assertSame(2,(int)CartItem::sole()->quantity);
    }

    public function test_duplicate_funding_and_decision_do_not_debit_twice_or_credit_wallet(): void
    {
        $f=$this->fixture(true); $r=$this->approve($f); $before=$this->book($f);
        app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Same decision');
        $funding=app(BuyerRefundFundingService::class)->allocate($r,$f['admin']);
        $this->assertSame(BuyerRefundFunding::READY,$funding->status);
        $this->assertSame('Simulation funded — wallet credit disabled',$funding->statusLabel());
        $this->assertEquals($before,$this->book($f));
        $this->assertDatabaseCount('simulation_commitments',1); $this->assertDatabaseCount('buyer_wallet_entries',0);
        $this->assertSame(1,\App\Models\SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->count());
        $this->assertFalse(config('suki_wallet.refund_credit_enabled')); $this->assertFalse(config('suki_wallet.checkout_enabled'));
    }

    public function test_actual_variable_commission_and_rider_earning_survive_refund_allocation(): void
    {
        $f=$this->fixture(false,true,false,2000); $s=$this->book($f);
        $this->assertSame(16000,(int)$s->seller_claim_minor); $this->assertSame(4000,(int)$s->platform_claim_minor);
        $before=DB::table('rider_shipping_fee_earnings')->get()->toJson();
        $this->approve($f);
        $this->assertSame($before,DB::table('rider_shipping_fee_earnings')->get()->toJson());
        $this->assertSame(5000,(int)DB::table('rider_shipping_fee_earnings')->sole()->amount_minor);
        $this->assertSame(5000,(int)$this->book($f)->shipping_protected_minor);
        $this->assertSame(0,(int)$this->book($f)->reverse_shipping_minor);
    }

    public function test_payout_enclosing_failure_restores_native_and_shared_reservations_and_defaults_stay_off(): void
    {
        $f=$this->fixture(); $before=$this->book($f);
        try { DB::transaction(function () use ($f) { app(SellerWalletService::class)->request($f['seller'],9000); throw new \RuntimeException('Injected payout failure'); }); }
        catch (\RuntimeException $e) { $this->assertSame('Injected payout failure',$e->getMessage()); }
        $this->assertEquals($before,$this->book($f)); $this->assertDatabaseCount('payouts',0);
        $this->assertDatabaseCount('simulation_commitments',0); $this->assertDatabaseCount('seller_wallet_entries',1);
        $defaults=require config_path('simulation_settlement.php');
        $this->assertSame(['enabled'=>false,'max_scenario_minor'=>0],$defaults);
    }
}
