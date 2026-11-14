<?php

namespace Tests\Feature;

use App\Models\{ReturnRefundRequest,ReturnRefundEvidence,Seller,SellerWalletEntry,User};
use App\Services\{ReturnRefundService,ReturnRefundWorkflowService,SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class SellerReviewReturnRefundTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();
        Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }

    private function purchase(bool $discounted=false): array
    {
        // Reuse the existing synthetic, verified COD purchase fixture, not live data.
        return (new DirectAdminReturnRefundTest('test_direct_approval_is_private_idempotent_and_records_one_wallet_adjustment_without_payment_or_restock'))->purchase($discounted);
    }

    private function data(): array { return ['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged item','description'=>'Received a damaged purchased item.','confirmed'=>1]; }

    private function submit(array $f,bool $proof=true): ReturnRefundRequest
    {
        $data=$this->data()+['workflow_mode'=>'ADMIN_REVIEW','seller_id'=>999999];
        if($proof)$data['file']=UploadedFile::fake()->image('proof.png');
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.store',$f['item']),$data)->assertSessionHasNoErrors()->assertRedirect();
        return ReturnRefundRequest::sole();
    }

    private function decide(array $f,ReturnRefundRequest $r,string $choice='APPROVED')
    {
        return $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.decide',$r),['decision'=>$choice,'reason'=>'Purchased item and proof reviewed.']);
    }

    private function protectedSnapshot(): array
    {
        $out=[];foreach(['orders','order_items','seller_orders','shipments','product_variants','payouts','payments','rider_cod_remittances','logistics_cod_remittances','logistics_cod_remittance_items','voucher_redemptions']as$table)$out[$table]=DB::table($table)->orderBy('id')->get()->toJson();return $out;
    }

    public function test_submission_proof_ownership_seller_approval_wallet_status_chat_and_replay(): void
    {
        $f=$this->purchase(true);$before=$this->protectedSnapshot();$r=$this->submit($f);
        $this->assertSame('SELLER_REVIEW',$r->workflow_mode);$this->assertSame($f['seller']->id,(int)$r->seller_id);
        $this->assertSame('Pending Seller Review',$r->statusLabel());$this->assertSame(19698,$r->refund_minor);
        $this->assertDatabaseCount('return_refund_workflows',0);$this->assertDatabaseCount('seller_wallet_entries',0);$this->assertDatabaseCount('return_refund_accounting',0);
        $this->assertFalse(DB::table('notification_outbox')->where('user_id',$f['admin']->id)->exists());
        $this->assertTrue(DB::table('notification_outbox')->where('user_id',$f['sellerUser']->id)->where('title','Return/refund request')->exists());
        $this->actingAs($f['sellerUser'])->get(route('seller.return-refunds.index',['search'=>'Purchased','status'=>'SUBMITTED']))->assertOk()->assertSee('Pending Seller Review')->assertSee('1 proof image(s)');
        $this->get(route('seller.return-refunds.show',$r))->assertOk()->assertSee('Purchased option')->assertSee('Received a damaged purchased item.')->assertSee('Finalize Seller decision');
        $evidence=ReturnRefundEvidence::sole();$this->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertOk()->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('Content-Type','image/png');
        $foreign=User::factory()->create(['role'=>'seller','status'=>'active']);Seller::create(['user_id'=>$foreign->id,'name'=>'Other store','slug'=>'foreign-store','status'=>'approved']);
        $this->actingAs($foreign)->get(route('seller.return-refunds.show',$r))->assertNotFound();
        $this->patchJson(route('seller.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Unauthorized'])->assertNotFound();
        $this->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertForbidden();
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('Admin monitoring is read-only')->assertDontSee('Finalize Seller decision')->assertDontSee('Finalize decision');
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Admin bypass'])->assertForbidden();
        $this->actingAs($f['buyer'])->patchJson(route('seller.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Buyer bypass'])->assertForbidden();
        $this->decide($f,$r)->assertOk();$intents=DB::table('notification_outbox')->count();
        $this->decide($f,$r)->assertOk();$this->decide($f,$r,'REJECTED')->assertUnprocessable();
        $r->refresh();$this->assertSame('Approved by Seller',$r->statusLabel());$this->assertNull($r->admin_decision);$this->assertNull($r->admin_reason);$this->assertSame($f['sellerUser']->id,(int)$r->reviewed_by);
        $this->assertSame(['SUBMITTED','SELLER_APPROVED'],$r->events()->orderBy('id')->pluck('action')->all());$this->assertSame($intents,DB::table('notification_outbox')->count());
        $entry=SellerWalletEntry::sole();$this->assertSame($r->id,(int)$entry->return_refund_request_id);$this->assertSame($f['seller']->id,(int)$entry->seller_id);$this->assertSame($f['so']->id,(int)$entry->seller_order_id);$this->assertSame(-17899,$entry->amount_minor);
        $this->assertSame($f['item']->id,(int)$entry->refundRequest->order_item_id);$this->assertSame(-17899,app(SellerWalletService::class)->summary($f['seller'])['available_minor']);
        $this->assertDatabaseHas('return_refund_accounting',['return_refund_request_id'=>$r->id,'refund_status'=>'REFUND_DUE','seller_adjustment_minor'=>17899]);
        $this->actingAs($f['sellerUser'])->get(route('seller.wallet'))->assertOk()->assertSee('Approved refund adjustment')->assertSee('Request RR-'.$r->id)->assertSee('recorded deficit');
        $page=$this->get(route('seller.return-refunds.index',['status'=>'SELLER_APPROVED']));$page->assertOk()->assertSee('Approved by Seller');$this->assertSame(1,$page->viewData('summary')['Approved by Seller']);$this->assertSame(0,$page->viewData('summary')['Approved by Admin']);
        foreach(['buyer'=>$f['buyer'],'seller'=>$f['sellerUser']]as$role=>$actor){
            $this->actingAs($actor)->get(route($role.'.return-refunds.show',$r))->assertOk()->assertSee('Approved by Seller')->assertSee('Purchased item and proof reviewed.');
            $this->post(route($role.'.messages.context.seller-order.store',$f['so']))->assertRedirect();
        }
        $this->assertSame($before,$this->protectedSnapshot());
    }

    public function test_optional_proof_rejection_reason_and_no_wallet_debit(): void
    {
        $f=$this->purchase();$r=$this->submit($f,false);
        $this->assertDatabaseCount('return_refund_evidence',0);
        $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>' '])->assertUnprocessable();
        $this->decide($f,$r,'REJECTED')->assertOk();$this->decide($f,$r,'REJECTED')->assertOk();$this->decide($f,$r)->assertUnprocessable();
        $this->assertSame('Rejected by Seller',$r->fresh()->statusLabel());$this->assertSame(['SUBMITTED','SELLER_REJECTED'],$r->events()->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseCount('seller_wallet_entries',0);$this->assertDatabaseCount('return_refund_accounting',0);
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Rejected by Seller')->assertSee('Purchased item and proof reviewed.');
    }

    public function test_submission_validation_ownership_optional_proof_and_duplicate_protection(): void
    {
        $f=$this->purchase();$foreign=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($foreign)->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertForbidden();
        $this->actingAs($f['buyer']);
        foreach([['quantity'=>1],['description'=>''],['confirmed'=>0],['file'=>UploadedFile::fake()->create('bad.txt',2,'text/plain')]]as$invalid){
            $this->postJson(route('buyer.return-refunds.store',$f['item']),array_replace($this->data(),$invalid))->assertUnprocessable();
            $this->assertDatabaseCount('return_refund_requests',0);
        }
        $r=$this->submit($f,false);
        $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        $this->assertDatabaseCount('return_refund_requests',1);$this->assertSame('SUBMITTED',$r->fresh()->status);$this->assertDatabaseCount('seller_wallet_entries',0);
    }

    public function test_disabled_gate_and_invalid_purchase_cannot_debit_but_rejection_remains_possible(): void
    {
        $f=$this->purchase();$r=$this->submit($f,false);
        config(['returns.admin_review_enabled'=>false,'returns.backend_enabled'=>true]);
        $this->decide($f,$r)->assertUnprocessable();$this->assertDatabaseCount('seller_wallet_entries',0);
        config(['returns.admin_review_enabled'=>true,'returns.backend_enabled'=>false]);
        $f['so']->update(['commission_minor'=>$f['so']->commission_minor+1]);
        $this->decide($f,$r)->assertUnprocessable();$this->assertDatabaseCount('seller_wallet_entries',0);$this->assertDatabaseCount('return_refund_accounting',0);
        $this->decide($f,$r,'REJECTED')->assertOk();$this->assertSame('Rejected by Seller',$r->fresh()->statusLabel());
    }

    public function test_existing_modes_keep_their_decision_owners(): void
    {
        $f=$this->purchase();$r=app(ReturnRefundService::class)->submitForAdmin($f['buyer'],$f['item'],$this->data());
        $this->decide($f,$r)->assertForbidden();
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Historical Admin review'])->assertOk();
        $this->assertTrue($r->fresh()->isAdminReview());$this->assertSame($f['admin']->id,(int)$r->fresh()->reviewed_by);
        $this->assertSame(['SUBMITTED','ADMIN_APPROVED'],$r->events()->orderBy('id')->pluck('action')->all());
    }

    public function test_intermediate_event_failure_rolls_back_approval_ledger_and_notifications(): void
    {
        $f=$this->purchase();$r=$this->submit($f);$before=$this->protectedSnapshot();$intents=DB::table('notification_outbox')->count();
        DB::unprepared("CREATE TRIGGER seller_event_failure BEFORE INSERT ON return_refund_events WHEN NEW.action = 'SELLER_APPROVED' BEGIN SELECT RAISE(ABORT, 'Synthetic Seller event failure'); END");
        try{app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Reviewed');$this->fail('Failure did not roll back');}catch(\Illuminate\Database\QueryException $e){$this->assertStringContainsString('Synthetic Seller event failure',$e->getMessage());}
        $this->assertSame('SUBMITTED',$r->fresh()->status);$this->assertDatabaseCount('seller_wallet_entries',0);$this->assertDatabaseCount('return_refund_accounting',0);$this->assertSame(1,$r->events()->count());$this->assertSame($intents,DB::table('notification_outbox')->count());$this->assertSame($before,$this->protectedSnapshot());
    }

    public static function gates(): array { return [[false,false],[true,false],[false,true],[true,true]]; }
    #[DataProvider('gates')]
    public function test_seller_activation_is_independent_of_financial_gate(bool $financial,bool $seller): void
    {
        $f=$this->purchase();config(['returns.backend_enabled'=>$financial,'returns.admin_review_enabled'=>$seller]);
        $result=$this->actingAs($f['buyer'])->postJson(route('buyer.return-refunds.store',$f['item']),$this->data());
        if($seller){$result->assertCreated();$r=ReturnRefundRequest::sole();$this->decide($f,$r)->assertOk();$this->assertSame('SELLER_REVIEW',$r->workflow_mode);}
        else{$result->assertUnprocessable();$this->assertDatabaseCount('return_refund_requests',0);}
    }

    public function test_migration_preserves_historical_modes_defaults_and_blocks_unsafe_rollback(): void
    {
        $f=$this->purchase();$migration=require database_path('migrations/2026_11_14_000001_allow_seller_review_return_refunds.php');
        $migration->down();$this->assertFalse(app(ReturnRefundWorkflowService::class)->available());
        $this->actingAs($f['buyer'])->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        $old=app(ReturnRefundService::class)->submitForAdmin($f['buyer'],$f['item'],$this->data());$before=$old->fresh()->toJson();
        $migration->up();$this->assertSame($before,$old->fresh()->toJson());$this->assertTrue(app(ReturnRefundWorkflowService::class)->available());
        try{DB::transaction(fn()=>DB::table('return_refund_requests')->where('id',$old->id)->update(['workflow_mode'=>'INVALID']));$this->fail('Invalid mode accepted');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        // Synthetic new mode; prove rollback refuses to reinterpret or lose its identity.
        $old->workflow_mode='SELLER_REVIEW';$old->save();
        try{$migration->down();$this->fail('Unsafe rollback accepted');}catch(\RuntimeException $e){$this->assertStringContainsString('Seller-review requests exist',$e->getMessage());}
        $this->assertSame('SELLER_REVIEW',$old->fresh()->workflow_mode);
    }
}
