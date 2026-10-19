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
use Tests\TestCase;

class TransactionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Review shop', 'slug' => 'review-shop', 'status' => 'approved']);
        $category = Category::create(['name' => 'Reviews', 'slug' => 'reviews', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Product', 'slug' => 'review-product', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'REVIEW-SKU', 'name' => 'Default', 'price_minor' => 10000, 'stock' => 1, 'is_active' => true]);
        $order = Order::create(['buyer_id' => $buyer->id, 'order_number' => 'REVIEW-1', 'status' => 'completed', 'payment_method' => 'cod', 'payment_status' => 'PAID', 'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150, 'recipient_name' => 'Buyer', 'recipient_phone' => '09123456789', 'province' => 'Province', 'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street']);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 10000, 'status' => 'completed', 'workflow_status' => OrderStatus::Completed, 'completed_at' => now()]);
        $item = OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'seller_id' => $sellerUser->id, 'product_id' => $product->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'unit_price' => 100, 'quantity' => 1, 'line_total' => 100]);
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
}
