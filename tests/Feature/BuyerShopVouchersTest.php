<?php

namespace Tests\Feature;

use App\Models\{BuyerStoreFollow, CartItem, Category, Product, ProductVariant, Seller, User, Voucher, VoucherClaim, VoucherRedemption};
use App\Services\{CheckoutService, ShopVoucherService, VoucherService};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Complete memory-only fixtures; never connects to a live database. */
class BuyerShopVouchersTest extends TestCase
{
    private User $buyer;
    protected function setUp(): void
    {
        parent::setUp();
        if(config('database.default')!=='sqlite'||config('database.connections.sqlite.database')!==':memory:'||config('database.connections.sqlite.url'))throw new \RuntimeException('Memory-only fixtures required.');
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, first_name TEXT, last_name TEXT, middle_initial TEXT, email_verified_at TEXT, phone_verified_at TEXT, verification_required_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE sellers (id INTEGER PRIMARY KEY, user_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE notifications (id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT)');
        \Tests\Support\ShopVoucherSchema::create();
        foreach([1=>'buyer',2=>'buyer',11=>'seller',22=>'seller'] as $id=>$role)User::forceCreate(['id'=>$id,'name'=>'Fixture '.$id,'role'=>$role,'status'=>'active']);
        Seller::create(['id'=>101,'user_id'=>11,'name'=>'Shop A','slug'=>'shop-a','status'=>'approved']);
        Seller::create(['id'=>202,'user_id'=>22,'name'=>'Shop B','slug'=>'shop-b','status'=>'approved']);
        // IDs are guarded; set fixture IDs through the query builder.
        DB::table('sellers')->where('user_id',11)->update(['id'=>101]);
        DB::table('sellers')->where('user_id',22)->update(['id'=>202]);
        $this->buyer=User::findOrFail(1);
    }

    private function voucher(array $values=[]): Voucher
    {
        return Voucher::create(array_replace(['code'=>'SHOP-'.(Voucher::count()+1),'scope'=>'SELLER','seller_id'=>101,'audience'=>'FOLLOWERS','discount_type'=>'FIXED','discount_minor'=>1000,'minimum_spend_minor'=>0,'per_buyer_limit'=>1,'is_active'=>true],$values))->fresh();
    }
    private function follow(int $seller=101,int $buyer=1): void { BuyerStoreFollow::firstOrCreate(['buyer_id'=>$buyer,'seller_id'=>$seller]); }
    private function reject(callable $action,string $message): void
    {
        try{$action();$this->fail('Invalid voucher operation succeeded.');}catch(ValidationException $e){$this->assertStringContainsString($message,$e->getMessage());}
    }

    public function test_navigation_and_buyer_authorization_preserve_existing_links(): void
    {
        $this->get(route('buyer.vouchers.index'))->assertRedirect(route('login'));
        $page=$this->actingAs($this->buyer)->get(route('buyer.vouchers.index'))->assertOk();
        $this->assertGreaterThanOrEqual(2,substr_count($page->getContent(),'href="'.route('buyer.vouchers.index').'"'));
        foreach(['buyer.account','buyer.my-orders','buyer.wishlist','buyer.cart'] as $route)$page->assertSee(route($route),false);
        $page->assertSee('Available Vouchers')->assertSee('Used / Expired')->assertSee('group-focus-within:visible',false);
        $this->assertDatabaseCount('voucher_claims',0);
        $this->actingAs(User::findOrFail(11))->get(route('buyer.vouchers.index'))->assertForbidden();
        $this->buyer->update(['is_suspended'=>true]);
        $this->actingAs($this->buyer)->get(route('buyer.vouchers.index'))->assertForbidden();
    }

    public function test_follower_discovery_claim_idempotency_and_unfollow_policy(): void
    {
        $voucher=$this->voucher(['code'=>'FOLLOW-ME']);
        $public=$this->voucher(['code'=>'PUBLIC-OFFER','audience'=>'PUBLIC','seller_id'=>202]);
        $this->actingAs($this->buyer)->get(route('buyer.vouchers.index'))->assertOk()->assertDontSee('FOLLOW-ME')->assertSee('PUBLIC-OFFER');
        $this->post(route('buyer.vouchers.claim',$voucher))->assertSessionHasErrors('voucher');
        $this->follow();
        $this->get(route('buyer.vouchers.index'))->assertSee('FOLLOW-ME');
        $this->post(route('buyer.vouchers.claim',$voucher))->assertRedirect(route('buyer.vouchers.index',['tab'=>'mine']));
        $this->post(route('buyer.vouchers.claim',$voucher))->assertRedirect();
        $this->assertDatabaseCount('voucher_claims',1);$this->assertSame(0,$voucher->fresh()->used_count);
        BuyerStoreFollow::where('buyer_id',1)->delete();
        $this->get(route('buyer.vouchers.index',['tab'=>'mine']))->assertSee('FOLLOW-ME')->assertSee('Copy Code');
        $this->assertSame(1000,app(VoucherService::class)->resolve([$voucher->code],collect([101=>2000]),$this->buyer)[0]['amount']);
        $this->post(route('buyer.vouchers.claim',$public))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('voucher_claims',2);
    }

    public function test_claim_limits_expiry_inactive_and_seller_visibility_are_enforced(): void
    {
        $service=app(ShopVoucherService::class);$voucher=$this->voucher(['audience'=>'PUBLIC','usage_limit'=>1]);
        $service->claim($this->buyer,$voucher->id);
        $this->reject(fn()=>$service->claim(User::findOrFail(2),$voucher->id),'claims');
        foreach([['expires_at'=>now()->subMinute()],['starts_at'=>now()->addDay()],['is_active'=>false],['usage_limit'=>1,'used_count'=>1]] as $values){
            $record=$this->voucher(['audience'=>'PUBLIC']+$values);
            // used_count is intentionally not mass assignable.
            if(isset($values['used_count']))$record->forceFill(['used_count'=>$values['used_count']])->save();
            $this->reject(fn()=>$service->claim($this->buyer,$record->id),isset($values['expires_at'])?'expired':(isset($values['starts_at'])?'active yet':(isset($values['used_count'])?'usage limit':'deactivated')));
        }
        User::findOrFail(11)->update(['is_suspended'=>true]);
        $this->reject(fn()=>$service->claim($this->buyer,$this->voucher(['audience'=>'PUBLIC'])->id),'shop');
    }

    public function test_claim_and_redemption_limits_are_distinct_and_history_is_real(): void
    {
        $voucher=$this->voucher(['audience'=>'PUBLIC']);app(ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $this->assertDatabaseCount('voucher_redemptions',0);
        VoucherRedemption::create(['voucher_id'=>$voucher->id,'order_id'=>90,'buyer_id'=>1,'code_snapshot'=>$voucher->code,'scope_snapshot'=>'SELLER','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);
        $this->reject(fn()=>app(VoucherService::class)->resolve([$voucher->code],collect([101=>3000]),$this->buyer),'usage limit');
        $this->actingAs($this->buyer)->get(route('buyer.vouchers.index',['tab'=>'mine']))->assertDontSee('value="'.$voucher->code.'"',false);
        $this->get(route('buyer.vouchers.index',['tab'=>'history']))->assertSee($voucher->code)->assertSee('Used 1 time');
        $expired=$this->voucher(['audience'=>'PUBLIC']);app(ShopVoucherService::class)->claim($this->buyer,$expired->id);$expired->update(['expires_at'=>now()->subMinute()]);
        $this->get(route('buyer.vouchers.index',['tab'=>'history']))->assertSee($expired->code)->assertSee('Expired');
        $this->actingAs(User::findOrFail(2))->get(route('buyer.vouchers.index',['tab'=>'history']))->assertDontSee('value="'.$voucher->code.'"',false);
    }

    public function test_canonical_discounts_require_claims_and_isolate_shop_minimum_and_cap(): void
    {
        $voucher=$this->voucher(['audience'=>'PUBLIC','discount_type'=>'PERCENTAGE','discount_bps'=>5000,'max_discount_minor'=>2000,'minimum_spend_minor'=>50000]);
        $service=app(VoucherService::class);
        $this->reject(fn()=>$service->resolve([$voucher->code],collect([101=>60000,202=>40000]),$this->buyer),'Claim');
        app(ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $result=$service->resolve([$voucher->code],collect([101=>60000,202=>40000]),$this->buyer);
        $this->assertSame(2000,$result[0]['amount']);$this->assertSame([101=>2000],$result[0]['allocation']);
        $this->reject(fn()=>$service->resolve([$voucher->code],collect([202=>100000]),$this->buyer),'Shop A');
        $this->reject(fn()=>$service->resolve([$voucher->code],collect([101=>40000,202=>60000]),$this->buyer),'eligible merchandise');
        $this->reject(fn()=>$service->resolve([$voucher->code],collect([101=>60000]),User::findOrFail(2)),'Claim');
        $legacy=$this->voucher(['audience'=>null,'per_buyer_limit'=>null,'minimum_spend_minor'=>0,'discount_minor'=>999999]);
        $this->assertSame([101=>60000],$service->resolve([$legacy->code],collect([101=>60000,202=>40000]))[0]['allocation']);
        $stack=$service->resolve([$voucher->code,$legacy->code],collect([101=>60000,202=>40000]),$this->buyer);
        $this->assertSame(60000,collect($stack)->sum('amount'));
    }

    public function test_follower_notifications_are_outbox_backed_scheduled_and_not_duplicated(): void
    {
        $this->follow();$voucher=$this->voucher(['starts_at'=>now()->addHour()]);$service=app(ShopVoucherService::class);
        $service->notifyFollowers($voucher);$this->assertDatabaseCount('notification_outbox',0);
        $this->travel(2)->hours();
        $service->notifyDue();$service->notifyDue();$service->notifyFollowers($voucher);
        $this->assertDatabaseCount('notification_outbox',1);$this->assertDatabaseCount('notifications',1);$this->assertDatabaseCount('voucher_claims',0);
        $context=json_decode(DB::table('notification_outbox')->value('context'),true);
        $this->assertSame(route('buyer.vouchers.index'),$context['url']);
        $this->actingAs($this->buyer)->get(route('notifications.page'))->assertOk()->assertSee('View shop vouchers');
        $this->travelBack();
    }

    private function checkoutFixtures(): void
    {
        foreach([\App\Models\Order::class,\App\Models\OrderItem::class,\App\Models\SellerOrder::class,\App\Models\Shipment::class,CartItem::class,\App\Models\Address::class,Product::class,ProductVariant::class,\App\Models\ProductImage::class,Category::class] as $class){
            $model=new $class();$table=$model->getTable();
            DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
            foreach(array_unique([...$model->getFillable(),'created_at','updated_at',...(in_array($class,[Product::class,ProductVariant::class])?['deleted_at']:[])]) as $column){
                $type=str_ends_with($column,'_id')||str_ends_with($column,'_minor')||in_array($column,['quantity','selected','stock','is_active','is_default'],true)?'INTEGER':'TEXT';
                DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);
            }
        }
        DB::table('transaction_reviews')->count();
    }

    public function test_checkout_revalidates_claims_and_keeps_redemptions_atomic_and_shop_scoped(): void
    {
        DB::statement('CREATE TABLE transaction_reviews (id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT)');
        $this->checkoutFixtures();
        $category=Category::create(['name'=>'Everyday','slug'=>'everyday','is_active'=>true]);
        foreach([101=>60000,202=>40000] as $seller=>$price){
            $product=Product::create(['seller_id'=>$seller,'category_id'=>$category->id,'name'=>'Product '.$seller,'slug'=>'item-'.$seller,'description'=>'Fixture','is_active'=>true,'moderation_status'=>'APPROVED']);
            $variant=$product->variants()->create(['sku'=>'SKU-'.$seller,'name'=>'Flavor','options'=>['Flavor'=>'Custom'],'price_minor'=>$price,'stock'=>5,'is_active'=>true]);
            CartItem::create(['user_id'=>1,'product_variant_id'=>$variant->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'price'=>0.01,'quantity'=>1,'selected'=>true]);
        }
        $voucher=$this->voucher(['audience'=>'PUBLIC','discount_type'=>'PERCENTAGE','discount_bps'=>5000,'max_discount_minor'=>2000,'minimum_spend_minor'=>50000,'usage_limit'=>1]);
        app(ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $this->actingAs($this->buyer)->get(route('buyer.checkout'))->assertOk()->assertSee('Eligible claimed vouchers')->assertSee($voucher->code)->assertSee('form="checkoutVoucherApply"',false);
        $this->from(route('buyer.checkout'))->post(route('buyer.cart.vouchers.apply'),['voucher_code'=>$voucher->code])->assertSessionHasNoErrors()->assertSessionHas('checkout_voucher_codes',[$voucher->code]);
        $this->get(route('buyer.checkout'))->assertSee('Voucher '.$voucher->code);
        $this->delete(route('buyer.cart.vouchers.remove',$voucher->code))->assertSessionHas('checkout_voucher_codes',[]);
        $checkout=app(CheckoutService::class);$address=['recipient_name'=>'Buyer','recipient_phone'=>'0912','province'=>'P','municipality'=>'C','barangay'=>'B','street_address'=>'Street'];
        $voucher->update(['is_active'=>false]);
        $this->reject(fn()=>$checkout->checkout($this->buyer,$address,'cod',[$voucher->code]),'deactivated');
        $this->assertDatabaseCount('orders',0);$this->assertDatabaseCount('voucher_redemptions',0);$this->assertSame(0,$voucher->fresh()->used_count);
        $voucher->update(['is_active'=>true]);
        $order=$checkout->checkout($this->buyer,$address,'cod',[$voucher->code]);
        $this->assertSame(108000,$order->total_minor);$this->assertSame(1,$voucher->fresh()->used_count);
        $redemption=VoucherRedemption::sole();$this->assertSame(1,$redemption->buyer_id);$this->assertSame(101,$redemption->seller_id_snapshot);$this->assertSame(2000,$redemption->discount_minor);
        $this->assertSame([101=>63000,202=>45000],$order->sellerOrders->mapWithKeys(fn($sellerOrder)=>[$sellerOrder->seller_id=>$sellerOrder->shipment->cod_amount_minor])->all());
        $this->assertSame([4,4],ProductVariant::orderBy('id')->pluck('stock')->all());
        $this->reject(fn()=>$checkout->checkout($this->buyer,$address,'cod',[$voucher->code]),'cart is empty');
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('voucher_redemptions',1);
    }

    public function test_seller_can_publish_audiences_caps_and_limits_without_forging_ownership(): void
    {
        $payload=['code'=>'NEW-SHOP','name'=>'Lunch offer','description'=>'Custom shop discount','discount_type'=>'PERCENTAGE','discount_value'=>'15','max_discount_cap'=>'150.50','minimum_spend'=>'500','per_buyer_limit'=>2,'usage_limit'=>10,'is_active'=>1];
        $this->actingAs(User::findOrFail(11))->post(route('seller.vouchers.store'),$payload)->assertSessionHasNoErrors();
        $voucher=Voucher::sole();$this->assertSame('FOLLOWERS',$voucher->audience);$this->assertSame(101,$voucher->seller_id);
        $this->assertSame(15050,$voucher->max_discount_minor);$this->assertSame(50000,$voucher->minimum_spend_minor);$this->assertSame(2,$voucher->per_buyer_limit);
        $this->get(route('seller.vouchers.edit',$voucher))->assertSee('Followers Only')->assertSee('Public Shop Voucher')->assertSee('Lunch offer');
        $this->patch(route('seller.vouchers.update',$voucher),$payload+['audience'=>'PUBLIC'])->assertSessionHasNoErrors();
        $this->assertSame('PUBLIC',$voucher->fresh()->audience);
        $this->actingAs(User::findOrFail(22))->patch(route('seller.vouchers.update',$voucher),$payload)->assertNotFound();
        $this->actingAs(User::findOrFail(11))->post(route('seller.vouchers.store'),array_replace($payload,['code'=>'BAD','audience'=>'ANYONE']))->assertSessionHasErrors('audience');
        $this->post(route('seller.vouchers.store'),array_replace($payload,['code'=>'BAD','per_buyer_limit'=>0]))->assertSessionHasErrors('per_buyer_limit');
        $this->post(route('seller.vouchers.store'),array_replace($payload,['code'=>'BAD','max_discount_cap'=>'0']))->assertSessionHasErrors('max_discount_cap');
        $this->assertDatabaseCount('vouchers',1);
    }

    public function test_per_buyer_repeat_uses_and_revocation_preserve_claim_and_redemption_history(): void
    {
        $voucher=$this->voucher(['audience'=>'PUBLIC','per_buyer_limit'=>2]);app(ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        VoucherRedemption::create(['voucher_id'=>$voucher->id,'order_id'=>1,'buyer_id'=>1,'code_snapshot'=>$voucher->code,'scope_snapshot'=>'SELLER','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);
        $this->assertCount(1,app(ShopVoucherService::class)->claimedForCheckout($this->buyer,collect([101=>10000])));
        $this->actingAs($this->buyer)->get(route('buyer.vouchers.index',['tab'=>'mine']))->assertSee('value="'.$voucher->code.'"',false);
        $voucher->update(['is_active'=>false]);
        $this->reject(fn()=>app(VoucherService::class)->resolve([$voucher->code],collect([101=>10000]),$this->buyer),'deactivated');
        $this->get(route('buyer.vouchers.index',['tab'=>'history']))->assertSee($voucher->code);
        $this->assertDatabaseCount('voucher_claims',1);$this->assertDatabaseCount('voucher_redemptions',1);
    }

    public function test_schema_addition_is_nullable_and_preserves_legacy_rows_in_memory(): void
    {
        $legacy=$this->voucher(['audience'=>null]);
        \Illuminate\Support\Facades\Schema::drop('voucher_claims');
        \Illuminate\Support\Facades\Schema::table('vouchers',fn($table)=>$table->dropColumn(['name','description','audience','max_discount_minor','per_buyer_limit','followers_notified_at']));
        $migration=require database_path('migrations/2026_11_03_000002_add_shop_voucher_claims_and_audience.php');
        $migration->up();
        $this->assertNull($legacy->fresh()->audience);$this->assertSame(1000,$legacy->fresh()->discount_minor);
        app(VoucherService::class)->resolve([$legacy->code],collect([101=>2000]));
        $migration->down();$this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('voucher_claims'));
        $this->assertSame(1,DB::table('vouchers')->count());$this->assertSame(1000,DB::table('vouchers')->value('discount_minor'));
    }

    public function test_legacy_code_only_vouchers_can_enforce_an_explicit_per_buyer_limit_without_claims(): void
    {
        $voucher=$this->voucher(['audience'=>null,'per_buyer_limit'=>1]);
        $this->assertCount(1,app(VoucherService::class)->resolve([$voucher->code],collect([101=>2000]),$this->buyer));
        $this->assertDatabaseCount('voucher_claims',0);
        VoucherRedemption::create(['voucher_id'=>$voucher->id,'order_id'=>9,'buyer_id'=>1,'code_snapshot'=>$voucher->code,'scope_snapshot'=>'SELLER','discount_type_snapshot'=>'FIXED','discount_value_snapshot'=>1000,'discount_minor'=>1000]);
        $this->reject(fn()=>app(VoucherService::class)->resolve([$voucher->code],collect([101=>2000]),$this->buyer),'usage limit');
    }

    public function test_public_storefront_only_exposes_active_public_vouchers(): void
    {
        DB::statement('CREATE TABLE transaction_reviews (id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT)');
        $this->checkoutFixtures();
        $this->voucher(['code'=>'PUBLIC-STORE','audience'=>'PUBLIC']);
        $this->voucher(['code'=>'FOLLOWER-PRIVATE','audience'=>'FOLLOWERS']);
        $this->voucher(['code'=>'EXPIRED-STORE','audience'=>'PUBLIC','expires_at'=>now()->subMinute()]);
        $this->get(route('buyer.stores.show','shop-a'))->assertOk()->assertSee('PUBLIC-STORE')->assertDontSee('FOLLOWER-PRIVATE')->assertDontSee('EXPIRED-STORE')->assertSee(route('buyer.vouchers.index'),false);
    }
}
