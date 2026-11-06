<?php
namespace Tests\Feature;

use App\Enums\{OrderStatus, ComplaintCategory};
use App\Models\{CartItem, Category, Complaint, DeliveryEvent, LogisticsProvider, Order, OrderItem, Product, ProductVariant, ReturnRefundRequest, Rider, RiderCodRemittance, Seller, SellerOrder, SellerWalletEntry, Shipment, User, Voucher};
use App\Services\{CheckoutService, CodRemittanceService, MoneyReportService, ReturnRefundService, SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class HeldEarningReconciliationTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated memory database required.');
        parent::setUp();$this->assertStoragePathIsIsolated();$this->assertSame('sqlite',DB::connection()->getDriverName());$this->assertSame(':memory:',DB::connection()->getDatabaseName());
        Mail::fake();Notification::fake();Http::preventStrayRequests();Storage::fake('local');Storage::fake('public');config(['returns.backend_enabled'=>true,'returns.earning_release_enabled'=>true]);
    }
    private function fixture(bool $vouchers=false, bool $otherParcel=false): array
    {
        $buyer=User::factory()->create(['role'=>'buyer','status'=>'active']);$admin=User::factory()->create(['role'=>'admin','status'=>'active']);
        $seller=Seller::create(['user_id'=>User::factory()->create(['role'=>'seller','status'=>'active'])->id,'name'=>'Integrity shop','slug'=>'integrity-shop','status'=>'approved']);
        $category=Category::create(['name'=>'Integrity','slug'=>'integrity','is_active'=>true]);$variants=collect();
        for($i=0;$i<2;$i++){
            $shop=$seller;
            if($otherParcel && $i===1)$shop=Seller::create(['user_id'=>User::factory()->create(['role'=>'seller','status'=>'active'])->id,'name'=>'Other shop','slug'=>'other-shop','status'=>'approved']);
            $p=Product::create(['seller_id'=>$shop->id,'category_id'=>$category->id,'name'=>'Product '.$i,'slug'=>'integrity-'.$i,'is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>1000]);
            $v=ProductVariant::create(['product_id'=>$p->id,'sku'=>'INT-'.$i,'name'=>'Flavor / pack','price_minor'=>10001,'stock'=>10,'is_active'=>true]);$variants->push($v);
            CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$v->id,'product_slug'=>$p->slug,'product_name'=>$p->name,'quantity'=>2,'price'=>100.01]);
        }
        $codes=[];
        if($vouchers)foreach(['SELLER'=>101,'PLATFORM'=>201] as $scope=>$amount){$v=Voucher::create(['code'=>'INT-'.$scope,'scope'=>$scope,'seller_id'=>$scope==='SELLER'?$seller->id:null,'discount_type'=>'FIXED','discount_minor'=>$amount,'is_active'=>true]);$codes[]=$v->code;}
        $order=app(CheckoutService::class)->checkout($buyer,['recipient_name'=>'Test','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test'],'cod',$codes);
        $so=$order->sellerOrders->first();$shipment=$so->shipment;
        $provider=LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics'])->id,'name'=>'Provider','slug'=>'integrity-provider','status'=>'approved']);
        $rider=Rider::create(['user_id'=>User::factory()->create(['role'=>'rider','status'=>'active'])->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'TEST','is_active'=>true]);
        $so->update(['workflow_status'=>'DELIVERED','status'=>'delivered','delivered_at'=>now(),'logistics_provider_id'=>$provider->id]);
        $shipment->update(['workflow_status'=>'DELIVERED','status'=>'delivered','logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'proof_path'=>'isolated/proof.jpg','proof_delivered_at'=>now(),'delivered_by'=>$rider->user_id,'cod_collected'=>true,'cod_collected_minor'=>$shipment->cod_amount_minor,'cod_collected_at'=>now()]);
        DeliveryEvent::create(['shipment_id'=>$shipment->id,'status'=>'COD_COLLECTED','attempt'=>0,'user_id'=>$rider->user_id,'occurred_at'=>now()]);
        app(CodRemittanceService::class)->createLiability($shipment->fresh(['rider']));
        $order->update(['status'=>$otherParcel?'PLACED':'DELIVERED','payment_status'=>$otherParcel?'UNPAID':'PAID']);
        $items=$so->items;return compact('buyer','admin','seller','order','so','shipment','items','variants','rider');
    }

    private function completed(bool $settled=true): array
    {
        $f=$this->fixture();$f['order']->update(['status'=>'COMPLETED']);$f['so']->update(['workflow_status'=>'COMPLETED','status'=>'completed','completed_at'=>now()]);$f['shipment']->update(['workflow_status'=>'COMPLETED']);
        app(SellerWalletService::class)->creditCompleted($f['so']);$f['source']=SellerWalletEntry::where('type','SALE_EARNING')->sole();$this->assertSame('HELD',$f['source']->status);
        if($settled)$this->settle($f,false);
        return $f;
    }

    private function settle(array $f,bool $throughService): void
    {
        $svc=app(CodRemittanceService::class);$liability=RiderCodRemittance::sole();$provider=$f['rider']->logisticsProvider;
        $svc->submitRider($liability,$f['rider']->user);$svc->reviewRider($liability,$provider->owner,'ACCEPTED',null);
        $batch=$svc->submitLogistics($provider->owner,[$liability->id],null);
        if($throughService)$svc->reviewLogistics($batch,$f['admin'],'ACCEPTED',null);
        else $batch->update(['status'=>'ACCEPTED','accepted_minor'=>$batch->expected_minor,'reviewed_by'=>$f['admin']->id,'reviewed_at'=>now()]);
    }

    private function request(array $f,?OrderItem $item=null): ReturnRefundRequest
    {
        $item??=$f['items']->first();return app(ReturnRefundService::class)->submit($f['buyer'],$item,['type'=>'REFUND','quantity'=>$item->quantity,'reason'=>'Damaged','description'=>'Damaged item']);
    }

    private function complaint(array $f,?SellerOrder $so=null): Complaint
    {
        $so??=$f['so'];return Complaint::create(['reference'=>'HELD-'.$so->id,'complainant_id'=>$f['buyer']->id,'complainant_role'=>'BUYER','order_id'=>$so->order_id,'seller_order_id'=>$so->id,'buyer_id'=>$f['buyer']->id,'seller_id'=>$so->seller_id,'category'=>ComplaintCategory::Other->value,'status'=>'OPEN','subject'=>'Financial review','description'=>'Linked financial complaint','context_snapshot'=>[]]);
    }

    public function test_full_release_is_append_only_idempotent_and_not_new_lifetime_earnings(): void
    {
        $f=$this->completed();$wallet=app(SellerWalletService::class);$before=$f['source']->fresh()->toJson();$e=$f['source']->amount_minor;
        $this->assertSame($e,$wallet->summary($f['seller'])['held_minor']);
        $this->assertTrue($wallet->reconcileSellerOrder($f['so']));$this->assertFalse($wallet->reconcileSellerOrder($f['so']));
        $release=SellerWalletEntry::where('type','EARNING_RELEASE')->sole();$this->assertSame($f['source']->id,$release->source_wallet_entry_id);$this->assertSame($e,$release->amount_minor);$this->assertNull($release->return_refund_request_id);
        $summary=$wallet->summary($f['seller']);$this->assertSame($e,$summary['available_minor']);$this->assertSame(0,$summary['held_minor']);$this->assertSame($e,$summary['total_earnings_minor']);$this->assertSame($before,$f['source']->fresh()->toJson());
        $this->assertSame($f['source']->id,$release->sourceEarning->id);$this->assertSame($release->id,$f['source']->release->id);
    }

    public function test_two_refunds_release_once_only_after_the_last_active_request(): void
    {
        $f=$this->completed();$a=$this->request($f);$b=$this->request($f,$f['items']->last());$wallet=app(SellerWalletService::class);$e=$f['source']->amount_minor;$before=$f['source']->fresh()->toJson();
        app(ReturnRefundService::class)->decide($a,$f['admin'],'APPROVED','Verified');
        $debit=-SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->sum('amount_minor');
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());$this->assertSame($e,$wallet->summary($f['seller'])['held_minor']);$this->assertSame(-$debit,$wallet->summary($f['seller'])['available_minor']);
        app(ReturnRefundService::class)->decide($b,$f['admin'],'APPROVED','Verified');
        app(ReturnRefundService::class)->decide($b,$f['admin'],'APPROVED','Replay');
        $this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());$this->assertSame(2,SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->count());
        $this->assertSame(0,$wallet->summary($f['seller'])['available_minor']);$this->assertSame(0,$wallet->summary($f['seller'])['held_minor']);$this->assertSame($before,$f['source']->fresh()->toJson());
    }

    public function test_one_approved_item_leaves_the_other_items_entitlement_payable(): void
    {
        $f=$this->completed();$r=$this->request($f);app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Verified');
        $d=-SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->sum('amount_minor');
        $this->assertSame($f['source']->amount_minor-$d,app(SellerWalletService::class)->summary($f['seller'])['available_minor']);
    }

    public function test_rejection_releases_only_when_cod_and_all_other_holds_are_clear(): void
    {
        $f=$this->completed(false);$r=$this->request($f);app(ReturnRefundService::class)->decide($r,$f['admin'],'REJECTED','Not supported');
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->settle($f,true);$this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());$this->assertSame(0,SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->count());
    }

    public function test_complaint_resolution_does_not_bypass_cod_but_later_cod_acceptance_releases(): void
    {
        $f=$this->completed(false);$complaint=$this->complaint($f);$before=$f['source']->fresh()->toJson();
        app(\App\Services\Admin\ComplaintService::class)->dismiss($complaint,$f['admin'],'Resolved');
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->settle($f,true);$this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());$this->assertSame($before,$f['source']->fresh()->toJson());
    }

    public function test_cod_acceptance_cannot_release_while_a_linked_complaint_remains(): void
    {
        $f=$this->completed(false);$complaint=$this->complaint($f);$this->settle($f,true);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        app(\App\Services\Admin\ComplaintService::class)->dismiss($complaint,$f['admin'],'Resolved');
        $this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
    }

    public function test_unrelated_order_complaint_does_not_hold_target_release_but_pauses_seller_withdrawals(): void
    {
        $f=$this->completed();$parent=$f['order']->replicate();$parent->order_number='OTHER';$parent->reference='OTHER';$parent->save();
        $other=$f['so']->replicate();$other->order_id=$parent->id;$other->save();$this->complaint($f,$other);
        $wallet=app(SellerWalletService::class);$this->assertTrue($wallet->reconcileSellerOrder($f['so']));$this->assertNotNull($wallet->payoutRestriction($f['seller']->id));
        $this->actingAs($f['seller']->owner)->get(route('seller.wallet'))->assertOk()->assertSee('linked financial complaint')->assertDontSee('id="walletPayoutForm"',false);
    }

    public function test_existing_reservations_are_deducted_once_and_not_rewritten(): void
    {
        $f=$this->completed();$wallet=app(SellerWalletService::class);$wallet->reconcileSellerOrder($f['so']);$p=$wallet->request($f['seller'],1000);
        $row=SellerWalletEntry::where('payout_id',$p->id)->sole();$before=$row->toJson();$wallet->reconcileSellerOrder($f['so']);
        $s=$wallet->summary($f['seller']);$this->assertSame(1000,$s['reserved_minor']);$this->assertSame($f['source']->amount_minor-1000,$s['available_minor']);$this->assertSame($before,$row->fresh()->toJson());
    }

    public function test_paid_payout_and_refund_can_create_recovery_deficit_without_extra_credits(): void
    {
        $f=$this->completed();$wallet=app(SellerWalletService::class);$wallet->reconcileSellerOrder($f['so']);$p=$wallet->request($f['seller'],$f['source']->amount_minor-1000);$wallet->review($p,$f['admin'],'APPROVED');$wallet->markPaid($p,$f['admin'],'manual','Verified payout');$before=$p->fresh()->toJson();
        $r=$this->request($f);app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Verified');$d=-SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->sum('amount_minor');
        $this->assertSame(1000-$d,$wallet->summary($f['seller'])['available_minor']);$this->assertLessThan(0,$wallet->summary($f['seller'])['available_minor']);$this->assertSame($before,$p->fresh()->toJson());$this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
    }

    public function test_historical_source_changed_to_available_never_counts_source_and_release_twice(): void
    {
        $f=$this->completed();$wallet=app(SellerWalletService::class);$wallet->reconcileSellerOrder($f['so']);
        $f['source']->update(['status'=>'AVAILABLE']); // Synthetic old-writer compatibility scenario.
        $s=$wallet->summary($f['seller']);$this->assertSame($f['source']->amount_minor,$s['available_minor']);$this->assertSame($f['source']->amount_minor,$s['total_earnings_minor']);$this->assertSame(0,$s['held_minor']);$this->assertFalse($wallet->reconcileSellerOrder($f['so']));
    }

    public static function inconsistentRefund(): array{return [['quantity'],['accounting'],['status'],['refund-amount']];}
    #[DataProvider('inconsistentRefund')]
    public function test_inconsistent_approved_refund_history_keeps_original_earning_held(string $case): void
    {
        $f=$this->completed();$a=$this->request($f);$b=$this->request($f,$f['items']->last());
        app(ReturnRefundService::class)->decide($a,$f['admin'],'APPROVED','Verified');
        if($case==='quantity')$a->fresh()->update(['quantity'=>1]);
        elseif($case==='accounting')DB::table('return_refund_accounting')->where('return_refund_request_id',$a->id)->update(['merchandise_minor'=>1]);
        elseif($case==='status')DB::table('return_refund_accounting')->where('return_refund_request_id',$a->id)->update(['refund_status'=>'REFUND_PAID']);
        else $a->fresh()->update(['refund_minor'=>1]);
        app(ReturnRefundService::class)->decide($b,$f['admin'],'REJECTED','Not supported');
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->assertSame('HELD',$f['source']->fresh()->status);
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($f['so']));
    }

    public static function malformed(): array{return [['amount'],['seller'],['status'],['source-amount'],['cancelled'],['cod'],['legacy-adjustment']];}
    #[DataProvider('malformed')]
    public function test_malformed_history_is_not_released_or_credited(string $case): void
    {
        $f=$this->completed();$e=$f['source']->amount_minor;
        if(in_array($case,['amount','seller'],true)){
            $sellerId=$f['seller']->id;if($case==='seller')$sellerId=Seller::create(['user_id'=>User::factory()->create(['role'=>'seller'])->id,'name'=>'Foreign','slug'=>'foreign','status'=>'approved'])->id;
            SellerWalletEntry::create(['seller_id'=>$sellerId,'seller_order_id'=>$f['so']->id,'source_wallet_entry_id'=>$f['source']->id,'type'=>'EARNING_RELEASE','status'=>'POSTED','amount_minor'=>$case==='amount'?$e-1:$e]);
        }elseif($case==='status')$f['source']->update(['status'=>'POSTED']);
        elseif($case==='source-amount')$f['source']->update(['amount_minor'=>$e-1]);
        elseif($case==='cancelled')$f['order']->update(['status'=>'CANCELLED']);
        elseif($case==='cod')\App\Models\LogisticsCodRemittance::query()->update(['status'=>'SUBMITTED','accepted_minor'=>null]);
        else SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-1,'reference'=>'UNKNOWN']);
        $before=SellerWalletEntry::orderBy('id')->get()->toJson();$wallet=app(SellerWalletService::class);$this->assertFalse($wallet->reconcileSellerOrder($f['so']));$this->assertSame($before,SellerWalletEntry::orderBy('id')->get()->toJson());
        if(in_array($case,['amount','seller'],true)){$this->assertSame(0,$wallet->summary($f['seller'])['available_minor']);$this->assertSame($e,$wallet->summary($f['seller'])['held_minor']);}
    }

    public static function settlementPaths(): array { return [['release'],['completion']]; }
    #[DataProvider('settlementPaths')]
    public function test_another_shipments_settlement_cannot_fund_this_earning(string $path): void
    {
        $f=$this->completed(false);$liability=RiderCodRemittance::sole();
        $otherParent=$f['order']->replicate();$otherParent->order_number='UNRELATED';$otherParent->reference='UNRELATED';$otherParent->save();
        $otherOrder=$f['so']->replicate();$otherOrder->order_id=$otherParent->id;$otherOrder->save();
        $otherShipment=$f['shipment']->replicate();$otherShipment->seller_order_id=$otherOrder->id;$otherShipment->tracking_code='UNRELATED';$otherShipment->save();
        // A contradictory legacy receipt names this order but a different parcel.
        $foreignReceipt=$liability->replicate();$foreignReceipt->shipment_id=$otherShipment->id;
        $foreignReceipt->status='ACCEPTED';$foreignReceipt->submitted_minor=$foreignReceipt->accepted_minor=$liability->expected_minor;$foreignReceipt->save();
        $batch=\App\Models\LogisticsCodRemittance::create(['logistics_provider_id'=>$liability->logistics_provider_id,'status'=>'ACCEPTED',
            'expected_minor'=>$liability->expected_minor,'submitted_minor'=>$liability->expected_minor,'accepted_minor'=>$liability->expected_minor]);
        \App\Models\LogisticsCodRemittanceItem::create(['logistics_cod_remittance_id'=>$batch->id,'rider_cod_remittance_id'=>$foreignReceipt->id,'amount_minor'=>$liability->expected_minor]);
        $wallet=app(SellerWalletService::class);
        if($path==='completion'){$f['source']->delete();$wallet->creditCompleted($f['so']);}
        else $this->assertFalse($wallet->reconcileSellerOrder($f['so']));
        $this->assertSame('HELD',SellerWalletEntry::where('type','SALE_EARNING')->sole()->status);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->assertSame(0,$wallet->summary($f['seller'])['available_minor']);
    }

    public function test_release_source_unique_foreign_key_shape_and_rollback_are_enforced(): void
    {
        $f=$this->completed();app(SellerWalletService::class)->reconcileSellerOrder($f['so']);$release=SellerWalletEntry::where('type','EARNING_RELEASE')->sole();
        foreach(['duplicate','source-delete','wrong-shape'] as $case){
            try{DB::transaction(function()use($case,$release,$f){if($case==='duplicate')$release->replicate()->save();elseif($case==='source-delete')$f['source']->delete();else $release->update(['status'=>'AVAILABLE']);});$this->fail('Constraint missing');}
            catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        }
        $migration=require database_path('migrations/2026_11_06_000003_add_source_identity_to_earning_releases.php');
        try{$migration->down();$this->fail('Destructive rollback allowed');}catch(\RuntimeException $e){$this->assertStringContainsString('destructive rollback',$e->getMessage());}
    }

    public function test_release_shape_rejects_an_auto_increment_self_reference(): void
    {
        $f=$this->completed();
        $nextId=(int)DB::table('sqlite_sequence')->where('name','seller_wallet_entries')->value('seq')+1;
        $this->expectException(\Illuminate\Database\QueryException::class);
        SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'source_wallet_entry_id'=>$nextId,
            'type'=>'EARNING_RELEASE','status'=>'POSTED','amount_minor'=>$f['source']->amount_minor]);
    }

    public function test_release_migration_refuses_a_misnamed_refund_identity_index_without_altering_history(): void
    {
        $f=$this->completed();
        $migration=require database_path('migrations/2026_11_06_000003_add_source_identity_to_earning_releases.php');
        $migration->down();
        DB::statement('DROP INDEX seller_wallet_refund_request_unique');
        DB::statement('CREATE UNIQUE INDEX seller_wallet_refund_request_unique ON seller_wallet_entries (amount_minor)');
        $before=SellerWalletEntry::orderBy('id')->get()->toJson();
        try{$migration->up();$this->fail('An incompatible identity index was accepted');}
        catch(\RuntimeException $exception){$this->assertStringContainsString('Phase 2 wallet identity',$exception->getMessage());}
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('seller_wallet_entries','source_wallet_entry_id'));
        $this->assertSame($before,SellerWalletEntry::orderBy('id')->get()->toJson());
    }

    public function test_reports_show_release_movement_without_extra_sales_or_lifetime_earnings(): void
    {
        $f=$this->completed();app(SellerWalletService::class)->reconcileSellerOrder($f['so']);$data=app(MoneyReportService::class)->seller($f['seller']);
        $row=$data['rows']->first(fn($row)=>$row['entry']->type==='EARNING_RELEASE');$this->assertNotNull($row);$this->assertSame(0,$row['gross']);$this->assertSame(0,$row['commission']);$this->assertSame(0,$row['discount']);$this->assertSame($f['source']->amount_minor,$data['wallet']['total_earnings_minor']);
    }

    private function assertAdminMatchesWallet(array $f): array
    {
        $wallet=app(SellerWalletService::class)->summary($f['seller']);
        $data=app(MoneyReportService::class)->admin(['seller_id'=>$f['seller']->id]);
        foreach(['available','held','reserved'] as $field)$this->assertSame($wallet[$field.'_minor'],$data['seller_'.$field.'_minor']);
        $this->assertSame($wallet['total_earnings_minor'],$data['seller_lifetime_earnings_minor']);
        return $data;
    }

    public function test_admin_and_seller_balances_agree_through_release_refund_reservation_and_payout(): void
    {
        $f=$this->completed();$wallet=app(SellerWalletService::class);
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_held_minor']);
        $this->assertTrue($wallet->reconcileSellerOrder($f['so']));
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_available_minor']);
        $paid=$wallet->request($f['seller'],1000);$wallet->review($paid,$f['admin'],'APPROVED');$wallet->markPaid($paid,$f['admin'],'manual','Isolated');
        $reserved=$wallet->request($f['seller'],500);
        $request=$this->request($f);app(ReturnRefundService::class)->decide($request,$f['admin'],'APPROVED','Verified');
        $data=$this->assertAdminMatchesWallet($f);
        $this->assertSame(16502,$data['seller_available_minor']);$this->assertSame(17002,$data['seller_payable_minor']);
        $this->assertSame(1000,$data['seller_paid_minor']);$this->assertSame(500,$data['seller_reserved_minor']);
        $this->assertSame(-18002,$data['refund_adjustments_minor']);$this->assertSame('PENDING',$reserved->fresh()->status);
        $this->assertSame('REFUND_DUE',DB::table('return_refund_accounting')->sole()->refund_status);
        $before=SellerWalletEntry::orderBy('id')->get()->toJson();
        $response=$this->actingAs($f['admin'])->get(route('admin.financial-report',['seller_id'=>$f['seller']->id,'from'=>'2000-01-01','to'=>'2000-01-02']));
        $response->assertOk()->assertSee('Current Seller wallet balances')->assertSee('Verified Seller payouts');
        $this->assertSame(16502,$response->viewData('data')['seller_available_minor']);
        $this->assertSame($before,SellerWalletEntry::orderBy('id')->get()->toJson());
        $this->actingAs($f['buyer'])->get(route('admin.financial-report'))->assertForbidden();
    }

    public function test_admin_report_ignores_malformed_releases_and_does_not_double_count_available_source(): void
    {
        $f=$this->completed();$source=$f['source'];
        $release=SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'source_wallet_entry_id'=>$source->id,
            'type'=>'EARNING_RELEASE','status'=>'POSTED','amount_minor'=>1]);
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_held_minor']);
        $this->assertSame(0,$this->assertAdminMatchesWallet($f)['seller_available_minor']);
        $release->update(['amount_minor'=>$source->amount_minor]);
        $source->update(['status'=>'AVAILABLE']);
        $data=$this->assertAdminMatchesWallet($f);$this->assertSame(36004,$data['seller_available_minor']);$this->assertSame(0,$data['seller_held_minor']);
    }

    private function undoFinancialMigrations(): void
    {
        foreach(['000003_add_source_identity_to_earning_releases','000002_scope_refund_wallet_entries_to_requests','000001_enforce_one_return_request_per_item'] as $migration){
            $file=require database_path('migrations/2026_11_06_'.$migration.'.php');$file->down();
        }
    }

    public function test_cod_and_complaint_can_finish_before_migrations_but_retained_earning_has_explicit_retry_outcome(): void
    {
        $f=$this->completed(false);$complaint=$this->complaint($f);$before=$f['source']->fresh()->toJson();$this->undoFinancialMigrations();
        $wallet=app(SellerWalletService::class);
        $this->assertSame('SCHEMA_UNAVAILABLE',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        $this->settle($f,true);
        app(\App\Services\Admin\ComplaintService::class)->dismiss($complaint,$f['admin'],'Resolved');
        $this->assertSame('DISMISSED',$complaint->fresh()->status->value);
        $this->assertSame('ACCEPTED',\App\Models\LogisticsCodRemittance::sole()->status);
        $this->assertEquals(json_decode($before,true),$f['source']->fresh()->toArray()+['return_refund_request_id'=>null,'source_wallet_entry_id'=>null]);
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_held_minor']);
        $this->actingAs($f['seller']->owner)->get(route('seller.wallet'))->assertOk()->assertSee('financial schema review and migration');
        foreach(['000001_enforce_one_return_request_per_item','000002_scope_refund_wallet_entries_to_requests','000003_add_source_identity_to_earning_releases'] as $migration){
            $file=require database_path('migrations/2026_11_06_'.$migration.'.php');$file->up();
        }
        config(['returns.earning_release_enabled'=>false]);
        $this->assertSame('ACTIVATION_DISABLED',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        config(['returns.earning_release_enabled'=>true]);
        $this->assertSame('RELEASED',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        $this->assertSame('NO_RELEASE',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        $this->assertSame($before,$f['source']->fresh()->toJson());$this->assertSame(1,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_available_minor']);
    }

    public static function unavailableSchemas(): array { return [['request-only'],['no-source'],['source-only'],['wrong-index'],['wrong-predicate'],['missing-fk'],['missing-trigger']]; }
    #[DataProvider('unavailableSchemas')]
    public function test_incomplete_financial_schema_never_activates_releases_or_refund_processing(string $case): void
    {
        $f=$this->completed();$source=$f['source']->fresh()->toJson();
        if(in_array($case,['request-only','no-source','source-only'],true)){
            $file=require database_path('migrations/2026_11_06_000003_add_source_identity_to_earning_releases.php');$file->down();
            if(in_array($case,['request-only','source-only'],true)){$file=require database_path('migrations/2026_11_06_000002_scope_refund_wallet_entries_to_requests.php');$file->down();}
            if($case==='source-only')\Illuminate\Support\Facades\Schema::table('seller_wallet_entries',fn($t)=>$t->unsignedBigInteger('source_wallet_entry_id')->nullable());
        }elseif($case==='wrong-index'){
            DB::statement('DROP INDEX seller_wallet_release_source_unique');DB::statement('CREATE UNIQUE INDEX seller_wallet_release_source_unique ON seller_wallet_entries (amount_minor)');
        }elseif($case==='wrong-predicate'){
            DB::statement('DROP INDEX seller_wallet_legacy_identity_unique');DB::statement('CREATE UNIQUE INDEX seller_wallet_legacy_identity_unique ON seller_wallet_entries (seller_id,seller_order_id,type) WHERE return_refund_request_id IS NOT NULL');
        }elseif($case==='missing-fk')\Illuminate\Support\Facades\Schema::table('seller_wallet_entries',fn($t)=>$t->dropForeign(['source_wallet_entry_id']));
        else DB::statement('DROP TRIGGER seller_wallet_release_shape_insert');
        $wallet=app(SellerWalletService::class);
        $this->assertSame('SCHEMA_UNAVAILABLE',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_held_minor']);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        try{$this->request($f);$this->fail('Inconsistent schema accepted');}catch(ValidationException $e){$this->assertArrayHasKey('request',$e->errors());}
        // Ignore newly added nullable identity columns in this history comparison.
        foreach(json_decode($source,true) as $key=>$value)$this->assertEquals($value,$f['source']->fresh()->toArray()[$key] ?? null);
    }

    public static function releaseFlags(): array { return [[false],[null],['true'],['false'],[1]]; }
    #[DataProvider('releaseFlags')]
    public function test_release_gate_is_separate_and_requires_boolean_activation(mixed $flag): void
    {
        $f=$this->completed();config(['returns.backend_enabled'=>false,'returns.earning_release_enabled'=>$flag]);
        $wallet=app(SellerWalletService::class);$this->assertSame('ACTIVATION_DISABLED',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        config(['returns.earning_release_enabled'=>true]);
        $this->assertSame('RELEASED',$wallet->reconcileSellerOrderResult($f['so'])['status']);
        try{$this->request($f);$this->fail('Refund gate bypassed');}catch(ValidationException $e){$this->assertArrayHasKey('request',$e->errors());}
    }

    public function test_disabled_release_gate_applies_to_cod_and_complaint_closure_without_false_release_notification(): void
    {
        $defaults=require config_path('returns.php');$this->assertFalse($defaults['backend_enabled']);$this->assertFalse($defaults['earning_release_enabled']);
        $f=$this->completed(false);$complaint=$this->complaint($f);
        config(['returns.backend_enabled'=>false,'returns.earning_release_enabled'=>false]);
        $this->settle($f,true);app(\App\Services\Admin\ComplaintService::class)->dismiss($complaint,$f['admin'],'Resolved');
        $this->assertSame('ACCEPTED',\App\Models\LogisticsCodRemittance::sole()->status);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
        $this->assertSame(0,DB::table('notification_outbox')->where('title','COD reconciliation complete')->count());
        $this->assertSame(36004,$this->assertAdminMatchesWallet($f)['seller_held_minor']);
        config(['returns.earning_release_enabled'=>true]);
        $this->assertSame('RELEASED',app(SellerWalletService::class)->reconcileSellerOrderResult($f['so'])['status']);
    }
}
