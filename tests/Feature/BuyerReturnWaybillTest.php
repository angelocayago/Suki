<?php

namespace Tests\Feature;

use App\Models\{Address,ReturnRefundRequest,ReturnWaybill,User};
use App\Services\{ReturnRefundService,ReturnWaybillService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Mail,Notification,Schema,Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class BuyerReturnWaybillTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();$this->assertStoragePathIsIsolated();
        Http::preventStrayRequests();Mail::fake();Notification::fake();Storage::fake('local');Storage::fake('public');
        config(['returns.backend_enabled'=>false,'returns.admin_review_enabled'=>true,'returns.earning_release_enabled'=>false]);
    }

    public function purchase(): array
    {
        $f=(new DirectAdminReturnRefundTest('test_direct_approval_is_private_idempotent_and_records_one_wallet_adjustment_without_payment_or_restock'))->purchase();
        $f['order']->update(['recipient_name'=>'Original Buyer','street_address'=>'12 Purchased Street','barangay'=>'Buyer Barangay','municipality'=>'Buyer City','province'=>'Buyer Province','postal_code'=>'4000']);
        $f['address']=Address::create(['user_id'=>$f['sellerUser']->id,'label'=>'Store pickup','recipient'=>'Seller contact','phone'=>'PRIVATE-PHONE','line1'=>'34 Return Street','barangay'=>'Seller Barangay','city'=>'Seller City','province'=>'Seller Province','postal_code'=>'5000']);
        $f['seller']->update(['pickup_address_id'=>$f['address']->id]);
        return $f;
    }

    public function request(array $f,bool $approve=true): ReturnRefundRequest
    {
        $r=app(ReturnRefundService::class)->submitForSeller($f['buyer'],$f['item'],['type'=>'RETURN','quantity'=>2,'reason'=>'Damaged','description'=>'Private concern not for the parcel label']);
        if($approve)app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'APPROVED','Seller accepted return.');
        return $r->fresh();
    }

    private function snapshot(): array
    {
        $out=[];foreach(['orders','order_items','seller_orders','shipments','return_refund_requests','return_refund_events','return_refund_accounting','seller_wallet_entries','payouts','payments','notification_outbox']as$table)$out[$table]=DB::table($table)->orderBy('id')->get()->toJson();return $out;
    }

    public function test_buyer_can_issue_and_reprint_a_private_stable_label_without_other_side_effects(): void
    {
        $f=$this->purchase();$r=$this->request($f);$before=$this->snapshot();
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertSee('Print Return Waybill');
        // Visiting the document URL never issues a reference.
        $this->get(route('buyer.return-refunds.waybill.show',$r))->assertNotFound();$this->assertDatabaseCount('return_waybills',0);
        $this->post(route('buyer.return-refunds.waybill.store',$r),['tracking_number'=>'FORGED','seller_id'=>9999])->assertRedirect(route('buyer.return-refunds.waybill.show',$r));
        $label=ReturnWaybill::sole();$original=$label->toJson();
        $this->assertMatchesRegularExpression('/^RET-[A-F0-9-]{36}$/',$label->tracking_number);$this->assertSame($r->id,(int)$label->return_refund_request_id);
        $this->assertSame($f['item']->id,(int)$label->returnRequest->order_item_id);$this->assertSame($f['seller']->id,(int)$label->returnRequest->seller_id);
        $page=$this->get(route('buyer.return-refunds.waybill.show',$r));
        $page->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow')->assertSee('<td>2</td>',false);
        foreach(['RETURN WAYBILL',$label->tracking_number,$f['order']->order_number,'RR-'.$r->id,'Original Buyer','12 Purchased Street','Buyer Barangay','Buyer City','34 Return Street','Seller City','Return Shop','Purchased product','Purchased option','Shipping Fee Payer: Seller','PHT','window.print()','@media print']as$text)$page->assertSee($text,false);
        $page->assertDontSee('PRIVATE-PHONE')->assertDontSee('COD amount')->assertDontSee('Private concern')->assertDontSee('private/proof.jpg');
        $this->assertStringContainsString('no-store',$page->headers->get('Cache-Control'));
        $this->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect();$this->get(route('buyer.return-refunds.waybill.show',$r))->assertOk();
        $this->assertDatabaseCount('return_waybills',1);$this->assertSame($original,$label->fresh()->toJson());$this->assertSame($before,$this->snapshot());
    }

    public function test_reprint_retains_original_purchase_and_issued_address_snapshots(): void
    {
        $f=$this->purchase();$r=$this->request($f);$this->actingAs($f['buyer'])->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect();
        $f['product']->update(['name'=>'Changed listing']);$f['variant']->update(['name'=>'Changed variant']);
        $f['address']->update(['line1'=>'Changed Seller Address']);$f['order']->update(['street_address'=>'Changed Order Address']);
        $this->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect();
        $this->get(route('buyer.return-refunds.waybill.show',$r))->assertOk()->assertSee('Purchased product')->assertSee('Purchased option')->assertSee('12 Purchased Street')->assertSee('34 Return Street')->assertDontSee('Changed listing')->assertDontSee('Changed Seller Address')->assertDontSee('Changed Order Address');
    }

    public static function ineligible(): array { return [['pending'],['rejected'],['ADMIN_REVIEW'],['FINANCIAL'],['missing-history'],['wrong-actor'],['wrong-owner'],['partial-quantity']]; }
    #[DataProvider('ineligible')]
    public function test_ineligible_or_inconsistent_requests_never_issue_labels(string $case): void
    {
        $f=$this->purchase();$r=$this->request($f,$case!=='pending'&&$case!=='rejected');
        if($case==='rejected')app(ReturnRefundService::class)->sellerDecide($r,$f['sellerUser'],'REJECTED','Not accepted.');
        if(in_array($case,['ADMIN_REVIEW','FINANCIAL'],true)){$r->workflow_mode=$case;$r->save();}
        if($case==='missing-history')$r->events()->where('action','SELLER_APPROVED')->delete();
        if($case==='wrong-actor')$r->events()->where('action','SELLER_APPROVED')->update(['actor_id'=>$f['admin']->id]);
        if($case==='wrong-owner')$r->update(['seller_id'=>\App\Models\Seller::create(['user_id'=>User::factory()->create(['role'=>'seller'])->id,'name'=>'Foreign','slug'=>'foreign','status'=>'approved'])->id]);
        if($case==='partial-quantity')$r->update(['quantity'=>1]);
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show',$r))->assertOk()->assertDontSee('Print Return Waybill');
        $this->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect()->assertSessionHasErrors('waybill');
        $this->get(route('buyer.return-refunds.waybill.show',$r))->assertRedirect()->assertSessionHasErrors('waybill');$this->assertDatabaseCount('return_waybills',0);
    }

    public function test_guests_other_buyers_sellers_and_admins_cannot_access_buyer_labels(): void
    {
        $f=$this->purchase();$r=$this->request($f);
        $this->get(route('buyer.return-refunds.waybill.show',$r))->assertRedirect(route('login'));
        $this->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect(route('login'));
        app(ReturnWaybillService::class)->issue($f['buyer'],$r);$before=ReturnWaybill::sole()->toJson();
        $foreign=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($foreign)->get(route('buyer.return-refunds.waybill.show',$r))->assertNotFound();$this->post(route('buyer.return-refunds.waybill.store',$r))->assertNotFound();
        foreach([$f['sellerUser'],$f['admin']]as$actor){$this->actingAs($actor)->get(route('buyer.return-refunds.waybill.show',$r))->assertForbidden();$this->post(route('buyer.return-refunds.waybill.store',$r))->assertForbidden();}
        $this->assertSame($before,ReturnWaybill::sole()->toJson());
    }

    public static function missingAddresses(): array { return [['buyer'],['seller'],['foreign-seller-address'],['seller-incomplete']]; }
    #[DataProvider('missingAddresses')]
    public function test_missing_addresses_are_safe_validation_errors(string $case): void
    {
        $f=$this->purchase();$r=$this->request($f);
        if($case==='buyer')$f['order']->update(['street_address'=>'']);
        if($case==='seller')$f['seller']->update(['pickup_address_id'=>null]);
        if($case==='foreign-seller-address')$f['address']->update(['user_id'=>$f['buyer']->id]);
        if($case==='seller-incomplete')$f['address']->update(['city'=>'']);
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect(route('buyer.return-refunds.show',$r))->assertSessionHasErrors('waybill');$this->assertDatabaseCount('return_waybills',0);
    }

    public function test_province_less_addresses_remain_valid_and_missing_migration_is_safe(): void
    {
        $f=$this->purchase();$r=$this->request($f);$f['order']->update(['province'=>null]);$f['address']->update(['province'=>null]);
        $this->actingAs($f['buyer'])->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect(route('buyer.return-refunds.waybill.show',$r));
        $this->get(route('buyer.return-refunds.waybill.show',$r))->assertOk();
        // In a separate empty fixture state, missing schema never falls back to shipments.
        ReturnWaybill::query()->delete();Schema::drop('return_waybills');
        $this->post(route('buyer.return-refunds.waybill.store',$r))->assertRedirect()->assertSessionHasErrors('waybill');
    }

    public function test_unique_tracking_and_request_constraints_and_rollback_preserve_issued_metadata(): void
    {
        $f=$this->purchase();$r=$this->request($f);$label=app(ReturnWaybillService::class)->issue($f['buyer'],$r);
        $indexes=collect(Schema::getIndexes('return_waybills'));
        $this->assertTrue($indexes->contains(fn($i)=>$i['unique']&&$i['columns']===['tracking_number']));$this->assertTrue($indexes->contains(fn($i)=>$i['primary']&&$i['columns']===['return_refund_request_id']));
        try{DB::transaction(fn()=>DB::table('return_waybills')->insert($label->getAttributes()));$this->fail('Duplicate label accepted');}catch(\Illuminate\Database\QueryException $e){$this->assertNotEmpty($e->getMessage());}
        $migration=require database_path('migrations/2026_11_14_000002_create_return_waybills.php');
        try{$migration->down();$this->fail('Issued metadata discarded');}catch(\RuntimeException $e){$this->assertStringContainsString('Retain issued',$e->getMessage());}
        $this->assertDatabaseCount('return_waybills',1);
    }

    public function test_different_approved_returns_receive_different_namespaced_references(): void
    {
        $first=$this->purchase();$a=$this->request($first);$one=app(ReturnWaybillService::class)->issue($first['buyer'],$a);
        // Free fixture-only catalog identifiers so the same verified purchase helper can be reused.
        $first['seller']->update(['slug'=>'first-store']);$first['product']->update(['slug'=>'first-product']);$first['variant']->update(['sku'=>'FIRST']);
        DB::table('categories')->where('id',$first['product']->category_id)->update(['slug'=>'first-category']);
        DB::table('logistics_providers')->where('slug','provider')->update(['slug'=>'first-provider']);
        $second=$this->purchase();$b=$this->request($second);$two=app(ReturnWaybillService::class)->issue($second['buyer'],$b);
        $this->assertNotSame($one->tracking_number,$two->tracking_number);$this->assertDatabaseCount('return_waybills',2);
        foreach([$one,$two]as$label){$this->assertStringStartsWith('RET-',$label->tracking_number);$this->assertDatabaseMissing('shipments',['tracking_code'=>$label->tracking_number]);}
    }

    public function test_an_existing_normal_tracking_collision_is_skipped_without_overwriting_it(): void
    {
        $f=$this->purchase();$r=$this->request($f);
        $first='11111111-1111-4111-8111-111111111111';$second='22222222-2222-4222-8222-222222222222';
        $f['shipment']->update(['tracking_code'=>'RET-'.$first]);
        $values=[\Ramsey\Uuid\Uuid::fromString($first),\Ramsey\Uuid\Uuid::fromString($second)];
        \Illuminate\Support\Str::createUuidsUsing(function()use(&$values){return array_shift($values);});
        try{$label=app(ReturnWaybillService::class)->issue($f['buyer'],$r);}
        finally{\Illuminate\Support\Str::createUuidsNormally();}
        $this->assertSame('RET-'.$second,$label->tracking_number);$this->assertSame('RET-'.$first,$f['shipment']->fresh()->tracking_code);$this->assertDatabaseCount('return_waybills',1);
    }
}
