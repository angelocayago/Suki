<?php
namespace Tests\Feature;

use App\Models\{BuyerRefundFunding,BuyerWallet,BuyerWalletEntry,ReturnRefundRequest,SellerWalletEntry,User};
use App\Services\{BuyerRefundFundingService,BuyerWalletService,ReturnRefundService,SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Schema,Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class BuyerWalletFoundationTest extends IsolatedStorageTestCase {
    use RefreshDatabase, \Tests\Support\CartCheckoutIntent;
    protected function setUp(): void{
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>false,'suki_wallet.checkout_enabled'=>false,'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }
    public function purchase(bool $discounted=false): array{return (new DirectAdminReturnRefundTest('test_direct_approval_is_private_idempotent_and_records_one_wallet_adjustment_without_payment_or_restock'))->purchase($discounted);}
    public function request(array $f): ReturnRefundRequest{return app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged','description'=>'Synthetic funding request.']);}
    public function approve(array $f,ReturnRefundRequest $r): void{app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Accepted with funding still unverified.');}
    private function rejectCredit(ReturnRefundRequest $r,User $actor): void{
        try{app(BuyerRefundFundingService::class)->credit($r,$actor);$this->fail('Unverified money was posted.');}catch(ValidationException $e){$this->assertArrayHasKey('wallet_funding',$e->errors());}
        $this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_zero_balance_wallet_page_history_and_account_link_are_read_only_and_owned(): void{
        config(['suki_wallet'=>require config_path('suki_wallet.php')]);
        $f=$this->purchase();$this->actingAs($f['buyer']);
        $page=$this->get(route('buyer.wallet',['buyer_id'=>$f['sellerUser']->id]));
        $page->assertOk()->assertSee('Suki Wallet Balance')->assertSee('Available to Spend')->assertSee('No wallet transactions yet.')->assertSee('₱0.00')->assertSee('Wallet payments at Checkout are not available yet.')->assertSee('data-lucide="wallet"',false);
        foreach(['My Account','My Orders','My Returns &amp; Refunds','Messages','My Vouchers','Notifications','My reviews','Wishlist','Log Out']as$label)$page->assertSee($label,false);
        $this->assertSame(['balance_minor'=>0,'available_minor'=>0],$page->viewData('wallet'));
        $this->assertSame(0,$page->viewData('entries')->total());$this->assertDatabaseCount('buyer_wallets',0);$this->assertDatabaseCount('buyer_wallet_entries',0);$this->assertDatabaseCount('buyer_refund_fundings',0);
        $this->post(route('buyer.wallet'),['amount'=>100000,'buyer_id'=>$f['buyer']->id])->assertStatus(405);
        $this->get(route('buyer.wallet',['page'=>0]))->assertSessionHasErrors('page');
    }

    public function test_guests_sellers_and_admins_cannot_open_buyer_wallets(): void{
        $f=$this->purchase();$this->get(route('buyer.wallet'))->assertRedirect(route('login'));
        foreach([$f['sellerUser'],$f['admin']]as$actor)$this->actingAs($actor)->get(route('buyer.wallet'))->assertForbidden();
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);$r=$this->request($f);$this->approve($f,$r);
        $page=$this->actingAs($other)->get(route('buyer.wallet',['buyer_id'=>$f['buyer']->id]));$page->assertOk()->assertDontSee('RR-'.$r->id);
        $this->assertSame(0,$page->viewData('fundings')->total());$this->assertSame(0,$page->viewData('wallet')['available_minor']);
    }

    public function test_seller_approval_records_one_unfunded_obligation_with_exact_discount_components_and_no_buyer_credit(): void{
        $f=$this->purchase(true);$r=$this->request($f);$before=$f['shipment']->fresh()->toJson();
        $this->assertDatabaseCount('buyer_refund_fundings',0);$this->approve($f,$r);$this->approve($f,$r);
        $this->assertDatabaseCount('buyer_refund_fundings',1);$this->assertDatabaseCount('seller_wallet_entries',1);$this->assertDatabaseCount('return_refund_accounting',1);$this->assertDatabaseCount('buyer_wallet_entries',0);
        $funding=BuyerRefundFunding::sole();
        foreach(['buyer_id'=>$f['buyer']->id,'seller_id'=>$f['seller']->id,'buyer_refund_minor'=>19698,'seller_adjustment_minor'=>17899,'commission_reversal_minor'=>2000,'seller_discount_minor'=>101,'platform_discount_minor'=>201,'funded_minor'=>0,'custody_funded_minor'=>0,'reserve_funded_minor'=>0,'seller_recovery_funded_minor'=>0]as$key=>$value)$this->assertSame($value,(int)$funding->$key);
        $this->assertSame(BuyerRefundFunding::AWAITING,$funding->status);$this->assertNull($funding->verified_at);$this->assertNull($funding->verified_by);$this->assertNull($funding->consumed_at);
        $this->assertSame($r->id,(int)$funding->return_refund_request_id);$this->assertSame('BUYER-REFUND-RR-'.$r->id,$funding->source_identity);$this->assertSame(-17899,$funding->sellerAdjustment->amount_minor);
        $this->assertSame(-17899,app(SellerWalletService::class)->summary($f['seller'])['available_minor']);$this->assertSame('REFUND_DUE',DB::table('return_refund_accounting')->sole()->refund_status);
        $page=$this->actingAs($f['buyer'])->get(route('buyer.wallet'));$page->assertOk()->assertSee('Approved — awaiting funding')->assertSee('196.98')->assertSee('RR-'.$r->id);
        $this->assertSame(0,$page->viewData('wallet')['balance_minor']);$this->assertSame($before,$f['shipment']->fresh()->toJson());
        $this->rejectCredit($r,$f['admin']);config(['suki_wallet.refund_credit_enabled'=>true]);$this->rejectCredit($r,$f['admin']);
        $this->assertSame(2,$r->events()->count());
    }

    public function test_pending_rejected_and_historical_admin_requests_are_not_backfilled(): void{
        $f=$this->purchase();$r=$this->request($f);$this->assertDatabaseCount('buyer_refund_fundings',0);
        app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'REJECTED','Rejected request.');$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertDatabaseCount('buyer_wallet_entries',0);
        $this->rejectCredit($r,$f['admin']);
    }

    public function test_old_approvals_remain_old_after_schema_deployment_and_replay(): void{
        $f=$this->purchase();$r=$this->request($f);$migration=require database_path('migrations/2026_11_14_000003_create_buyer_wallet_foundation.php');$migration->down();
        $this->approve($f,$r);$this->assertFalse(Schema::hasTable('buyer_refund_fundings'));$migration->up();
        $this->approve($f,$r);$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertDatabaseCount('buyer_wallet_entries',0);$this->assertDatabaseCount('seller_wallet_entries',1);
    }

    public function test_historical_admin_decisions_keep_their_contract_without_wallet_backfill(): void{
        $f=$this->purchase();$r=app(ReturnRefundService::class)->submitForAdmin($f['buyer'],$f['item'],['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged','description'=>'Historical review fixture.']);
        app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Historical Admin contract.');
        $this->assertTrue($r->fresh()->isAdminReview());$this->assertDatabaseCount('seller_wallet_entries',1);$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_no_public_actor_can_credit_and_wallet_checkout_is_rejected_even_if_its_future_flag_is_set(): void{
        $f=$this->purchase();$r=$this->request($f);$this->approve($f,$r);
        foreach([$f['buyer'],$f['sellerUser']]as$actor){try{app(BuyerRefundFundingService::class)->credit($r,$actor);$this->fail('Unauthorized funding operation accepted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}}
        $this->actingAs($f['buyer']);$orders=DB::table('orders')->count();$stock=$f['variant']->fresh()->stock;
        \App\Models\CartItem::create(['user_id'=>$f['buyer']->id,'product_variant_id'=>$f['variant']->id,'product_slug'=>$f['product']->slug,'product_name'=>$f['product']->name,'quantity'=>1,'price'=>100]);
        $selection=$this->cartCheckoutIntent($f['buyer']);
        foreach([false,true]as$flag){config(['suki_wallet.checkout_enabled'=>$flag]);$this->postJson(route('buyer.checkout.place-order'),$selection+['address_id'=>1,'payment_method'=>'wallet'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');}
        $this->assertSame($orders,DB::table('orders')->count());$this->assertSame($stock,$f['variant']->fresh()->stock);$this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_a_forged_funded_status_or_paid_cod_order_is_not_a_verified_funding_source(): void{
        $f=$this->purchase();$r=$this->request($f);$this->approve($f,$r);$funding=BuyerRefundFunding::sole();
        $this->assertSame('PAID',$f['order']->fresh()->payment_status);$this->assertDatabaseCount('logistics_cod_remittances',0);$this->assertSame('OUTSTANDING',DB::table('rider_cod_remittances')->sole()->status);
        DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->update(['status'=>BuyerRefundFunding::READY,'funded_minor'=>$funding->buyer_refund_minor,'custody_funded_minor'=>$funding->buyer_refund_minor,'funding_source_type'=>'COD_REMITTANCE','funding_source_reference'=>'unverified-reference','verified_by'=>$f['admin']->id,'verified_at'=>now()]);
        config(['suki_wallet.refund_credit_enabled'=>true]);$this->rejectCredit($r,$f['admin']);
        $this->assertSame(0,app(BuyerWalletService::class)->summary($f['buyer'])['available_minor']);$this->assertSame(-18000,app(SellerWalletService::class)->summary($f['seller'])['available_minor']);
    }

    public function test_history_renders_synthetic_posted_entries_and_never_exposes_them_to_another_buyer(): void{
        // Display fixture only: this does not exercise or authorize a funding/posting operation.
        $f=$this->purchase();$r=$this->request($f);$this->approve($f,$r);$funding=BuyerRefundFunding::sole();
        DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->update(['status'=>BuyerRefundFunding::CREDITED,'funded_minor'=>$funding->buyer_refund_minor,'custody_funded_minor'=>$funding->buyer_refund_minor,'funding_source_type'=>'SYNTHETIC_DISPLAY_FIXTURE','funding_source_reference'=>'display-only','verified_by'=>$f['admin']->id,'verified_at'=>now(),'consumed_at'=>now()]);
        BuyerWallet::create(['buyer_id'=>$f['buyer']->id]);
        DB::table('buyer_wallet_entries')->insert(['buyer_id'=>$f['buyer']->id,'type'=>'REFUND_CREDIT','status'=>'POSTED','amount_minor'=>20000,'return_refund_request_id'=>$r->id,'source_identity'=>'SYNTHETIC-DISPLAY-RR-'.$r->id,'created_at'=>now()]);
        $page=$this->actingAs($f['buyer'])->get(route('buyer.wallet'));$page->assertOk()->assertSee('Refund Credit')->assertSee('RR-'.$r->id)->assertSee('Credited to Suki Wallet');
        $this->assertSame(['balance_minor'=>20000,'available_minor'=>20000],$page->viewData('wallet'));$this->assertSame(1,$page->viewData('entries')->total());
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);$page=$this->actingAs($other)->get(route('buyer.wallet',['buyer_id'=>$f['buyer']->id]));
        $page->assertOk()->assertDontSee('RR-'.$r->id);$this->assertSame(0,$page->viewData('wallet')['balance_minor']);$this->assertSame(0,$page->viewData('entries')->total());
    }

    public function test_funding_insert_failure_rolls_back_seller_decision_adjustment_accounting_history_and_notifications(): void{
        $f=$this->purchase();$r=$this->request($f);$intents=DB::table('notification_outbox')->count();
        DB::unprepared("CREATE TRIGGER funding_insert_failure BEFORE INSERT ON buyer_refund_fundings BEGIN SELECT RAISE(ABORT, 'Synthetic funding failure'); END");
        try{$this->approve($f,$r);$this->fail('Funding failure did not abort approval.');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('Synthetic funding failure',$e->getMessage());}
        $this->assertSame('SUBMITTED',$r->fresh()->status);$this->assertDatabaseCount('seller_wallet_entries',0);$this->assertDatabaseCount('return_refund_accounting',0);$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertSame(1,$r->events()->count());$this->assertSame($intents,DB::table('notification_outbox')->count());
    }

    public function test_rollout_gate_and_missing_schema_fail_closed_without_changing_returns_activation(): void{
        $f=$this->purchase();config(['suki_wallet.enabled'=>false]);
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertStatus(503)->assertDontSee('href="'.route('buyer.wallet').'"',false);
        config(['suki_wallet.enabled'=>'true']);$this->get(route('buyer.wallet'))->assertStatus(503);
        config(['suki_wallet.enabled'=>true]);Schema::drop('buyer_wallet_entries');$this->get(route('buyer.wallet'))->assertStatus(503);
        $returns=require config_path('returns.php');$wallet=require config_path('suki_wallet.php');
        $this->assertSame(['backend_enabled'=>false,'admin_review_enabled'=>true,'earning_release_enabled'=>false],$returns);
        $this->assertSame(['enabled'=>true,'refund_credit_enabled'=>false,'checkout_enabled'=>false],$wallet);$this->assertFalse(app(BuyerWalletService::class)->checkoutAvailable());
    }

    public function test_unique_funding_identity_monetary_constraints_and_safe_rollback(): void{
        $f=$this->purchase();$r=$this->request($f);$this->approve($f,$r);$funding=BuyerRefundFunding::sole();
        foreach([['funded_minor'=>-1],['buyer_refund_minor'=>-1],['status'=>'UNKNOWN'],['funded_minor'=>1],['buyer_refund_minor'=>1.5]]as$invalid){
            try{DB::transaction(fn()=>DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->update($invalid));$this->fail('Malformed funding accepted.');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        }
        try{DB::transaction(fn()=>DB::table('buyer_refund_fundings')->insert($funding->getAttributes()));$this->fail('Duplicate funding accepted.');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        $migration=require database_path('migrations/2026_11_14_000003_create_buyer_wallet_foundation.php');
        try{$migration->down();$this->fail('Financial metadata was destroyed.');}catch(\RuntimeException $e){$this->assertStringContainsString('destructive rollback refused',$e->getMessage());}
        $this->assertSame(0,BuyerWalletEntry::count());
    }
}
