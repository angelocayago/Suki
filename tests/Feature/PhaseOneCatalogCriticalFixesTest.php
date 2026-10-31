<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseOneCatalogCriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_media_is_used_and_placeholder_is_only_used_without_media(): void
    {
        $withImage = $this->product('With image');
        ProductImage::create(['product_id' => $withImage->id, 'path' => 'products/'.$withImage->id.'/photo.webp', 'position' => 1, 'color' => 'Green']);
        $withoutImage = $this->product('Without image');

        $items = app(ProductCatalogService::class)->marketplaceProducts();

        $this->assertStringContainsString('/storage/products/'.$withImage->id.'/photo.webp', $items[(string) $withImage->slug]['image']);
        $this->assertStringNotContainsString('placehold.co', $items[(string) $withImage->slug]['image']);
        $this->assertStringContainsString('placehold.co', $items[(string) $withoutImage->slug]['image']);
    }

    public function test_sold_count_includes_placed_items_and_excludes_cancelled_orders(): void
    {
        $product = $this->product('Sold product');
        $this->assertSame(0, app(ProductCatalogService::class)->marketplaceProducts()[(string) $product->slug]['sold']);
        $order = $this->orderFor($product, 3, 'PLACED', 'pending');
        $this->assertSame(3, app(ProductCatalogService::class)->marketplaceProducts()[(string) $product->slug]['sold']);
        $order->update(['status' => 'CANCELLED']);
        $order->sellerOrders()->update(['status' => 'cancelled']);
        $this->assertSame(0, app(ProductCatalogService::class)->marketplaceProducts()[(string) $product->slug]['sold']);
    }

    private function product(string $name): Product
    {
        $category = Category::create(['name' => $name.' category', 'slug' => str($name)->slug()->append('-c'), 'is_active' => true]);
        $owner = User::factory()->create(['status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => $name.' seller', 'slug' => str($name)->slug()->append('-s'), 'category_id' => $category->id, 'status' => 'approved']);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => $name, 'slug' => str($name)->slug()->append('-p'), 'description' => 'Fixture', 'is_active' => true, 'moderation_status' => ProductModerationStatus::Approved->value]);
        ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.$product->id, 'name' => 'Default', 'price_minor' => 10000, 'stock' => 10, 'is_active' => true]);
        return $product;
    }

    private function orderFor(Product $product, int $quantity, string $status, string $sellerStatus): Order
    {
        $buyer = User::factory()->create();
        $order = Order::create(['order_number' => 'ORD-'.$product->id, 'buyer_id' => $buyer->id, 'seller_id' => $product->seller->user_id, 'status' => $status, 'recipient_name' => 'Buyer', 'recipient_phone' => '09170000000', 'province' => 'Metro Manila', 'municipality' => 'Manila', 'barangay' => 'Test', 'street_address' => 'Test street']);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $product->seller_id, 'subtotal_minor' => 10000 * $quantity, 'shipping_fee_minor' => 5000, 'commission_minor' => 0, 'status' => $sellerStatus, 'workflow_status' => 'PLACED']);
        OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'seller_id' => $product->seller->user_id, 'product_id' => $product->id, 'product_variant_id' => $product->variants()->value('id'), 'product_slug' => (string) $product->slug, 'product_name' => $product->name, 'quantity' => $quantity, 'unit_price' => 100, 'unit_price_minor' => 10000, 'line_total' => 100 * $quantity]);
        return $order;
    }
}
