<?php
namespace Tests\Feature;
use App\Models\{Address,CartItem,Category,Product,ProductVariant,Role,Seller,User,Voucher}; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class PhaseFourVoucherTest extends TestCase {use RefreshDatabase, \Tests\Support\FakesCheckoutLocations;
 protected function setUp(): void {parent::setUp();$this->fakeCheckoutLocations();}
    #[\PHPUnit\Framework\Attributes\DataProvider('overlappingDiscounts')]
    public function test_overlapping_discounts_reconcile_preview_cod_items_and_funding(array $specs, int $expectedDiscount): void
    {
        $buyer = $this->user('buyer');
        $catalog = $this->item('overlap');
        $catalog['product']->update(['commission_bps' => 1000]);
        $catalog['variant']->update(['price_minor' => 10001]);
        $second = ProductVariant::create(['product_id' => $catalog['product']->id, 'sku' => 'SECOND', 'name' => 'Second', 'price_minor' => 5002, 'stock' => 5, 'is_active' => true]);
        $this->cart($buyer, $catalog);
        CartItem::create(['user_id' => $buyer->id, 'product_slug' => $catalog['product']->slug, 'product_name' => 'Second', 'product_variant_id' => $second->id, 'price' => 1, 'quantity' => 1]);
        $vouchers = [];
        foreach ($specs as $index => [$scope, $type, $value]) {
            $vouchers[] = Voucher::create(['code' => 'STACK'.$index, 'scope' => $scope, 'seller_id' => $scope === 'SELLER' ? $catalog['seller']->id : null, 'discount_type' => $type, 'discount_minor' => $type === 'FIXED' ? $value : 0, 'discount_bps' => $type === 'PERCENTAGE' ? $value : 0, 'minimum_spend_minor' => 15003, 'is_active' => true]);
        }
        $codes = array_map(fn ($voucher) => $voucher->code, $vouchers);
        $checkout = app(\App\Services\CheckoutService::class);
        $summary = $checkout->cartSummary($buyer, $codes);
        $this->assertNull($summary['voucher_error']);
        $this->assertSame($expectedDiscount / 100, $summary['discount']);
        $this->assertSame($summary['applied_vouchers'], $checkout->cartSummary($buyer, array_reverse($codes))['applied_vouchers']);
        $this->actingAs($buyer)->withSession(['checkout_voucher_codes' => $codes]);
        $this->get(route('buyer.cart'))->assertOk()->assertViewHas('discount', $summary['discount'])->assertViewHas('total', $summary['total']);
        $this->get(route('buyer.checkout'))->assertOk()->assertViewHas('discount', $summary['discount'])->assertViewHas('total', $summary['total']);
        $this->post(route('buyer.checkout.place-order'), ['address_id' => $this->address($buyer)->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = $buyer->buyerOrders()->with('items', 'sellerOrders.shipment', 'voucherRedemptions')->sole();
        $this->assertSame(20003 - $expectedDiscount, $order->total_minor);
        $this->assertGreaterThanOrEqual(0, $order->total_minor);
        $this->assertSame($order->total_minor, $order->sellerOrders->sum('shipment.cod_amount_minor'));
        $this->assertSame($expectedDiscount, (int) $order->voucherRedemptions->sum('discount_minor'));
        $sellerFunded = (int) $order->voucherRedemptions->where('scope_snapshot', 'SELLER')->sum('discount_minor');
        $platformFunded = $expectedDiscount - $sellerFunded;
        $this->assertSame($sellerFunded, (int) $order->items->sum('seller_funded_discount_minor'));
        $this->assertSame($platformFunded, (int) $order->items->sum('platform_funded_discount_minor'));
        foreach ($order->items as $item) {
            $gross = $item->unit_price_minor * $item->quantity;
            $this->assertLessThanOrEqual($gross, $item->seller_funded_discount_minor + $item->platform_funded_discount_minor);
            $this->assertSame(intdiv($gross * 1000 + 5000, 10000), $item->commission_minor);
            $refund = app(\App\Services\ReturnRefundAllocationService::class)->calculate($item, $item->quantity);
            $this->assertSame($gross - $item->seller_funded_discount_minor - $item->platform_funded_discount_minor, $refund['buyer_refund_due_minor']);
        }
        foreach ($vouchers as $voucher) {
            $redeemed = $order->voucherRedemptions->where('voucher_id', $voucher->id)->sum('discount_minor');
            $this->assertSame($redeemed > 0 ? 1 : 0, $voucher->fresh()->used_count);
        }
    }

    public static function overlappingDiscounts(): array
    {
        return [
            'single fixed' => [[['SELLER', 'FIXED', 1000]], 1000],
            'two oversized fixed' => [[['SELLER', 'FIXED', 10000], ['PLATFORM', 'FIXED', 10000]], 15003],
            'seller then percentage' => [[['SELLER', 'FIXED', 10000], ['PLATFORM', 'PERCENTAGE', 5000]], 15003],
            'percentage then fixed' => [[['PLATFORM', 'PERCENTAGE', 3333], ['SELLER', 'FIXED', 2000]], 7000],
            'combined funding fits but overlaps first item' => [[['SELLER', 'FIXED', 8000], ['PLATFORM', 'FIXED', 6000]], 14000],
            'fully covered extra code' => [[['SELLER', 'FIXED', 20000], ['PLATFORM', 'FIXED', 1000]], 15003],
        ];
    }

    public function test_disjoint_store_vouchers_and_platform_capacity_reconcile_per_seller(): void
    {
        $buyer = $this->user('buyer');
        $first = $this->item('a');
        $second = $this->item('b');
        $this->cart($buyer, $first);
        $this->cart($buyer, $second);
        $a = Voucher::create(['code' => 'ONLYA', 'scope' => 'SELLER', 'seller_id' => $first['seller']->id, 'discount_type' => 'FIXED', 'discount_minor' => 60000, 'is_active' => true]);
        $b = Voucher::create(['code' => 'ONLYB', 'scope' => 'SELLER', 'seller_id' => $second['seller']->id, 'discount_type' => 'FIXED', 'discount_minor' => 10000, 'is_active' => true]);
        $service = app(\App\Services\VoucherService::class);
        $subtotals = collect([$first['seller']->id => 50000, $second['seller']->id => 30000]);
        $preview = $service->preview(['ONLYB', 'ONLYA'], $subtotals);
        $this->assertNull($preview['error']);
        $this->assertSame(60000, collect($preview['redemptions'])->sum('amount'));
        $this->assertSame([$first['seller']->id => 50000], $preview['redemptions'][0]['allocation']);
        Voucher::create(['code' => 'PLATFORM', 'scope' => 'PLATFORM', 'discount_type' => 'FIXED', 'discount_minor' => 99999, 'is_active' => true]);
        $codes = ['PLATFORM', 'ONLYA', 'ONLYB'];
        $summary = app(\App\Services\CheckoutService::class)->cartSummary($buyer, $codes);
        $this->assertSame(800, $summary['discount']);
        $this->actingAs($buyer)->withSession(['checkout_voucher_codes' => $codes])->post(route('buyer.checkout.place-order'), ['address_id' => $this->address($buyer)->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $order = $buyer->buyerOrders()->with('sellerOrders.shipment', 'voucherRedemptions')->sole();
        $this->assertSame(10000, $order->total_minor);
        foreach ($order->sellerOrders as $sellerOrder) {
            $this->assertSame(5000, $sellerOrder->shipment->cod_amount_minor);
            $this->assertSame($sellerOrder->subtotal_minor, $order->voucherRedemptions->where('seller_order_id', $sellerOrder->id)->sum('discount_minor'));
        }
    }

    public function test_expired_and_exhausted_codes_reject_checkout_even_after_another_code_covers_the_cart(): void
    {
        $buyer = $this->user('buyer');
        $catalog = $this->item('a');
        $this->cart($buyer, $catalog);
        Voucher::create(['code' => 'FULL', 'scope' => 'PLATFORM', 'discount_type' => 'FIXED', 'discount_minor' => 99999, 'is_active' => true]);
        foreach ([['code' => 'EXPIRED', 'expires_at' => now()->subDay()], ['code' => 'USED', 'usage_limit' => 1]] as $attributes) {
            $voucher = Voucher::create($attributes + ['scope' => 'PLATFORM', 'discount_type' => 'FIXED', 'discount_minor' => 1000, 'is_active' => true]);
            if ($voucher->code === 'USED') { $voucher->used_count = 1; $voucher->save(); }
            $codes = ['FULL', $voucher->code];
            $this->assertNotNull(app(\App\Services\CheckoutService::class)->cartSummary($buyer, $codes)['voucher_error']);
            $this->actingAs($buyer)->withSession(['checkout_voucher_codes' => $codes])->post(route('buyer.checkout.place-order'), ['address_id' => $this->address($buyer)->id, 'payment_method' => 'cod'])->assertSessionHasErrors('voucher');
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(5, $catalog['variant']->fresh()->stock);
        }
    }

 public function test_platform_and_seller_vouchers_apply_only_to_merchandise_and_snapshot():void{$buyer=$this->user('buyer');$a=$this->item('a',50000);$b=$this->item('b',30000);Voucher::create(['code'=>'STORE100','scope'=>'SELLER','seller_id'=>$a['seller']->id,'discount_type'=>'FIXED','discount_minor'=>10000,'is_active'=>true]);Voucher::create(['code'=>'PLAT10','scope'=>'PLATFORM','discount_type'=>'PERCENTAGE','discount_bps'=>1000,'is_active'=>true]);$this->cart($buyer,$a);$this->cart($buyer,$b);$this->actingAs($buyer)->withSession(['checkout_voucher_codes'=>['STORE100','PLAT10']])->post(route('buyer.checkout.place-order'),['address_id'=>$this->address($buyer)->id,'payment_method'=>'cod'])->assertSessionHasNoErrors();$order=$buyer->buyerOrders()->with('sellerOrders.shipment','voucherRedemptions')->sole();$this->assertSame(72000,$order->total_minor);$this->assertSame(2,$order->voucherRedemptions->count());$this->assertSame(37000,$order->sellerOrders->firstWhere('seller_id',$a['seller']->id)->shipment->cod_amount_minor);$this->assertSame(35000,$order->sellerOrders->firstWhere('seller_id',$b['seller']->id)->shipment->cod_amount_minor);}
 public function test_invalid_future_inactive_and_wrong_store_vouchers_are_rejected():void{$buyer=$this->user('buyer');$a=$this->item('only');$other=$this->item('other');$this->cart($buyer,$a);foreach([['code'=>'OFF','scope'=>'PLATFORM','is_active'=>false],['code'=>'FUTURE','scope'=>'PLATFORM','is_active'=>true,'starts_at'=>now()->addDay()],['code'=>'OTHER','scope'=>'SELLER','seller_id'=>$other['seller']->id,'is_active'=>true]] as $data){Voucher::create($data+['discount_type'=>'FIXED','discount_minor'=>100]);$this->actingAs($buyer)->withSession(['checkout_voucher_codes'=>[$data['code']]])->post(route('buyer.checkout.place-order'),['address_id'=>$this->address($buyer)->id,'payment_method'=>'cod'])->assertSessionHasErrors('voucher');}}
 private function user($role){$u=User::factory()->create(['role'=>$role,'status'=>'active']);$u->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>$role])->id]);return $u;} private function item($slug){$o=$this->user('seller');$seller=Seller::create(['user_id'=>$o->id,'name'=>$slug,'slug'=>$slug,'status'=>'approved','commission_bps'=>0]);$c=Category::firstOrCreate(['slug'=>'v'],['name'=>'V','is_active'=>true]);$product=Product::create(['seller_id'=>$seller->id,'category_id'=>$c->id,'name'=>$slug,'slug'=>uniqid($slug),'is_active'=>true,'moderation_status'=>'APPROVED']);$variant=ProductVariant::create(['product_id'=>$product->id,'sku'=>uniqid(),'name'=>'D','price_minor'=>$slug==='b'?30000:50000,'stock'=>5,'is_active'=>true]);return compact('seller','product','variant');} private function cart($u,$x){CartItem::create(['user_id'=>$u->id,'product_slug'=>$x['product']->slug,'product_name'=>$x['product']->name,'product_variant_id'=>$x['variant']->id,'price'=>1,'quantity'=>1]);} private function address($u){return Address::firstOrCreate(['user_id'=>$u->id],[...$this->checkoutLocationCodes(),'label'=>'H','recipient'=>$u->name,'phone'=>'0912','line1'=>'x','barangay'=>'x','city'=>'x','province'=>'x','postal_code'=>'1','is_default'=>true]);}}
