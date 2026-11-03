<?php

namespace Tests\Feature;

use App\Enums\ProductModerationStatus;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Minimal in-memory fixture schema; application migrations are never invoked. */
class SellerProductFormsTest extends TestCase
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
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, brand TEXT, description TEXT, shipping_options TEXT, is_active INTEGER, moderation_status TEXT, moderation_reason TEXT, moderated_by INTEGER, moderated_at TEXT, video_path TEXT, video_mime TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_variants'=>'id INTEGER PRIMARY KEY, image_path TEXT, product_id INTEGER, sku TEXT UNIQUE, weight_grams INTEGER, length_mm INTEGER, width_mm INTEGER, height_mm INTEGER, name TEXT, options TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_images'=>'id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT, position INTEGER, color TEXT, created_at TEXT, updated_at TEXT',
            'product_moderation_events'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, actor_id INTEGER, previous_status TEXT, new_status TEXT, reason TEXT, source TEXT, metadata TEXT, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT',
            'orders'=>'id INTEGER PRIMARY KEY, status TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_order_id INTEGER, quantity INTEGER',
        ];
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('adminsAfterCommit', 'afterCommit')->andReturnNull();
        foreach($schema as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        \Tests\Support\ShopVoucherSchema::create();
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

    private function universal(array $groups = [], array $overrides = []): array
    {
        $token = $this->actingAs($this->seller)->get(route('seller.products.create'))->viewData('creationToken');
        $combinations = [[]];
        foreach ($groups as $group) {
            $next = [];
            foreach ($combinations as $combination) {
                foreach (array_values(array_unique(array_filter(array_map('trim', explode(',', $group['values']))))) as $value) $next[] = $combination + [trim($group['name']) => $value];
            }
            $combinations = $next;
        }
        $variants = [];
        if ($groups) foreach ($combinations as $index => $options) $variants[] = ['options'=>$options,'price'=>number_format(35 + $index * 10,2,'.',''),'stock'=>50-$index,'sku'=>'','weight'=>''];
        return array_replace(['name'=>'Universal Product','description'=>'Suitable for any approved category.','category_id'=>2,'status'=>'active','product_mode'=>$groups?'variants':'simple','creation_token'=>$token,'price'=>'50.00','stock'=>100,'weight'=>'0.500','option_groups'=>$groups,'variants'=>$variants],$overrides);
    }

    public function test_simple_products_support_authorized_categories_and_repeat_form_submission_is_idempotent(): void
    {
        foreach ([2,3] as $category) {
            $payload = $this->universal([],['category_id'=>$category,'name'=>'Simple '.$category]);
            $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
            $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        }
        $this->assertSame(2,Product::count());
        foreach (Product::with('variants')->get() as $product) {
            $variant=$product->variants->sole();
            $this->assertNull($variant->options);
            $this->assertSame(5000,$variant->price_minor);
            $this->assertSame(100,$variant->stock);
            $this->assertSame(500,$variant->weight_grams);
            $this->assertSame('PENDING',$product->moderation_status->value);
        }
    }

    public function test_custom_single_two_and_three_groups_persist_exact_combinations_without_an_extra_default(): void
    {
        foreach ([
            [['name'=>'Scent','values'=>'Citrus, Lavender']],
            [['name'=>'Flavor','values'=>'Chocolate, Vanilla'],['name'=>'Pack Size','values'=>'Single, Pack of 6']],
            [['name'=>'Storage','values'=>'128 GB, 256 GB'],['name'=>'Capacity','values'=>'Standard, Extended'],['name'=>'Material','values'=>'Steel']],
        ] as $groups) {
            $payload=$this->universal($groups);
            $payload['variants'][0]['weight']='0.125';
            $payload['variants'][0]['sku']='CUSTOM-'.Product::count();
            $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
            $product=Product::latest('id')->with('variants')->first();
            $this->assertCount(count($payload['variants']),$product->variants);
            foreach($product->variants as $index=>$variant) {
                $this->assertSame($payload['variants'][$index]['options'],$variant->options);
                $this->assertSame((int)round((float)$payload['variants'][$index]['price']*100),$variant->price_minor);
                $this->assertSame($payload['variants'][$index]['stock'],$variant->stock);
                $this->assertSame($index===0?125:500,$variant->weight_grams);
            }
            $this->assertSame(count($product->variants),$product->variants->pluck('sku')->unique()->count());
        }
    }

    public function test_fifty_combinations_are_supported_and_inactive_products_remain_unpublished(): void
    {
        $payload=$this->universal([['name'=>'Model','values'=>implode(',',range(1,10))],['name'=>'Pack Quantity','values'=>'1,2,3,4,5']],['status'=>'inactive']);
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $product=Product::with('variants')->sole();
        $this->assertCount(50,$product->variants);
        $this->assertFalse($product->is_active);
        $this->assertSame(0,$product->variants->where('is_active',true)->count());
        $this->assertNull(app(ProductCatalogService::class)->marketplaceProduct($product->slug));
    }

    public function test_group_normalization_ignores_empty_and_duplicate_values_but_rejects_duplicate_names_and_mismatched_combinations(): void
    {
        $payload=$this->universal([['name'=>'Flavor','values'=>'Chocolate, Vanilla']]);
        $payload['option_groups']=[['name'=>' Flavor ','values'=>' Chocolate, ,chocolate, Vanilla, VANILLA, ']];
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $this->assertSame(2,DB::table('product_variants')->count());
        $base=$this->universal([['name'=>'Flavor','values'=>'Chocolate, Vanilla']]);
        foreach ([
            ['option_groups'=>[['name'=>'Flavor','values'=>'Chocolate'],['name'=>' flavor ','values'=>'Vanilla']]],
            ['option_groups'=>[['name'=>'','values'=>'Chocolate']]],
            ['option_groups'=>[['name'=>'Flavor','values'=>' , , ']]],
            ['variants'=>[$base['variants'][0],$base['variants'][0]]],
            ['variants'=>[$base['variants'][0]]],
            ['variants'=>[['options'=>['Storage'=>'Unknown'],'price'=>50,'stock'=>1]]],
            ['product_mode'=>'simple'],
        ] as $invalid) {
            $this->post(route('seller.products.store'),array_replace($base,$invalid))->assertSessionHasErrors();
            $this->assertSame(1,Product::count());
        }
    }

    public function test_server_enforces_limits_stock_money_weight_sku_ownership_and_category_even_for_forged_payloads(): void
    {
        $base=$this->universal([['name'=>'Storage','values'=>'128 GB, 256 GB']]);
        foreach ([
            ['category_id'=>5], ['category_id'=>1], ['status'=>'draft'], ['creation_token'=>str_repeat('x',40)],
            ['option_groups'=>array_fill(0,4,['name'=>'Option','values'=>'One'])],
            ['option_groups'=>[['name'=>'Flavor','values'=>implode(',',range(1,21))]]],
            ['option_groups'=>[['name'=>'Flavor','values'=>implode(',',range(1,8))],['name'=>'Pack','values'=>implode(',',range(1,7))]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>-1,'stock'=>1]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>'1.001','stock'=>1]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>50,'stock'=>1.5]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>50,'stock'=>2147483648]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>50,'stock'=>1,'weight'=>-1]]],
            ['variants'=>[['options'=>['Storage'=>'128 GB'],'price'=>50,'stock'=>1,'weight'=>'0.0001']]],
            ['variants'=>[['id'=>999,'options'=>['Storage'=>'128 GB'],'price'=>50,'stock'=>1]]],
        ] as $invalid) {
            $this->post(route('seller.products.store'),array_replace($base,$invalid))->assertSessionHasErrors();
            $this->assertSame(0,Product::count());
        }
        $this->product(1,202);
        $base['variants'][0]['sku']='SKU-1';
        $this->post(route('seller.products.store'),$base)->assertSessionHasErrors('sku');
        $this->assertSame(1,Product::count());
        $this->assertSame(1,DB::table('product_variants')->count());
        $base['variants'][0]['sku']='DUPLICATE';$base['variants'][1]['sku']='DUPLICATE';
        $this->post(route('seller.products.store'),$base)->assertSessionHasErrors();
        $this->actingAs(User::find(22))->put(route('seller.products.update',1),$this->payload(['category_id'=>2]))->assertSessionHasErrors('category_id');
        $this->actingAs($this->seller)->put(route('seller.products.update',1),$this->payload())->assertNotFound();
    }

    public function test_custom_options_survive_edit_and_inventory_updates_without_deleting_variant_history(): void
    {
        $payload=$this->universal([['name'=>'Flavor','values'=>'Chocolate, Vanilla']]);
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $product=Product::with('variants')->sole();
        $first=$product->variants[0];$second=$product->variants[1];
        $this->get(route('seller.products.edit',$product))->assertOk()->assertSee('options][Flavor]',false)->assertSee('Vanilla');
        $edit=$this->payload(['name'=>$product->name,'description'=>$product->description,'price'=>35,'stock'=>20,'sku'=>$first->sku,'weight'=>'0.500','variation_name'=>['Flavor'],'variation_value'=>['Chocolate'],'variants'=>[
            ['id'=>$second->id,'name'=>$second->name,'sku'=>$second->sku,'price'=>45,'stock'=>15,'options'=>['Flavor'=>'Vanilla'],'weight'=>'0.750'],
        ]]);
        $this->put(route('seller.products.update',$product),$edit)->assertSessionHasNoErrors();
        $this->assertSame([$first->id,$second->id],$product->variants()->orderBy('id')->pluck('id')->all());
        $this->assertSame(['Flavor'=>'Vanilla'],$second->fresh()->options);
        $this->assertSame(750,$second->fresh()->weight_grams);
        app(\App\Services\SellerInventoryService::class)->setStock($product->seller,$second->id,9);
        $this->assertSame(9,$second->fresh()->stock);
        $this->assertSame(20,$first->fresh()->stock);
        // Omitted saved variants remain intact, retaining all order references.
        unset($edit['variants']);
        $this->put(route('seller.products.update',$product),$edit)->assertSessionHasNoErrors();
        $this->assertSame(2,$product->variants()->count());
        $this->assertSame(9,$second->fresh()->stock);
    }

    public function test_validation_rerenders_option_groups_variant_fields_and_feedback_and_keeps_images_product_level(): void
    {
        $payload=$this->universal([['name'=>'Material','values'=>'Wood, Steel']]);
        $payload['name']='';
        $this->from(route('seller.products.create'))->post(route('seller.products.store'),$payload)->assertSessionHasErrors('name');
        $page=$this->get(route('seller.products.create'))->assertOk()->assertSee('Material')->assertSee('Wood, Steel')->assertSee('Generate Variant Combinations')->assertDontSee('Save as Draft');
        if($dir=getenv('SELLER_FORM_CHECK_DIR'))file_put_contents($dir.'/create-options.html',$page->getContent());
        $payload['name']='Photos';
        $root=sys_get_temp_dir().'/suki-universal-photos-'.bin2hex(random_bytes(6));
        config(['filesystems.disks.public.root'=>$root]);
        $payload['images']=[\Illuminate\Http\UploadedFile::fake()->createWithContent('product.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))];
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('product_images')->count());
        $this->assertFileExists($root.'/'.DB::table('product_images')->value('path'));
    }

    public function test_created_custom_variants_flow_through_moderation_storefront_cart_checkout_and_inventory(): void
    {
        // Extend this test's guarded, in-memory fixture schema. No migrations.
        foreach ([\App\Models\Order::class,\App\Models\OrderItem::class,\App\Models\SellerOrder::class,\App\Models\Shipment::class,\App\Models\CartItem::class,\App\Models\Address::class] as $modelClass) {
            $model=new $modelClass();$table=$model->getTable();
            $exists=\Illuminate\Support\Facades\Schema::hasTable($table);
            if(!$exists)DB::statement('CREATE TABLE '.$table.' (id INTEGER PRIMARY KEY)');
            foreach(array_unique([...$model->getFillable(),'created_at','updated_at']) as $column) {
                if(\Illuminate\Support\Facades\Schema::hasColumn($table,$column))continue;
                $integer=str_ends_with($column,'_id')||str_ends_with($column,'_minor')||in_array($column,['quantity','selected','is_default','cod_collected','attempts'],true);
                DB::statement('ALTER TABLE '.$table.' ADD COLUMN '.$column.' '.($integer?'INTEGER':'TEXT'));
            }
        }
        DB::statement('CREATE UNIQUE INDEX cart_variant_identity ON cart_items(user_id,product_variant_id)');
        DB::statement('CREATE TABLE IF NOT EXISTS buyer_store_follows (id INTEGER PRIMARY KEY,buyer_id INTEGER,seller_id INTEGER,created_at TEXT,updated_at TEXT)');
        DB::statement('CREATE TABLE wishlist_items (id INTEGER PRIMARY KEY,user_id INTEGER,product_slug TEXT)');
        foreach(['created_at','updated_at','body','seller_response'] as $column)DB::statement('ALTER TABLE transaction_reviews ADD COLUMN '.$column.' TEXT');
        $payload=$this->universal([['name'=>'Flavor','values'=>'Chocolate, Vanilla'],['name'=>'Pack Size','values'=>'Single, Pack of 6']]);
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $product=Product::with('variants')->sole();
        $buyer=User::forceCreate(['id'=>33,'name'=>'Buyer','role'=>'buyer','status'=>'active']);
        $admin=User::forceCreate(['id'=>44,'name'=>'Admin','role'=>'admin','status'=>'active']);
        $this->actingAs($buyer)->get(route('buyer.product',$product->slug))->assertNotFound();
        app(\App\Services\Admin\ProductModerationService::class)->approve($product,$admin);
        $this->get(route('buyer.product',$product->slug))->assertOk()->assertSee('Flavor')->assertSee('Pack Size')->assertSee('Pack of 6');
        $this->get(route('buyer.stores.show',$product->seller->slug))->assertOk()->assertSee($product->name);
        $first=$product->variants[0];$last=$product->variants[3];
        $this->post(route('buyer.cart.add',$product->slug),['variant_id'=>$first->id,'quantity'=>1])->assertSessionHasNoErrors();
        $this->post(route('buyer.cart.add',$product->slug),['variant_id'=>$last->id,'quantity'=>2])->assertSessionHasNoErrors();
        $summary=app(\App\Services\CheckoutService::class)->cartSummary($buyer);
        $this->assertCount(2,$summary['cart']);
        $this->assertSame(165,$summary['subtotal']);
        $this->get(route('buyer.cart'))->assertOk()->assertSee('Flavor: Chocolate')->assertSee('Pack Size: Pack of 6');
        $this->get(route('buyer.checkout'))->assertOk()->assertViewHas('total',215);
        $order=app(\App\Services\CheckoutService::class)->checkout($buyer,['recipient_name'=>'Buyer','recipient_phone'=>'0912','province'=>'P','municipality'=>'C','barangay'=>'B','street_address'=>'Street'],'cod');
        $this->assertSame(21500,$order->total_minor);
        $this->assertSame([$first->id=>1,$last->id=>2],$order->items->pluck('quantity','product_variant_id')->all());
        $this->assertSame(['Flavor'=>'Vanilla','Pack Size'=>'Pack of 6'],$order->items->last()->variation);
        $this->assertSame(49,$first->fresh()->stock);
        $this->assertSame(45,$last->fresh()->stock);
        $this->assertSame(49,$product->variants[1]->fresh()->stock);
        $this->assertSame(21500,$order->sellerOrders->sole()->shipment->cod_amount_minor);
        // Omitted/linked variants survive editing with their stable IDs.
        $linkedId=$last->id;
        $this->actingAs($this->seller)->put(route('seller.products.update',$product),$this->payload([
            'name'=>$product->name,'description'=>$product->description,'price'=>35,'stock'=>49,'sku'=>$first->sku,
            'weight'=>'0.500','variation_name'=>['Flavor','Pack Size'],'variation_value'=>['Chocolate','Single'],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(4,$product->variants()->count());
        $this->assertSame($linkedId,$order->items()->orderByDesc('id')->first()->product_variant_id);
    }

    private function product(int $id, int $seller=101, bool $active=true, string $moderation='APPROVED', int $category=2): void
    {
        DB::table('products')->insert(['id'=>$id,'seller_id'=>$seller,'category_id'=>$category,'name'=>($seller===101?'Owned':'FOREIGN').' Product '.$id,'slug'=>'product-'.$id,'description'=>'Saved description','is_active'=>$active,'moderation_status'=>$moderation,'created_at'=>'2026-11-01 04:00:00','updated_at'=>'2026-11-01 04:00:00']);
        DB::table('product_variants')->insert(['id'=>$id*10+1,'product_id'=>$id,'sku'=>'SKU-'.$id,'name'=>'Default','options'=>'{"Color":"Blue"}','price_minor'=>10000,'stock'=>20,'is_active'=>1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name'=>'Test Product', 'description'=>'Useful description', 'category_id'=>2,
            'price'=>'100.25', 'stock'=>10, 'status'=>'active', 'sku'=>'',
            'variation_name'=>['Color'], 'variation_value'=>['Blue'],
        ], $overrides);
    }

    public function test_forms_render_with_real_manifest_actions_values_and_owned_images(): void
    {
        $this->product(1); $this->product(2, 202);
        DB::table('product_images')->insert(['id'=>1,'product_id'=>1,'path'=>'products/blue.webp','color'=>'Blue','position'=>0]);
        DB::table('product_variants')->insert(['id'=>12,'product_id'=>1,'sku'=>'EXISTING-RED','name'=>'Red','price_minor'=>10025,'stock'=>1,'is_active'=>1,'options'=>'{"Color":"Red"}']);
        $this->actingAs($this->seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('Basic Information')->assertSee('Product Photos')->assertSee('Package Details')
            ->assertSee('name="_token"', false)->assertSee('action="'.route('seller.products.store').'"',false)
            ->assertSee('Generated automatically after saving')->assertDontSee('name="shipping_options',false)
            ->assertSee('Allowed Category')->assertDontSee('Forbidden Category')->assertDontSee('Save as Draft');
        $edit=$this->get(route('seller.products.edit',1))->assertOk()
            ->assertSee('value="PUT"',false)->assertSee('action="'.route('seller.products.update',1).'"',false)
            ->assertSee('value="Owned Product 1"',false)->assertSee('products/blue.webp')->assertSee('<figcaption>Blue</figcaption>',false)
            ->assertSee('name="variants[0][id]" value="12"',false)->assertSee('value="EXISTING-RED"',false);
        $this->get(route('seller.products.edit',2))->assertNotFound();
        // Preserve rendered HTML outside the repository for JS/Blade verification.
        if ($dir=getenv('SELLER_FORM_CHECK_DIR')) file_put_contents($dir.'/edit.html',$edit->getContent());
    }

    public function test_simple_and_variable_products_get_server_generated_unique_skus(): void
    {
        $this->actingAs($this->seller)->post(route('seller.products.store'),$this->payload())
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        $this->post(route('seller.products.store'),$this->payload(['name'=>'Variable Product','variants'=>[
            ['name'=>'Red','sku'=>'','price'=>200,'stock'=>0,'options'=>['Color'=>'Red']],
            ['name'=>'Green','price'=>250,'stock'=>11,'options'=>['Color'=>'Green']],
        ]]))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        $skus=DB::table('product_variants')->orderBy('id')->pluck('sku');
        $this->assertCount(4,$skus); $this->assertCount(4,$skus->unique());
        foreach($skus as $sku) $this->assertMatchesRegularExpression('/^SUKI-\d+-[A-Z0-9]{6}$/',$sku);
        $this->assertSame([101],DB::table('products')->distinct()->pluck('seller_id')->all());
        $this->assertSame(['[]','[]'],DB::table('products')->pluck('shipping_options')->all());
        $this->assertSame(['PENDING','PENDING'],DB::table('products')->pluck('moderation_status')->all());
        $this->assertSame(2,DB::table('product_moderation_events')->count());
    }

    public function test_generator_skips_collisions_including_soft_deleted_skus(): void
    {
        $this->product(1);
        DB::table('product_variants')->where('id',11)->update(['sku'=>'SUKI-2-AAAAAA','deleted_at'=>now()]);
        $sequence=['AAAAAA','BBBBBB','BBBBBB','CCCCCC'];
        \Illuminate\Support\Str::createRandomStringsUsing(function ($length) use (&$sequence) {
            return $length === 6 ? array_shift($sequence) : substr(bin2hex(random_bytes($length)),0,$length);
        });
        try {
            $this->actingAs($this->seller)->post(route('seller.products.store'),$this->payload(['variants'=>[
                ['name'=>'Extra','price'=>100,'stock'=>1],
            ]]))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        } finally { \Illuminate\Support\Str::createRandomStringsNormally(); }
        $this->assertSame(['SUKI-2-BBBBBB','SUKI-2-CCCCCC'],DB::table('product_variants')->where('product_id',2)->pluck('sku')->all());
    }

    public function test_unique_constraint_race_rolls_back_and_returns_a_validation_error(): void
    {
        $this->product(1);
        // Force the database to reject a generated SKU after the existence probe.
        DB::statement("CREATE TRIGGER sku_race BEFORE INSERT ON product_variants BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: product_variants.sku'); END");
        $this->actingAs($this->seller)->from(route('seller.products.create'))
            ->post(route('seller.products.store'),$this->payload())->assertRedirect(route('seller.products.create'))->assertSessionHasErrors('sku');
        $this->assertSame(1,DB::table('products')->count());
        $this->assertSame(1,DB::table('product_variants')->count());
        $this->assertSame(0,DB::table('product_moderation_events')->count());
    }

    public function test_edit_preserves_skus_variant_ids_images_shipping_and_stock_only_approval(): void
    {
        $this->product(1);
        DB::table('products')->where('id',1)->update(['shipping_options'=>'["suki_rider"]']);
        DB::table('product_images')->insert(['id'=>1,'product_id'=>1,'path'=>'products/saved.webp','color'=>'Blue','position'=>0]);
        DB::table('product_variants')->insert(['id'=>12,'product_id'=>1,'sku'=>'RED-SKU','name'=>'Red','price_minor'=>20000,'stock'=>11,'is_active'=>1,'options'=>'{"Color":"Red"}']);
        $data=$this->payload(['name'=>'Owned Product 1','description'=>'Saved description','price'=>100,'stock'=>10,'variants'=>[
            ['id'=>12,'name'=>'Red','sku'=>'','price'=>200,'stock'=>1,'status'=>'active','options'=>['Color'=>'Red']],
        ]]);
        $this->actingAs($this->seller)->put(route('seller.products.update',1),$data)->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        $this->assertSame([11,12],DB::table('product_variants')->orderBy('id')->pluck('id')->all());
        $this->assertSame(['SKU-1','RED-SKU'],DB::table('product_variants')->orderBy('id')->pluck('sku')->all());
        $this->assertSame([10,1],DB::table('product_variants')->orderBy('id')->pluck('stock')->all());
        $this->assertSame('APPROVED',Product::find(1)->moderation_status->value);
        $this->assertSame(['suki_rider'],Product::find(1)->shipping_options);
        $this->assertSame('Blue',DB::table('product_images')->value('color'));
        $this->assertSame('products/saved.webp',DB::table('product_images')->value('path'));
        $data['price']=101;
        $this->put(route('seller.products.update',1),$data)->assertSessionHasNoErrors();
        $this->assertSame('PENDING',Product::find(1)->moderation_status->value);
        $this->assertSame('seller_material_edit',DB::table('product_moderation_events')->value('source'));
    }

    public function test_forged_shipping_category_and_foreign_variant_requests_cannot_write(): void
    {
        $this->product(1); $this->product(2,202);
        $this->actingAs($this->seller)->post(route('seller.products.store'),$this->payload(['shipping_options'=>['suki_rider']]))->assertSessionHasErrors('shipping_options');
        foreach([1,5,6] as $category) $this->post(route('seller.products.store'),$this->payload(['category_id'=>$category]))->assertSessionHasErrors('category_id');
        $this->put(route('seller.products.update',2),$this->payload())->assertNotFound();
        $this->put(route('seller.products.update',1),$this->payload(['shipping_options'=>['logistics_partner']]))->assertSessionHasErrors('shipping_options');
        $this->put(route('seller.products.update',1),$this->payload(['variants'=>[['id'=>21,'name'=>'Forged','price'=>1,'stock'=>0]]]))->assertNotFound();
        $this->assertSame('Owned Product 1',Product::find(1)->name);
        $this->assertSame(20,(int)DB::table('product_variants')->where('id',21)->value('stock'));
        $this->assertSame(2,DB::table('products')->count());
        $this->assertSame(0,DB::table('product_moderation_events')->count());
    }

    public function test_validation_restores_sparse_variants_and_their_ids_and_values(): void
    {
        $this->product(1);
        $data=$this->payload(['name'=>'','variants'=>[4=>['id'=>12,'name'=>'Keep Red','sku'=>'RED','price'=>200,'stock'=>10,'status'=>'inactive','options'=>['Color'=>'Red']]]]);
        $this->actingAs($this->seller)->from(route('seller.products.edit',1))->put(route('seller.products.update',1),$data)->assertSessionHasErrors('name')->assertRedirect(route('seller.products.edit',1));
        $this->get(route('seller.products.edit',1))->assertOk()->assertSee('Please review the following fields.')
            ->assertSee('name="variants[4][id]" value="12"',false)->assertSee('value="Keep Red"',false)->assertSee('value="Red"',false);
        $this->assertSame(1,DB::table('product_variants')->count());
    }

    public function test_effective_routes_keep_seller_security_middleware(): void
    {
        foreach(['create','store','edit','update'] as $action) {
            $route=app('router')->getRoutes()->getByName('seller.products.'.$action);
            $this->assertNotNull($route);
            foreach(['web','auth','not_suspended','approved','role:seller'] as $middleware) $this->assertContains($middleware,$route->gatherMiddleware());
        }
        $this->get(route('seller.products.create'))->assertRedirect(route('login'));
    }

    public function test_new_image_color_is_saved_without_replacing_existing_photos(): void
    {
        $this->product(1);
        DB::table('product_images')->insert(['id'=>1,'product_id'=>1,'path'=>'products/saved.webp','color'=>'Blue','position'=>0]);
        // A dedicated temporary public disk, never the application's upload disk.
        $root=sys_get_temp_dir().'/suki-form-images-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.public.root'=>$root]);
        $image=\Illuminate\Http\UploadedFile::fake()->createWithContent('red.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->actingAs($this->seller)->put(route('seller.products.update',1),$this->payload([
            'name'=>'Owned Product 1','description'=>'Saved description','price'=>100,
            'images'=>[$image],'image_colors'=>['Red'],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));
        $photos=DB::table('product_images')->orderBy('position')->get();
        $this->assertCount(2,$photos);
        $this->assertSame('products/saved.webp',$photos[0]->path);
        $this->assertSame(['Blue','Red'],$photos->pluck('color')->all());
        $this->assertFileExists($root.'/'.$photos[1]->path);
        $this->assertSame('PENDING',Product::find(1)->moderation_status->value);
    }

    public function test_universal_create_preserves_generated_variants_and_incoming_images_and_video_together(): void
    {
        $root=sys_get_temp_dir().'/suki-merged-create-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.public.root'=>$root]);
        $payload=$this->universal([['name'=>'Flavor','values'=>'Chocolate, Vanilla'],['name'=>'Pack Size','values'=>'Single, Pack of 6']]);
        $page=$this->get(route('seller.products.create'))->assertOk()->assertSee('Product Video')->assertSee('Generate Variant Combinations')->assertSee('20 JPG, PNG or WebP photos, 4 MB each');
        $this->assertSame(1,substr_count($page->getContent(),'id="video"'));
        $payload['variants'][0]['weight']='0.125';
        $payload['video']=\Illuminate\Http\UploadedFile::fake()->create('product.mp4',1024,'video/mp4');
        $payload['images']=[];
        for($index=0;$index<20;$index++)$payload['images'][]=\Illuminate\Http\UploadedFile::fake()->createWithContent('photo-'.$index.'.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $product=Product::with('variants','images')->sole();
        $this->assertCount(4,$product->variants);
        $this->assertCount(20,$product->images);
        $this->assertSame(125,$product->variants[0]->weight_grams);
        $this->assertSame(500,$product->variants[3]->weight_grams);
        $this->assertSame(['Flavor'=>'Vanilla','Pack Size'=>'Pack of 6'],$product->variants[3]->options);
        $this->assertSame('PENDING',$product->moderation_status->value);
        $this->assertSame('video/mp4',$product->video_mime);
        $this->assertFileExists($root.'/'.$product->video_path);
        $this->assertNull(app(ProductCatalogService::class)->marketplaceProduct($product->slug));
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $this->assertSame(1,Product::count());
        $this->assertCount(20,$product->fresh()->images);
        DB::table('products')->where('id',$product->id)->update(['moderation_status'=>'APPROVED']);
        $public=app(ProductCatalogService::class)->marketplaceProduct($product->slug);
        $this->assertCount(4,$public['variants']);
        $this->assertSame(['Chocolate','Vanilla'],$public['option_groups']['Flavor']);
        $this->assertSame(\Illuminate\Support\Facades\Storage::disk('public')->url($product->video_path),$public['video']);
        $this->get(route('seller.products.edit',$product))->assertOk()->assertSee('Remove saved product video')->assertSee('options][Flavor]',false);
    }

    public function test_universal_create_and_edit_keep_the_same_server_media_limits(): void
    {
        $base=$this->universal([['name'=>'Capacity','values'=>'Small, Large']]);
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $tooBig=\Illuminate\Http\UploadedFile::fake()->createWithContent('oversize.png',$png)->size(4097);
        $this->post(route('seller.products.store'),$base+['images'=>[$tooBig]])->assertSessionHasErrors('images.0');
        $this->post(route('seller.products.store'),$base+['images'=>array_fill(0,21,\Illuminate\Http\UploadedFile::fake()->createWithContent('small.png',$png))])->assertSessionHasErrors('images');
        $this->post(route('seller.products.store'),$base+['video'=>\Illuminate\Http\UploadedFile::fake()->create('bad.mov',1,'video/quicktime')])->assertSessionHasErrors('video');
        $this->post(route('seller.products.store'),$base+['video'=>\Illuminate\Http\UploadedFile::fake()->create('big.mp4',25601,'video/mp4')])->assertSessionHasErrors('video');
        $this->assertSame(0,Product::count());
    }

    public function test_product_video_can_be_uploaded_replaced_and_removed(): void
    {
        $this->product(1);

        $root=sys_get_temp_dir().'/suki-form-video-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.public.root'=>$root]);

        $first=\Illuminate\Http\UploadedFile::fake()->create(
            'demo.mp4',
            1024,
            'video/mp4'
        );

        $this->actingAs($this->seller)->put(
            route('seller.products.update',1),
            $this->payload([
                'name'=>'Owned Product 1',
                'description'=>'Saved description',
                'price'=>100,
                'video'=>$first,
            ])
        )->assertSessionHasNoErrors()->assertRedirect(route('seller.products'));

        $product=DB::table('products')->where('id',1)->first();

        $this->assertNotNull($product->video_path);
        $this->assertSame('video/mp4',$product->video_mime);
        $this->assertFileExists($root.'/'.$product->video_path);
        $this->assertSame(
            'PENDING',
            Product::find(1)->moderation_status->value
        );

        $oldPath=$product->video_path;

        $replacement=\Illuminate\Http\UploadedFile::fake()->create(
            'replacement.webm',
            1024,
            'video/webm'
        );

        $this->put(
            route('seller.products.update',1),
            $this->payload([
                'name'=>'Owned Product 1',
                'description'=>'Saved description',
                'price'=>100,
                'video'=>$replacement,
            ])
        )->assertSessionHasNoErrors();

        $product=DB::table('products')->where('id',1)->first();

        $this->assertNotSame($oldPath,$product->video_path);
        $this->assertSame('video/webm',$product->video_mime);
        $this->assertFileDoesNotExist($root.'/'.$oldPath);
        $this->assertFileExists($root.'/'.$product->video_path);

        $replacementPath=$product->video_path;

        $this->put(
            route('seller.products.update',1),
            $this->payload([
                'name'=>'Owned Product 1',
                'description'=>'Saved description',
                'price'=>100,
                'remove_video'=>1,
            ])
        )->assertSessionHasNoErrors();

        $product=DB::table('products')->where('id',1)->first();

        $this->assertNull($product->video_path);
        $this->assertNull($product->video_mime);
        $this->assertFileDoesNotExist($root.'/'.$replacementPath);
    }

    public function test_product_video_validation_rejects_wrong_type_and_files_over_25_mb(): void
    {
        $this->product(1);

        $wrongType=\Illuminate\Http\UploadedFile::fake()->create(
            'clip.mov',
            100,
            'video/quicktime'
        );

        $this->actingAs($this->seller)->put(
            route('seller.products.update',1),
            $this->payload([
                'name'=>'Owned Product 1',
                'description'=>'Saved description',
                'price'=>100,
                'video'=>$wrongType,
            ])
        )->assertSessionHasErrors('video');

        $tooLarge=\Illuminate\Http\UploadedFile::fake()->create(
            'huge.mp4',
            25601,
            'video/mp4'
        );

        $this->put(
            route('seller.products.update',1),
            $this->payload([
                'name'=>'Owned Product 1',
                'description'=>'Saved description',
                'price'=>100,
                'video'=>$tooLarge,
            ])
        )->assertSessionHasErrors('video');

        $this->assertNull(
            DB::table('products')->where('id',1)->value('video_path')
        );
    }

    public function test_marketplace_exposes_existing_media_and_falls_back_when_local_files_are_missing(): void
    {
        $this->product(1);

        $root=sys_get_temp_dir().'/suki-marketplace-media-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.public.root'=>$root]);

        \Illuminate\Support\Facades\Storage::disk('public')
            ->put('products/1/cover.webp','image-fixture');

        \Illuminate\Support\Facades\Storage::disk('public')
            ->put('products/1/video/demo.mp4','video-fixture');

        DB::table('product_images')->insert([
            'id'=>1,
            'product_id'=>1,
            'path'=>'products/1/cover.webp',
            'color'=>'Blue',
            'position'=>0,
        ]);

        DB::table('products')->where('id',1)->update([
            'video_path'=>'products/1/video/demo.mp4',
            'video_mime'=>'video/mp4',
        ]);

        $catalog=app(ProductCatalogService::class);
        $item=$catalog->marketplaceProduct('product-1');

        $this->assertNotNull($item);
        $this->assertSame(
            \Illuminate\Support\Facades\Storage::disk('public')
                ->url('products/1/cover.webp'),
            $item['image']
        );
        $this->assertSame(
            \Illuminate\Support\Facades\Storage::disk('public')
                ->url('products/1/video/demo.mp4'),
            $item['video']
        );
        $this->assertSame('video/mp4',$item['video_mime']);

        \Illuminate\Support\Facades\Storage::disk('public')
            ->delete([
                'products/1/cover.webp',
                'products/1/video/demo.mp4',
            ]);

        $item=$catalog->marketplaceProduct('product-1');

        $this->assertNotNull($item);
        $this->assertStringStartsWith(
            'https://placehold.co/800x800?text=',
            $item['image']
        );
        $this->assertNull($item['video']);
    }


    private function variantPhoto(string $name = 'variant.png'): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_variant_images_follow_custom_combinations_and_saved_ids_through_editing(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $payload = $this->universal([['name'=>'Flavor','values'=>'Chicken, Beef'],['name'=>'Pack Size','values'=>'Single, Six']]);
        $payload['variants'] = array_reverse($payload['variants']);
        foreach ($payload['variants'] as $index => &$row) { $row['image']=$this->variantPhoto('photo-'.$index.'.png'); $row['price']=89+$index*10; }
        unset($row);
        $this->post(route('seller.products.store'),$payload)->assertSessionHasNoErrors();
        $product=Product::with('variants')->sole();
        foreach ($product->variants as $index=>$variant) {
            $this->assertSame($payload['variants'][$index]['options'],$variant->options);
            $this->assertSame((89+$index*10)*100,$variant->price_minor);
            $this->assertStringContainsString('/variants/'.$variant->id.'/', $variant->image_path);
            \Illuminate\Support\Facades\Storage::disk('public')->assertExists($variant->image_path);
        }
        $this->assertCount(4,array_unique($product->variants->pluck('image_path')->all()));
        $product->update(['moderation_status'=>'APPROVED']);
        $catalog=app(ProductCatalogService::class)->marketplaceProduct($product->slug);
        $this->assertSame([89,99,109,119],array_column($catalog['variants'],'price'));
        $this->assertNotEmpty($catalog['variants'][0]['image']);
        $this->get(route('seller.products.edit',$product))->assertOk()->assertSee('data-saved-image=',false);
        $primary=$product->variants[0];$additional=$product->variants[1];$omitted=$product->variants[3];
        $oldPrimary=$primary->image_path;$oldAdditional=$additional->image_path;
        $edit=$this->payload(['name'=>$product->name,'category_id'=>2,'sku'=>$primary->sku,'variation_name'=>array_keys($primary->options),'variation_value'=>array_values($primary->options),'variants'=>[['id'=>$additional->id,'name'=>$additional->name,'options'=>$additional->options,'sku'=>$additional->sku,'price'=>99,'stock'=>15]],'variant_image'=>$this->variantPhoto('replacement.png')]);
        $this->put(route('seller.products.update',$product),$edit)->assertSessionHasNoErrors();
        $this->assertNotSame($oldPrimary,$primary->fresh()->image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPrimary);
        $this->assertSame($oldAdditional,$additional->fresh()->image_path);
        $this->assertSame($omitted->image_path,$omitted->fresh()->image_path);
        $this->assertSame('PENDING',$product->fresh()->moderation_status->value);
        unset($edit['variant_image']);
        $edit['variants'][0]['image']=$this->variantPhoto('additional-replacement.png');
        $this->put(route('seller.products.update',$product),$edit)->assertSessionHasNoErrors();
        $this->assertNotSame($oldAdditional,$additional->fresh()->image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldAdditional);
        $oldAdditional=$additional->fresh()->image_path;
        unset($edit['variants'][0]['image']);
        $edit['remove_variant_image']='1';$edit['variants'][0]['remove_image']='1';
        $this->put(route('seller.products.update',$product),$edit)->assertSessionHasNoErrors();
        $this->assertNull($primary->fresh()->image_path);$this->assertNull($additional->fresh()->image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldAdditional);
        $this->assertSame(4,$product->variants()->count());
    }

    public function test_variant_upload_validation_ownership_and_failed_transaction_cleanup(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $payload=$this->universal([['name'=>'Storage','values'=>'Small, Large']]);
        $payload['variants'][0]['image']=\Illuminate\Http\UploadedFile::fake()->create('bad.svg',1,'image/svg+xml');
        $this->post(route('seller.products.store'),$payload)->assertSessionHasErrors('variants.0.image');
        $photo=$this->variantPhoto();
        $payload['variants'][0]['image']=\Illuminate\Http\UploadedFile::fake()->createWithContent('large.png',file_get_contents($photo->getRealPath()).str_repeat('x',4097*1024));
        $this->post(route('seller.products.store'),$payload)->assertSessionHasErrors('variants.0.image');
        $payload['variants'][0]['image']=$this->variantPhoto();
        $payload['variants'][1]['sku']='CONFLICT';
        $this->product(90,202);
        DB::table('product_variants')->where('id',901)->update(['sku'=>'CONFLICT']);
        $this->post(route('seller.products.store'),$payload)->assertSessionHasErrors('variants');
        $this->assertSame(1,Product::count());
        $this->assertSame([],\Illuminate\Support\Facades\Storage::disk('public')->allFiles());
        $this->put(route('seller.products.update',90),$this->payload(['variant_image'=>$this->variantPhoto()]))->assertNotFound();
        $this->assertSame([],\Illuminate\Support\Facades\Storage::disk('public')->allFiles());
    }

    public function test_approved_image_column_migration_preserves_existing_variants_and_rolls_back_in_memory(): void
    {
        $this->product(7);
        \Illuminate\Support\Facades\Schema::table('product_variants',fn($table)=>$table->dropColumn('image_path'));
        $migration=require database_path('migrations/2026_11_03_000001_add_image_path_to_product_variants_table.php');
        $migration->up();
        $this->assertNull(DB::table('product_variants')->where('id',71)->value('image_path'));
        $this->assertSame(10000,DB::table('product_variants')->where('id',71)->value('price_minor'));
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('product_variants','image_path'));
        $this->assertSame(71,DB::table('product_variants')->value('id'));
    }

    public function test_missing_variant_files_fall_back_and_removal_cannot_delete_other_media(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->product(8);
        $product=Product::with('variants')->findOrFail(8);$variant=$product->variants->sole();
        $variant->update(['image_path'=>'products/foreign/keep.png']);
        $this->assertNull(app(ProductCatalogService::class)->marketplaceProduct($product->slug)['variants'][0]['image']);
        \Illuminate\Support\Facades\Storage::disk('public')->put('products/foreign/keep.png','unrelated');
        $this->actingAs($this->seller)->put(route('seller.products.update',$product),$this->payload(['sku'=>$variant->sku,'remove_variant_image'=>'1']))->assertSessionHasNoErrors();
        $this->assertNull($variant->fresh()->image_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('products/foreign/keep.png');
    }

    public function test_existing_variant_only_products_get_thumbnails_everywhere_without_resubmission(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $disk=\Illuminate\Support\Facades\Storage::disk('public');
        $this->product(1);$this->product(2,202);
        $first='products/1/variants/11/first.png';$second='products/1/variants/12/second.png';
        $disk->put($first,'first');$disk->put($second,'second');
        DB::table('product_variants')->where('id',11)->update(['image_path'=>$first,'options'=>json_encode(['Flavor'=>'Chicken','Pack Size'=>'Single'])]);
        DB::table('product_variants')->insert(['id'=>12,'product_id'=>1,'sku'=>'SECOND','name'=>'Second','options'=>json_encode(['Flavor'=>'Beef','Pack Size'=>'Six']),'price_minor'=>9900,'stock'=>3,'is_active'=>1,'image_path'=>$second]);
        $before=[DB::table('products')->get()->toJson(),DB::table('product_variants')->get()->toJson(),$disk->allFiles()];
        $expected=$disk->url($first);$product=Product::with(['images','variants'])->findOrFail(1);$catalog=app(ProductCatalogService::class);
        $this->assertSame($expected,$catalog->sellerItem($product)['image']);
        $this->assertSame($expected,$catalog->marketplaceProduct('product-1')['image']);
        $this->assertSame($expected,$catalog->marketplaceProducts()['product-1']['image']);
        $page=$this->actingAs($this->seller)->get(route('seller.inventory',['stock'=>'low-stock']))->assertOk()->assertDontSee('FOREIGN Product');
        $this->assertCount(1,$page->viewData('products'));
        $this->assertSame(12,$page->viewData('products')[0]['variant_id']);
        $this->assertSame($expected,$page->viewData('products')[0]['image']);
        $this->get(route('seller.products'))->assertOk()->assertSee($expected,false);
        $this->app['auth']->forgetGuards();
        $this->get(route('landing'))->assertOk()->assertSee($expected,false);
        $this->get(route('buyer.shop'))->assertOk()->assertSee($expected,false);
        $this->get(route('buyer.product','product-1'))->assertOk()->assertSee($expected,false)->assertSee('Pack Size');
        $this->assertSame(0,DB::table('product_images')->count());
        $this->assertSame($before,[DB::table('products')->get()->toJson(),DB::table('product_variants')->get()->toJson(),$disk->allFiles()]);
    }

    public function test_thumbnail_priority_missing_files_and_variant_eligibility_are_deterministic(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');$disk=\Illuminate\Support\Facades\Storage::disk('public');
        $this->product(1);$product=Product::findOrFail(1);$catalog=app(ProductCatalogService::class);
        $disk->put('first.png','first');$disk->put('second.png','second');$disk->put('main.png','main');
        DB::table('product_variants')->where('id',11)->update(['image_path'=>'missing.png']);
        foreach ([[9,0,5,'first.png'],[10,1,0,'first.png'],[12,1,5,'second.png'],[13,1,5,'first.png']] as [$id,$active,$stock,$path])DB::table('product_variants')->insert(['id'=>$id,'product_id'=>1,'sku'=>'THUMB-'.$id,'name'=>'Variant','price_minor'=>100,'stock'=>$stock,'is_active'=>$active,'image_path'=>$path]);
        $this->assertSame($disk->url('second.png'),$catalog->thumbnail($product->fresh(['images','variants'])));
        DB::table('product_images')->insert([['id'=>1,'product_id'=>1,'path'=>'missing-main.png','position'=>0],['id'=>2,'product_id'=>1,'path'=>'main.png','position'=>1]]);
        $this->assertSame($disk->url('main.png'),$catalog->thumbnail($product->fresh(['images','variants'])));
        DB::table('product_images')->where('id',2)->update(['path'=>'also-missing.png']);
        $this->assertSame($disk->url('second.png'),$catalog->thumbnail($product->fresh(['images','variants'])));
        DB::table('product_variants')->whereIn('id',[12,13])->update(['deleted_at'=>now()]);
        $this->assertNull($catalog->thumbnail($product->fresh(['images','variants'])));
        $this->assertStringStartsWith('https://placehold.co/',$catalog->marketplaceProduct('product-1')['image']);
        $this->assertNull($catalog->sellerItem($product->fresh(['images','variants']))['image']);
        $product->update(['moderation_status'=>'PENDING']);$this->assertNull($catalog->marketplaceProduct('product-1'));
    }

    public function test_listing_thumbnail_resolution_uses_eager_loaded_relations_without_n_plus_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        foreach(range(1,8) as $id)$this->product($id);
        DB::enableQueryLog();DB::flushQueryLog();
        $products=app(ProductCatalogService::class)->sellerProducts(\App\Models\Seller::findOrFail(101));
        $queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertCount(8,$products);$this->assertLessThanOrEqual(5,count($queries));
    }
}
