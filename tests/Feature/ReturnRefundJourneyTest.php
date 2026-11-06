<?php
namespace Tests\Feature;

use App\Models\{Address,CartItem,Category,DeliveryEvent,LogisticsProvider,Product,ProductVariant,ReturnRefundRequest,ReturnRefundWorkflow,Rider,Seller,SellerWalletEntry,User};
use App\Services\{CheckoutService,CodRemittanceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Schema,Storage};
use Tests\Support\IsolatedStorageTestCase;

class ReturnRefundJourneyTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();$this->assertSame(':memory:',DB::connection()->getDatabaseName());
        Mail::fake();Notification::fake();Http::preventStrayRequests();Storage::fake('local');Storage::fake('public');
        config(['returns.backend_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }
    private function purchase(): array
    {
        $buyer=User::factory()->create(['role'=>'buyer','status'=>'active']);$admin=User::factory()->create(['role'=>'admin','status'=>'active']);
        $sellerUser=User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller=Seller::create(['user_id'=>$sellerUser->id,'name'=>'Journey shop','slug'=>'journey-shop','status'=>'approved']);
        $address=Address::create(['user_id'=>$sellerUser->id,'recipient'=>'Journey shop','phone'=>'000','postal_code'=>'4009','line1'=>'Test return street','city'=>'Santa Cruz','province'=>'Laguna','barangay'=>'Duhat','city_municipality_code'=>'0403413000','barangay_code'=>'0403413012']);$seller->update(['pickup_address_id'=>$address->id]);
        $category=Category::create(['name'=>'Journey','slug'=>'journey','is_active'=>true]);
        $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Purchased item','slug'=>'journey-item','is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>1000]);
        $variant=ProductVariant::create(['product_id'=>$product->id,'name'=>'Flavor / Pack','sku'=>'JOURNEY','price_minor'=>10000,'stock'=>10,'is_active'=>true]);
        CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>2,'price'=>100]);
        $order=app(CheckoutService::class)->checkout($buyer,['recipient_name'=>'Buyer','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test'],'cod',[]);
        $so=$order->sellerOrders->first();$shipment=$so->shipment;$item=$so->items->first();
        $provider=LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics'])->id,'name'=>'Provider','slug'=>'journey-provider','status'=>'approved']);
        $rider=Rider::create(['user_id'=>User::factory()->create(['role'=>'rider','status'=>'active'])->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'TEST','is_active'=>true]);
        $so->update(['workflow_status'=>'DELIVERED','status'=>'delivered','delivered_at'=>now(),'logistics_provider_id'=>$provider->id]);
        $shipment->update(['workflow_status'=>'DELIVERED','status'=>'delivered','logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'proof_path'=>'isolated/proof.jpg','proof_delivered_at'=>now(),'delivered_by'=>$rider->user_id,'cod_collected'=>true,'cod_collected_minor'=>$shipment->cod_amount_minor,'cod_collected_at'=>now()]);
        DeliveryEvent::create(['shipment_id'=>$shipment->id,'status'=>'COD_COLLECTED','attempt'=>0,'user_id'=>$rider->user_id,'occurred_at'=>now()]);
        app(CodRemittanceService::class)->createLiability($shipment->fresh());$order->update(['status'=>'DELIVERED','payment_status'=>'PAID']);
        return compact('buyer','admin','sellerUser','seller','variant','order','so','shipment','item');
    }
    private function submit(array $f): ReturnRefundRequest
    {
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.store',$f['item']),['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged item','description'=>'Packaging and item were damaged.','confirmed'=>1,'file'=>UploadedFile::fake()->image('concern.png')])->assertSessionHasNoErrors()->assertRedirect();
        return ReturnRefundRequest::sole();
    }
    private function destination(string $status='PENDING'): array
    {
        return ['method'=>'GCASH','holder'=>'Private recipient name','account'=>'09123456789','parcel_status'=>$status,'courier'=>$status==='SHIPPED'?'Test courier':null,'tracking_number'=>$status==='SHIPPED'?'RETURN-123':null];
    }
    public function test_visible_buyer_seller_admin_journey_preserves_privacy_and_financial_snapshots(): void
    {
        $f=$this->purchase();$snapshots=[];foreach(['orders','seller_orders','shipments','order_items','product_variants'] as $table)$snapshots[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        $this->actingAs($f['buyer'])->get(route('buyer.my-orders'))->assertOk()->assertSee('My Returns &amp; Refunds',false);
        $this->get(route('buyer.order-details',$f['order']->order_number))->assertOk()->assertSee(route('buyer.return-refunds.create',$f['item']),false);
        $this->get(route('buyer.return-refunds.create',$f['item']))->assertOk()->assertSee('Confirm and Submit Return / Refund')->assertSee('200.00');
        $r=$this->submit($f);$this->get(route('buyer.order-details',$f['order']->order_number))->assertOk()->assertSee('View Request')->assertSee('SUBMITTED');
        $this->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Chat with Seller');
        $this->actingAs($f['sellerUser'])->get(route('seller.return-refunds.index'))->assertOk()->assertSee('Pending review')->assertSee('View details');
        $this->get(route('seller.return-refunds.show',$r))->assertOk()->assertSee('Confirm willingness to proceed')->assertSee('Dispute/Decline')->assertSee('Chat with Buyer');
        $this->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'APPROVE','note'=>'Please return the item for inspection.'])->assertOk();
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Submit Refund &amp; Return Details',false);
        $this->post(route('buyer.return-refunds.details',$r),array_replace($this->destination(),['account'=>'bad']))->assertSessionHasErrors('account');
        $this->assertNull(ReturnRefundWorkflow::sole()->destination);$this->assertNull(session()->get('_old_input.account'));
        $this->post(route('buyer.return-refunds.details',$r),array_replace($this->destination('SHIPPED'),['courier'=>null]))->assertSessionHasErrors('courier');
        $this->post(route('buyer.return-refunds.details',$r),$this->destination())->assertSessionHasNoErrors()->assertRedirect();
        $w=ReturnRefundWorkflow::sole();$this->assertSame('PENDING',$w->parcel_status);$this->assertNull($w->tracking_number);
        $this->assertStringNotContainsString('09123456789',DB::table('return_refund_workflows')->value('destination'));$this->assertArrayNotHasKey('destination',$w->toArray());
        $this->actingAs($f['sellerUser'])->get(route('seller.return-refunds.show',$r))->assertOk()->assertDontSee('09123456789')->assertDontSee('Private recipient name');
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Too early'])->assertUnprocessable();
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.details',$r),$this->destination('SHIPPED'))->assertSessionHasNoErrors();
        $this->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Track Return')->assertSee('RETURN-123');
        $this->actingAs($f['sellerUser'])->get(route('seller.return-refunds.show',$r))->assertOk()->assertSee('Confirm Returned Parcel');
        $this->post(route('seller.return-refunds.receive',$r),['inspection_result'=>'ACCEPTED','inspection_note'=>'Correct item received.'])->assertSessionHasNoErrors();
        $this->post(route('seller.return-refunds.evidence.store',$r),['file'=>UploadedFile::fake()->image('inspection.png'),'description'=>'Seller inspection'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2,\App\Models\ReturnRefundEvidence::count());
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.index'))->assertOk()->assertSee('RR-'.$r->id);
        $this->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('09123456789')->assertSee('Returned parcel received');
        $r->update(['status'=>'UNDER_REVIEW']);
        $this->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('Finalize decision');
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Purchase and return evidence verified.'])->assertOk();
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Repeated'])->assertOk();
        $this->assertSame('REFUND_DUE',DB::table('return_refund_accounting')->sole()->refund_status);$this->assertSame(1,SellerWalletEntry::where('type','REFUND_ADJUSTMENT')->count());
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Payment Pending')->assertSee('No verified payment');
        foreach($snapshots as $table=>$snapshot)$this->assertSame($snapshot,DB::table($table)->orderBy('id')->get()->toJson());
        $this->assertStringNotContainsString('09123456789',DB::table('notification_outbox')->get()->toJson());
    }
    public function test_ownership_duplicate_destination_validation_and_disabled_schema_fail_closed(): void
    {
        $f=$this->purchase();$other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->get(route('buyer.return-refunds.create',$f['item']))->assertForbidden();
        $r=$this->submit($f);$this->actingAs($f['buyer'])->get(route('buyer.return-refunds.create',$f['item']))->assertRedirect(route('buyer.return-refunds.show',$r));
        $this->actingAs($other)->get(route('buyer.return-refunds.show',$r))->assertNotFound();
        $this->post(route('buyer.return-refunds.details',$r),$this->destination())->assertForbidden();
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.details',$r),$this->destination())->assertSessionHasErrors('request');
        $this->assertNull(ReturnRefundWorkflow::sole()->destination);
        $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'REJECT','note'=>'Item differs from described condition; Admin review requested.'])->assertOk();
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Admin review required')->assertDontSee('name="account"',false);
        config(['returns.backend_enabled'=>false]);
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('actions are disabled')->assertSee('value="APPROVED" disabled',false);
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'No activation'])->assertUnprocessable();
    }

    public function test_missing_schema_and_disabled_activation_show_read_only_form_and_create_no_request(): void
    {
        $f=$this->purchase();Schema::drop('return_refund_workflows');
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.create',$f['item']))->assertOk()->assertSee('temporarily disabled')->assertSee('fieldset disabled',false);
        $this->postJson(route('buyer.return-refunds.store',$f['item']),['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged','description'=>'Damaged','confirmed'=>1])->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->assertDatabaseCount('return_refund_requests',0);$this->assertDatabaseCount('seller_wallet_entries',0);
    }

    public function test_existing_order_chat_photo_only_and_mixed_replies_are_visible_after_reopening(): void
    {
        $f=$this->purchase();config(['messaging.attachments_enabled'=>true]);Storage::fake('message_attachments');
        $this->actingAs($f['buyer'])->post(route('buyer.messages.context.seller-order.store',$f['so']))->assertRedirect();
        $conversation=\App\Models\Conversation::where('seller_order_id',$f['so']->id)->sole();
        $this->get(route('buyer.messages.show',$conversation->reference))->assertOk()->assertSee('Add Photo')->assertSee('name="images[]"',false)->assertSee('class="suki-chat-composer"',false);
        $this->post(route('buyer.messages.store',$conversation->reference),['images'=>[UploadedFile::fake()->image('buyer-photo.png')]])->assertRedirect();
        $attachment=\App\Models\MessageAttachment::sole();
        $this->get(route('buyer.messages.show',$conversation->reference))->assertOk()->assertSee('data-message-gallery',false)->assertSee('messageImageLightbox')->assertDontSee($attachment->path);
        $this->actingAs($f['sellerUser'])->get(route('seller.messages.show',$conversation->reference))->assertOk()->assertSee('Add Photo')->assertSee('data-message-gallery',false)->assertSee('class="suki-chat-composer"',false);
        $this->post(route('seller.messages.store',$conversation->reference),['body'=>'Inspection photo reply','images'=>[UploadedFile::fake()->image('seller-photo.png')]])->assertRedirect();
        $this->actingAs($f['buyer'])->getJson(route('buyer.messages.poll',$conversation->reference))->assertOk()->assertJsonCount(2,'messages')->assertJsonCount(1,'messages.0.attachments')->assertJsonCount(1,'messages.1.attachments');
        $this->get(route('buyer.messages.attachments.show',['conversation'=>$conversation->reference,'attachment'=>$attachment->id]))->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->get(route('buyer.messages.attachments.show',['conversation'=>$conversation->reference,'attachment'=>$attachment->id]))->assertForbidden();
        $this->assertSame(2,\App\Models\Message::where('conversation_id',$conversation->id)->count());
        config(['messaging.attachments_enabled'=>false]);
        foreach([['buyer',$f['buyer']],['seller',$f['sellerUser']]] as [$role,$actor]){
            $this->actingAs($actor)->get(route($role.'.messages.show',$conversation->reference))->assertOk()->assertSee('Add Photo (unavailable)')->assertSee('Photo sharing has not been enabled yet.')->assertSee('class="suki-chat-composer"',false);
        }
    }

    public function test_failed_submission_evidence_metadata_rolls_back_request_and_deletes_only_new_file(): void
    {
        $f=$this->purchase();Storage::disk('local')->put('existing.txt','Preserve');$before=DB::table('notification_outbox')->count();
        \App\Models\ReturnRefundEvidence::creating(fn()=>throw new \RuntimeException('Isolated metadata failure'));
        try{$this->withoutExceptionHandling()->actingAs($f['buyer'])->post(route('buyer.return-refunds.store',$f['item']),['type'=>'RETURN','quantity'=>2,'reason'=>'Damage','description'=>'Damage','confirmed'=>1,'file'=>UploadedFile::fake()->image('evidence.png')]);$this->fail('Expected failure');}
        catch(\RuntimeException $e){$this->assertSame('Isolated metadata failure',$e->getMessage());}
        finally{\App\Models\ReturnRefundEvidence::flushEventListeners();}
        $this->assertDatabaseCount('return_refund_requests',0);$this->assertDatabaseCount('return_refund_workflows',0);$this->assertDatabaseCount('return_refund_events',0);
        $this->assertSame(['existing.txt'],Storage::disk('local')->allFiles());$this->assertSame($before,DB::table('notification_outbox')->count());
    }

    public function test_bank_destination_rejects_meaningless_identifiers_without_flashing_private_inputs(): void
    {
        $f=$this->purchase();$r=$this->submit($f);
        $this->actingAs($f['sellerUser'])->patchJson(route('seller.return-refunds.respond',$r),['recommendation'=>'APPROVE','note'=>'Please return.'])->assertOk();
        $this->actingAs($f['buyer']);
        foreach(['----','    ','ABCD','1---','@1234'] as $account){
            $this->post(route('buyer.return-refunds.details',$r),array_replace($this->destination(),['method'=>'BANK','bank'=>'Test bank','account'=>$account]))->assertSessionHasErrors('account');
            $this->assertNull(session()->get('_old_input.account'));$this->assertNull(ReturnRefundWorkflow::sole()->destination);
        }
        foreach([['bank'=>null],['bank'=>'---'],['holder'=>'---'],['method'=>'OTHER']] as $invalid){
            $this->post(route('buyer.return-refunds.details',$r),array_replace($this->destination(),['method'=>'BANK','bank'=>'Test bank','account'=>'0012-3456'],$invalid))->assertSessionHasErrors(array_key_first($invalid));
        }
        foreach(['0012345678','0012-3456 7890','GB82 WEST 1234 5698 7654 32'] as $account){
            $this->post(route('buyer.return-refunds.details',$r),array_replace($this->destination(),['method'=>'BANK','bank'=>'Test bank','account'=>$account]))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($account,ReturnRefundWorkflow::sole()->destination['account']);
            $this->assertStringNotContainsString($account,DB::table('return_refund_workflows')->value('destination'));
        }
        $this->actingAs($f['sellerUser'])->get(route('seller.return-refunds.show',$r))->assertOk()->assertDontSee('GB82 WEST');
    }

    public function test_under_review_legacy_request_needs_review_for_approval_but_admin_can_explicitly_reject(): void
    {
        $f=$this->purchase();$r=$this->submit($f);$r->update(['status'=>'UNDER_REVIEW']);ReturnRefundWorkflow::query()->delete();
        $this->actingAs($f['admin'])->get(route('admin.return-refunds.show',$r))->assertOk()->assertSee('support review')->assertSee('Select Decision')->assertSee('value="APPROVED" disabled',false);
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'','reason'=>'Review'])->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Cannot prove workflow'])->assertUnprocessable();
        $this->actingAs($f['buyer'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Wrong role'])->assertForbidden();
        config(['returns.backend_enabled'=>false]);
        $this->actingAs($f['admin'])->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Historical workflow cannot be verified.'])->assertOk();
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'REJECTED','reason'=>'Repeated'])->assertOk();
        $this->patchJson(route('admin.return-refunds.decide',$r),['decision'=>'APPROVED','reason'=>'Conflicting decision'])->assertUnprocessable();
        $this->assertSame('ADMIN_REJECTED',$r->fresh()->status);$this->assertDatabaseCount('return_refund_accounting',0);$this->assertDatabaseCount('seller_wallet_entries',0);
    }

    public function test_pending_amounts_and_neutral_admin_decision_are_consistent_in_all_three_portals(): void
    {
        $f=$this->purchase();$r=$this->submit($f);
        foreach([['buyer',$f['buyer']],['seller',$f['sellerUser']],['admin',$f['admin']]] as [$role,$actor]){
            $this->actingAs($actor)->get(route($role.'.return-refunds.show',$r))->assertOk()->assertSee('Requested Refund')->assertSee('200.00')->assertSee('Awaiting Decision')->assertDontSee('Refund Due</dt>',false);
        }
        $this->get(route('admin.return-refunds.show',$r))->assertSee('<option value="" selected disabled>Select Decision</option>',false);
    }

    public static function cleanupFailures(): array { return [['false'],['throw']]; }
    #[\PHPUnit\Framework\Attributes\DataProvider('cleanupFailures')]
    public function test_submission_cleanup_failure_is_sanitized_and_preserves_original_exception(string $failure): void
    {
        $f=$this->purchase();$disk=Storage::disk('local');$disk->put('existing.txt','Preserve');
        $proxy=\Mockery::mock($disk);$deletion=$proxy->shouldReceive('delete')->once()->with(\Mockery::on(fn($path)=>str_starts_with($path,'return-refund-evidence/')));
        if($failure==='throw')$deletion->andThrow(new \RuntimeException('Sensitive filesystem path must not be logged'));else $deletion->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);\Illuminate\Support\Facades\Log::spy();
        \App\Models\ReturnRefundEvidence::creating(fn()=>throw new \RuntimeException('Isolated metadata failure'));
        try{$this->withoutExceptionHandling()->actingAs($f['buyer'])->post(route('buyer.return-refunds.store',$f['item']),['type'=>'RETURN','quantity'=>2,'reason'=>'Damage','description'=>'Damage','confirmed'=>1,'file'=>UploadedFile::fake()->image('evidence.png')]);$this->fail('Expected failure');}
        catch(\RuntimeException $e){$this->assertSame('Isolated metadata failure',$e->getMessage());}
        finally{\App\Models\ReturnRefundEvidence::flushEventListeners();}
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with($failure==='throw'?'Private refund submission evidence cleanup failed.':'Private refund submission evidence needs cleanup.');
        $this->assertSame('Preserve',$disk->get('existing.txt'));$this->assertDatabaseCount('return_refund_requests',0);$this->assertDatabaseCount('return_refund_workflows',0);$this->assertDatabaseCount('return_refund_evidence',0);
    }
}
