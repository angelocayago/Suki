<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\SellerVoucher;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerMessaging;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SellerMessagingTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        User::factory()->create();
        $this->shop = $this->shop();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Buyer Display', 'email_verified_at' => now()]);
        $this->actingAs($this->shop->owner);
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(4, 0));
    }

    private function shop(): Seller
    {
        return Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id,
            'name' => 'Message shop', 'slug' => uniqid('message-'), 'status' => 'approved', 'business_category' => 'Pet Supplies']);
    }

    private function thread(?User $buyer = null, ?Seller $shop = null, ?string $body = 'Incoming question'): SellerConversation
    {
        $this->actingAs($buyer ?? $this->buyer);
        $thread = SellerMessaging::startForBuyer($shop ?? $this->shop);
        if ($body !== null) {
            SellerMessaging::sendForBuyer((string) $thread->id, $body, (string) Str::uuid());
        }
        $this->actingAs($this->shop->owner);

        return $thread;
    }

    public function test_empty_inbox_does_not_create_conversations_or_messages(): void
    {
        $this->get(route('seller.messages.index'))->assertOk()->assertSee('No conversations found')->assertSee('Messages, 0 unread incoming messages');
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_messages', 0);
    }

    public function test_future_creation_contract_is_authenticated_shop_based_and_unique(): void
    {
        $thread = $this->thread(body: null);
        $this->actingAs($this->buyer);
        $again = SellerMessaging::startForBuyer($this->shop);
        $this->assertSame($thread->id, $again->id);
        $this->assertSame($this->buyer->id, $thread->buyer_id);
        $this->assertSame($this->shop->id, $thread->seller_id);
        $this->assertNotEquals($this->shop->user_id, $thread->seller_id);
        $this->assertDatabaseCount('seller_conversations', 1);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('seller_conversations')->insert(['buyer_id' => $this->buyer->id, 'seller_id' => $this->shop->id]);
    }

    public function test_incoming_preview_and_seller_reply_persist_with_independent_counts(): void
    {
        $thread = $this->thread();
        $this->get(route('seller.messages.index'))->assertOk()->assertSee('Buyer Display')->assertSee('Incoming question')->assertSee('Messages, 1 unread incoming messages')->assertSee('1 unread across your inbox');
        $key = (string) Str::uuid();
        $this->post(route('seller.messages.reply', $thread->id), ['body' => '  A saved reply  ', 'submission_key' => $key])->assertRedirect(route('seller.messages.show', $thread->id));
        $reply = SellerMessage::where('sender_role', 'seller')->sole();
        $this->assertSame('A saved reply', $reply->body);
        $this->assertSame($this->shop->user_id, $reply->sender_user_id);
        $this->assertSame(1, SellerMessaging::unread($this->shop));
        $this->assertNotNull($thread->fresh()->last_message_at);
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertSee('A saved reply')->assertSee('Your shop')->assertSee('Buyer Display');
        $this->assertSame(0, SellerMessaging::unread($this->shop));
        $this->assertNull($reply->fresh()->read_at);
        // Only the incoming Buyer message generates a notice; the reply does not.
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_duplicate_submission_does_not_duplicate_content_and_token_reuse_with_other_content_conflicts(): void
    {
        $thread = $this->thread();
        $data = ['body' => 'Once', 'submission_key' => (string) Str::uuid()];
        $this->post(route('seller.messages.reply', $thread->id), $data)->assertRedirect();
        $before = $thread->fresh()->updated_at->toIso8601String();
        $this->travel(1)->hours();
        $this->post(route('seller.messages.reply', $thread->id), $data)->assertRedirect();
        $this->assertSame($before, $thread->fresh()->updated_at->toIso8601String());
        $this->post(route('seller.messages.reply', $thread->id), array_replace($data, ['body' => 'Different']))->assertConflict();
        $this->assertSame(1, SellerMessage::where('sender_role', 'seller')->count());
    }

    public static function invalidMessages(): array
    {
        return [[['body' => '']], [['body' => " \n\t "]], [['body' => "\u{00a0} \u{00a0}"]], [['body' => str_repeat('a', 5001)]],
            [['body' => ['nested']]], [['submission_key' => 'bad']], [['sender_id' => 999]], [['sender_role' => 'buyer']], [['buyer_id' => 999]],
            [['seller_id' => 999]], [['receiver_id' => 999]], [['conversation_id' => 999]], [['sender_user_id' => 999]]];
    }

    #[DataProvider('invalidMessages')]
    public function test_invalid_and_spoofed_reply_inputs_are_rejected(array $invalid): void
    {
        $thread = $this->thread();
        $this->post(route('seller.messages.reply', $thread->id), array_replace(['body' => 'Valid', 'submission_key' => (string) Str::uuid()], $invalid))->assertSessionHasErrors();
        $this->assertSame(0, SellerMessage::where('sender_role', 'seller')->count());
    }

    public function test_foreign_seller_cannot_list_view_reply_or_read_another_shop_thread(): void
    {
        $thread = $this->thread();
        $this->actingAs($this->shop()->owner);
        $this->get(route('seller.messages.index'))->assertOk()->assertDontSee('Buyer Display')->assertDontSee('Incoming question');
        $this->get(route('seller.messages.show', $thread->id))->assertNotFound();
        $this->post(route('seller.messages.reply', $thread->id), ['body' => 'Intrusion', 'submission_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertNull(SellerMessage::firstOrFail()->read_at);
    }

    public function test_future_buyer_send_rejects_cross_buyer_access(): void
    {
        $thread = $this->thread();
        $this->actingAs(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $this->expectException(ModelNotFoundException::class);
        SellerMessaging::sendForBuyer((string) $thread->id, 'Intrusion', (string) Str::uuid());
    }

    public function test_only_displayed_incoming_ids_are_read_when_new_messages_arrive_concurrently(): void
    {
        $thread = $this->thread();
        $other = $this->thread(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        $seen = SellerMessage::where('conversation_id', $thread->id)->pluck('id')->all();
        $this->actingAs($this->buyer);
        $later = SellerMessaging::sendForBuyer((string) $thread->id, 'Arrived after page selection', (string) Str::uuid());
        $this->actingAs($this->shop->owner);
        SellerMessaging::markDisplayedRead($this->shop, (string) $thread->id, array_merge($seen, SellerMessage::where('conversation_id', $other->id)->pluck('id')->all()));
        $readAt = SellerMessage::findOrFail($seen[0])->read_at->toIso8601String();
        $this->travel(1)->hours();
        SellerMessaging::markDisplayedRead($this->shop, (string) $thread->id, $seen);
        $this->assertSame($readAt, SellerMessage::findOrFail($seen[0])->read_at->toIso8601String());
        $this->assertNull($later->fresh()->read_at);
        $this->assertNull(SellerMessage::where('conversation_id', $other->id)->firstOrFail()->read_at);
    }

    public function test_long_histories_are_bounded_and_render_chronologically_without_reading_unseen_pages(): void
    {
        $thread = $this->thread(body: null);
        $this->actingAs($this->buyer);
        for ($i = 0; $i < 53; $i++) {
            SellerMessaging::sendForBuyer((string) $thread->id, 'Message '.$i, (string) Str::uuid());
        }
        $this->actingAs($this->shop->owner);
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertViewHas('messages', fn ($m) => $m->count() === 50 && $m->first()->body === 'Message 3' && $m->last()->body === 'Message 52');
        $this->assertSame(3, SellerMessaging::unread($this->shop));
        $this->get(route('seller.messages.show', ['conversation' => $thread->id, 'page' => 2]))->assertOk()->assertViewHas('messages', fn ($m) => $m->count() === 3 && $m->first()->body === 'Message 0');
        $this->assertSame(0, SellerMessaging::unread($this->shop));
    }

    public function test_inbox_search_filters_ordering_chips_and_pagination(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->thread(User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Search Buyer '.$i]), body: 'Preview '.$i);
            $this->travel(1)->minutes();
        }
        $this->get(route('seller.messages.index'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 21 && $p->count() === 20 && $p->first()->last_body === 'Preview 20');
        $this->get(route('seller.messages.index', ['search' => 'Search Buyer', 'read_status' => 'unread', 'sort' => 'oldest', 'page' => 2]))->assertOk()
            ->assertViewHas('paginator', fn ($p) => $p->count() === 1 && $p->total() === 21)->assertViewHas('filterChips', function ($chips) {
                $this->assertCount(3, $chips);
                foreach ($chips as $chip) {
                    $this->assertStringNotContainsString('page=', $chip['url']);
                }

                return true;
            });
        $this->get(route('seller.messages.index', ['read_status' => 'read']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 0);
        $this->getJson(route('seller.messages.index', ['sort' => 'bad']))->assertUnprocessable();
    }

    public function test_conversation_and_message_authorship_are_immutable(): void
    {
        $thread = $this->thread();
        $this->expectException(\LogicException::class);
        $thread->update(['buyer_id' => User::factory()->create()->id]);
    }

    public function test_model_rejects_forged_author_even_for_internal_inserts(): void
    {
        $thread = $this->thread();
        $this->expectException(\LogicException::class);
        SellerMessage::create(['conversation_id' => $thread->id, 'sender_role' => 'buyer', 'sender_user_id' => $this->shop->user_id,
            'body' => 'Forged', 'submission_key' => (string) Str::uuid()]);
    }

    public function test_ineligible_buyer_cannot_start_threads(): void
    {
        $this->buyer->update(['is_suspended' => true]);
        $this->actingAs($this->buyer->fresh());
        $this->expectException(HttpException::class);
        SellerMessaging::startForBuyer($this->shop);
    }

    public function test_guests_buyers_suspended_and_ambiguous_sellers_are_rejected_and_local_pending_is_preserved(): void
    {
        auth()->logout();
        $this->withSession(['seller_logged_in' => true])->get(route('seller.messages.index'))->assertRedirect(route('login'));
        $this->actingAs($this->buyer)->get(route('seller.messages.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => true]);
        $this->actingAs($this->shop->owner->fresh())->get(route('seller.messages.index'))->assertForbidden();
        $this->shop->owner->update(['is_suspended' => false]);
        $this->actingAs($this->shop->owner->fresh());
        $this->shop->update(['status' => 'pending']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->instance('env', 'local');
        $this->get(route('seller.messages.index'))->assertOk();
        $this->app->instance('env', 'production');
        $this->get(route('seller.messages.index'))->assertForbidden();
        $this->app->instance('env', 'testing');
        $this->shop->update(['status' => 'approved']);
        $second = $this->shop();
        $second->update(['user_id' => $this->shop->user_id]);
        $this->get(route('seller.messages.index'))->assertConflict();
    }

    public function test_reply_rate_limit_and_csrf_are_enforced(): void
    {
        $thread = $this->thread();
        $this->app->instance('env', 'production');
        $this->post(route('seller.messages.reply', $thread->id), ['body' => 'No CSRF', 'submission_key' => (string) Str::uuid()])->assertStatus(419);
        $this->app->instance('env', 'testing');
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('seller.messages.reply', $thread->id), ['body' => 'Reply', 'submission_key' => (string) Str::uuid()])->assertRedirect();
        }
        $this->post(route('seller.messages.reply', $thread->id), ['body' => 'Rate limited', 'submission_key' => (string) Str::uuid()])->assertStatus(429);
        $this->assertSame(30, SellerMessage::where('sender_role', 'seller')->count());
    }

    public function test_xss_and_buyer_privacy_and_invalid_ids(): void
    {
        $this->buyer->update(['name' => '<script>NAME</script>', 'phone' => 'PHONE-SECRET', 'email' => 'PRIVATE-BUYER@example.test']);
        $thread = $this->thread(body: '<script>BODY</script>');
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertSee('&lt;script&gt;BODY&lt;/script&gt;', false)->assertDontSee('<script>BODY</script>', false)
            ->assertDontSee('PHONE-SECRET')->assertDontSee('PRIVATE-BUYER@example.test');
        $this->get('/seller/messages/01')->assertNotFound();
        $this->get('/seller/messages/999999999999999999999')->assertNotFound();
        $this->get('/seller/messages/not-number')->assertNotFound();
    }

    public function test_missing_migration_is_explicit_and_does_not_break_existing_seller_pages(): void
    {
        Schema::drop('seller_messages');
        Schema::drop('seller_conversations');
        $this->get(route('seller.vouchers.index'))->assertOk()->assertSee('Messages unavailable');
        $this->get(route('seller.messages.index'))->assertStatus(503);
    }

    public function test_sends_roll_back_with_transaction_and_do_not_generate_notifications(): void
    {
        $thread = $this->thread(body: null);
        DB::beginTransaction();
        $this->actingAs($this->buyer);
        SellerMessaging::sendForBuyer((string) $thread->id, 'Rolled back', (string) Str::uuid());
        DB::rollBack();
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertNull($thread->fresh()->last_message_at);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_message_actions_preserve_nonempty_commerce_voucher_notification_and_session_records(): void
    {
        $thread = $this->thread();
        SellerCategories::populate();
        $product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Existing stock', 'slug' => 'message-stock', 'is_active' => true]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'MESSAGE-STOCK', 'price_minor' => 500, 'stock' => 8, 'is_active' => true]);
        $voucher = SellerVoucher::create(['seller_id' => $this->shop->id, 'name' => 'Saved', 'code' => 'MESSAGE-KEEP', 'discount_type' => 'fixed', 'discount_minor' => 100,
            'minimum_minor' => 0, 'product_scope' => 'selected', 'starts_at' => now(), 'ends_at' => now()->addWeek(), 'per_buyer_limit' => 1, 'status' => 'paused']);
        $voucher->products()->attach($product->id);
        $order = Order::create(['seller_id' => $this->shop->user_id, 'buyer_id' => $this->buyer->id, 'order_number' => 'MESSAGE-ORDER', 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID',
            'recipient_name' => 'Saved recipient', 'recipient_phone' => '123', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test', 'total_amount' => 5, 'shipping_fee' => 0]);
        $group = $order->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 500]);
        $order->items()->create(['seller_order_id' => $group->id, 'seller_id' => $this->shop->user_id, 'product_slug' => $product->slug, 'product_name' => 'Saved item', 'unit_price' => 5, 'quantity' => 1, 'line_total' => 5]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 500, 'status' => 'pending']);
        $provider = DB::table('logistics_providers')->insertGetId(['user_id' => $this->shop->user_id, 'name' => 'Fixture', 'slug' => 'message-provider', 'status' => 'approved']);
        $shipment = DB::table('shipments')->insertGetId(['seller_order_id' => $group->id, 'logistics_provider_id' => $provider, 'tracking_code' => 'MESSAGE-TRACK', 'status' => 'assigned', 'fee_minor' => 0, 'cod_amount_minor' => 500, 'cod_collected' => false, 'attempts' => 0]);
        DB::table('delivery_events')->insert(['shipment_id' => $shipment, 'status' => 'assigned', 'attempt' => 0, 'user_id' => $this->shop->user_id, 'occurred_at' => now()]);
        $tables = ['products', 'product_variants', 'orders', 'order_items', 'seller_orders', 'payments', 'shipments', 'delivery_events', 'seller_vouchers', 'seller_voucher_products', 'notifications', 'sellers'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $session = ['seller_products' => ['keep' => true], 'seller_promotions' => ['inert' => true], 'cart' => ['keep' => true]];
        $this->withSession($session)->get(route('seller.messages.index'))->assertOk()->assertSee('2 unread across your inbox')->assertSessionHasAll($session);
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertSessionHasAll($session);
        $this->post(route('seller.messages.reply', $thread->id), ['body' => 'Safe reply', 'submission_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasAll($session);
        $this->assertSame($before, $snapshot());
    }

    public static function ineligibleBuyers(): array
    {
        return [[['status' => 'pending']], [['email_verified_at' => null]], [['role' => 'seller']], [['role' => 'admin']]];
    }

    #[DataProvider('ineligibleBuyers')]
    public function test_future_initiation_rejects_unverified_inactive_and_non_buyer_accounts(array $changes): void
    {
        $this->buyer->forceFill($changes)->save();
        $this->actingAs($this->buyer->fresh());
        $this->expectException(HttpException::class);
        SellerMessaging::startForBuyer($this->shop);
    }

    public function test_future_initiation_requires_an_approved_shop_and_eligible_owner(): void
    {
        $this->shop->update(['status' => 'pending']);
        $this->actingAs($this->buyer);
        $this->app->instance('env', 'local');
        $this->expectException(HttpException::class);
        // Buyer initiation never inherits the Seller-only local pending exception.
        SellerMessaging::startForBuyer($this->shop);
    }

    public function test_history_remains_visible_but_replies_stop_when_buyer_becomes_ineligible(): void
    {
        $thread = $this->thread();
        $this->buyer->update(['is_suspended' => true]);
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertSee('Incoming question')->assertSee('currently unavailable for replies');
        $this->post(route('seller.messages.reply', $thread->id), ['body' => 'Blocked', 'submission_key' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('seller_messages', 1);
    }

    public function test_saved_message_content_and_authorship_cannot_be_changed(): void
    {
        $this->thread();
        $this->expectException(\LogicException::class);
        SellerMessage::firstOrFail()->update(['body' => 'Rewritten']);
    }

    public function test_inbox_materialization_has_constant_query_count_as_conversations_grow(): void
    {
        $this->thread();
        DB::enableQueryLog();
        DB::flushQueryLog();
        SellerMessaging::page($this->shop, Request::create('/seller/messages'));
        $queries = count(DB::getQueryLog());
        for ($i = 0; $i < 21; $i++) {
            $this->thread(User::factory()->create(['role' => 'buyer', 'status' => 'active']));
        }
        DB::flushQueryLog();
        $data = SellerMessaging::page($this->shop, Request::create('/seller/messages'));
        $this->assertSame($queries, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertSame(22, $data['paginator']->total());
        $this->assertSame(20, $data['paginator']->count());
    }
}
