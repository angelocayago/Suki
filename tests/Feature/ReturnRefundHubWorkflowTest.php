<?php

namespace Tests\Feature;

use App\Models\{Address,CartItem,Category,LogisticsHub,LogisticsProvider,Product,ProductVariant,ReturnRefundRequest,ReturnRefundWorkflow,Seller,User};
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedStorageTestCase;

class ReturnRefundHubWorkflowTest extends IsolatedStorageTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Disposable SQLite required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        config(['returns.backend_enabled' => true, 'returns.earning_release_enabled' => false]);
    }

    private function fixture(): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Hub return shop', 'slug' => 'hub-return-'.uniqid(), 'status' => 'approved']);
        $address = Address::create(['user_id' => $sellerUser->id, 'recipient' => 'Hub return shop', 'phone' => '000', 'postal_code' => '4009', 'line1' => 'Return Street', 'region' => 'CALABARZON', 'region_code' => '0400000000', 'province' => 'Laguna', 'province_code' => '0403400000', 'city' => 'Santa Cruz', 'city_municipality_code' => '0403426000', 'barangay' => 'Duhat', 'barangay_code' => '0403426005']);
        $seller->update(['pickup_address_id' => $address->id]);
        $category = Category::create(['name' => 'Hub return '.uniqid(), 'slug' => 'hub-return-category-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Hub return item', 'slug' => 'hub-return-item-'.uniqid(), 'is_active' => true, 'moderation_status' => 'APPROVED', 'commission_bps' => 1000]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Standard', 'sku' => 'HUB-'.uniqid(), 'price_minor' => 10000, 'stock' => 8, 'is_active' => true]);
        CartItem::create(['user_id' => $buyer->id, 'product_variant_id' => $variant->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'quantity' => 2, 'price' => 100]);
        $order = app(CheckoutService::class)->checkout($buyer, ['recipient_name' => 'Buyer', 'recipient_phone' => '000', 'region' => 'CALABARZON', 'region_code' => '0400000000', 'province' => 'Laguna', 'province_code' => '0403400000', 'municipality' => 'Santa Cruz', 'city_municipality_code' => '0403426000', 'barangay' => 'Duhat', 'barangay_code' => '0403426005', 'street_address' => 'Buyer Street'], 'cod', []);
        $sellerOrder = $order->sellerOrders->first();
        $item = $sellerOrder->items->first();
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => 'Hub provider', 'slug' => 'hub-provider-'.uniqid(), 'status' => 'approved']);
        $hub = LogisticsHub::create(['logistics_provider_id' => $provider->id, 'name' => 'Duhat return hub', 'region' => 'CALABARZON', 'region_code' => '0400000000', 'province' => 'Laguna', 'province_code' => '0403400000', 'city' => 'Santa Cruz', 'city_municipality_code' => '0403426000', 'barangay' => 'Duhat', 'barangay_code' => '0403426005', 'is_active' => true]);
        $sellerOrder->shipment->update(['logistics_provider_id' => $provider->id, 'destination_hub_id' => $hub->id]);
        $return = ReturnRefundRequest::create(['buyer_id' => $buyer->id, 'order_id' => $order->id, 'seller_order_id' => $sellerOrder->id, 'order_item_id' => $item->id, 'seller_id' => $seller->id, 'type' => 'RETURN', 'status' => 'SELLER_RESPONDED', 'quantity' => 2, 'buyer_reason' => 'Damaged', 'buyer_description' => 'Damaged on delivery.', 'seller_recommendation' => 'APPROVE', 'seller_note' => 'Return for inspection.', 'seller_responded_at' => now()]);
        ReturnRefundWorkflow::create(['return_refund_request_id' => $return->id, 'return_address' => 'Seller return address']);
        return compact('buyer', 'admin', 'sellerUser', 'variant', 'logisticsUser', 'provider', 'hub', 'return');
    }

    private function authorize(array $f): ReturnRefundWorkflow
    {
        $this->actingAs($f['admin'])->patch(route('admin.return-refunds.authorize-return', $f['return']), ['reason' => 'Physical item inspection is required.'])->assertSessionHasNoErrors();
        return ReturnRefundWorkflow::where('return_refund_request_id', $f['return']->id)->firstOrFail();
    }

    private function moveToSellerReceipt(array $f): ReturnRefundWorkflow
    {
        $workflow = $this->authorize($f);
        $this->actingAs($f['logisticsUser'])->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $f['hub']->id, 'return_reference' => $workflow->return_reference])->assertSessionHasNoErrors();
        $this->post(route('logistics.return-refunds.dispatch', $f['return']), ['hub_id' => $f['hub']->id])->assertSessionHasNoErrors();
        $this->actingAs($f['sellerUser'])->post(route('seller.return-refunds.physical-receipt', $f['return']))->assertSessionHasNoErrors();
        return $workflow->fresh();
    }

    public function test_authorized_return_moves_forward_once_and_restocking_is_idempotent(): void
    {
        $f = $this->fixture();
        $workflow = $this->authorize($f);
        $this->assertSame('RETURN_AUTHORIZED', $f['return']->fresh()->status);
        $this->actingAs($f['buyer'])->get(route('buyer.return-refunds.show', $f['return']))->assertOk()->assertSee($workflow->return_reference)->assertSee('Designated hub');
        $this->actingAs($f['logisticsUser'])->get(route('logistics.return-refunds.index'))->assertOk()->assertSee('Duhat return hub');
        $this->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $f['hub']->id, 'return_reference' => $workflow->return_reference])->assertSessionHasNoErrors();
        $this->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $f['hub']->id, 'return_reference' => $workflow->return_reference])->assertSessionHasNoErrors();
        $this->post(route('logistics.return-refunds.dispatch', $f['return']), ['hub_id' => $f['hub']->id])->assertSessionHasNoErrors();
        $this->post(route('logistics.return-refunds.dispatch', $f['return']), ['hub_id' => $f['hub']->id])->assertSessionHasNoErrors();
        $this->actingAs($f['sellerUser'])->post(route('seller.return-refunds.physical-receipt', $f['return']))->assertSessionHasNoErrors();
        $this->post(route('seller.return-refunds.physical-receipt', $f['return']))->assertSessionHasNoErrors();
        $before = $f['variant']->fresh()->stock;
        $this->post(route('seller.return-refunds.inspection', $f['return']), ['outcome' => 'RESTOCKABLE', 'note' => 'Sealed and resellable.'])->assertSessionHasNoErrors();
        $this->post(route('seller.return-refunds.inspection', $f['return']), ['outcome' => 'RESTOCKABLE', 'note' => 'Replay.'])->assertSessionHasNoErrors();
        $this->assertSame($before + 2, $f['variant']->fresh()->stock);
        $this->assertSame('INSPECTED', $workflow->fresh()->logistics_state);
        $this->assertTrue($workflow->fresh()->stock_restored);
        $this->assertDatabaseCount('return_refund_accounting', 0);
        $this->assertDatabaseCount('seller_wallet_entries', 0);
    }

    public function test_authorization_and_hub_reference_are_scoped_and_invalid_order_is_rejected(): void
    {
        $f = $this->fixture();
        $workflow = $this->authorize($f);
        $otherBuyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($otherBuyer)->get(route('buyer.return-refunds.show', $f['return']))->assertNotFound();
        $this->actingAs($f['logisticsUser'])->post(route('logistics.return-refunds.dispatch', $f['return']), ['hub_id' => $f['hub']->id])->assertSessionHasErrors('state');
        $this->actingAs($f['sellerUser'])->post(route('seller.return-refunds.physical-receipt', $f['return']))->assertSessionHasErrors('state');
        $this->actingAs($f['logisticsUser']);
        $this->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $f['hub']->id, 'return_reference' => 'RR-TAMPERED'])->assertSessionHasErrors('state');
        $wrongHub = LogisticsHub::create(['logistics_provider_id' => $f['provider']->id, 'name' => 'Wrong hub', 'region' => 'CALABARZON', 'region_code' => '0400000000', 'province' => 'Laguna', 'province_code' => '0403400000', 'city' => 'Santa Cruz', 'city_municipality_code' => '0403426000', 'barangay' => 'Other', 'barangay_code' => '0403426006', 'is_active' => true]);
        $this->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $wrongHub->id, 'return_reference' => $workflow->return_reference])->assertSessionHasErrors('request');
        $otherSeller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($otherSeller)->post(route('seller.return-refunds.physical-receipt', $f['return']))->assertForbidden();
        $this->assertSame('AWAITING_BUYER_DROPOFF', $workflow->fresh()->logistics_state);
    }

    public function test_wrong_provider_cannot_receive_and_non_restockable_inspection_does_not_change_stock(): void
    {
        $f = $this->fixture();
        $workflow = $this->authorize($f);
        $otherUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $otherProvider = LogisticsProvider::create(['user_id' => $otherUser->id, 'name' => 'Other provider', 'slug' => 'other-provider-'.uniqid(), 'status' => 'approved']);
        $otherHub = LogisticsHub::create(['logistics_provider_id' => $otherProvider->id, 'name' => 'Other hub', 'region' => 'CALABARZON', 'region_code' => '0400000000', 'province' => 'Laguna', 'province_code' => '0403400000', 'city' => 'Santa Cruz', 'city_municipality_code' => '0403426000', 'barangay' => 'Duhat', 'barangay_code' => '0403426005', 'is_active' => true]);
        $this->actingAs($otherUser)->post(route('logistics.return-refunds.hub-receipt', $f['return']), ['hub_id' => $otherHub->id, 'return_reference' => $workflow->return_reference])->assertSessionHasErrors('request');
        $this->moveToSellerReceipt($f);
        $before = $f['variant']->fresh()->stock;
        $this->actingAs($f['sellerUser'])->post(route('seller.return-refunds.inspection', $f['return']), ['outcome' => 'DAMAGED', 'note' => 'Not fit for resale.'])->assertSessionHasNoErrors();
        $this->assertSame($before, $f['variant']->fresh()->stock);
        $this->assertFalse(ReturnRefundWorkflow::where('return_refund_request_id', $f['return']->id)->sole()->stock_restored);
    }
}
