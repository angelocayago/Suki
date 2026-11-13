<?php

namespace Tests\Feature;

use App\Models\{LogisticsProvider, Order, Seller, SellerOrder, Shipment, ShipmentSmsNotification, User};
use App\Services\ShipmentSmsService;
use App\Support\PhilippineMobileNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShipmentSmsNotificationTest extends TestCase
{
    use RefreshDatabase;
    private Shipment $shipment;
    private User $buyer;
    private LogisticsProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync', 'services.transactional_sms.driver' => 'test', 'services.transactional_sms.enabled' => false]);
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'phone' => '09171234567']);
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $seller = Seller::create(['user_id' => $sellerUser->id, 'name' => 'SMS Seller', 'slug' => 'sms-seller', 'status' => 'approved']);
        $order = Order::create(['order_number' => 'SMS-ORDER-1', 'buyer_id' => $this->buyer->id, 'status' => 'OUT_FOR_DELIVERY', 'payment_method' => 'cod', 'payment_status' => 'PENDING', 'subtotal' => 100, 'shipping_fee' => 0, 'discount_amount' => 0, 'total_amount' => 100, 'recipient_name' => 'Buyer', 'recipient_phone' => '09171234567', 'province' => 'Laguna', 'municipality' => 'Santa Cruz', 'barangay' => 'Duhat', 'street_address' => 'Safe address']);
        $sellerOrder = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'pending', 'workflow_status' => 'OUT_FOR_DELIVERY']);
        $logisticsUser = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $this->provider = LogisticsProvider::create(['user_id' => $logisticsUser->id, 'name' => 'SMS Logistics', 'slug' => 'sms-logistics', 'status' => 'approved']);
        $this->shipment = Shipment::create(['seller_order_id' => $sellerOrder->id, 'logistics_provider_id' => $this->provider->id, 'tracking_code' => 'SUKI-SMS-001', 'workflow_status' => 'OUT_FOR_DELIVERY', 'status' => 'out_for_delivery', 'fee_minor' => 0, 'cod_amount_minor' => 10000, 'cod_collected' => false, 'attempts' => 0]);
    }

    public function test_normalises_phone_and_queues_each_event_once_without_network(): void
    {
        $this->assertSame('+639171234567', PhilippineMobileNumber::normalise('0917 123 4567'));
        $this->assertNull(PhilippineMobileNumber::normalise('08171234567'));
        $service = app(ShipmentSmsService::class);
        $first = $service->queue($this->shipment, ShipmentSmsService::OUT_FOR_DELIVERY);
        $duplicate = $service->queue($this->shipment, ShipmentSmsService::OUT_FOR_DELIVERY);
        $accepted = $service->queue($this->shipment, ShipmentSmsService::DELIVERY_ACCEPTED);
        $failed = $service->queue($this->shipment, ShipmentSmsService::DELIVERY_ATTEMPT_FAILED);
        $delivery = $service->queue($this->shipment, ShipmentSmsService::DELIVERED);
        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame('SENT', $first->fresh()->status);
        $this->assertSame('SENT', $accepted->fresh()->status);
        $this->assertSame('SENT', $failed->fresh()->status);
        $this->assertSame('SENT', $delivery->fresh()->status);
        $this->assertStringContainsString('₱100.00', $first->body);
        $this->assertDatabaseCount('shipment_sms_notifications', 4);
    }

    public function test_invalid_phone_is_cancelled_without_breaking_the_shipment(): void
    {
        $this->shipment->sellerOrder->order->update(['recipient_phone' => 'not-a-phone']);
        $notice = app(ShipmentSmsService::class)->queue($this->shipment, ShipmentSmsService::DELIVERY_ATTEMPT_FAILED);
        $this->assertSame('CANCELLED', $notice->status);
        $this->assertSame('OUT_FOR_DELIVERY', $this->shipment->fresh()->workflow_status->value);
    }

    public function test_provider_failure_is_recorded_without_network_or_transition_rollback(): void
    {
        config(['services.transactional_sms.driver' => 'semaphore', 'services.transactional_sms.enabled' => true, 'services.transactional_sms.api_key' => 'test-key', 'services.transactional_sms.endpoint' => 'https://sms.invalid']);
        Http::fake(['https://sms.invalid' => Http::response([], 500)]);
        $notice = app(ShipmentSmsService::class)->queue($this->shipment, ShipmentSmsService::DELIVERED);
        $this->assertSame('FAILED', $notice->fresh()->status);
        $this->assertSame('OUT_FOR_DELIVERY', $this->shipment->fresh()->workflow_status->value);
        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'Safe address'));
    }

    public function test_admin_and_provider_logs_are_scoped(): void
    {
        app(ShipmentSmsService::class)->queue($this->shipment, ShipmentSmsService::DELIVERED);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.sms-notifications.index'))->assertOk()->assertSee('SUKI-SMS-001');
        $this->actingAs($this->provider->owner)->get(route('logistics.sms-notifications.index'))->assertOk()->assertSee('SUKI-SMS-001')->assertDontSee('09171234567');
        $this->actingAs($this->buyer)->get(route('logistics.sms-notifications.index'))->assertForbidden();
    }
}
