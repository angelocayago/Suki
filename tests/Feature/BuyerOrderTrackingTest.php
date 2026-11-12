<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{DeliveryEvent, Order, Seller, SellerOrder, Shipment, User};
use App\Support\BuyerOrderTracking;
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Storage};
use Tests\Support\{BuyerPurchasesSchema, IsolatedStorageTestCase};

class BuyerOrderTrackingTest extends IsolatedStorageTestCase
{
    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Isolated SQLite required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        BuyerPurchasesSchema::create();
        Storage::fake('public'); Mail::fake(); Notification::fake(); Http::preventStrayRequests();
    }

    private function purchase(int $packages = 1, string $status = 'PLACED'): array
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $order = Order::create(['order_number' => 'TRACK-'.$buyer->id, 'reference' => 'TRACK-'.$buyer->id,
            'buyer_id' => $buyer->id, 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'subtotal' => 100, 'shipping_fee' => 50, 'discount_amount' => 0, 'total_amount' => 150,
            'placed_at' => '2026-11-06 15:59:00', 'recipient_name' => 'Synthetic buyer', 'recipient_phone' => '09000000000',
            'province' => 'Fixture province', 'municipality' => 'Fixture city', 'barangay' => 'Fixture barangay', 'street_address' => 'Fixture street']);
        for ($i = 0; $i < $packages; $i++) {
            $owner = User::factory()->create(['role' => 'seller', 'status' => 'active']);
            $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Store '.$i, 'slug' => 'store-'.$buyer->id.'-'.$i, 'status' => 'approved']);
            $parcel = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id,
                'subtotal_minor' => 10000, 'shipping_fee_minor' => 5000, 'commission_minor' => 0,
                'status' => OrderStatus::from($status)->legacySellerStatus(), 'workflow_status' => $status]);
            Shipment::create(['seller_order_id' => $parcel->id, 'tracking_code' => $order->order_number.'-'.$i,
                'status' => OrderStatus::from($status)->legacyShipmentStatus(), 'workflow_status' => $status,
                'fee_minor' => 5000, 'cod_amount_minor' => 15000, 'cod_collected' => false, 'attempts' => 0]);
        }
        return [$buyer, $order];
    }

    private function graph(Order $order): Order
    {
        return $order->fresh()->load(['sellerOrders.seller', 'sellerOrders.shipment.events' => fn ($q) => $q->orderBy('id')]);
    }

    private function event(Shipment $shipment, string $status, string $time = '2026-11-06 16:05:00', int $attempt = 0, ?string $note = null): DeliveryEvent
    {
        return $shipment->events()->create(['status' => $status, 'occurred_at' => $time, 'attempt' => $attempt, 'user_id' => 1, 'note' => $note]);
    }

    public function test_current_status_never_invents_prior_milestones_and_only_explicit_times_are_shown(): void
    {
        [$buyer, $order] = $this->purchase();
        $parcel = $order->sellerOrders->first(); $shipment = $parcel->shipment;
        foreach (OrderStatus::cases() as $status) {
            $parcel->update(['workflow_status' => $status, 'status' => $status->legacySellerStatus()]);
            $shipment->update(['workflow_status' => $status, 'status' => $status->legacyShipmentStatus(), 'status_changed_at' => '2026-11-06 16:10:00']);
            $data = app(BuyerOrderTracking::class)->present($this->graph($order));
            $this->assertSame([], $data['packages'][0]['main']);
            $this->assertSame([], $data['packages'][0]['activity']);
            $this->assertSame(BuyerOrderTracking::label($status->value), $data['packages'][0]['current']);
            if ($column = $status->timestampColumn()) {
                $parcel->update([$column => '2026-11-06 16:05:00']);
                $data = app(BuyerOrderTracking::class)->present($this->graph($order));
                $entries = [...$data['packages'][0]['main'], ...$data['packages'][0]['activity']];
                $this->assertCount(1, $entries); $this->assertSame($status->value, $entries[0]['code']);
                $this->assertSame('Nov 7, 2026 · 12:05 AM PHT', $entries[0]['time']['label']);
                $parcel->update([$column => null]);
            }
        }
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order, $parcel]))->assertOk()
            ->assertSee('Last recorded update')->assertSee('Nov 7, 2026')->assertDontSee('style="width:', false);
    }

    public function test_package_sources_ignore_parent_milestones_and_presenter_executes_no_queries(): void
    {
        [, $order] = $this->purchase(2, 'PREPARING');
        $order->update(['confirmed_at' => '2026-11-06 16:01:00', 'delivered_at' => '2026-11-06 16:02:00']);
        $graph = $this->graph($order);
        DB::enableQueryLog(); DB::flushQueryLog();
        $data = app(BuyerOrderTracking::class)->present($graph);
        $this->assertSame([], DB::getQueryLog()); DB::disableQueryLog();
        foreach ($data['packages'] as $package) $this->assertSame([], $package['main']);
        $this->assertSame('Order placed', $data['status']);
        $this->assertSame('Seller preparing', $data['packages'][0]['current']);
    }

    public function test_event_deduplication_keeps_distinct_times_attempts_and_stable_id_order(): void
    {
        [, $order] = $this->purchase(2, 'OUT_FOR_DELIVERY');
        [$a, $b] = $order->sellerOrders->all();
        $a->update(['picked_up_at' => '2026-11-06 16:05:00']);
        $picked = $this->event($a->shipment, 'PICKED_UP');
        $one = $this->event($a->shipment, 'DELIVERY_ATTEMPT_FAILED', attempt: 1, note: 'Buyer unavailable: SECRET A');
        $two = $this->event($a->shipment, 'DELIVERY_ATTEMPT_FAILED', attempt: 2, note: 'Buyer unavailable: SECRET B');
        $retry = $this->event($a->shipment, 'DELIVERY_RESCHEDULED', attempt: 1);
        $retry2 = $this->event($a->shipment, 'DELIVERY_RESCHEDULED', attempt: 2);
        $this->event($b->shipment, 'PICKED_UP');
        $entries = app(BuyerOrderTracking::class)->present($this->graph($order))['packages'];
        $this->assertSame([$picked->id, $one->id, $two->id, $retry->id, $retry2->id], array_column($entries[0]['main'], 'id'));
        $this->assertCount(1, $entries[1]['main']);
        $this->assertSame('Buyer unavailable', $entries[0]['main'][1]['reason']);
        $a->update(['picked_up_at' => '2026-11-06 16:06:00']);
        $entries = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertCount(6, $entries['packages'][0]['main']);
        $this->assertSame('2026-11-06 16:06:00.000000', $entries['packages'][0]['main'][5]['time']['sort']);
    }

    public function test_exception_history_stays_visible_and_never_exposes_raw_notes_or_financial_events(): void
    {
        [$buyer, $order] = $this->purchase(status: 'DELIVERY_FAILED');
        $parcel = $order->sellerOrders->first();
        $this->event($parcel->shipment, 'DELIVERY_ATTEMPT_FAILED', attempt: 1, note: 'Buyer unavailable: SECRET RIDER 09123456789');
        $this->event($parcel->shipment, 'DELIVERY_ISSUE_REPORTED', note: 'UNRECOGNIZED PRIVATE NOTE');
        $this->event($parcel->shipment, 'PICKUP_RIDER_REASSIGNED', note: 'PRIVATE RIDER NAME');
        $this->event($parcel->shipment, 'COD_COLLECTED', note: 'PRIVATE FINANCIAL NOTE');
        $this->event($parcel->shipment, 'DELIVERY_RESCHEDULED');
        $parcel->shipment->update(['rescheduled_at' => '2026-11-07 01:00:00']);
        $response = $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order, $parcel]))->assertOk()
            ->assertSee('Attempt 1')->assertSee('Reason: Buyer unavailable')->assertSee('Delivery issue reported')
            ->assertSee('Delivery rescheduled')->assertSee('Recorded rescheduled time')->assertSee('More activity');
        foreach (['SECRET RIDER', '09123456789', 'UNRECOGNIZED PRIVATE NOTE', 'PRIVATE RIDER NAME', 'COD_COLLECTED', 'PRIVATE FINANCIAL NOTE'] as $secret) $response->assertDontSee($secret);
        $dom = new \DOMDocument(); @$dom->loadHTML($response->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//details//li[contains(.,"Attempt 1") or contains(.,"Delivery rescheduled")]')->length);
        $this->assertSame(1, $xpath->query('//details//li[contains(.,"Pickup rider reassigned")]')->length);
        $this->assertSame(1, $xpath->query('//li[@aria-current="step"]')->length);
    }

    public function test_cancellation_overrides_stale_packages_and_does_not_duplicate_or_leak_reasons(): void
    {
        [$buyer, $order] = $this->purchase(status: 'OUT_FOR_DELIVERY');
        $parcel = $order->sellerOrders->first();
        $order->update(['status' => 'CANCELLED', 'cancelled_at' => '2026-11-06 16:06:00', 'cancel_reason' => 'changed_mind']);
        $this->event($parcel->shipment, 'CANCELLED', note: 'INTERNAL CANCELLATION');
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame('Cancelled', $data['packages'][0]['current']);
        $this->assertCount(1, array_filter($data['packages'][0]['main'], fn ($e) => $e['code'] === 'CANCELLED'));
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order, $parcel]))->assertOk()
            ->assertSee('I changed my mind')->assertDontSee('INTERNAL CANCELLATION');
        $this->getJson(route('buyer.seller-order.tracking-data', [$order, $parcel]))->assertOk()->assertJsonPath('terminal', true)->assertJsonPath('trackable', false);
        $order->update(['cancelled_at' => null, 'cancel_reason' => 'PRIVATE CANCELLATION TEXT']);
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame([], $data['packages'][0]['main']);
        $this->assertNull($data['packages'][0]['cancel_reason']);
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertDontSee('PRIVATE CANCELLATION TEXT');
    }

    public function test_unknown_status_missing_shipment_and_historical_order_are_honest_and_safe(): void
    {
        [$buyer, $order] = $this->purchase(); $parcel = $order->sellerOrders->first();
        DB::table('seller_orders')->where('id', $parcel->id)->update(['workflow_status' => 'PRIVATE LEGACY STATE']);
        DB::table('shipments')->where('seller_order_id', $parcel->id)->update(['workflow_status' => 'PRIVATE LEGACY STATE']);
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order, $parcel]))->assertOk()->assertSee('Status unavailable')->assertDontSee('PRIVATE LEGACY STATE');
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertSee('Status unavailable')->assertDontSee('PRIVATE LEGACY STATE');
        $parcel->shipment->delete();
        $this->get(route('buyer.seller-order.track', [$order, $parcel]))->assertOk()->assertSee('Your seller is still preparing this package. Tracking will appear once it ships.');
        $this->getJson(route('buyer.seller-order.tracking-data', [$order, $parcel]))->assertNotFound();
        $parcel->delete(); $order->update(['placed_at' => null]);
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertSee('Order record created')->assertSee('Historical order tracking');
    }

    public function test_multiple_packages_are_separate_and_tracking_json_query_count_does_not_grow(): void
    {
        [$buyer, $order] = $this->purchase(); $parcel = $order->sellerOrders->first();
        $this->event($parcel->shipment, 'DELIVERED', '2026-11-06 16:05:00');
        $measure = function () use ($buyer, $order, $parcel) {
            DB::enableQueryLog(); DB::flushQueryLog();
            $response = $this->actingAs($buyer)->getJson(route('buyer.seller-order.tracking-data', [$order, $parcel]))->assertOk();
            $queries = [count(DB::getQueryLog())];
            foreach ([route('buyer.seller-order.track', [$order, $parcel]), route('buyer.order-details', $order->order_number)] as $url) {
                DB::flushQueryLog(); $this->get($url)->assertOk(); $queries[] = count(DB::getQueryLog());
            }
            DB::disableQueryLog(); return [$queries, $response];
        };
        [$before] = $measure();
        for ($i = 1; $i < 5; $i++) {
            $seller = Seller::create(['name' => 'Additional store '.$i, 'slug' => 'extra-'.$i, 'status' => 'approved']);
            $so = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'workflow_status' => 'PREPARING', 'status' => 'packed']);
            $shipment = Shipment::create(['seller_order_id' => $so->id, 'tracking_code' => 'EXTRA-'.$i, 'workflow_status' => 'PREPARING', 'status' => 'unassigned']);
            $this->event($shipment, 'CONFIRMED');
        }
        [$after, $response] = $measure();
        $this->assertSame($before, $after);
        $this->assertSame(5, substr_count($response->json('tracking_html'), 'aria-label="Package from'));
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame(['DELIVERED'], array_column($data['packages'][0]['main'], 'code'));
        $this->assertSame(['CONFIRMED'], array_column($data['packages'][1]['main'], 'code'));
        $this->assertSame('Seller preparing', $data['packages'][1]['current']);
    }

    public function test_recorded_times_sort_before_event_ids_and_conflicting_statuses_are_not_reconciled_by_the_ui(): void
    {
        [$buyer, $order] = $this->purchase(status: 'OUT_FOR_DELIVERY'); $parcel = $order->sellerOrders->first();
        $later = $this->event($parcel->shipment, 'OUT_FOR_DELIVERY', '2026-11-06 16:10:00');
        $earlier = $this->event($parcel->shipment, 'PICKED_UP', '2026-11-06 16:05:00');
        $parcel->update(['workflow_status' => 'PREPARING']);
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame([$earlier->id,$later->id], array_column($data['packages'][0]['main'], 'id'));
        $this->assertSame('2026-11-06 16:05:00', $earlier->getRawOriginal('occurred_at'));
        $this->assertSame('2026-11-07T00:05:00+08:00', $data['packages'][0]['main'][0]['time']['iso']);
        $this->assertTrue($data['packages'][0]['inconsistent']);
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$order,$parcel]))->assertOk()
            ->assertSee('Tracking records are not yet in sync.')->assertSee('Seller preparing')->assertSee('Out for delivery');
        $this->assertSame('PREPARING', $parcel->fresh()->getRawOriginal('workflow_status'));
        $parcel->shipment->update(['workflow_status' => 'PLACED']);
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame('The recorded seller and delivery statuses differ. Please check the updates below.', $data['packages'][0]['message']);
    }

    public function test_authorization_json_compatibility_and_all_tracking_reads_do_not_write_application_records(): void
    {
        [$buyer, $order] = $this->purchase(status: 'OUT_FOR_DELIVERY'); $parcel = $order->sellerOrders->first();
        $url = route('buyer.seller-order.track', [$order, $parcel]); $json = route('buyer.seller-order.tracking-data', [$order, $parcel]);
        $this->get($url)->assertRedirect(route('login')); $this->getJson($json)->assertUnauthorized();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->get($url)->assertNotFound(); $this->getJson($json)->assertNotFound();
        [, $otherOrder] = $this->purchase();
        $this->actingAs($buyer)->get(route('buyer.seller-order.track', [$otherOrder, $parcel]))->assertNotFound();
        $this->getJson(route('buyer.seller-order.tracking-data', [$otherOrder, $parcel]))->assertNotFound();
        $this->get(route('buyer.seller-order.track', [999999, $parcel]))->assertNotFound();
        $writes = [];
        DB::listen(function ($query) use (&$writes) { if (!preg_match('/^\s*(select|pragma|with)\b/i', $query->sql)) $writes[] = $query->sql; });
        $this->get($url)->assertOk();
        $this->get(route('buyer.order-details', $order->order_number))->assertOk()->assertSee('Fixture street')->assertSee('Overall order status');
        $this->getJson($json)->assertOk()->assertJsonStructure(['tracking_code','status','latitude','longitude','updated_at','trackable'])
            ->assertJsonPath('status', 'OUT_FOR_DELIVERY')->assertJsonPath('trackable', true)->assertJsonPath('latitude', null);
        $this->assertSame([], $writes);
        $this->assertSame('150.00', $order->fresh()->total_amount); $this->assertSame('UNPAID', $order->fresh()->payment_status);
        $this->assertSame(0, DeliveryEvent::count());
    }

    public function test_suspended_unapproved_and_wrong_role_accounts_remain_blocked(): void
    {
        [$buyer, $order] = $this->purchase(); $parcel = $order->sellerOrders->first();
        $url = route('buyer.seller-order.track', [$order, $parcel]);
        $buyer->update(['status' => 'pending']); $this->actingAs($buyer)->get($url)->assertForbidden();
        $buyer->update(['status' => 'active', 'is_suspended' => true]); $this->actingAs($buyer)->get($url)->assertForbidden();
        $buyer->update(['is_suspended' => false, 'role' => 'seller']); $this->actingAs($buyer)->get($url)->assertForbidden();
    }

    public function test_rts_and_terminal_statuses_and_missing_event_times_remain_truthful(): void
    {
        [$buyer, $order] = $this->purchase(status: 'RTS_INITIATED'); $parcel = $order->sellerOrders->first();
        $this->event($parcel->shipment, 'DELIVERY_ATTEMPT_FAILED', attempt: 3, note: 'Buyer refused delivery: SECRET');
        $this->actingAs($buyer)->get($url = route('buyer.seller-order.track', [$order, $parcel]))->assertOk()->assertSee('Return to sender initiated');
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame(['DELIVERY_ATTEMPT_FAILED'], array_column($data['packages'][0]['main'], 'code'));
        foreach (['RTS_RETURNING', 'RTS_COMPLETED', 'DELIVERED', 'COMPLETED'] as $status) {
            $parcel->update(['workflow_status' => $status]); $parcel->shipment->update(['workflow_status' => $status]);
            $this->getJson(route('buyer.seller-order.tracking-data', [$order, $parcel]))->assertOk()
                ->assertJsonPath('terminal', $status !== 'RTS_RETURNING')->assertJsonPath('latitude', null);
        }
        $event = $this->event($parcel->shipment, 'DELIVERED'); DB::table('delivery_events')->where('id', $event->id)->update(['occurred_at' => null]);
        $data = app(BuyerOrderTracking::class)->present($this->graph($order));
        $this->assertSame(['DELIVERY_ATTEMPT_FAILED'], array_column($data['packages'][0]['main'], 'code'));
    }
}
