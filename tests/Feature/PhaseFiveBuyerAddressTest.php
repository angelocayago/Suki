<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveBuyerAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_addresses_are_persistent_owned_and_single_default(): void
    {
        $buyer=$this->buyer(); $other=$this->buyer();
        $payload=['name'=>'Buyer One','phone'=>'09171234567','province'=>'Laguna','municipality'=>'Santa Rosa','barangay'=>'Balibago','street'=>'One Street','postal_code'=>'4026','label'=>'Home'];
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload)->assertRedirect();
        $first=Address::where('user_id',$buyer->id)->sole();
        $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload+['label'=>'Office','is_default'=>true])->assertRedirect();
        $this->assertSame(2, Address::where('user_id',$buyer->id)->count());
        $this->assertSame(1, Address::where('user_id',$buyer->id)->where('is_default',true)->count());
        $this->actingAs($other)->post(route('buyer.addresses.default', $first->id))->assertNotFound();
        $this->assertDatabaseHas('addresses',['id'=>$first->id,'user_id'=>$buyer->id]);
    }

    private function buyer(): User
    {
        $user=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'buyer'])->id]);
        return $user;
    }
}
