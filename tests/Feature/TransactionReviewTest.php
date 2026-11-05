<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\TransactionReview;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\IsolatedStorageTestCase;

class TransactionReviewTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite memory database required.');
        parent::setUp();
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::connection()->getDriverName());
        $this->assertSame(':memory:', \Illuminate\Support\Facades\DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
    }

    private function fixture(string $suffix = ''): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Review shop', 'slug' => 'review-shop'.$suffix, 'status' => 'approved']);
        $category = Category::create(['name' => 'Reviews'.$suffix, 'slug' => 'reviews'.$suffix, 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Product', 'slug' => 'review-product'.$suffix, 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => 'REVIEW-SKU'.$suffix, 'name' => 'Chocolate / Pack of 6', 'price_minor' => 10000, 'stock' => 1, 'is_active' => true]);
        $order = Order::create(['buyer_id' => $buyer->id, 'order_number' => 'REVIEW-1'.$suffix, 'status' => 'completed', 'payment_method' => 'cod', 'payment_status' => 'PAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'recipient_name' => 'Buyer', 'recipient_phone' => '09123456789', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street']);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 10000, 'status' => 'completed', 'workflow_status' => OrderStatus::Completed, 'completed_at' => now()]);
        $item = OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'seller_id' => $sellerUser->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'variant_name' => $variant->name, 'variation' => ['Flavor' => 'Chocolate', 'Pack Size' => 'Pack of 6'], 'product_slug' => $product->slug, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);
        return compact('buyer', 'sellerUser', 'seller', 'product', 'order', 'sellerOrder', 'item');
    }

    public function test_completed_buyer_can_review_once_and_edit_once(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['buyer'])->postJson(route('reviews.product', $f['item']), ['rating' => 5, 'body' => 'Good'])->assertCreated();
        $review = TransactionReview::firstOrFail();
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 4])->assertUnprocessable();
        $this->patchJson(route('reviews.edit', $review), ['rating' => 4, 'body' => 'Updated'])->assertOk();
        $this->patchJson(route('reviews.edit', $review), ['rating' => 3])->assertUnprocessable();
        $this->assertSame(4, $review->fresh()->rating);
    }

    public function test_non_purchaser_incomplete_expired_and_invalid_input_are_rejected(): void
    {
        $f = $this->fixture();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->postJson(route('reviews.product', $f['item']), ['rating' => 5])->assertForbidden();
        $this->actingAs($f['buyer'])->postJson(route('reviews.product', $f['item']), ['rating' => 0])->assertUnprocessable();
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 6])->assertUnprocessable();
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 5, 'product_id' => 999999])->assertForbidden();
        $this->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 5, 'seller_id' => 999999])->assertForbidden();
        $this->actingAs($other)->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 5])->assertForbidden();
        $this->actingAs($f['buyer']);
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 5, 'body' => str_repeat('x', 501)])->assertUnprocessable();
        $f['sellerOrder']->update(['workflow_status' => OrderStatus::Delivered]);
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 5])->assertUnprocessable();
        $f['sellerOrder']->update(['workflow_status' => OrderStatus::Completed, 'completed_at' => now()->subDays(31)]);
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 5])->assertUnprocessable();
        $this->assertDatabaseCount('transaction_reviews', 0);
    }

    public function test_store_response_and_admin_moderation_are_scoped_and_hidden_reviews_are_excluded(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['buyer'])->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 5, 'body' => 'Reliable store'])->assertCreated();
        $this->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 5])->assertUnprocessable();
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 4])->assertCreated();
        $review = TransactionReview::where('kind', 'product')->firstOrFail();
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($otherSeller)->postJson(route('reviews.respond', $review), ['response' => 'No'])->assertForbidden();
        $this->actingAs($f['sellerUser'])->postJson(route('reviews.respond', $review), ['response' => 'Thank you', 'rating' => 1])->assertOk();
        $this->assertSame(4, $review->fresh()->rating);
        $this->assertSame(1, $f['buyer']->unreadNotifications()->count());
        $this->postJson(route('reviews.moderate', $review), ['action' => 'hide', 'reason' => 'Moderation'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $storeReview = TransactionReview::where('kind', 'seller')->firstOrFail();
        $this->actingAs($admin)->get(route('buyer.stores.show', $f['seller']->slug))->assertSee('Reliable store');
        $this->postJson(route('reviews.moderate', $storeReview), ['action' => 'hide', 'reason' => 'Review'])->assertOk();
        $this->get(route('buyer.stores.show', $f['seller']->slug))->assertDontSee('Reliable store');
        $this->postJson(route('reviews.moderate', $storeReview), ['action' => 'restore', 'reason' => 'Restored'])->assertOk();
        $this->actingAs($admin)->postJson(route('reviews.moderate', $review), ['action' => 'hide'])->assertUnprocessable();
        $this->postJson(route('reviews.moderate', $review), ['action' => 'hide', 'reason' => 'Moderation'])->assertOk();
        $this->getJson(route('reviews.product-summary', $f['product']))->assertJsonPath('review_count', 0)->assertJsonPath('average_rating', null);
        $this->get(route('buyer.product', $f['product']->slug))->assertOk()->assertDontSee('Thank you');
        $hiddenProduct = app(ProductCatalogService::class)->marketplaceProduct($f['product']->slug);
        $this->assertSame(0, $hiddenProduct['ratings']);
        $this->assertDatabaseHas('review_moderation_events', ['transaction_review_id' => $review->id, 'admin_id' => $admin->id, 'action' => 'hide']);
        $this->postJson(route('reviews.moderate', $review), ['action' => 'restore', 'reason' => 'Restored'])->assertOk();
        $this->getJson(route('reviews.product-summary', $f['product']))->assertJsonPath('review_count', 1)->assertJsonPath('average_rating', 4);
        $this->get(route('buyer.product', $f['product']->slug))->assertOk()->assertSee('Thank you');
        $visibleProduct = app(ProductCatalogService::class)->marketplaceProduct($f['product']->slug);
        $this->assertSame(1, $visibleProduct['ratings']);
        $this->assertEquals(4, $visibleProduct['rating']);
        $this->assertSame('5.0/5', $visibleProduct['seller_rating']);
    }

    public function test_only_the_recorded_final_delivery_rider_can_be_rated(): void
    {
        $f = $this->fixture();
        $providerUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $providerUser->id, 'name' => 'Provider', 'slug' => 'provider', 'status' => 'approved']);
        $pickupUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $finalUser = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $pickup = Rider::create(['user_id' => $pickupUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'PICKUP', 'is_active' => true]);
        $final = Rider::create(['user_id' => $finalUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'FINAL', 'is_active' => true]);
        $shipment = Shipment::create(['seller_order_id' => $f['sellerOrder']->id, 'logistics_provider_id' => $provider->id, 'pickup_rider_id' => $pickup->id, 'rider_id' => $final->id, 'tracking_code' => 'REVIEW-TRACKING', 'status' => 'delivered', 'workflow_status' => OrderStatus::Delivered, 'fee_minor' => 0, 'cod_amount_minor' => 0, 'cod_collected' => false, 'attempts' => 0, 'delivered_by' => $finalUser->id, 'proof_delivered_at' => now()]);
        $this->actingAs($f['buyer'])->postJson(route('reviews.rider', $f['sellerOrder']), ['rating' => 5, 'rider_id' => $pickup->id])->assertForbidden();
        $f['sellerOrder']->update(['workflow_status' => OrderStatus::Delivered]);
        $this->postJson(route('reviews.rider', $f['sellerOrder']), ['rating' => 5, 'rider_id' => $final->id])->assertUnprocessable();
        $f['sellerOrder']->update(['workflow_status' => OrderStatus::Completed]);
        $this->postJson(route('reviews.rider', $f['sellerOrder']), ['rating' => 5, 'rider_id' => $final->id])->assertCreated();
        $this->assertDatabaseHas('transaction_reviews', ['kind' => 'rider', 'rider_id' => $final->id]);
    }

    public function test_role_pages_show_only_authorized_reviews_and_html_forms_work(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['buyer'])->get(route('reviews.buyer-page'))->assertOk()->assertSee('Product')->assertSee('Store review');
        $this->post(route('reviews.product', $f['item']), ['rating' => 5, 'body' => '<script>alert(1)</script>'])
            ->assertRedirect(route('reviews.buyer-page'));
        $this->actingAs($f['sellerUser'])->get(route('reviews.seller-page'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($otherSeller)->get(route('reviews.seller-page'))->assertOk()->assertDontSee('alert(1)');
        $this->get(route('reviews.admin-page'))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('reviews.admin-page'))->assertOk()->assertSee('Review moderation');
    }

    public function test_completed_order_actions_and_history_link_to_exact_purchase_and_seller(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['buyer'])->get(route('buyer.order-details', $f['order']->order_number))->assertOk()
            ->assertSee('Rate Product')->assertSee('Rate Seller')->assertSee('Chocolate / Pack of 6')
            ->assertSee(app(\App\Support\BuyerReviews::class)->url($f['sellerOrder']->id, 'product-review-'.$f['item']->id), false)
            ->assertSee(app(\App\Support\BuyerReviews::class)->url($f['sellerOrder']->id, 'seller-review-'.$f['sellerOrder']->id), false);
        $this->get(route('buyer.my-orders'))->assertOk()->assertSee('Review Purchase')->assertSee(route('buyer.order-details', $f['order']->order_number), false);
        $this->assertDatabaseCount('transaction_reviews', 0);
    }

    public static function blockedStates(): array
    {
        return [['delivered'], ['cancelled'], ['future'], ['expired'], ['missing-completion'], ['wrong-seller']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedStates')]
    public function test_actions_and_submission_agree_on_ineligible_product_purchases(string $state): void
    {
        $f = $this->fixture();
        match ($state) {
            'delivered' => $f['sellerOrder']->update(['workflow_status' => OrderStatus::Delivered]),
            'cancelled' => $f['sellerOrder']->update(['status' => 'cancelled']),
            'future' => $f['sellerOrder']->update(['completed_at' => now()->addDay()]),
            'expired' => $f['sellerOrder']->update(['completed_at' => now()->subDays(31)]),
            'missing-completion' => $f['sellerOrder']->update(['completed_at' => null]),
            'wrong-seller' => $f['product']->update(['seller_id' => $this->fixture('-other')['seller']->id]),
        };
        $this->actingAs($f['buyer'])->get(route('buyer.order-details', $f['order']->order_number))->assertOk()->assertDontSee('>Rate Product<', false);
        $response = $this->postJson(route('reviews.product', $f['item']), ['rating' => 5]);
        $state === 'wrong-seller' ? $response->assertForbidden() : $response->assertUnprocessable();
        $this->assertDatabaseCount('transaction_reviews', 0);
        if ($state === 'expired') {
            $this->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 4])->assertCreated();
        }
    }

    public function test_deep_link_is_owned_and_can_reach_an_older_purchase_beyond_the_first_page(): void
    {
        $f = $this->fixture();
        $f['sellerOrder']->update(['completed_at' => now()->subDay()]);
        for ($n = 0; $n < 16; $n++) {
            $parent = $f['order']->replicate(); $parent->order_number = 'LATER-'.$n; $parent->save();
            SellerOrder::create(['order_id' => $parent->id, 'seller_id' => $f['seller']->id, 'subtotal_minor' => 0, 'status' => 'completed', 'workflow_status' => OrderStatus::Completed, 'completed_at' => now()]);
        }
        $url = route('reviews.buyer-page', ['seller_order' => $f['sellerOrder']->id]);
        $this->actingAs($f['buyer'])->get($url)->assertOk()->assertSee('id="product-review-'.$f['item']->id.'"', false)->assertSee('Back to Order Details');
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']))->get($url)->assertNotFound();
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 5])->assertForbidden();
        $this->actingAs($f['buyer'])->get(route('reviews.buyer-page', ['seller_order' => $f['sellerOrder']->id, 'edit_item' => 99999]))->assertNotFound();
        $this->getJson(route('reviews.buyer-page', ['seller_order' => 'bad']))->assertUnprocessable();
    }

    public function test_multiseller_purchase_has_separate_seller_rating_targets(): void
    {
        $a = $this->fixture(); $b = $this->fixture('-second');
        $b['sellerOrder']->update(['order_id' => $a['order']->id]);
        $b['item']->update(['order_id' => $a['order']->id]);
        $this->actingAs($a['buyer'])->get(route('buyer.order-details', $a['order']->order_number))->assertOk()
            ->assertSee(app(\App\Support\BuyerReviews::class)->url($a['sellerOrder']->id, 'seller-review-'.$a['sellerOrder']->id), false)
            ->assertSee(app(\App\Support\BuyerReviews::class)->url($b['sellerOrder']->id, 'seller-review-'.$b['sellerOrder']->id), false);
        $this->postJson(route('reviews.seller', $b['sellerOrder']), ['rating' => 3, 'seller_id' => $a['seller']->id])->assertForbidden();
        $this->postJson(route('reviews.seller', $b['sellerOrder']), ['rating' => 3])->assertCreated();
        $this->assertDatabaseHas('transaction_reviews', ['seller_order_id' => $b['sellerOrder']->id, 'seller_id' => $b['seller']->id, 'kind' => 'seller']);
        $this->assertDatabaseMissing('transaction_reviews', ['seller_id' => $a['seller']->id]);
    }

    public function test_submission_redirects_to_review_and_existing_actions_preserve_one_edit_policy(): void
    {
        $f = $this->fixture(); $key = 'item:'.$f['item']->id;
        $url = app(\App\Support\BuyerReviews::class)->url($f['sellerOrder']->id, 'product-review-'.$f['item']->id);
        $this->actingAs($f['buyer'])->post(route('reviews.product', $f['item']), ['rating' => 5, 'body' => 'My submitted review', '_review_form' => $key])->assertRedirect($url);
        $review = TransactionReview::sole();
        $this->get($url)->assertOk()->assertSee('My submitted review')->assertSee('Edit Review (one edit available)')->assertDontSee('Awaiting product review');
        $this->get(route('buyer.order-details', $f['order']->order_number))->assertOk()->assertSee('View Review')->assertSee('Edit Review')->assertDontSee('>Rate Product<', false);
        $this->get(route('reviews.buyer-page', ['seller_order' => $f['sellerOrder']->id, 'edit_item' => $f['item']->id]))->assertOk()->assertSee('<details class="mt-3"  open', false);
        $this->patch(route('reviews.edit', $review), ['rating' => 4, 'body' => 'Edited review', '_review_form' => $key])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Edited review')->assertSee('Editing no longer available.')->assertDontSee('Edit Review (one edit available)');
        $this->postJson(route('reviews.product', $f['item']), ['rating' => 2])->assertUnprocessable();
        $this->patchJson(route('reviews.edit', $review), ['rating' => 2])->assertUnprocessable();
    }

    public function test_hidden_and_expired_reviews_remain_private_readable_and_noneditable(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['buyer'])->postJson(route('reviews.product', $f['item']), ['rating' => 4, 'body' => 'Private hidden feedback'])->assertCreated();
        TransactionReview::sole()->update(['hidden_at' => now()]);
        $f['sellerOrder']->update(['completed_at' => now()->subDays(31)]);
        $this->get(route('reviews.buyer-page', ['seller_order' => $f['sellerOrder']->id]))->assertOk()->assertSee('Private hidden feedback')->assertSee('Hidden by moderation')->assertSee('30-day product review window has expired')->assertDontSee('Edit Review (one edit available)');
        $this->get(route('buyer.product', $f['product']->slug))->assertOk()->assertDontSee('Private hidden feedback')->assertSee('href="#customer-reviews"', false);
        $this->getJson(route('reviews.product-summary', $f['product']))->assertJsonPath('review_count', 0);
    }

    public function test_validation_old_input_is_scoped_to_the_attempted_form_and_safely_escaped(): void
    {
        $f = $this->fixture(); $url = route('reviews.buyer-page', ['seller_order' => $f['sellerOrder']->id]);
        $body = '<script>attempt</script>'.str_repeat('x', 501);
        $this->actingAs($f['buyer'])->from($url)->post(route('reviews.product', $f['item']), ['rating' => 3, 'body' => $body, '_review_form' => 'item:'.$f['item']->id])->assertSessionHasErrors('body')->assertSessionHasInput('rating', 3);
        $response = $this->get($url)->assertOk()->assertSee('&lt;script&gt;attempt&lt;/script&gt;', false)->assertDontSee('<script>attempt</script>', false);
        $this->assertSame(1, substr_count($response->getContent(), '&lt;script&gt;attempt&lt;/script&gt;'));
        $this->assertSame(1, preg_match_all('/value="3"[^>]*checked/', $response->getContent()));
        $this->assertDatabaseCount('transaction_reviews', 0);
    }

    public function test_existing_seller_rating_is_viewable_without_an_edit_or_duplicate_form(): void
    {
        $f = $this->fixture();
        $url = app(\App\Support\BuyerReviews::class)->url($f['sellerOrder']->id, 'seller-review-'.$f['sellerOrder']->id);
        $this->actingAs($f['buyer'])->post(route('reviews.seller', $f['sellerOrder']), ['rating' => 2, 'body' => 'Store service feedback', '_review_form' => 'seller-order:'.$f['sellerOrder']->id])->assertRedirect($url);
        $review = TransactionReview::sole();
        $this->get(route('buyer.order-details', $f['order']->order_number))->assertOk()->assertSee('View Seller Rating')->assertDontSee('>Rate Seller<', false)->assertSee('Rate Product');
        $this->get($url)->assertOk()->assertSee('Store service feedback')->assertSee('Seller ratings cannot be edited.')->assertDontSee('action="'.route('reviews.seller', $f['sellerOrder']).'"', false);
        $this->patchJson(route('reviews.edit', $review), ['rating' => 5])->assertForbidden();
        $this->postJson(route('reviews.seller', $f['sellerOrder']), ['rating' => 5])->assertUnprocessable();
        $this->getJson(route('reviews.product-summary', $f['product']))->assertJsonPath('review_count', 0);
    }
}
