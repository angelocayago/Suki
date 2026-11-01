<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guarded SQLite memory fixtures only; no migrations or shared database writes. */
class SellerHeaderNotificationsTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName(),'Refuse fixture writes outside memory.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, logo_path TEXT, category_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT',
            'categories'=>'id INTEGER PRIMARY KEY, name TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, seller_id INTEGER',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, deleted_at TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, kind TEXT',
            'return_refund_requests'=>'id INTEGER PRIMARY KEY, seller_id INTEGER',
            'conversations'=>'id INTEGER PRIMARY KEY, reference TEXT, seller_id INTEGER, seller_order_id INTEGER, complaint_id INTEGER',
            'conversation_participants'=>'id INTEGER PRIMARY KEY, conversation_id INTEGER, user_id INTEGER, role_context TEXT, seller_id INTEGER, left_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Lance Seller','first_name'=>'Lance','last_name'=>'Seller','role'=>'seller','status'=>'active']);
        DB::table('users')->insert(['id'=>22,'name'=>'Other Admin','role'=>'admin','status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Owned shop','slug'=>'owned-shop','logo_path'=>'seller-store-assets/owned.webp','category_id'=>1,'status'=>'approved'],['id'=>202,'user_id'=>22,'name'=>'Foreign shop','slug'=>'foreign-shop','logo_path'=>'seller-store-assets/FOREIGN.webp','category_id'=>1,'status'=>'approved']]);
        DB::table('categories')->insert(['id'=>1,'name'=>'Assigned Category']);
        DB::table('seller_orders')->insert([['id'=>1,'seller_id'=>101],['id'=>2,'seller_id'=>202]]);
        DB::table('products')->insert([['id'=>1,'seller_id'=>101,'deleted_at'=>null],['id'=>2,'seller_id'=>202,'deleted_at'=>null],['id'=>3,'seller_id'=>101,'deleted_at'=>'2026-11-01']]);
        DB::table('transaction_reviews')->insert([['id'=>1,'seller_id'=>101,'kind'=>'product'],['id'=>2,'seller_id'=>202,'kind'=>'product']]);
        DB::table('return_refund_requests')->insert([['id'=>1,'seller_id'=>101],['id'=>2,'seller_id'=>202]]);
    }

    private function notice(int $number, int $user=11, bool $read=false, array $context=[], ?string $title=null, ?string $morph=null): string
    {
        $id='00000000-0000-4000-8000-'.str_pad((string)$number,12,'0',STR_PAD_LEFT);
        DB::table('notifications')->insert(['id'=>$id,'type'=>\App\Notifications\DomainEventNotification::class,'notifiable_type'=>$morph ?? $this->seller->getMorphClass(),'notifiable_id'=>$user,'data'=>json_encode(['title'=>$title ?? 'Real event '.$number,'message'=>'Actual stored message '.$number,'context'=>$context]),'read_at'=>$read?'2026-11-01 05:00:00':null,'created_at'=>'2026-11-01 04:00:00','updated_at'=>'2026-11-01 04:00:00']);
        return $id;
    }

    private function inbox(array $query=[])
    {
        return $this->actingAs($this->seller)->get(route('seller.notifications.index',$query))->assertOk();
    }

    public function test_header_keeps_actions_uses_owned_photo_profile_links_and_post_logout(): void
    {
        $response=$this->actingAs($this->seller)->withSession(['seller_profile'=>['profile_photo'=>'FOREIGN-SESSION-PHOTO.webp']])->get(route('seller.notifications.index'))->assertOk();
        $response->assertSee(route('buyer.stores.show','owned-shop'))->assertSee(route('seller.products.create'))->assertSee(route('seller.store.profile'))
            ->assertSee('seller-store-assets/owned.webp')->assertSee('Lance Seller profile menu')->assertDontSee('FOREIGN-SESSION-PHOTO')->assertDontSee('seller-store-assets/FOREIGN.webp')->assertSee(route('seller.profile'))
            ->assertSee('id="sellerNotificationBell"',false)->assertSee('id="sellerProfileToggle"',false)
            ->assertSee('method="POST" data-seller-logout',false)->assertSee('name="_token"',false);
        $html=$response->getContent();
        $this->assertStringNotContainsString('href="'.route('seller.promotions').'"',$html);
        $this->assertStringContainsString('href="'.route('seller.vouchers.index').'"',$html);
        $this->assertStringContainsString('href="'.route('seller.notifications.index').'"',$html);
        foreach(['seller.promotions','seller.promotions.store','seller.promotions.destroy'] as $name) $this->assertNotNull(app('router')->getRoutes()->getByName($name));
        if($dir=getenv('SELLER_HEADER_CHECK_DIR')) file_put_contents($dir.'/header.html',$html);
    }

    public function test_empty_header_hides_badges_and_avatar_falls_back_to_real_initial(): void
    {
        DB::table('sellers')->where('id',101)->update(['logo_path'=>null]);
        $response=$this->inbox()->assertSee('No notifications yet.')->assertSee('data-seller-avatar-fallback >L</span>',false);
        $html=$response->getContent();
        preg_match('/<span class="seller-header-badge"[^>]*>(.*?)<\/span>/s',$html,$match);
        $this->assertStringContainsString('hidden',$match[0]); $this->assertSame('',$match[1]);
        $this->assertStringNotContainsString('Notifications, 0 unread',$html);
    }

    public function test_preview_and_page_are_recipient_and_morph_scoped_and_gets_do_not_mark_read(): void
    {
        $own=$this->notice(1); $this->notice(2,read:true); $other=$this->notice(3,22,title:'OTHER PRIVATE');
        $wrongMorph=$this->notice(4,title:'WRONG MORPH',morph:'App\\Models\\Seller');
        $this->inbox()->assertSee('Real event 1')->assertSee('Real event 2')->assertDontSee('OTHER PRIVATE')->assertDontSee('WRONG MORPH')->assertSee('Notifications, 1 unread');
        $this->getJson(route('seller.notifications.preview'))->assertOk()->assertJsonPath('unread_count',1)->assertJsonCount(2,'notifications')->assertJsonMissing(['title'=>'OTHER PRIVATE'])->assertJsonMissing(['title'=>'WRONG MORPH']);
        foreach([$own,$other,$wrongMorph] as $id) $this->assertNull(DB::table('notifications')->where('id',$id)->value('read_at'));
        $this->assertSame(4,DB::table('notifications')->count());
    }

    public function test_badge_caps_large_count_and_preview_contains_only_recent_five(): void
    {
        foreach(range(1,105) as $id) $this->notice($id);
        $this->inbox()->assertSee('99+')->assertSee('Notifications, 105 unread');
        $response=$this->getJson(route('seller.notifications.preview'))->assertOk()->assertJsonPath('unread_count',105)->assertJsonCount(5,'notifications');
        $this->assertSame('Real event 105',$response->json('notifications.0.title'));
    }

    public function test_inbox_pagination_and_unread_filter_are_real_and_stable(): void
    {
        foreach(range(1,28) as $id) $this->notice($id);
        $this->notice(99,read:true); $this->notice(100,22);
        $response=$this->inbox(['filter'=>'unread']);
        $this->assertSame(28,$response->viewData('notifications')->total()); $this->assertSame(25,$response->viewData('notifications')->count());
        $next=$response->viewData('notifications')->nextPageUrl(); $this->assertStringContainsString('filter=unread',$next);
        $this->get($next)->assertOk()->assertViewHas('notifications',fn($p)=>$p->count()===3);
        $this->inbox()->assertViewHas('notifications',fn($p)=>$p->total()===29);
        $this->get(route('seller.notifications.index',['filter'=>'fake']))->assertSessionHasErrors('filter');
    }

    public function test_canonical_read_operations_only_change_the_authenticated_recipient(): void
    {
        $own=$this->notice(1); $this->notice(2); $foreign=$this->notice(3,22); $morph=$this->notice(4,morph:'App\\Models\\Seller');
        $this->actingAs($this->seller)->post(route('seller.notifications.read',$foreign))->assertNotFound();
        $this->post(route('seller.notifications.read','not-a-uuid'))->assertNotFound();
        $this->post(route('seller.notifications.open','not-a-uuid'))->assertNotFound();
        $this->post(route('seller.notifications.read',$morph))->assertNotFound();
        $this->post(route('seller.notifications.read',$own),['filter'=>'unread'])->assertRedirect(route('seller.notifications.index',['filter'=>'unread']));
        $this->assertNotNull(DB::table('notifications')->where('id',$own)->value('read_at'));
        $this->postJson(route('seller.notifications.read-all'))->assertOk()->assertJsonPath('unread_count',0);
        $this->assertNull(DB::table('notifications')->where('id',$foreign)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id',$morph)->value('read_at'));
        $this->inbox(['filter'=>'unread'])->assertSee('No unread notifications.');
    }

    public function test_open_notification_uses_owned_canonical_destinations_and_never_raw_urls(): void
    {
        $contexts=[['seller_order_id'=>1],['product_id'=>1],['review_id'=>1],['return_refund_request_id'=>1]];
        $targets=[route('seller.orders.show',1),route('seller.products.edit',1),route('reviews.seller-page'),route('seller.return-refunds.show',1)];
        foreach($contexts as $index=>$context) {
            $id=$this->notice($index+1,context:$context);
            $this->actingAs($this->seller)->post(route('seller.notifications.open',$id))->assertRedirect($targets[$index]);
            $this->assertNotNull(DB::table('notifications')->where('id',$id)->value('read_at'));
        }
        foreach([['seller_order_id'=>2],['product_id'=>2],['product_id'=>3],['review_id'=>2],['return_refund_request_id'=>2],['url'=>'https://evil.example/','seller_order_id'=>'1 OR 1=1']] as $index=>$context) {
            $id=$this->notice($index+20,context:$context);
            $this->post(route('seller.notifications.open',$id),['return_to'=>'https://evil.example'])->assertRedirect(route('seller.notifications.index'));
        }
        $foreign=$this->notice(100,22); $this->post(route('seller.notifications.open',$foreign))->assertNotFound();
    }

    public function test_conversation_destinations_use_current_canonical_participant_permissions(): void
    {
        DB::table('conversations')->insert([['id'=>1,'reference'=>'OWN-THREAD','seller_id'=>101,'seller_order_id'=>1,'complaint_id'=>null],['id'=>2,'reference'=>'FOREIGN-THREAD','seller_id'=>202,'seller_order_id'=>2,'complaint_id'=>null]]);
        DB::table('conversation_participants')->insert([['id'=>1,'conversation_id'=>1,'user_id'=>11,'role_context'=>'SELLER','seller_id'=>101,'left_at'=>null],['id'=>2,'conversation_id'=>2,'user_id'=>11,'role_context'=>'SELLER','seller_id'=>202,'left_at'=>null]]);
        $own=$this->notice(1,context:['conversation_id'=>1]); $foreign=$this->notice(2,context:['conversation_id'=>2]);
        $this->actingAs($this->seller)->post(route('seller.notifications.open',$own))->assertRedirect(route('seller.messages.show','OWN-THREAD'));
        $this->post(route('seller.notifications.open',$foreign))->assertRedirect(route('seller.notifications.index'));
    }

    public function test_notification_content_is_escaped_and_wrapped(): void
    {
        $this->notice(1,title:'<script>alert(1)</script>');
        $this->inbox()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $css=file_get_contents(resource_path('views/seller/header/style.blade.php'));
        $this->assertStringContainsString('overflow-wrap:anywhere',$css); $this->assertStringContainsString('@media(max-width:640px)',$css);
    }

    public function test_new_seller_guards_and_existing_generic_endpoints_are_preserved(): void
    {
        foreach(['index','preview','read','read-all','open'] as $action) foreach(['web','auth','not_suspended','approved','role:seller'] as $guard) $this->assertContains($guard,app('router')->getRoutes()->getByName('seller.notifications.'.$action)->gatherMiddleware());
        $this->get(route('seller.notifications.index'))->assertRedirect(route('login'));
        $admin=User::find(22); $this->actingAs($admin)->get(route('seller.notifications.index'))->assertForbidden();
        $this->getJson(route('notifications.index'))->assertOk();
        foreach(['page'=>'page','index'=>'index','unread-count'=>'unreadCount','read'=>'markRead','read-all'=>'markAllRead'] as $route=>$action) $this->assertSame(\App\Http\Controllers\NotificationInboxController::class.'@'.$action,app('router')->getRoutes()->getByName('notifications.'.$route)->getActionName());
    }
}
