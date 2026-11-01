<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\MessagingService;
use App\Support\SellerInbox;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Explicit disposable memory schema only. Never runs application migrations. */
class SellerMessagesInboxTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach ([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, status TEXT, logo_path TEXT, category_id INTEGER',
            'categories'=>'id INTEGER PRIMARY KEY, name TEXT',
            'conversations'=>'id INTEGER PRIMARY KEY, reference TEXT, conversation_key TEXT, type TEXT, status TEXT, subject TEXT, seller_id INTEGER, seller_order_id INTEGER, order_id INTEGER, product_id INTEGER, shipment_id INTEGER, complaint_id INTEGER, created_by INTEGER, context_snapshot TEXT, last_message_at TEXT, closed_at TEXT, created_at TEXT, updated_at TEXT',
            'conversation_participants'=>'id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, user_id INTEGER, role_context TEXT, seller_id INTEGER, rider_id INTEGER, logistics_provider_id INTEGER, joined_at TEXT, left_at TEXT, last_read_message_id INTEGER, last_read_at TEXT, created_at TEXT, updated_at TEXT',
            'messages'=>'id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER, sender_id INTEGER, type TEXT, body TEXT, reply_to_id INTEGER, sent_at TEXT, created_at TEXT, updated_at TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, order_id INTEGER',
            'orders'=>'id INTEGER PRIMARY KEY, buyer_id INTEGER, order_number TEXT',
            'complaints'=>'id INTEGER PRIMARY KEY, complainant_id INTEGER, assigned_to INTEGER',
            'shipments'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, rider_id INTEGER, pickup_rider_id INTEGER, logistics_provider_id INTEGER',
            'riders'=>'id INTEGER PRIMARY KEY, user_id INTEGER, logistics_provider_id INTEGER',
            'logistics_providers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, status TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        foreach ([11=>'seller',22=>'buyer',33=>'buyer',44=>'admin',55=>'seller',66=>'logistics',77=>'rider',88=>'superadmin'] as $id=>$role) {
            DB::table('users')->insert(['id'=>$id,'name'=>$id===22?'Fallback buyer':ucfirst($role).' '.$id,'first_name'=>$id===22?'Buyer':null,'last_name'=>$id===22?'Gagsti':null,'role'=>$role,'status'=>'active']);
        }
        $this->seller=User::findOrFail(11);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Shuk','slug'=>'shuk','status'=>'approved'],['id'=>202,'user_id'=>55,'name'=>'Foreign Shop','slug'=>'foreign-shop','status'=>'approved']]);
        DB::table('orders')->insert(['id'=>1,'buyer_id'=>22,'order_number'=>'ORDER-REAL-1']);
        DB::table('seller_orders')->insert([['id'=>1,'seller_id'=>101,'order_id'=>1],['id'=>2,'seller_id'=>202,'order_id'=>1]]);
        DB::table('logistics_providers')->insert(['id'=>301,'user_id'=>66,'name'=>'Provider','slug'=>'provider','status'=>'approved']);
        DB::table('riders')->insert(['id'=>401,'user_id'=>77,'logistics_provider_id'=>301]);
        DB::table('shipments')->insert(['id'=>501,'seller_order_id'=>1,'rider_id'=>401,'pickup_rider_id'=>401,'logistics_provider_id'=>301]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 04:00:00','UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow(); parent::tearDown();
    }

    private function conversation(int $id=1, string $type='STORE_INQUIRY', array $extra=[]): Conversation
    {
        DB::table('conversations')->insert($extra+['id'=>$id,'reference'=>'00000000-0000-4000-8000-'.str_pad((string)$id,12,'0',STR_PAD_LEFT),'type'=>$type,'status'=>'ACTIVE','seller_id'=>101,'context_snapshot'=>json_encode(['seller'=>['name'=>'Shuk'],'product'=>['name'=>'Gelo']]),'created_at'=>'2026-11-01 00:00:00']);
        DB::table('conversation_participants')->insert([
            ['conversation_id'=>$id,'user_id'=>11,'role_context'=>'SELLER','seller_id'=>101],
            ['conversation_id'=>$id,'user_id'=>22,'role_context'=>'BUYER','seller_id'=>null],
        ]);
        return Conversation::findOrFail($id);
    }

    private function message(Conversation $conversation, string $body='Buyer message', int $sender=22, ?string $date=null): int
    {
        return DB::table('messages')->insertGetId(['conversation_id'=>$conversation->id,'sender_id'=>$sender,'type'=>'TEXT','body'=>$body,'sent_at'=>$date ?? '2026-10-31 17:00:00']);
    }

    private function inbox(array $query=[])
    {
        return $this->actingAs($this->seller)->get(route('seller.messages.index',$query))->assertOk();
    }

    public function test_two_pane_pages_share_counterpart_identity_and_preserve_context_and_forms(): void
    {
        $conversation=$this->conversation(); $this->message($conversation,'<script>unsafe</script>');
        $this->inbox()->assertViewIs('seller.messages.inbox')->assertSee('Select a conversation')->assertSee('Buyer Gagsti')->assertSee('Shuk')->assertSee('Gelo')->assertDontSee('<script>unsafe</script>',false);
        $response=$this->get(route('seller.messages.show',$conversation->reference))->assertOk()->assertSee('id="sellerInbox"',false)
            ->assertSee('<h2>Buyer Gagsti</h2>',false)->assertSee('aria-current="page"',false)->assertSee('data-last-id="1"',false)
            ->assertSee('name="_token"',false)->assertSee('maxlength="10000"',false)->assertSee('inbox-back')->assertSee('Today')
            ->assertSee('1:00 AM')->assertSee('&lt;script&gt;unsafe&lt;/script&gt;',false)->assertDontSee('type="file"',false);
        $this->assertNull(DB::table('conversation_participants')->where('user_id',11)->value('last_read_message_id'));
        if ($dir=getenv('SELLER_INBOX_CHECK_DIR')) file_put_contents($dir.'/inbox.html',$response->getContent());
    }

    public function test_filters_search_unread_and_support_types_use_owned_real_records(): void
    {
        $one=$this->conversation(1); $first=$this->message($one,'Incoming'); $this->message($one,'Outgoing',11);
        $this->conversation(2,'ORDER',['subject'=>'Order question','seller_order_id'=>1,'order_id'=>1,'context_snapshot'=>json_encode(['order'=>['order_number'=>'ORDER-REAL-1']])]);
        $this->conversation(3,'ADMIN_SUPPORT',['subject'=>'Support case']);
        DB::table('conversation_participants')->where('conversation_id',3)->where('user_id',22)->delete();
        DB::table('conversation_participants')->insert(['conversation_id'=>3,'user_id'=>44,'role_context'=>'ADMIN']);
        $this->assertSame(3,$this->inbox()->viewData('memberships')->total());
        foreach (['inquiry'=>1,'order'=>1,'unread'=>1] as $filter=>$count) $this->assertSame($count,$this->inbox(['filter'=>$filter])->viewData('memberships')->total());
        $this->assertSame(1,(int)$this->inbox(['filter'=>'unread'])->viewData('memberships')->first()->unread_count);
        foreach (['Buyer Gagsti'=>2,'Gelo'=>2,'ORDER-REAL-1'=>1,'Support case'=>1,'missing'=>0,'%'=>0] as $search=>$count) $this->assertSame($count,$this->inbox(['search'=>$search])->viewData('memberships')->total());
        $this->inbox(['search'=>'missing'])->assertSee('No matching conversations');
        $this->postJson(route('seller.messages.read',$one->reference),['message_id'=>$first])->assertNoContent();
        $this->assertSame(0,$this->inbox(['filter'=>'unread'])->viewData('memberships')->total());
    }

    public function test_stale_and_departed_memberships_are_excluded_before_pagination_without_n_plus_one(): void
    {
        for ($i=1;$i<=18;$i++) $this->conversation($i,'ORDER',['seller_order_id'=>1]);
        for ($i=19;$i<=36;$i++) $this->conversation($i,'ORDER',['seller_order_id'=>2,'subject'=>'FOREIGN SECRET']);
        $this->conversation(37); DB::table('conversation_participants')->where('conversation_id',37)->where('user_id',11)->update(['left_at'=>'2026-11-01']);
        $this->conversation(38,'COMPLAINT',['complaint_id'=>1]);DB::table('complaints')->insert(['id'=>1,'complainant_id'=>22,'assigned_to'=>44]);
        DB::enableQueryLog();$page=$this->inbox(['search'=>'Gelo']);$queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertSame(18,$page->viewData('memberships')->total());$this->assertCount(15,$page->viewData('memberships'));
        $page->assertDontSee('FOREIGN SECRET')->assertSee('search=Gelo',false)->assertSee('page=2',false);
        $this->assertLessThan(22,count($queries),'Inbox queries must be bounded independently of conversation count.');
        $second=$this->inbox(['search'=>'Gelo','page'=>2])->viewData('memberships');$this->assertCount(3,$second);
        foreach ($page->viewData('memberships') as $membership) $this->assertTrue(app(MessagingService::class)->canAccessAs($membership->conversation,$this->seller,['SELLER']));
    }

    public function test_initial_newest_fifty_older_pages_and_forward_poll_have_no_gaps_or_duplicates(): void
    {
        $conversation=$this->conversation();for ($i=1;$i<=125;$i++) $this->message($conversation,'Message '.$i);
        $response=$this->actingAs($this->seller)->get(route('seller.messages.show',$conversation->reference))->assertOk();
        $this->assertSame(range(76,125),$response->viewData('messages')->pluck('id')->all());
        $response->assertSee('data-last-id="125"',false)->assertSee('id="inboxLoadOlder"',false);
        $one=$this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'before_id'=>76]))->assertOk()->assertJsonPath('has_older',true)->json('messages');
        $two=$this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'before_id'=>26]))->assertOk()->assertJsonPath('has_older',false)->json('messages');
        $this->assertSame(range(26,75),array_column($one,'id'));$this->assertSame(range(1,25),array_column($two,'id'));
        $this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'before_id'=>1]))->assertOk()->assertJsonCount(0,'messages')->assertJsonPath('has_older',false);
        $last=$this->message($conversation,'New arrival');
        $this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'after_id'=>125]))->assertOk()->assertJsonCount(1,'messages')->assertJsonPath('latest_id',$last)->assertJsonPath('messages.0.display.id',(string)$last)->assertJsonPath('messages.0.display.time','1:00 AM');
        $this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'after_id'=>1,'before_id'=>100]))->assertUnprocessable();
        $this->assertNull(DB::table('conversation_participants')->where('user_id',11)->value('last_read_message_id'));
    }

    public function test_authorization_applies_to_show_poll_older_send_and_read_and_eligibility(): void
    {
        $conversation=$this->conversation();$message=$this->message($conversation);
        $this->actingAs(User::findOrFail(55));
        $this->get(route('seller.messages.show',$conversation->reference))->assertForbidden();
        $this->getJson(route('seller.messages.poll',['conversation'=>$conversation->reference,'before_id'=>10]))->assertForbidden();
        $this->postJson(route('seller.messages.store',$conversation->reference),['body'=>'forged'])->assertForbidden();
        $this->postJson(route('seller.messages.read',$conversation->reference),['message_id'=>$message])->assertForbidden();
        $this->inbox()->assertSee('Buyer Gagsti');
        DB::table('conversation_participants')->where('conversation_id',1)->where('user_id',11)->update(['left_at'=>'2026-11-01']);
        $this->get(route('seller.messages.show',$conversation->reference))->assertForbidden();
        $this->assertSame(0,$this->inbox()->viewData('memberships')->total());
        $this->seller->forceFill(['is_suspended'=>true])->save();$this->actingAs($this->seller)->get(route('seller.messages.index'))->assertForbidden();
    }

    public function test_send_validation_read_cursor_and_closed_or_archived_threads_remain_canonical(): void
    {
        $conversation=$this->conversation();$one=$this->message($conversation);$two=$this->message($conversation,'Later');
        $this->actingAs($this->seller)->postJson(route('seller.messages.store',$conversation->reference),['body'=>'  Hello\nBuyer  '])->assertCreated()->assertJsonPath('message.body','Hello\nBuyer');
        $last=DB::table('messages')->max('id');
        $this->assertSame($last,(int)DB::table('conversation_participants')->where('user_id',11)->value('last_read_message_id'));
        $this->postJson(route('seller.messages.read',$conversation->reference),['message_id'=>$one])->assertNoContent();
        $this->assertSame($last,(int)DB::table('conversation_participants')->where('user_id',11)->value('last_read_message_id'));
        foreach (['   ',str_repeat('x',10001)] as $body) $this->postJson(route('seller.messages.store',$conversation->reference),['body'=>$body])->assertUnprocessable();
        foreach (['CLOSED','ARCHIVED'] as $status) {
            $conversation->update(['status'=>$status]);
            $this->get(route('seller.messages.show',$conversation->reference))->assertOk()->assertSee('This conversation is '.strtolower($status));
            $this->postJson(route('seller.messages.store',$conversation->reference),['body'=>'blocked'])->assertForbidden();
        }
        $this->assertSame(3,DB::table('messages')->count());
    }

    public function test_non_seller_portals_keep_shared_views_json_contracts_and_correct_initial_order(): void
    {
        $conversation=$this->conversation(1,'ORDER',['seller_order_id'=>1,'order_id'=>1,'shipment_id'=>501]);
        foreach ([[44,'ADMIN',null,null],[88,'ADMIN',null,null],[66,'LOGISTICS',null,301],[77,'DELIVERY_RIDER',401,null]] as [$user,$role,$rider,$provider]) DB::table('conversation_participants')->insert(['conversation_id'=>1,'user_id'=>$user,'role_context'=>$role,'rider_id'=>$rider,'logistics_provider_id'=>$provider]);
        for ($i=1;$i<=55;$i++) $this->message($conversation,'Existing contract '.$i);
        foreach (['buyer'=>22,'admin'=>44,'superadmin'=>88,'logistics'=>66,'rider'=>77] as $portal=>$id) {
            $this->actingAs(User::findOrFail($id));
            $this->get(route($portal.'.messages.index'))->assertOk()->assertViewIs('messages.index');
            $response=$this->get(route($portal.'.messages.show',$conversation->reference))->assertOk()->assertViewIs('messages.show')->assertDontSee('id="sellerInbox"',false);
            $this->assertSame(range(6,55),$response->viewData('messages')->pluck('id')->all());
            $this->getJson(route($portal.'.messages.poll',['conversation'=>$conversation->reference,'after_id'=>54]))->assertOk()->assertJsonPath('messages.0.id',55)->assertJsonMissingPath('messages.0.display');
        }
        foreach (['buyer'=>22,'admin'=>44,'superadmin'=>88,'logistics'=>66,'rider'=>77] as $portal=>$id) {
            $this->actingAs(User::findOrFail($id));
            $sent=$this->postJson(route($portal.'.messages.store',$conversation->reference),['body'=>'Preserved '.$portal.' send'])->assertCreated()->assertJsonMissingPath('message.display')->json('message.id');
            $this->postJson(route($portal.'.messages.read',$conversation->reference),['message_id'=>$sent])->assertNoContent();
        }
    }

    public function test_identity_fallback_group_roles_and_all_canonical_types_remain_available(): void
    {
        $one=$this->conversation();
        DB::table('users')->where('id',22)->update(['first_name'=>null,'last_name'=>null]);
        $this->inbox()->assertSee('Fallback buyer');
        $this->get(route('seller.messages.show',$one->reference))->assertOk()->assertSee('<h2>Fallback buyer</h2>',false);
        foreach (['COMPLAINT','RIDER_SUPPORT','LOGISTICS_SUPPORT','ADMIN_SUPPORT'] as $offset=>$type) {
            $id=$offset+2;$this->conversation($id,$type);
            DB::table('conversation_participants')->where('conversation_id',$id)->where('user_id',22)->delete();
            DB::table('conversation_participants')->insert(['conversation_id'=>$id,'user_id'=>44,'role_context'=>'ADMIN']);
        }
        $page=$this->inbox();$this->assertSame(5,$page->viewData('memberships')->total());$page->assertSee('Admin 44');
        $this->assertSame(1,$this->inbox(['filter'=>'inquiry'])->viewData('memberships')->total());
        DB::table('conversation_participants')->insert(['conversation_id'=>1,'user_id'=>33,'role_context'=>'BUYER','left_at'=>'2026-11-01']);
        $this->get(route('seller.messages.show',$one->reference))->assertOk()->assertSee('<h2>Fallback buyer</h2>',false)->assertDontSee('Buyer 33');
    }

    public function test_manila_dates_grouping_normal_post_and_invalid_or_foreign_cursors(): void
    {
        $one=$this->conversation();$this->message($one,'Older',22,'2026-10-25 17:00:00');
        $this->message($one,'Yesterday',22,'2026-10-30 17:00:00');$this->message($one,'Today',22,'2026-10-31 17:00:00');
        $this->message($one,'Consecutive',22,'2026-10-31 17:01:00');
        $html=$this->actingAs($this->seller)->get(route('seller.messages.show',$one->reference))->assertOk()->assertSee('Yesterday')->assertSee('Today')->assertSee('Oct 26, 2026')->assertSee('1:01 AM')->getContent();
        $this->assertSame(1,substr_count($html,'class="inbox-message  is-grouped"'));
        $this->post(route('seller.messages.store',$one->reference),['body'=>"Plain form\nNewline"])->assertRedirect(route('seller.messages.show',$one->reference));
        $this->assertSame("Plain form\nNewline",DB::table('messages')->orderByDesc('id')->value('body'));
        $other=$this->conversation(2);$foreign=$this->message($other);
        $this->postJson(route('seller.messages.read',$one->reference),['message_id'=>$foreign])->assertNotFound();
        foreach (['before_id'=>0,'after_id'=>-1] as $key=>$value) $this->getJson(route('seller.messages.poll',['conversation'=>$one->reference,$key=>$value]))->assertUnprocessable();
        foreach (['filter'=>'fake','search'=>str_repeat('x',101),'page'=>0] as $key=>$value) $this->getJson(route('seller.messages.index',[$key=>$value]))->assertUnprocessable();
        $this->seller->forceFill(['verification_required_at'=>'2026-11-01','email_verified_at'=>null])->save();
        $this->actingAs($this->seller)->get(route('seller.messages.index'))->assertForbidden();
    }
}
