<?php
namespace Tests\Feature;

use App\Models\{BuyerRefundFunding,ReturnRefundRequest,RiderCodRemittance,User};
use App\Services\{BuyerRefundFundingService,CodRemittanceService,ReturnRefundService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class BuyerRefundFundingAssessmentTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>false,'suki_wallet.checkout_enabled'=>false,'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }
    public function fixture(bool $discounted=false): array
    {
        $helper=new BuyerWalletFoundationTest('test_zero_balance_wallet_page_history_and_account_link_are_read_only_and_owned');
        $f=$helper->purchase($discounted);$r=$helper->request($f);$helper->approve($f,$r);return [$f,$r];
    }
    private function snapshot(): array
    {
        $out=[];foreach(['orders','seller_orders','order_items','shipments','payments','payouts','seller_wallet_entries','return_refund_requests','return_refund_accounting','buyer_refund_fundings','buyer_wallets','buyer_wallet_entries']as$table)$out[$table]=DB::table($table)->get()->toJson();return $out;
    }
    private function invalid(callable $operation): void
    {
        try{$operation();$this->fail('Unsupported allocation/posting accepted.');}catch(ValidationException $e){$this->assertArrayHasKey('wallet_funding',$e->errors());}
    }
    public function test_valid_obligation_assessment_is_read_only_and_repeated_attempts_never_fund_or_credit(): void
    {
        [$f,$r]=$this->fixture(true);$before=$this->snapshot();$service=app(BuyerRefundFundingService::class);
        $assessment=$service->assess($r,$f['admin']);
        $this->assertSame(19698,$assessment['buyer_refund_minor']);$this->assertSame(17899,$assessment['seller_adjustment_minor']);$this->assertSame(2000,$assessment['commission_reversal_minor']);
        $this->assertSame(0,$assessment['verified_allocatable_minor']);$this->assertFalse($assessment['can_allocate']);$this->assertFalse($assessment['can_credit']);$this->assertSame('MISSING_EXCLUSIVE_FUNDING_SOURCE',$assessment['blocker']);
        foreach([false,true]as$flag){config(['suki_wallet.refund_credit_enabled'=>$flag]);$this->invalid(fn()=>$service->allocate($r,$f['admin']));$this->invalid(fn()=>$service->credit($r,$f['admin']));}
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('seller_wallet_entries',1);$this->assertDatabaseCount('buyer_wallet_entries',0);
    }
    public function test_even_accepted_upstream_cod_is_not_an_exclusive_unencumbered_refund_source(): void
    {
        [$f,$r]=$this->fixture();$cod=app(CodRemittanceService::class);$receipt=RiderCodRemittance::sole();
        $providerUser=User::findOrFail($receipt->provider->user_id);
        $cod->submitRider($receipt,$receipt->rider->user,'synthetic-receipt');$cod->reviewRider($receipt,$providerUser,'ACCEPTED',null);
        $batch=$cod->submitLogistics($providerUser,[$receipt->id],'synthetic-upstream');$cod->reviewLogistics($batch,$f['admin'],'ACCEPTED',null);
        $before=$this->snapshot();$assessment=app(BuyerRefundFundingService::class)->assess($r,$f['admin']);
        $this->assertSame('ACCEPTED',$batch->fresh()->status);$this->assertFalse($assessment['can_credit']);$this->assertSame(0,$assessment['verified_allocatable_minor']);
        $this->invalid(fn()=>app(BuyerRefundFundingService::class)->allocate($r,$f['admin']));$this->assertSame($before,$this->snapshot());
    }
    public function test_unauthorized_suspended_and_forged_in_memory_admins_cannot_assess(): void
    {
        [$f,$r]=$this->fixture();$before=$this->snapshot();$f['buyer']->role='admin';
        foreach([$f['buyer'],$f['sellerUser']]as$actor){try{app(BuyerRefundFundingService::class)->assess($r,$actor);$this->fail('Unauthorized actor accepted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}}
        $this->assertSame($before,$this->snapshot());$f['admin']->update(['is_suspended'=>true]);
        try{app(BuyerRefundFundingService::class)->assess($r,$f['admin']);$this->fail('Suspended Admin accepted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
    public function test_changed_accounting_ownership_and_terminal_states_are_rejected_without_repairs(): void
    {
        [$f,$r]=$this->fixture();$service=app(BuyerRefundFundingService::class);
        foreach(['accounting','ownership','rejected']as$case){DB::beginTransaction();try{
            if($case==='accounting')DB::table('return_refund_accounting')->where('return_refund_request_id',$r->id)->increment('commission_reversal_minor');
            elseif($case==='ownership')DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->update(['buyer_id'=>$f['admin']->id]);
            else DB::table('return_refund_requests')->where('id',$r->id)->update(['status'=>'ADMIN_REJECTED']);
            $before=$this->snapshot();$this->invalid(fn()=>$service->allocate($r,$f['admin']));$this->assertSame($before,$this->snapshot());
        }finally{DB::rollBack();}}
    }
    public function test_historical_299_approval_can_be_displayed_without_backfill_or_spendable_money(): void
    {
        [$f,$r]=$this->fixture();
        // Synthetic pre-foundation approval; existing production records are never touched.
        DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->delete();DB::table('return_refund_requests')->where('id',$r->id)->update(['refund_minor'=>29900]);
        $before=$this->snapshot();$page=$this->actingAs($f['buyer'])->get(route('buyer.wallet'));
        $page->assertOk()->assertSee('Approved Refunds Without a Wallet Funding Record')->assertSee('299.00')->assertSee('No wallet funding record — not credited here')->assertSee('They do not increase your spendable balance.');
        $this->assertSame(0,$page->viewData('wallet')['available_minor']);$this->assertSame(1,$page->viewData('historical')->total());$this->invalid(fn()=>app(BuyerRefundFundingService::class)->allocate($r,$f['admin']));
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);$page=$this->actingAs($other)->get(route('buyer.wallet',['buyer_id'=>$f['buyer']->id]));$page->assertOk()->assertDontSee('RR-'.$r->id);$this->assertSame(0,$page->viewData('historical')->total());
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertDatabaseCount('buyer_wallet_entries',0);
    }
}
