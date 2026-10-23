<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessageAttachment;
use App\Models\User;
use App\Support\ChatWorkspace;
use App\Support\SellerMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessagingWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('chat_images');
        User::factory()->create();
        $this->shop = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Workspace shop', 'slug' => 'workspace-shop', 'status' => 'approved']);
        $this->buyer = User::factory()->create(['name' => 'Workspace Buyer', 'role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
    }

    private function thread(): SellerConversation
    {
        $this->actingAs($this->buyer);
        $thread = SellerMessaging::startForBuyer($this->shop);
        SellerMessaging::sendForBuyer((string) $thread->id, 'Buyer <b>escaped</b>', (string) Str::uuid());

        return $thread;
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($document);
    }

    public static function roles(): array
    {
        return [['seller'], ['buyer']];
    }

    #[DataProvider('roles')]
    public function test_empty_workspace_does_not_select_create_or_fabricate_a_conversation(string $side): void
    {
        $this->actingAs($side === 'seller' ? $this->shop->owner : $this->buyer);
        $response = $this->get(route($side.'.messages.index'))->assertOk()->assertSee('No conversations found')->assertSee('Select a conversation')->assertSee('data-chat-workspace', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//aside[@aria-label="Conversations"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-chat-composer]')->length);
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertDatabaseCount('seller_messages', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    #[DataProvider('roles')]
    public function test_direct_conversation_has_two_panels_correct_selection_alignment_identity_and_private_images(string $side): void
    {
        $thread = $this->thread();
        SellerMessaging::sendForBuyer((string) $thread->id, '', (string) Str::uuid(), [UploadedFile::fake()->image('one.png')]);
        $this->actingAs($this->shop->owner);
        SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'Seller reply', (string) Str::uuid(), [UploadedFile::fake()->image('reply.webp')]);
        $this->actingAs($side === 'seller' ? $this->shop->owner : $this->buyer);
        $response = $this->get(route($side.'.messages.show', $thread->id))->assertOk()->assertSee('Buyer &lt;b&gt;escaped&lt;/b&gt;', false)->assertDontSee('<b>escaped</b>', false)->assertSee('Back to Conversations')->assertSee('View full image')->assertSee('5000');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//a[@aria-current="page" and @data-conversation-id="'.$thread->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-chat-history]')->length);
        $this->assertSame(2, $xpath->query('//*[@data-chat-history]//img')->length);
        $outgoing = $xpath->query('//li[contains(@class,"is-outgoing")]');
        $incoming = $xpath->query('//li[contains(@class,"is-incoming")]');
        $this->assertSame($side === 'seller' ? 1 : 2, $outgoing->length);
        $this->assertSame($side === 'seller' ? 2 : 1, $incoming->length);
        foreach ($xpath->query('//*[@data-chat-history]//img') as $image) {
            $this->assertStringContainsString('/chat-images/', $image->getAttribute('src'));
            $this->assertStringNotContainsString('/storage/', $image->getAttribute('src'));
        }
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertSame(1, $xpath->query('//form[@data-chat-composer and @enctype="multipart/form-data"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-order-context or @data-presence]')->length);
        $export = getenv('SUKI_CHAT_VISUAL_EXPORT');
        if ($export && is_dir($export)) {
            // Optional offline visual artifacts from isolated HTTP fixtures, never live records.
            $html = str_replace('http://localhost/build/', 'file:///'.str_replace('\\', '/', public_path('build')).'/', $response->getContent());
            $html = str_replace('http://localhost/images/', 'file:///'.str_replace('\\', '/', public_path('images')).'/', $html);
            foreach (SellerMessageAttachment::all() as $attachment) {
                $html = str_replace(route('messages.images.show', $attachment->id), 'data:'.$attachment->mime.';base64,'.base64_encode(Storage::disk('chat_images')->get($attachment->path)), $html);
            }
            file_put_contents($export.'/'.$side.'.html', $html);
        }
    }

    public function test_selected_thread_outside_filtered_inbox_is_pinned_and_filter_links_keep_selection(): void
    {
        $thread = $this->thread();
        $this->actingAs($this->shop->owner);
        $response = $this->get(route('seller.messages.show', ['conversation' => $thread->id, 'list' => ['search' => 'No such Buyer', 'sort' => 'oldest']]))->assertOk()->assertSee('Selected conversation outside this list page or filter')->assertViewHas('inboxPaginator', fn ($p) => $p->total() === 0);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//a[@aria-current="page" and @data-conversation-id="'.$thread->id.'"]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="list[search]"]')->length);
        foreach ($response->viewData('workspaceFilterChips') as $chip) {
            $this->assertStringStartsWith(route('seller.messages.show', $thread->id), $chip['url']);
            $this->assertStringContainsString('list%5B', $chip['url']);
            $this->assertStringNotContainsString('list%5Bpage%5D', $chip['url']);
        }
    }

    public function test_inbox_and_history_pages_are_independent_and_keep_validated_navigation_state(): void
    {
        $thread = $this->thread();
        for ($i = 0; $i < 20; $i++) {
            SellerConversation::create(['buyer_id' => User::factory()->create(['role' => 'buyer', 'status' => 'active'])->id, 'seller_id' => $this->shop->id]);
        }
        $this->actingAs($this->shop->owner);
        for ($i = 0; $i < 52; $i++) {
            SellerMessaging::sendForSeller($this->shop, (string) $thread->id, 'History '.$i, (string) Str::uuid());
        }
        $response = $this->get(route('seller.messages.show', ['conversation' => $thread->id, 'page' => 2, 'list' => ['page' => 2, 'sort' => 'oldest']]))->assertOk()
            ->assertViewHas('inboxPaginator', fn ($p) => $p->currentPage() === 2 && $p->count() === 1 && $p->total() === 21)
            ->assertViewHas('historyPaginator', fn ($p) => $p->currentPage() === 2 && $p->count() === 3)
            ->assertViewHas('messages', fn ($rows) => ! $rows->contains('body', 'History 51'));
        $xpath = $this->xpath($response->getContent());
        $history = $xpath->query('//*[@data-chat-history]')->item(0);
        $this->assertStringContainsString('History 0', $history->textContent);
        $this->assertStringNotContainsString('History 51', $history->textContent);
        parse_str(parse_url($response->viewData('historyPaginator')->url(1), PHP_URL_QUERY), $historyQuery);
        $this->assertEquals(2, $historyQuery['list']['page']);
        $this->assertSame('oldest', $historyQuery['list']['sort']);
        parse_str(parse_url($response->viewData('inboxPaginator')->url(1), PHP_URL_QUERY), $inboxQuery);
        $this->assertEquals(2, $inboxQuery['page']);
        $this->assertEquals(1, $inboxQuery['list']['page']);
    }

    #[DataProvider('roles')]
    public function test_send_redirect_retains_allowlisted_sidebar_state_and_bad_navigation_cannot_send(string $side): void
    {
        $thread = $this->thread();
        $this->actingAs($side === 'seller' ? $this->shop->owner : $this->buyer);
        $list = ['search' => 'Workspace', 'page' => 2];
        if ($side === 'seller') {
            $list['sort'] = 'oldest';
        }
        $route = $side === 'seller' ? 'seller.messages.reply' : 'buyer.messages.send';
        $this->post(route($route, ['conversation' => $thread->id, 'list' => $list]), ['body' => 'Retained filters', 'submission_key' => (string) Str::uuid()])->assertRedirect(route($side.'.messages.show', ['conversation' => $thread->id, 'list' => $list]));
        $before = DB::table('seller_messages')->count();
        $this->postJson(route($route, ['conversation' => $thread->id, 'list' => ['search' => ['bad']]]), ['body' => 'Rejected', 'submission_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('seller_messages', $before);
    }

    public function test_unrelated_orders_never_become_context_and_rendering_does_not_mutate_them(): void
    {
        $thread = $this->thread();
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->shop->user_id, 'order_number' => 'UNLINKED-ORDER-SECRET', 'status' => 'COMPLETED', 'payment_method' => 'cod', 'payment_status' => 'UNPAID', 'recipient_name' => 'Buyer', 'recipient_phone' => '123', 'province' => 'Test', 'municipality' => 'Test', 'barangay' => 'Test', 'street_address' => 'Test', 'total_amount' => 5, 'shipping_fee' => 0]);
        $segment = $order->sellerOrders()->create(['seller_id' => $this->shop->id, 'status' => 'completed', 'subtotal_minor' => 500]);
        $order->items()->create(['seller_order_id' => $segment->id, 'seller_id' => $this->shop->user_id, 'product_slug' => 'unlinked', 'product_name' => 'Unlinked item secret', 'quantity' => 1, 'unit_price' => 5, 'line_total' => 5]);
        $snapshot = fn () => collect(['orders', 'order_items', 'seller_orders'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->toJson()])->all();
        $before = $snapshot();
        foreach (['buyer', 'seller'] as $side) {
            $this->actingAs($side === 'seller' ? $this->shop->owner : $this->buyer)->get(route($side.'.messages.show', ['conversation' => $thread->id, 'order_id' => $order->id]))->assertOk()->assertDontSee('UNLINKED-ORDER-SECRET')->assertDontSee('Unlinked item secret')->assertDontSee('Order Context')->assertDontSee('Online')->assertDontSee('Offline');
        }
        $this->assertSame($before, $snapshot());
    }

    public function test_invalid_nested_filters_do_not_read_history_and_foreign_threads_remain_inaccessible(): void
    {
        $thread = $this->thread();
        $this->actingAs($this->shop->owner)->getJson(route('seller.messages.show', ['conversation' => $thread->id, 'list' => ['sort' => 'untrusted']]))->assertUnprocessable();
        $this->assertNull(DB::table('seller_messages')->value('read_at'));
        $foreign = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($foreign)->get(route('buyer.messages.show', $thread->id))->assertNotFound();
        $other = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Other shop', 'slug' => 'other-workspace', 'status' => 'approved']);
        $this->actingAs($other->owner)->get(route('seller.messages.show', $thread->id))->assertNotFound();
    }

    public function test_sidebar_query_count_stays_constant_as_the_inbox_grows(): void
    {
        $thread = $this->thread();
        $this->actingAs($this->shop->owner);
        DB::enableQueryLog();
        DB::flushQueryLog();
        ChatWorkspace::seller(Request::create('/seller/messages', 'GET'));
        $small = count(DB::getQueryLog());
        for ($i = 0; $i < 25; $i++) {
            SellerConversation::create(['buyer_id' => User::factory()->create()->id, 'seller_id' => $this->shop->id]);
        }
        DB::flushQueryLog();
        $data = ChatWorkspace::seller(Request::create('/seller/messages', 'GET'));
        $this->assertSame($small, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertCount(20, $data['inboxPaginator']);
    }
}
