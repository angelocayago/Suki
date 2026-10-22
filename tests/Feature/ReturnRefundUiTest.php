<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRefundEvidence;
use App\Models\ReturnRefundRequest;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRefundUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_index_is_scoped_and_approved_detail_is_truthful(): void
    {
        [$buyer, $request] = $this->request('ADMIN_APPROVED');
        [, $otherRequest] = $this->request('SUBMITTED');

        $this->actingAs($buyer)->get(route('buyer.return-refunds.index'))
            ->assertOk()->assertSee('RR-'.$request->id)->assertDontSee('RR-'.$otherRequest->id);
        $this->actingAs($buyer)->get(route('buyer.return-refunds.show', $request))
            ->assertOk()->assertSee('Approved — Refund Due')->assertSee('₱12.00')->assertDontSee('Paid')->assertDontSee('Disbursed');
    }

    public function test_buyer_cannot_view_another_buyers_return_detail(): void
    {
        [$buyer] = $this->request();
        [, $otherRequest] = $this->request();

        $this->actingAs($buyer)->get(route('buyer.return-refunds.show', $otherRequest))->assertNotFound();
    }

    public function test_buyer_order_only_renders_return_action_for_currently_eligible_item(): void
    {
        [$buyer, , $item] = $this->request('ADMIN_REJECTED', 'DELIVERED');
        $this->request('SUBMITTED', 'PLACED', $buyer);
        $order = $item->order;

        $this->actingAs($buyer)->get(route('buyer.order-details', $order->order_number))
            ->assertOk()->assertSee('Return / Refund');

        [$ineligibleBuyer, , $ineligibleItem] = $this->request('SUBMITTED', 'PLACED');
        $this->actingAs($ineligibleBuyer)->get(route('buyer.order-details', $ineligibleItem->order->order_number))
            ->assertOk()->assertDontSee('Return / Refund');
    }

    public function test_seller_queue_is_scoped_and_recommendation_uses_existing_endpoint(): void
    {
        [$buyer, $request, $item, $sellerUser] = $this->request();
        [, $otherRequest] = $this->request();

        $this->actingAs($sellerUser)->get(route('seller.return-refunds.index'))
            ->assertOk()->assertSee($request->buyer->name)->assertDontSee($otherRequest->buyer->name);
        $this->actingAs($sellerUser)->get(route('seller.return-refunds.show', $otherRequest))->assertNotFound();
        $this->actingAs($sellerUser)->patch(route('seller.return-refunds.respond', $request), ['recommendation' => 'APPROVE', 'note' => 'Item condition supports approval.'])
            ->assertOk();
        $this->assertDatabaseHas('return_refund_requests', ['id' => $request->id, 'status' => 'SELLER_RESPONDED', 'seller_recommendation' => 'APPROVE']);

        $admin = $this->user('admin');
        $this->actingAs($sellerUser)->patch(route('admin.return-refunds.decide', $request), ['decision' => 'APPROVED', 'reason' => 'No authority.'])->assertForbidden();
        $this->assertNotNull($buyer);
        $this->assertNotNull($item);
        $this->assertNotNull($admin);
    }

    public function test_admin_queue_detail_and_canonical_final_decision_are_available_only_to_admins(): void
    {
        [$buyer, $request] = $this->request();
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.return-refunds.index'))->assertOk()->assertSee('RR-'.$request->id);
        $this->actingAs($admin)->get(route('admin.return-refunds.show', $request))->assertOk()->assertSee('Finalize decision');
        $this->actingAs($buyer)->get(route('admin.return-refunds.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.return-refunds.show', $request))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.return-refunds.decide', $request), ['decision' => 'APPROVED', 'reason' => 'Evidence reviewed.'])->assertOk();
        $this->actingAs($admin)->get(route('admin.return-refunds.show', $request->fresh()))->assertOk()->assertSee('Approved — Refund Due')->assertDontSee('Paid')->assertDontSee('Disbursed');
    }

    public function test_private_evidence_is_available_to_participants_and_admin_only(): void
    {
        Storage::fake('local');
        [$buyer, $request, , $sellerUser] = $this->request();
        $evidence = $this->evidence($request);
        $admin = $this->user('admin');

        $this->actingAs($buyer)->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertOk();
        $this->actingAs($sellerUser)->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertOk();
        $this->actingAs($admin)->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertOk();
    }

    public function test_private_evidence_denies_unrelated_users_and_guests(): void
    {
        Storage::fake('local');
        [, $request] = $this->request();
        $evidence = $this->evidence($request);

        $this->actingAs($this->user('buyer'))->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
        $this->actingAs($this->sellerUser())->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
        $this->actingAs($this->user('rider'))->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
        $this->actingAs($this->user('logistics'))->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
        $this->get(route('return-refunds.evidence.show', [$request, $evidence]))->assertForbidden();
    }

    public function test_evidence_upload_after_final_decision_is_denied(): void
    {
        [$buyer, $request] = $this->request('ADMIN_APPROVED');

        $this->actingAs($buyer)->post(route('return-refunds.evidence.store', $request), [
            'file' => \Illuminate\Http\UploadedFile::fake()->create('evidence.jpg', 10, 'image/jpeg'),
        ])->assertStatus(422);
    }

    private function request(string $status = 'SUBMITTED', string $workflow = 'DELIVERED', ?User $buyer = null): array
    {
        $buyer ??= $this->user('buyer');
        $sellerUser = $this->sellerUser();
        $seller = Seller::query()->where('user_id', $sellerUser->id)->first();
        $order = Order::create(['order_number' => 'ORD-'.uniqid(), 'reference' => 'REF-'.uniqid(), 'buyer_id' => $buyer->id, 'seller_id' => $sellerUser->id, 'status' => 'COMPLETED', 'payment_method' => 'cod', 'payment_status' => 'PAID', 'subtotal' => 12, 'shipping_fee' => 0, 'discount_amount' => 0, 'total_amount' => 12, 'total_minor' => 1200, 'recipient_name' => 'Buyer', 'recipient_phone' => '09170000000', 'province' => 'P', 'municipality' => 'M', 'barangay' => 'B', 'street_address' => 'Street', 'shipping_address' => []]);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 1200, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => strtolower($workflow) === 'placed' ? 'pending' : 'delivered', 'workflow_status' => $workflow]);
        $item = OrderItem::create(['order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'seller_id' => $sellerUser->id, 'product_slug' => 'item-'.uniqid(), 'product_name' => 'Returnable Item', 'quantity' => 1, 'unit_price' => 12, 'line_total' => 12, 'unit_price_minor' => 1200, 'commission_minor' => 0, 'seller_funded_discount_minor' => 0, 'platform_funded_discount_minor' => 0]);
        $request = ReturnRefundRequest::create(['buyer_id' => $buyer->id, 'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'order_item_id' => $item->id, 'seller_id' => $seller->id, 'type' => 'REFUND', 'status' => $status, 'quantity' => 1, 'buyer_reason' => 'Damaged', 'buyer_description' => 'Damaged on arrival.', 'refund_minor' => $status === 'ADMIN_APPROVED' ? 1200 : null]);

        return [$buyer, $request, $item, $sellerUser];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active', 'is_suspended' => false]);
        $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => $role])->id]);

        return $user;
    }

    private function sellerUser(): User
    {
        $user = $this->user('seller');
        Seller::create(['user_id' => $user->id, 'name' => 'Store '.uniqid(), 'slug' => 'store-'.uniqid(), 'status' => 'approved', 'commission_bps' => 0]);

        return $user;
    }

    private function evidence(ReturnRefundRequest $request): ReturnRefundEvidence
    {
        $path = 'return-refund-evidence/'.$request->id.'/evidence.jpg';
        Storage::disk('local')->put($path, 'evidence');

        return ReturnRefundEvidence::create(['return_refund_request_id' => $request->id, 'uploaded_by' => $request->buyer_id, 'path' => $path, 'mime' => 'image/jpeg', 'size' => 8]);
    }
}
