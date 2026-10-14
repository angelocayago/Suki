<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeMessagingContextEntryPointTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_product_chat_uses_the_typed_buyer_entry_and_prevents_duplicates(): void
    {
        $fixture = $this->fixture();
        $route = route('buyer.messages.context.product.store', $fixture['product']);

        $this->post($route)->assertRedirect(route('login'));

        $page = $this->actingAs($fixture['buyer'])
            ->get(route('buyer.product', $fixture['product']->slug));
        $page->assertOk()->assertSee($route, false);

        $first = $this->actingAs($fixture['buyer'])->post($route);
        $conversation = Conversation::sole();
        $first->assertRedirect(route('buyer.messages.show', $conversation));

        $this->actingAs($fixture['buyer'])
            ->post($route)
            ->assertRedirect(route('buyer.messages.show', $conversation));

        $this->assertDatabaseCount('conversations', 1);
        $this->assertSame(
            'STORE_INQUIRY:SELLER:'.$fixture['seller']->id
                .':BUYER:'.$fixture['buyer']->id
                .':PRODUCT:'.$fixture['product']->id,
            $conversation->conversation_key
        );

        $this->actingAs($fixture['seller_user'])->post($route)->assertForbidden();
        $this->actingAs($fixture['buyer'])
            ->post('/buyer/messages/context/products/999999999')
            ->assertNotFound();
    }

    public function test_buyer_order_page_exposes_one_action_per_seller_order_and_keeps_them_isolated(): void
    {
        $fixture = $this->fixture();
        $second = $this->secondSellerOrder($fixture['order']);

        $firstRoute = route(
            'buyer.messages.context.seller-order.store',
            $fixture['seller_order']
        );
        $secondRoute = route(
            'buyer.messages.context.seller-order.store',
            $second['seller_order']
        );

        $this->actingAs($fixture['buyer'])
            ->get(route('buyer.order-details', $fixture['order']->order_number))
            ->assertOk()
            ->assertSee($firstRoute, false)
            ->assertSee($secondRoute, false)
            ->assertSee('Message '.$fixture['seller']->name)
            ->assertSee('Message '.$second['seller']->name);

        $this->actingAs($fixture['buyer'])->post($firstRoute)->assertRedirect();
        $this->actingAs($fixture['buyer'])->post($secondRoute)->assertRedirect();

        $this->assertDatabaseCount('conversations', 2);
        $this->assertDatabaseHas('conversations', [
            'conversation_key' => 'ORDER:SELLER_ORDER:'.$fixture['seller_order']->id,
        ]);
        $this->assertDatabaseHas('conversations', [
            'conversation_key' => 'ORDER:SELLER_ORDER:'.$second['seller_order']->id,
        ]);

        $otherBuyer = $this->user('buyer');
        $this->actingAs($otherBuyer)->post($firstRoute)->assertForbidden();
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_seller_entry_accepts_only_the_store_that_owns_the_database_seller_order(): void
    {
        $fixture = $this->fixture();
        $otherSeller = $this->secondSellerOrder($fixture['order']);
        $route = route(
            'seller.messages.context.seller-order.store',
            $fixture['seller_order']
        );

        $response = $this->actingAs($fixture['seller_user'])->post($route);
        $conversation = Conversation::sole();
        $response->assertRedirect(route('seller.messages.show', $conversation));

        $this->actingAs($otherSeller['seller_user'])
            ->post($route)
            ->assertForbidden();

        $this->actingAs($fixture['seller_user'])
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertSee($route, false)
            ->assertSee('Message Buyer');
    }

    public function test_session_fallback_seller_orders_do_not_render_messaging_actions(): void
    {
        $sellerUser = $this->user('seller');
        Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Session Fallback Store',
            'slug' => 'session-fallback-store',
            'status' => 'approved',
        ]);
        $legacyOrder = [
            'id' => 'LEGACY-SESSION-ORDER',
            'status' => 'placed',
            'payment_method' => 'cod',
            'shipping_address' => ['name' => 'Legacy Buyer'],
            'items' => [],
            'total' => 100,
        ];

        $this->actingAs($sellerUser)
            ->withSession(['orders' => ['LEGACY-SESSION-ORDER' => $legacyOrder]])
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertDontSee('Message Buyer')
            ->assertDontSee('/seller/messages/context/seller-orders/LEGACY-SESSION-ORDER', false);
    }

    private function fixture(): array
    {
        $buyer = $this->user('buyer');
        $sellerUser = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Context Seller',
            'slug' => 'context-seller',
            'status' => 'approved',
        ]);
        $category = Category::create([
            'name' => 'Context Products',
            'slug' => 'context-products',
            'is_active' => true,
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Context Product',
            'slug' => 'context-product',
            'description' => 'A visible product used for context entry testing.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CONTEXT-PRIMARY',
            'name' => 'Default',
            'price_minor' => 10000,
            'stock' => 10,
            'is_active' => true,
        ]);
        $order = $this->order($buyer);
        $sellerOrder = $this->sellerOrder($order, $seller);

        return [
            'buyer' => $buyer,
            'seller_user' => $sellerUser,
            'seller' => $seller,
            'product' => $product,
            'order' => $order,
            'seller_order' => $sellerOrder,
        ];
    }

    private function secondSellerOrder(Order $order): array
    {
        $sellerUser = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Second Context Seller',
            'slug' => 'second-context-seller-'.$sellerUser->id,
            'status' => 'approved',
        ]);

        return [
            'seller_user' => $sellerUser,
            'seller' => $seller,
            'seller_order' => $this->sellerOrder($order, $seller),
        ];
    }

    private function order(User $buyer): Order
    {
        return Order::create([
            'order_number' => 'ORD-CONTEXT-'.$buyer->id,
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Placed->value,
            'payment_method' => 'cod',
            'payment_status' => 'PENDING',
            'subtotal' => 200,
            'shipping_fee' => 20,
            'discount_amount' => 0,
            'total_amount' => 220,
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'City',
            'barangay' => 'Barangay',
            'street_address' => '123 Context Street',
            'placed_at' => now(),
        ]);
    }

    private function sellerOrder(Order $order, Seller $seller): SellerOrder
    {
        return SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 1000,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Placed->value,
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
    }
}
