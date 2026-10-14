<?php

namespace Tests\Feature;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use App\Enums\MessageType;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PhaseThreeMessagingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_inquiry_uses_persisted_marketplace_context_and_prevents_duplicates(): void
    {
        $fixture = $this->fixture();

        $first = $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product'],
            'Product question'
        );
        $duplicate = $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product'],
            'A different subject'
        );

        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame(
            'STORE_INQUIRY:SELLER:'.$fixture['seller']->id
                .':BUYER:'.$fixture['buyer']->id
                .':PRODUCT:'.$fixture['product']->id,
            $first->conversation_key
        );
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $fixture['buyer']->id,
            'role_context' => 'BUYER',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $fixture['seller_user']->id,
            'role_context' => 'SELLER',
            'seller_id' => $fixture['seller']->id,
        ]);
    }

    public function test_product_inquiry_rejects_legacy_and_non_visible_products(): void
    {
        $fixture = $this->fixture();
        $legacyProduct = new Product([
            'name' => 'Legacy hardcoded product',
            'slug' => 'legacy-hardcoded-product',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);

        try {
            $this->service()->createProductInquiry($fixture['buyer'], $legacyProduct);
            $this->fail('A non-persisted legacy product should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }

        $fixture['product']->update(['is_active' => false]);
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product']
        ));

        $fixture['product']->update([
            'is_active' => true,
            'moderation_status' => 'REJECTED',
        ]);
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product']
        ));

        $fixture['product']->update(['moderation_status' => 'HIDDEN']);
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product']
        ));
    }

    public function test_product_inquiry_requires_an_active_buyer_and_active_approved_seller(): void
    {
        $fixture = $this->fixture();
        $suspendedBuyer = $this->user('buyer', ['is_suspended' => true]);

        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $suspendedBuyer,
            $fixture['product']
        ));
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['seller_user'],
            $fixture['product']
        ));

        $fixture['seller']->update(['status' => 'suspended']);
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product']
        ));

        $fixture['seller']->update(['status' => 'approved']);
        $fixture['seller_user']->update(['is_suspended' => true]);
        $this->assertForbidden(fn () => $this->service()->createProductInquiry(
            $fixture['buyer'],
            $fixture['product']
        ));
    }

    public function test_typed_seller_order_conversations_enforce_ownership_and_multi_seller_isolation(): void
    {
        $fixture = $this->fixture();
        $otherBuyer = $this->user('buyer');
        $second = $this->appendSellerOrder($fixture['order'], $fixture['provider'], 'typed-second');

        $buyerConversation = $this->service()->createSellerOrderConversation(
            $fixture['buyer'],
            $fixture['seller_order']
        );
        $sellerConversation = $this->service()->createSellerOrderConversation(
            $fixture['seller_user'],
            $fixture['seller_order']
        );
        $secondConversation = $this->service()->createSellerOrderConversation(
            $fixture['buyer'],
            $second['seller_order']
        );

        $this->assertSame($buyerConversation->id, $sellerConversation->id);
        $this->assertNotSame($buyerConversation->id, $secondConversation->id);
        $this->assertSame(
            'ORDER:SELLER_ORDER:'.$fixture['seller_order']->id,
            $buyerConversation->conversation_key
        );
        $this->assertSame(
            'ORDER:SELLER_ORDER:'.$second['seller_order']->id,
            $secondConversation->conversation_key
        );

        $this->assertForbidden(fn () => $this->service()->createSellerOrderConversation(
            $otherBuyer,
            $fixture['seller_order']
        ));
        $this->assertForbidden(fn () => $this->service()->createSellerOrderConversation(
            $fixture['seller_user'],
            $second['seller_order']
        ));
    }

    public function test_admin_support_key_is_stable_when_the_participating_admin_changes(): void
    {
        $fixture = $this->fixture();
        $firstAdmin = $this->user('admin');
        $secondAdmin = $this->user('superadmin');

        $first = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::AdminSupport,
            [],
            [$firstAdmin]
        );
        $second = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::AdminSupport,
            [],
            [$secondAdmin]
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            'ADMIN_SUPPORT:USER:'.$fixture['buyer']->id.':SELLER:0',
            $first->conversation_key
        );
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $firstAdmin->id,
            'role_context' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $secondAdmin->id,
            'role_context' => 'ADMIN',
        ]);
    }

    public function test_context_conversation_is_created_once_with_derived_participants(): void
    {
        $fixture = $this->fixture();

        $first = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']],
            subject: 'Order help'
        );
        $duplicate = $this->service()->createConversation(
            $fixture['seller_user'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']],
            subject: 'A different duplicate subject'
        );

        $this->assertSame($first->id, $duplicate->id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        $this->assertSame('ORDER:SELLER_ORDER:'.$fixture['seller_order']->id, $first->conversation_key);
        $this->assertSame(ConversationType::Order, $first->type);
        $this->assertSame(ConversationStatus::Active, $first->status);
        $this->assertSame($fixture['order']->order_number, $first->context_snapshot['order']['order_number']);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $fixture['buyer']->id,
            'role_context' => 'BUYER',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $first->id,
            'user_id' => $fixture['seller_user']->id,
            'role_context' => 'SELLER',
            'seller_id' => $fixture['seller']->id,
        ]);
    }

    public function test_buyer_and_multi_seller_order_isolation_is_enforced(): void
    {
        $fixture = $this->fixture();
        $otherBuyer = $this->user('buyer');
        $second = $this->appendSellerOrder($fixture['order'], $fixture['provider'], 'second');

        $firstConversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']]
        );
        $secondConversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $second['seller_order']]
        );

        $this->assertNotSame($firstConversation->id, $secondConversation->id);
        $this->assertFalse($this->service()->canAccess($firstConversation, $otherBuyer));
        $this->assertFalse($this->service()->canAccess($secondConversation, $fixture['seller_user']));
        $this->assertTrue($this->service()->canAccess($secondConversation, $second['seller_user']));

        $this->assertForbidden(fn () => $this->service()->createConversation(
            $otherBuyer,
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']]
        ));
        $this->assertForbidden(fn () => $this->service()->createConversation(
            $fixture['seller_user'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $second['seller_order']]
        ));
    }

    public function test_multi_store_seller_membership_keeps_an_explicit_store_context(): void
    {
        $fixture = $this->fixture();
        $secondStore = Seller::create([
            'user_id' => $fixture['seller_user']->id,
            'name' => 'Second Owned Store',
            'slug' => 'second-owned-store',
            'status' => 'approved',
        ]);
        $second = $this->appendSellerOrder(
            $fixture['order'],
            $fixture['provider'],
            'owned-second',
            $fixture['seller_user'],
            $secondStore
        );

        $firstConversation = $this->service()->createConversation(
            $fixture['seller_user'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']]
        );
        $secondConversation = $this->service()->createConversation(
            $fixture['seller_user'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $second['seller_order']]
        );

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $firstConversation->id,
            'user_id' => $fixture['seller_user']->id,
            'seller_id' => $fixture['seller']->id,
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $secondConversation->id,
            'user_id' => $fixture['seller_user']->id,
            'seller_id' => $secondStore->id,
        ]);
        $this->assertSame(2, ConversationParticipant::where('user_id', $fixture['seller_user']->id)
            ->where('role_context', 'SELLER')->count());
    }

    public function test_pickup_and_delivery_riders_are_isolated_by_current_assignment(): void
    {
        $fixture = $this->fixture();
        $unassignedUser = $this->user('rider');
        $unassignedRider = Rider::create([
            'user_id' => $unassignedUser->id,
            'logistics_provider_id' => $fixture['provider']->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'UNASSIGNED',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $pickupConversation = $this->service()->createRiderShipmentConversation(
            $fixture['pickup_user'],
            $fixture['shipment']
        );
        $pickupDuplicate = $this->service()->createRiderShipmentConversation(
            $fixture['pickup_user'],
            $fixture['shipment']
        );
        $deliveryConversation = $this->service()->createRiderShipmentConversation(
            $fixture['delivery_user'],
            $fixture['shipment']
        );

        $this->assertSame($pickupConversation->id, $pickupDuplicate->id);
        $this->assertStringContainsString(':PICKUP_RIDER', $pickupConversation->conversation_key);
        $this->assertStringContainsString(':DELIVERY_RIDER', $deliveryConversation->conversation_key);
        $this->assertTrue($this->service()->canAccess($pickupConversation, $fixture['pickup_user']));
        $this->assertFalse($this->service()->canAccess($pickupConversation, $fixture['delivery_user']));
        $this->assertTrue($this->service()->canAccess($deliveryConversation, $fixture['delivery_user']));
        $this->assertFalse($this->service()->canAccess($deliveryConversation, $fixture['pickup_user']));
        $this->assertFalse($this->service()->canAccess($pickupConversation, $unassignedUser));

        $this->assertForbidden(fn () => $this->service()->createRiderShipmentConversation(
            $unassignedUser,
            $fixture['shipment']
        ));

        $fixture['shipment']->update(['pickup_rider_id' => $unassignedRider->id]);
        $this->assertFalse($this->service()->canAccess($pickupConversation->fresh(), $fixture['pickup_user']));
        $this->assertTrue($this->service()->canAccess(
            $this->service()->createRiderShipmentConversation($unassignedUser, $fixture['shipment']->fresh()),
            $unassignedUser
        ));
    }

    public function test_logistics_access_requires_the_provider_handling_the_shipment(): void
    {
        $fixture = $this->fixture();
        $otherLogisticsUser = $this->user('logistics');
        $otherProvider = LogisticsProvider::create([
            'user_id' => $otherLogisticsUser->id,
            'name' => 'Other Logistics',
            'slug' => 'other-logistics',
            'status' => 'approved',
        ]);

        $conversation = $this->service()->createLogisticsShipmentConversation(
            $fixture['logistics_user'],
            $fixture['shipment']
        );
        $duplicate = $this->service()->createLogisticsShipmentConversation(
            $fixture['logistics_user'],
            $fixture['shipment']
        );

        $this->assertSame($conversation->id, $duplicate->id);
        $this->assertTrue($this->service()->canAccess($conversation, $fixture['logistics_user']));
        $this->assertFalse($this->service()->canAccess($conversation, $otherLogisticsUser));
        $this->assertForbidden(fn () => $this->service()->createLogisticsShipmentConversation(
            $otherLogisticsUser,
            $fixture['shipment']
        ));

        $fixture['shipment']->update(['logistics_provider_id' => $otherProvider->id]);
        $this->assertFalse($this->service()->canAccess($conversation->fresh(), $fixture['logistics_user']));
        $this->assertTrue($this->service()->canAccess(
            $this->service()->createLogisticsShipmentConversation($otherLogisticsUser, $fixture['shipment']->fresh()),
            $otherLogisticsUser
        ));

        $fixture['shipment']->update(['logistics_provider_id' => null]);
        $this->assertForbidden(fn () => $this->service()->createLogisticsShipmentConversation(
            $otherLogisticsUser,
            $fixture['shipment']->fresh()
        ));
    }

    public function test_complaint_conversation_allows_only_the_filer_and_current_assigned_admin(): void
    {
        $fixture = $this->fixture();
        $assignedAdmin = $this->user('admin');
        $replacementAdmin = $this->user('superadmin');
        $unrelatedAdmin = $this->user('admin');
        $complaint = Complaint::create([
            'reference' => 'CMP-ACCESS-'.$fixture['order']->id,
            'complainant_id' => $fixture['buyer']->id,
            'complainant_role' => 'BUYER',
            'order_id' => $fixture['order']->id,
            'seller_order_id' => $fixture['seller_order']->id,
            'shipment_id' => $fixture['shipment']->id,
            'buyer_id' => $fixture['buyer']->id,
            'seller_id' => $fixture['seller']->id,
            'product_id' => $fixture['product']->id,
            'assigned_to' => $assignedAdmin->id,
            'assigned_at' => now(),
            'category' => ComplaintCategory::Other,
            'status' => ComplaintStatus::Open,
            'subject' => 'Assigned complaint conversation',
            'description' => 'Only the filer and current assignee may participate.',
            'resolution_notes' => 'Private resolution notes must not enter messaging context.',
        ]);

        $filerConversation = $this->service()->createComplaintConversation($fixture['buyer'], $complaint);
        $adminConversation = $this->service()->createComplaintConversation($assignedAdmin, $complaint);

        $this->assertSame($filerConversation->id, $adminConversation->id);
        $this->assertSame('COMPLAINT:'.$complaint->id, $filerConversation->conversation_key);
        $this->assertTrue($this->service()->canAccess($filerConversation, $fixture['buyer']));
        $this->assertTrue($this->service()->canAccess($filerConversation, $assignedAdmin));
        $this->assertArrayNotHasKey('resolution_notes', $filerConversation->context_snapshot);
        $this->assertForbidden(fn () => $this->service()->createComplaintConversation(
            $unrelatedAdmin,
            $complaint
        ));

        $complaint->update([
            'assigned_to' => $replacementAdmin->id,
            'assigned_at' => now(),
        ]);

        $this->assertFalse($this->service()->canAccess($filerConversation->fresh(), $assignedAdmin));
        $replacementConversation = $this->service()->createComplaintConversation(
            $replacementAdmin,
            $complaint->fresh()
        );
        $this->assertSame($filerConversation->id, $replacementConversation->id);
        $this->assertTrue($this->service()->canAccess($replacementConversation, $replacementAdmin));
        $this->assertSame(ComplaintStatus::Open, $complaint->fresh()->status);
    }

    public function test_only_active_admins_can_participate_and_policy_uses_service_boundaries(): void
    {
        $fixture = $this->fixture();
        $admin = $this->user('admin');
        $suspendedAdmin = $this->user('admin', ['is_suspended' => true]);
        $inactiveAdmin = $this->user('superadmin', ['status' => 'inactive']);

        $conversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::AdminSupport,
            [],
            [$admin]
        );

        $this->assertTrue(Gate::forUser($admin)->allows('view', $conversation));
        $this->assertTrue(Gate::forUser($admin)->allows('send', $conversation));
        $this->assertFalse(Gate::forUser($suspendedAdmin)->allows('view', $conversation));

        foreach ([$suspendedAdmin, $inactiveAdmin] as $invalidAdmin) {
            $this->assertForbidden(fn () => $this->service()->createConversation(
                $fixture['buyer'],
                ConversationType::AdminSupport,
                [],
                [$invalidAdmin]
            ));
        }
    }

    public function test_messages_and_read_cursors_are_transactionally_updated(): void
    {
        $fixture = $this->fixture();
        $conversation = $this->service()->createConversation(
            $fixture['buyer'],
            ConversationType::Order,
            ['order' => $fixture['order'], 'seller_order' => $fixture['seller_order']]
        );

        $first = $this->service()->sendMessage($conversation, $fixture['buyer'], 'Is this ready?');

        $this->assertSame(MessageType::Text, $first->type);
        $this->assertSame(0, $this->service()->unreadCount($conversation, $fixture['buyer']));
        $this->assertSame(1, $this->service()->unreadCount($conversation, $fixture['seller_user']));
        $this->assertNotNull($conversation->fresh()->last_message_at);

        $this->service()->markRead($conversation, $fixture['seller_user'], $first);
        $this->assertSame(0, $this->service()->unreadCount($conversation, $fixture['seller_user']));

        $reply = $this->service()->sendMessage(
            $conversation,
            $fixture['seller_user'],
            'It is being prepared.',
            replyTo: $first
        );

        $this->assertSame($first->id, $reply->reply_to_id);
        $this->assertSame(1, $this->service()->unreadCount($conversation, $fixture['buyer']));
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversation->id,
            'user_id' => $fixture['seller_user']->id,
            'last_read_message_id' => $reply->id,
        ]);
    }

    public function test_messaging_never_mutates_erp_or_complaint_state(): void
    {
        $fixture = $this->fixture();
        $admin = $this->user('admin');
        $complaint = Complaint::create([
            'reference' => 'CMP-MSG-'.$fixture['order']->id,
            'complainant_id' => $fixture['buyer']->id,
            'complainant_role' => 'BUYER',
            'order_id' => $fixture['order']->id,
            'seller_order_id' => $fixture['seller_order']->id,
            'shipment_id' => $fixture['shipment']->id,
            'buyer_id' => $fixture['buyer']->id,
            'seller_id' => $fixture['seller']->id,
            'product_id' => $fixture['product']->id,
            'assigned_to' => $admin->id,
            'assigned_at' => now(),
            'category' => ComplaintCategory::Other,
            'status' => ComplaintStatus::Open,
            'subject' => 'Messaging immutability check',
            'description' => 'No ERP state may be changed.',
        ]);
        $before = $this->erpState($fixture, $complaint);

        $conversation = $this->service()->createComplaintConversation($fixture['buyer'], $complaint);
        $message = $this->service()->sendMessage($conversation, $fixture['buyer'], 'Supporting explanation.');
        $this->service()->markRead($conversation, $admin, $message);

        $this->assertSame($before, $this->erpState($fixture, $complaint));
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_rider_shipment_entry_points_keep_pickup_and_delivery_assignments_isolated(): void
    {
        $fixture = $this->fixture();
        $pickupRoute = route('rider.messages.context.shipment.store', $fixture['seller_order']);

        $this->post($pickupRoute)->assertRedirect(route('login'));

        $pickupResponse = $this->actingAs($fixture['pickup_user'])->post($pickupRoute);
        $pickupConversation = Conversation::where('created_by', $fixture['pickup_user']->id)->sole();
        $pickupResponse->assertRedirect(route('rider.messages.show', $pickupConversation));

        $this->actingAs($fixture['pickup_user'])
            ->post($pickupRoute)
            ->assertRedirect(route('rider.messages.show', $pickupConversation));

        $fixture['seller_order']->update(['workflow_status' => OrderStatus::ReadyForPickup]);
        $fixture['shipment']->update(['workflow_status' => OrderStatus::ReadyForPickup]);
        $this->actingAs($fixture['pickup_user'])
            ->get(route('rider.deliveries'))
            ->assertOk()
            ->assertSee($pickupRoute, false);

        $deliveryResponse = $this->actingAs($fixture['delivery_user'])->post($pickupRoute);
        $deliveryConversation = Conversation::where('created_by', $fixture['delivery_user']->id)->sole();
        $deliveryResponse->assertRedirect(route('rider.messages.show', $deliveryConversation));

        $this->assertNotSame($pickupConversation->id, $deliveryConversation->id);
        $this->assertDatabaseCount('conversations', 2);

        $otherUser = $this->user('rider');
        $otherRider = Rider::create([
            'user_id' => $otherUser->id,
            'logistics_provider_id' => $fixture['provider']->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'MSG-REASSIGNED',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        $this->actingAs($otherUser)->post($pickupRoute)->assertForbidden();

        $fixture['shipment']->update(['pickup_rider_id' => $otherRider->id]);
        $this->actingAs($fixture['pickup_user'])
            ->get(route('rider.messages.show', $pickupConversation))
            ->assertForbidden();
        $this->actingAs($fixture['pickup_user'])->post($pickupRoute)->assertForbidden();

        $replacement = $this->actingAs($otherUser)->post($pickupRoute);
        $replacementConversation = Conversation::where('created_by', $otherUser->id)->sole();
        $replacement->assertRedirect(route('rider.messages.show', $replacementConversation));
    }

    public function test_logistics_shipment_entry_point_enforces_current_provider_ownership(): void
    {
        $fixture = $this->fixture();
        $route = route('logistics.messages.context.shipment.store', $fixture['seller_order']);

        $response = $this->actingAs($fixture['logistics_user'])->post($route);
        $conversation = Conversation::sole();
        $response->assertRedirect(route('logistics.messages.show', $conversation));

        $this->actingAs($fixture['logistics_user'])
            ->post($route)
            ->assertRedirect(route('logistics.messages.show', $conversation));
        $this->assertDatabaseCount('conversations', 1);

        $fixture['seller_order']->update(['workflow_status' => OrderStatus::AtSortingCenter]);
        $fixture['shipment']->update(['workflow_status' => OrderStatus::AtSortingCenter]);
        $this->actingAs($fixture['logistics_user'])
            ->get(route('logistics.parcels'))
            ->assertOk()
            ->assertSee($route, false);

        $otherUser = $this->user('logistics');
        $otherProvider = LogisticsProvider::create([
            'user_id' => $otherUser->id,
            'name' => 'Reassigned Logistics',
            'slug' => 'reassigned-logistics',
            'status' => 'approved',
        ]);

        $this->actingAs($otherUser)->post($route)->assertForbidden();
        $fixture['shipment']->update(['logistics_provider_id' => $otherProvider->id]);

        $this->actingAs($fixture['logistics_user'])
            ->get(route('logistics.messages.show', $conversation))
            ->assertForbidden();
        $this->actingAs($fixture['logistics_user'])->post($route)->assertForbidden();

        $replacement = $this->actingAs($otherUser)->post($route);
        $replacementConversation = Conversation::where('created_by', $otherUser->id)->sole();
        $replacement->assertRedirect(route('logistics.messages.show', $replacementConversation));
    }

    public function test_complaint_entry_points_allow_only_filer_and_current_assigned_admin(): void
    {
        $fixture = $this->fixture();
        $assignedAdmin = $this->user('admin');
        $replacementAdmin = $this->user('superadmin');
        $unrelatedAdmin = $this->user('admin');
        $otherBuyer = $this->user('buyer');
        $complaint = Complaint::create([
            'reference' => 'CMP-ENTRY-'.$fixture['order']->id,
            'complainant_id' => $fixture['buyer']->id,
            'complainant_role' => 'BUYER',
            'order_id' => $fixture['order']->id,
            'seller_order_id' => $fixture['seller_order']->id,
            'shipment_id' => $fixture['shipment']->id,
            'buyer_id' => $fixture['buyer']->id,
            'seller_id' => $fixture['seller']->id,
            'product_id' => $fixture['product']->id,
            'assigned_to' => $assignedAdmin->id,
            'assigned_at' => now(),
            'category' => ComplaintCategory::Other,
            'status' => ComplaintStatus::Open,
            'subject' => 'Complaint messaging entry',
            'description' => 'Only the filer and current assigned admin can communicate.',
            'resolution_notes' => 'Internal notes must remain outside messaging.',
        ]);

        $participantRoute = route('complaints.messages.context.store', $complaint);
        $adminRoute = route('superadmin.messages.context.complaint.store', $complaint);

        $this->actingAs($fixture['buyer'])
            ->get(route('complaints.mine.show', $complaint))
            ->assertOk()
            ->assertSee($participantRoute, false);

        $participantResponse = $this->actingAs($fixture['buyer'])->post($participantRoute);
        $conversation = Conversation::where('complaint_id', $complaint->id)->sole();
        $participantResponse->assertRedirect(route('buyer.messages.show', $conversation));

        $this->actingAs($assignedAdmin)
            ->get(route('superadmin.complaints.show', $complaint))
            ->assertOk()
            ->assertSee($adminRoute, false);
        $this->actingAs($assignedAdmin)
            ->post($adminRoute)
            ->assertRedirect(route('superadmin.messages.show', $conversation));

        $this->actingAs($fixture['buyer'])
            ->post(route('buyer.messages.store', $conversation), ['body' => 'Buyer complaint message'])
            ->assertRedirect();
        $this->actingAs($assignedAdmin)
            ->post(route('superadmin.messages.store', $conversation), ['body' => 'Assigned admin response'])
            ->assertRedirect();

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        $this->assertDatabaseCount('messages', 2);
        $this->assertArrayNotHasKey('resolution_notes', $conversation->context_snapshot);

        $this->actingAs($otherBuyer)->post($participantRoute)->assertForbidden();
        $this->actingAs($unrelatedAdmin)->post($adminRoute)->assertForbidden();

        $complaint->update(['assigned_to' => $replacementAdmin->id, 'assigned_at' => now()]);
        $this->actingAs($assignedAdmin)
            ->get(route('superadmin.messages.show', $conversation))
            ->assertForbidden();
        $this->actingAs($assignedAdmin)->post($adminRoute)->assertForbidden();

        $this->actingAs($replacementAdmin)
            ->post($adminRoute)
            ->assertRedirect(route('superadmin.messages.show', $conversation));
        $this->assertDatabaseCount('conversations', 1);
        $this->assertSame(ComplaintStatus::Open, $complaint->fresh()->status);
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
            'name' => 'Messaging Store',
            'slug' => 'messaging-store',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Messaging Logistics',
            'slug' => 'messaging-logistics',
            'status' => 'approved',
        ]);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'MSG-PICKUP',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'MSG-DELIVERY',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $order = Order::create([
            'order_number' => 'ORD-MSG-'.$buyer->id,
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
        $shipment->update([
            'pickup_rider_id' => $pickupRider->id,
            'rider_id' => $deliveryRider->id,
        ]);

        return $sellerOrderData + compact(
            'buyer', 'sellerUser', 'logisticsUser', 'pickupUser', 'deliveryUser',
            'seller', 'provider', 'pickupRider', 'deliveryRider', 'order', 'shipment'
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
            'name' => str($suffix)->headline().' Store',
            'slug' => $suffix.'-messaging-store',
            'status' => 'approved',
        ]);
        $category = Category::firstOrCreate(
            ['slug' => 'messaging-category'],
            ['name' => 'Messaging Category', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($suffix)->headline().' Product',
            'slug' => $suffix.'-messaging-product-'.$seller->id,
            'description' => 'Messaging test product.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($suffix).'-MSG-'.$seller->id,
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
            'tracking_code' => 'TRK-'.strtoupper($suffix).'-'.$sellerOrder->id,
            'status' => 'unassigned',
            'workflow_status' => OrderStatus::Preparing->value,
            'fee_minor' => 1000,
            'cod_amount_minor' => 11000,
            'cod_collected' => false,
            'attempts' => 0,
        ]);

        return [
            'seller_user' => $sellerUser,
            'seller' => $seller,
            'product' => $product,
            'variant' => $variant,
            'seller_order' => $sellerOrder,
            'shipment' => $shipment,
        ];
    }

    private function erpState(array $fixture, Complaint $complaint): array
    {
        return [
            'order' => $fixture['order']->fresh()->status,
            'payment' => $fixture['order']->fresh()->payment_status,
            'seller_order' => $fixture['seller_order']->fresh()->workflow_status->value,
            'shipment' => $fixture['shipment']->fresh()->workflow_status->value,
            'stock' => $fixture['variant']->fresh()->stock,
            'moderation' => $fixture['product']->fresh()->moderation_status->value,
            'complaint' => $complaint->fresh()->status->value,
        ];
    }

    private function assertForbidden(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The unauthorized messaging operation should have been rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
