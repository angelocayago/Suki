<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{CartItem, Category, DeliveryEvent, LogisticsProvider, Order, Payment, Product, ProductVariant, Rider, Seller, SellerOrder, Shipment, User, Voucher, VoucherClaim, VoucherRedemption};
use App\Services\{CheckoutService, DomainNotificationService, OrderCancellationService, OrderWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedStorageTestCase;

class BuyerOrderCancellationTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite memory required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
        Storage::fake('public'); Mail::fake(); Notification::fake(); Http::preventStrayRequests();
    }

    private function fixture(int $sellers = 2): array
    {
        $buyer = User::factory()->create(['role'=>'buyer','status'=>'active','is_suspended'=>false]);
        $category = Category::create(['name'=>'Cancellation fixtures','slug'=>'cancel-fixtures','is_active'=>true]);
        $variants = collect();
        for ($i=0; $i<$sellers; $i++) {
            $seller = Seller::create(['user_id'=>User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false])->id,'name'=>'Shop '.$i,'slug'=>'cancel-shop-'.$i,'status'=>'approved']);
            $product = Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Product '.$i,'slug'=>'cancel-product-'.$i,'is_active'=>true,'moderation_status'=>'APPROVED']);
            $variant = ProductVariant::create(['product_id'=>$product->id,'sku'=>'CANCEL-'.$i,'name'=>'Flavor / Pack of 6','price_minor'=>10001,'stock'=>10,'is_active'=>true]);
            $variants->push($variant);
            CartItem::create(['user_id'=>$buyer->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'product_variant_id'=>$variant->id,'price'=>100.01,'quantity'=>2]);
        }
        $order = app(CheckoutService::class)->checkout($buyer, ['recipient_name'=>'Test buyer','recipient_phone'=>'000','province'=>'Test','municipality'=>'Test','barangay'=>'Test','street_address'=>'Test'], 'cod');
        return compact('buyer','variants','order');
    }

    private function rider(): Rider
    {
        $provider=LogisticsProvider::create(['user_id'=>User::factory()->create(['role'=>'logistics'])->id,'name'=>'Test provider','slug'=>'cancel-provider','status'=>'approved']);
        return Rider::create(['user_id'=>User::factory()->create(['role'=>'rider','status'=>'active','is_suspended'=>false])->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'motorcycle','plate_no'=>'TEST-ONLY','license_number'=>'TEST-ONLY','is_active'=>true]);
    }

    public static function routes(): array { return [['buyer.order.cancel',1],['buyer.order.cancel',2],['buyer.order.cancel.legacy',2]]; }

    #[DataProvider('routes')]
    public function test_eligible_cancellation_restores_exact_variants_once_through_both_routes(string $route, int $sellers): void
    {
        $f=$this->fixture($sellers); $order=$f['order'];
        $f['variants'][0]->delete(); // Historical exact identity remains restorable.
        $money=$order->only(['subtotal','shipping_fee','discount_amount','total_amount','total_minor','payment_status']);
        $items=$order->items->toJson();
        $this->actingAs($f['buyer'])->post(route($route,$order->order_number),['cancel_reason'=>'wrong_product'])
            ->assertRedirect(route('buyer.order-details',$order->order_number))->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED',$order->fresh()->status);
        foreach($f['variants'] as $variant)$this->assertSame(10,ProductVariant::withTrashed()->findOrFail($variant->id)->stock);
        foreach($order->fresh()->sellerOrders as $so){$this->assertSame('cancelled',$so->status);$this->assertSame('cancelled',$so->shipment->status);$this->assertSame(OrderStatus::Placed,$so->workflow_status);}
        $this->assertSame($money,$order->fresh()->only(array_keys($money))); $this->assertSame($items,$order->fresh()->items->toJson());
        $notices=DB::table('notification_outbox')->count();
        $this->post(route($route,$order->order_number),['cancel_reason'=>'changed_mind'])->assertSessionHasNoErrors();
        $this->assertSame($notices,DB::table('notification_outbox')->count());
        foreach($f['variants'] as $variant)$this->assertSame(10,ProductVariant::withTrashed()->findOrFail($variant->id)->stock);
        $this->get(route('buyer.order-details',$order->order_number))->assertOk()->assertSee('Wrong product or variation')->assertSee('Cancelled on')->assertDontSee('data-cancel-order=',false);
    }

    public static function unsafeStates(): array
    {
        return array_map(fn($case)=>[$case], ['confirmed','preparing','ready','picked','sorting','out','completed','rts','shipment','pickup-assigned','delivery-assigned','accepted','provider','event','paid','prepaid','collected','collected-amount','collection-date','payment','wallet','remittance','earning','null-variant','wrong-variant','quantity','missing-shipment','timestamp','parent','proof']);
    }

    #[DataProvider('unsafeStates')]
    public function test_ineligible_mixed_or_inconsistent_records_reject_without_restoring_inventory(string $case): void
    {
        $f=$this->fixture();$order=$f['order'];$so=$order->sellerOrders->last();$shipment=$so->shipment;
        $status=match($case){'confirmed'=>'CONFIRMED','preparing'=>'PREPARING','ready'=>'READY_FOR_PICKUP','picked'=>'PICKED_UP','sorting'=>'SORTED','out'=>'OUT_FOR_DELIVERY','completed'=>'COMPLETED','rts'=>'RTS_RETURNING',default=>null};
        if($status)$so->update(['workflow_status'=>$status,'status'=>OrderStatus::from($status)->legacySellerStatus()]);
        switch($case){
            case 'shipment': $shipment->update(['workflow_status'=>'PICKED_UP']); break;
            case 'pickup-assigned': $shipment->update(['pickup_rider_id'=>$this->rider()->id]); break;
            case 'delivery-assigned': $shipment->update(['rider_id'=>$this->rider()->id]); break;
            case 'accepted': $shipment->update(['pickup_accepted_at'=>now()]); break;
            case 'provider': $rider=$this->rider();$so->update(['logistics_provider_id'=>$rider->logistics_provider_id]);break;
            case 'event': DeliveryEvent::create(['shipment_id'=>$shipment->id,'status'=>'PICKUP_ACCEPTED','attempt'=>0,'user_id'=>$f['buyer']->id,'occurred_at'=>now()]);break;
            case 'paid':$order->update(['payment_status'=>'PAID']);break;
            case 'prepaid':$order->update(['payment_method'=>'card']);break;
            case 'collected':$shipment->update(['cod_collected'=>true]);break;
            case 'collected-amount':$shipment->update(['cod_collected_minor'=>100]);break;
            case 'collection-date':$shipment->update(['cod_collected_at'=>now()]);break;
            case 'payment':Payment::create(['order_id'=>$order->id,'method'=>'cod','status'=>'PAID','amount_minor'=>100]);break;
            case 'wallet':DB::table('seller_wallet_entries')->insert(['seller_id'=>$so->seller_id,'seller_order_id'=>$so->id,'type'=>'SALE_EARNING','status'=>'HELD','amount_minor'=>100]);break;
            case 'remittance':$rider=$this->rider();DB::table('rider_cod_remittances')->insert(['shipment_id'=>$shipment->id,'seller_order_id'=>$so->id,'rider_id'=>$rider->id,'logistics_provider_id'=>$rider->logistics_provider_id,'expected_minor'=>100,'status'=>'OUTSTANDING']);break;
            case 'earning':$rider=$this->rider();DB::table('rider_shipping_fee_earnings')->insert(['shipment_id'=>$shipment->id,'seller_order_id'=>$so->id,'rider_id'=>$rider->id,'amount_minor'=>100,'earned_at'=>now()]);break;
            case 'null-variant':$order->items->last()->update(['product_variant_id'=>null]);break;
            case 'wrong-variant':$order->items->last()->update(['product_variant_id'=>$f['variants'][0]->id]);break;
            case 'quantity':$order->items->last()->update(['quantity'=>0]);break;
            case 'missing-shipment':$shipment->delete();break;
            case 'timestamp':$so->update(['confirmed_at'=>now()]);break;
            case 'parent':$order->update(['status'=>'CONFIRMED']);break;
            case 'proof':$shipment->update(['proof_path'=>'existing-proof.jpg']);break;
        }
        $this->actingAs($f['buyer'])->from(route('buyer.order-details',$order->order_number))
            ->post(route('buyer.order.cancel',$order->order_number),['cancel_reason'=>'other'])->assertSessionHasErrors('order',null,'cancellation');
        $this->assertNotSame('CANCELLED',$order->fresh()->status);
        foreach($f['variants'] as $variant)$this->assertSame(8,$variant->fresh()->stock);
        $this->assertNotNull(app(OrderCancellationService::class)->rejectionReason($order->fresh()));
    }

    public function test_actual_seller_acceptance_with_placed_sibling_blocks_both_routes_and_buyer_actions(): void
    {
        $f=$this->fixture();$order=$f['order'];$so=$order->sellerOrders->last();
        app(OrderWorkflowService::class)->transition($so,$so->seller->owner,OrderStatus::Confirmed);
        $this->assertSame('PLACED',$order->fresh()->status);
        foreach(['buyer.order.cancel','buyer.order.cancel.legacy'] as $route)$this->actingAs($f['buyer'])->post(route($route,$order->order_number),['cancel_reason'=>'other'])->assertSessionHasErrors('order',null,'cancellation');
        foreach(['buyer.my-orders','buyer.order-details'] as $route){
            $response=$this->get($route==='buyer.my-orders'?route($route):route($route,$order->order_number));
            $response->assertOk()->assertDontSee('data-cancel-order=',false)->assertSee('At least one seller has started processing');
        }
    }

    public function test_authorization_reason_validation_and_named_feedback_preserve_input(): void
    {
        $f=$this->fixture(1);$number=$f['order']->order_number;
        foreach(['buyer.order.cancel','buyer.order.cancel.legacy'] as $route){
            $this->actingAs(User::factory()->create(['role'=>'buyer','status'=>'active']))->post(route($route,$number),['cancel_reason'=>'other'])->assertNotFound();
            $this->actingAs($f['buyer'])->from(route('buyer.order-details',$number))->post(route($route,$number),['cancel_reason'=>'invalid'])->assertSessionHasErrors('cancel_reason',null,'cancellation')->assertSessionHasInput('cancel_order_reference',$number);
            $this->get(route('buyer.order-details',$number))->assertOk()->assertSee('Cancellation could not be completed.')->assertSee('data-error-reference="'.$number.'"',false);
        }
        $this->actingAs($f['order']->sellerOrders->first()->seller->owner)->post(route('buyer.order.cancel',$number),['cancel_reason'=>'other'])->assertForbidden();
        $this->assertSame(8,$f['variants'][0]->fresh()->stock);
    }

    public function test_shared_reason_dialog_and_order_number_contract_are_used_on_both_pages(): void
    {
        $f=$this->fixture();$number=$f['order']->order_number;
        foreach([route('buyer.my-orders'),route('buyer.order-details',$number)] as $url){
            $response=$this->actingAs($f['buyer'])->get($url)->assertOk()->assertSee('data-cancel-order="'.$number.'"',false)->assertSee('data-cancel-action="'.route('buyer.order.cancel',$number).'"',false)->assertSee('<dialog',false)->assertSee('Keep Order')->assertSee('name="cancel_reason"',false)->assertSee('aria-labelledby="buyerCancellationTitle"',false);
            $this->assertStringNotContainsString("confirm('Are you sure you want to cancel",$response->getContent());
            $this->assertSame(1,substr_count($response->getContent(),'id="buyerCancellationDialog"'));
            foreach(OrderCancellationService::REASONS as $value=>$label)$response->assertSee('value="'.$value.'"',false);
        }
    }

    public function test_voucher_records_and_all_historical_money_remain_unchanged(): void
    {
        $f=$this->fixture();$order=$f['order'];
        $voucher=Voucher::create(['code'=>'CANCEL-KEEP','scope'=>'SELLER','seller_id'=>$order->sellerOrders->first()->seller_id,'discount_type'=>'FIXED','discount_minor'=>100,'used_count'=>1,'is_active'=>true,'audience'=>'PUBLIC','per_buyer_limit'=>1]);
        VoucherClaim::create(['voucher_id'=>$voucher->id,'buyer_id'=>$f['buyer']->id,'claimed_at'=>now()]);
        VoucherRedemption::create(['voucher_id'=>$voucher->id,'order_id'=>$order->id,'seller_order_id'=>$order->sellerOrders->first()->id,'buyer_id'=>$f['buyer']->id,'code_snapshot'=>$voucher->code,'scope_snapshot'=>'SELLER','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>100,'discount_minor'=>100]);
        $before=[];foreach(['vouchers','voucher_claims','voucher_redemptions','order_items'] as $table)$before[$table]=DB::table($table)->orderBy('id')->get()->toJson();
        $cod=$order->sellerOrders->map(fn($so)=>$so->shipment->cod_amount_minor)->all();
        app(OrderCancellationService::class)->cancelByBuyer($order,$f['buyer'],'changed_mind');
        foreach($before as $table=>$json)$this->assertSame($json,DB::table($table)->orderBy('id')->get()->toJson());
        $this->assertSame($cod,$order->fresh()->sellerOrders->map(fn($so)=>$so->shipment->cod_amount_minor)->all());
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('seller_wallet_entries',0);
    }

    public function test_failure_after_restoration_rolls_back_the_entire_cancellation(): void
    {
        $f=$this->fixture();
        $this->mock(DomainNotificationService::class)->shouldReceive('afterCommit')->once()->andReturnUsing(function(){
            $this->assertGreaterThan(0,DB::transactionLevel());throw new \RuntimeException('Isolated simulated failure');
        });
        try {app(OrderCancellationService::class)->cancelByBuyer($f['order'],$f['buyer'],'other');$this->fail('Expected rollback');}
        catch(\RuntimeException $e){$this->assertSame('Isolated simulated failure',$e->getMessage());}
        $this->assertSame('PLACED',$f['order']->fresh()->status);
        foreach($f['variants'] as $variant)$this->assertSame(8,$variant->fresh()->stock);
        foreach($f['order']->fresh()->sellerOrders as $so){$this->assertSame('pending',$so->status);$this->assertSame('unassigned',$so->shipment->status);}
    }

    public function test_cancellation_wins_then_seller_and_pickup_operations_cannot_advance(): void
    {
        $f=$this->fixture(1);$so=$f['order']->sellerOrders->first();$rider=$this->rider();
        app(OrderCancellationService::class)->cancelByBuyer($f['order'],$f['buyer'],'other');
        foreach(['seller','pickup'] as $operation){
            try {
                if($operation==='seller')app(OrderWorkflowService::class)->transition($so,$so->seller->owner,OrderStatus::Confirmed);
                else app(OrderWorkflowService::class)->acceptPickup($so,$rider);
                $this->fail('Cancelled parcel advanced');
            } catch(ValidationException $e){$this->assertArrayHasKey('order',$e->errors());}
        }
        $this->assertSame('CANCELLED',$f['order']->fresh()->status);$this->assertSame(10,$f['variants'][0]->fresh()->stock);
    }

    public function test_legacy_seller_status_url_uses_the_same_cancellation_serialization_boundary(): void
    {
        $f=$this->fixture(1);$order=$f['order'];$so=$order->sellerOrders->first();
        $this->actingAs($so->seller->owner)->post(route('order.status.update',$order->order_number),['status'=>'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Confirmed,$so->fresh()->workflow_status);
        $this->actingAs($f['buyer'])->post(route('buyer.order.cancel',$order->order_number),['cancel_reason'=>'other'])->assertSessionHasErrors('order',null,'cancellation');
        $this->assertSame(8,$f['variants'][0]->fresh()->stock);
    }
}
