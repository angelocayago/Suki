<?php

namespace Tests\Feature;

use App\Models\{BuyerRefundFunding,ReturnRefundRequest,SellerWalletEntry};
use App\Services\{ReturnRefundAllocationService,ReturnRefundService,SellerWalletService,SimulationRefundApprovalGuard};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Schema,Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class SimulationRefundApprovalGuardTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION')!=='sqlite' || getenv('DB_DATABASE')!==':memory:' || getenv('DB_URL')) throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp(); $this->assertStoragePathIsIsolated(); Http::preventStrayRequests(); Mail::fake(); Notification::fake(); Storage::fake('local'); Storage::fake('public');
        config(['simulation_settlement.enabled'=>true,'simulation_settlement.max_scenario_minor'=>100000,'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false,'suki_wallet.refund_credit_enabled'=>false,'suki_wallet.checkout_enabled'=>false]);
    }

    public function fixture(bool $discounted=false,int $commissionBps=1000): array
    {
        return (new SimulationSettlementTest('test_exact_discounted_source_is_conserved_and_reverse_shipping_is_explicitly_no_charge'))->fixture($discounted,true,false,$commissionBps);
    }

    public function data(): array { return ['type'=>'REFUND','quantity'=>2,'reason'=>'Synthetic damage','description'=>'Isolated approval safety check','confirmed'=>1]; }

    /** Adversarial independent writer, not an application submission shortcut. */
    public function staged(array $f,string $mode): ReturnRefundRequest
    {
        $r=new ReturnRefundRequest;
        $r->forceFill(['buyer_id'=>$f['buyer']->id,'order_id'=>$f['order']->id,'seller_order_id'=>$f['so']->id,'order_item_id'=>$f['item']->id,'seller_id'=>$f['seller']->id,
            'workflow_mode'=>$mode,'type'=>'REFUND','status'=>'SUBMITTED','quantity'=>$f['item']->quantity,'buyer_reason'=>'Synthetic','buyer_description'=>'Isolated staged record',
            'refund_minor'=>$f['item']->unit_price_minor*$f['item']->quantity-$f['item']->seller_funded_discount_minor-$f['item']->platform_funded_discount_minor])->save();
        return $r;
    }

    private function snapshot(): array
    {
        $out=[];foreach (['return_refund_requests','return_refund_accounting','return_refund_events','notification_outbox','seller_wallet_entries','buyer_refund_fundings','buyer_wallet_entries','simulation_settlements','simulation_refund_scopes','simulation_commitments','simulation_settlement_events','payouts'] as $table) $out[$table]=DB::table($table)->orderByRaw('1')->get()->toJson();
        return $out;
    }

    private function invalid(callable $callback): void
    {
        try { $callback(); $this->fail('Unsupported approval was accepted.'); } catch (ValidationException $e) { $this->assertArrayHasKey('simulation',$e->errors()); }
    }

    public static function unsupportedModes(): array { return ['Admin'=>['ADMIN_REVIEW'],'Financial'=>['FINANCIAL']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsupportedModes')]
    public function test_unsupported_submissions_and_existing_mode_decisions_fail_before_obligations(string $mode): void
    {
        $f=$this->fixture();$service=app(ReturnRefundService::class);$before=$this->snapshot();
        // Even enabling the old financial gate in this isolated case cannot admit
        // the cohort to the historical financial path.
        config(['returns.backend_enabled'=>true]);
        $this->invalid(fn()=>$mode==='ADMIN_REVIEW'?$service->submitForAdmin($f['buyer'],$f['item'],$this->data()):$service->submit($f['buyer'],$f['item'],$this->data()));
        $this->assertSame($before,$this->snapshot());
        $r=$this->staged($f,$mode);$before=$this->snapshot();
        $this->invalid(fn()=>$service->decide($r,$f['admin'],'APPROVED','Synthetic authorized Admin attempt'));
        $this->assertSame($before,$this->snapshot());
        $this->assertDatabaseCount('return_refund_accounting',0);$this->assertDatabaseCount('buyer_refund_fundings',0);$this->assertDatabaseCount('simulation_commitments',0);
    }

    public function test_supported_approval_is_coordinated_and_reserved_exactly_once(): void
    {
        $f=$this->fixture(true);$service=app(ReturnRefundService::class);$r=$service->submitForSeller($f['buyer'],$f['item'],$this->data());
        $seen=false;DB::listen(function($q)use(&$seen){if(str_contains($q->sql,'simulation_settlement_mutex') && str_starts_with(strtolower($q->sql),'select'))$seen=true;});
        $service->sellerDecide($r,$f['sellerUser'],'APPROVED','Synthetic review');$before=$this->snapshot();
        $service->sellerDecide($r,$f['sellerUser'],'APPROVED','Duplicate review');
        $this->assertTrue($seen);$this->assertSame($before,$this->snapshot());
        $this->assertSame(19698,(int)DB::table('simulation_settlements')->sole()->refund_reserved_minor);
        $this->assertSame(BuyerRefundFunding::READY,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertDatabaseCount('simulation_refund_scopes',1);$this->assertDatabaseCount('simulation_commitments',1);
        $this->assertSame(1,SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->count());$this->assertDatabaseCount('buyer_wallet_entries',0);
    }

    public function test_zero_and_partial_claims_remain_explicit_and_cannot_reuse_payout_reservations(): void
    {
        $f=$this->fixture(false,0);$wallet=app(SellerWalletService::class);$p=$wallet->request($f['seller'],20000);
        $service=app(ReturnRefundService::class);$r=$service->submitForSeller($f['buyer'],$f['item'],$this->data());$service->sellerDecide($r,$f['sellerUser'],'APPROVED','Synthetic review');
        $c=DB::table('simulation_commitments')->where('kind','REFUND')->sole();$book=DB::table('simulation_settlements')->sole();
        $this->assertSame('PARTIAL',$c->status);$this->assertSame(0,(int)$c->seller_minor+(int)$c->platform_minor);
        $this->assertSame(20000,(int)$book->payout_reserved_minor);$this->assertSame(BuyerRefundFunding::AWAITING,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertSame(-20000,$wallet->summary($f['seller'])['available_minor']);
        try{$wallet->request($f['seller'],1);$this->fail('Refund claim reused');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
        $wallet->review($p,$f['admin'],'REJECTED','Synthetic release');
        $this->assertSame(BuyerRefundFunding::READY,BuyerRefundFunding::findOrFail($r->id)->status);
        $this->assertSame(20000,(int)DB::table('simulation_settlements')->sole()->refund_reserved_minor);
    }

    public function test_private_adjustment_writer_cannot_run_outside_coordinator(): void
    {
        $f=$this->fixture();$r=app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],$this->data());$before=$this->snapshot();
        $writer=new \ReflectionMethod(ReturnRefundService::class,'recordRefundAdjustment');
        $this->invalid(fn()=>$writer->invoke(app(ReturnRefundService::class),$r,$f['so'],app(ReturnRefundAllocationService::class)->calculate($f['item'],2)));
        $this->assertSame($before,$this->snapshot());
    }

    public function test_missing_coordinator_or_database_guard_fails_closed(): void
    {
        $f=$this->fixture();$r=app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],$this->data());$before=$this->snapshot();
        foreach (['mutex','funding','trigger'] as $case) {
            DB::beginTransaction();try {
                if ($case==='mutex') Schema::drop('simulation_settlement_mutex');
                elseif ($case==='funding') Schema::drop('buyer_refund_fundings');
                else DB::statement('DROP TRIGGER sim_refund_request_update');
                $this->invalid(fn()=>app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Unavailable guard'));
            } finally { DB::rollBack(); }
            $this->assertSame($before,$this->snapshot());
        }
    }

    public function test_independent_status_and_obligation_writes_require_coordination(): void
    {
        $f=$this->fixture();$r=$this->staged($f,'SELLER_REVIEW');$before=$this->snapshot();
        $writes=[
            fn()=>DB::table('return_refund_requests')->where('id',$r->id)->update(['status'=>'ADMIN_APPROVED']),
            fn()=>DB::table('return_refund_accounting')->insert(['return_refund_request_id'=>$r->id,'merchandise_minor'=>20000,'seller_adjustment_minor'=>18000,'commission_reversal_minor'=>2000,'seller_voucher_minor'=>0,'platform_voucher_minor'=>0,'refund_status'=>'REFUND_DUE','created_at'=>now(),'updated_at'=>now()]),
            fn()=>SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'return_refund_request_id'=>$r->id,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-18000,'reference'=>'RR-'.$r->id,'description'=>'Independent writer']),
        ];
        foreach ($writes as $write) {
            try { DB::transaction($write);$this->fail('Uncoordinated write succeeded'); }catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
            $this->assertSame($before,$this->snapshot());
        }
    }

    public function test_reverse_coverage_detects_missing_commitment_and_rolls_back(): void
    {
        $f=$this->fixture();$s=app(ReturnRefundService::class);$r=$s->submitForSeller($f['buyer'],$f['item'],$this->data());$s->sellerDecide($r,$f['sellerUser'],'APPROVED','Synthetic review');$before=$this->snapshot();
        $this->invalid(fn()=>DB::transaction(function()use($r){DB::table('simulation_commitments')->where('return_refund_request_id',$r->id)->delete();app(SimulationRefundApprovalGuard::class)->verifyCoverage();}));
        $this->assertSame($before,$this->snapshot());
    }

    public function test_enclosing_failure_rolls_back_approval_debit_funding_and_claim(): void
    {
        $f=$this->fixture();$s=app(ReturnRefundService::class);$r=$s->submitForSeller($f['buyer'],$f['item'],$this->data());$before=$this->snapshot();
        try { DB::transaction(function()use($s,$r,$f){$s->sellerDecide($r,$f['sellerUser'],'APPROVED','Synthetic review');throw new \RuntimeException('Injected approval failure');}); }
        catch(\RuntimeException $e){$this->assertSame('Injected approval failure',$e->getMessage());}
        $this->assertSame($before,$this->snapshot());$this->assertFalse(config('suki_wallet.refund_credit_enabled'));$this->assertFalse(config('suki_wallet.checkout_enabled'));$this->assertFalse(config('returns.earning_release_enabled'));
    }

    public function test_non_enrolled_admin_review_still_works_and_financial_gate_stays_closed(): void
    {
        $f=(new DirectAdminReturnRefundTest('test_direct_approval_is_private_idempotent_and_records_one_wallet_adjustment_without_payment_or_restock'))->purchase();$s=app(ReturnRefundService::class);
        $r=$s->submitForAdmin($f['buyer'],$f['item'],$this->data());$s->decide($r,$f['admin'],'APPROVED','Existing authorized review');
        $this->assertSame('ADMIN_APPROVED',$r->fresh()->status);$this->assertDatabaseCount('simulation_refund_scopes',0);$this->assertSame(-18000,SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->sole()->amount_minor);
        $this->assertFalse(config('returns.backend_enabled'));
        try{$s->submit($f['buyer'],$f['item'],$this->data());$this->fail('Financial mode activated');}catch(ValidationException $e){$this->assertArrayHasKey('request',$e->errors());}
        $this->assertDatabaseCount('return_refund_accounting',1);$this->assertDatabaseCount('buyer_wallet_entries',0);
    }
}
