<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{CartItem, DeliveryEvent, Order, Product, ProductVariant, Seller, SellerWalletEntry, User};
use App\Services\{AcademicWalletPaymentContract, AcademicWalletPaymentService, BuyerWalletService, CheckoutService, OrderCancellationService, OrderWorkflowService, ReturnRefundService, RiderShippingFeeService, SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class AcademicWalletPaymentTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    use \Tests\Support\CartCheckoutIntent, \Tests\Support\FakesCheckoutLocations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL')) throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['academic_wallet.seller_approval_credit_enabled'=>true,'simulation_settlement.enabled'=>true,'simulation_settlement.max_scenario_minor'=>100000,
            'suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>true,'suki_wallet.checkout_enabled'=>true,
            'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>true]);
    }

    public function funded(): array
    {
        $f=(new SimulationSettlementTest('test_exact_discounted_source_is_conserved_and_reverse_shipping_is_explicitly_no_charge'))->fixture(true);
        $r=app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],['type'=>'REFUND','quantity'=>2,'reason'=>'Synthetic damage','description'=>'Academic credit source','confirmed'=>1]);
        app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Reviewed original purchase');
        return $f+['refund'=>$r];
    }

    public function target(array $f,int $price=5000): array
    {
        $key=bin2hex(random_bytes(5));$owner=User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller=Seller::create(['user_id'=>$owner->id,'name'=>'Wallet destination '.$key,'slug'=>'wallet-'.$key,'status'=>'approved']);
        $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$f['product']->category_id,'name'=>'Wallet purchase','slug'=>'wallet-item-'.$key,'is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>1000]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'name'=>'Option','sku'=>'WALLET-'.$key,'price_minor'=>$price,'stock'=>10,'is_active'=>true]);
        $cart=CartItem::create(['user_id'=>$f['buyer']->id,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>1,'price'=>$price/100]);
        return compact('owner','seller','product','variant','cart');
    }

    public function pay(array $f,string $key='ACADEMIC-PAY-001',array $vouchers=[]): Order
    {
        return app(CheckoutService::class)->checkout($f['buyer'],$f['address'],'wallet',$vouchers,null,$key);
    }

    public function delivered(Order $order,array $source): void
    {
        $so=$order->sellerOrders()->sole();$sh=$so->shipment;$rider=$source['shipment']->fresh();
        // Fulfillment assignment is synthetic setup. Delivery itself uses the
        // native proof/role validation and shipping-liability service, with no COD.
        $order->update(['status'=>'OUT_FOR_DELIVERY','out_for_delivery_at'=>now()]);$so->update(['workflow_status'=>'OUT_FOR_DELIVERY','status'=>'shipped','out_for_delivery_at'=>now()]);
        $sh->update(['workflow_status'=>'OUT_FOR_DELIVERY','status'=>OrderStatus::OutForDelivery->legacyShipmentStatus(),'logistics_provider_id'=>$rider->logistics_provider_id,'rider_id'=>$rider->rider_id]);
        app(\App\Services\DeliveryCompletionService::class)->deliver($so->fresh(),User::findOrFail($rider->delivered_by),[
            'proof_photo'=>\Illuminate\Http\UploadedFile::fake()->image('academic-wallet-delivery.jpg'),
            'received_by'=>'Synthetic Buyer',
        ]);
    }

    public function test_refund_payment_held_completion_available_and_all_replays_are_conserved(): void
    {
        $f=$this->funded();$target=$this->target($f);
        $this->assertSame(19698,app(BuyerWalletService::class)->summary($f['buyer'])['balance_minor']);
        $order=$this->pay($f);$p=DB::table('academic_wallet_payments')->sole();$so=$order->sellerOrders()->sole();
        $this->assertSame('wallet',$order->payment_method);$this->assertSame('PAID',$order->payment_status);
        $this->assertSame(10000,(int)$order->total_minor);$this->assertSame(9698,app(BuyerWalletService::class)->summary($f['buyer'])['balance_minor']);
        $this->assertSame(9698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
        $this->assertSame(4500,(int)$p->seller_minor);$this->assertSame(500,(int)$p->platform_minor);$this->assertSame(5000,(int)$p->shipping_minor);
        $this->assertSame(0,(int)$so->shipment->cod_amount_minor);$this->assertFalse($so->shipment->cod_collected);
        $this->assertDatabaseMissing('rider_cod_remittances',['seller_order_id'=>$so->id]);
        $this->assertSame(4500,app(SellerWalletService::class)->summary($target['seller'])['held_minor']);
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->actingAs($target['owner'])->get(route('seller.wallet'))->assertOk()->assertSee('HELD')->assertSee('45.00');
        $again=$this->pay($f);$this->assertSame($order->id,$again->id);$this->assertDatabaseCount('academic_wallet_payments',1);
        $this->assertSame(9,$target['variant']->fresh()->stock);
        $this->delivered($order,$f);app(OrderWorkflowService::class)->confirmReceipt($order->fresh(),$f['buyer']);
        $this->assertSame(4500,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['held_minor']);
        $this->actingAs($target['owner'])->get(route('seller.wallet'))->assertOk()->assertSee('Earning release')->assertSee('45.00');
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($so->fresh()));
        app(OrderWorkflowService::class)->confirmReceipt($order->fresh(),$f['buyer']);
        $this->assertDatabaseCount('academic_wallet_settlements',1);
        $this->assertSame(1,SellerWalletEntry::where('seller_order_id',$so->id)->where('type','EARNING_RELEASE')->count());
        $this->assertSame(9698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
        app(AcademicWalletPaymentContract::class)->verify();
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertOk()->assertSee('Order Payment')->assertSee('96.98');
    }

    public function test_whole_placed_cancellation_reverses_once_releases_allocations_and_restocks(): void
    {
        $f=$this->funded();$target=$this->target($f);$order=$this->pay($f);$service=app(OrderCancellationService::class);
        $service->cancelByBuyer($order,$f['buyer'],'changed_mind');$service->cancelByBuyer($order,$f['buyer'],'changed_mind');
        $this->assertSame(19698,app(BuyerWalletService::class)->summary($f['buyer'])['balance_minor']);
        $this->assertSame(19698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
        $this->assertDatabaseCount('academic_wallet_reversals',1);$this->assertDatabaseCount('buyer_wallet_entries',3);
        $this->assertSame(10,$target['variant']->fresh()->stock);$this->assertSame('REFUNDED',$order->fresh()->payment_status);
        $this->assertSame('unassigned',$order->sellerOrders()->sole()->shipment->status);
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['held_minor']);
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($order->sellerOrders()->sole()));
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['total_earnings_minor']);
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertOk()->assertSee('Payment Reversal')->assertSee('196.98');
    }

    public function test_insufficient_funds_and_multi_seller_roll_back_orders_inventory_and_ledger(): void
    {
        $f=$this->funded();$a=$this->target($f,20000);$before=Order::count();
        $this->invalid(fn()=>$this->pay($f));$this->assertSame($before,Order::count());$this->assertSame(10,$a['variant']->fresh()->stock);$this->assertDatabaseCount('academic_wallet_payments',0);
        $a['variant']->update(['price_minor'=>1000]);$b=$this->target($f,1000);
        $this->invalid(fn()=>$this->pay($f));$this->assertSame($before,Order::count());$this->assertSame(10,$b['variant']->fresh()->stock);
    }

    public function test_held_funds_cannot_be_paid_out_or_released_without_completion_and_delivery_proof(): void
    {
        $f=$this->funded();$target=$this->target($f);$order=$this->pay($f);$so=$order->sellerOrders()->sole();
        $this->invalid(fn()=>app(SellerWalletService::class)->request($target['seller'],1));
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($so));
        $this->delivered($order,$f);$so->shipment->update(['proof_path'=>null]);
        app(OrderWorkflowService::class)->confirmReceipt($order->fresh(),$f['buyer']);
        $this->assertSame(4500,app(SellerWalletService::class)->summary($target['seller'])['held_minor']);
        $this->assertSame(0,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->assertDatabaseCount('academic_wallet_settlements',0);
    }

    public function test_shipping_is_included_in_sufficiency_and_completed_payments_cannot_reverse(): void
    {
        $f=$this->funded();$this->target($f);$order=$this->pay($f);$next=$this->target($f);
        // 96.98 covers the next 50.00 item but not its complete 100.00 total.
        $this->invalid(fn()=>$this->pay($f,'ACADEMIC-PAY-002'));
        $this->assertSame(10,$next['variant']->fresh()->stock);$this->assertDatabaseCount('academic_wallet_payments',1);
        $this->delivered($order,$f);app(OrderWorkflowService::class)->confirmReceipt($order->fresh(),$f['buyer']);
        $this->invalid(fn()=>app(OrderCancellationService::class)->cancelByBuyer($order->fresh(),$f['buyer'],'changed_mind'));
        $this->assertDatabaseCount('academic_wallet_reversals',0);$this->assertSame(9698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
    }

    public function test_ledger_capture_failure_rolls_back_stock_vouchers_order_and_debit(): void
    {
        $f=$this->funded();$target=$this->target($f);$before=Order::count();
        \App\Models\Payment::creating(function(){throw new \RuntimeException('Injected wallet capture failure');});
        try {
            try {$this->pay($f);$this->fail('Failed capture accepted');}catch(\RuntimeException $e){$this->assertSame('Injected wallet capture failure',$e->getMessage());}
        } finally {\App\Models\Payment::flushEventListeners();}
        $this->assertSame($before,Order::count());$this->assertSame(10,$target['variant']->fresh()->stock);
        $this->assertDatabaseCount('academic_wallet_payments',0);$this->assertDatabaseCount('buyer_wallet_entries',1);
        $this->assertSame(19698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
        $this->assertSame(1,CartItem::where('user_id',$f['buyer']->id)->count());
    }

    public function test_cancellation_failure_rolls_back_the_order_restock_and_reversal_together(): void
    {
        $f=$this->funded();$target=$this->target($f);$order=$this->pay($f);
        \App\Models\BuyerWalletEntry::creating(function($entry){if($entry->type==='PAYMENT_REVERSAL')throw new \RuntimeException('Injected reversal failure');});
        try {
            try {app(OrderCancellationService::class)->cancelByBuyer($order,$f['buyer'],'changed_mind');$this->fail('Failed reversal accepted');}
            catch(\RuntimeException $e){$this->assertSame('Injected reversal failure',$e->getMessage());}
        } finally {\App\Models\BuyerWalletEntry::flushEventListeners();}
        $this->assertSame('PLACED',$order->fresh()->status);$this->assertSame(9,$target['variant']->fresh()->stock);
        $this->assertDatabaseCount('academic_wallet_reversals',0);$this->assertSame(9698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
    }

    public function test_existing_backings_and_consumption_records_are_immutable(): void
    {
        $f=$this->funded();$this->target($f);$this->pay($f);$p=DB::table('academic_wallet_payments')->sole();
        foreach ([fn()=>DB::table('academic_wallet_payments')->where('id',$p->id)->update(['total_minor'=>1]),
            fn()=>DB::table('academic_wallet_allocations')->where('payment_id',$p->id)->update(['amount_minor'=>1]),
            fn()=>DB::table('buyer_wallet_entries')->where('id',$p->payment_entry_id)->delete()] as $attack) {
            try {DB::transaction($attack);$this->fail('Consumption history was rewritten');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        }
        app(AcademicWalletPaymentContract::class)->verify();$this->assertSame(9698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
    }

    public function test_a_coordinated_but_unallocated_raw_debit_cannot_commit(): void
    {
        $f=$this->funded();
        try {
            DB::transaction(function()use($f){
                DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>1]);
                DB::table('buyer_wallet_entries')->insert(['buyer_id'=>$f['buyer']->id,'type'=>'ORDER_PAYMENT','status'=>'POSTED','amount_minor'=>-1,'order_id'=>$f['order']->id,'source_identity'=>'UNALLOCATED-RAW-DEBIT','created_at'=>now()]);
                DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>0]);
                if(DB::getDriverName()==='pgsql')DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });$this->fail('Unallocated payment committed');
        }catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        $this->assertDatabaseCount('buyer_wallet_entries',1);$this->assertSame(19698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
    }

    public function test_complaint_holds_and_rollout_gates_preserve_the_original_held_earning(): void
    {
        $f=$this->funded();$target=$this->target($f);$order=$this->pay($f);$so=$order->sellerOrders()->sole();
        $complaint=\App\Models\Complaint::create(['reference'=>'WALLET-HOLD-'.$so->id,'complainant_id'=>$f['buyer']->id,'complainant_role'=>'BUYER','order_id'=>$order->id,'seller_order_id'=>$so->id,'buyer_id'=>$f['buyer']->id,'seller_id'=>$so->seller_id,'category'=>\App\Enums\ComplaintCategory::Other->value,'status'=>'OPEN','subject'=>'Synthetic delivery review','description'=>'Hold until resolved','context_snapshot'=>[]]);
        $this->delivered($order,$f);app(OrderWorkflowService::class)->confirmReceipt($order->fresh(),$f['buyer']);
        $this->assertDatabaseCount('academic_wallet_settlements',0);$this->assertSame(4500,app(SellerWalletService::class)->summary($target['seller'])['held_minor']);
        $complaint->update(['status'=>'RESOLVED']);config(['returns.earning_release_enabled'=>false]);
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($so->fresh()));
        config(['returns.earning_release_enabled'=>true]);$this->assertTrue(app(SellerWalletService::class)->reconcileSellerOrder($so->fresh()));
        $payout=app(SellerWalletService::class)->request($target['seller'],2000);
        $this->assertSame(2500,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($so->fresh()));
        $this->assertSame(2500,app(SellerWalletService::class)->summary($target['seller'])['available_minor']);
        $this->assertSame(2000,app(SellerWalletService::class)->summary($target['seller'])['reserved_minor']);
    }

    public function test_saved_voucher_and_commission_allocations_conserve_the_payment(): void
    {
        $f=$this->funded();$target=$this->target($f);
        $seller=\App\Models\Voucher::create(['code'=>'WALLET-SELLER','scope'=>'SELLER','seller_id'=>$target['seller']->id,'discount_type'=>'FIXED','discount_minor'=>101,'is_active'=>true]);
        $platform=\App\Models\Voucher::create(['code'=>'WALLET-PLATFORM','scope'=>'PLATFORM','discount_type'=>'FIXED','discount_minor'=>201,'is_active'=>true]);
        $order=$this->pay($f,'ACADEMIC-VOUCHER',[$seller->code,$platform->code]);$p=DB::table('academic_wallet_payments')->sole();
        $this->assertSame(9698,(int)$order->total_minor);$this->assertSame(4399,(int)$p->seller_minor);$this->assertSame(299,(int)$p->platform_minor);$this->assertSame(5000,(int)$p->shipping_minor);
        $this->assertSame(10000,app(AcademicWalletPaymentService::class)->available($f['buyer']));
        $this->assertSame(1,$seller->fresh()->used_count);$this->assertSame(1,$platform->fresh()->used_count);
        $this->assertSame(2,DB::table('voucher_redemptions')->where('order_id',$order->id)->count());
    }

    public function test_native_checkout_ui_and_post_use_the_server_total_and_preserve_buyer_privacy(): void
    {
        $f=$this->funded();$this->target($f);$this->fakeCheckoutLocations();
        $address=\App\Models\Address::create(['user_id'=>$f['buyer']->id,'recipient'=>'Synthetic Buyer','phone'=>'09171234567','line1'=>'Test street','city'=>'City A','province'=>'Province A','barangay'=>'Barangay A','is_default'=>true]+$this->checkoutLocationCodes());
        $this->actingAs($f['buyer']);$intent=$this->cartCheckoutIntent();
        $this->get(route('buyer.checkout',$intent))->assertOk()->assertSee('Wallet balance:')->assertSee('196.98')->assertSee('Order total including shipping:')->assertSee('100.00')->assertViewHas('walletIssue',null);
        $this->post(route('buyer.checkout.place-order'),$intent+['address_id'=>$address->id,'payment_method'=>'wallet'])->assertSessionHasNoErrors()->assertRedirect();
        $order=Order::where('payment_method','wallet')->sole();$this->assertSame(10000,$order->total_minor);$this->assertSame('PAID',$order->payment_status);
        $this->get(route('buyer.order-success',['order'=>$order->order_number]))->assertOk()->assertSee('Suki Wallet');
        $this->get(route('buyer.wallet'))->assertOk()->assertSee('Order Payment')->assertSee('96.98');
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->get(route('buyer.wallet',['buyer_id'=>$f['buyer']->id]))->assertOk()->assertDontSee('96.98')->assertDontSee($order->order_number);
    }

    public function test_unfunded_platform_subsidies_are_unavailable_in_preview_and_rejected_atomically(): void
    {
        $f=$this->funded();$target=$this->target($f);
        $voucher=\App\Models\Voucher::create(['code'=>'UNFUNDED-PLATFORM','scope'=>'PLATFORM','discount_type'=>'FIXED','discount_minor'=>1000,'is_active'=>true]);
        $summary=app(CheckoutService::class)->cartSummary($f['buyer'],[$voucher->code]);
        $this->assertStringContainsString('additional funding',app(AcademicWalletPaymentService::class)->supportsSummary($summary));
        $this->invalid(fn()=>$this->pay($f,'UNSUPPORTED-SUBSIDY',[$voucher->code]));
        $this->assertSame(10,$target['variant']->fresh()->stock);$this->assertSame(0,$voucher->fresh()->used_count);
        $this->assertDatabaseCount('academic_wallet_payments',0);$this->assertSame(19698,app(AcademicWalletPaymentService::class)->available($f['buyer']));
    }

    public function test_checkout_displays_insufficient_balance_after_a_payment(): void
    {
        $f=$this->funded();$this->target($f);$this->pay($f);$this->target($f);$this->actingAs($f['buyer']);
        $this->get(route('buyer.checkout',$this->cartCheckoutIntent()))->assertOk()->assertSee('96.98')->assertSee('100.00')
            ->assertSee('Insufficient Suki Wallet balance')->assertViewHas('walletIssue',fn($issue)=>str_contains($issue,'including shipping'));
    }

    protected function invalid(callable $call): void
    {
        try {$call();$this->fail('Unsupported or unbacked operation accepted');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
    }
}
