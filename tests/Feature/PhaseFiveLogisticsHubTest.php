<?php

namespace Tests\Feature;

use App\Models\LogisticsHub;
use App\Models\LogisticsProvider;
use App\Models\Address;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhaseFiveLogisticsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_creates_canonical_hub_idempotently_and_cannot_manage_another_providers_hub(): void
    {
        [$user, $provider] = $this->provider('one'); [, $other] = $this->provider('two'); $this->fakePsgc();
        $payload=['region'=>'01','province'=>'0101','municipality'=>'010101','barangay'=>'010101001'];
        $this->actingAs($user)->post(route('logistics.hubs.store'), $payload)->assertSessionHas('success');
        $this->actingAs($user)->post(route('logistics.hubs.store'), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('logistics_hubs', 1);
        $hub=LogisticsHub::sole(); $this->assertSame($provider->id,$hub->logistics_provider_id);
        $this->assertSame('Region', $hub->region);
        $this->assertSame('01', $hub->region_code);
        $this->assertSame('Province', $hub->province);
        $this->assertSame('0101', $hub->province_code);
        $this->assertSame('City', $hub->city);
        $this->assertSame('010101', $hub->city_municipality_code);
        $this->assertSame('Barangay', $hub->barangay);
        $this->assertSame('010101001', $hub->barangay_code);
        $this->actingAs($other->owner)->patch(route('logistics.hubs.toggle',$hub))->assertNotFound();
        $this->actingAs($user)->post(route('logistics.hubs.store'), ['region'=>'01','province'=>'0101','municipality'=>'999','barangay'=>'010101001'])->assertSessionHasErrors('municipality');
    }

    public function test_provider_can_assign_only_its_rider_to_its_active_hub(): void
    {
        [$user,$provider]=$this->provider('one'); [, $other]=$this->provider('two');
        $hub=LogisticsHub::create(['logistics_provider_id'=>$provider->id,'name'=>'Hub','region'=>'R','region_code'=>'01','province'=>'P','province_code'=>'0101','city'=>'C','city_municipality_code'=>'010101','barangay'=>'B','barangay_code'=>'010101001','is_active'=>true]);
        $rider=$this->rider($provider); $foreign=$this->rider($other);
        $this->actingAs($user)->patch(route('logistics.hubs.riders.assign',$rider),['logistics_hub_id'=>$hub->id])->assertSessionHas('success');
        $this->assertSame($hub->id,$rider->fresh()->logistics_hub_id);
        $this->actingAs($user)->patch(route('logistics.hubs.riders.assign',$foreign),['logistics_hub_id'=>$hub->id])->assertNotFound();
        $hub->update(['is_active'=>false]);
        $this->actingAs($user)->patch(route('logistics.hubs.riders.assign',$rider),['logistics_hub_id'=>$hub->id])->assertNotFound();
    }

    public function test_pickup_requires_matching_active_hub_in_addition_to_service_area(): void
    {
        [$actor,$provider]=$this->provider('pickup'); $rider=$this->rider($provider); [$sellerOrder,$shipment]=$this->shipment($provider, OrderStatus::ReadyForPickup); $this->area($rider,$provider,'010101001'); $workflow=app(OrderWorkflowService::class);
        $this->rejects(fn()=> $workflow->assignPickupRider($sellerOrder,$provider,$actor,$rider));
        $rider->update(['logistics_hub_id'=>$this->hub($provider,'999999999')->id]); $rider->refresh();
        $this->rejects(fn()=> $workflow->assignPickupRider($sellerOrder,$provider,$actor,$rider));
        $rider->update(['logistics_hub_id'=>$this->hub($provider,'010101001')->id]); $rider->refresh();
        $workflow->assignPickupRider($sellerOrder,$provider,$actor,$rider);
        $this->assertSame($rider->id,$shipment->fresh()->pickup_rider_id);
    }

    public function test_delivery_and_reassignment_require_matching_active_hub_in_addition_to_service_area(): void
    {
        [$actor,$provider]=$this->provider('delivery'); $rider=$this->rider($provider); [$sellerOrder,$shipment]=$this->shipment($provider, OrderStatus::AssignedToRider); $this->area($rider,$provider,'010101001'); $workflow=app(OrderWorkflowService::class);
        $this->rejects(fn()=> $workflow->reassignDeliveryRider($sellerOrder,$provider,$actor,$rider));
        $wrong=$this->hub($provider,'999999999'); $rider->update(['logistics_hub_id'=>$wrong->id]); $rider->refresh();
        $this->rejects(fn()=> $workflow->reassignDeliveryRider($sellerOrder,$provider,$actor,$rider));
        $matching=$this->hub($provider,'010101001'); $rider->update(['logistics_hub_id'=>$matching->id]); $rider->refresh();
        $workflow->reassignDeliveryRider($sellerOrder,$provider,$actor,$rider);
        $this->assertSame($rider->id,$shipment->fresh()->rider_id);
        $shipment->update(['rider_id'=>null]); $matching->update(['is_active'=>false]); $rider->refresh();
        $this->rejects(fn()=> $workflow->reassignDeliveryRider($sellerOrder,$provider,$actor,$rider));
    }

    public function test_delivery_assignment_requires_matching_active_hub_in_addition_to_service_area(): void
    {
        [$actor,$provider]=$this->provider('delivery-assignment'); $rider=$this->rider($provider); [$sellerOrder,$shipment]=$this->shipment($provider, OrderStatus::Sorted); $this->area($rider,$provider,'010101001'); $workflow=app(OrderWorkflowService::class);
        $this->rejects(fn()=> $workflow->transition($sellerOrder,$actor,OrderStatus::AssignedToRider,['rider'=>$rider]));
        $rider->update(['logistics_hub_id'=>$this->hub($provider,'999999999')->id]); $rider->refresh();
        $this->rejects(fn()=> $workflow->transition($sellerOrder,$actor,OrderStatus::AssignedToRider,['rider'=>$rider]));
        $rider->update(['logistics_hub_id'=>$this->hub($provider,'010101001')->id]); $rider->refresh();
        $workflow->transition($sellerOrder,$actor,OrderStatus::AssignedToRider,['rider'=>$rider]);
        $this->assertSame($rider->id,$shipment->fresh()->rider_id);
    }

    private function provider(string $key): array { $user=User::factory()->create(['role'=>'logistics','status'=>'active','is_suspended'=>false]);$user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'logistics'])->id]);$provider=LogisticsProvider::create(['user_id'=>$user->id,'name'=>$key,'slug'=>$key.'-'.uniqid(),'status'=>'approved']);return [$user,$provider]; }
    private function rider(LogisticsProvider $provider): Rider { $user=User::factory()->create(['role'=>'rider','status'=>'active','is_suspended'=>false]);$user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'rider'])->id]);return Rider::create(['user_id'=>$user->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'Bike','plate_no'=>'P-'.uniqid(),'is_active'=>true,'availability'=>'ONLINE','approved_at'=>now()]); }
    private function hub(LogisticsProvider $provider,string $barangay): LogisticsHub { return LogisticsHub::firstOrCreate(['logistics_provider_id'=>$provider->id,'barangay_code'=>$barangay],['name'=>'Hub '.$barangay,'region'=>'Region','region_code'=>'01','province'=>'Province','province_code'=>'0101','city'=>'City','city_municipality_code'=>'010101','barangay'=>'Barangay','is_active'=>true]); }
    private function area(Rider $rider,LogisticsProvider $provider,string $barangay): void { RiderServiceArea::create(['rider_id'=>$rider->id,'logistics_provider_id'=>$provider->id,'region_code'=>'01','province_code'=>'0101','city_municipality_code'=>'010101','barangay_code'=>$barangay,'area_key'=>RiderServiceArea::key('01','0101','010101',$barangay),'is_active'=>true]); }
    private function shipment(LogisticsProvider $provider,OrderStatus $status): array { $sellerUser=User::factory()->create(['role'=>'seller','status'=>'active']);$sellerUser->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'seller'])->id]);$buyer=User::factory()->create(['role'=>'buyer','status'=>'active']);$pickup=Address::create(['user_id'=>$sellerUser->id,'label'=>'Hub Pickup','recipient'=>'Seller','phone'=>'1','line1'=>'Street','region'=>'Region','region_code'=>'01','province'=>'Province','province_code'=>'0101','city'=>'City','city_municipality_code'=>'010101','barangay'=>'Barangay','barangay_code'=>'010101001','is_default'=>true]);$seller=Seller::create(['user_id'=>$sellerUser->id,'name'=>'Seller','slug'=>'hub-seller-'.uniqid(),'status'=>'approved','pickup_address_id'=>$pickup->id]);$order=Order::create(['order_number'=>'HUB-'.uniqid(),'reference'=>'HUB-'.uniqid(),'buyer_id'=>$buyer->id,'seller_id'=>$sellerUser->id,'status'=>$status->value,'payment_method'=>'cod','payment_status'=>'UNPAID','subtotal'=>1,'shipping_fee'=>50,'discount_amount'=>0,'total_amount'=>51,'total_minor'=>5100,'recipient_name'=>'Buyer','recipient_phone'=>'1','province'=>'Province','region_code'=>'01','province_code'=>'0101','municipality'=>'City','city_municipality_code'=>'010101','barangay'=>'Barangay','barangay_code'=>'010101001','street_address'=>'Street','shipping_address'=>[]]);$so=SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'logistics_provider_id'=>$provider->id,'subtotal_minor'=>100,'shipping_fee_minor'=>5000,'commission_minor'=>0,'status'=>$status->legacySellerStatus(),'workflow_status'=>$status]);$shipment=Shipment::create(['seller_order_id'=>$so->id,'logistics_provider_id'=>$provider->id,'tracking_code'=>'HUB-'.uniqid(),'status'=>$status->legacyShipmentStatus(),'workflow_status'=>$status,'fee_minor'=>5000,'cod_amount_minor'=>5100,'cod_collected'=>false,'attempts'=>0]);return [$so,$shipment]; }
    private function rejects(callable $callback): void { try{$callback();$this->fail('Hub eligibility should reject this rider.');}catch(\Illuminate\Validation\ValidationException $e){$this->assertNotEmpty($e->errors());} }
    private function fakePsgc(): void { Http::fake(['*regions'=>Http::response(['data'=>[['code'=>'01','name'=>'Region']]]),'*regions/01/provinces'=>Http::response(['data'=>[['code'=>'0101','name'=>'Province']]]),'*provinces/0101/cities-municipalities'=>Http::response(['data'=>[['code'=>'010101','name'=>'City']]]),'*cities-municipalities/010101/barangays'=>Http::response(['data'=>[['code'=>'010101001','name'=>'Barangay']]])]); }
}
