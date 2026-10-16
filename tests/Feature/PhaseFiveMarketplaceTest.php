<?php

namespace Tests\Feature;

use App\Models\BuyerStoreFollow;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_follow_active_store_once_and_cannot_unfollow_for_another_buyer(): void
    {
        $seller = $this->seller();
        $buyer = $this->user('buyer');
        $other = $this->user('buyer');
        $this->actingAs($buyer)->post(route('buyer.stores.follow', $seller))->assertRedirect();
        $this->actingAs($buyer)->post(route('buyer.stores.follow', $seller))->assertRedirect();
        $this->assertDatabaseCount('buyer_store_follows', 1);
        $this->actingAs($other)->delete(route('buyer.stores.unfollow', $seller))->assertRedirect();
        $this->assertDatabaseCount('buyer_store_follows', 1);
        $this->actingAs($buyer)->delete(route('buyer.stores.unfollow', $seller))->assertRedirect();
        $this->assertDatabaseCount('buyer_store_follows', 0);
    }

    public function test_suspended_store_is_not_public_or_followable(): void
    {
        $seller = $this->seller();
        $seller->owner->update(['is_suspended'=>true]);
        $buyer = $this->user('buyer');
        $this->get(route('buyer.stores.show', $seller))->assertNotFound();
        $this->actingAs($buyer)->post(route('buyer.stores.follow', $seller))->assertNotFound();
    }

    public function test_public_store_only_shows_approved_active_in_stock_products(): void
    {
        $seller = $this->seller(); $category = Category::firstOrCreate(['slug'=>'market'], ['name'=>'Market', 'is_active'=>true]);
        $visible = Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Visible','slug'=>'visible-'.$seller->id,'is_active'=>true,'moderation_status'=>'APPROVED']);
        ProductVariant::create(['product_id'=>$visible->id,'sku'=>'VIS-'.$seller->id,'name'=>'Default','price_minor'=>1000,'stock'=>2,'is_active'=>true]);
        $hidden = Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Hidden','slug'=>'hidden-'.$seller->id,'is_active'=>true,'moderation_status'=>'PENDING']);
        ProductVariant::create(['product_id'=>$hidden->id,'sku'=>'HID-'.$seller->id,'name'=>'Default','price_minor'=>1000,'stock'=>2,'is_active'=>true]);
        $this->get(route('buyer.stores.show', $seller))->assertOk()->assertSee('Visible')->assertDontSee('Hidden');
    }

    private function seller(): Seller
    {
        $owner=$this->user('seller');
        return Seller::create(['user_id'=>$owner->id,'name'=>'Store '.$owner->id,'slug'=>'store-'.$owner->id,'status'=>'approved']);
    }

    private function user(string $role): User
    {
        $user=User::factory()->create(['role'=>$role,'status'=>'active']);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>$role])->id]);
        return $user;
    }
}
