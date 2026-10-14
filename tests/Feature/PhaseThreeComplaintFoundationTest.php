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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PhaseThreeComplaintFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_a_complaint_for_an_owned_order_without_changing_erp_state(): void
    {
        $fixture = $this->marketplaceFixture();
        $before = $this->erpState($fixture);

        $complaint = $this->service()->createComplaint(
            actor: $fixture['buyer'],
            category: ComplaintCategory::DamagedItem,
            subject: 'Item arrived damaged',
            description: 'The parcel was sealed, but the item inside was cracked.',
            order: $fixture['order'],
            sellerOrder: $fixture['seller_order'],
            shipment: $fixture['shipment'],
            seller: $fixture['seller'],
            product: $fixture['product'],
            rider: $fixture['delivery_rider'],
            logisticsProvider: $fixture['provider'],
        );

        $this->assertMatchesRegularExpression('/^CMP-\d{8}-[A-Z0-9]{10}$/', $complaint->reference);
        $this->assertSame('BUYER', $complaint->complainant_role);
        $this->assertSame(ComplaintStatus::Open, $complaint->status);
        $this->assertSame($fixture['buyer']->id, $complaint->buyer_id);
        $this->assertSame($fixture['seller_order']->id, $complaint->seller_order_id);
        $this->assertSame($fixture['delivery_rider']->id, $complaint->rider_id);
        $this->assertSame('DELIVERY', $complaint->rider_role);
        $this->assertSame($fixture['product']->name, $complaint->context_snapshot['product']['name']);
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'actor_id' => $fixture['buyer']->id,
            'action' => 'COMPLAINT_CREATED',
            'new_status' => 'OPEN',
        ]);
        $this->assertSame($before, $this->erpState($fixture));
    }

    public function test_complainants_are_limited_to_records_they_own_or_handle(): void
    {
        $fixture = $this->marketplaceFixture();
        $other = $this->marketplaceFixture('other');

        $attempts = [
            fn () => $this->service()->createComplaint(
                $fixture['buyer'], ComplaintCategory::Other, 'Not my order', 'Unauthorized buyer context.', $other['order']
            ),
            fn () => $this->service()->createComplaint(
                $fixture['seller_user'], ComplaintCategory::Other, 'Not my seller order', 'Unauthorized seller context.',
                $other['order'], $other['seller_order']
            ),
            fn () => $this->service()->createComplaint(
                $fixture['pickup_user'], ComplaintCategory::DeliveryConduct, 'Not my parcel', 'Unauthorized rider context.',
                shipment: $other['shipment']
            ),
            fn () => $this->service()->createComplaint(
                $fixture['logistics_user'], ComplaintCategory::DeliveryConduct, 'Not our parcel', 'Unauthorized logistics context.',
                shipment: $other['shipment']
            ),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('An actor must not create a complaint for an unrelated marketplace record.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame(0, Complaint::count());
    }

    public function test_assigned_riders_and_logistics_can_create_parcel_complaints(): void
    {
        $fixture = $this->marketplaceFixture();

        $pickupComplaint = $this->service()->createComplaint(
            actor: $fixture['pickup_user'],
            category: ComplaintCategory::SellerConduct,
            subject: 'Pickup handover concern',
            description: 'The parcel handover requires administrator review.',
            shipment: $fixture['shipment'],
        );
        $deliveryComplaint = $this->service()->createComplaint(
            actor: $fixture['delivery_user'],
            category: ComplaintCategory::BuyerConduct,
            subject: 'Delivery conduct concern',
            description: 'The delivery attempt requires administrator review.',
            shipment: $fixture['shipment'],
        );
        $logisticsComplaint = $this->service()->createComplaint(
            actor: $fixture['logistics_user'],
            category: ComplaintCategory::DamagedItem,
            subject: 'Sorting center damage',
            description: 'Damage was observed while handling this parcel.',
            shipment: $fixture['shipment'],
        );

        $this->assertSame('PICKUP', $pickupComplaint->rider_role);
        $this->assertSame($fixture['pickup_rider']->id, $pickupComplaint->rider_id);
        $this->assertSame('DELIVERY', $deliveryComplaint->rider_role);
        $this->assertSame($fixture['delivery_rider']->id, $deliveryComplaint->rider_id);
        $this->assertSame('LOGISTICS', $logisticsComplaint->complainant_role);
        $this->assertSame($fixture['provider']->id, $logisticsComplaint->logistics_provider_id);
    }

    public function test_multi_seller_specific_context_requires_a_seller_order(): void
    {
        $fixture = $this->marketplaceFixture();
        $second = $this->appendSellerOrder($fixture['order'], 'second');

        try {
            $this->service()->createComplaint(
                actor: $fixture['buyer'],
                category: ComplaintCategory::WrongItem,
                subject: 'Wrong seller item',
                description: 'The affected seller must be selected explicitly.',
                order: $fixture['order'],
                product: $second['product'],
            );
            $this->fail('Seller-specific context on a multi-seller order must require seller_order_id.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seller_order', $exception->errors());
        }

        $complaint = $this->service()->createComplaint(
            actor: $fixture['buyer'],
            category: ComplaintCategory::WrongItem,
            subject: 'Wrong seller item',
            description: 'The affected seller order is explicitly selected.',
            order: $fixture['order'],
            sellerOrder: $second['seller_order'],
            product: $second['product'],
        );

        $this->assertSame($second['seller_order']->id, $complaint->seller_order_id);
        $this->assertSame($second['seller']->id, $complaint->seller_id);
        $this->assertSame($second['product']->id, $complaint->product_id);
    }

    public function test_only_active_admins_can_be_assigned_and_manage_status_history(): void
    {
        $fixture = $this->marketplaceFixture();
        $admin = $this->user('admin');
        $otherAdmin = $this->user('admin');
        $suspendedAdmin = $this->user('admin', ['is_suspended' => true]);
        $complaint = $this->service()->createComplaint(
            $fixture['buyer'], ComplaintCategory::OrderNotReceived, 'Order not received', 'The order did not arrive.',
            $fixture['order']
        );

        try {
            $this->service()->assignAdmin($complaint, $admin, $suspendedAdmin);
            $this->fail('A suspended administrator cannot be assigned.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $assigned = $this->service()->assignAdmin($complaint, $admin, $otherAdmin);
        $reviewing = $this->service()->changeStatus($assigned, $otherAdmin, ComplaintStatus::UnderReview, notes: 'Review started.');

        try {
            $this->service()->changeStatus($reviewing, $otherAdmin, ComplaintStatus::WaitingResponse);
            $this->fail('WAITING_RESPONSE must identify the waiting party.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('waiting_on', $exception->errors());
        }

        $waiting = $this->service()->changeStatus(
            $reviewing,
            $otherAdmin,
            ComplaintStatus::WaitingResponse,
            ComplaintWaitingOn::Buyer,
            'Waiting for buyer documentation.'
        );
        $reopened = $this->service()->changeStatus($waiting, $otherAdmin, ComplaintStatus::UnderReview);
        $resolved = $this->service()->resolve($reopened, $otherAdmin, 'Evidence reviewed and case resolved.');

        $this->assertSame(ComplaintStatus::Resolved, $resolved->status);
        $this->assertSame($otherAdmin->id, $resolved->resolved_by);
        $this->assertSame(6, $resolved->events()->count());
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'action' => 'WAITING_RESPONSE',
            'previous_status' => 'UNDER_REVIEW',
            'new_status' => 'WAITING_RESPONSE',
            'waiting_on' => 'BUYER',
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->assignAdmin($resolved, $admin, $admin);
    }

    public function test_invalid_transitions_and_blank_resolution_notes_are_rejected(): void
    {
        $fixture = $this->marketplaceFixture();
        $admin = $this->user('admin');
        $complaint = $this->service()->createComplaint(
            $fixture['buyer'], ComplaintCategory::PaymentConcern, 'Payment concern', 'Payment requires review.',
            $fixture['order']
        );

        foreach ([
            fn () => $this->service()->changeStatus($complaint, $admin, ComplaintStatus::Resolved),
            fn () => $this->service()->resolve($complaint, $admin, 'Resolution before review.'),
            fn () => $this->service()->dismiss($complaint, $admin, '   '),
        ] as $operation) {
            try {
                $operation();
                $this->fail('The invalid complaint operation should be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $dismissed = $this->service()->dismiss($complaint, $admin, 'No actionable marketplace issue was found.');
        $this->assertSame(ComplaintStatus::Dismissed, $dismissed->status);
        $this->assertSame(2, $dismissed->events()->count());
    }

    public function test_all_categories_are_persisted_without_touching_marketplace_records(): void
    {
        $admin = $this->user('admin');

        foreach (ComplaintCategory::cases() as $index => $category) {
            $complaint = $this->service()->createComplaint(
                $admin,
                $category,
                'Administrative complaint '.$index,
                'Complaint category persistence check.'
            );
            $this->assertSame($category, $complaint->category);
        }

        $this->assertSame(count(ComplaintCategory::cases()), Complaint::count());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('shipments', 0);
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

    private function marketplaceFixture(string $suffix = 'primary'): array
    {
        $buyer = $this->user('buyer');
        $sellerUser = $this->user('seller');
        $logisticsUser = $this->user('logistics');
        $pickupUser = $this->user('rider');
        $deliveryUser = $this->user('rider');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => str($suffix)->headline().' Store',
            'slug' => $suffix.'-complaint-store',
            'status' => 'approved',
        ]);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id,
            'name' => str($suffix)->headline().' Logistics',
            'slug' => $suffix.'-complaint-logistics',
            'status' => 'approved',
        ]);
        $pickupRider = Rider::create([
            'user_id' => $pickupUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'PK-'.$provider->id,
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryUser->id,
            'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'DL-'.$provider->id,
            'is_active' => true,
            'approved_at' => now(),
        ]);
        $category = Category::firstOrCreate(
            ['slug' => 'complaint-category'],
            ['name' => 'Complaint Category', 'is_active' => true]
        );
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($suffix)->headline().' Product',
            'slug' => $suffix.'-complaint-product',
            'description' => 'Complaint test product.',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($suffix).'-COMPLAINT',
            'name' => 'Default',
            'price_minor' => 10000,
            'stock' => 10,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper($suffix).'-'.$buyer->id,
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Delivered->value,
            'payment_method' => 'cod',
            'payment_status' => 'PAID',
            'subtotal' => 100,
            'shipping_fee' => 10,
            'discount_amount' => 0,
            'total_amount' => 110,
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
            'subtotal_minor' => 10000,
            'shipping_fee_minor' => 1000,
            'commission_minor' => 1000,
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
            'unit_price' => 100,
            'unit_price_minor' => 10000,
            'quantity' => 1,
            'line_total' => 100,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id,
            'pickup_rider_id' => $pickupRider->id,
            'rider_id' => $deliveryRider->id,
            'tracking_code' => 'TRK-'.strtoupper($suffix).'-'.$order->id,
            'status' => 'delivered',
            'workflow_status' => OrderStatus::Delivered->value,
            'fee_minor' => 1000,
            'cod_amount_minor' => 0,
            'cod_collected' => true,
            'attempts' => 1,
        ]);

        return compact(
            'buyer', 'sellerUser', 'logisticsUser', 'pickupUser', 'deliveryUser', 'seller', 'provider',
            'pickupRider', 'deliveryRider', 'product', 'variant', 'order', 'sellerOrder', 'shipment'
        ) + [
            'seller_user' => $sellerUser,
            'logistics_user' => $logisticsUser,
            'pickup_user' => $pickupUser,
            'delivery_user' => $deliveryUser,
            'pickup_rider' => $pickupRider,
            'delivery_rider' => $deliveryRider,
            'seller_order' => $sellerOrder,
        ];
    }

    private function appendSellerOrder(Order $order, string $suffix): array
    {
        $sellerUser = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'name' => str($suffix)->headline().' Store',
            'slug' => $suffix.'-multi-store',
            'status' => 'approved',
        ]);
        $category = Category::where('slug', 'complaint-category')->firstOrFail();
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => str($suffix)->headline().' Multi Product',
            'slug' => $suffix.'-multi-product',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($suffix).'-MULTI',
            'name' => 'Default',
            'price_minor' => 5000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'subtotal_minor' => 5000,
            'shipping_fee_minor' => 0,
            'commission_minor' => 500,
            'status' => 'delivered',
            'workflow_status' => OrderStatus::Delivered->value,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'seller_order_id' => $sellerOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_slug' => $product->slug,
            'product_name' => $product->name,
            'unit_price' => 50,
            'unit_price_minor' => 5000,
            'quantity' => 1,
            'line_total' => 50,
        ]);

        return ['seller' => $seller, 'seller_order' => $sellerOrder, 'product' => $product];
    }

    private function erpState(array $fixture): array
    {
        return [
            'order_status' => $fixture['order']->fresh()->status,
            'payment_status' => $fixture['order']->fresh()->payment_status,
            'seller_order_status' => $fixture['seller_order']->fresh()->workflow_status->value,
            'shipment_status' => $fixture['shipment']->fresh()->workflow_status->value,
            'variant_stock' => $fixture['variant']->fresh()->stock,
            'product_moderation' => $fixture['product']->fresh()->moderation_status->value,
        ];
    }
}
