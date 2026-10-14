<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TestAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemFunctionalStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_accounts_catalog_and_portal_pages_are_browser_ready(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $seller = User::where('email', 'seller@test.com')->sole();
        $admin = User::where('email', 'admin@test.com')->sole();
        $rider = User::where('email', 'rider@test.com')->sole();
        $logistics = User::where('email', 'logistics@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();

        $this->assertNotNull($buyer->addresses()->where('is_default', true)->first());

        $this->get(route('buyer.home'))->assertOk()->assertSee($product->name);
        $this->get(route('buyer.shop'))->assertOk()->assertSee($product->name);
        $this->get(route('buyer.product', $product->slug))->assertOk()->assertSee($product->name);

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('buyer.product', $product->slug));
        $this->get(route('buyer.checkout'))->assertOk();
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('superadmin.dashboard'))->assertOk();
        $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk();
        $this->actingAs($logistics)->get(route('logistics.dashboard'))->assertOk();
    }

    public function test_seeded_roles_cannot_cross_portal_boundaries(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $seller = User::where('email', 'seller@test.com')->sole();
        $rider = User::where('email', 'rider@test.com')->sole();
        $logistics = User::where('email', 'logistics@test.com')->sole();

        $this->actingAs($buyer)->get(route('seller.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('superadmin.dashboard'))->assertForbidden();
        $this->actingAs($rider)->get(route('logistics.dashboard'))->assertForbidden();
        $this->actingAs($logistics)->get(route('rider.dashboard'))->assertForbidden();
    }

    public function test_seeded_accounts_complete_the_database_erp_lifecycle_consistently(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $sellerUser = User::where('email', 'seller@test.com')->sole();
        $pickupUser = User::where('email', 'rider@test.com')->sole();
        $deliveryUser = User::where('email', 'delivery.rider@test.com')->sole();
        $logisticsUser = User::where('email', 'logistics@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();
        $address = $buyer->addresses()->where('is_default', true)->sole();

        $this->actingAs($buyer)
            ->from(route('buyer.product', $product->slug))
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('buyer.product', $product->slug));

        $this->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();

        $order = $buyer->buyerOrders()->with('sellerOrders.shipment')->sole();
        $sellerOrder = $order->sellerOrders->sole();

        foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::ReadyForPickup] as $status) {
            $this->actingAs($sellerUser)
                ->post(route('seller.orders.status', $sellerOrder), ['status' => $status->value])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($pickupUser)
            ->post(route('rider.database.pickup.accept', $sellerOrder))
            ->assertSessionHasNoErrors();
        foreach ([OrderStatus::PickedUp, OrderStatus::AtSortingCenter] as $status) {
            $this->post(route('rider.database.order.status', $sellerOrder), ['status' => $status->value])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($logisticsUser)
            ->post(route('logistics.database.orders.receive', $sellerOrder))
            ->assertSessionHasNoErrors();
        $this->post(route('logistics.database.order.status', $sellerOrder), [
            'status' => OrderStatus::Sorted->value,
            'sorting_area' => 'Acceptance Area',
        ])->assertSessionHasNoErrors();
        $this->post(route('logistics.database.order.status', $sellerOrder), [
            'status' => OrderStatus::AssignedToRider->value,
            'rider_id' => $deliveryUser->rider->id,
        ])->assertSessionHasNoErrors();

        foreach ([OrderStatus::OutForDelivery, OrderStatus::Delivered] as $status) {
            $this->actingAs($deliveryUser)
                ->post(route('rider.database.order.status', $sellerOrder), [
                    'status' => $status->value,
                    'proof_photo' => \Tests\Support\DeliveryProofFile::image(),
                    'received_by' => 'Buyer', 'cod_collected' => '1',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($buyer)
            ->post(route('order.status.update', $order->order_number), ['status' => 'completed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Completed, $sellerOrder->fresh()->workflow_status);
        $this->assertSame(OrderStatus::Completed->value, $order->fresh()->status);
        $this->assertSame(OrderStatus::Completed, $sellerOrder->shipment->fresh()->workflow_status);
    }

    public function test_seeded_admin_can_render_existing_management_areas(): void
    {
        $this->seed(TestAccountsSeeder::class);
        $admin = User::where('email', 'admin@test.com')->sole();

        $this->actingAs($admin)->get(route('superadmin.users'))->assertOk();
        $this->get(route('superadmin.applications'))->assertOk();
        $this->get(route('superadmin.sellers'))->assertOk();
        $this->get(route('superadmin.products.index'))->assertOk();
        $this->get(route('superadmin.compliance.index'))->assertOk();
        $this->get(route('superadmin.complaints.index'))->assertOk();
    }

    public function test_seeded_buyer_and_seller_can_message_from_product_and_seller_order_contexts(): void
    {
        $this->seed(TestAccountsSeeder::class);

        $buyer = User::where('email', 'buyer@test.com')->sole();
        $sellerUser = User::where('email', 'seller@test.com')->sole();
        $product = Product::where('slug', 'acceptance-product')->sole();
        $address = $buyer->addresses()->where('is_default', true)->sole();

        $this->actingAs($buyer)
            ->post(route('buyer.messages.context.product.store', $product))
            ->assertRedirect();

        $productConversation = Conversation::where('product_id', $product->id)->sole();
        $this->post(route('buyer.messages.store', $productConversation), [
            'body' => 'Is this acceptance product available?',
        ])->assertRedirect();

        $this->actingAs($sellerUser)
            ->get(route('seller.messages.show', $productConversation))
            ->assertOk()
            ->assertSee('Is this acceptance product available?');
        $this->post(route('seller.messages.store', $productConversation), [
            'body' => 'Yes, it is available.',
        ])->assertRedirect();

        $this->assertSame(2, $productConversation->participants()->count());
        $this->assertSame(2, $productConversation->messages()->count());

        $this->actingAs($buyer)
            ->get(route('buyer.messages.show', $productConversation))
            ->assertOk()
            ->assertSee('Yes, it is available.');

        $this->post(route('buyer.cart.add', $product->slug), ['quantity' => 1]);
        $this->post(route('buyer.checkout.place-order'), [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();

        $sellerOrder = $buyer->buyerOrders()->sole()->sellerOrders()->sole();

        $this->post(route('buyer.messages.context.seller-order.store', $sellerOrder))
            ->assertRedirect();

        $orderConversation = Conversation::where('seller_order_id', $sellerOrder->id)->sole();
        $this->assertNotSame($productConversation->id, $orderConversation->id);
        $this->assertSame(2, $orderConversation->participants()->count());

        $this->actingAs($sellerUser)
            ->post(route('seller.messages.context.seller-order.store', $sellerOrder))
            ->assertRedirect(route('seller.messages.show', $orderConversation));

        $this->assertSame(2, Conversation::count());
        $this->assertDatabaseCount('conversation_participants', 4);
        $this->assertDatabaseCount('messages', 2);
    }
}
