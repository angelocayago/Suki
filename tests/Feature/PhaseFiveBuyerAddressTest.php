<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhaseFiveBuyerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_addresses_are_persistent_owned_and_single_default(): void
    {
        $buyer=$this->buyer(); $other=$this->buyer();
        $this->fakePsgc();
        $payload=['name'=>'Buyer One','phone'=>'09171234567','region'=>'01','province'=>'0101','municipality'=>'010101','barangay'=>'010101001','street'=>'One Street','postal_code'=>'4026','label'=>'Home'];
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload)->assertRedirect();
        $first=Address::where('user_id',$buyer->id)->sole();
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload+['label'=>'Office','is_default'=>true])->assertRedirect();
        $this->assertSame(2, Address::where('user_id',$buyer->id)->count());
        $this->assertSame(1, Address::where('user_id',$buyer->id)->where('is_default',true)->count());
        $this->actingAs($other)->post(route('buyer.addresses.default', $first->id))->assertNotFound();
        $this->assertDatabaseHas('addresses',['id'=>$first->id,'user_id'=>$buyer->id]);
    }

    public function test_buyer_can_open_and_submit_the_existing_address_edit_route_for_a_legacy_address(): void
    {
        $buyer = $this->buyer(); $other = $this->buyer(); $this->fakePsgc();
        $address = Address::create([
            'user_id' => $buyer->id, 'label' => 'Home', 'recipient' => 'Legacy Buyer', 'phone' => '09171234567',
            'line1' => 'Near the Barangay Hall', 'region' => 'Region IV-A', 'province' => 'Laguna',
            'city' => 'Cavinti', 'barangay' => 'Paowin', 'postal_code' => '4013', 'is_default' => true,
        ]);
        $payload = ['name' => 'Updated Buyer', 'phone' => '09179876543', 'region' => '01', 'province' => '0101', 'municipality' => '010101', 'barangay' => '010101001', 'street' => 'Updated Street', 'postal_code' => '4026', 'label' => 'Work'];

        $this->actingAs($buyer)->get(route('buyer.addresses'))
            ->assertOk()
            ->assertSee('Edit Address')
            ->assertSee('Legacy Buyer')
            ->assertSee(route('buyer.addresses.update', $address), false)
            ->assertSee('region_code', false);

        $this->actingAs($other)->patch(route('buyer.addresses.update', $address), $payload)->assertNotFound();
        $this->actingAs($buyer)->patch(route('buyer.addresses.update', $address), $payload)
            ->assertRedirect(route('buyer.addresses'));

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id, 'recipient' => 'Updated Buyer', 'region_code' => '01', 'province_code' => '0101',
            'city_municipality_code' => '010101', 'barangay_code' => '010101001',
        ]);
    }

    private function buyer(): User
    {
        $user=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'buyer'])->id]);
        return $user;
    }

    private function fakePsgc(): void
    {
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '01', 'name' => 'Region A']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
        ]);
    }
}
