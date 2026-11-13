<?php

namespace Tests\Feature;

use App\Models\{CartItem, Category, DeliveryEvent, LogisticsProvider, Product, ProductVariant, ReturnRefundRequest, Rider, Seller, User};
use App\Services\{CheckoutService, CodRemittanceService, SellerWalletService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Schema, Storage};
use Tests\Support\IsolatedStorageTestCase;

class DirectAdminReturnRefundTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    public static function gateMatrix(): array
    {
        return ['both disabled'=>[false,false], 'Admin only'=>[false,true], 'financial only'=>[true,false], 'both enabled'=>[true,true]];
    }

    public static function adminDecisionMatrix(): array
    {
        $cases = [];
        foreach (self::gateMatrix() as $label=>$flags) foreach (['APPROVED','REJECTED'] as $decision) $cases[$label.' '.$decision] = [...$flags,$decision];
        return $cases;
    }

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        Http::preventStrayRequests(); Mail::fake(); Notification::fake(); Storage::fake('local'); Storage::fake('public');
        config(['returns.backend_enabled'=>false, 'returns.admin_review_enabled'=>true, 'returns.earning_release_enabled'=>false]);
    }

    private function purchase(bool $discounted = false): array
    {
        $buyer = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $admin = User::factory()->create(['role'=>'admin','status'=>'active']);
        $sellerUser = User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller = Seller::create(['user_id'=>$sellerUser->id,'name'=>'Return Shop','slug'=>'return-shop','status'=>'approved']);
        // No pickup address: direct Admin review must not require parcel handling.
        $category = Category::create(['name'=>'Category','slug'=>'category','is_active'=>true]);
        $product = Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Purchased product','slug'=>'purchased-product','is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>1000]);
        $variant = ProductVariant::create(['product_id'=>$product->id,'name'=>'Purchased option','sku'=>'DIRECT','price_minor'=>10000,'stock'=>10,'is_active'=>true]);
        CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>2,'price'=>100]);
        $codes = [];
        if ($discounted) foreach (['SELLER'=>101, 'PLATFORM'=>201] as $scope=>$amount) {
            $voucher = \App\Models\Voucher::create(['code'=>'DIRECT-'.$scope,'scope'=>$scope,'seller_id'=>$scope==='SELLER'?$seller->id:null,'discount_type'=>'FIXED','discount_minor'=>$amount,'is_active'=>true]);
            $codes[] = $voucher->code;
        }
        $order = app(CheckoutService::class)->checkout($buyer,['recipient_name'=>'Buyer','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test'],'cod',$codes);
        $so = $order->sellerOrders->first(); $shipment = $so->shipment; $item = $so->items->first();
        $provider = LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics'])->id,'name'=>'Provider','slug'=>'provider','status'=>'approved']);
        $rider = Rider::create(['user_id'=>User::factory()->create(['role'=>'rider','status'=>'active'])->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'TEST','is_active'=>true]);
        $so->update(['workflow_status'=>'DELIVERED','status'=>'delivered','delivered_at'=>now(),'logistics_provider_id'=>$provider->id]);
        $shipment->update(['workflow_status'=>'DELIVERED','status'=>'delivered','logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'proof_path'=>'private/proof.jpg','proof_delivered_at'=>now(),'delivered_by'=>$rider->user_id,'cod_collected'=>true,'cod_collected_minor'=>$shipment->cod_amount_minor,'cod_collected_at'=>now()]);
        DeliveryEvent::create(['shipment_id'=>$shipment->id,'status'=>'COD_COLLECTED','attempt'=>0,'user_id'=>$rider->user_id,'occurred_at'=>now()]);
        app(CodRemittanceService::class)->createLiability($shipment->fresh());
        $order->update(['status'=>'DELIVERED','payment_status'=>'PAID']);
        return compact('buyer','admin','sellerUser','seller','product','variant','order','so','shipment','item');
    }

    private function data(array $overrides = []): array
    {
        return $overrides + ['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged item','description'=>'Item was damaged on arrival.','confirmed'=>1];
    }

    private function submit(array $f): ReturnRefundRequest
    {
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.store',$f['item']), $this->data(['workflow_mode'=>'FINANCIAL','file'=>UploadedFile::fake()->image('evidence.png')]))->assertSessionHasNoErrors()->assertRedirect();
        return ReturnRefundRequest::sole();
    }

    private function financialSnapshot(): array
    {
        $snapshot = [];
        foreach (['orders','order_items','seller_orders','shipments','product_variants','seller_wallet_entries','payouts','payments','rider_cod_remittances','logistics_cod_remittances','logistics_cod_remittance_items','voucher_redemptions','return_refund_accounting'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $snapshot;
    }

    public function test_direct_approval_is_private_idempotent_and_has_no_financial_or_inventory_effects(): void
    {
        $f = $this->purchase();
        $before = $this->financialSnapshot();
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.create',$f['item']))->assertOk()->assertSee('Admin will review')->assertDontSee('approved pickup/return address');
        $r = $this->submit($f);
        $this->assertTrue($r->isAdminReview());
        $this->assertSame(20000, $r->refund_minor);
        $this->assertDatabaseCount('return_refund_workflows',0);
        $this->assertSame(0,ReturnRefundRequest::financial()->count());
        $wallet = app(SellerWalletService::class);
        $this->assertFalse((new \ReflectionMethod($wallet,'hasActiveReturn'))->invoke($wallet,$f['so']->id));
        $this->assertNull($wallet->payoutRestriction($f['seller']->id));
        $this->assertSame($before,$this->financialSnapshot());
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('Pending Admin Review')->assertSee('Finalize decision')->assertSee('Purchased option');
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Purchase evidence reviewed.'])->assertOk();
        $this->assertSame('ADMIN_APPROVED',$r->fresh()->status);
        $this->assertSame($f['admin']->id,$r->fresh()->reviewed_by);
        $this->assertNotNull($r->fresh()->decided_at);
        $this->assertSame(['SUBMITTED','ADMIN_APPROVED'],$r->events()->orderBy('id')->pluck('action')->all());
        $intents = DB::table('notification_outbox')->count();
        foreach ([$f['buyer']->id,$f['sellerUser']->id] as $recipient) {
            $this->assertTrue(DB::table('notification_outbox')->where('user_id',$recipient)->where('title','Return/refund decision')->exists());
        }
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Repeated'])->assertOk();
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Conflicting'])->assertUnprocessable();
        $this->assertSame($intents,DB::table('notification_outbox')->count());
        $this->assertSame(2,$r->events()->count());
        foreach (['buyer'=>$f['buyer'],'seller'=>$f['sellerUser']] as $role=>$user) {
            $this->actingAs($user)->get(route($role.'.return-refunds.show',$r))->assertOk()->assertSee('Approved')->assertSee('Contact Seller')->assertSee('200.00')->assertDontSee('Confirm Returned Parcel')->assertDontSee('name="account"',false);
            $this->get(route($role.'.return-refunds.index'))->assertOk()->assertSee('Contact Seller');
            $this->post(route($role.'.messages.context.seller-order.store',$f['so']))->assertRedirect();
        }
        $this->assertSame($before,$this->financialSnapshot());
        $this->assertSame(1,\App\Models\Conversation::where('seller_order_id',$f['so']->id)->count());
        $evidence = \App\Models\ReturnRefundEvidence::sole();
        $this->actingAs($f['buyer'])->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertOk();
        $foreign = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($foreign)->get(route('return-refunds.evidence.show',[$r,$evidence]))->assertForbidden();
        $this->post(route('buyer.messages.context.seller-order.store',$f['so']))->assertForbidden();
        $this->get(route('buyer.return-refunds.show',$r))->assertNotFound();
    }

    public function test_rejection_requires_reason_and_only_an_eligible_admin_can_decide(): void
    {
        $f = $this->purchase(); $before = $this->financialSnapshot(); $r = $this->submit($f);
        foreach ([$f['buyer'],$f['sellerUser']] as $actor) $this->actingAs($actor)->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Unauthorized'])->assertForbidden();
        $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'APPROVE','note'=>'Seller response'])->assertUnprocessable();
        $f['admin']->update(['is_suspended'=>true]);
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Suspended'])->assertForbidden();
        $f['admin']->update(['is_suspended'=>false]);
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>''])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Evidence does not support this request.'])->assertOk();
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Rejected')->assertSee('Evidence does not support this request.')->assertDontSee('Chat with Seller');
        $this->assertSame($before,$this->financialSnapshot());
    }

    public function test_validation_ownership_duplicates_and_order_eligibility_remain_authoritative(): void
    {
        $f = $this->purchase();
        $foreign = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($foreign)->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertForbidden();
        $this->actingAs($f['buyer']);
        foreach ([['quantity'=>0],['quantity'=>1],['quantity'=>3],['quantity'=>'1.5'],['reason'=>''],['description'=>''],['confirmed'=>0],['file'=>UploadedFile::fake()->create('text.txt',1,'text/plain')],['file'=>UploadedFile::fake()->image('large.png')->size(5121)]] as $invalid) {
            $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('return_refund_requests',0);
        $f['so']->update(['workflow_status'=>'PLACED','status'=>'pending']);
        $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        $f['so']->update(['workflow_status'=>'DELIVERED','status'=>'delivered']);
        // Current catalog moderation cannot erase the eligibility of a purchase.
        $f['product']->update(['moderation_status'=>'HIDDEN','is_active'=>false]);
        $r = $this->submit($f);
        $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data(['workflow_mode'=>'FINANCIAL']))->assertUnprocessable();
        $this->assertDatabaseCount('return_refund_requests',1);
        $this->assertSame('ADMIN_REVIEW',$r->fresh()->workflow_mode);
        $this->assertCount(1,Storage::disk('local')->allFiles());
    }

    public function test_missing_discriminator_or_disabled_activation_blocks_new_requests(): void
    {
        $f = $this->purchase(); config(['returns.admin_review_enabled'=>false]);
        $this->actingAs($f['buyer'])->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        config(['returns.admin_review_enabled'=>true]);
        Schema::table('return_refund_requests',fn($table)=>$table->dropColumn('workflow_mode'));
        $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        $this->assertDatabaseCount('return_refund_requests',0);
    }

    public function test_direct_amount_preserves_both_discounts_and_excludes_shipping(): void
    {
        $f = $this->purchase(true); $before = $this->financialSnapshot();
        $r = $this->submit($f);
        $this->assertSame(20000-101-201,$r->refund_minor);
        $this->assertGreaterThan(0,$f['so']->shipping_fee_minor);
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Verified purchase discounts.'])->assertOk();
        $this->assertSame(19698,$r->fresh()->refund_minor);
        $this->assertSame($before,$this->financialSnapshot());
    }

    public function test_additive_migration_preserves_historical_identity_and_refuses_loss_of_direct_mode(): void
    {
        $f = $this->purchase();
        config(['returns.backend_enabled'=>true]);
        $r = app(\App\Services\ReturnRefundService::class)->submit($f['buyer'],$f['item'],$this->data());
        $migration = require database_path('migrations/2026_11_13_000001_add_return_refund_workflow_mode.php');
        $migration->down();
        $before = $r->fresh()->getAttributes();
        $this->assertSame(1,ReturnRefundRequest::financial()->count());
        $migration->up();
        $this->assertSame('FINANCIAL',$r->fresh()->workflow_mode);
        $attributes = $r->fresh()->getAttributes(); unset($attributes['workflow_mode']);
        $this->assertSame($before,$attributes);
        $this->assertNotNull(app(SellerWalletService::class)->payoutRestriction($f['seller']->id));
        $r->workflow_mode = 'ADMIN_REVIEW'; $r->save();
        try { $migration->down(); $this->fail('Discriminator was discarded.'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Retain the workflow discriminator',$error->getMessage()); }
        $this->assertTrue(Schema::hasColumn('return_refund_requests','workflow_mode'));
    }

    public function test_failed_decision_event_rolls_back_the_decision_and_notifications(): void
    {
        $f = $this->purchase(); $r = $this->submit($f); $before = $this->financialSnapshot();
        $intents = DB::table('notification_outbox')->count();
        DB::unprepared("CREATE TRIGGER direct_event_failure BEFORE INSERT ON return_refund_events WHEN NEW.action = 'ADMIN_APPROVED' BEGIN SELECT RAISE(ABORT, 'Synthetic event failure'); END");
        try { app(\App\Services\ReturnRefundService::class)->decide($r,$f['admin'],'APPROVED','Reviewed.'); $this->fail('Event failure was swallowed'); }
        catch (\Illuminate\Database\QueryException) { $this->assertSame('SUBMITTED',$r->fresh()->status); }
        $this->assertNull($r->fresh()->admin_decision);
        $this->assertSame(1,$r->events()->count());
        $this->assertSame($intents,DB::table('notification_outbox')->count());
        $this->assertSame($before,$this->financialSnapshot());
    }

    public function test_approval_revalidates_purchase_and_a_justified_rejection_remains_available(): void
    {
        $f = $this->purchase(); $r = $this->submit($f);
        $f['shipment']->update(['cod_collected'=>false]);
        $before = $this->financialSnapshot();
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Reviewed.'])->assertUnprocessable();
        $this->assertSame('SUBMITTED',$r->fresh()->status);
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Purchase evidence could not be verified.'])->assertOk();
        $this->assertSame('ADMIN_REJECTED',$r->fresh()->status);
        $this->assertSame(20000,$r->fresh()->refund_minor);
        $this->assertSame($before,$this->financialSnapshot());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminDecisionMatrix')]
    public function test_admin_gate_matrix_controls_submission_ui_and_both_decisions(bool $financial, bool $direct, string $choice): void
    {
        $f = $this->purchase(); $before = $this->financialSnapshot();
        config(['returns.backend_enabled'=>$financial,'returns.admin_review_enabled'=>$direct]);
        $html = $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.create',$f['item']))->assertOk()->getContent();
        $this->assertSame(!$direct,str_contains($html,'fieldset disabled'));
        $response = $this->postJson(route('buyer.return-refunds.store',$f['item']),$this->data(['workflow_mode'=>'FINANCIAL']));
        if ($direct) {
            $response->assertCreated();
            $r = ReturnRefundRequest::sole();
            $this->assertSame('ADMIN_REVIEW',$r->workflow_mode);
        } else {
            $response->assertUnprocessable(); $this->assertDatabaseCount('return_refund_requests',0);
            // Existing simplified records remain readable even when their gate is off.
            config(['returns.admin_review_enabled'=>true]); $r = $this->submit($f);
            config(['returns.admin_review_enabled'=>false]);
        }
        $adminHtml = $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->getContent();
        $this->assertSame(!$direct,str_contains($adminHtml,'<fieldset disabled'));
        $decision = $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>$choice,'reason'=>'Reviewed.']);
        if ($direct) $decision->assertOk(); else $decision->assertUnprocessable();
        $this->assertSame($direct ? 'ADMIN_'.$choice : 'SUBMITTED',$r->fresh()->status);
        foreach (['buyer'=>$f['buyer'],'seller'=>$f['sellerUser']] as $role=>$actor) {
            $this->actingAs($actor)->get(route($role.'.return-refunds.show',$r))->assertOk();
        }
        $this->assertSame($before,$this->financialSnapshot());
        $this->assertSame(0,ReturnRefundRequest::financial()->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gateMatrix')]
    public function test_financial_gate_matrix_preserves_historical_review_without_cross_activation(bool $financial, bool $direct): void
    {
        $f = $this->purchase(); config(['returns.backend_enabled'=>true]);
        $r = app(\App\Services\ReturnRefundService::class)->submit($f['buyer'],$f['item'],$this->data());
        $w = \App\Models\ReturnRefundWorkflow::create(['return_refund_request_id'=>$r->id,'return_address'=>'Historical return address','destination'=>['method'=>'GCASH','holder'=>'Synthetic','account'=>'09123456789'],'parcel_status'=>'SHIPPED','courier'=>'Synthetic courier','tracking_number'=>'SYNTHETIC']);
        config(['returns.backend_enabled'=>$financial,'returns.admin_review_enabled'=>$direct]);
        $before = $this->financialSnapshot();
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk();
        $seller = $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'APPROVE','note'=>'Historical recommendation.']);
        if ($financial) $seller->assertOk(); else $seller->assertUnprocessable();
        $receipt = $this->post(route('seller.return-refunds.receive',$r),['inspection_result'=>'ACCEPTED','inspection_note'=>'Historical item received.']);
        if ($financial) $receipt->assertSessionHasNoErrors(); else $receipt->assertSessionHasErrors('request');
        $html = $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->getContent();
        $this->assertSame(!$financial,str_contains($html,'<fieldset disabled'));
        $decision = $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Historical purchase and parcel verified.']);
        if ($financial) {
            $decision->assertOk();
            $this->assertDatabaseCount('return_refund_accounting',1);
            $this->assertDatabaseHas('seller_wallet_entries',['return_refund_request_id'=>$r->id,'type'=>'REFUND_ADJUSTMENT']);
        } else {
            $decision->assertUnprocessable();
            $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Disabled historical workflow.'])->assertUnprocessable();
            $this->assertSame('SUBMITTED',$r->fresh()->status);
            $this->assertSame('SHIPPED',$w->fresh()->parcel_status);
            $this->assertSame($before,$this->financialSnapshot());
            $this->assertSame(1,$r->events()->count());
        }
        $this->assertDatabaseMissing('seller_wallet_entries',['type'=>'EARNING_RELEASE']);
        $this->assertSame('FINANCIAL',$r->fresh()->workflow_mode);
    }

    public function test_gate_defaults_are_false_and_non_boolean_admin_values_fail_closed(): void
    {
        $defaults = require config_path('returns.php');
        foreach (['backend_enabled','admin_review_enabled','earning_release_enabled'] as $flag) $this->assertFalse($defaults[$flag]);
        $f = $this->purchase(); config(['returns.backend_enabled'=>true]);
        foreach ([false,null,0,1,'true','1',[]] as $value) {
            config(['returns.admin_review_enabled'=>$value]);
            $this->actingAs($f['buyer'])->postJson(route('buyer.return-refunds.store',$f['item']),$this->data())->assertUnprocessable();
        }
        $this->assertDatabaseCount('return_refund_requests',0);
    }
}
