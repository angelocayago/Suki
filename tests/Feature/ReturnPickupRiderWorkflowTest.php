<?php

namespace Tests\Feature;

use App\Models\{Address,CartItem,Category,LogisticsHub,LogisticsProvider,Product,ProductVariant,ReturnRefundRequest,ReturnRefundWorkflow,Rider,RiderServiceArea,Seller,User};
use App\Services\{CheckoutService,ReturnPickupWorkflowService,ReturnRefundWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\IsolatedStorageTestCase;

class ReturnPickupRiderWorkflowTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); config(['returns.admin_review_enabled'=>true,'returns.backend_enabled'=>true]); }

    public function test_request_scoped_return_pickup_moves_forward_once_without_financial_writes(): void
    {
        $buyer=User::factory()->create(['role'=>'buyer','status'=>'active']); $sellerUser=User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller=Seller::create(['user_id'=>$sellerUser->id,'name'=>'Return seller','slug'=>'return-seller-'.uniqid(),'status'=>'approved']);
        $address=Address::create(['user_id'=>$sellerUser->id,'recipient'=>'Return seller','phone'=>'000','postal_code'=>'4009','line1'=>'Seller street','region'=>'CALABARZON','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0403400000','city'=>'Santa Cruz','city_municipality_code'=>'0403426000','barangay'=>'Duhat','barangay_code'=>'0403426005']); $seller->update(['pickup_address_id'=>$address->id]);
        $category=Category::create(['name'=>'Return '.uniqid(),'slug'=>'return-'.uniqid(),'is_active'=>true]); $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Return product','slug'=>'return-product-'.uniqid(),'is_active'=>true,'moderation_status'=>'APPROVED','commission_bps'=>1000]); $variant=ProductVariant::create(['product_id'=>$product->id,'name'=>'Default','sku'=>'RET-'.uniqid(),'price_minor'=>10000,'stock'=>5,'is_active'=>true]);
        CartItem::create(['user_id'=>$buyer->id,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>1,'price'=>100]);
        $order=app(CheckoutService::class)->checkout($buyer,['recipient_name'=>'Buyer','recipient_phone'=>'000','region'=>'CALABARZON','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0403400000','municipality'=>'Santa Cruz','city_municipality_code'=>'0403426000','barangay'=>'Duhat','barangay_code'=>'0403426005','street_address'=>'Buyer street'],'cod',[]); $so=$order->sellerOrders->first(); $item=$so->items->first();
        $logistics=User::factory()->create(['role'=>'logistics','status'=>'active']); $provider=LogisticsProvider::create(['user_id'=>$logistics->id,'name'=>'Return provider','slug'=>'return-provider-'.uniqid(),'status'=>'approved']); $hub=LogisticsHub::create(['logistics_provider_id'=>$provider->id,'name'=>'Return hub','region'=>'CALABARZON','region_code'=>'0400000000','province'=>'Laguna','province_code'=>'0403400000','city'=>'Santa Cruz','city_municipality_code'=>'0403426000','barangay'=>'Duhat','barangay_code'=>'0403426005','is_active'=>true]);
        $riderUser=User::factory()->create(['role'=>'rider','status'=>'active']); $rider=Rider::create(['user_id'=>$riderUser->id,'logistics_provider_id'=>$provider->id,'logistics_hub_id'=>$hub->id,'vehicle_type'=>'motorcycle','plate_no'=>'RET-'.uniqid(),'is_active'=>true,'availability'=>'ONLINE','approved_at'=>now(),'active_assignment_capacity'=>2]); RiderServiceArea::create(['rider_id'=>$rider->id,'logistics_provider_id'=>$provider->id,'area_key'=>'0400000000:0403426000:0403426005','region_code'=>'0400000000','city_municipality_code'=>'0403426000','barangay_code'=>'0403426005','is_active'=>true]);
        $request=ReturnRefundRequest::create(['buyer_id'=>$buyer->id,'order_id'=>$order->id,'seller_order_id'=>$so->id,'order_item_id'=>$item->id,'seller_id'=>$seller->id,'type'=>'RETURN','status'=>'RETURN_AUTHORIZED','quantity'=>1,'buyer_reason'=>'Damaged','buyer_description'=>'Damaged item']); $workflow=ReturnRefundWorkflow::create(['return_refund_request_id'=>$request->id,'return_address'=>'Seller street','logistics_provider_id'=>$provider->id,'return_hub_id'=>$hub->id,'return_reference'=>'RR-TEST-'.uniqid(),'logistics_state'=>'FOR_PICKUP']);
        $this->actingAs($buyer)->get(route('return-refunds.waybill',$request))->assertOk()->assertSee($workflow->return_reference)->assertSee('Seller Responsible');
        $otherBuyer=User::factory()->create(['role'=>'buyer','status'=>'active']); $this->actingAs($otherBuyer)->get(route('return-refunds.waybill',$request))->assertForbidden();
        $service=app(ReturnPickupWorkflowService::class); $assignment=$service->assign($request,$logistics,$provider,$rider); $this->assertSame('ASSIGNED',$assignment->status); $service->respond($assignment,$riderUser,true); try { $service->pickup($request,$riderUser,'RR-INVALID'); $this->fail('An invalid reference must be rejected.'); } catch (\Illuminate\Validation\ValidationException) {} $service->pickup($request,$riderUser,$workflow->return_reference); $this->assertSame('PICKED_UP',$workflow->fresh()->logistics_state); $service->receiveAtHub($request,$logistics,$provider,$hub,$workflow->return_reference); $service->dispatch($request,$logistics,$provider,$hub); $service->deliverToSeller($request,$riderUser); app(ReturnRefundWorkflowService::class)->receiveBySeller($request,$sellerUser); $before=$variant->fresh()->stock; app(ReturnRefundWorkflowService::class)->inspect($request,$sellerUser,'RESTOCKABLE','Sealed return'); app(ReturnRefundWorkflowService::class)->inspect($request,$sellerUser,'RESTOCKABLE','Replay');
        $this->assertSame('INSPECTED',$workflow->fresh()->logistics_state); $this->assertSame($before+1,$variant->fresh()->stock); $this->assertSame(0,\DB::table('seller_wallet_entries')->count()); $this->assertSame(0,\DB::table('return_refund_accounting')->count());
    }
}
