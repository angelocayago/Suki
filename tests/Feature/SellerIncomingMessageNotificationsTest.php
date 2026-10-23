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
use App\Support\SellerNotifications;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerIncomingMessageNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('chat_images');
        $owner = User::factory()->make(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $owner->forceFill(['id' => 17])->save();
        $this->shop = new Seller(['user_id' => 17, 'name' => 'KWEK-KWEK', 'slug' => 'notification-local-shop', 'status' => 'pending']);
        $this->shop->forceFill(['id' => 9])->save();
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'pending', 'is_suspended' => false]);
        $this->buyer->forceFill(['email_verified_at' => null])->save();
        $this->app->instance('env', 'local');
        config(['seller-messaging.local_pending_shop_id' => 9]);
        $this->actingAs($this->buyer);
    }

    private function send(string $url, array $data = [])
    {
        return $this->withSession(['_token' => 'notification-test-token'])->post($url, $data + ['_token' => 'notification-test-token', 'body' => 'Private message contents', 'submission_key' => (string) Str::uuid()]);
    }

    private function start(array $data = []): SellerConversation
    {
        $this->send(route('buyer.messages.start', ['kind' => 'shops', 'id' => 9]), $data)->assertRedirect();

        return SellerConversation::sole();
    }

    private function notice(): DatabaseNotification
    {
        return DatabaseNotification::where('type', SellerNotifications::BUYER_MESSAGE_CREATED)->sole();
    }

    public static function kinds(): array
    {
        return [['text'], ['image'], ['mixed']];
    }

    #[DataProvider('kinds')]
    public function test_new_text_image_and_mixed_messages_create_one_minimal_shop_addressed_notice(string $kind): void
    {
        $data = ['body' => $kind === 'image' ? '' : 'Private message contents'];
        if ($kind !== 'text') {
            $data['images'] = [UploadedFile::fake()->image('secret-original-name.png')];
        }
        $before = $this->shop->fresh()->getAttributes();
        $thread = $this->start($data);
        $notice = $this->notice();
        $this->assertSame(Seller::class, $notice->notifiable_type);
        $this->assertEquals(9, $notice->notifiable_id);
        $this->assertNotEquals(17, $notice->notifiable_id);
        $this->assertSame(['title' => 'New Buyer Message', 'message' => 'You have received a new message from a Buyer.', 'destination' => ['type' => 'conversation', 'id' => $thread->id]], $notice->data);
        $this->assertNull($notice->read_at);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($before, $this->shop->fresh()->getAttributes());
        $this->actingAs($this->shop->owner)->get(route('seller.notifications.index'))->assertOk()->assertSee('New Buyer Message')->assertSee('View Conversation')->assertSee(route('seller.messages.show', $thread->id))->assertSee('1 unread across your inbox');
    }

    public function test_authenticated_http_flow_keeps_chat_and_notification_reads_independent(): void
    {
        $thread = $this->start();
        $notice = $this->notice();
        $this->actingAs($this->shop->owner);
        $this->get(route('seller.notifications.index', ['type' => SellerNotifications::BUYER_MESSAGE_CREATED]))->assertOk()->assertViewHas('types', fn ($types) => $types[SellerNotifications::BUYER_MESSAGE_CREATED] === 'Messages')->assertViewHas('filterChips', fn ($chips) => count($chips) === 1);
        $this->send(route('seller.notifications.read', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $this->assertSame(1, SellerMessaging::unread($this->shop));
        $this->send(route('seller.notifications.unread', $notice->id))->assertRedirect();
        $this->get(route('seller.messages.show', $thread->id))->assertOk()->assertSee('Private message contents');
        $this->assertSame(0, SellerMessaging::unread($this->shop));
        $this->assertNull($notice->fresh()->read_at);
        $this->send(route('seller.messages.reply', $thread->id), ['body' => 'Seller reply'])->assertRedirect();
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($this->buyer)->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Seller reply');
        $this->send(route('buyer.messages.send', $thread->id), ['body' => 'Further Buyer message'])->assertRedirect();
        $this->assertDatabaseCount('notifications', 2);
        $this->actingAs($this->shop->owner);
        $this->send(route('seller.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $this->shop->notifications()->whereNull('read_at')->count());
        $this->assertSame(1, SellerMessaging::unread($this->shop));
        $this->get(route('seller.notifications.index'))->assertOk()->assertSee('0 unread across your inbox')->assertSee('Messages, 1 unread incoming messages');
    }

    public function test_same_key_retries_and_repeated_callbacks_preserve_one_notice_and_read_state(): void
    {
        $thread = SellerMessaging::startForBuyer($this->shop);
        $key = (string) Str::uuid();
        $message = SellerMessaging::sendForBuyer((string) $thread->id, 'Once', $key);
        $notice = $this->notice();
        $notice->update(['read_at' => now()->subMinute()]);
        $before = $notice->fresh()->getAttributes();
        SellerNotifications::buyerMessageCreated($message);
        SellerNotifications::buyerMessageCreated($message);
        $this->send(route('buyer.messages.send', $thread->id), ['body' => 'Once', 'submission_key' => $key])->assertRedirect();
        $this->send(route('buyer.messages.send', $thread->id), ['body' => 'Changed', 'submission_key' => $key])->assertConflict();
        $this->assertSame($before, $this->notice()->getAttributes());
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('seller_messages', 1);
    }

    public function test_image_retry_creates_no_duplicate_notice_or_files_and_conflicting_image_is_rejected(): void
    {
        $thread = $this->start();
        $key = (string) Str::uuid();
        $bytes = UploadedFile::fake()->image('one.png')->getContent();
        $data = fn () => ['body' => '', 'submission_key' => $key, 'images' => [UploadedFile::fake()->createWithContent('one.png', $bytes)]];
        $this->send(route('buyer.messages.send', $thread->id), $data())->assertRedirect();
        $this->send(route('buyer.messages.send', $thread->id), $data())->assertRedirect();
        $this->send(route('buyer.messages.send', $thread->id), ['body' => '', 'submission_key' => $key, 'images' => [UploadedFile::fake()->image('different.png', 50, 50)]])->assertConflict();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('seller_message_attachments', 1);
        $this->assertCount(1, Storage::disk('chat_images')->allFiles());
    }

    public function test_validation_and_image_decode_failure_create_no_message_notice_or_empty_conversation(): void
    {
        $url = route('buyer.messages.start', ['kind' => 'shops', 'id' => 9]);
        $this->send($url, ['body' => ' '])->assertSessionHasErrors('body');
        $this->send($url, ['images' => [UploadedFile::fake()->createWithContent('fake.png', '<svg>active</svg>')]])->assertSessionHasErrors('images');
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => 9]))->assertOk();
        $this->get(route('buyer.messages.index'))->assertOk();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_conversations', 0);
    }

    public function test_enclosing_rollback_removes_notice_message_activity_and_private_files(): void
    {
        $stale = null;
        try {
            DB::transaction(function () use (&$stale) {
                $thread = SellerMessaging::startForBuyer($this->shop);
                $stale = SellerMessaging::sendForBuyer((string) $thread->id, '', (string) Str::uuid(), [UploadedFile::fake()->image('rollback.png')]);
                $this->assertDatabaseCount('notifications', 1);
                throw new \RuntimeException('Rollback source');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Rollback source', $e->getMessage());
        }
        SellerNotifications::buyerMessageCreated($stale);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_message_attachments', 0);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_notification_insert_failure_rolls_back_first_message_and_already_written_images(): void
    {
        DatabaseNotification::creating(function ($notification) {
            if ($notification->type === SellerNotifications::BUYER_MESSAGE_CREATED) {
                throw new \RuntimeException('Simulated notification insert failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->send(route('buyer.messages.start', ['kind' => 'shops', 'id' => 9]), ['images' => [UploadedFile::fake()->image('first.png')]]);
            $this->fail('Send unexpectedly succeeded');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated notification insert failure', $e->getMessage());
        }
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_message_attachments', 0);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_missing_notification_storage_fails_closed_and_cleans_private_images(): void
    {
        Schema::drop('notifications');
        $this->send(route('buyer.messages.start', ['kind' => 'shops', 'id' => 9]), ['images' => [UploadedFile::fake()->image('first.png')]])->assertStatus(503);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_partial_upload_failure_emits_no_notification_and_cleans_the_partial_file(): void
    {
        $disk = Storage::disk('chat_images');
        $mock = \Mockery::mock(FilesystemAdapter::class);
        $mock->shouldReceive('put')->once()->andReturnUsing(function ($path, $bytes) use ($disk) {
            $disk->put($path, $bytes);
            throw new \RuntimeException('Partial write failure');
        });
        $mock->shouldReceive('delete')->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::shouldReceive('disk')->with('chat_images')->andReturn($mock);
        $this->send(route('buyer.messages.start', ['kind' => 'shops', 'id' => 9]), ['images' => [UploadedFile::fake()->image('partial.png')]])->assertSessionHasErrors('images');
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_approved_shop_receives_notifications_in_production_without_pending_exceptions(): void
    {
        $this->shop->update(['status' => 'approved']);
        $this->buyer->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($this->buyer->fresh());
        $this->app->instance('env', 'production');
        $thread = $this->start();
        $this->assertEquals(9, $this->notice()->notifiable_id);
        $this->actingAs($this->shop->owner)->get(route('seller.notifications.index'))->assertOk()->assertSee(route('seller.messages.show', $thread->id));
    }

    public function test_buyer_notification_writes_preserve_nonempty_commerce_vouchers_and_existing_notices(): void
    {
        $this->shop->update(['business_category' => 'Pet Supplies']);
        SellerCategories::populate();
        $product = Product::create(['seller_id' => 9, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Saved stock', 'slug' => 'notification-stock', 'is_active' => true]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'MSG03-STOCK', 'price_minor' => 500, 'stock' => 8, 'is_active' => true]);
        $voucher = SellerVoucher::create(['seller_id' => 9, 'name' => 'Saved', 'code' => 'MSG03-KEEP', 'discount_type' => 'fixed', 'discount_minor' => 100, 'minimum_minor' => 0, 'product_scope' => 'selected', 'starts_at' => now(), 'ends_at' => now()->addWeek(), 'per_buyer_limit' => 1, 'status' => 'paused']);
        $voucher->products()->attach($product->id);
        $order = Order::create(['seller_id' => 17, 'buyer_id' => $this->buyer->id, 'order_number' => 'MSG03-ORDER', 'status' => 'PLACED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'recipient_name' => 'Saved recipient', 'recipient_phone' => '123', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test', 'total_amount' => 5, 'shipping_fee' => 0]);
        $segment = $order->sellerOrders()->create(['seller_id' => 9, 'status' => 'completed', 'subtotal_minor' => 500]);
        $order->items()->create(['seller_order_id' => $segment->id, 'seller_id' => 17, 'product_slug' => $product->slug, 'product_name' => 'Saved item', 'unit_price' => 5, 'quantity' => 1, 'line_total' => 5]);
        $order->payments()->create(['method' => 'cod', 'amount_minor' => 500, 'status' => 'pending']);
        $tables = ['users', 'sellers', 'products', 'product_variants', 'orders', 'order_items', 'seller_orders', 'payments', 'seller_vouchers', 'seller_voucher_products'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $voucherNotice = DatabaseNotification::where('type', SellerNotifications::VOUCHER_CREATED)->sole()->getAttributes();
        $thread = $this->start(['images' => [UploadedFile::fake()->image('new.png')]]);
        $this->send(route('buyer.messages.send', $thread->id), ['body' => 'Next question'])->assertRedirect();
        $this->assertSame($before, $snapshot());
        $this->assertSame($voucherNotice, DatabaseNotification::where('type', SellerNotifications::VOUCHER_CREATED)->sole()->getAttributes());
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_unsaved_hydrated_historical_and_seller_messages_cannot_manufacture_notices(): void
    {
        $thread = SellerMessaging::startForBuyer($this->shop);
        SellerNotifications::buyerMessageCreated(new SellerMessage(['sender_role' => 'buyer', 'conversation_id' => $thread->id]));
        $saved = SellerMessage::create(['conversation_id' => $thread->id, 'sender_role' => 'buyer', 'sender_user_id' => $this->buyer->id, 'body' => 'Historical fixture', 'submission_key' => (string) Str::uuid()]);
        SellerNotifications::buyerMessageCreated($saved->fresh());
        $this->actingAs($this->shop->owner);
        $reply = SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'Seller', (string) Str::uuid());
        SellerNotifications::buyerMessageCreated($reply);
        $this->get(route('seller.messages.show', $thread->id))->assertOk();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_foreign_recipient_and_foreign_missing_or_untrusted_conversation_destinations_are_safe(): void
    {
        $thread = $this->start();
        $notice = $this->notice();
        $foreign = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Foreign shop', 'slug' => 'foreign-message-notice', 'status' => 'approved']);
        $this->actingAs($foreign->owner)->get(route('seller.notifications.index'))->assertOk()->assertDontSee('New Buyer Message');
        $this->send(route('seller.notifications.read', $notice->id))->assertNotFound();
        $this->send(route('seller.notifications.unread', $notice->id))->assertNotFound();
        $foreignThread = SellerConversation::create(['buyer_id' => $this->buyer->id, 'seller_id' => $foreign->id]);
        $this->actingAs($this->shop->owner);
        foreach ([['type' => 'conversation', 'id' => $foreignThread->id], ['type' => 'conversation', 'id' => 999999], ['type' => 'conversation', 'id' => (string) $thread->id], ['type' => 'https://evil.invalid', 'id' => $thread->id], 'malformed'] as $destination) {
            $notice->update(['data' => ['title' => 'Safe notice', 'message' => 'Saved notice', 'destination' => $destination, 'url' => 'https://evil.invalid']]);
            $this->get(route('seller.notifications.index'))->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->first()->url === null)->assertDontSee('https://evil.invalid');
        }
    }

    public function test_voucher_and_message_destinations_coexist_without_changing_voucher_notice(): void
    {
        $thread = $this->start();
        $voucher = SellerVoucher::create(['seller_id' => 9, 'name' => 'Preserved voucher', 'code' => 'MSG03-VOUCHER', 'discount_type' => 'percentage', 'discount_bps' => 1000, 'minimum_minor' => 0, 'product_scope' => 'all', 'starts_at' => now(), 'ends_at' => now()->addWeek(), 'per_buyer_limit' => 1, 'status' => 'paused']);
        $saved = DatabaseNotification::where('type', SellerNotifications::VOUCHER_CREATED)->sole()->getAttributes();
        $this->actingAs($this->shop->owner)->get(route('seller.notifications.index'))->assertOk()->assertSee(route('seller.messages.show', $thread->id))->assertSee(route('seller.vouchers.show', $voucher->id))->assertSee('View voucher')->assertSee('View Conversation')->assertSee('2 unread across your inbox');
        $this->assertSame($saved, DatabaseNotification::where('type', SellerNotifications::VOUCHER_CREATED)->sole()->getAttributes());
        $this->assertDatabaseCount('notifications', 2);
    }
}
