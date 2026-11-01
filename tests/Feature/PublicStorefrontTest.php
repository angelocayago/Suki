<?php

namespace Tests\Feature;

use App\Models\{Category, Product, ProductVariant, Seller, User, TransactionReview};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;
    private Category $category;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('These fixtures require explicit isolated SQLite memory settings.');
        }
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $owner = User::factory()->create(['role'=>'seller', 'status'=>'active', 'is_suspended'=>false]);
        $this->category = Category::create(['name'=>'Home essentials', 'slug'=>'home-essentials', 'is_active'=>true]);
        $this->shop = Seller::create(['user_id'=>$owner->id, 'name'=>'River & Reed', 'slug'=>'river-reed', 'status'=>'approved', 'category_id'=>$this->category->id, 'description'=>'Everyday goods, thoughtfully chosen.', 'logo_path'=>'seller-store-assets/logo.png', 'banner_path'=>'seller-store-assets/banner.png']);
    }

    private function product(string $name='Woven basket', array $attributes=[], array $variant=[]): Product
    {
        $product=Product::create(array_replace(['seller_id'=>$this->shop->id, 'category_id'=>$this->category->id, 'name'=>$name, 'slug'=>\Illuminate\Support\Str::slug($name), 'is_active'=>true, 'moderation_status'=>'APPROVED'], $attributes));
        ProductVariant::create(array_replace(['product_id'=>$product->id, 'sku'=>'SKU-'.$product->id, 'name'=>'Default', 'price_minor'=>12550, 'stock'=>5, 'is_active'=>true], $variant));
        return $product;
    }

    private function page(array $query=[])
    {
        return $this->get(route('buyer.stores.show', ['seller'=>$this->shop->slug]+$query));
    }

    public function test_real_store_data_images_product_price_and_existing_detail_and_chat_links(): void
    {
        $product=$this->product();
        $product->images()->create(['path'=>'products/basket.png','position'=>0]);
        $page=$this->page()->assertOk()->assertSee('River &amp; Reed',false)->assertSee('Everyday goods, thoughtfully chosen.')
            ->assertSee('Home essentials')->assertSee('Approved store')->assertSee('?125.50')->assertSee('In stock')
            ->assertSee(asset('storage/seller-store-assets/banner.png'),false)->assertSee(asset('storage/seller-store-assets/logo.png'),false)
            ->assertSee(route('buyer.product',$product->slug),false)->assertSee(route('login'),false)->assertSee('Sign in to chat with seller')
            ->assertSee('rel="canonical" href="'.route('buyer.stores.show',$this->shop).'"',false)->assertSee('data-url="'.route('buyer.stores.show',$this->shop).'"',false);
        $this->assertSame(1,$page->viewData('visibleCount'));
        if($dir=getenv('STOREFRONT_RENDER_DIR'))file_put_contents($dir.'/storefront.html',$page->getContent());
    }

    public function test_store_visibility_remains_approved_active_unsuspended_even_for_owner(): void
    {
        $this->shop->update(['status'=>'pending']);
        $this->page()->assertNotFound();
        $this->actingAs($this->shop->owner);$this->page()->assertNotFound();
        $this->shop->update(['status'=>'approved']);
        $this->shop->owner->update(['is_suspended'=>true]);$this->page()->assertNotFound();
        $this->shop->owner->update(['is_suspended'=>false,'status'=>'inactive']);$this->page()->assertNotFound();
    }

    public function test_only_owned_active_approved_in_stock_products_and_non_deleted_variants_are_public(): void
    {
        $good=$this->product('Public basket');
        $this->product('Pending basket',['moderation_status'=>'PENDING']);
        $this->product('Inactive basket',['is_active'=>false]);
        $this->product('Empty basket',[],['stock'=>0]);
        $this->product('Inactive variant',[],['is_active'=>false]);
        $deleted=$this->product('Deleted variant');$deleted->variants()->first()->delete();
        $other=Seller::create(['user_id'=>$this->shop->user_id,'name'=>'Other','slug'=>'other','status'=>'approved']);
        $this->product('Foreign basket',['seller_id'=>$other->id]);
        $page=$this->page()->assertOk();
        $this->assertSame([$good->id],$page->viewData('products')->pluck('id')->all());
        $this->assertSame(1,$page->viewData('visibleCount'));
    }

    public function test_search_category_and_sort_combine_and_search_treats_wildcards_literally(): void
    {
        $other=Category::create(['name'=>'Kitchen','slug'=>'kitchen','is_active'=>true]);
        $first=$this->product('Cotton towel',[],['price_minor'=>900]);
        $second=$this->product('Cotton basket',[],['price_minor'=>1800]);
        $this->product('Cotton napkin',['category_id'=>$other->id],['price_minor'=>100]);
        $page=$this->page(['q'=>'COTTON','category'=>$this->category->id,'sort'=>'price_desc'])->assertOk();
        $this->assertSame([$second->id,$first->id],$page->viewData('products')->pluck('id')->all());
        $this->assertSame([$first->id,$second->id],$this->page(['category'=>$this->category->id,'sort'=>'price_asc'])->viewData('products')->pluck('id')->all());
        $this->page(['q'=>'%'])->assertOk()->assertSee('No products match your filters.')->assertSee('Clear filters');
        $this->page(['category'=>999999])->assertOk()->assertSee('No products match your filters.');
    }

    public function test_variant_min_price_sort_and_count_exclude_inactive_and_deleted_variants(): void
    {
        $product=$this->product('Variable basket',[],['price_minor'=>400]);
        $product->variants()->create(['sku'=>'VAR-2','name'=>'Large','price_minor'=>800,'stock'=>2,'is_active'=>true]);
        $product->variants()->create(['sku'=>'VAR-3','name'=>'Hidden','price_minor'=>1,'stock'=>4,'is_active'=>false]);
        $gone=$product->variants()->create(['sku'=>'VAR-4','name'=>'Deleted','price_minor'=>2,'stock'=>4,'is_active'=>true]);$gone->delete();
        $page=$this->page()->assertOk()->assertSee('From')->assertSee('?4.00')->assertSee('2 variants')->assertDontSee('?0.01')->assertDontSee('SALE');
        $this->assertEquals(400,$page->viewData('products')->first()->storefront_price);
    }

    public function test_twelve_per_page_deterministic_newest_sort_and_filter_persistence(): void
    {
        for($i=1;$i<=14;$i++)$this->product('Basket '.$i,['created_at'=>now()->subDays(15-$i)]);
        $page=$this->page(['q'=>'Basket','category'=>$this->category->id,'sort'=>'newest'])->assertOk();
        $products=$page->viewData('products');$this->assertCount(12,$products);$this->assertSame('Basket 14',$products->first()->name);
        $url=$products->nextPageUrl();$this->assertStringContainsString('q=Basket',$url);$this->assertStringContainsString('category='.$this->category->id,$url);$this->assertStringContainsString('sort=newest',$url);$this->assertStringEndsWith('#products',$url);
        $this->assertCount(2,$this->page(['q'=>'Basket','page'=>2])->viewData('products'));
        $this->assertStringContainsString('href="'.route('buyer.stores.show',$this->shop).'#products"',$page->getContent());
    }

    public function test_product_relationship_queries_do_not_grow_with_card_count_and_invalid_filters_are_rejected(): void
    {
        $this->product('One basket');
        DB::enableQueryLog();DB::flushQueryLog();
        $this->page()->assertOk();$single=count(DB::getQueryLog());
        for($i=2;$i<=12;$i++)$this->product('Basket '.$i);
        DB::flushQueryLog();$this->page()->assertOk();$full=count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($single,$full,'Product cards must not issue per-card queries.');
        $this->assertLessThan(25,$full);
        $this->getJson(route('buyer.stores.show',['seller'=>$this->shop->slug,'q'=>['invalid']]))->assertUnprocessable();
        $this->getJson(route('buyer.stores.show',['seller'=>$this->shop->slug,'sort'=>'unsupported']))->assertUnprocessable();
    }

    public function test_public_reviews_aggregates_replies_and_privacy(): void
    {
        // Review creation permissions remain in their existing controller; fixtures are memory-only.
        $buyer=User::factory()->create(['name'=>'PRIVATE BUYER NAME','email'=>'private@example.test']);
        $order=\App\Models\Order::create(['buyer_id'=>$buyer->id,'order_number'=>'STOREFRONT-REVIEW','status'=>'completed','payment_method'=>'cod','payment_status'=>'PAID','subtotal'=>100,'shipping_fee'=>0,'discount_amount'=>0,'total_amount'=>100,'recipient_name'=>'Buyer','recipient_phone'=>'09123456789','province'=>'Province','municipality'=>'City','barangay'=>'Barangay','street_address'=>'PRIVATE STREET']);
        $sellerOrder=\App\Models\SellerOrder::create(['order_id'=>$order->id,'seller_id'=>$this->shop->id,'subtotal_minor'=>10000,'status'=>'completed','workflow_status'=>\App\Enums\OrderStatus::Completed,'completed_at'=>now()]);
        foreach([[5,'Genuine public review',null],[1,'HIDDEN BODY',now()]] as [$rating,$body,$hidden]) {
            TransactionReview::forceCreate(['kind'=>'seller','eligibility_key'=>'storefront-'.$rating,'seller_order_id'=>$sellerOrder->id,'seller_id'=>$this->shop->id,'buyer_id'=>$buyer->id,'rating'=>$rating,'body'=>$body,'hidden_at'=>$hidden,'seller_response'=>'Thank you for shopping.']);
        }
        $page=$this->page()->assertOk()->assertSee('Genuine public review')->assertSee('Thank you for shopping.')->assertSee('5.0')
            ->assertDontSee('HIDDEN BODY')->assertDontSee('PRIVATE BUYER NAME')->assertDontSee('private@example.test');
        $this->assertSame(1,$page->viewData('seller')->visible_reviews_count);
    }

    public function test_fallbacks_empty_states_and_no_private_details_or_fake_metrics(): void
    {
        $this->shop->update(['logo_path'=>null,'banner_path'=>null,'description'=>null]);
        $this->page()->assertOk()->assertSee('No published products available.')->assertSee('No reviews yet.')
            ->assertSee('This store has not added a description yet.')->assertDontSee($this->shop->owner->email)->assertDontSee('Response rate')->assertDontSee('sold');
    }

    public function test_follow_and_inquiry_controls_use_existing_authorized_routes_and_owner_has_no_edit_on_public_page(): void
    {
        $product=$this->product();$buyer=User::factory()->create(['role'=>'buyer','status'=>'active']);
        $this->actingAs($buyer);$this->page()->assertOk()->assertSee('Follow store')->assertSee(route('buyer.messages.context.product.store',$product),false);
        $route=app('router')->getRoutes()->getByName('buyer.messages.context.product.store');
        foreach(['auth','not_suspended','approved','role:buyer','throttle:20,1'] as $middleware)$this->assertContains($middleware,$route->gatherMiddleware());
        $this->actingAs($this->shop->owner);$this->page()->assertOk()->assertDontSee('Edit Store Profile')->assertDontSee('Chat about this product')->assertDontSee('Follow store');
    }
}
