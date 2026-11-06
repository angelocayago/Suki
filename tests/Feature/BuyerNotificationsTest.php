<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\{Order, Seller, SellerOrder, Shipment, User};
use App\Notifications\DomainEventNotification;
use App\Services\OrderWorkflowService;
use Illuminate\Support\Facades\{DB, Http, Storage};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\{BuyerPurchasesSchema, IsolatedStorageTestCase};

class BuyerNotificationsTest extends IsolatedStorageTestCase
{
    private User $buyer;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('SQLite memory required.');
        parent::setUp();
        $this->assertStoragePathIsIsolated();
        BuyerPurchasesSchema::create();
        Storage::fake('public'); Http::preventStrayRequests();
        // Minimal synthetic rider records for authorized ERP transitions.
        DB::statement('CREATE TABLE riders (id INTEGER PRIMARY KEY, user_id INTEGER, is_active INTEGER, logistics_provider_id INTEGER)');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'is_suspended' => false]);
        $this->travelTo(now()->startOfSecond());
    }

    private function notice(User $user, string $title, array $context = []): string
    {
        $user->notify(new DomainEventNotification($title, 'A purchase update.', $context));
        return $user->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail()->id;
    }

    private function purchase(string $number = 'NOTICE-ORDER', string $status = 'DELIVERED', ?User $buyer = null): SellerOrder
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Synthetic Shop', 'slug' => strtolower($number), 'status' => 'approved']);
        $order = Order::create(['buyer_id' => ($buyer ?? $this->buyer)->id, 'order_number' => $number, 'reference' => $number,
            'status' => $status, 'payment_method' => 'card', 'payment_status' => 'PAID', 'subtotal' => 100, 'total_amount' => 100,
            'shipping_fee' => 0, 'delivered_at' => now()->subDays(3), 'recipient_name' => 'Synthetic Buyer']);
        $state = OrderStatus::from($status);
        $parcel = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'status' => $state->legacySellerStatus(),
            'workflow_status' => $status, 'delivered_at' => now()->subDays(3), 'subtotal_minor' => 10000]);
        Shipment::create(['seller_order_id' => $parcel->id, 'workflow_status' => $status, 'status' => $state->legacyShipmentStatus(),
            'tracking_code' => $number, 'proof_path' => 'synthetic-proof.jpg', 'cod_collected' => false]);
        return $parcel;
    }

    public function test_bell_page_filters_counts_and_read_actions_are_visible_and_private(): void
    {
        $parcel = $this->purchase();
        $own = $this->notice($this->buyer, 'Owned purchase', ['seller_order_id' => $parcel->id]);
        $this->travel(1)->seconds();
        $read = $this->notice($this->buyer, 'Previously read');
        $this->buyer->notifications()->findOrFail($read)->markAsRead();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $foreign = $this->notice($other, 'Private other notification');
        $response = $this->actingAs($this->buyer)->get(route('notifications.page'))->assertOk()
            ->assertSee('Owned purchase')->assertSee('Previously read')->assertSee('NOTICE-ORDER')->assertSee('1 unread')
            ->assertSee('View all notifications')->assertSee('Mark all as read')->assertDontSee('Private other notification');
        $this->assertSame(1, substr_count($response->getContent(), '<details class="bn-bell"'));
        $this->get(route('notifications.page', ['filter' => 'unread']))->assertOk()->assertViewHas('rows', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $own);
        $this->getJson(route('notifications.unread-count'))->assertJsonPath('unread_count', 1);
        $this->postJson(route('notifications.read', $foreign))->assertNotFound();
        $this->post(route('notifications.open', $foreign))->assertNotFound();
        $this->postJson(route('notifications.read', $own))->assertOk()->assertJsonPath('unread_count', 0);
        $this->postJson(route('notifications.read', $own))->assertJsonPath('unread_count', 0);
        $this->notice($this->buyer, 'New update');
        $this->post(route('notifications.read-all', ['filter' => 'unread']))->assertRedirect(route('notifications.page', ['filter' => 'unread']));
        $this->get(route('notifications.page', ['filter' => 'unread']))->assertSee('You’re all caught up');
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->getJson(route('notifications.preview'))->assertJsonPath('unread_count', 0)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_open_rechecks_order_ownership_ignores_untrusted_urls_and_marks_read(): void
    {
        $parcel = $this->purchase();
        $id = $this->notice($this->buyer, 'On the way', ['seller_order_id' => $parcel->id, 'status' => 'OUT_FOR_DELIVERY']);
        $this->actingAs($this->buyer)->post(route('notifications.open', $id))->assertRedirect(route('buyer.seller-order.track', [$parcel->order_id, $parcel->id]));
        $this->assertSame(0, $this->buyer->unreadNotifications()->count());
        $this->get(route('buyer.seller-order.track', [$parcel->order_id, $parcel->id]))->assertOk();
        $this->travel(1)->seconds();
        $id = $this->notice($this->buyer, 'Completed', ['order_id' => $parcel->order_id]);
        $this->post(route('notifications.open', $id))->assertRedirect(route('buyer.order-details', $parcel->order->order_number));
        $this->get(route('buyer.order-details', $parcel->order->order_number))->assertOk();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $foreign = $this->purchase('FOREIGN-ORDER', buyer: $other);
        $this->travel(1)->seconds();
        $bad = $this->notice($this->buyer, 'Invalid context', ['order_id' => $foreign->order_id, 'url' => 'https://outside.test']);
        $this->post(route('notifications.open', $bad))->assertRedirect(route('notifications.page'));
        $this->travel(1)->seconds();
        $mismatch = $this->notice($this->buyer, 'Mismatch', ['seller_order_id' => $parcel->id, 'order_id' => $foreign->order_id]);
        $this->post(route('notifications.open', $mismatch))->assertRedirect(route('notifications.page'));
        $this->get(route('notifications.page'))->assertDontSee('FOREIGN-ORDER')->assertDontSee('https://outside.test');
    }

    public function test_recent_limit_pagination_ordering_and_escaped_content(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->notice($this->buyer, $i === 26 ? '<script>unsafe()</script>' : 'Update '.$i);
            $this->travel(1)->seconds();
        }
        $preview = $this->actingAs($this->buyer)->getJson(route('notifications.preview'))->assertOk()->assertJsonCount(5, 'notifications')
            ->assertJsonPath('notifications.0.title', '<script>unsafe()</script>');
        $this->get(route('notifications.page'))->assertOk()->assertViewHas('notifications', fn ($page) => $page->total() === 27 && $page->count() === 25)
            ->assertSee('&lt;script&gt;unsafe()&lt;/script&gt;', false)->assertDontSee('<script>unsafe()</script>', false);
        $this->get(route('notifications.page', ['page' => 2]))->assertOk()->assertViewHas('rows', fn ($rows) => count($rows) === 2);
        $this->getJson(route('notifications.index', ['filter' => 'unread']))->assertJsonPath('notifications.total', 27);
    }

    public function test_empty_and_access_controls(): void
    {
        $this->getJson(route('notifications.preview'))->assertUnauthorized();
        $this->actingAs($this->buyer)->get(route('notifications.page'))->assertOk()->assertSee('No notifications yet');
        $this->buyer->update(['is_suspended' => true]);
        $this->get(route('notifications.page'))->assertForbidden();
        $this->getJson(route('notifications.preview'))->assertForbidden();
        $this->actingAs($this->buyer)->postJson(route('notifications.read-all'))->assertForbidden();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->getJson(route('notifications.preview'))->assertForbidden();
    }

    public static function updates(): array
    {
        return [['PLACED', 'CONFIRMED', 'Order confirmed'], ['READY_FOR_PICKUP', 'PICKED_UP', 'Order shipped'],
            ['ASSIGNED_TO_RIDER', 'OUT_FOR_DELIVERY', 'Out for delivery'], ['OUT_FOR_DELIVERY', 'DELIVERED', 'Order delivered']];
    }

    #[DataProvider('updates')]
    public function test_real_transitions_create_readable_clickable_buyer_notifications_once(string $from, string $to, string $title): void
    {
        $parcel = $this->purchase(status: $from);
        if ($to === 'CONFIRMED') {
            $actor = $parcel->seller->owner;
        } else {
            $actor = User::factory()->create(['role' => 'rider', 'status' => 'active', 'is_suspended' => false]);
            $rider = DB::table('riders')->insertGetId(['user_id' => $actor->id, 'is_active' => true]);
            $parcel->shipment->update(['pickup_rider_id' => $rider, 'rider_id' => $rider, 'delivery_accepted_at' => now()]);
        }
        $workflow = app(OrderWorkflowService::class);
        $workflow->transition($parcel, $actor, $to);
        $notice = $this->buyer->notifications()->sole();
        $this->assertSame($title, $notice->data['title']);
        $this->assertStringContainsString('NOTICE-ORDER', $notice->data['message']);
        $this->assertSame($to, $notice->data['context']['status']);
        try { $workflow->transition($parcel, $actor, $to); $this->fail('Repeated transition accepted.'); }
        catch (ValidationException $exception) { $this->assertSame(1, $this->buyer->notifications()->count()); }
        $this->actingAs($this->buyer)->post(route('notifications.open', $notice->id))->assertRedirect($to === 'CONFIRMED'
            ? route('buyer.order-details', 'NOTICE-ORDER') : route('buyer.seller-order.track', [$parcel->order_id, $parcel->id]));
    }

    public function test_manual_and_automatic_completion_deliver_once_and_failures_send_nothing(): void
    {
        $manual = $this->purchase('MANUAL');
        $url = route('order.status.update', 'MANUAL');
        $this->actingAs($this->buyer)->post($url, ['status' => 'completed'])->assertSessionHas('success');
        $this->post($url, ['status' => 'completed'])->assertSessionHas('success');
        $this->assertSame(1, $this->buyer->notifications()->count());
        $this->assertSame('Order completed', $this->buyer->notifications()->sole()->data['title']);
        $this->assertSame('COMPLETED', $manual->order->fresh()->status);
        $auto = $this->purchase('AUTO');
        $early = $this->purchase('EARLY'); $early->update(['delivered_at' => now()->subDays(3)->addSecond()]);
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->assertSame(2, $this->buyer->notifications()->count());
        $this->assertSame('COMPLETED', $auto->order->fresh()->status);
        $this->assertSame('DELIVERED', $early->order->fresh()->status);
        $this->assertTrue($this->buyer->notifications()->get()->contains(fn ($n) => str_contains($n->data['message'], 'automatically completed')));
        $this->artisan('notifications:retry')->assertSuccessful();
        $this->assertSame(2, $this->buyer->notifications()->count());
    }

    public function test_historical_parent_only_completion_also_notifies_once(): void
    {
        foreach (['LEGACY-MANUAL', 'LEGACY-AUTO'] as $number) {
            $parcel = $this->purchase($number);
            $parcel->shipment->delete();
            $parcel->delete();
        }
        $this->actingAs($this->buyer)->post(route('order.status.update', 'LEGACY-MANUAL'), ['status' => 'completed'])->assertSessionHas('success');
        $this->post(route('order.status.update', 'LEGACY-MANUAL'), ['status' => 'completed'])->assertSessionHas('success');
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->assertSame(2, $this->buyer->notifications()->count());
        foreach ($this->buyer->notifications as $notice) {
            $this->assertSame('Order completed', $notice->data['title']);
            $this->assertSame('COMPLETED', $notice->data['context']['status']);
            $this->post(route('notifications.open', $notice->id))->assertRedirect(route('buyer.order-details', Order::findOrFail($notice->data['context']['order_id'])->order_number));
        }
    }
}
