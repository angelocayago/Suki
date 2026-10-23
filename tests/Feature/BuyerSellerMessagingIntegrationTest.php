<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\SellerMessageAttachment;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerMessaging;
use App\Support\SellerNotifications;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyerSellerMessagingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private User $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('chat_images');
        SellerCategories::populate();
        $this->shop = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Verified Shop', 'slug' => 'verified-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
        $this->product = Product::create(['seller_id' => $this->shop->id, 'category_id' => SellerCategories::main($this->shop)->id, 'name' => 'Genuine Product', 'slug' => 'genuine-product', 'is_active' => true]);
        $this->product->variants()->create(['name' => 'Default', 'sku' => 'MSG02-SKU', 'price_minor' => 500, 'stock' => 10, 'is_active' => true]);
        $this->actingAs($this->buyer);
    }

    private function startUrl(): string
    {
        return route('buyer.messages.start', ['kind' => 'products', 'id' => $this->product->id]);
    }

    private function data(array $changes = []): array
    {
        return $changes + ['body' => 'Buyer question', 'submission_key' => (string) Str::uuid()];
    }

    private function start(array $changes = []): SellerConversation
    {
        $this->post($this->startUrl(), $this->data($changes))->assertSessionHasNoErrors()->assertRedirect();

        return SellerConversation::sole();
    }

    public function test_complete_authenticated_http_buyer_seller_buyer_flow_and_read_receipts(): void
    {
        $this->get(route('buyer.messages.contact', ['kind' => 'products', 'id' => $this->product->id]))->assertOk()->assertSee('Genuine Product')->assertSee('Verified Shop');
        $this->assertDatabaseCount('seller_conversations', 0);
        $thread = $this->start();
        $incoming = SellerMessage::sole();
        $this->assertNull($incoming->read_at);
        $this->actingAs($this->shop->owner)->get(route('seller.messages.index'))->assertOk()->assertSee('Buyer question')->assertSee('Messages, 1 unread incoming messages');
        $this->get(route('seller.messages.show', $thread->id))->assertOk();
        $this->assertNotNull($incoming->fresh()->read_at);
        $this->post(route('seller.messages.reply', $thread->id), $this->data(['body' => 'Seller reply']))->assertRedirect();
        $reply = SellerMessage::where('sender_role', 'seller')->sole();
        $this->assertNull($reply->read_at);
        $this->actingAs($this->buyer)->get(route('buyer.messages.index'))->assertOk()->assertSee('Verified Shop')->assertSee('Seller reply')->assertSee('1 unread incoming messages');
        $this->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Seller reply');
        $this->assertNotNull($reply->fresh()->read_at);
        $this->post(route('buyer.messages.send', $thread->id), $this->data(['body' => 'Further Buyer message']))->assertRedirect();
        $this->assertDatabaseCount('seller_messages', 3);
        $this->assertSame(1, SellerMessaging::unread($this->shop));
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(2, $this->shop->notifications()->where('type', SellerNotifications::BUYER_MESSAGE_CREATED)->count());
    }

    public static function formats(): array
    {
        return [['jpg'], ['png'], ['webp']];
    }

    #[DataProvider('formats')]
    public function test_image_only_uploads_are_decoded_reencoded_private_and_authorized(string $extension): void
    {
        $thread = $this->start(['body' => '', 'images' => [UploadedFile::fake()->image('untrusted.'.$extension, 32, 24)]]);
        $attachment = SellerMessageAttachment::sole();
        Storage::disk('chat_images')->assertExists($attachment->path);
        $this->assertStringNotContainsString('untrusted', $attachment->path);
        $this->assertSame('', SellerMessage::sole()->body);
        $url = route('messages.images.show', $attachment->id);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', $attachment->mime);
        $this->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee($url)->assertSee('View full image');
        $this->actingAs($this->shop->owner)->get($url)->assertOk();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->get($url)->assertStatus(403);
        $foreign = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Other', 'slug' => 'other', 'status' => 'approved']);
        $this->actingAs($foreign->owner)->get($url)->assertNotFound();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_both_sides_send_mixed_and_multiple_images_and_submission_retries_do_not_duplicate_files(): void
    {
        $key = (string) Str::uuid();
        $image = UploadedFile::fake()->image('picture.png');
        $bytes = file_get_contents($image->getRealPath());
        $data = ['body' => 'Mixed first', 'submission_key' => $key, 'images' => [UploadedFile::fake()->createWithContent('first.png', $bytes), UploadedFile::fake()->createWithContent('second.png', $bytes)]];
        $this->post($this->startUrl(), $data)->assertRedirect();
        $this->post($this->startUrl(), array_replace($data, ['images' => [UploadedFile::fake()->createWithContent('renamed.png', $bytes), UploadedFile::fake()->createWithContent('renamed2.png', $bytes)]]))->assertRedirect();
        $this->assertDatabaseCount('seller_conversations', 1);
        $this->assertDatabaseCount('seller_messages', 1);
        $this->assertDatabaseCount('seller_message_attachments', 2);
        $this->assertCount(2, Storage::disk('chat_images')->allFiles());
        $thread = SellerConversation::sole();
        $this->actingAs($this->shop->owner)->post(route('seller.messages.reply', $thread->id), $this->data(['body' => 'Seller image', 'images' => [UploadedFile::fake()->image('seller.webp')]]))->assertRedirect();
        $this->assertDatabaseCount('seller_message_attachments', 3);
        $this->actingAs($this->buyer)->post(route('buyer.messages.send', $thread->id), ['body' => 'Mixed first', 'submission_key' => $key, 'images' => [UploadedFile::fake()->image('different.png', 3, 3)]])->assertConflict();
        $this->assertCount(3, Storage::disk('chat_images')->allFiles());
    }

    public function test_polyglot_trailing_content_is_removed_and_original_name_and_client_mime_are_not_trusted(): void
    {
        $file = UploadedFile::fake()->image('base.png');
        $bytes = file_get_contents($file->getRealPath()).'<script>ACTIVE</script>';
        $this->start(['images' => [UploadedFile::fake()->createWithContent('../../evil.php', $bytes)]]);
        $image = SellerMessageAttachment::sole();
        $this->assertSame('image/png', $image->mime);
        $this->assertStringNotContainsString('ACTIVE', Storage::disk('chat_images')->get($image->path));
    }

    public static function invalidUploads(): array
    {
        return [['svg'], ['gif'], ['pdf'], ['malformed'], ['oversized'], ['too-many'], ['dimension'], ['string']];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_uploads_leave_no_empty_conversation_records_or_files(string $kind): void
    {
        $files = match ($kind) {
            'svg' => [UploadedFile::fake()->createWithContent('x.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            'gif' => [UploadedFile::fake()->image('x.gif')],
            'pdf' => [UploadedFile::fake()->createWithContent('x.jpg', '%PDF-1.7 fake')],
            'malformed' => [UploadedFile::fake()->createWithContent('x.png', "\x89PNG\r\n\x1a\ninvalid")],
            'oversized' => [UploadedFile::fake()->create('x.jpg', 5121, 'image/jpeg')],
            'too-many' => array_map(fn () => UploadedFile::fake()->image('x.png'), range(1, 6)),
            'dimension' => [UploadedFile::fake()->image('x.png', 4097, 2)],
            'string' => ['invalid'],
        };
        $this->post($this->startUrl(), $this->data(['images' => $files]))->assertSessionHasErrors();
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_message_attachments', 0);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_outer_rollback_cleans_images_but_unrelated_savepoint_rollback_does_not(): void
    {
        DB::beginTransaction();
        $this->start(['images' => [UploadedFile::fake()->image('x.png')]]);
        $paths = Storage::disk('chat_images')->allFiles();
        $this->assertCount(1, $paths);
        DB::beginTransaction();
        DB::rollBack();
        Storage::disk('chat_images')->assertExists($paths[0]);
        DB::rollBack();
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('seller_message_attachments', 0);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_storage_failure_cleans_partial_files_and_database_rows(): void
    {
        $disk = Storage::disk('chat_images');
        $mock = \Mockery::mock(FilesystemAdapter::class);
        $mock->shouldReceive('put')->once()->andReturnUsing(function ($path, $content) use ($disk) {
            $disk->put($path, $content);

            return true;
        });
        $mock->shouldReceive('put')->once()->andReturnUsing(function ($path, $content) use ($disk) {
            $disk->put($path, $content);
            throw new \RuntimeException('Simulated disk failure');
        });
        $mock->shouldReceive('delete')->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::shouldReceive('disk')->with('chat_images')->andReturn($mock);
        $this->post($this->startUrl(), $this->data(['images' => [UploadedFile::fake()->image('one.png'), UploadedFile::fake()->image('two.png')]]))->assertSessionHasErrors('images');
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_message_attachments', 0);
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_ownership_eligibility_empty_first_send_spoofing_and_legacy_entries(): void
    {
        $this->post($this->startUrl(), $this->data(['body' => ' ']))->assertSessionHasErrors('body');
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->post($this->startUrl(), $this->data(['buyer_id' => 999]))->assertSessionHasErrors('buyer_id');
        $thread = $this->start();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->get(route('buyer.messages.show', $thread->id))->assertNotFound();
        $this->post(route('buyer.messages.send', $thread->id), $this->data())->assertNotFound();
        $this->actingAs($this->buyer);
        $this->buyer->forceFill(['email_verified_at' => null])->save();
        $this->get(route('buyer.messages.index'))->assertForbidden();
        $this->buyer->forceFill(['email_verified_at' => now()])->save();
        $this->shop->update(['status' => 'pending']);
        $this->get(route('buyer.messages.contact', ['kind' => 'products', 'id' => $this->product->id]))->assertNotFound();
        $this->assertDatabaseCount('seller_conversations', 1);
    }

    public function test_buyer_history_is_bounded_and_reads_only_the_visible_page(): void
    {
        $thread = $this->start();
        $this->actingAs($this->shop->owner);
        for ($i = 0; $i < 52; $i++) {
            SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'Reply '.$i, (string) Str::uuid());
        }
        $this->actingAs($this->buyer)->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Reply 51')->assertDontSee('Reply 0');
        $this->assertSame(2, SellerMessaging::buyerUnread());
        $this->get(route('buyer.messages.show', ['conversation' => $thread->id, 'page' => 2]))->assertOk()->assertSee('Reply 0')
            ->assertViewHas('messages', fn ($rows) => ! $rows->contains('body', 'Reply 51'));
        $this->assertSame(0, SellerMessaging::buyerUnread());
        $this->get(route('buyer.messages.index', ['search' => 'Missing shop']))->assertOk()->assertDontSee('Buyer question');
    }

    public function test_buyer_transport_rejects_guests_wrong_roles_inactive_products_and_self_contact(): void
    {
        Auth::logout();
        $this->get(route('buyer.messages.index'))->assertRedirect(route('login'));
        $this->actingAs($this->shop->owner)->get(route('buyer.messages.index'))->assertForbidden();
        $this->actingAs($this->buyer);
        $this->product->update(['is_active' => false]);
        $this->get(route('buyer.messages.contact', ['kind' => 'products', 'id' => $this->product->id]))->assertNotFound();
        $this->shop->update(['user_id' => $this->buyer->id]);
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->shop->id]))->assertNotFound();
        $this->assertDatabaseCount('seller_conversations', 0);
    }

    public function test_buyer_transport_enforces_csrf_throttling_and_escapes_saved_text(): void
    {
        $this->app['env'] = 'production';
        $this->post($this->startUrl(), $this->data())->assertStatus(419);
        $this->app['env'] = 'testing';
        $thread = $this->start(['body' => '<script>alert(99)</script>']);
        $this->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('&lt;script&gt;alert(99)&lt;/script&gt;', false)->assertDontSee('<script>alert(99)</script>', false);
        for ($i = 0; $i < 29; $i++) {
            $this->post(route('buyer.messages.send', $thread->id), $this->data())->assertRedirect();
        }
        $this->post(route('buyer.messages.send', $thread->id), $this->data())->assertStatus(429);
    }

    public function test_attachment_migration_is_optional_for_existing_text_chat_and_required_for_images(): void
    {
        Schema::drop('seller_message_attachments');
        $thread = $this->start();
        $this->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Buyer question');
        $this->post(route('buyer.messages.send', $thread->id), $this->data(['images' => [UploadedFile::fake()->image('one.png')]]))->assertStatus(503);
        $this->get(route('messages.images.show', 1))->assertStatus(503);
        $this->assertDatabaseCount('seller_messages', 1);
        $this->assertCount(0, Storage::disk('chat_images')->allFiles());
    }

    public function test_buyer_inbox_paginates_owned_threads_and_searches_saved_shop_names(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $shop = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Inbox shop '.sprintf('%02d', $i), 'slug' => 'inbox-shop-'.$i, 'status' => 'approved']);
            SellerMessaging::startForBuyer($shop);
        }
        $foreignBuyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($foreignBuyer);
        SellerMessaging::startForBuyer($this->shop);
        $this->actingAs($this->buyer)->get(route('buyer.messages.index'))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 21 && $p->count() === 20);
        $this->get(route('buyer.messages.index', ['page' => 2]))->assertOk()->assertViewHas('paginator', fn ($p) => $p->count() === 1);
        $this->get(route('buyer.messages.index', ['search' => 'Inbox shop 03']))->assertOk()->assertViewHas('paginator', fn ($p) => $p->total() === 1);
    }

    public function test_buyer_read_selection_does_not_read_later_messages_or_other_threads(): void
    {
        $thread = $this->start();
        $this->actingAs($this->shop->owner);
        $first = SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'First', (string) Str::uuid());
        $later = SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'Later', (string) Str::uuid());
        $this->actingAs($this->buyer);
        SellerMessaging::markBuyerDisplayedRead((string) $thread->id, [$first->id]);
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($later->fresh()->read_at);
        $this->assertSame(1, SellerMessaging::buyerUnread());
    }
}
