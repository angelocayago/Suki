<?php

namespace Tests\Feature;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeMessagingInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_inboxes_render_only_authorized_conversations_with_context_and_unread_count(): void
    {
        $fixture = $this->fixture();
        $conversation = $this->orderConversation($fixture);
        $this->service()->sendMessage($conversation, $fixture['seller_user'], 'Your order is being prepared.');

        $this->actingAs($fixture['buyer'])
            ->get(route('buyer.messages.index'))
            ->assertOk()
            ->assertSee('Order preparation')
            ->assertSee($fixture['order']->order_number)
            ->assertSee('Your order is being prepared.')
            ->assertSee('1');

        $this->actingAs($fixture['seller_user'])
            ->get(route('seller.messages.show', $conversation->reference))
            ->assertOk()
            ->assertSee('Your order is being prepared.')
            ->assertSee('Seller');
    }

    public function test_non_participants_cannot_view_poll_send_or_mark_a_conversation_read(): void
    {
        $fixture = $this->fixture();
        $conversation = $this->orderConversation($fixture);
        $message = $this->service()->sendMessage($conversation, $fixture['seller_user'], 'Private order update.');
        $otherBuyer = $this->user('buyer');

        $this->actingAs($otherBuyer)->get(route('buyer.messages.show', $conversation->reference))->assertForbidden();
        $this->actingAs($otherBuyer)->getJson(route('buyer.messages.poll', $conversation->reference))->assertForbidden();
        $this->actingAs($otherBuyer)->post(route('buyer.messages.store', $conversation->reference), [
            'body' => 'Unauthorized message',
        ])->assertForbidden();
        $this->actingAs($otherBuyer)->postJson(route('buyer.messages.read', $conversation->reference), [
            'message_id' => $message->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 1);
    }

    public function test_portal_role_context_blocks_multi_role_cross_portal_access(): void
    {
        $fixture = $this->fixture();
        $buyerRole = Role::firstOrCreate(['name' => 'buyer']);
        $fixture['seller_user']->roles()->syncWithoutDetaching([$buyerRole->id]);
        $conversation = $this->orderConversation($fixture);

        $this->actingAs($fixture['seller_user'])
            ->get(route('seller.messages.show', $conversation->reference))
            ->assertOk();
        $this->actingAs($fixture['seller_user'])
            ->get(route('buyer.messages.show', $conversation->reference))
            ->assertForbidden();
    }

    public function test_multi_store_and_multi_seller_conversations_remain_isolated(): void
    {
        $fixture = $this->fixture();
        $secondOwnedStore = Seller::create([
            'user_id' => $fixture['seller_user']->id,
            'name' => 'Second Owned Store',
            'slug' => 'interface-second-owned-store',
            'status' => 'approved',
        ]);
        $secondOwned = $this->appendSellerOrder(
            $fixture['order'], $fixture['provider'], 'owned-second', $fixture['seller_user'], $secondOwnedStore
        );
        $otherSeller = $this->appendSellerOrder($fixture['order'], $fixture['provider'], 'other-seller');

        $firstConversation = $this->orderConversation($fixture);
        $secondConversation = $this->service()->createConversation(
            $fixture['seller_user'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $secondOwned['seller_order']],
            subject: 'Second store order'
        );
        $otherConversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $otherSeller['seller_order']],
            subject: 'Other seller order'
        );

        $response = $this->actingAs($fixture['seller_user'])->get(route('seller.messages.index'));
        $response->assertOk()
            ->assertSee($firstConversation->subject)
            ->assertSee($secondConversation->subject)
            ->assertDontSee($otherConversation->subject);

        $this->actingAs($fixture['seller_user'])
            ->get(route('seller.messages.show', $otherConversation->reference))
            ->assertForbidden();
    }

    public function test_rider_and_logistics_routes_revalidate_current_assignments(): void
    {
        $fixture = $this->fixture();
        $admin = $this->user('admin');
        $pickupConversation = $this->service()->createConversation(
            $fixture['pickup_user'],
            ConversationType::RiderSupport,
            ['shipment' => $fixture['shipment'], 'rider' => $fixture['pickup_rider']],
            [$admin],
            'Pickup support'
        );
        $deliveryConversation = $this->service()->createConversation(
            $fixture['delivery_user'],
            ConversationType::RiderSupport,
            ['shipment' => $fixture['shipment'], 'rider' => $fixture['delivery_rider']],
            [$admin],
            'Delivery support'
        );
        $logisticsConversation = $this->service()->createConversation(
            $fixture['logistics_user'],
            ConversationType::LogisticsSupport,
            ['shipment' => $fixture['shipment']],
            [$admin],
            'Parcel support'
        );

        $this->actingAs($fixture['pickup_user'])
            ->get(route('rider.messages.show', $pickupConversation->reference))
            ->assertOk();
        $this->actingAs($fixture['pickup_user'])
            ->get(route('rider.messages.show', $deliveryConversation->reference))
            ->assertForbidden();

        $otherLogisticsUser = $this->user('logistics');
        LogisticsProvider::create([
            'user_id' => $otherLogisticsUser->id,
            'name' => 'Other Interface Logistics',
            'slug' => 'other-interface-logistics',
            'status' => 'approved',
        ]);
        $this->actingAs($fixture['logistics_user'])
            ->get(route('logistics.messages.show', $logisticsConversation->reference))
            ->assertOk();
        $this->actingAs($otherLogisticsUser)
            ->get(route('logistics.messages.show', $logisticsConversation->reference))
            ->assertForbidden();

        $fixture['shipment']->update(['pickup_rider_id' => null]);
        $this->actingAs($fixture['pickup_user'])
            ->get(route('rider.messages.show', $pickupConversation->reference))
            ->assertForbidden();
    }

    public function test_polling_is_read_only_and_read_endpoint_advances_the_cursor(): void
    {
        $fixture = $this->fixture();
        $conversation = $this->orderConversation($fixture);
        $message = $this->service()->sendMessage($conversation, $fixture['seller_user'], '<script>alert(1)</script>');

        $this->actingAs($fixture['buyer'])
            ->getJson(route('buyer.messages.poll', [
                'conversation' => $conversation->reference,
                'after_id' => 0,
            ]))
            ->assertOk()
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.body', '<script>alert(1)</script>')
            ->assertJsonMissingPath('messages.0.sender.email');

        $this->assertNull($conversation->participants()->where('user_id', $fixture['buyer']->id)->value('last_read_message_id'));

        $this->actingAs($fixture['buyer'])
            ->postJson(route('buyer.messages.read', $conversation->reference), ['message_id' => $message->id])
            ->assertNoContent();

        $this->assertSame(
            $message->id,
            $conversation->participants()->where('user_id', $fixture['buyer']->id)->value('last_read_message_id')
        );
        $this->actingAs($fixture['buyer'])
            ->get(route('buyer.messages.show', $conversation->reference))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_send_route_uses_foundation_rules_and_rejects_closed_conversations(): void
    {
        $fixture = $this->fixture();
        $conversation = $this->orderConversation($fixture);

        $this->actingAs($fixture['buyer'])
            ->post(route('buyer.messages.store', $conversation->reference), ['body' => 'Buyer interface message.'])
            ->assertRedirect(route('buyer.messages.show', $conversation->reference));
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $fixture['buyer']->id,
            'body' => 'Buyer interface message.',
        ]);

        $conversation->update(['status' => ConversationStatus::Closed, 'closed_at' => now()]);
        $this->actingAs($fixture['seller_user'])
            ->post(route('seller.messages.store', $conversation->reference), ['body' => 'Late message.'])
            ->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_admin_access_requires_active_participation_and_messaging_does_not_mutate_erp_state(): void
    {
        $fixture = $this->fixture();
        $admin = $this->user('admin');
        $otherAdmin = $this->user('admin');
        $conversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::AdminSupport,
            [],
            [$admin],
            'Buyer support'
        );
        $before = $this->erpState($fixture);

        $this->actingAs($admin)
            ->get(route('superadmin.messages.show', $conversation->reference))
            ->assertOk();
        $this->actingAs($otherAdmin)
            ->get(route('superadmin.messages.show', $conversation->reference))
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('superadmin.messages.store', $conversation->reference), ['body' => 'Admin response.'])
            ->assertRedirect(route('superadmin.messages.show', $conversation->reference));

        $this->assertSame($before, $this->erpState($fixture));
    }

    private function service(): MessagingService
    {
        return app(MessagingService::class);
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ], $attributes));
    }

    private function fixture(): array
    {
        $buyer = $this->user('buyer');
        $sellerUser = $this->user('seller');
        $logisticsUser = $this->user('logistics');
        $pickupUser = $this->user('rider');
        $deliveryUser = $this->user('rider');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => 'Interface Store',
            'slug' => 'interface-store',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Interface Logistics',
            'slug' => 'interface-logistics',
            'status' => 'approved',
        ]);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'UI-PICKUP',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'UI-DELIVERY',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $order = Order::create([
            'order_number' => 'ORD-UI-'.$buyer->id,
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Preparing->value,
            'payment_method' => 'cod',
            'payment_status' => 'PENDING',
            'subtotal' => 100,
            'shipping_fee' => 10,
            'discount_amount' => 0,
            'total_amount' => 110,
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789',
            'province' => 'Province',
            'municipality' => 'City',
            'barangay' => 'Barangay',
            'street_address' => '123 Street',
            'placed_at' => now(),
        ]);
        $sellerOrderData = $this->appendSellerOrder($order, $provider, 'primary', $sellerUser, $seller);
        $shipment = $sellerOrderData['shipment'];
        $shipment->update(['pickup_rider_id' => $pickupRider->id, 'rider_id' => $deliveryRider->id]);

        return $sellerOrderData + compact(
            'buyer', 'seller', 'provider', 'order', 'shipment'
        ) + [
            'seller_user' => $sellerUser,
            'logistics_user' => $logisticsUser,
            'pickup_user' => $pickupUser,
            'delivery_user' => $deliveryUser,
            'pickup_rider' => $pickupRider,
            'delivery_rider' => $deliveryRider,
        ];
    }

    private function appendSellerOrder(
        Order $order,
        LogisticsProvider $provider,
        string $suffix,
        ?User $sellerUser = null,
        ?Seller $seller = null,
    ): array {
        $sellerUser ??= $this->user('seller');
        $seller ??= Seller::create([
            'user_id' => $sellerUser->id,
            'name' => str($suffix)->headline().' Interface Store',
            'slug' => $suffix.'-interface-store',
            'status' => 'approved',
        ]);
        $category = Category::firstOrCreate(
            ['slug' => 'interface-category'],
            ['name' => 'Interface Category', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($suffix)->headline().' Interface Product',
            'slug' => $suffix.'-interface-product-'.$seller->id,
            'description' => 'Messaging interface test product.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($suffix).'-UI-'.$seller->id,
            'name' => 'Default',
            'price_minor' => 10000,
            'stock' => 10,
            'is_active' => true,
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 1000,
            'commission_minor' => 1000,
            'status' => 'pending',
            'workflow_status' => OrderStatus::Preparing->value,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id,
            'tracking_code' => 'TRK-UI-'.strtoupper($suffix).'-'.$sellerOrder->id,
            'status' => 'unassigned',
            'workflow_status' => OrderStatus::Preparing->value,
            'fee_minor' => 1000,
            'cod_amount_minor' => 11000,
            'cod_collected' => false,
            'attempts' => 0,
        ]);

        return compact('sellerUser', 'seller', 'product', 'variant', 'sellerOrder', 'shipment') + [
            'seller_user' => $sellerUser,
            'seller_order' => $sellerOrder,
        ];
    }

    private function orderConversation(array $fixture): Conversation
    {
        return $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']],
            subject: 'Order preparation'
        );
    }

    private function erpState(array $fixture): array
    {
        return [
            'order' => $fixture['order']->fresh()->status,
            'payment' => $fixture['order']->fresh()->payment_status,
            'seller_order' => $fixture['seller_order']->fresh()->workflow_status->value,
            'shipment' => $fixture['shipment']->fresh()->workflow_status->value,
            'stock' => $fixture['variant']->fresh()->stock,
            'moderation' => $fixture['product']->fresh()->moderation_status->value,
        ];
    }
}
