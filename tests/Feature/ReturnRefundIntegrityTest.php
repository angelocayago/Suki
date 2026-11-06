<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{CartItem, Category, DeliveryEvent, LogisticsProvider, Order, OrderItem, Product, ProductVariant, ReturnRefundEvidence, ReturnRefundRequest, Rider, Seller, SellerWalletEntry, Shipment, User, Voucher};
use App\Services\{CheckoutService, CodRemittanceService, ReturnRefundAllocationService, ReturnRefundService, SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Schema, Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class ReturnRefundIntegrityTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Only isolated SQLite memory is allowed.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());$this->assertSame(':memory:',DB::connection()->getDatabaseName());$this->assertStoragePathIsIsolated();
        Mail::fake();Notification::fake();Http::preventStrayRequests();Storage::fake('local');Storage::fake('public');
        config(['returns.backend_enabled'=>true]);
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

    private function submit(array $f, ?OrderItem $item=null): ReturnRefundRequest
    {
        $item??=$f['items']->first();return app(ReturnRefundService::class)->submit($f['buyer'],$item,['type'=>'REFUND','quantity'=>$item->quantity,'reason'=>'Damaged','description'=>'Purchase arrived damaged.']);
    }

    private function reject(callable $call, string $field): void
    {
        try{$call();$this->fail('Unsafe operation succeeded');}catch(ValidationException $e){$this->assertArrayHasKey($field,$e->errors());}
    }

    public function test_default_gate_disables_new_submission_and_approval_without_new_ui(): void
    {
        $this->assertFalse((require config_path('returns.php'))['backend_enabled']);$f=$this->fixture();$r=$this->submit($f);
        config(['returns.backend_enabled'=>false]);$this->reject(fn()=>$this->submit($f,$f['items']->last()),'request');
        $this->reject(fn()=>app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed'),'request');
        $this->assertDatabaseCount('seller_wallet_entries',0);
    }

    public static function ambiguousFlags(): array
    {
        return [[null],[false],['false'],['true'],['1'],[1],[[]]];
    }

    #[DataProvider('ambiguousFlags')]
    public function test_only_explicit_boolean_true_enables_financial_processing(mixed $flag): void
    {
        $f=$this->fixture();$r=$this->submit($f);
        $before=[];
        foreach(['return_refund_requests','return_refund_events','return_refund_accounting','seller_wallet_entries','notification_outbox'] as $table) {
            $before[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        }
        config(['returns.backend_enabled'=>$flag]);
        $this->reject(fn()=>$this->submit($f,$f['items']->last()),'request');
        $this->reject(fn()=>app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed'),'request');
        foreach($before as $table=>$snapshot)$this->assertSame($snapshot,DB::table($table)->orderBy('id')->get()->toJson());
    }

    public function test_two_item_refunds_share_one_seller_order_and_are_idempotent_without_changing_snapshots(): void
    {
        $f=$this->fixture(true);$snapshots=[];foreach(['orders','seller_orders','shipments','order_items','vouchers','voucher_redemptions','rider_cod_remittances','product_variants'] as $table)$snapshots[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        $requests=$f['items']->map(fn($item)=>$this->submit($f,$item));
        foreach($requests as $r){
            $result=app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed evidence');
            $expected=app(ReturnRefundAllocationService::class)->calculate($r->item,$r->quantity);
            $this->assertSame($expected['buyer_refund_due_minor'],$result->refund_minor);
            $entry=SellerWalletEntry::where('return_refund_request_id',$r->id)->sole();$this->assertSame(-$expected['seller_adjustment_minor'],$entry->amount_minor);
            $this->assertSame($entry->id,$r->walletAdjustment->id);$this->assertSame($r->id,$entry->refundRequest->id);
            app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Repeated');
            $this->reject(fn()=>app(ReturnRefundService::class)->decide($r,$f['admin'],'REJECTED','Conflict'),'decision');
        }
        $this->assertDatabaseCount('seller_wallet_entries',2);$this->assertDatabaseCount('return_refund_accounting',2);
        $this->assertSame(2,DB::table('return_refund_events')->where('action','ADMIN_APPROVED')->count());
        $this->assertSame(['REFUND_DUE'],DB::table('return_refund_accounting')->distinct()->pluck('refund_status')->all());
        foreach($snapshots as $table=>$json)$this->assertSame($json,DB::table($table)->orderBy('id')->get()->toJson());
    }

    public static function statuses(): array{return [['SUBMITTED'],['SELLER_RESPONDED'],['UNDER_REVIEW'],['ADMIN_APPROVED'],['ADMIN_REJECTED']];}
    #[DataProvider('statuses')]
    public function test_any_existing_request_blocks_lifetime_resubmission(string $status): void
    {
        $f=$this->fixture();$r=$this->submit($f);$r->update(['status'=>$status]);$this->reject(fn()=>$this->submit($f),'item');$this->assertDatabaseCount('return_refund_requests',1);
    }

    public static function invalidCases(): array{return array_map(fn($x)=>[$x],['uncollected','amount','timestamp','event','liability','cancelled-parent','cancelled-child','workflow','variant','owner-link','discount','negative','total','commission','parent-payment','cod-rejected']);}
    #[DataProvider('invalidCases')]
    public function test_inconsistent_purchase_or_uncollected_cash_is_rejected(string $case): void
    {
        $f=$this->fixture();$item=$f['items']->first();
        switch($case){
            case 'uncollected':$f['shipment']->update(['cod_collected'=>false]);break;
            case 'amount':$f['shipment']->update(['cod_collected_minor'=>1]);break;
            case 'timestamp':$f['shipment']->update(['cod_collected_at'=>null]);break;
            case 'event':DeliveryEvent::query()->delete();break;
            case 'liability':DB::table('rider_cod_remittances')->delete();break;
            case 'cancelled-parent':$f['order']->update(['status'=>'CANCELLED']);break;
            case 'cancelled-child':$f['so']->update(['status'=>'cancelled']);break;
            case 'workflow':$f['so']->update(['workflow_status'=>'PREPARING']);break;
            case 'variant':$item->update(['product_variant_id'=>$f['variants']->last()->id]);break;
            case 'owner-link':$item->update(['seller_id'=>$f['buyer']->id]);break;
            case 'discount':$item->update(['seller_funded_discount_minor'=>1]);break;
            case 'negative':DB::table('order_items')->where('id',$item->id)->update(['unit_price_minor'=>-1]);break;
            case 'total':$f['so']->update(['subtotal_minor'=>1]);break;
            case 'commission':$item->update(['commission_minor'=>999999]);break;
            case 'parent-payment':$f['order']->update(['payment_status'=>'UNPAID']);break;
            case 'cod-rejected':DB::table('rider_cod_remittances')->update(['status'=>'REJECTED']);break;
        }
        try{$this->submit($f,$item);$this->fail('Unsafe purchase accepted');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
        $this->assertDatabaseCount('return_refund_requests',0);$this->assertSame(8,$f['variants']->first()->fresh()->stock);
    }

    public function test_full_quantity_wrong_buyer_and_wrong_seller_are_validated_server_side(): void
    {
        $f=$this->fixture();$item=$f['items']->first();
        $this->actingAs($f['buyer'])->postJson(route('buyer.return-refunds.store',$item),['type'=>'REFUND','quantity'=>1,'reason'=>'Damage','description'=>'Detail'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->postJson(route('buyer.return-refunds.store',$item),['type'=>'REFUND','quantity'=>2,'reason'=>'Damage','description'=>'Detail'])->assertForbidden();
        $r=$this->submit($f);$foreign=User::factory()->create(['role'=>'seller','status'=>'active']);
        $this->actingAs($foreign)->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'APPROVE','note'=>'Forged'])->assertForbidden();
    }

    public function test_relevant_collected_parcel_does_not_require_other_seller_parcel_to_be_paid(): void
    {
        $f=$this->fixture(otherParcel:true);$r=$this->submit($f);$result=app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        $this->assertSame('ADMIN_APPROVED',$result->status);$this->assertSame('UNPAID',$f['order']->fresh()->payment_status);
    }

    public function test_active_requests_pause_payout_creation_and_payment_without_rewriting_paid_records(): void
    {
        $f=$this->fixture();$wallet=app(SellerWalletService::class);
        SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>36004]);
        $payout=$wallet->request($f['seller'],1000);$wallet->review($payout,$f['admin'],'APPROVED');
        $r=$this->submit($f);$snapshot=$payout->fresh()->toJson();
        $this->reject(fn()=>$wallet->request($f['seller'],100),'payout');$this->reject(fn()=>$wallet->markPaid($payout,$f['admin'],'manual','Test'),'payout');$this->assertSame($snapshot,$payout->fresh()->toJson());
        app(ReturnRefundService::class)->decide($r,$f['admin'],'REJECTED','Not supported');
        $wallet->markPaid($payout,$f['admin'],'manual','Test');$paid=$payout->fresh()->toJson();
        $this->reject(fn()=>$wallet->markPaid($payout,$f['admin'],'manual','Replay'),'payout');$this->assertSame($paid,$payout->fresh()->toJson());
    }

    public function test_held_delivered_earning_can_record_refund_due_without_premature_release_or_rewrite(): void
    {
        $f=$this->fixture();$r=$this->submit($f);$entry=SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'SALE_EARNING','status'=>'HELD','amount_minor'=>36004]);$snapshot=$entry->fresh()->toJson();
        $this->assertFalse(app(SellerWalletService::class)->reconcileSellerOrder($f['so']));
        $result=app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        $this->assertSame('ADMIN_APPROVED',$result->status);
        $this->assertSame($snapshot,$entry->fresh()->toJson());$this->assertDatabaseCount('seller_wallet_entries',2);$this->assertDatabaseCount('return_refund_accounting',1);
        $this->assertSame(0,SellerWalletEntry::where('type','EARNING_RELEASE')->count());
    }

    public function test_approval_rechecks_evidence_and_rolls_back_all_financial_effects(): void
    {
        $f=$this->fixture();$r=$this->submit($f);$f['shipment']->update(['cod_collected_minor'=>1]);
        $this->reject(fn()=>app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed'),'accounting');
        $this->assertSame('SUBMITTED',$r->fresh()->status);$this->assertDatabaseCount('return_refund_accounting',0);$this->assertDatabaseCount('seller_wallet_entries',0);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('evidence.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='));
    }

    public function test_private_evidence_final_state_and_failed_insert_cleanup(): void
    {
        $f=$this->fixture();$r=$this->submit($f);
        $this->actingAs($f['buyer'])->postJson(route('return-refunds.evidence.store',$r),['file'=>$this->photo()])->assertCreated();
        $evidence=ReturnRefundEvidence::sole();$this->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'buyer','status'=>'active']))->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertForbidden();
        $this->actingAs($f['buyer']);$before=Storage::disk('local')->allFiles();
        ReturnRefundEvidence::creating(function(){throw new \RuntimeException('Isolated insertion failure');});
        try{$this->withoutExceptionHandling()->postJson(route('return-refunds.evidence.store',$r),['file'=>$this->photo()]);$this->fail('Expected insertion failure');}
        catch(\RuntimeException $e){$this->assertSame('Isolated insertion failure',$e->getMessage());}
        finally{ReturnRefundEvidence::flushEventListeners();$this->withExceptionHandling();}
        $this->assertSame($before,Storage::disk('local')->allFiles());$this->assertDatabaseCount('return_refund_evidence',1);
        app(ReturnRefundService::class)->decide($r,$f['admin'],'REJECTED','Reviewed');
        $this->postJson(route('return-refunds.evidence.store',$r),['file'=>$this->photo()])->assertUnprocessable();$this->assertSame($before,Storage::disk('local')->allFiles());
    }

    public function test_request_unique_index_rejects_duplicate_and_migration_preflight_preserves_history(): void
    {
        $f=$this->fixture();$r=$this->submit($f);
        Schema::table('return_refund_requests',fn($t)=>$t->dropUnique('return_refund_one_item_unique'));
        $copy=$r->replicate();$copy->status='ADMIN_REJECTED';$copy->save();$before=ReturnRefundRequest::orderBy('id')->get()->toJson();
        $migration=require database_path('migrations/2026_11_06_000001_enforce_one_return_request_per_item.php');
        try{$migration->up();$this->fail('Duplicated historical records were accepted');}catch(\RuntimeException $e){$this->assertStringContainsString('duplicated',$e->getMessage());}
        $this->assertSame($before,ReturnRefundRequest::orderBy('id')->get()->toJson());
    }

    public function test_wallet_migration_preserves_legacy_rows_and_rollback_refuses_new_financial_entries(): void
    {
        $f=$this->fixture();$releaseMigration=require database_path('migrations/2026_11_06_000003_add_source_identity_to_earning_releases.php');$releaseMigration->down();
        $migration=require database_path('migrations/2026_11_06_000002_scope_refund_wallet_entries_to_requests.php');$migration->down();
        $entry=SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>36004]);$attributes=$entry->getAttributes();
        $migration->up();$releaseMigration->up();$this->assertEquals($attributes,array_intersect_key($entry->fresh()->getAttributes(),$attributes));
        $r=$this->submit($f);app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        try{$migration->down();$this->fail('Destructive rollback succeeded');}catch(\RuntimeException $e){$this->assertStringContainsString('destructive rollback',$e->getMessage());}
        $this->assertTrue(Schema::hasColumn('seller_wallet_entries','return_refund_request_id'));
    }

    public function test_database_constraints_enforce_lifetime_and_request_scoped_identities(): void
    {
        $f=$this->fixture();$r=$this->submit($f);
        try{DB::transaction(function()use($r){$copy=$r->replicate();$copy->status='ADMIN_REJECTED';$copy->save();});$this->fail('Lifetime constraint missing');}
        catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('UNIQUE',$e->getMessage());}
        app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        $entry=SellerWalletEntry::where('return_refund_request_id',$r->id)->sole();
        try{DB::transaction(fn()=>$entry->replicate()->save());$this->fail('Request identity constraint missing');}
        catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('UNIQUE',$e->getMessage());}
        $this->assertDatabaseCount('return_refund_requests',1);$this->assertDatabaseCount('seller_wallet_entries',1);
    }

    public function test_completed_soft_deleted_variant_and_legacy_adjustment_keep_their_original_identity(): void
    {
        $f=$this->fixture();$f['order']->update(['status'=>'COMPLETED']);$f['so']->update(['workflow_status'=>'COMPLETED','status'=>'completed']);$f['shipment']->update(['workflow_status'=>'COMPLETED']);
        $f['variants']->first()->delete();
        $legacy=SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'REFUND_ADJUSTMENT','status'=>'AVAILABLE','amount_minor'=>-100,'reference'=>'LEGACY-UNTOUCHED']);$before=$legacy->fresh()->toJson();
        $r=$this->submit($f);$result=app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        $this->assertSame('ADMIN_APPROVED',$result->status);$this->assertSame($before,$legacy->fresh()->toJson());$this->assertDatabaseCount('seller_wallet_entries',2);
    }

    public function test_decision_event_failure_rolls_back_accounting_ledger_status_and_notification_intents(): void
    {
        $f=$this->fixture();$r=$this->submit($f);$before=DB::table('notification_outbox')->count();
        \App\Models\ReturnRefundEvent::creating(function($event){if($event->action==='ADMIN_APPROVED')throw new \RuntimeException('Isolated decision failure');});
        try{app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');$this->fail('Expected failure');}
        catch(\RuntimeException $e){$this->assertSame('Isolated decision failure',$e->getMessage());}
        finally{\App\Models\ReturnRefundEvent::flushEventListeners();}
        $this->assertDatabaseCount('return_refund_accounting',0);$this->assertDatabaseCount('seller_wallet_entries',0);$this->assertSame('SUBMITTED',$r->fresh()->status);$this->assertSame($before,DB::table('notification_outbox')->count());
    }

    public function test_missing_migration_keeps_processing_disabled_even_if_config_is_enabled(): void
    {
        $f=$this->fixture();Schema::table('return_refund_requests',fn($t)=>$t->dropUnique('return_refund_one_item_unique'));
        $this->reject(fn()=>$this->submit($f),'request');$this->assertDatabaseCount('return_refund_requests',0);
    }

    public function test_full_centavo_allocations_do_not_round_away_voucher_funding(): void
    {
        $item=new OrderItem(['seller_order_id'=>999,'quantity'=>2,'unit_price_minor'=>1,'commission_minor'=>0,'seller_funded_discount_minor'=>1,'platform_funded_discount_minor'=>1]);
        $result=app(ReturnRefundAllocationService::class)->calculate($item,2);$this->assertSame(0,$result['buyer_refund_due_minor']);$this->assertSame(1,$result['seller_funded_discount_minor']);$this->assertSame(1,$result['platform_funded_discount_minor']);
        $this->reject(fn()=>app(ReturnRefundAllocationService::class)->calculate($item,1),'quantity');
    }

    public function test_contradictory_upstream_cod_settlement_is_rejected(): void
    {
        $f=$this->fixture();$liability=\App\Models\RiderCodRemittance::sole();$amount=$liability->expected_minor;
        $liability->update(['status'=>'ACCEPTED','submitted_minor'=>$amount,'accepted_minor'=>$amount]);
        $remittance=\App\Models\LogisticsCodRemittance::create(['logistics_provider_id'=>$f['shipment']->logistics_provider_id,'status'=>'REJECTED','expected_minor'=>$amount,'submitted_minor'=>$amount]);
        \App\Models\LogisticsCodRemittanceItem::create(['logistics_cod_remittance_id'=>$remittance->id,'rider_cod_remittance_id'=>$liability->id,'amount_minor'=>$amount]);
        $this->reject(fn()=>$this->submit($f),'payment');$this->assertDatabaseCount('return_refund_requests',0);
    }

    public function test_refund_adjustment_prevents_an_underfunded_previously_reserved_payout_from_being_paid(): void
    {
        $f=$this->fixture();$wallet=app(SellerWalletService::class);
        SellerWalletEntry::create(['seller_id'=>$f['seller']->id,'seller_order_id'=>$f['so']->id,'type'=>'SALE_EARNING','status'=>'AVAILABLE','amount_minor'=>36004]);
        $payout=$wallet->request($f['seller'],35000);$wallet->review($payout,$f['admin'],'APPROVED');
        $r=$this->submit($f);app(ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed');
        $this->reject(fn()=>$wallet->markPaid($payout,$f['admin'],'manual','Unfunded'),'payout');
        $this->assertSame('APPROVED',$payout->fresh()->status);$this->assertSame(0,SellerWalletEntry::where('type','PAYOUT_COMPLETED')->count());
    }
}
