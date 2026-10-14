<?php

namespace Tests\Feature;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintWaitingOn;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Complaint;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Admin\ComplaintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseThreeComplaintAdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_roles_can_access_complaint_pages(): void
    {
        $admin = $this->user('admin');
        $superadmin = $this->user('superadmin');
        $seller = $this->user('seller');
        $suspendedAdmin = $this->user('admin', ['is_suspended' => true]);

        $this->actingAs($admin)->get(route('superadmin.complaints.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('superadmin.complaints.index'))->assertOk();
        $this->actingAs($seller)->get(route('superadmin.complaints.index'))->assertForbidden();
        $this->actingAs($suspendedAdmin)->get(route('superadmin.complaints.index'))->assertForbidden();
    }

    public function test_queue_is_paginated_and_filters_by_all_supported_fields(): void
    {
        $admin = $this->user('admin');
        $fixture = $this->fixture();
        $target = $this->complaint($fixture, ComplaintCategory::DamagedItem, 'Filtered damaged item');
        $this->service()->assignAdmin($target, $admin, $admin);

        foreach (range(1, 15) as $index) {
            $this->service()->createComplaint(
                $admin,
                ComplaintCategory::Other,
                'Administrative case '.$index,
                'Pagination fixture.'
            );
        }

        $queue = $this->actingAs($admin)->get(route('superadmin.complaints.index'));
        $queue->assertOk()->assertSeeText('Complaints & Disputes');
        $this->assertSame(16, $queue->viewData('complaints')->total());
        $this->assertSame(15, $queue->viewData('complaints')->perPage());

        $filtered = $this->actingAs($admin)->get(route('superadmin.complaints.index', [
            'status' => ComplaintStatus::Open->value,
            'category' => ComplaintCategory::DamagedItem->value,
            'assigned_to' => $admin->id,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'order_reference' => $fixture['order']->order_number,
        ]));

        $filtered->assertOk()->assertSeeText($target->reference)->assertSeeText($fixture['order']->order_number);
        $this->assertSame(1, $filtered->viewData('complaints')->total());

        $unassigned = $this->actingAs($admin)->get(route('superadmin.complaints.index', [
            'assigned_to' => 'UNASSIGNED',
        ]));
        $this->assertSame(15, $unassigned->viewData('complaints')->total());
    }

    public function test_detail_page_displays_complaint_erp_context_snapshot_and_history(): void
    {
        $admin = $this->user('admin');
        $fixture = $this->fixture();
        $complaint = $this->complaint($fixture, ComplaintCategory::DeliveryConduct, 'Delivery conduct review');
        $this->service()->assignAdmin($complaint, $admin, $admin);

        $this->actingAs($admin)->get(route('superadmin.complaints.show', $complaint))
            ->assertOk()
            ->assertSeeText($complaint->reference)
            ->assertSeeText('Delivery conduct review')
            ->assertSeeText($fixture['order']->order_number)
            ->assertSeeText((string) $fixture['seller_order']->id)
            ->assertSeeText($fixture['product']->name)
            ->assertSeeText($fixture['seller']->name)
            ->assertSeeText($fixture['buyer']->name)
            ->assertSeeText($fixture['shipment']->tracking_code)
            ->assertSeeText($fixture['pickup_user']->name)
            ->assertSeeText($fixture['delivery_user']->name)
            ->assertSeeText($fixture['provider']->name)
            ->assertSeeText('Complaint Created')
            ->assertSeeText('Complaint Assigned')
            ->assertSeeText('Context captured when filed');
    }

    public function test_admin_actions_use_service_transitions_and_render_complete_history(): void
    {
        $admin = $this->user('admin');
        $assignee = $this->user('admin');
        $fixture = $this->fixture();
        $complaint = $this->complaint($fixture, ComplaintCategory::OrderNotReceived, 'Order not received');

        $this->actingAs($admin)->patch(route('superadmin.complaints.assignment', $complaint), [
            'assigned_to' => $assignee->id,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::UnderReview->value,
            'notes' => 'Administrator review started.',
        ])->assertRedirect();
        $this->actingAs($assignee)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::WaitingResponse->value,
            'waiting_on' => ComplaintWaitingOn::Buyer->value,
            'notes' => 'Waiting for buyer confirmation.',
        ])->assertRedirect();
        $this->actingAs($assignee)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::UnderReview->value,
        ])->assertRedirect();
        $this->actingAs($assignee)->post(route('superadmin.complaints.resolve', $complaint), [
            'resolution_notes' => 'Delivery records were reviewed and the case was resolved.',
        ])->assertRedirect();

        $resolved = $complaint->fresh();
        $this->assertSame(ComplaintStatus::Resolved, $resolved->status);
        $this->assertSame($assignee->id, $resolved->assigned_to);
        $this->assertSame(6, $resolved->events()->count());

        $this->actingAs($admin)->get(route('superadmin.complaints.show', $resolved))
            ->assertOk()
            ->assertSeeText('Waiting Response')
            ->assertSeeText('Waiting for Buyer')
            ->assertSeeText('Complaint Resolved')
            ->assertSeeText('Delivery records were reviewed and the case was resolved.')
            ->assertSeeText('This complaint is closed and cannot be modified.');

        $this->actingAs($admin)->patch(route('superadmin.complaints.status', $resolved), [
            'status' => ComplaintStatus::UnderReview->value,
        ])->assertSessionHasErrors('status');
    }

    public function test_assignment_rejects_inactive_admin_and_status_requires_waiting_party(): void
    {
        $admin = $this->user('admin');
        $inactiveAdmin = $this->user('admin', ['status' => 'deactivated']);
        $fixture = $this->fixture();
        $complaint = $this->complaint($fixture, ComplaintCategory::Other, 'Validation case');

        $this->actingAs($admin)->patch(route('superadmin.complaints.assignment', $complaint), [
            'assigned_to' => $inactiveAdmin->id,
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::UnderReview->value,
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::WaitingResponse->value,
        ])->assertSessionHasErrors('waiting_on');
    }

    public function test_admin_can_dismiss_an_open_complaint_with_required_notes(): void
    {
        $admin = $this->user('admin');
        $fixture = $this->fixture();
        $complaint = $this->complaint($fixture, ComplaintCategory::Other, 'Dismissal case');

        $this->actingAs($admin)->post(route('superadmin.complaints.dismiss', $complaint), [
            'resolution_notes' => '',
        ])->assertSessionHasErrors('resolution_notes');

        $this->actingAs($admin)->post(route('superadmin.complaints.dismiss', $complaint), [
            'resolution_notes' => 'No actionable marketplace dispute was found.',
        ])->assertRedirect();

        $this->assertSame(ComplaintStatus::Dismissed, $complaint->fresh()->status);
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'action' => 'COMPLAINT_DISMISSED',
            'new_status' => ComplaintStatus::Dismissed->value,
        ]);
    }

    public function test_portal_actions_do_not_modify_erp_workflow_or_inventory(): void
    {
        $admin = $this->user('admin');
        $fixture = $this->fixture();
        $complaint = $this->complaint($fixture, ComplaintCategory::ProductQuality, 'Quality review');
        $before = $this->erpState($fixture);

        $this->actingAs($admin)->patch(route('superadmin.complaints.assignment', $complaint), [
            'assigned_to' => $admin->id,
        ]);
        $this->actingAs($admin)->patch(route('superadmin.complaints.status', $complaint), [
            'status' => ComplaintStatus::UnderReview->value,
        ]);
        $this->actingAs($admin)->post(route('superadmin.complaints.resolve', $complaint), [
            'resolution_notes' => 'Administrative review completed.',
        ]);

        $this->assertSame($before, $this->erpState($fixture));
    }

    private function complaint(array $fixture, ComplaintCategory $category, string $subject): Complaint
    {
        return $this->service()->createComplaint(
            actor: $fixture['buyer'],
            category: $category,
            subject: $subject,
            description: 'Complaint Admin Portal test description.',
            order: $fixture['order'],
            sellerOrder: $fixture['seller_order'],
            shipment: $fixture['shipment'],
            seller: $fixture['seller'],
            product: $fixture['product'],
            rider: $fixture['delivery_rider'],
            logisticsProvider: $fixture['provider'],
        );
    }

    private function service(): ComplaintService
    {
        return app(ComplaintService::class);
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
            'name' => 'Complaint Portal Store',
            'slug' => 'complaint-portal-store',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => 'Complaint Portal Logistics',
            'slug' => 'complaint-portal-logistics',
            'status' => 'approved',
        ]);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'CMP-PICKUP',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'CMP-DELIVERY',
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $category = Category::firstOrCreate(
            ['slug' => 'complaint-portal-category'],
            ['name' => 'Complaint Portal Category', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Complaint Portal Product',
            'slug' => 'complaint-portal-product',
            'description' => 'Complaint portal test product.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'COMPLAINT-PORTAL-SKU',
            'name' => 'Default',
            'price_minor' => 12500,
            'stock' => 7,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-COMPLAINT-PORTAL',
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Delivered->value,
            'payment_method' => 'cod',
            'payment_status' => 'PAID',
            'subtotal' => 125,
            'shipping_fee' => 10,
            'discount_amount' => 0,
            'total_amount' => 135,
            'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789',
            'province' => 'Test Province',
            'municipality' => 'Test City',
            'barangay' => 'Test Barangay',
            'street_address' => '123 Test Street',
            'placed_at' => now(),
            'delivered_at' => now(),
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 12500,
            'shipping_fee_minor' => 1000,
            'commission_minor' => 1250,
            'status' => 'delivered',
            'workflow_status' => OrderStatus::Delivered->value,
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'unit_price' => 125,
            'unit_price_minor' => 12500,
            'quantity' => 1,
            'line_total' => 125,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id,
            'pickup_rider_id' => $pickupRider->id,
            'rider_id' => $deliveryRider->id,
            'tracking_code' => 'TRK-COMPLAINT-PORTAL',
            'status' => 'delivered',
            'workflow_status' => OrderStatus::Delivered->value,
            'fee_minor' => 1000,
            'cod_amount_minor' => 0,
            'cod_collected' => true,
            'attempts' => 1,
        ]);

        return [
            'buyer' => $buyer,
            'seller' => $seller,
            'provider' => $provider,
            'pickup_user' => $pickupUser,
            'delivery_user' => $deliveryUser,
            'delivery_rider' => $deliveryRider,
            'product' => $product,
            'variant' => $variant,
            'order' => $order,
            'seller_order' => $sellerOrder,
            'shipment' => $shipment,
        ];
    }

    private function erpState(array $fixture): array
    {
        return [
            $fixture['order']->fresh()->status,
            $fixture['order']->fresh()->payment_status,
            $fixture['seller_order']->fresh()->workflow_status->value,
            $fixture['shipment']->fresh()->workflow_status->value,
            $fixture['variant']->fresh()->stock,
            $fixture['product']->fresh()->moderation_status->value,
        ];
    }
}
