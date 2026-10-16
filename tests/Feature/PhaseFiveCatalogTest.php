<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseFiveCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_catalog_filters_visible_products_across_shared_category_and_sorts(): void
    {
        $root=Category::create(['name'=>'Apparel','slug'=>'apparel','is_active'=>true]); $child=Category::create(['name'=>'Tops','slug'=>'tops','parent_id'=>$root->id,'is_active'=>true]);
        $one=$this->seller('one'); $two=$this->seller('two');
        $this->product($one,$child,'Blue Top',2000,3,'APPROVED'); $this->product($two,$child,'Red Top',1000,2,'APPROVED'); $this->product($one,$child,'Hidden Top',500,5,'PENDING');
        $catalog=app(ProductCatalogService::class);
        $page=$catalog->marketplacePage(['q'=>'Top','category'=>$root->id,'sort'=>'price_asc','in_stock'=>'1']);
        $this->assertSame(2,$page->total());
        $this->assertSame('Red Top',$page->items()[0]['name']);
        $this->assertSame('Blue Top',$page->items()[1]['name']);
    }

    private function seller(string $suffix): Seller
    {
        $user=User::factory()->create(['role'=>'seller','status'=>'active']); $user->roles()->syncWithoutDetaching([Role::firstOrCreate(['name'=>'seller'])->id]);
        return Seller::create(['user_id'=>$user->id,'name'=>$suffix,'slug'=>$suffix.'-'.$user->id,'status'=>'approved']);
    }

    private function product(Seller $seller, Category $category, string $name, int $price, int $stock, string $status): void
    {
        $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>$name,'slug'=>strtolower(str_replace(' ','-',$name)).'-'.$seller->id.'-'.$status,'is_active'=>true,'moderation_status'=>$status]);
        ProductVariant::create(['product_id'=>$product->id,'sku'=>uniqid('sku',true),'name'=>'Default','price_minor'=>$price,'stock'=>$stock,'is_active'=>true]);
    }
}
