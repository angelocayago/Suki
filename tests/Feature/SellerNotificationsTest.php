<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerVoucher;
use App\Models\User;
use App\Observers\SellerVoucherNotificationObserver;
use App\Support\SellerCategories;
use App\Support\SellerNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        User::factory()->create();
        $this->shop = $this->shop();
        $this->actingAs($this->shop->owner);
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(4, 0));
    }

    private function shop(): Seller
    {
        return Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Notification shop', 'slug' => uniqid('notif-'), 'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function notification(array $attributes = [], ?Seller $shop = null): DatabaseNotification
    {
        return DatabaseNotification::create($attributes + ['id' => (string) Str::uuid(), 'notifiable_type' => Seller::class,
            'notifiable_id' => ($shop ?? $this->shop)->id, 'type' => SellerNotifications::VOUCHER_CREATED,
            'data' => ['title' => 'Saved notification', 'message' => 'Saved message'], 'created_at' => now(), 'updated_at' => now()]);
    }

    private function voucher(array $changes = []): SellerVoucher
    {
        return SellerVoucher::create($changes + ['seller_id' => $this->shop->id, 'name' => 'Real saved configuration', 'code' => uniqid('SAVE'),
            'discount_type' => 'percentage', 'discount_bps' => 1000, 'minimum_minor' => 0, 'product_scope' => 'all',
            'starts_at' => now(), 'ends_at' => now()->addWeek(), 'per_buyer_limit' => 1, 'status' => 'paused']);
    }

    public function test_empty_inbox_bell_and_page_views_do_not_generate_history(): void
    {
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('No notifications found')->assertSee('0 unread across your inbox')->assertDontSee('data-seller-unread-count', false);
        $this->get(route('seller.vouchers.index'))->assertOk();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_voucher_insert_creates_one_shop_addressed_notification_and_duplicate_callbacks_are_idempotent(): void
    {
        $voucher = $this->voucher();
        (new SellerVoucherNotificationObserver)->created($voucher);
        (new SellerVoucherNotificationObserver)->created($voucher);
        $notification = DatabaseNotification::firstOrFail();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(Seller::class, $notification->notifiable_type);
        $this->assertEquals($this->shop->id, $notification->notifiable_id);
        $this->assertNotEquals($this->shop->user_id, $notification->notifiable_id);
        $this->assertSame($voucher->id, $notification->data['destination']['id']);
        $this->assertNull($notification->read_at);
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('Voucher created')->assertSee(route('seller.vouchers.show', $voucher->id));
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('Notifications, 1 unread')->assertSee('data-seller-unread-count', false);
        $voucher->update(['name' => 'Renamed']);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_notification_rolls_back_with_source_and_failed_activation_does_not_leave_a_notice(): void
    {
        DB::beginTransaction();
        $rolledBack = $this->voucher();
        $this->assertDatabaseCount('notifications', 1);
        DB::rollBack();
        SellerNotifications::voucherCreated($rolledBack);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_vouchers', 0);
        $this->post(route('seller.vouchers.store'), ['name' => 'Invalid activation', 'code' => 'NO-PRODUCTS', 'discount_type' => 'percentage',
            'discount_value' => '10', 'minimum_spend' => 0, 'starts_at' => '2026-10-23T12:00', 'ends_at' => '2026-10-30T12:00',
            'per_buyer_limit' => 1, 'product_scope' => 'all', 'status' => 'active'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_vouchers', 0);
    }

    public function test_existing_vouchers_are_not_backfilled_and_unsaved_instances_do_not_generate_notices(): void
    {
        $voucher = SellerVoucher::withoutEvents(fn () => $this->voucher());
        $this->get(route('seller.notifications.index'))->assertOk();
        SellerNotifications::voucherCreated($voucher->fresh());
        SellerNotifications::voucherCreated(new SellerVoucher(['id' => 999, 'seller_id' => $this->shop->id]));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_mark_read_unread_and_all_persist_and_never_modify_foreign_or_user_notifications(): void
    {
        $first = $this->notification();
        $second = $this->notification();
        $foreign = $this->notification([], $this->shop());
        $userNotification = $this->notification(['notifiable_type' => User::class, 'notifiable_id' => $this->shop->id]);
        $this->post(route('seller.notifications.read', $first->id), ['seller_id' => $foreign->notifiable_id])->assertRedirect(route('seller.notifications.index'));
        $readAt = $first->fresh()->read_at->toIso8601String();
        $this->travel(1)->hours();
        $this->post(route('seller.notifications.read', $first->id))->assertRedirect();
        $this->assertSame($readAt, $first->fresh()->read_at->toIso8601String());
        $this->assertNull($second->fresh()->read_at);
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('1 unread across your inbox');
        $this->post(route('seller.notifications.unread', $first->id))->assertRedirect();
        $this->assertNull($first->fresh()->read_at);
        $this->post(route('seller.notifications.read-all'), ['seller_id' => $foreign->notifiable_id])->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertNull($userNotification->fresh()->read_at);
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('0 unread across your inbox');
    }

    public function test_foreign_content_choices_and_mutations_are_isolated(): void
    {
        $this->notification();
        $foreign = $this->notification(['type' => 'FOREIGN-TYPE', 'data' => ['title' => 'FOREIGN-SECRET']], $this->shop());
        $this->get(route('seller.notifications.index'))->assertOk()->assertDontSee('FOREIGN-SECRET')->assertDontSee('FOREIGN-TYPE');
        foreach (['read', 'unread'] as $action) {
            $this->post(route('seller.notifications.'.$action, $foreign->id))->assertNotFound();
        }
        $this->getJson(route('seller.notifications.index', ['type' => 'FOREIGN-TYPE']))->assertUnprocessable();
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_listing_filtering_dates_chips_and_pagination_use_all_owned_records(): void
    {
        for ($i = 0; $i < 23; $i++) {
            $this->notification(['data' => ['title' => 'Notice '.$i], 'created_at' => now()->subMinutes(23 - $i)]);
        }
        $this->notification(['type' => 'system.event', 'read_at' => now(), 'created_at' => now()->subDays(2)]);
        $this->get(route('seller.notifications.index'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 24 && $p->count() === 20)
            ->assertViewHas('notifications', fn ($n) => $n->first()->title === 'Notice 22')->assertSee('23 unread across your inbox');
        $criteria = ['read_status' => 'unread', 'type' => SellerNotifications::VOUCHER_CREATED, 'date_range' => 'custom', 'from' => '2026-10-23', 'to' => '2026-10-23', 'sort' => 'oldest', 'page' => 2];
        $this->get(route('seller.notifications.index', $criteria))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 23 && $p->count() === 3)
            ->assertSee('23 unread across your inbox')->assertViewHas('filterChips', function ($chips) {
                $this->assertCount(4, $chips);
                foreach ($chips as $chip) {
                    $this->assertStringNotContainsString('page=', $chip['url']);
                }
                $date = collect($chips)->firstWhere('key', 'date_range');
                $this->assertStringNotContainsString('from=', $date['url']);
                $this->assertStringNotContainsString('to=', $date['url']);

                return true;
            });
        $this->get(route('seller.notifications.index', ['read_status' => 'read']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1)->assertSee('23 unread across your inbox');
        $this->assertSame(23, $this->shop->notifications()->whereNull('read_at')->count());
    }

    public static function invalidFilters(): array
    {
        return [[['read_status' => 'bad']], [['sort' => 'unsafe']], [['type' => ['nested']]], [['page' => 0]], [['date_range' => 'bad']],
            [['date_range' => 'custom', 'from' => '2026-10-24', 'to' => '2026-10-23']], [['date_range' => 'custom', 'from' => 'bad', 'to' => '2026-10-23']]];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $criteria): void
    {
        $this->getJson(route('seller.notifications.index', $criteria))->assertUnprocessable();
    }

    public function test_destinations_are_allowlisted_scoped_and_safe_when_resources_disappear(): void
    {
        $voucher = $this->voucher();
        $foreign = $this->voucher(['seller_id' => $this->shop()->id]);
        $this->notification(['data' => ['title' => '<script>BAD</script>', 'message' => '<img src=x onerror=BAD>', 'link' => 'https://evil.example',
            'destination' => ['type' => 'voucher', 'id' => $foreign->id]]]);
        $this->notification(['data' => ['destination' => ['type' => 'https://evil.example', 'id' => $voucher->id]]]);
        $this->notification(['data' => ['destination' => 'malformed']]);
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('&lt;script&gt;BAD&lt;/script&gt;', false)->assertDontSee('<script>BAD</script>', false)
            ->assertDontSee('https://evil.example')->assertDontSee(route('seller.vouchers.show', $foreign->id))->assertSee('Destination unavailable');
        $voucher->delete();
        $this->get(route('seller.notifications.index'))->assertOk()->assertDontSee(route('seller.vouchers.show', $voucher->id));
    }

    public function test_account_access_and_local_pending_shop_rules(): void
    {
        auth()->logout();
        $this->withSession(['seller_logged_in' => true])->get(route('seller.notifications.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'buyer']))->get(route('seller.notifications.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => true]);
        $this->actingAs($this->shop->owner->fresh())->get(route('seller.notifications.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => false]);
        $this->actingAs($this->shop->owner->fresh());
        $this->shop->update(['status' => 'pending']);
        $this->app->instance('env', 'local');
        $this->get(route('seller.notifications.index'))->assertOk();
        $this->app->instance('env', 'production');
        $this->get(route('seller.notifications.index'))->assertForbidden();
        $this->app->instance('env', 'testing');
        $this->shop->update(['status' => 'approved']);
        $second = $this->shop();
        $second->update(['user_id' => $this->shop->user_id]);
        $this->get(route('seller.notifications.index'))->assertConflict();
    }

    public function test_invalid_ids_get_mutations_and_csrf_are_rejected(): void
    {
        $n = $this->notification();
        $this->post('/seller/notifications/not-a-uuid/read')->assertNotFound();
        $this->post(route('seller.notifications.read', (string) Str::uuid()))->assertNotFound();
        $this->get(route('seller.notifications.read', $n->id))->assertStatus(405);
        $this->app->instance('env', 'production');
        $this->post(route('seller.notifications.read', $n->id))->assertStatus(419);
        $token = str_repeat('a', 40);
        $this->withSession(['_token' => $token])->post(route('seller.notifications.read', $n->id), ['_token' => $token])->assertRedirect();
        $this->app->instance('env', 'testing');
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_missing_migration_reports_unavailable_and_does_not_break_existing_voucher_pages(): void
    {
        Schema::drop('notifications');
        $this->voucher();
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('Notifications are unavailable')->assertDontSee('data-seller-unread-count', false);
        $this->get(route('seller.notifications.index'))->assertStatus(503);
        $this->post(route('seller.notifications.read-all'))->assertStatus(503);
    }

    public function test_notification_actions_do_not_mutate_business_records_or_unrelated_sessions(): void
    {
        SellerCategories::populate();
        $p = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Stock', 'slug' => 'notification-stock', 'is_active' => true]);
        $p->variants()->create(['name' => 'Default', 'sku' => 'NOTIF-STOCK', 'price_minor' => 10000, 'stock' => 9, 'is_active' => true]);
        $v = $this->voucher();
        $v->products()->attach($p->id);
        $tables = ['products', 'product_variants', 'orders', 'order_items', 'seller_orders', 'payments', 'shipments', 'delivery_events', 'seller_vouchers', 'seller_voucher_products', 'sellers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $n = DatabaseNotification::firstOrFail();
        $session = ['seller_products' => ['keep' => true], 'seller_promotions' => ['inert' => true], 'cart' => ['keep' => true]];
        $this->withSession($session)->get(route('seller.notifications.index'))->assertOk()->assertSessionHasAll($session);
        foreach (['read', 'unread'] as $action) {
            $this->post(route('seller.notifications.'.$action, $n->id))->assertRedirect()->assertSessionHasAll($session);
        }
        $this->post(route('seller.notifications.read-all'))->assertRedirect()->assertSessionHasAll($session);
        $this->assertSame($before, $snapshot());
    }

    public function test_successful_voucher_http_save_produces_a_notice_only_for_the_current_shop(): void
    {
        $foreign = $this->shop();
        $this->post(route('seller.vouchers.store'), ['seller_id' => $foreign->id, 'name' => 'HTTP configuration', 'code' => 'HTTP-SAVE', 'discount_type' => 'percentage',
            'discount_value' => '10', 'minimum_spend' => 0, 'starts_at' => '2026-10-23T12:00', 'ends_at' => '2026-10-30T12:00',
            'per_buyer_limit' => 1, 'product_scope' => 'all', 'status' => 'paused'])->assertSessionHasNoErrors()->assertRedirect();
        $notification = DatabaseNotification::sole();
        $this->assertEquals($this->shop->id, $notification->notifiable_id);
        $this->assertSame(0, $foreign->notifications()->count());
        $this->assertDatabaseCount('seller_vouchers', 1);
    }

    public function test_bell_materialization_is_bounded_and_uses_constant_queries_as_history_grows(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->notification();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = SellerNotifications::shell();
        $queries = count(DB::getQueryLog());
        for ($i = 0; $i < 30; $i++) {
            $this->notification();
        }
        DB::flushQueryLog();
        $second = SellerNotifications::shell();
        $this->assertSame($queries, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(6, $first['unread']);
        $this->assertSame(36, $second['unread']);
        $this->assertCount(5, $first['recent']);
        $this->assertCount(5, $second['recent']);
    }
}
