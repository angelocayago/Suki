<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, Order, Product, ProductVariant, Seller, User, Voucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\Support\{FakesCheckoutLocations, IsolatedStorageTestCase};

class BuyerSelectiveCheckoutTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, FakesCheckoutLocations;
    private User $buyer;
    private Address $address;
    private array $rows;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite only.');
        parent::setUp(); $this->assertStoragePathIsIsolated(); $this->fakeCheckoutLocations(); Storage::fake('public');
        $this->buyer = User::factory()->create(['role'=>'buyer','status'=>'active','is_suspended'=>false]);
        $this->address = Address::create($this->checkoutLocationCodes() + ['user_id'=>$this->buyer->id,'label'=>'Home','recipient'=>'Buyer','phone'=>'09000000000','line1'=>'Fixture street','province'=>'Province A','city'=>'City A','barangay'=>'Barangay A','is_default'=>true]);
        $category = Category::create(['name'=>'Selection','slug'=>'selection','is_active'=>true]);
        $this->rows = [];
        for ($store=0;$store<2;$store++) {
            $owner=User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false]);
            $seller=Seller::create(['user_id'=>$owner->id,'name'=>'Store '.$store,'slug'=>'store-'.$store,'status'=>'approved']);
            $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'Product '.$store,'slug'=>'product-'.$store,'is_active'=>true,'moderation_status'=>'APPROVED']);
            for($option=0;$option<2;$option++) {
                $variant=$product->variants()->create(['sku'=>'SELECT-'.$store.'-'.$option,'name'=>'Option '.$option,'options'=>['Flavor'=>'Option '.$option],'price_minor'=>10000+1000*$option,'stock'=>10,'is_active'=>true]);
                $this->rows[]=CartItem::create(['user_id'=>$this->buyer->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'product_variant_id'=>$variant->id,'quantity'=>1,'price'=>1]);
            }
        }
        $this->actingAs($this->buyer);
    }

    private function choose(array $ids, bool $checkout=true)
    {
        $revision=$this->getJson(route('buyer.cart'))->assertOk()->json('data.selection.revision');
        return $this->post(route('buyer.cart.selection'),['draft_revision'=>$revision,'cart_item_ids'=>$ids,'intent'=>$checkout?'checkout':'update']);
    }

    public function test_cart_native_forms_and_server_summary_use_only_the_saved_selection(): void
    {
        $this->get(route('buyer.cart'))->assertOk()->assertSee('Select at least one item to check out.')
            ->assertSee('form="cart-selection-form"', false)->assertSee('name="cart_item_ids[]"', false)
            ->assertSee('Checkout checked items')->assertSee('Update Selection')->assertSee('data-cart-store="1"', false);
        $this->choose([$this->rows[0]->id], false)->assertRedirect(route('buyer.cart'));
        $page = $this->get(route('buyer.cart'))->assertOk();
        $this->assertSame([$this->rows[0]->id], $page->viewData('selection')['ids']);
        $this->assertEquals(150, $page->viewData('selected_summary')['total']);
        $this->getJson(route('buyer.cart'))->assertOk()->assertJsonPath('data.selected_summary.total', 150)
            ->assertJsonPath('data.selected_summary.seller_count', 1)
            ->assertJsonPath('data.selection.ids', [$this->rows[0]->id]);
        $html = $this->getJson(route('buyer.cart'))->json('data.summary_html');
        $this->assertStringContainsString('₱150.00', $html);
        $this->assertStringContainsString('1 selected item', $html);
        $this->assertStringNotContainsString('Total Shipping', $html);
    }

    public function test_native_checkout_post_freezes_checked_ids_without_javascript(): void
    {
        $token = $this->token([$this->rows[1]->id]);
        $this->place($token)->assertRedirect();
        $this->assertSame([$this->rows[1]->product_variant_id], Order::sole()->items->pluck('product_variant_id')->all());
        $this->assertDatabaseCount('cart_items', 3);
    }

    public function test_add_confirmation_identifies_units_product_variant_and_preserves_selection(): void
    {
        $this->choose([$this->rows[0]->id], false);
        $this->from(route('buyer.product', $this->rows[1]->product_slug))->post(route('buyer.cart.add', $this->rows[1]->product_slug), [
            'variant_id' => $this->rows[1]->product_variant_id, 'quantity' => 2,
        ])->assertRedirect()->assertSessionHas('success', 'Added 2 × Product 0 — Option 1 to your Cart.');
        $this->assertSame(3, $this->rows[1]->fresh()->quantity);
        $this->assertDatabaseCount('cart_items', 4);
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids', [$this->rows[0]->id]);
    }

    private function token(array $ids): string
    {
        $response=$this->choose($ids)->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY) ?? '',$query);
        $this->assertArrayHasKey('cart_selection',$query);
        return $query['cart_selection'];
    }

    private function place(string $token)
    {
        return $this->from(route('buyer.checkout',['cart_selection'=>$token]))->post(route('buyer.checkout.place-order'),
            ['cart_selection'=>$token,'address_id'=>$this->address->id,'payment_method'=>'cod','total'=>0,'price'=>0]);
    }

    public function test_none_selected_and_web_missing_context_never_falls_back_to_all(): void
    {
        $this->getJson(route('buyer.cart'))->assertOk()->assertJsonPath('data.selection.ids',[])->assertJsonPath('data.selected_summary.total',0);
        $this->choose([])->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('cart_selection');
        $this->get(route('buyer.checkout'))->assertRedirect(route('buyer.cart'));
        $this->post(route('buyer.checkout.place-order'),['address_id'=>$this->address->id,'payment_method'=>'cod'])->assertRedirect(route('buyer.cart'));
        $this->assertDatabaseCount('orders',0); $this->assertDatabaseCount('cart_items',4);
    }

    public function test_selected_only_order_shipping_exact_cleanup_and_durable_replay(): void
    {
        $token=$this->token([$this->rows[0]->id,$this->rows[2]->id]);
        $page=$this->get(route('buyer.checkout',['cart_selection'=>$token]))->assertOk();
        $this->assertSame([$this->rows[0]->id,$this->rows[2]->id],array_keys($page->viewData('cart')));
        $this->assertEquals(100,$page->viewData('shipping')); $this->assertEquals(300,$page->viewData('total'));
        $first=$this->place($token)->assertRedirect(); $order=Order::sole();
        $this->assertSame(30000,$order->total_minor); $this->assertCount(2,$order->sellerOrders);
        $this->assertSame([$this->rows[1]->id,$this->rows[3]->id],CartItem::orderBy('id')->pluck('id')->all());
        $this->assertSame([9,10,9,10],ProductVariant::orderBy('id')->pluck('stock')->all());
        $notifications=DB::table('notification_outbox')->count();
        $again=$this->place($token)->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('order_items',2);$this->assertDatabaseCount('shipments',2);
        $this->assertSame($notifications,DB::table('notification_outbox')->count());
        $this->assertSame([9,10,9,10],ProductVariant::orderBy('id')->pluck('stock')->all());
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($other)->get(route('buyer.checkout',['cart_selection'=>$token]))->assertRedirect(route('buyer.cart'));
    }

    public function test_revisions_reject_older_tabs_and_frozen_snapshot_ignores_new_draft(): void
    {
        $token=$this->token([$this->rows[0]->id]);
        $this->postJson(route('buyer.cart.selection'),['draft_revision'=>0,'cart_item_ids'=>[$this->rows[1]->id]])
            ->assertStatus(409)->assertJsonPath('code','stale_selection')->assertJsonPath('data.selection.ids',[$this->rows[0]->id]);
        $this->choose([$this->rows[2]->id],false)->assertRedirect();
        $this->place($token)->assertRedirect();
        $this->assertSame([$this->rows[0]->product_variant_id],Order::sole()->items->pluck('product_variant_id')->all());
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[$this->rows[2]->id]);
    }

    public function test_foreign_missing_duplicate_and_malformed_ids_are_rejected_without_partial_draft_updates(): void
    {
        $other=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $foreign=$this->rows[0]->replicate();$foreign->user_id=$other->id;$foreign->save();
        foreach ([[$foreign->id],[999999],[$this->rows[0]->id,$this->rows[0]->id],['abc'],[0],[-1],[1.5]] as $ids) {
            $this->postJson(route('buyer.cart.selection'),['draft_revision'=>0,'cart_item_ids'=>$ids])->assertStatus(422)->assertJsonPath('data.selection.ids',[]);
        }
        $this->assertDatabaseCount('orders',0);
    }

    public function test_unselected_invalid_sibling_does_not_block_selected_rows_and_new_invalid_selection_is_rejected(): void
    {
        $this->rows[1]->productVariant->update(['stock'=>0]);
        $this->choose([$this->rows[1]->id])->assertSessionHasErrors('cart_item_ids');
        $token=$this->token([$this->rows[0]->id]);$this->place($token)->assertRedirect();
        $this->assertDatabaseHas('cart_items',['id'=>$this->rows[1]->id]);$this->assertDatabaseCount('order_items',1);
    }

    public function test_selected_row_becoming_invalid_is_retained_and_blocks_with_its_specific_name_and_reason(): void
    {
        $token=$this->token([$this->rows[0]->id]);$this->rows[0]->productVariant->update(['stock'=>0]);
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[$this->rows[0]->id])
            ->assertJsonPath('data.selected_summary.has_unavailable_items',true);
        $this->get(route('buyer.checkout',['cart_selection'=>$token]))->assertOk()->assertSee('This item is no longer available in the marketplace.');
        $this->place($token)->assertSessionHasErrors('cart');$this->assertDatabaseCount('orders',0);
        $this->choose([] ,false)->assertRedirect();$this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[]);
    }

    public function test_quantity_price_variant_seller_or_removed_row_changes_block_final_placement(): void
    {
        foreach (['quantity','price','variant','seller','removed'] as $change) {
            $row=$this->rows[0]->fresh();$variant=$row->productVariant;$originalSeller=$variant->product->seller_id;
            $token=$this->token([$row->id]);
            if($change==='quantity')$row->update(['quantity'=>2]);
            if($change==='price')$variant->update(['price_minor'=>12000]);
            if($change==='variant') { $replacement=$variant->replicate();$replacement->sku='REPLACEMENT';$replacement->save();$row->update(['product_variant_id'=>$replacement->id]); }
            if($change==='seller')$variant->product->update(['seller_id'=>$this->rows[2]->productVariant->product->seller_id]);
            if($change==='removed')$row->delete();
            $this->place($token)->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('cart_selection');
            $this->assertDatabaseCount('orders',0);$this->assertSame(10,$variant->fresh()->stock);
            if($change!=='removed') { $row->update(['quantity'=>1,'product_variant_id'=>$variant->id]);$variant->update(['price_minor'=>10000]);$variant->product->update(['seller_id'=>$originalSeller]); }
        }
    }

    public function test_expired_context_and_legacy_routes_cannot_bypass_selection(): void
    {
        $token=$this->token([$this->rows[0]->id]);$this->travel(31)->minutes();
        $this->get(route('buyer.checkout',['cart_selection'=>$token]))->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('cart_selection');
        $this->place($token)->assertRedirect(route('buyer.cart'));$this->assertDatabaseCount('orders',0);$this->travelBack();
        $this->get(route('buyer.checkout.legacy'))->assertRedirect(route('buyer.checkout'));
        $this->post(route('buyer.checkout.place-order.legacy'),['address_id'=>$this->address->id,'payment_method'=>'cod'])->assertRedirect(route('buyer.cart'));
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[$this->rows[0]->id]);
    }

    public function test_selected_subtotals_drive_vouchers_and_replay_consumes_usage_once(): void
    {
        $seller=$this->rows[0]->productVariant->product->seller_id;
        $voucher=Voucher::create(['code'=>'SELECTONLY','scope'=>'SELLER','seller_id'=>$seller,'discount_type'=>'FIXED','discount_minor'=>1000,'minimum_spend_minor'=>15000,'is_active'=>true]);
        $this->choose([$this->rows[0]->id],false);
        $this->post(route('buyer.cart.vouchers.apply'),['voucher_code'=>$voucher->code])->assertSessionHasErrors('voucher_code');
        $this->choose([$this->rows[0]->id,$this->rows[1]->id],false);
        $this->post(route('buyer.cart.vouchers.apply'),['voucher_code'=>$voucher->code])->assertSessionHasNoErrors();
        $token=$this->token([$this->rows[0]->id,$this->rows[1]->id]);
        $page=$this->get(route('buyer.checkout',['cart_selection'=>$token]))->assertOk();$this->assertEquals(250,$page->viewData('total'));
        $this->place($token)->assertRedirect();$this->place($token)->assertRedirect();
        $this->assertSame(1,$voucher->fresh()->used_count);$this->assertDatabaseCount('voucher_redemptions',1);
    }

    public function test_draft_persists_after_quantity_remove_and_wishlist_operations_and_new_rows_are_unselected(): void
    {
        $this->choose([$this->rows[0]->id,$this->rows[1]->id],false);
        $this->post(route('buyer.cart.update',$this->rows[0]->id),['quantity'=>2])->assertRedirect();
        $this->post(route('buyer.cart.remove',$this->rows[1]->id))->assertRedirect();
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[$this->rows[0]->id]);
        $this->post(route('buyer.wishlist.add',$this->rows[0]->product_slug),['cart_item_id'=>$this->rows[0]->id])->assertRedirect();
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids',[]);
    }

    public function test_selection_update_avoids_redundant_cart_reads_and_returns_confirmed_summary(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->postJson(route('buyer.cart.selection'), ['draft_revision'=>0,'cart_item_ids'=>[$this->rows[0]->id,$this->rows[2]->id],'intent'=>'update'])
            ->assertOk()->assertJsonPath('data.selected_summary.subtotal', 200)
            ->assertJsonPath('data.selected_summary.shipping', 100)->assertJsonPath('data.selected_summary.total', 300);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $reads = count(array_filter($queries, fn($query) => str_starts_with($query['query'], 'select * from "cart_items"')));
        fwrite(STDOUT, "\nSelection profile: ".count($queries).' SQL queries, '.$reads." Cart summary reads.\n");
        $this->assertLessThanOrEqual(3, $reads, 'Selection updates must not compute an unused Checkout preview.');
        $this->assertLessThanOrEqual(30, count($queries), 'Cart display relationships must be batch-loaded rather than queried per line.');
    }

    public function test_ajax_reconciles_shipping_and_voucher_eligibility_after_selection_changes(): void
    {
        $seller = $this->rows[0]->productVariant->product->seller_id;
        Voucher::create(['code'=>'FASTSELECT','scope'=>'SELLER','seller_id'=>$seller,'discount_type'=>'FIXED','discount_minor'=>1000,'minimum_spend_minor'=>15000,'is_active'=>true]);
        $this->withSession(['checkout_voucher_codes'=>['FASTSELECT']]);
        $first = $this->postJson(route('buyer.cart.selection'), ['draft_revision'=>0,'cart_item_ids'=>[$this->rows[0]->id,$this->rows[1]->id,$this->rows[2]->id]])
            ->assertOk()->assertJsonPath('data.selected_summary.shipping', 100)->assertJsonPath('data.selected_summary.discount', 10)->assertJsonPath('data.selected_summary.total', 400);
        $next = $this->postJson(route('buyer.cart.selection'), ['draft_revision'=>$first->json('data.selection.revision'),'cart_item_ids'=>[$this->rows[0]->id]])
            ->assertOk()->assertJsonPath('data.selected_summary.shipping', 50)->assertJsonPath('data.selected_summary.discount', 0)->assertJsonPath('data.selected_summary.total', 150);
        $this->assertNotEmpty($next->json('data.selected_summary.voucher_error'));
        $this->assertStringContainsString('Not applied:', $next->json('data.summary_html'));
        $this->post(route('buyer.cart.selection'), ['draft_revision'=>$next->json('data.selection.revision'),'cart_item_ids'=>[$this->rows[0]->id],'intent'=>'checkout'])
            ->assertRedirect(route('buyer.cart'))->assertSessionHasErrors('voucher_code');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('voucher_redemptions', 0);
    }
}
