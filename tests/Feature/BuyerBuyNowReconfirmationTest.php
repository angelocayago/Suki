<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, Order, Product, ProductVariant, Seller, User, Voucher};
use App\Support\DirectCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage, View};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\{FakesCheckoutLocations, IsolatedStorageTestCase};

class BuyerBuyNowReconfirmationTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, FakesCheckoutLocations;

    private User $buyer;
    private Address $address;
    private Product $product;
    private ProductVariant $variant;
    private ProductVariant $otherVariant;
    private Seller $otherSeller;
    private string $savedCart;
    private array $draft;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory only.');
        parent::setUp();
        $this->assertStoragePathIsIsolated(); $this->fakeCheckoutLocations(); Storage::fake('public');
        $this->buyer = User::factory()->create(['role'=>'buyer','status'=>'active','is_suspended'=>false]);
        $category = Category::create(['name'=>'Offer fixtures','slug'=>'offer-fixtures','is_active'=>true]);
        $variants = [];
        foreach (['Original store','Other store'] as $index => $name) {
            $owner = User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false]);
            $seller = Seller::create(['user_id'=>$owner->id,'name'=>$name,'slug'=>'offer-store-'.$index,'status'=>'approved']);
            $product = Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Offer product '.$index,'slug'=>'offer-product-'.$index,'is_active'=>true,'moderation_status'=>'APPROVED']);
            $variants[] = $product->variants()->create(['sku'=>'OFFER-'.$index,'name'=>'Standard','options'=>['Flavor'=>'Original','Size'=>'Two'],'price_minor'=>9900,'stock'=>10,'is_active'=>true]);
            if ($index === 0) $this->product = $product; else $this->otherSeller = $seller;
            CartItem::create(['user_id'=>$this->buyer->id,'product_variant_id'=>$variants[$index]->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'quantity'=>1,'price'=>99]);
        }
        [$this->variant, $this->otherVariant] = $variants;
        $this->address = Address::create($this->checkoutLocationCodes() + ['user_id'=>$this->buyer->id,'recipient'=>'Synthetic buyer','phone'=>'09000000000','province'=>'Province A','city'=>'City A','barangay'=>'Barangay A','line1'=>'Fixture street','is_default'=>true]);
        $this->savedCart = DB::table('cart_items')->orderBy('id')->get()->toJson();
        $this->draft = ['ids'=>[CartItem::where('user_id',$this->buyer->id)->value('id')],'revision'=>7];
        $this->actingAs($this->buyer)->withSession(['cart_selection.'.$this->buyer->id=>$this->draft,'checkout_voucher_codes'=>['UNRELATED']]);
    }

    private function begin(): string
    {
        $this->post(route('buyer.buy-now',$this->product->slug), ['variant_id'=>$this->variant->id,'quantity'=>2])->assertRedirect();
        return array_key_last(session('buy_now_selections'));
    }

    private function review(string $token)
    {
        return $this->get(route('buyer.checkout',['buy_now'=>$token]))->assertOk();
    }

    private function place(string $token, ?string $offer, array $extra = [], string $route = 'buyer.checkout.place-order')
    {
        return $this->post(route($route), $extra + ['buy_now'=>$token,'buy_now_offer'=>$offer,'address_id'=>$this->address->id,'payment_method'=>'cod']);
    }

    private function assertCartUntouched(): void
    {
        $this->assertSame($this->savedCart, DB::table('cart_items')->orderBy('id')->get()->toJson());
        $this->assertSame($this->draft, session('cart_selection.'.$this->buyer->id));
        $this->assertSame(['UNRELATED'], session('checkout_voucher_codes'));
    }

    private function assertNoPurchase(): void
    {
        foreach (['orders','seller_orders','order_items','shipments','voucher_redemptions','notification_outbox','notifications'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame(10, $this->otherVariant->fresh()->stock);
        $this->assertCartUntouched();
    }

    private function voucher(string $token): Voucher
    {
        $voucher = Voucher::create(['code'=>'OFFER10','scope'=>'PLATFORM','discount_type'=>'FIXED','discount_minor'=>1000,'minimum_spend_minor'=>10000,'is_active'=>true]);
        $this->from(route('buyer.checkout',['buy_now'=>$token]))->post(route('buyer.cart.vouchers.apply'), ['buy_now'=>$token,'voucher_code'=>$voucher->code])->assertSessionHasNoErrors();
        return $voucher;
    }

    public function test_unchanged_review_places_once_and_replay_preserves_inventory_vouchers_notices_and_cart(): void
    {
        $token = $this->begin(); $voucher = $this->voucher($token); $page = $this->review($token);
        $offer = $page->viewData('buyNowOfferId');
        $page->assertSee('name="buy_now_offer" value="'.$offer.'"', false);
        $this->assertEquals(238, $page->viewData('total'));
        $response = $this->place($token,$offer)->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::sole();
        $this->assertSame(23800,$order->total_minor); $this->assertSame($this->variant->id,$order->items->sole()->product_variant_id);
        $this->assertSame(2,$order->items->sole()->quantity); $this->assertSame(8,$this->variant->fresh()->stock);
        $this->assertSame(1,$voucher->fresh()->used_count); $this->assertDatabaseCount('voucher_redemptions',1);
        $notices = DB::table('notification_outbox')->count(); $this->assertGreaterThan(0,$notices);
        $this->place($token,$offer)->assertRedirect($response->headers->get('Location'));
        $this->assertDatabaseCount('orders',1); $this->assertDatabaseCount('order_items',1); $this->assertDatabaseCount('seller_orders',1); $this->assertDatabaseCount('shipments',1);
        $this->assertSame(8,$this->variant->fresh()->stock); $this->assertSame(1,$voucher->fresh()->used_count);
        $this->assertDatabaseCount('voucher_redemptions',1); $this->assertSame($notices,DB::table('notification_outbox')->count()); $this->assertCartUntouched();
    }

    public static function priceChanges(): array { return [[12345,29690],[8900,22800]]; }

    #[DataProvider('priceChanges')]
    public function test_changed_price_requires_a_new_preview_and_explicit_placement(int $price, int $total): void
    {
        $token = $this->begin(); $page = $this->review($token); $oldOffer = $page->viewData('buyNowOfferId');
        $this->assertEquals(248,$page->viewData('total'));
        $this->variant->update(['price_minor'=>$price]);
        foreach (range(1,2) as $_) {
            $this->place($token,$oldOffer)->assertRedirect(route('buyer.checkout',['buy_now'=>$token]))->assertSessionHasErrors(['buy_now_offer'=>DirectCheckout::REVIEW_MESSAGE]);
            $this->assertSame($oldOffer,session('buy_now_selections.'.$token.'.offer.id')); $this->assertNoPurchase();
        }
        $refreshed = $this->review($token)->assertSee(DirectCheckout::REVIEW_MESSAGE);
        $this->assertEquals($total / 100,$refreshed->viewData('total')); $newOffer = $refreshed->viewData('buyNowOfferId');
        $this->assertNotSame($oldOffer,$newOffer); $this->assertNoPurchase();
        $this->place($token,$oldOffer)->assertSessionHasErrors('buy_now_offer');
        $this->assertSame($newOffer,session('buy_now_selections.'.$token.'.offer.id')); $this->assertNoPurchase();
        $this->place($token,$newOffer)->assertSessionHasNoErrors();
        $this->assertSame($total,Order::sole()->total_minor); $this->assertSame(8,$this->variant->fresh()->stock); $this->assertCartUntouched();
    }

    public function test_price_before_first_preview_is_current_and_expiry_is_not_extended_by_review(): void
    {
        $token = $this->begin(); $expires = session('buy_now_selections.'.$token.'.expires_at');
        $this->variant->update(['price_minor'=>12345]); $this->travel(5)->minutes();
        $page = $this->review($token); $this->assertEquals(296.9,$page->viewData('total'));
        $this->assertSame($expires,session('buy_now_selections.'.$token.'.expires_at'));
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasNoErrors(); $this->assertSame(29690,Order::sole()->total_minor);
    }

    public static function identities(): array { return [['seller'],['product'],['options'],['name'],['quantity'],['variant']]; }

    #[DataProvider('identities')]
    public function test_material_identity_changes_cannot_silently_enter_the_order(string $change): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        match ($change) {
            'seller' => $this->product->update(['seller_id'=>$this->otherSeller->id]),
            'product' => $this->variant->update(['product_id'=>$this->otherVariant->product_id]),
            'options' => $this->variant->update(['options'=>['Flavor'=>'Changed','Size'=>'Two']]),
            'name' => $this->variant->update(['name'=>'Changed option']),
            'quantity' => $this->withSession(['buy_now_selections.'.$token.'.quantity'=>3]),
            'variant' => $this->withSession(['buy_now_selections.'.$token.'.variant_id'=>$this->otherVariant->id]),
        };
        $this->place($token,$offer)->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase();
    }

    public function test_option_key_order_is_canonical_and_client_money_quantity_and_variant_are_ignored(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        $this->variant->update(['options'=>['Size'=>'Two','Flavor'=>'Original']]);
        $this->place($token,$offer,['quantity'=>999,'variant_id'=>$this->otherVariant->id,'price'=>0,'total'=>0,'buy_now_terms'=>['total_minor'=>0]])->assertSessionHasNoErrors();
        $this->assertSame(24800,Order::sole()->total_minor); $this->assertSame(2,Order::sole()->items->sole()->quantity);
        $this->assertSame($this->variant->id,Order::sole()->items->sole()->product_variant_id); $this->assertCartUntouched();
    }

    public function test_stock_changes_that_still_cover_the_quantity_do_not_freeze_inventory(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        $this->variant->update(['stock'=>9]);
        $this->place($token,$offer)->assertSessionHasNoErrors();
        $this->assertSame(7,$this->variant->fresh()->stock); $this->assertSame(24800,Order::sole()->total_minor); $this->assertCartUntouched();
    }

    public function test_voucher_rule_change_requires_review_even_when_the_payable_total_is_unchanged(): void
    {
        $token = $this->begin(); $voucher = $this->voucher($token); $page = $this->review($token);
        $this->assertEquals(238,$page->viewData('total')); $offer = $page->viewData('buyNowOfferId');
        $voucher->update(['minimum_spend_minor'=>12000]);
        $this->place($token,$offer)->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase(); $this->assertSame(0,$voucher->fresh()->used_count);
        $page = $this->review($token); $this->assertEquals(238,$page->viewData('total'));
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasNoErrors(); $this->assertSame(1,$voucher->fresh()->used_count);
    }

    public function test_changed_discount_and_compensating_price_cannot_bypass_the_terms_guard(): void
    {
        $token = $this->begin(); $voucher = $this->voucher($token); $page = $this->review($token);
        $voucher->update(['discount_minor'=>2000]); $this->variant->update(['price_minor'=>10400]);
        // The same total hides two material term changes; comparison must include both.
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase();
        $this->assertSame(0,$voucher->fresh()->used_count);
        $page = $this->review($token); $this->assertEquals(238,$page->viewData('total'));
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasNoErrors(); $this->assertSame(23800,Order::sole()->total_minor);
    }

    public function test_internal_voucher_calculation_does_not_create_a_reviewed_offer(): void
    {
        $token = $this->begin(); $this->voucher($token);
        $this->assertNull(session('buy_now_selections.'.$token.'.offer'));
        $this->place($token,null)->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase();
        $page = $this->review($token);
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasNoErrors();
    }

    public function test_voucher_posts_do_not_accept_recalculated_terms_without_a_returning_preview(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId'); $voucher = $this->voucher($token);
        $this->assertSame($offer,session('buy_now_selections.'.$token.'.offer.id'));
        $this->place($token,$offer)->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase();
        $this->assertSame(0,$voucher->fresh()->used_count);
        $page = $this->review($token);
        $this->place($token,$page->viewData('buyNowOfferId'))->assertSessionHasNoErrors(); $this->assertSame(23800,Order::sole()->total_minor);
    }

    public function test_stale_missing_forged_and_other_context_offers_cannot_replace_the_latest_offer(): void
    {
        $token = $this->begin(); $first = $this->review($token)->viewData('buyNowOfferId');
        $latest = $this->review($token)->viewData('buyNowOfferId');
        $otherToken = $this->begin(); $otherOffer = $this->review($otherToken)->viewData('buyNowOfferId');
        foreach ([$first,null,'invalid',str_repeat('0',64),$otherOffer] as $id) {
            $this->place($token,$id)->assertSessionHasErrors('buy_now_offer');
            $this->assertSame($latest,session('buy_now_selections.'.$token.'.offer.id')); $this->assertNoPurchase();
        }
        $this->place($token,$latest)->assertSessionHasNoErrors();
    }

    public function test_expired_and_foreign_buyer_contexts_cannot_place_orders(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        $this->travel(31)->minutes(); $this->place($token,$offer)->assertSessionHasErrors('buy_now'); $this->assertNoPurchase();
        $this->travelBack(); $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        $other = User::factory()->create(['role'=>'buyer','status'=>'active','is_suspended'=>false]);
        $this->actingAs($other); $this->place($token,$offer)->assertSessionHasErrors('buy_now');
        $this->actingAs($this->buyer); $this->assertNoPurchase();
    }

    public function test_legacy_submission_also_requires_the_reviewed_offer(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId');
        $this->variant->update(['price_minor'=>12345]);
        $this->place($token,$offer,[],'buyer.checkout.place-order.legacy')->assertSessionHasErrors('buy_now_offer'); $this->assertNoPurchase();
    }

    public function test_final_comparison_occurs_inside_the_order_transaction(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId'); $changed = false;
        DB::listen(function ($query) use (&$changed) {
            if (!$changed && DB::transactionLevel() > 1 && str_starts_with($query->sql,'select * from "orders"')) {
                $changed = true; DB::table('product_variants')->where('id',$this->variant->id)->update(['price_minor'=>12345]);
            }
        });
        $this->place($token,$offer)->assertSessionHasErrors('buy_now_offer'); $this->assertTrue($changed);
        $this->assertSame(9900,$this->variant->fresh()->price_minor); $this->assertNoPurchase();
    }

    public function test_failed_preview_render_does_not_record_an_offer(): void
    {
        $token = $this->begin();
        View::composer('buyer.checkout', fn () => throw new \RuntimeException('Synthetic preview render failure'));
        $this->get(route('buyer.checkout',['buy_now'=>$token]))->assertServerError();
        $this->assertNull(session('buy_now_selections.'.$token.'.offer')); $this->assertNoPurchase();
    }

    public function test_expiration_while_the_order_transaction_is_waiting_cannot_create_an_order(): void
    {
        $token = $this->begin(); $offer = $this->review($token)->viewData('buyNowOfferId'); $expired = false;
        DB::listen(function ($query) use (&$expired) {
            if (!$expired && DB::transactionLevel() > 1 && str_starts_with($query->sql,'select * from "product_variants"')) {
                $expired = true; $this->travel(31)->minutes();
            }
        });
        $this->place($token,$offer)->assertSessionHasErrors('buy_now_offer'); $this->assertTrue($expired); $this->assertNoPurchase();
        $this->travelBack();
    }

    public function test_preview_and_both_native_placement_routes_share_the_session_lock(): void
    {
        foreach (['buyer.checkout','buyer.checkout.place-order','buyer.checkout.place-order.legacy'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertSame(10,$route->locksFor()); $this->assertSame(10,$route->waitsFor());
        }
    }
}
