<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{DeliveryEvent, Order, Seller, SellerOrder, Shipment, User};
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Tests\Support\IsolatedStorageTestCase;

class BuyerPurchasesTest extends IsolatedStorageTestCase
{
    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated SQLite memory required.');
        }
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStoragePathIsIsolated();
        \Tests\Support\BuyerPurchasesSchema::create();
        Storage::fake('public'); Mail::fake(); Notification::fake(); Http::preventStrayRequests();
        $this->travelTo(now()->startOfSecond());
    }

    private function purchase(string $number = 'RECEIPT-ORDER', string $status = 'DELIVERED', int $parcels = 1): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $order = Order::create(['order_number' => $number, 'reference' => $number, 'buyer_id' => $buyer->id,
            'status' => $status, 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'subtotal' => 100,
            'shipping_fee' => 0, 'total_amount' => 100, 'total_minor' => 10000, 'placed_at' => now(),
            'recipient_name' => 'Test Buyer', 'recipient_phone' => '09123456789', 'province' => 'Test Province',
            'municipality' => 'Test City', 'barangay' => 'Test Barangay', 'street_address' => 'Test Street',
            'delivered_at' => $status === 'DELIVERED' ? now()->subDays(3) : null]);
        for ($i = 0; $i < $parcels; $i++) {
            $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
            $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'Receipt Shop '.$i, 'slug' => strtolower($number).'-'.$i, 'status' => 'approved']);
            $state = OrderStatus::from($status);
            $so = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 10000,
                'shipping_fee_minor' => 0, 'commission_minor' => 0, 'workflow_status' => $status, 'status' => $state->legacySellerStatus(),
                'delivered_at' => $order->delivered_at]);
            Shipment::create(['seller_order_id' => $so->id, 'tracking_code' => $number.'-'.$i, 'workflow_status' => $status,
                'status' => $state->legacyShipmentStatus(), 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);
            $order->items()->create(['seller_id' => $sellerUser->id, 'seller_order_id' => $so->id, 'product_slug' => 'receipt-item',
                'product_name' => 'Receipt Product', 'variant_name' => 'Pack of 6', 'unit_price' => 100, 'unit_price_minor' => 10000, 'quantity' => 1, 'line_total' => 100]);
        }
        return [$buyer, $order];
    }

    public function test_manual_receipt_updates_every_parcel_and_seller_pages_once(): void
    {
        [$buyer, $order] = $this->purchase(parcels: 2);
        foreach ($order->sellerOrders as $parcel) {
            $parcel->update(['delivered_at' => now()]);
        }
        $url = route('order.status.update', $order->order_number);
        $this->actingAs($buyer)->get(route('buyer.my-orders'))->assertOk()->assertSee('Order Received')->assertSee('Pack of 6')->assertSee('Receipt Shop 0');
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()
            ->assertSee('id="buyerReceiptDialog"', false)->assertSee('Confirm Received')->assertDontSee('window.confirm', false);
        $this->post($url, ['status' => 'completed'])->assertSessionHas('success');
        $this->assertSame('COMPLETED', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
        $events = DeliveryEvent::count();
        $notices = DB::table('notification_outbox')->count();
        $completedAt = $order->fresh()->completed_at->toDateTimeString();
        $this->post($url, ['status' => 'completed'])->assertSessionHas('success');
        $this->assertSame($events, DeliveryEvent::count());
        $this->assertSame($notices, DB::table('notification_outbox')->count());
        $this->assertSame($completedAt, $order->fresh()->completed_at->toDateTimeString());
        $this->get(route('buyer.my-orders', ['tab' => 'completed']))->assertOk()->assertSee($order->order_number)->assertDontSee('Order Received');
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertDontSee('Order Received');
        foreach ($order->fresh()->sellerOrders as $so) {
            $this->assertSame(OrderStatus::Completed, $so->workflow_status);
            $this->assertSame('completed', $so->status);
            $this->assertSame(OrderStatus::Completed, $so->shipment->workflow_status);
            $this->actingAs($so->seller->owner)->get(route('seller.orders', ['status' => 'COMPLETED']))->assertOk()->assertSee($order->order_number);
            $this->get(route('seller.dashboard'))->assertOk()->assertSee($order->order_number);
        }
    }

    public function test_receipt_and_tracking_ownership_and_undelivered_guard(): void
    {
        [$buyer, $order] = $this->purchase();
        [$other] = $this->purchase('OTHER');
        $url = route('order.status.update', $order->order_number);
        $track = route('buyer.seller-order.track', [$order, $order->sellerOrders->first()]);
        $this->actingAs($other)->post($url, ['status' => 'completed'])->assertForbidden();
        $this->get($track)->assertNotFound();
        $this->get(route('buyer.my-orders'))->assertDontSee('RECEIPT-ORDER');
        $this->get(route('buyer.order-details', $order->order_number))->assertRedirect(route('buyer.my-orders'));
        $this->actingAs($buyer)->get($track)->assertOk();
        $order->update(['status' => 'OUT_FOR_DELIVERY']);
        $this->post($url, ['status' => 'completed'])->assertSessionHas('error');
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
        $order->update(['status' => 'COMPLETED']);
        $this->actingAs($other)->post($url, ['status' => 'completed'])->assertForbidden();
    }

    public function test_tabs_follow_fulfillment_and_cod_is_not_to_pay(): void
    {
        [$buyer, $order] = $this->purchase(status: 'PLACED');
        $this->actingAs($buyer)->get(route('buyer.my-orders', ['tab' => 'to-ship']))->assertOk()->assertSee($order->order_number);
        $this->get(route('buyer.my-orders', ['tab' => 'to-pay']))->assertOk()->assertDontSee($order->order_number)->assertSee('No purchases in To Pay');
        $order->update(['payment_method' => 'card']);
        $this->get(route('buyer.my-orders', ['tab' => 'to-pay']))->assertSee($order->order_number);
        foreach (['OUT_FOR_DELIVERY' => 'to-receive', 'COMPLETED' => 'completed', 'CANCELLED' => 'cancelled'] as $status => $tab) {
            $order->update(['status' => $status]);
            $this->get(route('buyer.my-orders', ['tab' => $tab]))->assertOk()->assertSee($order->order_number);
            $this->get(route('buyer.my-orders', ['tab' => 'to-ship']))->assertDontSee($order->order_number);
        }
        $this->get(route('buyer.my-orders', ['tab' => 'unknown']))->assertStatus(422);
    }

    public function test_scheduler_boundary_undelivered_missing_timestamps_and_repeat_execution(): void
    {
        [, $due] = $this->purchase('DUE');
        [, $early] = $this->purchase('EARLY');
        $early->sellerOrders->first()->update(['delivered_at' => now()->subDays(3)->addSecond()]);
        [, $missing] = $this->purchase('MISSING');
        $missing->sellerOrders->first()->update(['delivered_at' => null]);
        [, $undelivered] = $this->purchase('UNDELIVERED', 'OUT_FOR_DELIVERY');
        $undelivered->sellerOrders->first()->update(['delivered_at' => now()->subDays(5)]);
        [, $legacy] = $this->purchase('LEGACY', parcels: 0);
        $id = $missing->sellerOrders->first()->id;
        $this->artisan('orders:complete-delivered')->expectsOutputToContain("Seller order {$id}: missing delivered_at")->assertExitCode(1);
        $this->assertSame('COMPLETED', $due->fresh()->status);
        $this->assertSame('COMPLETED', $legacy->fresh()->status);
        $this->assertSame('DELIVERED', $early->fresh()->status);
        $this->assertSame('DELIVERED', $missing->fresh()->status);
        $this->assertSame('OUT_FOR_DELIVERY', $undelivered->fresh()->status);
        $events = DeliveryEvent::count();
        $this->artisan('orders:complete-delivered')->assertExitCode(1);
        $this->assertSame($events, DeliveryEvent::count());
        $this->travel(1)->seconds();
        $this->artisan('orders:complete-delivered')->assertExitCode(1);
        $this->assertSame('COMPLETED', $early->fresh()->status);
    }

    public function test_mixed_parcels_roll_back_manual_receipt_but_automatic_completion_is_per_delivery(): void
    {
        [$buyer, $order] = $this->purchase(parcels: 2);
        $last = $order->sellerOrders->last();
        $last->update(['workflow_status' => 'OUT_FOR_DELIVERY']);
        $last->shipment->update(['workflow_status' => 'OUT_FOR_DELIVERY']);
        $this->actingAs($buyer)->post(route('order.status.update', $order->order_number), ['status' => 'completed'])->assertSessionHas('error');
        $this->assertSame(OrderStatus::Delivered, $order->sellerOrders->first()->fresh()->workflow_status);
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->assertSame(OrderStatus::Completed, $order->sellerOrders->first()->fresh()->workflow_status);
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
    }

    public function test_historical_receipt_and_missing_delivery_reporting(): void
    {
        [$buyer, $order] = $this->purchase('LEGACY-MANUAL', parcels: 0);
        $this->actingAs($buyer)->post(route('order.status.update', $order->order_number), ['status' => 'completed'])->assertSessionHas('success');
        $this->assertSame('COMPLETED', $order->fresh()->status);
        [, $missing] = $this->purchase('LEGACY-MISSING', parcels: 0);
        $missing->update(['delivered_at' => null]);
        $this->artisan('orders:complete-delivered')->expectsOutputToContain('Order LEGACY-MISSING: missing delivered_at')->assertExitCode(1);
        $this->assertSame('DELIVERED', $missing->fresh()->status);
        $this->assertNull($missing->fresh()->delivered_at);
        $this->assertNull($missing->fresh()->completed_at);
    }

    public function test_inconsistent_shipment_is_reported_and_cancelled_purchase_stays_cancelled(): void
    {
        [, $order] = $this->purchase();
        $order->sellerOrders->first()->shipment->update(['workflow_status' => 'OUT_FOR_DELIVERY']);
        [, $cancelled] = $this->purchase('CANCELLED-PURCHASE');
        $cancelled->update(['status' => 'CANCELLED']);
        $this->artisan('orders:complete-delivered')->expectsOutputToContain('inconsistent')->assertExitCode(1);
        $this->assertSame('DELIVERED', $order->fresh()->status);
        $this->assertSame('CANCELLED', $cancelled->fresh()->status);
        $this->assertSame(OrderStatus::Delivered, $cancelled->sellerOrders->first()->fresh()->workflow_status);
    }

    public function test_thumbnail_urls_use_existing_public_disk_and_preserve_external_sources(): void
    {
        Storage::disk('public')->put('products/test.jpg', 'synthetic image fixture');
        $url = Storage::disk('public')->url('products/test.jpg');
        foreach (['products/test.jpg', '/storage/products/test.jpg', 'storage/products/test.jpg'] as $path) {
            $this->assertSame($url, \App\Support\BuyerPurchases::image($path));
        }
        $this->assertSame('https://example.test/product.jpg', \App\Support\BuyerPurchases::image('https://example.test/product.jpg'));
        $this->assertSame(asset('images/logo.png'), \App\Support\BuyerPurchases::image('/images/logo.png'));
        foreach ([null, '', 'products/missing.jpg', '../private/secret.jpg', 'javascript:alert(1)', '//other.test/a.jpg'] as $path) {
            $this->assertNull(\App\Support\BuyerPurchases::image($path));
        }
    }

    public function test_purchase_pages_resolve_snapshot_paths_and_render_missing_image_fallbacks(): void
    {
        [$buyer, $order] = $this->purchase();
        $item = $order->items()->first();
        Storage::disk('public')->put('products/gelo.jpg', 'synthetic image fixture');
        $item->update(['image' => 'products/gelo.jpg']);
        foreach (['buyer.my-orders' => [], 'buyer.order-details' => [$order->order_number]] as $route => $parameters) {
            $this->actingAs($buyer)->get(route($route, $parameters))->assertOk()
                ->assertSee(Storage::disk('public')->url('products/gelo.jpg'), false)
                ->assertSee('data-receipt-form', false)->assertSee('Have you received your order? Confirm to complete your purchase.')
                ->assertSee('Orders are automatically completed 3 days after delivery.')
                ->assertDontSee('window.confirm', false);
        }
        $item->update(['image' => 'products/unavailable.jpg']);
        $this->get(route('buyer.my-orders'))->assertOk()->assertSee('Image unavailable')->assertDontSee('src="products/unavailable.jpg"', false);
        $item->update(['image' => 'https://example.test/missing.jpg']);
        $this->get(route('buyer.my-orders'))->assertSee('onerror="this.hidden = true; this.previousElementSibling.hidden = false;"', false);
    }
}
