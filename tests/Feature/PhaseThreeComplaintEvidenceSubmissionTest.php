<?php

namespace Tests\Feature;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Complaint;
use App\Models\ComplaintEvidence;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseThreeComplaintEvidenceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_submit_only_for_an_owned_order_with_constrained_seller_and_product_context(): void
    {
        $fixture = $this->fixture('buyer');
        $other = $this->fixture('other-buyer');

        $this->actingAs($fixture['buyer'])->get(route('buyer.complaints.create', $fixture['order']))
            ->assertOk()
            ->assertSeeText($fixture['order']->order_number)
            ->assertSeeText($fixture['seller']->name);

        $response = $this->actingAs($fixture['buyer'])->post(route('buyer.complaints.store', $fixture['order']), [
            'category' => ComplaintCategory::DamagedItem->value,
            'subject' => 'Damaged buyer item',
            'description' => 'The product was damaged during delivery.',
            'seller_order_id' => $fixture['seller_order']->id,
            'product_id' => $fixture['product']->id,
        ]);

        $complaint = Complaint::sole();
        $response->assertRedirect(route('complaints.mine.show', $complaint));
        $this->assertSame($fixture['order']->id, $complaint->order_id);
        $this->assertSame($fixture['seller_order']->id, $complaint->seller_order_id);
        $this->assertSame($fixture['product']->id, $complaint->product_id);

        $this->actingAs($fixture['buyer'])->post(route('buyer.complaints.store', $other['order']), [
            'category' => ComplaintCategory::Other->value,
            'subject' => 'Unauthorized order',
            'description' => 'This order belongs to another buyer.',
        ])->assertForbidden();

        $this->actingAs($fixture['buyer'])->post(route('buyer.complaints.store', $fixture['order']), [
            'category' => ComplaintCategory::ProductQuality->value,
            'subject' => 'Missing seller context',
            'description' => 'Product-specific complaint without seller order.',
            'product_id' => $fixture['product']->id,
        ])->assertSessionHasErrors('seller_order_id');
    }

    public function test_seller_can_submit_only_for_an_owned_seller_order(): void
    {
        $fixture = $this->fixture('seller');
        $other = $this->fixture('other-seller');

        $this->actingAs($fixture['seller_user'])
            ->post(route('seller.complaints.store', $fixture['seller_order']), [
                'category' => ComplaintCategory::BuyerConduct->value,
                'subject' => 'Seller complaint',
                'description' => 'Seller requests an administrative review.',
                'product_id' => $fixture['product']->id,
            ])->assertRedirect();

        $this->assertDatabaseHas('complaints', [
            'complainant_id' => $fixture['seller_user']->id,
            'complainant_role' => 'SELLER',
            'seller_order_id' => $fixture['seller_order']->id,
        ]);

        $this->actingAs($fixture['seller_user'])
            ->post(route('seller.complaints.store', $other['seller_order']), [
                'category' => ComplaintCategory::Other->value,
                'subject' => 'Unauthorized seller order',
                'description' => 'Cross-seller access must fail.',
            ])->assertForbidden();
    }

    public function test_only_assigned_pickup_and_delivery_riders_can_submit_for_a_shipment(): void
    {
        $fixture = $this->fixture('rider');
        $outsider = $this->user('rider');
        Rider::create([
            'user_id' => $outsider->id,
            'logistics_provider_id' => $fixture['provider']->id,
            'vehicle_type' => 'Motorcycle',
            'plate_no' => 'OUT-RIDER',
            'is_active' => true,
            'approved_at' => now(),
        ]);

        foreach ([$fixture['pickup_user'], $fixture['delivery_user']] as $riderUser) {
            $this->actingAs($riderUser)->post(route('rider.complaints.store', $fixture['seller_order']), [
                'category' => ComplaintCategory::DeliveryConduct->value,
                'subject' => 'Assigned rider complaint '.$riderUser->id,
                'description' => 'Assigned rider requests review.',
            ])->assertRedirect();
        }

        $this->actingAs($outsider)->post(route('rider.complaints.store', $fixture['seller_order']), [
            'category' => ComplaintCategory::Other->value,
            'subject' => 'Unassigned rider complaint',
            'description' => 'This rider was not assigned.',
        ])->assertForbidden();

        $this->assertSame(2, Complaint::count());
        $this->assertEqualsCanonicalizing(['PICKUP', 'DELIVERY'], Complaint::pluck('rider_role')->all());
    }

    public function test_logistics_can_submit_only_for_a_handled_parcel(): void
    {
        $fixture = $this->fixture('logistics');
        $other = $this->fixture('other-logistics');

        $this->actingAs($fixture['logistics_user'])
            ->post(route('logistics.complaints.store', $fixture['seller_order']), [
                'category' => ComplaintCategory::DamagedItem->value,
                'subject' => 'Sorting center concern',
                'description' => 'The handled parcel requires review.',
            ])->assertRedirect();

        $this->actingAs($fixture['logistics_user'])
            ->post(route('logistics.complaints.store', $other['seller_order']), [
                'category' => ComplaintCategory::Other->value,
                'subject' => 'Other provider parcel',
                'description' => 'Cross-provider access must fail.',
            ])->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'complainant_id' => $fixture['logistics_user']->id,
            'complainant_role' => 'LOGISTICS',
            'logistics_provider_id' => $fixture['provider']->id,
        ]);
    }

    public function test_participants_see_only_personally_filed_complaints_without_internal_admin_notes(): void
    {
        $fixture = $this->fixture('privacy');
        $other = $this->fixture('privacy-other');
        $admin = $this->user('admin');
        $complaint = $this->buyerComplaint($fixture);
        $otherComplaint = $this->buyerComplaint($other);
        $this->service()->changeStatus(
            $complaint,
            $admin,
            ComplaintStatus::UnderReview,
            notes: 'PRIVATE ADMINISTRATIVE NOTE'
        );

        $this->actingAs($fixture['buyer'])->get(route('complaints.mine.index'))
            ->assertOk()
            ->assertSeeText($complaint->reference)
            ->assertDontSeeText($otherComplaint->reference);
        $this->actingAs($fixture['buyer'])->get(route('complaints.mine.show', $complaint))
            ->assertOk()
            ->assertSeeText('Status Changed')
            ->assertDontSeeText('PRIVATE ADMINISTRATIVE NOTE');
        $this->actingAs($other['buyer'])->get(route('complaints.mine.show', $complaint))
            ->assertNotFound();
    }

    public function test_evidence_is_validated_stored_privately_and_recorded_in_history(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $fixture = $this->fixture('evidence');
        $complaint = $this->buyerComplaint($fixture);

        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('delivery-proof.pdf', 100, 'application/pdf'),
            'description' => 'Proof supplied by the buyer.',
        ])->assertRedirect();

        $evidence = ComplaintEvidence::sole();
        Storage::disk('local')->assertExists($evidence->path);
        Storage::disk('public')->assertMissing($evidence->path);
        $this->assertSame($fixture['buyer']->id, $evidence->uploaded_by);
        $this->assertSame('delivery-proof.pdf', $evidence->original_name);
        $this->assertSame('application/pdf', $evidence->mime_type);
        $this->assertSame(64, strlen($evidence->checksum));
        $this->assertStringNotContainsString('delivery-proof.pdf', $evidence->path);
        $this->assertDatabaseHas('complaint_events', [
            'complaint_id' => $complaint->id,
            'actor_id' => $fixture['buyer']->id,
            'action' => 'EVIDENCE_ATTACHED',
        ]);

        $this->actingAs($fixture['buyer'])
            ->get(route('complaints.evidence.show', [$complaint, $evidence]))
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($fixture['buyer'])
            ->get(route('complaints.evidence.download', [$complaint, $evidence]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_evidence_access_is_scoped_to_uploader_while_admin_has_full_access(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('evidence-access');
        $other = $this->fixture('evidence-other');
        $admin = $this->user('admin');
        $complaint = $this->buyerComplaint($fixture);

        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('buyer-proof.jpg', 20, 'image/jpeg'),
        ]);
        $buyerEvidence = ComplaintEvidence::latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('superadmin.complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('admin-proof.pdf', 20, 'application/pdf'),
        ]);
        $adminEvidence = ComplaintEvidence::latest('id')->firstOrFail();

        $this->actingAs($fixture['buyer'])
            ->get(route('complaints.evidence.show', [$complaint, $adminEvidence]))
            ->assertForbidden();
        $this->actingAs($other['buyer'])
            ->get(route('complaints.evidence.show', [$complaint, $buyerEvidence]))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('superadmin.complaints.evidence.show', [$complaint, $buyerEvidence]))
            ->assertOk();

        $this->actingAs($fixture['buyer'])->get(route('complaints.mine.show', $complaint))
            ->assertOk()
            ->assertSeeText('buyer-proof.jpg')
            ->assertDontSeeText('admin-proof.pdf');
    }

    public function test_unsafe_oversized_public_disk_and_closed_case_uploads_are_rejected(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $fixture = $this->fixture('evidence-security');
        $admin = $this->user('admin');
        $complaint = $this->buyerComplaint($fixture);

        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('evidence');
        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf'),
        ])->assertSessionHasErrors('evidence');

        config(['complaints.evidence_disk' => 'public']);
        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('public.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('evidence');
        config(['complaints.evidence_disk' => 'local']);

        $this->service()->changeStatus($complaint, $admin, ComplaintStatus::UnderReview);
        $this->service()->resolve($complaint, $admin, 'Complaint review completed.');
        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('status');

        $this->assertSame(0, ComplaintEvidence::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_submission_and_evidence_actions_do_not_modify_erp_state(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('immutability');
        $before = $this->erpState($fixture);

        $this->actingAs($fixture['buyer'])->post(route('buyer.complaints.store', $fixture['order']), [
            'category' => ComplaintCategory::ProductQuality->value,
            'subject' => 'Quality complaint',
            'description' => 'Requesting product quality review.',
            'seller_order_id' => $fixture['seller_order']->id,
            'product_id' => $fixture['product']->id,
        ]);
        $complaint = Complaint::sole();
        $this->actingAs($fixture['buyer'])->post(route('complaints.evidence.store', $complaint), [
            'evidence' => UploadedFile::fake()->create('quality.jpg', 20, 'image/jpeg'),
        ]);

        $this->assertSame($before, $this->erpState($fixture));
    }

    private function buyerComplaint(array $fixture): Complaint
    {
        return $this->service()->createComplaint(
            actor: $fixture['buyer'],
            category: ComplaintCategory::Other,
            subject: 'Complaint fixture',
            description: 'Participant and evidence test complaint.',
            order: $fixture['order'],
            sellerOrder: $fixture['seller_order'],
            shipment: $fixture['shipment'],
            seller: $fixture['seller'],
            product: $fixture['product'],
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

    private function fixture(string $suffix): array
    {
        $buyer = $this->user('buyer');
        $sellerUser = $this->user('seller');
        $logisticsUser = $this->user('logistics');
        $pickupUser = $this->user('rider');
        $deliveryUser = $this->user('rider');
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => str($suffix)->headline().' Store', 'slug' => $suffix.'-store', 'status' => 'approved']);
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => str($suffix)->headline().' Logistics', 'slug' => $suffix.'-logistics', 'status' => 'approved']);
        $pickupRider = Rider::create(['user_id' => $pickupUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'PK-'.$suffix, 'is_active' => true, 'approved_at' => now()]);
        $deliveryRider = Rider::create(['user_id' => $deliveryUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'DL-'.$suffix, 'is_active' => true, 'approved_at' => now()]);
        $category = Category::firstOrCreate(['slug' => 'complaint-submission-category'], ['name' => 'Complaint Submission Category', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => str($suffix)->headline().' Product', 'slug' => $suffix.'-product', 'description' => 'Complaint test product.', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => strtoupper($suffix).'-CMP', 'name' => 'Default', 'price_minor' => 10000, 'stock' => 8, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper($suffix), 'buyer_id' => $buyer->id, 'status' => OrderStatus::Delivered->value,
            'payment_method' => 'cod', 'payment_status' => 'PAID', 'subtotal' => 100, 'shipping_fee' => 10,
            'discount_amount' => 0, 'total_amount' => 110, 'recipient_name' => $buyer->name,
            'recipient_phone' => '09123456789', 'province' => 'Province', 'municipality' => 'City',
            'barangay' => 'Barangay', 'street_address' => '123 Test Street', 'placed_at' => now(), 'delivered_at' => now(),
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'logistics_provider_id' => $provider->id,
            'subtotal_minor' => 10000, 'shipping_fee_minor' => 1000, 'commission_minor' => 1000,
            'status' => 'delivered', 'workflow_status' => OrderStatus::Delivered->value, 'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'seller_id' => $sellerUser->id, 'seller_order_id' => $sellerOrder->id,
            'product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_slug' => $product->slug,
            'product_name' => $product->name, 'unit_price' => 100, 'unit_price_minor' => 10000,
            'quantity' => 1, 'line_total' => 100,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $provider->id,
            'pickup_rider_id' => $pickupRider->id, 'rider_id' => $deliveryRider->id,
            'tracking_code' => 'TRK-'.strtoupper($suffix), 'status' => 'delivered',
            'workflow_status' => OrderStatus::Delivered->value, 'fee_minor' => 1000,
            'cod_amount_minor' => 0, 'cod_collected' => true, 'attempts' => 1,
        ]);

        return [
            'buyer' => $buyer, 'seller_user' => $sellerUser, 'logistics_user' => $logisticsUser,
            'pickup_user' => $pickupUser, 'delivery_user' => $deliveryUser, 'seller' => $seller,
            'provider' => $provider, 'pickup_rider' => $pickupRider, 'delivery_rider' => $deliveryRider,
            'product' => $product, 'variant' => $variant, 'order' => $order,
            'seller_order' => $sellerOrder, 'shipment' => $shipment,
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
