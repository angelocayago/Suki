<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VersionedBuyerOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated API tests only.');
        }

        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->mock(DomainNotificationService::class)->shouldReceive('afterCommit', 'adminsAfterCommit')->andReturnNull();
    }

    public function test_buyer_order_history_is_paginated_filterable_and_private(): void
    {
        $buyer = $this->buyer();
        $first = $this->placedOrder($buyer, 'ORD-OLDEST');
        $second = $this->placedOrder($buyer, 'ORD-NEWEST');
        $second->update(['status' => 'COMPLETED']);
        $other = $this->placedOrder($this->buyer(), 'ORD-OTHER');
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->api($token, 'GET', '/api/v1/buyer/orders?per_page=1&sort=oldest')
            ->assertOk()->assertJsonPath('data.orders.total', 2)
            ->assertJsonPath('data.orders.data.0.order_number', $first->order_number);
        $this->api($token, 'GET', '/api/v1/buyer/orders?status=completed')
            ->assertOk()->assertJsonCount(1, 'data.orders.data')
            ->assertJsonPath('data.orders.data.0.order_number', $second->order_number);
        $this->api($token, 'GET', '/api/v1/buyer/orders?per_page=101')->assertUnprocessable();
        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$other->order_number)->assertNotFound();
        $this->api($token, 'GET', '/api/v1/buyer/orders/NO-SUCH-ORDER')->assertNotFound();
    }

    public function test_order_detail_and_tracking_use_safe_owned_persisted_data(): void
    {
        $buyer = $this->buyer();
        $order = $this->placedOrder($buyer, 'ORD-TRACKING');
        $parcel = $order->sellerOrders()->sole();
        $shipment = $parcel->shipment;
        $parcel->update(['workflow_status' => OrderStatus::PickedUp, 'status' => 'shipped', 'picked_up_at' => now()]);
        $shipment->update(['workflow_status' => OrderStatus::PickedUp, 'status' => 'in_transit', 'tracking_code' => 'TRACK-SAFE']);
        DeliveryEvent::create(['shipment_id' => $shipment->id, 'status' => 'PICKED_UP', 'attempt' => 0, 'note' => 'private rider note', 'occurred_at' => now()]);
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$order->id)
            ->assertOk()->assertJsonPath('data.order.order_number', 'ORD-TRACKING')
            ->assertJsonPath('data.order.payment_method', 'cod')
            ->assertJsonPath('data.order.items.0.quantity', 2)
            ->assertJsonMissing(['proof_path'])
            ->assertJsonMissing(['private rider note']);
        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$order->order_number.'/tracking')
            ->assertOk()->assertJsonPath('data.tracking.packages.0.tracking_code', 'TRACK-SAFE')
            ->assertJsonPath('data.tracking.packages.0.main.0.code', 'PICKED_UP')
            ->assertJsonMissing(['private rider note']);
        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$order->id.'/seller-orders/'.$parcel->id.'/tracking')
            ->assertOk()->assertJsonPath('data.tracking_code', 'TRACK-SAFE');
    }

    public function test_missing_shipment_and_foreign_tracking_are_safe(): void
    {
        $buyer = $this->buyer();
        $order = $this->placedOrder($buyer, 'ORD-NO-SHIPMENT');
        $order->sellerOrders()->sole()->shipment()->delete();
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$order->order_number.'/tracking')
            ->assertOk()->assertJsonPath('data.tracking.packages.0.delivery_status', 'Shipment not available');
        $foreign = $this->placedOrder($this->buyer(), 'ORD-FOREIGN-TRACKING');
        $this->api($token, 'GET', '/api/v1/buyer/orders/'.$foreign->order_number.'/tracking')->assertNotFound();
    }

    public function test_eligible_cancellation_restores_stock_once_without_changing_cod_accounting(): void
    {
        $buyer = $this->buyer();
        $order = $this->placedOrder($buyer, 'ORD-CANCEL');
        $item = $order->items()->sole();
        $variant = ProductVariant::findOrFail($item->product_variant_id);
        $stockBefore = $variant->stock;
        $money = $order->only(['subtotal', 'shipping_fee', 'total_amount', 'payment_status']);
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->order_number.'/cancel', ['cancel_reason' => 'wrong_product'])
            ->assertOk()->assertJsonPath('data.order.status', 'cancelled');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'CANCELLED', 'cancel_reason' => 'wrong_product']);
        $this->assertSame($stockBefore + 2, $variant->fresh()->stock);
        $this->assertSame($money, $order->fresh()->only(array_keys($money)));
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->id.'/cancel', ['cancel_reason' => 'changed_mind'])->assertOk();
        $this->assertSame($stockBefore + 2, $variant->fresh()->stock);

        $ineligible = $this->placedOrder($buyer, 'ORD-INELIGIBLE');
        $ineligible->update(['status' => 'CONFIRMED']);
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$ineligible->id.'/cancel', ['cancel_reason' => 'changed_mind'])->assertUnprocessable();
        $foreign = $this->placedOrder($this->buyer(), 'ORD-FOREIGN-CANCEL');
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$foreign->id.'/cancel', ['cancel_reason' => 'changed_mind'])->assertNotFound();
    }

    public function test_delivered_buyer_can_confirm_receipt_once_but_not_prematurely(): void
    {
        $buyer = $this->buyer();
        $order = $this->placedOrder($buyer, 'ORD-RECEIVED');
        $parcel = $order->sellerOrders()->sole();
        $shipment = $parcel->shipment;
        $order->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $parcel->update(['workflow_status' => OrderStatus::Delivered, 'status' => 'delivered', 'delivered_at' => now()]);
        $shipment->update(['workflow_status' => OrderStatus::Delivered, 'status' => 'delivered', 'cod_collected' => true, 'proof_path' => 'private-proof.jpg']);
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->id.'/received')->assertOk()
            ->assertJsonPath('data.order.status', 'completed');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'COMPLETED']);
        $this->assertSame(1, DB::table('seller_wallet_entries')->where('seller_order_id', $parcel->id)->count());
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->id.'/confirm-receipt')->assertOk();
        $this->assertSame(1, DB::table('seller_wallet_entries')->where('seller_order_id', $parcel->id)->count());

        $premature = $this->placedOrder($buyer, 'ORD-PREMATURE');
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$premature->id.'/received')->assertUnprocessable();
        $foreign = $this->placedOrder($this->buyer(), 'ORD-FOREIGN-RECEIVED');
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$foreign->id.'/received')->assertNotFound();
    }

    public function test_buy_again_uses_current_price_and_reports_unavailable_lines_without_creating_an_order(): void
    {
        $buyer = $this->buyer();
        $order = $this->placedOrder($buyer, 'ORD-BUY-AGAIN');
        $order->update(['status' => 'COMPLETED']);
        $item = $order->items()->sole();
        $variant = ProductVariant::findOrFail($item->product_variant_id);
        $variant->update(['price_minor' => 12500, 'stock' => 1]);
        $token = $buyer->createToken('buyer')->plainTextToken;
        $orders = Order::count();

        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->order_number.'/buy-again')->assertOk()
            ->assertJsonPath('data.added_units', 1)
            ->assertJsonPath('data.items.0.status', 'adjusted');
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'price' => '125.00']);
        $this->assertSame($orders, Order::count());

        $variant->update(['stock' => 0]);
        $this->api($token, 'POST', '/api/v1/buyer/orders/'.$order->id.'/buy-again')->assertOk()
            ->assertJsonPath('data.items.0.status', 'skipped');
        $this->assertSame($orders, Order::count());
    }

    private function buyer(): User
    {
        return User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false, 'verification_required_at' => null]);
    }

    private function placedOrder(User $buyer, string $number): Order
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'API Order Store '.$number, 'slug' => strtolower($number).'-store', 'status' => 'approved']);
        $category = Category::firstOrCreate(['slug' => 'api-order-category'], ['name' => 'API Order Category', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'API Product '.$number, 'slug' => strtolower($number).'-product', 'description' => 'Fixture', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => $number.'-SKU', 'name' => 'One', 'price_minor' => 9900, 'stock' => 10, 'is_active' => true]);
        $product->images()->create(['path' => 'images/demo-products/lahat-meron/lm001.webp', 'position' => 1]);
        CartItem::create(['user_id' => $buyer->id, 'product_variant_id' => $variant->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'price' => '99.00', 'quantity' => 2]);
        $order = app(CheckoutService::class)->checkout($buyer, [
            'recipient_name' => 'API Buyer', 'recipient_phone' => '09123456789', 'province' => 'Province',
            'municipality' => 'City', 'barangay' => 'Barangay', 'street_address' => 'Street',
        ], 'cod');
        $order->update(['order_number' => $number, 'placed_at' => now()]);

        return $order->fresh(['items', 'sellerOrders.shipment']);
    }

    private function api(string $token, string $method, string $url, array $data = [])
    {
        Auth::forgetGuards();
        Auth::setDefaultDriver('web');

        return $this->json($method, $url, $data, ['Authorization' => 'Bearer '.$token]);
    }
}
