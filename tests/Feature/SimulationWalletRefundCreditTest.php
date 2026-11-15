<?php

namespace Tests\Feature;

use App\Models\{BuyerRefundFunding,BuyerWalletEntry,ReturnRefundRequest,User};
use App\Services\{BuyerRefundFundingService,BuyerWalletService,ReturnRefundService,SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Storage};
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedStorageTestCase;

class SimulationWalletRefundCreditTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION')!=='sqlite' || getenv('DB_DATABASE')!==':memory:' || getenv('DB_URL')) throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp(); $this->assertStoragePathIsIsolated();Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['simulation_settlement.enabled'=>true,'simulation_settlement.max_scenario_minor'=>100000,'suki_wallet.enabled'=>true,'suki_wallet.refund_credit_enabled'=>true,'suki_wallet.checkout_enabled'=>false,'returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }

    public function fixture(bool $discounted=true): array
    {
        return (new SimulationSettlementTest('test_exact_discounted_source_is_conserved_and_reverse_shipping_is_explicitly_no_charge'))->fixture($discounted);
    }
    public function approve(array $f,string $decision='APPROVED'): ReturnRefundRequest
    {
        $s=app(ReturnRefundService::class);$r=$s->submitForSeller($f['buyer'],$f['item'],['type'=>'REFUND','quantity'=>2,'reason'=>'Synthetic damage','description'=>'Synthetic funded refund','confirmed'=>1]);
        return $s->sellerDecide($r,$f['sellerUser'],$decision,'Synthetic Seller review');
    }
    private function snapshot(): array
    {
        $out=[];foreach (['orders','seller_orders','order_items','shipments','product_variants','return_refund_requests','return_refund_accounting','seller_wallet_entries','payouts','buyer_wallets','buyer_wallet_entries','buyer_refund_fundings','simulation_commitments','simulation_settlements','simulation_wallet_backings','simulation_settlement_events'] as $t) $out[$t]=DB::table($t)->orderByRaw('1')->get()->toJson();return $out;
    }
    private function invalid(callable $call): void
    {
        try{$call();$this->fail('Unbacked credit accepted');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
    }

    public function test_full_discounted_refund_posts_once_and_retains_source_backing(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$sellerBefore=DB::table('seller_wallet_entries')->get()->toJson();
        $native=[];foreach(['orders','seller_orders','order_items','shipments','product_variants','return_refund_accounting','rider_shipping_fee_earnings']as$t)$native[$t]=DB::table($t)->get()->toJson();
        $this->assertSame(0,app(BuyerWalletService::class)->summary($f['buyer'])['balance_minor']);$this->assertDatabaseCount('buyer_wallets',0);
        $entry=app(BuyerRefundFundingService::class)->credit($r,$f['admin']);
        $this->assertSame(19698,$entry->amount_minor);$this->assertSame($f['buyer']->id,(int)$entry->buyer_id);$this->assertSame('REFUND_CREDIT',$entry->type);
        $this->assertSame(['balance_minor'=>19698,'available_minor'=>19698],app(BuyerWalletService::class)->summary($f['buyer']));
        $this->assertDatabaseCount('buyer_wallets',1);$this->assertDatabaseCount('buyer_wallet_entries',1);$this->assertDatabaseCount('simulation_wallet_backings',1);
        $b=DB::table('simulation_wallet_backings')->sole();$s=DB::table('simulation_settlements')->sole();
        $this->assertSame('BUYER_REFUND_BACKING',$b->kind);$this->assertSame(17899,(int)$b->seller_minor);$this->assertSame(1799,(int)$b->platform_minor);
        $this->assertSame(24698,(int)$s->issued_minor);$this->assertSame(0,(int)$s->refund_reserved_minor);$this->assertSame(19698,(int)$s->consumed_minor);$this->assertSame(5000,(int)$s->shipping_protected_minor);
        $this->assertSame(0,(int)$s->seller_available_minor);$this->assertSame(0,(int)$s->platform_available_minor);
        $funding=BuyerRefundFunding::findOrFail($r->id)->load(['postedCredit','simulationBacking']);
        $this->assertSame(BuyerRefundFunding::CREDITED,$funding->status);$this->assertSame('Credited to Suki Wallet',$funding->statusLabel());
        $this->assertNotNull($funding->consumed_at);$this->assertSame($sellerBefore,DB::table('seller_wallet_entries')->get()->toJson());
        foreach($native as$t=>$rows)$this->assertSame($rows,DB::table($t)->get()->toJson());
        $this->assertSame(1,DB::table('seller_wallet_entries')->where('type','REFUND_ADJUSTMENT')->count());
        $this->assertFalse(app(BuyerWalletService::class)->checkoutAvailable());$this->assertFalse(config('returns.earning_release_enabled'));
    }

    public function test_successful_replay_and_repeated_approval_leave_every_financial_row_unchanged(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$credit=app(BuyerRefundFundingService::class)->credit($r,$f['admin']);$before=$this->snapshot();
        $r->buyer_id=User::factory()->create(['role'=>'buyer'])->id; // input properties are never the destination
        $again=app(BuyerRefundFundingService::class)->credit($r,$f['admin']);
        app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Duplicate approval');
        $this->assertSame($credit->id,$again->id);$this->assertSame($before,$this->snapshot());
    }

    public function test_wallet_page_shows_owned_credit_and_other_buyers_cannot_access_it(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$entry=app(BuyerRefundFundingService::class)->credit($r,$f['admin']);
        $this->actingAs($f['buyer'])->get(route('buyer.wallet'))->assertOk()->assertSee('Refund Credit')->assertSee('196.98')->assertSee('Credited to Suki Wallet')->assertSee('RR-'.$r->id)->assertSee(\App\Support\ManilaTime::format($entry->created_at));
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->get(route('buyer.wallet',['buyer_id'=>$f['buyer']->id]))->assertOk()->assertSee('No wallet transactions yet')->assertDontSee('RR-'.$r->id)->assertDontSee('196.98');
        $this->assertDatabaseMissing('buyer_wallets',['buyer_id'=>$other->id]);
        $this->actingAs($f['sellerUser'])->get(route('buyer.wallet'))->assertForbidden();
    }

    public function test_partial_and_paid_out_refunds_never_create_wallet_funds_or_clear_deficits(): void
    {
        $f=$this->fixture(false);$wallet=app(SellerWalletService::class);$p=$wallet->request($f['seller'],9000);$wallet->review($p,$f['admin'],'APPROVED');$wallet->markPaid($p,$f['admin'],'manual','SIMULATION ONLY');
        $r=$this->approve($f);$before=$this->snapshot();$this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));
        $this->assertSame($before,$this->snapshot());$this->assertSame(-9000,$wallet->summary($f['seller'])['available_minor']);
        $this->assertDatabaseCount('buyer_wallets',0);$this->assertDatabaseCount('buyer_wallet_entries',0);$this->assertSame(9000,(int)DB::table('simulation_settlements')->sole()->consumed_minor);
    }

    public function test_reserved_payout_claims_cannot_be_credited_until_the_reservation_is_released(): void
    {
        $f=$this->fixture();$wallet=app(SellerWalletService::class);$p=$wallet->request($f['seller'],10000);$r=$this->approve($f);$before=$this->snapshot();
        $this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));$this->assertSame($before,$this->snapshot());
        $wallet->review($p,$f['admin'],'REJECTED','Synthetic release');$entry=app(BuyerRefundFundingService::class)->credit($r,$f['admin']);
        $this->assertSame(19698,$entry->amount_minor);$this->assertSame(0,(int)DB::table('simulation_settlements')->sole()->payout_reserved_minor);
        $this->invalid(fn()=>$wallet->request($f['seller'],1));
    }

    public static function disallowedRequests(): array { return ['pending'=>[null],'rejected'=>['REJECTED']]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('disallowedRequests')]
    public function test_unapproved_requests_do_not_credit(?string $decision): void
    {
        $f=$this->fixture();$s=app(ReturnRefundService::class);$r=$s->submitForSeller($f['buyer'],$f['item'],['type'=>'REFUND','quantity'=>2,'reason'=>'Synthetic','description'=>'Synthetic','confirmed'=>1]);
        if($decision)$s->sellerDecide($r,$f['sellerUser'],$decision,'Synthetic rejection');$before=$this->snapshot();
        $this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));$this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('buyer_wallets',0);
    }

    public function test_flags_and_untrusted_actors_fail_closed(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$before=$this->snapshot();
        foreach([$f['buyer'],$f['sellerUser']]as$actor){try{app(BuyerRefundFundingService::class)->credit($r,$actor);$this->fail('Untrusted actor posted credit');}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}}
        foreach(['suki_wallet.refund_credit_enabled','simulation_settlement.enabled']as$flag){config([$flag=>false]);$this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));config([$flag=>true]);}
        $this->assertSame($before,$this->snapshot());
    }

    public function test_tampered_provenance_and_inconsistent_replay_fail_without_compensating_credit(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$service=app(BuyerRefundFundingService::class);
        foreach([false,true]as$posted){if($posted)$service->credit($r,$f['admin']);$before=$this->snapshot();
            DB::beginTransaction();try{DB::table('custody_sources')->update(['provenance_hash'=>str_repeat('0',64)]);$this->invalid(fn()=>$service->credit($r,$f['admin']));}finally{DB::rollBack();}
            $this->assertSame($before,$this->snapshot());
        }
        $this->assertDatabaseCount('buyer_wallet_entries',1);
    }

    public function test_incorrect_buyer_identity_and_status_only_credit_are_rejected(): void
    {
        $f=$this->fixture();$r=$this->approve($f);$before=$this->snapshot();$other=User::factory()->create(['role'=>'buyer'])->id;
        try{DB::transaction(fn()=>DB::table('return_refund_requests')->where('id',$r->id)->update(['buyer_id'=>$other]));$this->fail('Purchase owner changed');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        $this->invalid(fn()=>DB::transaction(function()use($r,$f){DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>1]);DB::table('buyer_refund_fundings')->where('return_refund_request_id',$r->id)->update(['status'=>BuyerRefundFunding::CREDITED,'consumed_at'=>now()]);DB::table('simulation_settlement_mutex')->where('id',1)->update(['operation_open'=>0]);app(BuyerRefundFundingService::class)->credit($r,$f['admin']);}));
        $this->assertSame($before,$this->snapshot());
    }

    public static function failures(): array { return ['posting'=>['post'],'consumption'=>['backing']]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('failures')]
    public function test_posting_and_consumption_failures_roll_back_all_related_changes(string $where): void
    {
        $f=$this->fixture();$r=$this->approve($f);$before=$this->snapshot();
        if($where==='post') DB::unprepared("CREATE TRIGGER w5jb_fail_post BEFORE INSERT ON buyer_wallet_entries WHEN NEW.type='REFUND_CREDIT' BEGIN SELECT RAISE(ABORT,'Injected posting failure'); END");
        else DB::unprepared("CREATE TRIGGER w5jb_fail_backing BEFORE INSERT ON simulation_wallet_backings BEGIN SELECT RAISE(ABORT,'Injected consumption failure'); END");
        try{app(BuyerRefundFundingService::class)->credit($r,$f['admin']);$this->fail('Failure injection ignored');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('Injected',$e->getMessage());}
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('buyer_wallets',0);$this->assertDatabaseCount('buyer_wallet_entries',0);$this->assertDatabaseCount('simulation_wallet_backings',0);
    }

    public function test_historical_299_obligation_is_never_converted_or_repaired(): void
    {
        $f=(new DirectAdminReturnRefundTest('test_direct_approval_is_private_idempotent_and_records_one_wallet_adjustment_without_payment_or_restock'))->purchase();
        $s=app(ReturnRefundService::class);$r=$s->submitForAdmin($f['buyer'],$f['item'],['type'=>'REFUND','quantity'=>2,'reason'=>'Legacy','description'=>'Synthetic historical record','confirmed'=>1]);$s->decide($r,$f['admin'],'APPROVED','Historical approval');
        $r->update(['refund_minor'=>29900]);$before=$this->snapshot();$this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));
        $this->assertSame($before,$this->snapshot());$this->assertSame(29900,(int)$r->fresh()->refund_minor);$this->assertDatabaseCount('buyer_wallets',0);
    }

    public function test_authoritative_ledger_sum_cannot_overflow_on_a_new_credit(): void
    {
        $f=$this->fixture();$r=$this->approve($f);\App\Models\BuyerWallet::create(['buyer_id'=>$f['buyer']->id]);
        // Synthetic pre-existing, unclassified ledger rows; they are neither
        // simulation issuance nor proof of funds for this refund.
        $debit=new BuyerWalletEntry;$debit->forceFill(['buyer_id'=>$f['buyer']->id,'type'=>'ORDER_PAYMENT','status'=>'POSTED','amount_minor'=>-1,'order_id'=>$f['order']->id,'source_identity'=>'LEGACY-SYNTHETIC-DEBIT','created_at'=>now()])->save();
        $reversal=new BuyerWalletEntry;$reversal->forceFill(['buyer_id'=>$f['buyer']->id,'type'=>'PAYMENT_REVERSAL','status'=>'POSTED','amount_minor'=>PHP_INT_MAX,'order_id'=>$f['order']->id,'source_entry_id'=>$debit->id,'source_identity'=>'LEGACY-SYNTHETIC-REVERSAL','created_at'=>now()])->save();
        $before=$this->snapshot();$this->invalid(fn()=>app(BuyerRefundFundingService::class)->credit($r,$f['admin']));
        $this->assertSame($before,$this->snapshot());$this->assertDatabaseCount('simulation_wallet_backings',0);
    }
}
