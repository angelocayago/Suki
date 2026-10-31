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
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, brand TEXT, description TEXT, shipping_options TEXT, is_active INTEGER, moderation_status TEXT, moderation_reason TEXT, moderated_by INTEGER, moderated_at TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_variants'=>'id INTEGER PRIMARY KEY, product_id INTEGER, sku TEXT UNIQUE, weight_grams INTEGER, length_mm INTEGER, width_mm INTEGER, height_mm INTEGER, name TEXT, options TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_images'=>'id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT, position INTEGER, color TEXT, created_at TEXT, updated_at TEXT',
            'product_moderation_events'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, actor_id INTEGER, previous_status TEXT, new_status TEXT, reason TEXT, source TEXT, metadata TEXT, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT',
            'orders'=>'id INTEGER PRIMARY KEY, status TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_order_id INTEGER, quantity INTEGER',
        ];
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('adminsAfterCommit')->andReturnNull();
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
}
