<?php

namespace Tests\Feature;

use App\Models\{Address,CartItem,Category,Order,OrderItem,Product,ProductVariant,SellerOrder,User,Voucher,VoucherRedemption};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerBuyNowTest extends TestCase
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
        DB::statement('CREATE TABLE return_refund_requests (id INTEGER PRIMARY KEY, order_item_id INTEGER, status TEXT)');
        DB::statement('CREATE TABLE wishlist_items (id INTEGER PRIMARY KEY, user_id INTEGER, product_slug TEXT)');
        DB::statement('CREATE TABLE transaction_reviews (id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT)');
        \Tests\Support\ShopVoucherSchema::create();
        foreach([Order::class,OrderItem::class,SellerOrder::class,\App\Models\Shipment::class,CartItem::class,Address::class,Product::class,ProductVariant::class,\App\Models\ProductImage::class,Category::class] as $class){
            $model=new $class();$table=$model->getTable();DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
            foreach(array_unique([...$model->getFillable(),'created_at','updated_at',...(in_array($class,[Product::class,ProductVariant::class])?['deleted_at']:[])]) as $column){
                $type=str_ends_with($column,'_id')||str_ends_with($column,'_minor')||in_array($column,['quantity','selected','stock','is_active','is_default'],true)?'INTEGER':'TEXT';
                DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.$type);
            }
        }
        DB::statement('CREATE UNIQUE INDEX order_number_identity ON orders(order_number)');
        foreach([1=>'buyer',2=>'buyer',11=>'seller',22=>'rider'] as $id=>$role)User::forceCreate(['id'=>$id,'name'=>'User '.$id,'role'=>$role,'status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Shop A','slug'=>'shop-a','status'=>'approved'],['id'=>202,'user_id'=>11,'name'=>'Shop B','slug'=>'shop-b','status'=>'approved']]);
        $category=Category::create(['name'=>'Everyday','slug'=>'everyday','is_active'=>true]);
        $product=Product::create(['seller_id'=>101,'category_id'=>$category->id,'name'=>'Direct Product','slug'=>'direct-product','description'=>'A real product','is_active'=>true,'moderation_status'=>'APPROVED']);
        foreach([[11,'Chicken','Single',8900,10],[12,'Beef','Six',9900,5],[13,'Tuna','Single',10900,0]] as [$id,$flavor,$pack,$price,$stock]){
            $variant=$product->variants()->create(['sku'=>'SKU-'.$id,'name'=>$flavor,'options'=>['Flavor'=>$flavor,'Pack Size'=>$pack],'price_minor'=>$price,'stock'=>$stock,'is_active'=>true,'image_path'=>'variants/'.$id.'.png']);
            DB::table('product_variants')->where('id',$variant->id)->update(['id'=>$id]);
        }
        $other=Product::create(['seller_id'=>202,'category_id'=>$category->id,'name'=>'Saved Product','slug'=>'saved-product','description'=>'Saved cart','is_active'=>true,'moderation_status'=>'APPROVED']);
        $variant=$other->variants()->create(['sku'=>'SKU-21','name'=>'Default','price_minor'=>20000,'stock'=>10,'is_active'=>true]);DB::table('product_variants')->where('id',$variant->id)->update(['id'=>21]);
        CartItem::create(['user_id'=>1,'product_variant_id'=>11,'product_slug'=>$product->slug,'product_name'=>$product->name,'price'=>1,'quantity'=>1,'selected'=>true]);
        CartItem::create(['user_id'=>1,'product_variant_id'=>21,'product_slug'=>$other->slug,'product_name'=>$other->name,'price'=>1,'quantity'=>2,'selected'=>true]);
        Address::create(['user_id'=>1,'recipient'=>'Buyer','phone'=>'09123456789','province'=>'P','city'=>'C','barangay'=>'B','line1'=>'Street','is_default'=>true]);
        Voucher::create(['code'=>'CART-B','scope'=>'SELLER','seller_id'=>202,'discount_type'=>'FIXED','discount_minor'=>100,'is_active'=>true]);
        Storage::fake('public');Storage::disk('public')->put('variants/12.png','selected image');
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit')->andReturnNull();
        $this->buyer=User::findOrFail(1);
        $this->actingAs($this->buyer)->withSession(['checkout_voucher_codes'=>['CART-B']]);
    }
    protected function tearDown(): void { $this->travelBack();parent::tearDown(); }
    private function cart(): string { return DB::table('cart_items')->orderBy('id')->get()->toJson(); }
    private function start(array $values=[]): string
    {
        $this->from(route('buyer.product','direct-product'))->post(route('buyer.buy-now','direct-product'),array_replace(['variant_id'=>12,'quantity'=>2],$values))->assertRedirect();
        return array_key_last(session('buy_now_selections'));
    }
    private function place(string $token)
    {
        return $this->from(route('buyer.checkout',['buy_now'=>$token]))->post(route('buyer.checkout.place-order'),['buy_now'=>$token,'address_id'=>Address::where('user_id',1)->value('id'),'payment_method'=>'cod']);
    }

    public function test_buy_now_uses_exact_variant_and_quantity_without_changing_cart_or_cart_vouchers(): void
    {
        $before=$this->cart();$token=$this->start(['price'=>0,'seller_id'=>202]);
        $page=$this->get(route('buyer.checkout',['buy_now'=>$token]))->assertOk()->assertSee('Buy Now Checkout')->assertSee('Shop A')->assertSee('Flavor: Beef')->assertSee('Pack Size: Six')->assertDontSee('Saved Product');
        $items=$page->viewData('cart');$this->assertCount(1,$items);$this->assertSame(12,$items[0]['variant_id']);$this->assertSame(2,$items[0]['quantity']);$this->assertSame(9900,$items[0]['price_minor']);
        $this->assertEquals(248,$page->viewData('total'));$this->assertSame(Storage::disk('public')->url('variants/12.png'),$items[0]['image']);
        $this->assertSame($before,$this->cart());$this->assertSame(['CART-B'],session('checkout_voucher_codes'));$this->assertDatabaseCount('orders',0);
    }

    public function test_order_uses_canonical_snapshots_stock_shipments_and_durable_replay_identity(): void
    {
        $before=$this->cart();$token=$this->start();$response=$this->place($token)->assertRedirect();
        $order=Order::sole();$this->assertSame(24800,$order->total_minor);$this->assertSame(1,$order->buyer_id);
        $item=OrderItem::sole();$this->assertSame(12,$item->product_variant_id);$this->assertSame(2,$item->quantity);$this->assertSame(9900,$item->unit_price_minor);$this->assertSame(['Flavor'=>'Beef','Pack Size'=>'Six'],$item->variation);
        $this->assertSame(101,$order->sellerOrders->sole()->seller_id);$this->assertSame(24800,$order->sellerOrders->sole()->shipment->cod_amount_minor);$this->assertSame(3,ProductVariant::findOrFail(12)->stock);
        $this->assertSame($before,$this->cart());$this->assertSame(['CART-B'],session('checkout_voucher_codes'));$this->assertNull(session('buy_now_selections.'.$token));
        $again=$this->place($token)->assertRedirect($response->headers->get('Location'));$this->assertDatabaseCount('orders',1);$this->assertSame(3,ProductVariant::findOrFail(12)->stock);
        $this->get(route('buyer.checkout',['buy_now'=>$token]))->assertRedirect($again->headers->get('Location'));
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number);
        $this->assertSame($before,$this->cart());
    }

    public function test_buy_now_vouchers_use_only_direct_merchandise_and_preserve_normal_cart_choices(): void
    {
        $before=$this->cart();$token=$this->start();
        $voucher=Voucher::create(['code'=>'DIRECT-A','scope'=>'SELLER','seller_id'=>101,'audience'=>'PUBLIC','per_buyer_limit'=>1,'discount_type'=>'FIXED','discount_minor'=>1000,'minimum_spend_minor'=>15000,'is_active'=>true]);
        app(\App\Services\ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $this->from(route('buyer.checkout',['buy_now'=>$token]))->post(route('buyer.cart.vouchers.apply'),['buy_now'=>$token,'voucher_code'=>'CART-B'])->assertSessionHasErrors('voucher_code');
        $this->post(route('buyer.cart.vouchers.apply'),['buy_now'=>$token,'voucher_code'=>'DIRECT-A'])->assertSessionHasNoErrors();
        $this->assertSame(['CART-B'],session('checkout_voucher_codes'));$this->assertSame(['DIRECT-A'],session('buy_now_selections.'.$token.'.voucher_codes'));
        $page=$this->get(route('buyer.checkout',['buy_now'=>$token]))->assertOk();$this->assertEquals(238,$page->viewData('total'));
        $this->delete(route('buyer.cart.vouchers.remove','DIRECT-A'),['buy_now'=>$token])->assertSessionHasNoErrors();$this->assertSame([],session('buy_now_selections.'.$token.'.voucher_codes'));
        $this->post(route('buyer.cart.vouchers.apply'),['buy_now'=>$token,'voucher_code'=>'DIRECT-A'])->assertSessionHasNoErrors();
        $this->place($token)->assertRedirect();$this->assertSame(23800,Order::sole()->total_minor);$this->assertSame(101,VoucherRedemption::sole()->seller_id_snapshot);$this->assertSame(1,$voucher->fresh()->used_count);$this->assertSame($before,$this->cart());
    }

    public function test_invalid_product_variant_quantity_and_non_buyer_requests_are_rejected(): void
    {
        foreach([['variant_id'=>'','quantity'=>1],['variant_id'=>21,'quantity'=>1],['variant_id'=>13,'quantity'=>1],['variant_id'=>12,'quantity'=>6],['variant_id'=>12,'quantity'=>0]] as $values)$this->post(route('buyer.buy-now','direct-product'),$values)->assertSessionHasErrors();
        $this->assertNull(session('buy_now_selections'));$this->assertDatabaseCount('orders',0);
        foreach([11,22] as $id)$this->actingAs(User::findOrFail($id))->post(route('buyer.buy-now','direct-product'),['variant_id'=>12,'quantity'=>1])->assertForbidden();
        $this->app['auth']->forgetGuards();$this->from(route('buyer.product','direct-product'))->post(route('buyer.buy-now','direct-product'),['variant_id'=>12,'quantity'=>1])->assertRedirect(route('login'))->assertSessionHas('url.intended',route('buyer.product','direct-product'));
    }

    public function test_stock_price_and_approval_are_revalidated_without_consuming_cart_or_usage_on_failure(): void
    {
        $before=$this->cart();$token=$this->start();ProductVariant::findOrFail(12)->update(['price_minor'=>12345]);
        $page=$this->get(route('buyer.checkout',['buy_now'=>$token]))->assertOk();$this->assertEquals(296.9,$page->viewData('total'));
        ProductVariant::findOrFail(12)->update(['stock'=>1]);$this->place($token)->assertSessionHasErrors('cart');$this->assertDatabaseCount('orders',0);$this->assertSame(1,ProductVariant::findOrFail(12)->stock);$this->assertSame($before,$this->cart());
        ProductVariant::findOrFail(12)->update(['stock'=>5]);Product::where('slug','direct-product')->update(['moderation_status'=>'PENDING']);$this->place($token)->assertSessionHasErrors('cart');$this->assertDatabaseCount('voucher_redemptions',0);$this->assertDatabaseCount('orders',0);
    }

    public function test_cancel_expiry_and_wrong_user_state_leave_saved_cart_untouched(): void
    {
        $before=$this->cart();$token=$this->start();$this->post(route('buyer.buy-now.cancel'),['buy_now'=>$token])->assertRedirect(route('buyer.product','direct-product'));$this->assertNull(session('buy_now_selections.'.$token));
        $token=$this->start();$this->travel(31)->minutes();$this->get(route('buyer.checkout',['buy_now'=>$token]))->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('buy_now');$this->travelBack();
        $token=$this->start();$this->actingAs(User::findOrFail(2))->get(route('buyer.checkout',['buy_now'=>$token]))->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('buy_now');
        $this->assertSame($before,$this->cart());$this->assertDatabaseCount('orders',0);$this->assertSame(5,ProductVariant::findOrFail(12)->stock);
    }

    public function test_normal_cart_checkout_still_contains_and_orders_only_saved_cart_lines(): void
    {
        $token=$this->start();$page=$this->get(route('buyer.checkout'))->assertOk();$this->assertCount(2,$page->viewData('cart'));$this->assertNull($page->viewData('buyNowToken'));
        $this->post(route('buyer.checkout.place-order'),['address_id'=>Address::where('user_id',1)->value('id'),'payment_method'=>'cod'])->assertRedirect();
        $this->assertSame([11,21],OrderItem::orderBy('id')->pluck('product_variant_id')->all());$this->assertDatabaseCount('cart_items',0);$this->assertNotNull(session('buy_now_selections.'.$token));$this->assertSame(5,ProductVariant::findOrFail(12)->stock);
    }

    public function test_address_payment_and_expired_voucher_failures_leave_cart_stock_and_usage_intact(): void
    {
        $before=$this->cart();$token=$this->start();
        $foreign=Address::create(['user_id'=>2,'recipient'=>'Other','phone'=>'0912','province'=>'P','city'=>'C','barangay'=>'B','line1'=>'Other street']);
        $this->post(route('buyer.checkout.place-order'),['buy_now'=>$token,'address_id'=>$foreign->id,'payment_method'=>'cod'])->assertSessionHasErrors('address_id');
        $this->post(route('buyer.checkout.place-order'),['buy_now'=>$token,'address_id'=>Address::where('user_id',1)->value('id'),'payment_method'=>'card'])->assertSessionHasErrors('payment_method');
        $voucher=Voucher::create(['code'=>'EXPIRES','scope'=>'SELLER','seller_id'=>101,'audience'=>'PUBLIC','per_buyer_limit'=>1,'discount_type'=>'FIXED','discount_minor'=>1000,'is_active'=>true,'expires_at'=>now()->addHour()]);
        app(\App\Services\ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $this->post(route('buyer.cart.vouchers.apply'),['buy_now'=>$token,'voucher_code'=>$voucher->code])->assertSessionHasNoErrors();
        $voucher->update(['expires_at'=>now()->subMinute()]);$this->place($token)->assertSessionHasErrors('voucher');
        $this->assertSame(0,$voucher->fresh()->used_count);$this->assertDatabaseCount('voucher_redemptions',0);$this->assertDatabaseCount('orders',0);$this->assertSame(5,ProductVariant::findOrFail(12)->stock);$this->assertSame($before,$this->cart());
    }

    public function test_failure_after_stock_deduction_rolls_back_and_the_same_selection_can_retry(): void
    {
        $before=$this->cart();$token=$this->start();
        $voucher=Voucher::create(['code'=>'ATOMIC','scope'=>'SELLER','seller_id'=>101,'audience'=>'PUBLIC','per_buyer_limit'=>1,'discount_type'=>'FIXED','discount_minor'=>1000,'is_active'=>true]);
        app(\App\Services\ShopVoucherService::class)->claim($this->buyer,$voucher->id);
        $this->post(route('buyer.cart.vouchers.apply'),['buy_now'=>$token,'voucher_code'=>$voucher->code])->assertSessionHasNoErrors();
        VoucherRedemption::creating(fn()=>throw new \RuntimeException('Isolated injected redemption failure'));
        try { $this->place($token)->assertRedirect(route('buyer.checkout',['buy_now'=>$token]))->assertSessionHas('error'); }
        finally { VoucherRedemption::flushEventListeners(); }
        $this->assertDatabaseCount('orders',0);$this->assertDatabaseCount('seller_orders',0);$this->assertDatabaseCount('shipments',0);$this->assertDatabaseCount('voucher_redemptions',0);
        $this->assertSame(5,ProductVariant::findOrFail(12)->stock);$this->assertSame(0,$voucher->fresh()->used_count);$this->assertSame($before,$this->cart());
        $this->place($token)->assertRedirect();$this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('voucher_redemptions',1);$this->assertSame(1,$voucher->fresh()->used_count);
    }

    public function test_marketplace_promotions_are_truthful_and_best_sellers_use_real_order_counts(): void
    {
        $home=$this->get(route('buyer.home'))->assertOk()->assertSee('Featured Products')->assertDontSee('Flash Deals')->assertDontSee('0% OFF')->assertDontSee('line-through',false);$this->assertSame([],$home->viewData('bestSellers'));
        $order=Order::create(['order_number'=>'SALE-1','buyer_id'=>1,'status'=>'PLACED']);$sellerOrder=SellerOrder::create(['order_id'=>$order->id,'seller_id'=>101,'status'=>'pending']);
        OrderItem::create(['product_id'=>Product::where('slug','direct-product')->value('id'),'seller_order_id'=>$sellerOrder->id,'quantity'=>3]);
        $home=$this->get(route('buyer.home'))->assertOk();$this->assertSame(['Direct Product'],array_column($home->viewData('bestSellers'),'name'));$this->assertSame(3,$home->viewData('bestSellers')[0]['sold']);
        $this->get(route('buyer.shop'))->assertOk()->assertDontSee('line-through',false);$this->get(route('buyer.product','direct-product'))->assertOk()->assertDontSee('Proceeding to checkout')->assertSee('formaction="'.route('buyer.buy-now','direct-product').'"',false);
        $this->get(route('landing'))->assertOk();Product::query()->delete();$this->get(route('buyer.home'))->assertOk()->assertDontSee('Minimalist Shoulder Bag')->assertSee('No products are available yet.');
    }
}
