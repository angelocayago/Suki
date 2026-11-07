<?php

namespace Tests\Feature;

use App\Models\{CartItem, Category, Product, ProductVariant, Seller, User};
use Illuminate\Support\Facades\{DB, Http, Storage};
use Tests\Support\{BuyerPurchasesSchema, IsolatedStorageTestCase};

class BuyerProductDetailsWishlistTest extends IsolatedStorageTestCase
{
    private User $buyer;
    private Product $product;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated SQLite required.');
        }
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        BuyerPurchasesSchema::create();
        foreach (['product_id', 'seller_id', 'buyer_id', 'seller_order_id', 'order_item_id', 'rider_id', 'rating'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' INTEGER');
        foreach (['kind', 'eligibility_key', 'body', 'seller_response', 'hidden_at', 'edited'] as $column) DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' TEXT');
        DB::statement('CREATE TABLE cart_items (id INTEGER PRIMARY KEY)');
        foreach (array_unique([...(new CartItem())->getFillable(), 'created_at', 'updated_at']) as $column) DB::statement('ALTER TABLE cart_items ADD COLUMN '.$column.' '.(str_ends_with($column, '_id') || in_array($column, ['quantity', 'selected']) ? 'INTEGER' : 'TEXT'));
        Http::preventStrayRequests();
        Storage::fake('public');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Gallery shop', 'slug' => 'gallery-shop', 'status' => 'approved']);
        $category = Category::create(['name' => 'Gallery', 'slug' => 'gallery', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Gallery product', 'slug' => 'gallery-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $this->variant = $this->product->variants()->create(['name' => 'Available', 'sku' => 'GALLERY', 'price_minor' => 13500, 'stock' => 1, 'is_active' => true]);
        $this->product->variants()->create(['name' => 'Sold out', 'sku' => 'GALLERY-SOLD', 'price_minor' => 15000, 'stock' => 0, 'is_active' => true]);
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        return new \DOMXPath($document);
    }

    public function test_wishlist_stays_in_the_media_stage_but_thumbnails_are_outside_its_clipping_boundary(): void
    {
        foreach ([1, 0] as $stock) {
            $this->variant->update(['stock' => $stock]);
            $xpath = $this->document($this->actingAs($this->buyer)->get(route('buyer.product', $this->product->slug))->assertOk()->getContent());
            $forms = $xpath->query('//*[@id="productMediaStage"]//form');
            $this->assertCount(1, $forms);
            $this->assertSame(route('buyer.wishlist.toggle', $this->product->slug), $forms->item(0)->getAttribute('action'));
            $this->assertSame('POST', $forms->item(0)->getAttribute('method'));
            $this->assertCount(1, $xpath->query('//*[@id="productMediaStage"]//button[@aria-label="Add to Wishlist"]'));
            $this->assertCount(0, $xpath->query('//*[@id="productMediaStage"]//*[@id="productMediaThumbnails"]'));
            $this->assertCount(1, $xpath->query('//*[@id="productMediaStage"]/following-sibling::*[@id="productMediaThumbnails"]'));
            $this->assertCount($stock ? 0 : 2, $xpath->query('//form[@id="addToCartForm"]//button[@type="submit" and @disabled]'));
        }
    }

    public function test_saved_heart_state_and_normal_post_toggle_work_even_when_all_variants_are_sold_out(): void
    {
        $this->product->variants()->update(['stock' => 0]);
        $details = route('buyer.product', $this->product->slug);
        $toggle = route('buyer.wishlist.toggle', $this->product->slug);
        $this->actingAs($this->buyer)->from($details)->post($toggle)->assertRedirect($details)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wishlist_items', 1);
        $xpath = $this->document($this->get($details)->assertOk()->getContent());
        $buttons = $xpath->query('//*[@id="productMediaStage"]//button[@aria-label="Remove from Wishlist"]');
        $this->assertCount(1, $buttons);
        $this->assertStringContainsString('background-color: #1F6F5B', $buttons->item(0)->getAttribute('style'));
        $this->from($details)->post($toggle)->assertRedirect($details)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wishlist_items', 0);
        $this->get($details)->assertOk()->assertSee('aria-label="Add to Wishlist"', false);
    }

    public function test_sold_out_catalog_cards_keep_both_details_links_and_an_independent_wishlist_form(): void
    {
        $this->product->variants()->update(['stock' => 0]);
        $normal = $this->actingAs($this->buyer)->get(route('buyer.shop'))->assertOk()->getContent();
        $ajax = $this->getJson(route('buyer.shop'))->assertOk()->assertJsonPath('count', 1)->json('html');
        foreach ([$normal, $ajax] as $html) {
            $xpath = $this->document($html);
            $cards = $xpath->query('//article[contains(@class,"catalog-product-card")]');
            $this->assertCount(1, $cards);
            $links = $xpath->query('.//a[@href="'.route('buyer.product', $this->product->slug).'"]', $cards->item(0));
            $this->assertCount(2, $links);
            foreach ($links as $link) {
                $this->assertFalse($link->hasAttribute('disabled'));
                $this->assertNotSame('true', $link->getAttribute('aria-disabled'));
                $this->assertStringNotContainsString('pointer-events-none', $link->getAttribute('class'));
            }
            $this->assertCount(1, $xpath->query('.//form[@method="POST" and not(ancestor::a)]', $cards->item(0)));
        }
        $this->get(route('buyer.product', $this->product->slug))->assertOk()->assertSee('id="buyNowButton"', false);
        $this->postJson(route('buyer.cart.add', $this->product->slug), ['variant_id' => $this->variant->id, 'quantity' => 1])
            ->assertStatus(409)->assertJsonPath('code', 'out_of_stock');
        $this->from(route('buyer.product', $this->product->slug))
            ->post(route('buyer.buy-now', $this->product->slug), ['variant_id' => $this->variant->id, 'quantity' => 1])
            ->assertRedirect(route('buyer.product', $this->product->slug))->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('cart_items', 0);
        $this->getJson(route('buyer.shop', ['in_stock' => '1']))->assertOk()->assertJsonPath('count', 0);
    }

    public function test_non_public_products_still_return_404_and_guests_get_the_login_heart(): void
    {
        $html = $this->get(route('buyer.product', $this->product->slug))->assertOk()->getContent();
        $xpath = $this->document($html);
        $this->assertCount(1, $xpath->query('//*[@id="productMediaStage"]//a[@href="'.route('login').'" and @aria-label="Sign in to save to wishlist"]'));
        $this->assertCount(0, $xpath->query('//*[@id="productMediaStage"]//form'));
        foreach ([['is_active' => false], ['moderation_status' => 'PENDING'], ['moderation_status' => 'HIDDEN']] as $changes) {
            $this->product->update(['is_active' => true, 'moderation_status' => 'APPROVED']);
            $this->product->update($changes);
            $this->get(route('buyer.product', $this->product->slug))->assertNotFound();
            $this->getJson(route('buyer.shop'))->assertOk()->assertJsonPath('count', 0);
        }
        $this->product->update(['is_active' => true, 'moderation_status' => 'APPROVED']);
        $this->product->delete();
        $this->get(route('buyer.product', $this->product->slug))->assertNotFound();
    }
}
