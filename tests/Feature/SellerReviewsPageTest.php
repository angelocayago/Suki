<?php

namespace Tests\Feature;

use App\Models\TransactionReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Minimal guarded in-memory fixtures; no application migrations are invoked. */
class SellerReviewsPageTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName(),'Refuse fixture writes outside memory.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        foreach([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, role TEXT, status TEXT, email TEXT, phone TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, status TEXT, created_at TEXT, updated_at TEXT',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, name TEXT, deleted_at TEXT',
            'product_images'=>'id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT, position INTEGER',
            'seller_orders'=>'id INTEGER PRIMARY KEY, seller_id INTEGER',
            'order_items'=>'id INTEGER PRIMARY KEY, seller_order_id INTEGER, product_id INTEGER, product_name TEXT, variant_name TEXT, variation TEXT, image TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, kind TEXT, eligibility_key TEXT UNIQUE, buyer_id INTEGER, seller_order_id INTEGER, order_item_id INTEGER, seller_id INTEGER, product_id INTEGER, rider_id INTEGER, rating INTEGER, body TEXT, edited INTEGER DEFAULT 0, seller_response TEXT, hidden_at TEXT, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Seller Fixture','role'=>'seller','status'=>'active']);
        DB::table('users')->insert([['id'=>33,'name'=>'Buyer Display','first_name'=>'Buyer','last_name'=>'Display','email'=>'private@example.test','phone'=>'PRIVATE-PHONE'],['id'=>44,'name'=>'Other Buyer','first_name'=>'Other','last_name'=>'Buyer','email'=>'other@example.test','phone'=>'OTHER-PRIVATE-PHONE']]);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'name'=>'Owned shop','status'=>'approved'],['id'=>202,'user_id'=>22,'name'=>'Foreign shop','status'=>'approved']]);
        DB::table('products')->insert([['id'=>1,'seller_id'=>101,'name'=>'Current Blue Product'],['id'=>2,'seller_id'=>202,'name'=>'FOREIGN PRODUCT'],['id'=>3,'seller_id'=>101,'name'=>'Red Product']]);
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit')->andReturnNull();
    }

    private function review(int $id, int $seller=101, int $rating=5, string $kind='product', string $body='Magandang quality. Thank you!'): void
    {
        $product=$kind==='product'?($seller===101?1:2):null;
        DB::table('seller_orders')->insert(['id'=>$id,'seller_id'=>$seller]);
        DB::table('order_items')->insert(['id'=>$id,'seller_order_id'=>$id,'product_id'=>$product,'product_name'=>$seller===101?'Purchased Blue Product':'FOREIGN SNAPSHOT','variant_name'=>'Blue / Large','variation'=>'{"Color":"Blue","Size":"Large"}','image'=>'products/purchased-blue.webp']);
        DB::table('transaction_reviews')->insert(['id'=>$id,'kind'=>$kind,'eligibility_key'=>'review-'.$id,'buyer_id'=>$seller===101?33:44,'seller_order_id'=>$id,'order_item_id'=>$kind==='product'?$id:null,'seller_id'=>$seller,'product_id'=>$product,'rating'=>$rating,'body'=>$body,'created_at'=>'2026-11-01 04:00:00','updated_at'=>'2026-11-01 04:00:00']);
    }

    private function listing(array $query=[])
    {
        return $this->actingAs($this->seller)->get(route('reviews.seller-page',$query))->assertOk();
    }

    public function test_page_uses_real_owned_reviews_names_ratings_purchased_variants_and_images(): void
    {
        $this->review(1); $this->review(2,202); $this->review(3,101,4,'seller'); $this->review(4,101,5,'rider');
        $response=$this->actingAs($this->seller)->withSession(['reviews'=>[['body'=>'POISON']]])->get(route('reviews.seller-page'))->assertOk()
            ->assertSee('Buyer Display')->assertSee('5 out of 5 stars')->assertSee('Purchased Blue Product')->assertSee('Blue / Large')->assertSee('Color')->assertSee('products/purchased-blue.webp')
            ->assertSee('Shop review')->assertSee('Magandang quality. Thank you!')->assertDontSee('FOREIGN')->assertDontSee('POISON')->assertDontSee('private@example.test')->assertDontSee('PRIVATE-PHONE')
            ->assertSee('aria-expanded="false"',false)->assertDontSee('With Media')->assertDontSee('Download PDF')->assertDontSee('name="from"',false);
        $this->assertSame([3,1],$response->viewData('reviews')->pluck('id')->all());
        if($dir=getenv('SELLER_REVIEWS_CHECK_DIR')) file_put_contents($dir.'/reviews.html',$response->getContent());
    }

    public function test_long_multilingual_and_unbroken_text_is_full_escaped_and_separate_from_images(): void
    {
        $body=str_repeat('MahabangReviewWalangEspasyo',200)."\nSecond line: Salamat po! <script>alert('x')</script> & English.";
        $this->review(1,body:$body);
        $response=$this->listing()->assertSee(e($body),false)->assertDontSee("<script>alert('x')</script>",false);
        $html=$response->getContent();
        $this->assertLessThan(strpos($html,'class="sr-review-body"'),strpos($html,'class="sr-product-header"'));
        $css=file_get_contents(resource_path('views/reviews/seller-partials/style.blade.php'));
        $this->assertStringContainsString('grid-template-columns:minmax(0,1fr)',$css);
        $this->assertStringContainsString('overflow-wrap:anywhere',$css);
        $this->assertStringContainsString('white-space:pre-wrap',$css);
        $this->assertStringContainsString('min-width:0',$css);
        preg_match('/\.seller-reviews \.sr-review-body,\.seller-reviews \.sr-reply-body\{([^}]+)\}/',$css,$match);
        $this->assertNotEmpty($match);
        $this->assertDoesNotMatchRegularExpression('/(?:^|;)\s*(?:height:|position:|overflow:hidden|text-overflow:|line-clamp:)/',$match[1]);
        $this->assertStringContainsString('@media(max-width:640px)',$css);
        $this->assertSame($body,TransactionReview::find(1)->body);
    }

    public function test_hidden_review_policy_is_preserved_for_owner_and_foreign_records_remain_hidden(): void
    {
        $this->review(1); $this->review(2,202);
        DB::table('transaction_reviews')->update(['hidden_at'=>'2026-11-01 05:00:00']);
        $response=$this->listing()->assertSee('Hidden by moderation')->assertDontSee('FOREIGN');
        $this->assertSame([1],$response->viewData('reviews')->pluck('id')->all());
        $this->assertSame(2,DB::table('transaction_reviews')->whereNotNull('hidden_at')->count());
    }

    public function test_search_rating_product_combination_is_scoped_and_pagination_preserves_filters(): void
    {
        foreach(range(1,28) as $id) $this->review($id);
        $this->review(99,202); $this->review(30,101,1);
        $query=['search'=>'purchased blue','rating'=>5,'product'=>1];
        $response=$this->listing($query)->assertSee('sr-filter-badge">3',false);
        $this->assertSame(28,$response->viewData('reviews')->total());
        $this->assertSame(25,$response->viewData('reviews')->count());
        $next=$response->viewData('reviews')->nextPageUrl();
        parse_str(parse_url($next,PHP_URL_QUERY),$params);
        foreach($query as $key=>$value) $this->assertSame((string)$value,(string)$params[$key]);
        $this->get($next)->assertOk()->assertViewHas('reviews',fn($p)=>$p->count()===3);
        $this->assertCount(3,$response->viewData('chips'));
        foreach($response->viewData('chips') as $key=>$chip) {
            parse_str(parse_url($chip['url'],PHP_URL_QUERY) ?? '',$remaining);
            $this->assertArrayNotHasKey($key,$remaining); $this->assertArrayNotHasKey('page',$remaining);
            foreach(array_diff(array_keys($query),[$key]) as $kept) $this->assertSame((string)$query[$kept],(string)$remaining[$kept]);
        }
        $this->listing(['search'=>'BUYER DISPLAY'])->assertViewHas('reviews',fn($p)=>$p->total()===29);
        $this->listing(['search'=>'current blue'])->assertViewHas('reviews',fn($p)=>$p->total()===29);
        $this->listing(['rating'=>1])->assertViewHas('reviews',fn($p)=>$p->pluck('id')->all()===[30]);
        $this->listing(['search'=>'thank you'])->assertViewHas('reviews',fn($p)=>$p->total()===29);
        $this->assertSame([],$this->listing()->viewData('chips'));
    }

    public function test_empty_invalid_and_literal_keyword_filters_are_safe(): void
    {
        $this->listing()->assertSee('No reviews yet.');
        $this->review(1,body:'100% useful'); $this->review(2,body:'Another review');
        $this->listing(['search'=>'100%'])->assertViewHas('reviews',fn($p)=>$p->pluck('id')->all()===[1]);
        $this->listing(['search'=>"' OR 1=1 --"])->assertSee('No reviews match your filters.')->assertSee('Clear Filters');
        foreach([['rating'=>0],['rating'=>6],['product'=>2],['product'=>999],['page'=>0]] as $query) $this->actingAs($this->seller)->from(route('reviews.seller-page'))->get(route('reviews.seller-page',$query))->assertSessionHasErrors(array_key_first($query));
    }

    public function test_historical_products_stay_available_and_product_lookup_is_owned_reviewed_and_bounded(): void
    {
        $this->review(1); $this->review(2,202);
        DB::table('products')->where('id',1)->update(['deleted_at'=>'2026-11-01']);
        $this->listing(['product'=>1])->assertSee('Purchased Blue Product');
        $this->actingAs($this->seller)->getJson(route('reviews.seller-page',['lookup'=>'products','q'=>'BLUE']))->assertOk()->assertJsonCount(1)->assertJsonFragment(['id'=>1,'name'=>'Current Blue Product'])->assertJsonMissing(['id'=>2]);
        $this->getJson(route('reviews.seller-page',['lookup'=>'products','q'=>'Red']))->assertOk()->assertJsonCount(0);
        foreach(range(10,30) as $id) { DB::table('products')->insert(['id'=>$id,'seller_id'=>101,'name'=>'More '.$id]); $this->review($id); DB::table('transaction_reviews')->where('id',$id)->update(['product_id'=>$id]); }
        $this->getJson(route('reviews.seller-page',['lookup'=>'products']))->assertOk()->assertJsonCount(15);
    }

    public function test_missing_snapshot_image_uses_real_product_image_without_inventing_review_media(): void
    {
        $this->review(1); DB::table('order_items')->where('id',1)->update(['image'=>null]);
        DB::table('product_images')->insert([['id'=>1,'product_id'=>1,'path'=>'products/later.webp','position'=>2],['id'=>2,'product_id'=>1,'path'=>'products/first.webp','position'=>1]]);
        $this->listing()->assertSee('products/first.webp')->assertDontSee('products/later.webp')->assertDontSee('placehold.co');
        DB::table('product_images')->delete();
        $this->listing()->assertDontSee('<img class="sr-product-image"',false)->assertSee('Purchased Blue Product');
    }

    public function test_existing_reply_update_validation_and_moderation_do_not_change_buyer_review(): void
    {
        $this->review(1); $this->review(2,202); $this->review(3,101,5,'rider');
        $before=TransactionReview::find(1)->only(['body','rating','buyer_id','edited','hidden_at']);
        $this->actingAs($this->seller)->post(route('reviews.respond',1),['response'=>'Salamat!','rating'=>1,'body'=>'Attempted replacement'])->assertRedirect(route('reviews.seller-page'))->assertSessionHas('success','Response saved.');
        $this->assertSame('Salamat!',TransactionReview::find(1)->seller_response);
        $this->assertSame($before,TransactionReview::find(1)->only(array_keys($before)));
        $this->listing()->assertSee('Seller reply')->assertSee('Salamat!')->assertSee('Edit reply')->assertSee('name="_token"',false);
        $this->postJson(route('reviews.respond',1),['response'=>'Updated reply'])->assertOk();
        $this->assertSame('Updated reply',TransactionReview::find(1)->seller_response);
        $this->postJson(route('reviews.respond',1),['response'=>str_repeat('x',501)])->assertUnprocessable();
        $this->postJson(route('reviews.respond',1),['response'=>''])->assertUnprocessable();
        $this->postJson(route('reviews.respond',2),['response'=>'Forbidden'])->assertForbidden();
        $this->postJson(route('reviews.respond',3),['response'=>'Forbidden'])->assertForbidden();
        DB::table('transaction_reviews')->where('id',1)->update(['hidden_at'=>'2026-11-01 05:00:00']);
        $this->postJson(route('reviews.respond',1),['response'=>'Owner reply to hidden review'])->assertOk();
        $this->assertNotNull(TransactionReview::find(1)->hidden_at);
        $this->assertSame($before['body'],TransactionReview::find(1)->body);
    }

    public function test_reply_validation_input_belongs_only_to_the_target_review(): void
    {
        $this->review(1); $this->review(2); DB::table('transaction_reviews')->where('id',2)->update(['seller_response'=>'Saved second reply']);
        $body=str_repeat('Bad reply',70);
        $this->actingAs($this->seller)->from(route('reviews.seller-page'))->post(route('reviews.respond',1),['response'=>$body,'_reply_review'=>1])->assertSessionHasErrors('response');
        $response=$this->listing()->assertSee('Saved second reply')->assertSee($body);
        preg_match('/<textarea id="reply-2"[^>]*>(.*?)<\/textarea>/s',$response->getContent(),$matches);
        $this->assertSame('Saved second reply',$matches[1]);
        $this->assertNull(TransactionReview::find(1)->seller_response);
    }

    public function test_routes_guards_and_buyer_admin_handlers_remain_compatible(): void
    {
        foreach(['reviews.seller-page','reviews.respond'] as $name) foreach(['web','auth','approved','not_suspended','role:seller'] as $guard) $this->assertContains($guard,app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        $this->get(route('reviews.seller-page'))->assertRedirect(route('login'));
        $this->actingAs($this->seller)->get(route('reviews.admin-page'))->assertForbidden();
        $this->assertSame(\App\Http\Controllers\TransactionReviewController::class.'@respond',app('router')->getRoutes()->getByName('reviews.respond')->getActionName());
        $this->assertSame(\App\Http\Controllers\ReviewPageController::class.'@buyer',app('router')->getRoutes()->getByName('reviews.buyer-page')->getActionName());
        $this->assertSame(\App\Http\Controllers\ReviewPageController::class.'@admin',app('router')->getRoutes()->getByName('reviews.admin-page')->getActionName());
    }

    public function test_read_page_uses_only_select_queries_and_bounded_pagination_without_n_plus_one(): void
    {
        foreach(range(1,30) as $id) $this->review($id);
        DB::enableQueryLog();
        try {
            $response=$this->listing(); $this->assertSame(25,$response->viewData('reviews')->count());
            foreach(DB::getQueryLog() as $query) $this->assertMatchesRegularExpression('/^select\b/i',ltrim($query['query']));
            $this->assertLessThan(20,count(DB::getQueryLog()));
        } finally { DB::disableQueryLog(); DB::flushQueryLog(); }
    }
}
