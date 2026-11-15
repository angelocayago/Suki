<?php
namespace Tests\Feature;

use App\Models\{CustodySource,LogisticsCodRemittanceItem,RiderCodRemittance,User};
use App\Services\{BuyerRefundFundingService,CodRemittanceService,CustodySourceService,SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class CustodySourceTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>false,'suki_wallet.checkout_enabled'=>false,'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }
    public function fixture(bool $discounted=false,bool $accept=true): array
    {
        $helper=new BuyerWalletFoundationTest('test_zero_balance_wallet_page_history_and_account_link_are_read_only_and_owned');$f=$helper->purchase($discounted);
        $receipt=RiderCodRemittance::sole();$provider=User::findOrFail($receipt->provider->user_id);$cod=app(CodRemittanceService::class);
        $cod->submitRider($receipt,$receipt->rider->user,'synthetic-only');$cod->reviewRider($receipt,$provider,'ACCEPTED',null);
        $batch=$cod->submitLogistics($provider,[$receipt->id],'synthetic-only');if($accept)$cod->reviewLogistics($batch,$f['admin'],'ACCEPTED',null);
        return [$f,LogisticsCodRemittanceItem::sole(),$batch,$receipt];
    }
    private function snapshot(): array
    {
        $out=[];foreach(['orders','seller_orders','order_items','shipments','seller_wallet_entries','payouts','payments','return_refund_requests','return_refund_accounting','buyer_refund_fundings','buyer_wallet_entries']as$table)$out[$table]=DB::table($table)->get()->toJson();return $out;
    }
    private function invalid(callable $operation): void
    {
        try{$operation();$this->fail('Invalid source accepted.');}catch(ValidationException $e){$this->assertArrayHasKey('custody_source',$e->errors());}
    }
    public function test_accepted_source_is_registered_once_with_exact_components_but_wholly_unavailable(): void
    {
        [$f,$item]=$this->fixture(true);$before=$this->snapshot();$service=app(CustodySourceService::class);
        $proof=$service->inspect($item,$f['admin']);$this->assertFalse($proof['can_allocate']);$this->assertDatabaseCount('custody_sources',0);
        $source=$service->record($item,$f['admin']);$retry=$service->record($item,$f['admin']);
        $this->assertSame($source->id,$retry->id);$this->assertDatabaseCount('custody_sources',1);
        $this->assertSame('SIMULATED_ACCEPTED_COD',$source->kind);$this->assertSame('UNRECONCILED',$source->state);$this->assertSame('PHP',$source->currency);
        $this->assertSame(24698,$source->accepted_minor);$this->assertSame($source->accepted_minor,$source->unreconciled_minor);
        foreach(['allocatable_minor','committed_minor','consumed_minor']as$field)$this->assertSame(0,$source->$field);
        $this->assertSame(['merchandise_minor'=>20000,'seller_discount_minor'=>101,'platform_discount_minor'=>201,'commission_minor'=>2000,'shipping_minor'=>5000,'collected_minor'=>24698],$source->components);
        $this->assertSame('COD-UPSTREAM-ITEM-'.$item->id,$source->source_identity);$this->assertSame($before,$this->snapshot());
    }
    public function test_missing_acceptance_audit_and_inconsistent_amounts_cannot_register_sources(): void
    {
        [$f,$item,$batch]=$this->fixture(false,false);$service=app(CustodySourceService::class);$this->invalid(fn()=>$service->record($item,$f['admin']));
        app(CodRemittanceService::class)->reviewLogistics($batch,$f['admin'],'ACCEPTED',null);
        foreach(['audit','submission_audit','submission_amount','amount','linkage','collection']as$case){DB::beginTransaction();try{
            if($case==='audit')DB::table('cod_remittance_audits')->where('action','ADMIN_ACCEPTED')->delete();
            elseif($case==='submission_audit')DB::table('cod_remittance_audits')->where('action','RIDER_SUBMITTED')->delete();
            elseif($case==='submission_amount')DB::table('rider_cod_remittances')->where('shipment_id',$f['shipment']->id)->update(['submitted_minor'=>null]);
            elseif($case==='amount')DB::table('logistics_cod_remittance_items')->where('id',$item->id)->increment('amount_minor');
            elseif($case==='linkage'){
                $other=\App\Models\LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics'])->id,'name'=>'Other synthetic provider','slug'=>'other-synthetic-provider','status'=>'approved']);
                DB::table('logistics_cod_remittances')->where('id',$batch->id)->update(['logistics_provider_id'=>$other->id]);
            }
            else DB::table('shipments')->where('id',$f['shipment']->id)->update(['cod_collected'=>false]);
            $this->invalid(fn()=>$service->record($item,$f['admin']));$this->assertDatabaseCount('custody_sources',0);
        }finally{DB::rollBack();}}
    }
    public function test_untrusted_actors_cannot_register_and_changed_evidence_is_not_overwritten(): void
    {
        [$f,$item]=$this->fixture();$service=app(CustodySourceService::class);$f['buyer']->role='admin';
        foreach([$f['buyer'],$f['sellerUser']]as$actor){try{$service->record($item,$actor);$this->fail('Untrusted source writer accepted.');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}}
        $source=$service->record($item,$f['admin']);$original=$source->fresh()->toJson();
        // A modified recorded hash must be reviewed, never silently repaired.
        DB::table('custody_sources')->where('id',$source->id)->update(['provenance_hash'=>str_repeat('0',64)]);
        $this->invalid(fn()=>$service->record($item,$f['admin']));$this->assertSame(str_repeat('0',64),$source->fresh()->provenance_hash);$this->assertNotSame($original,$source->fresh()->toJson());
    }
    public function test_old_payouts_refunds_and_recovery_deficits_never_become_source_capacity(): void
    {
        [$f,$item]=$this->fixture();$helper=new BuyerWalletFoundationTest('test_zero_balance_wallet_page_history_and_account_link_are_read_only_and_owned');
        $request=$helper->request($f);$helper->approve($f,$request);
        \App\Models\Payout::create(['seller_id'=>$f['seller']->id,'amount_minor'=>100,'status'=>'PAID']);
        $before=$this->snapshot();$source=app(CustodySourceService::class)->record($item,$f['admin']);
        $this->assertSame(0,$source->allocatable_minor);$this->assertSame(-18000,app(SellerWalletService::class)->summary($f['seller'])['available_minor']);
        try{app(BuyerRefundFundingService::class)->allocate($request,$f['admin']);$this->fail('Unreconciled custody funded a refund.');}catch(ValidationException $e){$this->assertArrayHasKey('wallet_funding',$e->errors());}
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('buyer_wallet_entries',0);$this->assertDatabaseCount('seller_wallet_entries',1);
    }
    public function test_constraints_uniqueness_and_rollback_do_not_allow_capacity_or_evidence_loss(): void
    {
        [$f,$item]=$this->fixture();$source=app(CustodySourceService::class)->record($item,$f['admin']);
        foreach([['allocatable_minor'=>1],['committed_minor'=>1],['consumed_minor'=>1],['unreconciled_minor'=>0],['accepted_minor'=>-1],['accepted_minor'=>1.5],['currency'=>'USD'],['state'=>'AVAILABLE']]as$change){
            try{DB::transaction(fn()=>DB::table('custody_sources')->where('id',$source->id)->update($change));$this->fail('Unsafe source mutation accepted.');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        }
        $copy=$source->getAttributes();unset($copy['id']);$copy['source_identity']='forged-alias';
        try{DB::transaction(fn()=>DB::table('custody_sources')->insert($copy));$this->fail('Duplicate source accepted.');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        $migration=require database_path('migrations/2026_11_15_000001_create_unreconciled_custody_sources.php');
        try{$migration->down();$this->fail('Source provenance destroyed.');}catch(\RuntimeException $e){$this->assertStringContainsString('retained',$e->getMessage());}
        DB::table('custody_sources')->delete();
        try{DB::transaction(function()use($item,$f){app(CustodySourceService::class)->record($item,$f['admin']);throw new \RuntimeException('Synthetic enclosing transaction failure');});}catch(\RuntimeException $e){$this->assertSame('Synthetic enclosing transaction failure',$e->getMessage());}
        $this->assertDatabaseCount('custody_sources',0);
    }
}
