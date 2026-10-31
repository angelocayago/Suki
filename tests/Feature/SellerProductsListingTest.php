<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Minimal in-memory fixture schema; application migrations are never invoked. */
class SellerProductsListingTest extends TestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName(), 'Refuse fixture writes outside memory.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $schema = [
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, status TEXT, created_at TEXT, updated_at TEXT',
            'categories'=>'id INTEGER PRIMARY KEY, parent_id INTEGER, name TEXT, slug TEXT, is_active INTEGER, position INTEGER DEFAULT 0',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, brand TEXT, description TEXT, shipping_options TEXT, is_active INTEGER, moderation_status TEXT, moderation_reason TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_variants'=>'id INTEGER PRIMARY KEY, product_id INTEGER, sku TEXT, name TEXT, options TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_images'=>'id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT, position INTEGER, color TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT',
            'orders'=>'id INTEGER PRIMARY KEY, status TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_order_id INTEGER, quantity INTEGER',
        ];
        foreach($schema as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'name'=>'Seller Fixture','role'=>'seller','status'=>'active']);
        DB::table('users')->insert(['id'=>22,'name'=>'Other Seller','role'=>'seller','status'=>'active']);
        DB::table('sellers')->insert([['id'=>101,'user_id'=>11,'category_id'=>1,'name'=>'Owned Shop','slug'=>'owned-shop','status'=>'approved'],['id'=>202,'user_id'=>22,'category_id'=>4,'name'=>'Foreign Shop','slug'=>'foreign-shop','status'=>'approved']]);
        DB::table('categories')->insert([
            ['id'=>1,'parent_id'=>null,'name'=>'Assigned Root','slug'=>'assigned','is_active'=>1],
            ['id'=>2,'parent_id'=>1,'name'=>'Allowed Category','slug'=>'allowed','is_active'=>1],
            ['id'=>3,'parent_id'=>2,'name'=>'Allowed Child','slug'=>'child','is_active'=>1],
            ['id'=>4,'parent_id'=>null,'name'=>'Foreign Root','slug'=>'foreign','is_active'=>1],
            ['id'=>5,'parent_id'=>4,'name'=>'Forbidden Category','slug'=>'forbidden','is_active'=>1],
            ['id'=>6,'parent_id'=>1,'name'=>'Disabled Category','slug'=>'disabled','is_active'=>0],
        ]);
    }

    private function product(int $id, int $seller=101, bool $active=true, string $moderation='APPROVED', int $category=2): void
    {
        DB::table('products')->insert(['id'=>$id,'seller_id'=>$seller,'category_id'=>$category,'name'=>($seller===101?'Owned':'FOREIGN').' Product '.$id,'slug'=>'product-'.$id,'description'=>'Saved description','is_active'=>$active,'moderation_status'=>$moderation,'created_at'=>'2026-11-01 04:00:00','updated_at'=>'2026-11-01 04:00:00']);
        DB::table('product_variants')->insert(['id'=>$id*10+1,'product_id'=>$id,'sku'=>'SKU-'.$id,'name'=>'Default','options'=>'{"Color":"Blue"}','price_minor'=>10000,'stock'=>20,'is_active'=>1]);
    }

    public function test_pagination_search_category_and_activity_work_together(): void
    {
        foreach(range(1,18)as$id)$this->product($id);
        $this->product(99,202);
        $response=$this->actingAs($this->seller)->get('/seller/products?search=owned&status=active&category=2&stock=in_stock&sort=oldest');
        $response->assertOk()->assertDontSee('FOREIGN Product')->assertViewHas('paginator',fn($p)=>$p->total()===18&&$p->count()===15)->assertViewHas('summary',fn($s)=>$s['total']===18&&$s['active']===18);
        $next=$response->viewData('paginator')->nextPageUrl();
        foreach(['search=owned','status=active','category=2','stock=in_stock','sort=oldest']as$value)$this->assertStringContainsString($value,$next);
        $this->get($next)->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[16,17,18]);
        $this->get('/seller/products?search=sku-18')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[18]);
        $this->get('/seller/products?category=5')->assertSessionHasErrors('category');
        $this->get('/seller/products?category=6')->assertSessionHasErrors('category');
        $this->get('/seller/products?status=draft')->assertSessionHasErrors('status');
    }

    public function test_variant_summaries_exclude_inactive_and_archived_inventory(): void
    {
        $this->product(1);DB::table('product_variants')->where('id',11)->update(['stock'=>100]);
        DB::table('product_variants')->insert([
            ['id'=>12,'product_id'=>1,'sku'=>'BLUE-LOW','name'=>'Blue','price_minor'=>15000,'stock'=>5,'is_active'=>1,'deleted_at'=>null],
            ['id'=>13,'product_id'=>1,'sku'=>'ZERO','name'=>'Empty','price_minor'=>20000,'stock'=>0,'is_active'=>1,'deleted_at'=>null],
            ['id'=>14,'product_id'=>1,'sku'=>'INACTIVE-SKU','name'=>'Inactive','price_minor'=>1,'stock'=>900,'is_active'=>0,'deleted_at'=>null],
            ['id'=>15,'product_id'=>1,'sku'=>'DELETED-SKU','name'=>'Deleted','price_minor'=>2,'stock'=>900,'is_active'=>1,'deleted_at'=>'2026-11-01 00:00:00'],
        ]);
        DB::table('product_images')->insert(['id'=>1,'product_id'=>1,'path'=>'products/blue.webp','color'=>'Blue','position'=>0]);
        $this->actingAs($this->seller)->get('/seller/products')->assertOk()->assertSee('products/blue.webp')->assertViewHas('paginator',fn($p)=>$p[0]['stock']===105&&$p[0]['active_variant_count']===3&&$p[0]['variant_count']===4&&$p[0]['price_min']===10000&&$p[0]['price_max']===20000&&$p[0]['low_variants']===1&&$p[0]['empty_variants']===1);
        $this->get('/seller/products?stock=low_stock')->assertOk()->assertViewHas('paginator',fn($p)=>$p->total()===1);
        $this->get('/seller/products?search=inactive-sku')->assertOk()->assertViewHas('paginator',fn($p)=>$p->total()===1);
        $this->get('/seller/products?search=deleted-sku')->assertOk()->assertViewHas('paginator',fn($p)=>$p->total()===0);
    }

    public function test_stock_filters_match_the_active_variant_model(): void
    {
        foreach(range(1,5)as$id)$this->product($id);
        DB::table('product_variants')->where('id',11)->update(['stock'=>1]);
        DB::table('product_variants')->where('id',21)->update(['is_active'=>0]);
        DB::table('product_variants')->where('id',31)->update(['stock'=>0]);
        DB::table('product_variants')->where('id',41)->update(['stock'=>10]);
        DB::table('product_variants')->where('id',51)->update(['stock'=>11]);
        $this->actingAs($this->seller);
        foreach(['in_stock'=>[1,4,5],'low_stock'=>[1,4],'out_of_stock'=>[3],'no_active_variants'=>[2]]as$filter=>$ids)$this->get('/seller/products?sort=oldest&stock='.$filter)->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===$ids);
    }

    public function test_activity_and_moderation_remain_separate_from_marketplace_visibility(): void
    {
        foreach(ProductModerationStatus::cases()as$i=>$status)$this->product($i+1,101,true,$status->value);
        $this->product(5,101,false);$this->product(6);$this->product(7,101,true,'APPROVED',6);
        DB::table('product_variants')->where('id',61)->update(['is_active'=>0]);
        $this->actingAs($this->seller)->get('/seller/products?status=active')->assertOk()->assertViewHas('paginator',fn($p)=>$p->total()===6)->assertSee('Review: PENDING')->assertSee('Review: APPROVED')->assertSee('Review: REJECTED')->assertSee('Review: HIDDEN');
        $this->get('/seller/products?status=inactive')->assertOk()->assertViewHas('paginator',fn($p)=>$p->pluck('id')->all()===[5]);
        $catalog=app(ProductCatalogService::class);$visible=$catalog->marketplaceProducts();
        $this->assertSame([2],array_values(array_map(fn($p)=>$p['id'],$visible)));
        DB::table('sellers')->where('id',101)->update(['status'=>'pending']);$this->assertSame([],$catalog->marketplaceProducts());
    }

    public function test_existing_add_edit_and_archive_contracts_remain_accessible(): void
    {
        $this->product(1);$this->product(2,202);
        DB::table('product_images')->insert(['id'=>1,'product_id'=>1,'path'=>'products/preserved.webp','color'=>'Blue','position'=>0]);
        $this->actingAs($this->seller)->get('/seller/products')->assertOk()->assertSee(route('seller.products.create'))->assertSee(route('seller.products.edit',1))->assertSee(route('seller.products.archive',1))->assertSee('name="_token"',false)->assertSee('value="DELETE"',false);
        $this->get('/seller/products/create')->assertOk();
        $this->get('/seller/products/1/edit')->assertOk()->assertSee('Owned Product 1');
        $this->delete('/seller/products/2')->assertNotFound();
        $this->delete('/seller/products/1')->assertRedirect(route('seller.products'));
        $this->assertNotNull(Product::withTrashed()->findOrFail(1)->deleted_at);
        $this->assertFalse(Product::withTrashed()->findOrFail(1)->is_active);
        $this->assertSame(20,(int)DB::table('product_variants')->where('id',11)->value('stock'));
        $this->assertSame('Blue',DB::table('product_images')->where('id',1)->value('color'));
        $this->get('/seller/products')->assertOk()->assertDontSee('Owned Product 1')->assertDontSee('FOREIGN Product');
    }

    public function test_empty_clear_filters_and_read_only_rendering(): void
    {
        $this->actingAs($this->seller)->withSession(['seller_products'=>[['name'=>'POISON SESSION PRODUCT']]])->get('/seller/products')->assertOk()->assertSee('No matching products')->assertDontSee('POISON');
        $this->product(1);$queries=[];DB::listen(function($event)use(&$queries){$queries[]=$event->sql;});
        $response=$this->get('/seller/products?search=absent&stock=low_stock');$response->assertOk()->assertSee('No matching products')->assertSee('aria-expanded="false"',false)->assertSee('id="products-advanced-panel" class="products-advanced-fields" hidden',false);
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$xpath=new \DOMXPath($dom);$clear=$xpath->query('//div[@aria-label="Product filters"]//a')->item(0)->getAttribute('href');
        $this->assertSame(route('seller.products'),$clear);$this->get($clear)->assertOk()->assertViewHas('paginator',fn($p)=>$p->total()===1);
        foreach($queries as$sql)$this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|alter|create|drop)\b/i',$sql);
    }

    public function test_seller_account_and_profile_authorization_remain_enforced(): void
    {
        $this->get('/seller/products')->assertRedirect(route('login'));
        foreach([['role'=>'buyer'],['status'=>'pending'],['is_suspended'=>true],['verification_required_at'=>'2026-11-01 00:00:00','email_verified_at'=>null]]as$changes){$user=clone $this->seller;$user->forceFill($changes);$this->actingAs($user)->get('/seller/products')->assertForbidden();}
        DB::table('sellers')->where('id',101)->update(['status'=>'pending']);$this->actingAs($this->seller)->get('/seller/products')->assertNotFound();
    }

    public function test_compact_listing_structure_disclosure_and_single_primary_add_action(): void
    {
        $this->product(1);
        $response=$this->actingAs($this->seller)->get('/seller/products');$response->assertOk();
        $dom=new \DOMDocument();@$dom->loadHTML($response->getContent());$xpath=new \DOMXPath($dom);
        $this->assertSame(4,$xpath->query('//section[@aria-label="Matching product summary"]/div')->length);
        $this->assertSame(1,$xpath->query('//*[@id="sellerProductsListing"]//a[@href="'.route('seller.products.create').'"]')->length);
        $this->assertSame(1,$xpath->query('//*[@id="products-filter-toggle"][@type="button"][@aria-expanded="false"][@aria-controls="products-advanced-panel"]')->length);
        $this->assertSame(1,$xpath->query('//*[@id="products-advanced-panel"][@hidden]')->length);
        $this->assertSame(1,$xpath->query('//*[@data-select-page]')->length);
        $this->assertSame(1,$xpath->query('//*[@data-product-checkbox]')->length);
        $response->assertSee('Last updated')->assertSee('Manage Inventory')->assertSee('Registered business category:')->assertSee('Assigned Root');
        $this->get('/seller/products?stock=in_stock&sort=oldest')->assertOk()->assertViewHas('criteria',fn($c)=>$c['stock']==='in_stock'&&$c['sort']==='oldest')->assertSee('name="stock"',false)->assertSee('id="products-advanced-panel" class="products-advanced-fields" hidden',false);
        $empty=$this->get('/seller/products?search=missing');$empty->assertOk();
        @$dom->loadHTML($empty->getContent());$xpath=new \DOMXPath($dom);
        $this->assertSame(1,$xpath->query('//*[@id="sellerProductsListing"]//a[@href="'.route('seller.products.create').'"]')->length);
    }

    public function test_registered_category_is_read_only_and_single_filter_choice_is_hidden(): void
    {
        $this->product(1);
        DB::table('categories')->where('id',3)->update(['is_active'=>false]);
        $response=$this->actingAs($this->seller)->get('/seller/products?category=2');
        $response->assertOk()->assertSee('Assigned Root')->assertDontSee('id="products-category"',false)
            ->assertSee('type="hidden" name="category" value="2"',false);
        $this->assertSame(2,(int)DB::table('products')->where('id',1)->value('category_id'));
        $this->assertSame(1,(int)DB::table('sellers')->where('id',101)->value('category_id'));
        $categories=app(\App\Services\SellerCategoryService::class);
        $profile=\App\Models\Seller::findOrFail(101);
        $this->assertFalse($categories->allows($profile,1));
        $this->assertTrue($categories->allows($profile,2));
    }

    public function test_existing_server_side_root_category_restriction_is_not_bypassed(): void
    {
        $this->mock(ProductCatalogService::class,fn($mock)=>$mock->shouldNotReceive('create'));
        $this->actingAs($this->seller)->post('/seller/products',[
            'name'=>'Root category attempt','category_id'=>1,'description'=>'Test description',
            'price'=>'10.00','stock'=>1,'sku'=>'ROOT-ATTEMPT','status'=>'active',
        ])->assertSessionHasErrors('category_id');
        $this->assertSame(0,DB::table('products')->count());
    }
}
