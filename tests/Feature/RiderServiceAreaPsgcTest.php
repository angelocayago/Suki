<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RiderServiceAreaPsgcTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_owns_and_deactivates_verified_service_areas_without_duplicates(): void
    {
        [$providerUser, $provider, $rider, $otherProviderUser] = $this->actors();
        $this->fakePsgc();
        $payload = ['region' => '010000000', 'province' => '010000000', 'municipality' => '010000000', 'barangay' => '010000000'];

        $this->actingAs($providerUser)->post(route('logistics.riders.service-areas.store', $rider), $payload)->assertSessionHas('success');
        $this->actingAs($providerUser)->post(route('logistics.riders.service-areas.store', $rider), $payload)->assertSessionHas('success');
        $this->assertDatabaseCount('rider_service_areas', 1);
        $area = RiderServiceArea::firstOrFail();
        $this->actingAs($otherProviderUser)->post(route('logistics.riders.service-areas.store', $rider), $payload)->assertForbidden();
        $this->actingAs($rider->user)->post(route('logistics.riders.service-areas.store', $rider), $payload)->assertForbidden();
        $this->actingAs($providerUser)->delete(route('logistics.riders.service-areas.destroy', [$rider, $area]))->assertSessionHas('success');
        $this->assertFalse($area->fresh()->is_active);
    }

    public function test_pickup_and_delivery_reject_wrong_area_legacy_and_deactivated_authority(): void
    {
        [$providerUser, $provider, $rider] = $this->actors();
        [$sellerOrder, $shipment] = $this->shipment($provider, true);
        $this->area($rider, $provider, '020000000');
        $workflow = app(OrderWorkflowService::class);
        try { $workflow->assignPickupRider($sellerOrder, $provider, $providerUser, $rider); $this->fail('Wrong city was assigned.'); } catch (ValidationException $e) { $this->assertArrayHasKey('rider_id', $e->errors()); }
        $correct = $this->area($rider, $provider, '010000000');
        $workflow->assignPickupRider($sellerOrder, $provider, $providerUser, $rider);
        $this->assertSame($rider->id, $shipment->fresh()->pickup_rider_id);
        $correct->update(['is_active' => false, 'deactivated_at' => now()]);
        $shipment->update(['pickup_rider_id' => null]);
        try { $workflow->assignPickupRider($sellerOrder, $provider, $providerUser, $rider); $this->fail('Deactivated area was accepted.'); } catch (ValidationException $e) { $this->assertArrayHasKey('rider_id', $e->errors()); }

        $sellerOrder->seller->pickupAddress->update(['region_code' => null, 'city_municipality_code' => null, 'barangay_code' => null]);
        try { $workflow->assignPickupRider($sellerOrder, $provider, $providerUser, $rider); $this->fail('Legacy labels authorized pickup.'); } catch (ValidationException $e) { $this->assertStringContainsString('legacy address', $e->errors()['rider_id'][0]); }
    }

    public function test_assignment_page_filters_area_provider_and_unavailable_riders(): void
    {
        [$providerUser, $provider, $matching] = $this->actors();
        [$sellerOrder] = $this->shipment($provider, true);
        $this->area($matching, $provider, '010000000');
        $wrongArea = $this->rider($provider, 'wrong-area'); $this->area($wrongArea, $provider, '020000000');
        $offline = $this->rider($provider, 'offline', 'OFFLINE'); $this->area($offline, $provider, '010000000');
        [$otherUser, $otherProvider, $otherRider] = $this->actors('other'); $this->area($otherRider, $otherProvider, '010000000');

        $this->actingAs($providerUser)->get(route('logistics.assignments'))
            ->assertOk()->assertSee('value="'.$matching->id.'"', false)->assertDontSee('value="'.$wrongArea->id.'"', false)->assertDontSee('value="'.$offline->id.'"', false)->assertDontSee('value="'.$otherRider->id.'"', false);
        $this->actingAs($providerUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $wrongArea->id])->assertSessionHasErrors('rider_id');
        $this->actingAs($providerUser)->post(route('logistics.database.pickup-rider.assign', $sellerOrder), ['pickup_rider_id' => $matching->id])->assertSessionHas('success');
    }

    public function test_buyer_verified_address_edit_persists_codes_and_preserves_order_snapshot(): void
    {
        $buyer = $this->user('buyer', 'Address Buyer');
        $address = Address::create(['user_id'=>$buyer->id,'label'=>'Home','recipient'=>'Buyer','phone'=>'1','line1'=>'Old','region'=>'Old','city'=>'Old','barangay'=>'Old','province'=>'Old','postal_code'=>'1','is_default'=>true]);
        $snapshot = Order::create(['order_number'=>'SNAP-'.uniqid(),'reference'=>'SNAP-'.uniqid(),'buyer_id'=>$buyer->id,'status'=>'PLACED','payment_method'=>'cod','payment_status'=>'UNPAID','subtotal'=>1,'shipping_fee'=>0,'discount_amount'=>0,'total_amount'=>1,'total_minor'=>100,'recipient_name'=>'Buyer','recipient_phone'=>'1','province'=>'Snapshot Province','region_code'=>'010000000','province_code'=>'010000000','municipality'=>'Snapshot City','city_municipality_code'=>'010000000','barangay'=>'Snapshot Barangay','barangay_code'=>'010000000','street_address'=>'Snapshot Street','shipping_address'=>[]]);
        $this->fakePsgc();
        $payload=['name'=>'Buyer','phone'=>'2','label'=>'Work','street'=>'New Street','postal_code'=>'2','region'=>'010000000','province'=>'010000000','municipality'=>'010000000','barangay'=>'010000000'];
        $this->actingAs($buyer)->patch(route('buyer.addresses.update', $address->id), $payload)->assertSessionHas('success');
        $this->assertDatabaseHas('addresses',['id'=>$address->id,'region'=>'Region','region_code'=>'010000000','province'=>'Province','province_code'=>'010000000','city'=>'City','city_municipality_code'=>'010000000','barangay'=>'Barangay','barangay_code'=>'010000000']);
        $this->assertSame('Snapshot Street', $snapshot->fresh()->street_address);
        $this->assertSame('010000000', $snapshot->fresh()->city_municipality_code);
        $other=$this->user('buyer','Other Buyer');
        $this->actingAs($other)->patch(route('buyer.addresses.update',$address->id),$payload)->assertNotFound();

        $bad=array_merge($payload,['municipality'=>'999999999']);
        $this->actingAs($buyer)->patch(route('buyer.addresses.update',$address->id),$bad)->assertSessionHasErrors('municipality');
    }

    public function test_buyer_verified_address_edit_supports_the_existing_ncr_no_province_hierarchy(): void
    {
        $buyer=$this->user('buyer','NCR Buyer');
        $address=Address::create(['user_id'=>$buyer->id,'label'=>'Home','recipient'=>'Buyer','phone'=>'1','line1'=>'Old','region'=>'Old','city'=>'Old','barangay'=>'Old','province'=>'Old','postal_code'=>'1','is_default'=>true]);
        Http::fake(['https://psgc.cloud/api/v2/regions'=>Http::response(['data'=>[['code'=>'01','name'=>'Region A'],['code'=>'13','name'=>'NCR-style']]]),'https://psgc.cloud/api/v2/regions/13/provinces'=>Http::response(['data'=>[]]),'https://psgc.cloud/api/v2/regions/13/cities-municipalities'=>Http::response(['data'=>[['code'=>'130001','name'=>'NCR City']]]),'https://psgc.cloud/api/v2/cities-municipalities/130001/barangays'=>Http::response(['data'=>[['code'=>'130001001','name'=>'NCR Barangay']]])]);
        $this->actingAs($buyer)->patch(route('buyer.addresses.update',$address->id),['name'=>'Buyer','phone'=>'2','label'=>'Work','street'=>'New Street','postal_code'=>'2','region'=>'13','province'=>null,'municipality'=>'130001','barangay'=>'130001001'])->assertSessionHas('success');
        $this->assertDatabaseHas('addresses',['id'=>$address->id,'region'=>'NCR-style','region_code'=>'13','province'=>null,'province_code'=>null,'city'=>'NCR City','city_municipality_code'=>'130001','barangay'=>'NCR Barangay','barangay_code'=>'130001001']);
    }

    private function actors(string $suffix = ''): array
    {
        $providerUser = $this->user('logistics', 'Provider '.$suffix);
        $provider = LogisticsProvider::create(['user_id'=>$providerUser->id, 'name'=>'Provider '.$suffix, 'slug'=>'provider-'.uniqid(), 'status'=>'approved']);
        $rider = $this->rider($provider, 'rider-'.$suffix);
        $other = $this->user('logistics', 'Other '.$suffix);
        LogisticsProvider::create(['user_id'=>$other->id, 'name'=>'Other '.$suffix, 'slug'=>'other-'.uniqid(), 'status'=>'approved']);
        return [$providerUser, $provider, $rider, $other];
    }

    private function shipment(LogisticsProvider $provider, bool $ready): array
    {
        $sellerUser=$this->user('seller', 'Seller'); $buyer=$this->user('buyer', 'Buyer');
        $pickup=Address::create(['user_id'=>$sellerUser->id,'label'=>'Pickup','recipient'=>'Seller','phone'=>'1','line1'=>'Street','region'=>'Region','region_code'=>'010000000','province'=>'Province','province_code'=>'010000000','city'=>'City','city_municipality_code'=>'010000000','barangay'=>'Barangay','barangay_code'=>'010000000','is_default'=>true]);
        $seller=Seller::create(['user_id'=>$sellerUser->id,'name'=>'Seller','slug'=>'seller-'.uniqid(),'status'=>'approved','pickup_address_id'=>$pickup->id]);
        $status=$ready ? OrderStatus::ReadyForPickup : OrderStatus::AssignedToRider;
        $order=Order::create(['order_number'=>'PSGC-'.uniqid(),'reference'=>'PSGC-'.uniqid(),'buyer_id'=>$buyer->id,'seller_id'=>$sellerUser->id,'status'=>$status->value,'payment_method'=>'cod','payment_status'=>'UNPAID','subtotal'=>100,'shipping_fee'=>50,'discount_amount'=>0,'total_amount'=>150,'total_minor'=>15000,'recipient_name'=>'Buyer','recipient_phone'=>'1','province'=>'Province','region_code'=>'010000000','province_code'=>'010000000','municipality'=>'City','city_municipality_code'=>'010000000','barangay'=>'Barangay','barangay_code'=>'010000000','street_address'=>'Street','shipping_address'=>[]]);
        $sellerOrder=SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'logistics_provider_id'=>$provider->id,'subtotal_minor'=>10000,'shipping_fee_minor'=>5000,'commission_minor'=>0,'status'=>$status->legacySellerStatus(),'workflow_status'=>$status]);
        $shipment=Shipment::create(['seller_order_id'=>$sellerOrder->id,'logistics_provider_id'=>$provider->id,'tracking_code'=>'PSGC-'.uniqid(),'status'=>$status->legacyShipmentStatus(),'workflow_status'=>$status,'fee_minor'=>5000,'cod_amount_minor'=>15000,'cod_collected'=>false,'attempts'=>0]);
        return [$sellerOrder, $shipment];
    }

    private function user(string $role, string $name): User { $u=User::factory()->create(['role'=>$role,'name'=>$name,'status'=>'active','is_suspended'=>false,'email_verified_at'=>now(),'phone_verified_at'=>null]); $u->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>$role])->id]); return $u; }
    private function rider(LogisticsProvider $provider, string $name, string $availability='ONLINE'): Rider { $u=$this->user('rider', $name); return Rider::create(['user_id'=>$u->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'Bike','plate_no'=>'P-'.uniqid(),'is_active'=>true,'approved_at'=>now(),'availability'=>$availability]); }
    private function area(Rider $rider, LogisticsProvider $provider, string $city): RiderServiceArea { return RiderServiceArea::create(['rider_id'=>$rider->id,'logistics_provider_id'=>$provider->id,'region_code'=>'010000000','province_code'=>'010000000','city_municipality_code'=>$city,'barangay_code'=>null,'area_key'=>RiderServiceArea::key('010000000','010000000',$city,null),'is_active'=>true]); }
    private function fakePsgc(): void { Http::fake(['*regions'=>Http::response(['data'=>[['code'=>'010000000','name'=>'Region']]]),'*regions/010000000/provinces'=>Http::response(['data'=>[['code'=>'010000000','name'=>'Province']]]),'*provinces/010000000/cities-municipalities'=>Http::response(['data'=>[['code'=>'010000000','name'=>'City']]]),'*cities-municipalities/010000000/barangays'=>Http::response(['data'=>[['code'=>'010000000','name'=>'Barangay']]])]); }
}
