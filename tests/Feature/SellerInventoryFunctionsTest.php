<?php
namespace Tests\Feature;

use App\Models\{Category, Product, ProductVariant, Role, Seller, User};
use App\Services\SellerInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerInventoryFunctionsTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private Seller $seller;
    private Category $category;
    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Memory-only inventory fixtures required.');
        parent::setUp();
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->owner=User::factory()->create(['role'=>'seller','status'=>'active','is_suspended'=>false]);
        $this->owner->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'seller'])->id]);
        $this->seller=Seller::create(['user_id'=>$this->owner->id,'name'=>'Owned Inventory Shop','slug'=>'inventory-shop','status'=>'approved']);
        $this->category=Category::create(['name'=>'Everyday','slug'=>'everyday','is_active'=>true]);
        $this->actingAs($this->owner);
    }
    private function variant(string $name='Basket',int $stock=10,array $product=[],array $variant=[]): ProductVariant
    {
        $record=Product::create(array_replace(['seller_id'=>$this->seller->id,'category_id'=>$this->category->id,'name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name),'description'=>'Real inventory fixture','is_active'=>true,'moderation_status'=>'APPROVED'],$product));
        return $record->variants()->create(array_replace(['sku'=>'SKU-'.$record->id,'name'=>'Default','price_minor'=>12550,'stock'=>$stock,'is_active'=>true],$variant));
    }
    private function page(array $filters=[]){return $this->get(route('seller.inventory',$filters));}
    private function set(ProductVariant $variant,$quantity,$expected){return $this->from(route('seller.inventory'))->put(route('seller.inventory.set',$variant),['stock'=>$quantity,'expected_stock'=>$expected]);}

    public function test_summary_counts_owned_products_once_and_uses_active_variant_totals_with_existing_thresholds(): void
    {
        $first=$this->variant('Multi',5);
        $first->product->variants()->create(['sku'=>'SECOND','name'=>'Large','price_minor'=>500,'stock'=>8,'is_active'=>true]);
        $this->variant('Low',10);$this->variant('Empty',0);$this->variant('Inactive variant',90,[],['is_active'=>false]);
        $other=Seller::create(['user_id'=>$this->owner->id,'name'=>'Other','slug'=>'other','status'=>'approved']);
        $this->variant('FOREIGN PRODUCT',999,['seller_id'=>$other->id]);
        $page=$this->page()->assertOk()->assertDontSee('FOREIGN PRODUCT')->assertSee('Low Stock')->assertSee('In Stock')->assertSee('Out of Stock');
        $this->assertSame(['total'=>4,'units'=>23,'in_stock'=>1,'low_stock'=>1,'out_of_stock'=>2],$page->viewData('inventorySummary'));
        $this->assertCount(5,$page->viewData('products'));
        if($dir=getenv('INVENTORY_RENDER_DIR'))file_put_contents($dir.'/inventory.html',$page->getContent());
    }

    public function test_literal_name_and_sku_search_category_and_stock_filters_work_together_and_clear_is_canonical(): void
    {
        $wanted=$this->variant('Towel 0',10,[],['sku'=>'MATCH_%']);
        $this->variant('Other towel',11,[],['sku'=>'MATCH-ANY']);
        $category=Category::create(['name'=>'Kitchen','slug'=>'kitchen','is_active'=>true]);
        $this->variant('Kitchen towel',0,['category_id'=>$category->id]);
        $page=$this->page(['search'=>'match_%','category'=>$this->category->id,'stock'=>'low-stock'])->assertOk();
        $this->assertSame([$wanted->id],array_column($page->viewData('products'),'variant_id'));
        $this->assertCount(1,$this->page(['search'=>'TOWEL 0'])->viewData('products'));
        $this->assertCount(1,$this->page(['stock'=>'in-stock'])->viewData('products'));
        $this->assertCount(1,$this->page(['stock'=>'out-of-stock'])->viewData('products'));
        $this->assertCount(1,$this->page(['category'=>$category->id])->viewData('products'));
        $this->page(['search'=>'does-not-exist'])->assertOk()->assertSee('No matching inventory')->assertSee('href="'.route('seller.inventory').'"',false);
        $this->assertCount(3,$this->page()->viewData('products'));
    }

    public function test_save_is_absolute_and_only_changes_selected_variant_and_replayed_or_stale_saves_do_not_overwrite(): void
    {
        $variant=$this->variant('Multi',10);$other=$variant->product->variants()->create(['sku'=>'OTHER','name'=>'Other','stock'=>7,'price_minor'=>100,'is_active'=>true]);
        $this->set($variant,12,10)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(12,$variant->fresh()->stock);$this->assertSame(7,$other->fresh()->stock);
        $this->get(route('seller.inventory')); // Consume confirmed success flash.
        $this->set($variant,4,10)->assertSessionHasErrors('stock')->assertSessionMissing('success');
        $this->assertSame(12,$variant->fresh()->stock);
        $this->page()->assertSee('Stock changed since this page was loaded.')->assertSee('Stock was not saved.');
        $this->set($variant,0,12)->assertSessionHasNoErrors();$this->assertSame(0,$variant->fresh()->stock);
    }

    public function test_checkout_or_relative_adjustment_after_page_load_causes_absolute_save_conflict(): void
    {
        $variant=$this->variant('Concurrent',10);
        app(SellerInventoryService::class)->decrease($this->seller,$variant->id,3);
        $this->set($variant,15,10)->assertSessionHasErrors('stock');$this->assertSame(7,$variant->fresh()->stock);
        $this->post(route('seller.inventory.increase',$variant))->assertSessionHasNoErrors();$this->assertSame(8,$variant->fresh()->stock);
        $this->post(route('seller.inventory.decrease',$variant))->assertSessionHasNoErrors();$this->assertSame(7,$variant->fresh()->stock);
    }

    public function test_invalid_quantities_missing_snapshot_and_maximum_overflow_are_rejected(): void
    {
        $variant=$this->variant();
        foreach([-1,1.5,'bad',2147483648,null] as $quantity){$this->set($variant,$quantity,10)->assertSessionHasErrors('stock');$this->assertSame(10,$variant->fresh()->stock);}
        $this->put(route('seller.inventory.set',$variant),['stock'=>2])->assertSessionHasErrors('expected_stock');
        $this->set($variant,SellerInventoryService::MAX_STOCK,10)->assertSessionHasNoErrors();
        $this->post(route('seller.inventory.increase',$variant))->assertSessionHasErrors('stock');
        $this->assertSame(SellerInventoryService::MAX_STOCK,$variant->fresh()->stock);
    }

    public function test_cross_seller_roles_deleted_variants_csrf_and_route_middleware_remain_protected(): void
    {
        $variant=$this->variant();$otherOwner=User::factory()->create(['role'=>'seller','status'=>'active']);
        Seller::create(['user_id'=>$otherOwner->id,'name'=>'Other','slug'=>'foreign','status'=>'approved']);
        $this->actingAs($otherOwner);$this->set($variant,2,10)->assertNotFound();$this->page()->assertDontSee('Basket');
        foreach(['buyer','admin','rider','logistics'] as $role){$this->actingAs(User::factory()->create(['role'=>$role,'status'=>'active']));$this->set($variant,2,10)->assertForbidden();$this->page()->assertForbidden();}
        $this->actingAs($this->owner);$variant->delete();$this->set($variant,2,10)->assertNotFound();
        foreach(['web','auth','not_suspended','approved','role:seller'] as $middleware)$this->assertContains($middleware,app('router')->getRoutes()->getByName('seller.inventory.set')->gatherMiddleware());
        $this->app['env']='production';$this->put(route('seller.inventory.set',$variant),['stock'=>2,'expected_stock'=>10])->assertStatus(419);
    }

    public function test_valid_image_urls_missing_image_placeholder_and_no_session_data_leaks_into_canonical_inventory(): void
    {
        $disk=\Illuminate\Support\Facades\Storage::fake('public');
        $this->assertStringStartsWith(strtolower(str_replace('\\','/',sys_get_temp_dir())).'/',strtolower(str_replace('\\','/',$disk->path(''))));
        // A persisted image path is valid only when the file also exists.
        $disk->put('products/saved.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII='));
        $variant=$this->variant();$variant->product->images()->create(['path'=>'products/saved.png','position'=>0]);
        $this->page()->assertSee(\Illuminate\Support\Facades\Storage::disk('public')->url('products/saved.png'),false)->assertSee('data-inventory-placeholder',false)->assertSee('data-inventory-image',false);
        $variant->product->delete();
        $this->withSession(['seller_products'=>['fake'=>['name'=>'SESSION POISON','stock'=>999]]]);
        $page=$this->page()->assertOk()->assertSee('No inventory yet')->assertDontSee('SESSION POISON');
        $this->assertSame(0,$page->viewData('inventorySummary')['total']);
    }

    public function test_stock_update_is_reflected_in_product_presenter_storefront_and_buyer_detail_without_a_second_stock_source(): void
    {
        $variant=$this->variant('Synced',0);$product=$variant->product;
        $this->get(route('buyer.stores.show',$this->seller))->assertDontSee('Synced');
        $this->set($variant,9,0)->assertSessionHasNoErrors();
        $this->assertSame(9,$this->page()->viewData('products')[0]['stock']);
        $this->get(route('buyer.stores.show',$this->seller))->assertSee('Synced');
        $catalog=app(\App\Services\ProductCatalogService::class)->marketplaceProduct($product->slug);$this->assertSame(9,$catalog['variants'][0]['stock']);
        $this->get(route('buyer.product',$product->slug))->assertOk();
        $this->get(route('seller.products'))->assertOk()->assertSee('Synced')->assertSee('Low stock');
    }

    public function test_rendered_inventory_has_no_raw_blade_and_aggregate_status_uses_all_active_variants_even_when_filtered(): void
    {
        $variant=$this->variant('Multi',5,[],['sku'=>'FILTERED-SMALL']);
        $variant->product->variants()->create(['sku'=>'OTHER-LARGE','name'=>'Large','price_minor'=>100,'stock'=>8,'is_active'=>true]);
        $page=$this->page(['search'=>'FILTERED-SMALL','stock'=>'low-stock'])->assertOk();
        $html=$page->getContent();
        foreach(['@if','@endif','@else','@include','@php','@disabled','{{'] as $fragment)$this->assertStringNotContainsString($fragment,$html);
        $this->assertSame(13,$page->viewData('products')[0]['product_stock']);
        $this->assertSame(5,$page->viewData('products')[0]['stock']);
        preg_match('/<tbody id="inventoryTable">([\s\S]*?)<\/tbody>/',$html,$table);
        $this->assertStringContainsString('Aggregate Stock Status: </span>In Stock',$table[1]);
        $this->assertStringNotContainsString('Aggregate Stock Status: </span>Low Stock',$table[1]);
        $this->assertStringContainsString('Product total: 13 active units',$table[1]);
        $this->assertStringContainsString('name="expected_stock" value="5"',$table[1]);
        $this->assertStringContainsString(route('seller.products.edit',$variant->product_id),$table[1]);
        $this->assertSame(8,preg_match_all('/<th\b/',$html));
        $this->assertStringContainsString('overflow-x-auto',$html);
    }

    public function test_aggregate_boundaries_inactive_stock_and_real_variant_timestamp_display_in_manila(): void
    {
        foreach([0=>'Out of Stock',1=>'Low Stock',10=>'Low Stock',11=>'In Stock'] as $quantity=>$label) {
            $variant=$this->variant('Boundary '.$quantity,$quantity);
            $page=$this->page(['search'=>'Boundary '.$quantity])->assertOk();
            preg_match('/<tbody id="inventoryTable">([\s\S]*?)<\/tbody>/',$page->getContent(),$table);
            $this->assertStringContainsString('Aggregate Stock Status: </span>'.$label,$table[1]);
        }
        $inactive=$this->variant('Inactive',99,['is_active'=>false],['is_active'=>false]);
        DB::table('product_variants')->where('id',$inactive->id)->update(['updated_at'=>'2026-11-01 18:45:00']);
        DB::table('products')->where('id',$inactive->product_id)->update(['updated_at'=>'2020-01-01 00:00:00']);
        $page=$this->page(['search'=>'Inactive'])->assertOk()->assertSee('Inactive listing/variation')
            ->assertSee('Nov 2, 2026')->assertSee('2:45 AM')->assertSee('PHT')
            ->assertSee('datetime="2026-11-02T02:45:00+08:00"',false)
            ->assertSee('General variation record update; includes stock and other edits.')
            ->assertDontSee('@if')->assertDontSee('@endif');
        $this->assertSame(99,$page->viewData('products')[0]['stock']);
        $this->assertSame(0,$page->viewData('products')[0]['product_stock']);
        $this->assertSame('Asia/Manila',$page->viewData('products')[0]['updated_at']->timezoneName);
        preg_match('/<tbody id="inventoryTable">([\s\S]*?)<\/tbody>/',$page->getContent(),$table);
        $this->assertStringContainsString('Aggregate Stock Status: </span>Out of Stock',$table[1]);
        DB::table('product_variants')->where('id',$inactive->id)->update(['updated_at'=>null]);
        $this->page(['search'=>'Inactive'])->assertSee('Not recorded');
        $this->variant('Paused listing',15,['is_active'=>false]);
        $paused=$this->page(['search'=>'Paused listing'])->assertOk()->assertSee('Inactive listing/variation');
        $this->assertSame(15,$paused->viewData('products')[0]['product_stock']);
        preg_match('/<tbody id="inventoryTable">([\s\S]*?)<\/tbody>/',$paused->getContent(),$table);
        $this->assertStringContainsString('Aggregate Stock Status: </span>In Stock',$table[1]);
        if($dir=getenv('INVENTORY_RENDER_DIR'))file_put_contents($dir.'/inventory-followup.html',$page->getContent());
    }
}
