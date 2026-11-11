<?php

namespace Tests\Feature;

use App\Models\{Address, CartItem, Category, Order, Product, ProductVariant, Seller, User};
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\{FakesCheckoutLocations, IsolatedStorageTestCase};

class BuyerCartVariantSwitchTest extends IsolatedStorageTestCase
{
    use RefreshDatabase, FakesCheckoutLocations;

    private User $buyer;
    private Product $product;
    private ProductVariant $source;
    private ProductVariant $target;
    private CartItem $row;
    private Address $address;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite only.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        $this->fakeCheckoutLocations();
        Storage::fake('public');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Fixture shop', 'slug' => 'fixture-shop', 'status' => 'approved']);
        $category = Category::create(['name' => 'Fixture', 'slug' => 'fixture', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Fixture product', 'slug' => 'fixture-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $this->source = $this->variant('Chicken', 10000);
        $this->target = $this->variant('Beef', 12500);
        $this->row = $this->cart($this->source, 2);
        $this->address = Address::create($this->checkoutLocationCodes() + ['user_id' => $this->buyer->id, 'label' => 'Home', 'recipient' => 'Fixture Buyer', 'phone' => '09000000000', 'line1' => 'Fixture street', 'province' => 'Province A', 'city' => 'City A', 'barangay' => 'Barangay A', 'is_default' => true]);
        $this->actingAs($this->buyer);
    }

    private function variant(string $name, int $price): ProductVariant
    {
        return $this->product->variants()->create(['sku' => 'SWITCH-'.$name, 'name' => $name, 'options' => ['Flavor' => $name], 'price_minor' => $price, 'stock' => 10, 'is_active' => true]);
    }

    private function cart(ProductVariant $variant, int $quantity = 1): CartItem
    {
        return CartItem::create(['user_id' => $this->buyer->id, 'product_slug' => $this->product->slug, 'product_name' => $this->product->name, 'product_variant_id' => $variant->id, 'price' => 100, 'quantity' => $quantity]);
    }

    private function offer(): array
    {
        $data = $this->getJson(route('buyer.cart'))->assertOk()->json('data');
        $option = collect($data['variant_choices'][$this->row->id])->firstWhere('id', $this->target->id);
        return ['variant_id' => $this->target->id, 'draft_revision' => $data['selection']['revision'], 'variant_proof' => [$this->target->id => $option['proof']]];
    }

    private function change(array $offer)
    {
        return $this->postJson(route('buyer.cart.change-variant', $this->row->id), $offer);
    }

    private function choose(array $ids, bool $checkout = false)
    {
        $revision = $this->getJson(route('buyer.cart'))->json('data.selection.revision');
        return $this->post(route('buyer.cart.selection'), ['draft_revision' => $revision, 'cart_item_ids' => $ids, 'intent' => $checkout ? 'checkout' : 'update']);
    }

    private function frozen(): string
    {
        $response = $this->choose([$this->row->id], true)->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        return $query['cart_selection'];
    }

    public function test_replacement_preserves_identity_quantity_selection_and_uses_current_price_image_without_stock_changes(): void
    {
        Storage::disk('public')->put('variant.png', 'synthetic-image');
        $this->target->update(['image_path' => 'variant.png']);
        $sibling = $this->cart($this->variant('Fish', 15000), 3);
        $before = $sibling->fresh()->getAttributes();
        $this->choose([$this->row->id, $sibling->id])->assertRedirect();
        $offer = $this->offer();
        $this->change($offer)->assertOk()->assertJsonPath('success', true)->assertJsonPath('code', 'ok');
        $this->assertSame($this->target->id, $this->row->fresh()->product_variant_id);
        $this->assertSame(2, $this->row->fresh()->quantity);
        $this->assertSame('125.00', $this->row->fresh()->price);
        $this->assertSame($before, $sibling->fresh()->getAttributes());
        $this->assertSame([10, 10, 10], ProductVariant::orderBy('id')->pluck('stock')->all());
        $this->assertDatabaseCount('cart_items', 2);
        $page = $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids', [$this->row->id, $sibling->id])->assertJsonPath('data.selection.revision', $offer['draft_revision'] + 1);
        $this->assertEquals(750, $page->json('data.selected_summary.total'));
        $this->assertStringEndsWith('/variant.png', $page->json('data.cart.'.$this->row->id.'.image'));
        $this->assertSame('Beef', $page->json('data.cart.'.$this->row->id.'.variant'));
    }

    public function test_unselected_source_stays_unselected_and_native_post_redirects_to_same_row(): void
    {
        $this->from(route('buyer.cart'))->post(route('buyer.cart.change-variant', $this->row->id), $this->offer())
            ->assertRedirect(route('buyer.cart').'#cart-line-'.$this->row->id)->assertSessionHas('success');
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids', [])->assertJsonPath('data.selected_summary.total', 0);
        $this->assertSame(2, $this->row->fresh()->quantity);
    }

    public static function invalidTargets(): array
    {
        return [['sold_out'], ['shortage'], ['inactive_variant'], ['deleted_variant'], ['inactive_product'], ['unapproved_product'], ['deleted_product'], ['inactive_category'], ['unavailable_shop'], ['suspended_owner'], ['negative_price']];
    }

    #[DataProvider('invalidTargets')]
    public function test_current_eligibility_and_stock_are_authoritative_without_partial_writes(string $case): void
    {
        $offer = $this->offer(); $before = $this->row->fresh()->getAttributes();
        match ($case) {
            'sold_out' => $this->target->update(['stock' => 0]),
            'shortage' => $this->target->update(['stock' => 1]),
            'inactive_variant' => $this->target->update(['is_active' => false]),
            'deleted_variant' => $this->target->delete(),
            'inactive_product' => $this->product->update(['is_active' => false]),
            'unapproved_product' => $this->product->update(['moderation_status' => 'PENDING']),
            'deleted_product' => $this->product->delete(),
            'inactive_category' => $this->product->category->update(['is_active' => false]),
            'unavailable_shop' => $this->product->seller->update(['status' => 'pending']),
            'suspended_owner' => $this->product->seller->owner->update(['is_suspended' => true]),
            'negative_price' => $this->target->update(['price_minor' => -1]),
        };
        $response = $this->change($offer)->assertStatus(in_array($case, ['sold_out', 'shortage']) ? 409 : 422)->assertJsonPath('success', false);
        if ($case === 'shortage') $response->assertJsonPath('message', 'Only 1 available. Please lower your quantity.');
        $this->assertSame($before, $this->row->fresh()->getAttributes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(10, $this->source->fresh()->stock);
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.revision', 0);
    }

    public static function selections(): array { return [[false,false],[false,true],[true,false],[true,true]]; }

    #[DataProvider('selections')]
    public function test_existing_target_is_rejected_without_merging_or_changing_either_selection(bool $sourceSelected, bool $targetSelected): void
    {
        $targetRow = $this->cart($this->target, 3);
        $ids = array_values(array_filter([$sourceSelected ? $this->row->id : null, $targetSelected ? $targetRow->id : null]));
        $this->choose($ids)->assertRedirect();
        $offer = $this->offer(); $before = CartItem::orderBy('id')->get()->toArray();
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'variant_already_in_cart')->assertJsonPath('message', 'This variant is already in your Cart. Update that row or remove it before switching.');
        $this->assertSame($before, CartItem::orderBy('id')->get()->toArray());
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids', $ids)->assertJsonPath('data.selection.revision', $offer['draft_revision']);
    }

    public function test_foreign_missing_and_wrong_product_ids_are_non_disclosing(): void
    {
        $offer = $this->offer();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $foreign = $this->row->replicate(); $foreign->user_id = $other->id; $foreign->save();
        $this->postJson(route('buyer.cart.change-variant', $foreign->id), $offer)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson(route('buyer.cart.change-variant', 999999), $offer)->assertNotFound();
        $product = $this->product->replicate(); $product->slug = 'different-product'; $product->save();
        $variant = $this->target->replicate(); $variant->product_id = $product->id; $variant->sku = 'OTHER'; $variant->save();
        $offer['variant_id'] = $variant->id;
        $this->change($offer)->assertNotFound();
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
        $this->assertSame(2, $foreign->fresh()->quantity);
        $this->assertDatabaseCount('orders', 0);
    }

    public static function staleTerms(): array { return [['quantity'], ['source'], ['price'], ['options'], ['name'], ['image']]; }

    #[DataProvider('staleTerms')]
    public function test_material_source_and_target_changes_reject_the_reviewed_proof(string $case): void
    {
        $offer = $this->offer();
        match ($case) {
            'quantity' => $this->row->update(['quantity' => 3]),
            'source' => $this->row->update(['product_variant_id' => $this->target->id]),
            'price' => $this->target->update(['price_minor' => 14000]),
            'options' => $this->target->update(['options' => ['Flavor' => 'Changed']]),
            'name' => $this->product->update(['name' => 'Changed product']),
            'image' => $this->target->update(['image_path' => 'changed.png']),
        };
        $before = $this->row->fresh()->getAttributes();
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_variant_offer');
        $this->assertSame($before, $this->row->fresh()->getAttributes());
    }

    public function test_replay_and_stale_draft_never_overwrite_a_newer_selection(): void
    {
        $offer = $this->offer();
        $this->choose([$this->row->id])->assertRedirect();
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_selection');
        $offer = $this->offer();
        $this->change($offer)->assertOk();
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_selection');
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.selection.ids', [$this->row->id]);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(2, $this->row->fresh()->quantity);
    }

    public function test_sold_out_and_soft_deleted_source_can_be_repaired_but_ambiguous_legacy_identity_is_rejected(): void
    {
        $offer = $this->offer(); $this->source->update(['stock' => 0]); $this->source->delete();
        $this->change($offer)->assertOk();
        $this->assertSame($this->target->id, $this->row->fresh()->product_variant_id);
        $this->row->update(['product_variant_id' => null]);
        $this->variant('Fish', 15000);
        $this->getJson(route('buyer.cart'))->assertJsonPath('data.variant_choices', []);
        $offer['draft_revision'] = 1;
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_cart_item');
        $this->assertNull($this->row->fresh()->product_variant_id);
    }

    public function test_unambiguous_legacy_target_conflict_is_not_merged(): void
    {
        $this->source->update(['is_active' => false]);
        $legacy = $this->row->replicate(); $legacy->product_variant_id = null; $legacy->save();
        $this->change($this->offer())->assertStatus(409)->assertJsonPath('code', 'variant_already_in_cart');
        $this->assertNull($legacy->fresh()->product_variant_id);
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
    }

    public function test_frozen_checkout_is_immutable_and_fresh_checkout_buys_only_replaced_selected_row(): void
    {
        $sibling = $this->cart($this->variant('Fish', 15000), 3);
        $token = $this->frozen();
        $snapshot = session('cart_checkouts.'.$token);
        $this->change($this->offer())->assertOk();
        $this->assertSame($snapshot, session('cart_checkouts.'.$token));
        $this->get(route('buyer.checkout', ['cart_selection' => $token]))->assertRedirect(route('buyer.cart'));
        $this->from(route('buyer.checkout', ['cart_selection' => $token]))->post(route('buyer.checkout.place-order'), ['cart_selection' => $token, 'address_id' => $this->address->id, 'payment_method' => 'cod'])->assertRedirect(route('buyer.cart'));
        $this->assertDatabaseCount('orders', 0);
        $fresh = $this->frozen();
        $this->post(route('buyer.checkout.place-order'), ['cart_selection' => $fresh, 'address_id' => $this->address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$this->target->id], Order::sole()->items->pluck('product_variant_id')->all());
        $this->assertSame(2, Order::sole()->items->sole()->quantity);
        $this->assertSame([$sibling->id], CartItem::pluck('id')->all());
        $this->assertSame(8, $this->target->fresh()->stock);
        $this->assertSame(10, $this->source->fresh()->stock);
    }

    public function test_native_validation_redirect_and_route_protection(): void
    {
        $this->from(route('buyer.cart'))->post(route('buyer.cart.change-variant', $this->row->id), ['draft_revision' => 0])->assertRedirect(route('buyer.cart'))->assertSessionHasErrors(['variant_id', 'variant_proof']);
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
        $route = app('router')->getRoutes()->getByName('buyer.cart.change-variant');
        $this->assertSame(10, $route->locksFor());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('role:buyer', $route->gatherMiddleware());
        auth()->logout();
        $this->postJson(route('buyer.cart.change-variant', $this->row->id), [])->assertUnauthorized()->assertJsonPath('code', 'auth_required');
    }

    public function test_target_is_read_again_inside_transaction_and_failed_write_rolls_back(): void
    {
        $offer = $this->offer(); $changed = false;
        DB::listen(function ($query) use (&$changed) {
            if (!$changed && DB::transactionLevel() > 1 && str_starts_with($query->sql, 'select * from "cart_items"')) {
                $changed = true;
                DB::table('product_variants')->where('id', $this->target->id)->update(['price_minor' => 99999]);
            }
        });
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_variant_offer');
        $this->assertTrue($changed);
        $this->assertSame(12500, $this->target->fresh()->price_minor);
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
        $this->assertSame(2, $this->row->fresh()->quantity);
    }

    public function test_removed_source_and_forged_proof_do_not_mutate_cart(): void
    {
        $offer = $this->offer(); $offer['variant_proof'][$this->target->id] = str_repeat('0', 64);
        $this->change($offer)->assertStatus(409)->assertJsonPath('code', 'stale_variant_offer');
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
        $this->row->delete();
        $this->change($offer)->assertNotFound();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_picker_uses_separate_native_form_and_disabled_real_options_and_hides_for_single_variant(): void
    {
        $this->target->update(['stock' => 1]);
        $html = $this->get(route('buyer.cart'))->assertOk()->assertSee('data-cart-variant-picker', false)->assertSee('Choose variant')->assertSee('Only 1 available')->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//form[@data-cart-variant-form]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-cart-variant-form]/ancestor::form')->length);
        $this->assertSame(1, $xpath->query('//form[@data-cart-variant-form]//input[@type="radio" and @disabled]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-cart-variant-form]//button[@type="submit" and @disabled]')->length);
        $this->assertSame($this->source->id, $this->row->fresh()->product_variant_id);
        $this->target->delete();
        $this->get(route('buyer.cart'))->assertOk()->assertDontSee('data-cart-variant-picker', false);
    }
}
