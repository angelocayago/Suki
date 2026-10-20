<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAvatarMiniPassTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_uses_trimmed_unicode_initials_and_fallback(): void
    {
        foreach ([
            ['buyer', ' Angelo', 'A'], ['seller', 'Seller', 'S'], ['logistics', 'Logistics', 'L'],
            ['rider', 'Rider', 'R'], ['admin', 'Admin', 'A'], ['buyer', ' Ñora', 'Ñ'], ['buyer', '   ', 'U'],
        ] as [$role, $name, $initial]) {
            $user = User::factory()->make(['role' => $role, 'name' => '', 'first_name' => $name, 'last_name' => '']);
            $this->assertStringContainsString('>'.$initial.'</span>', view('components.profile-avatar', compact('user'))->render());
        }
    }

    public function test_admin_logistics_and_rider_profiles_only_update_safe_user_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email_verified_at' => now()]);
        $logistics = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        LogisticsProvider::create(['user_id' => $logistics->id, 'name' => 'Provider', 'slug' => 'profile-provider', 'status' => 'approved']);
        $riderUser = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => User::factory()->create(['role'=>'logistics','status'=>'active'])->id, 'name'=>'Rider Provider', 'slug'=>'rider-provider', 'status'=>'approved']);
        Rider::create(['user_id'=>$riderUser->id, 'logistics_provider_id'=>$provider->id, 'vehicle_type'=>'Bike', 'plate_no'=>'P-1', 'is_active'=>true]);

        foreach ([[$admin, 'admin', '09123456781'], [$logistics, 'logistics', '09123456782'], [$riderUser, 'rider', '09123456783']] as [$user, $area, $phone]) {
            $this->actingAs($user)->patch(route($area.'.profile.update'), ['first_name'=>'Updated','last_name'=>'Person','middle_initial'=>'U','phone'=>$phone,'role'=>'buyer','status'=>'inactive','logistics_provider_id'=>999])
                ->assertRedirect();
            $user->refresh();
            $this->assertSame('Updated', $user->first_name);
            $this->assertSame($area === 'admin' ? 'admin' : ($area === 'logistics' ? 'logistics' : 'rider'), $user->role);
            $this->assertSame('active', $user->status);
        }

        $this->actingAs($logistics)->get(route('admin.profile'))->assertForbidden();
    }

    public function test_active_rider_earnings_uses_the_controller_backed_compensation_placeholder(): void
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Performance Provider', 'slug' => 'performance-provider', 'status' => 'approved']);
        Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'PERF-1', 'is_active' => true]);

        $this->actingAs($user)->get(route('rider.earnings'))
            ->assertOk()
            ->assertSee('Compensation not configured')
            ->assertDontSee('prototype delivery rate');
    }

    public function test_rider_performance_credits_only_final_delivery_rider_and_authoritative_cod(): void
    {
        $riderUser = User::factory()->create(['role'=>'rider','status'=>'active','email_verified_at'=>now()]);
        $otherUser = User::factory()->create(['role'=>'rider','status'=>'active']);
        $owner = User::factory()->create(['role'=>'logistics','status'=>'active']);
        $otherOwner = User::factory()->create(['role'=>'logistics','status'=>'active']);
        $provider = LogisticsProvider::create(['user_id'=>$owner->id,'name'=>'Performance','slug'=>'performance','status'=>'approved']);
        $otherProvider = LogisticsProvider::create(['user_id'=>$otherOwner->id,'name'=>'Other Performance','slug'=>'other-performance','status'=>'approved']);
        $rider = Rider::create(['user_id'=>$riderUser->id,'logistics_provider_id'=>$provider->id,'vehicle_type'=>'Bike','plate_no'=>'P-1','is_active'=>true]);
        $other = Rider::create(['user_id'=>$otherUser->id,'logistics_provider_id'=>$otherProvider->id,'vehicle_type'=>'Bike','plate_no'=>'P-2','is_active'=>true]);
        $buyer = User::factory()->create(['role'=>'buyer','status'=>'active']);
        $sellerUser = User::factory()->create(['role'=>'seller','status'=>'active']);
        $seller = Seller::create(['user_id'=>$sellerUser->id,'name'=>'Store','slug'=>'performance-store','status'=>'approved']);
        $order = Order::create(['order_number'=>'PERF-1','reference'=>'PERF-1','buyer_id'=>$buyer->id,'seller_id'=>$sellerUser->id,'status'=>'DELIVERED','payment_method'=>'cod','payment_status'=>'UNPAID','subtotal'=>100,'shipping_fee'=>0,'discount_amount'=>0,'total_amount'=>100,'total_minor'=>10000,'recipient_name'=>'Buyer','recipient_phone'=>'09123456789','province'=>'P','municipality'=>'M','barangay'=>'B','street_address'=>'S','shipping_address'=>[]]);
        $sellerOrder = SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'subtotal_minor'=>10000,'shipping_fee_minor'=>0,'commission_minor'=>0,'status'=>'shipped','workflow_status'=>\App\Enums\OrderStatus::Delivered]);
        Shipment::create(['seller_order_id'=>$sellerOrder->id,'logistics_provider_id'=>$provider->id,'rider_id'=>$other->id,'tracking_code'=>'PERF-1','status'=>'delivered','workflow_status'=>\App\Enums\OrderStatus::Delivered,'delivered_by'=>$rider->id,'cod_collected'=>true,'cod_collected_minor'=>12345,'fee_minor'=>0,'cod_amount_minor'=>12345,'attempts'=>0]);
        foreach (['DELIVERY_FAILED'=>\App\Enums\OrderStatus::DeliveryFailed,'RTS'=>\App\Enums\OrderStatus::RtsCompleted,'CANCELLED'=>\App\Enums\OrderStatus::Delivered] as $suffix=>$status) {
            $excludedSeller = Seller::create(['user_id'=>$sellerUser->id,'name'=>'Store '.$suffix,'slug'=>'performance-store-'.strtolower($suffix),'status'=>'approved']);
            $so = SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$excludedSeller->id,'subtotal_minor'=>1,'shipping_fee_minor'=>0,'commission_minor'=>0,'status'=>'shipped','workflow_status'=>$status]);
            Shipment::create(['seller_order_id'=>$so->id,'logistics_provider_id'=>$provider->id,'rider_id'=>$rider->id,'tracking_code'=>'PERF-'.$suffix,'status'=>'failed','workflow_status'=>$status,'delivered_by'=>$suffix==='CANCELLED' ? $rider->id : null,'cod_collected'=>false,'fee_minor'=>0,'cod_amount_minor'=>0,'attempts'=>0]);
        }
        $this->actingAs($riderUser)->get(route('rider.earnings'))
            ->assertOk()->assertSee('Compensation not configured')->assertDontSee('prototype delivery rate')
            ->assertSee('123.45')->assertSee('1');
    }
}
