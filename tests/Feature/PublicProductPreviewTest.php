<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicProductPreviewTest extends TestCase
{
    /** Guarded fixture schema only: never run application migrations. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $schema = [
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, role TEXT, status TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, status TEXT, created_at TEXT, updated_at TEXT',
            'categories'=>'id INTEGER PRIMARY KEY, parent_id INTEGER, name TEXT, slug TEXT, is_active INTEGER, position INTEGER DEFAULT 0',
            'products'=>'id INTEGER PRIMARY KEY, seller_id INTEGER, category_id INTEGER, name TEXT, slug TEXT, brand TEXT, description TEXT, shipping_options TEXT, is_active INTEGER, moderation_status TEXT, moderation_reason TEXT, moderated_by INTEGER, moderated_at TEXT, video_path TEXT, video_mime TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_variants'=>'id INTEGER PRIMARY KEY, product_id INTEGER, sku TEXT UNIQUE, weight_grams INTEGER, length_mm INTEGER, width_mm INTEGER, height_mm INTEGER, name TEXT, options TEXT, price_minor INTEGER, stock INTEGER, is_active INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT',
            'product_images'=>'id INTEGER PRIMARY KEY, product_id INTEGER, path TEXT, position INTEGER, color TEXT, created_at TEXT, updated_at TEXT',
            'product_moderation_events'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, actor_id INTEGER, previous_status TEXT, new_status TEXT, reason TEXT, source TEXT, metadata TEXT, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, notifiable_type TEXT, notifiable_id INTEGER, read_at TEXT',
            'transaction_reviews'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_id INTEGER, kind TEXT, rating INTEGER, hidden_at TEXT',
            'orders'=>'id INTEGER PRIMARY KEY, status TEXT',
            'seller_orders'=>'id INTEGER PRIMARY KEY, order_id INTEGER, status TEXT',
            'order_items'=>'id INTEGER PRIMARY KEY, product_id INTEGER, seller_order_id INTEGER, quantity INTEGER',
        ];

        $schema['categories'] .= ', created_at TEXT, updated_at TEXT';
        $schema['roles'] = 'id INTEGER PRIMARY KEY, name TEXT, created_at TEXT, updated_at TEXT';
        $schema['role_user'] = 'id INTEGER PRIMARY KEY, role_id INTEGER, user_id INTEGER, created_at TEXT, updated_at TEXT';
        $schema['cart_items'] = 'id INTEGER PRIMARY KEY, user_id INTEGER, product_variant_id INTEGER, product_slug TEXT, product_name TEXT, price NUMERIC, image TEXT, quantity INTEGER, selected INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT';
        $schema['wishlist_items'] = 'id INTEGER PRIMARY KEY, user_id INTEGER, product_slug TEXT, product_name TEXT, price NUMERIC, image TEXT, created_at TEXT, updated_at TEXT';
        $schema['transaction_reviews'] .= ', created_at TEXT, updated_at TEXT, body TEXT, seller_response TEXT';
        foreach ($schema as $table => $columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
    }

    public function test_guest_can_view_an_approved_active_product_preview_and_its_details(): void
    {
        $product = $this->product('Public preview', 'A guest-visible product description.');

        $this->get(route('buyer.product', $product->slug))
            ->assertOk()
            ->assertSee('Public preview')
            ->assertSee('A guest-visible product description.')
            ->assertSee('Default')
            ->assertSee('Sign in to purchase or save this item.');
    }

    public function test_guest_cannot_mutate_cart_or_wishlist_from_a_public_product_preview(): void
    {
        $product = $this->product('Protected preview');

        $this->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect(route('login'));
        $this->post(route('buyer.wishlist.toggle', $product->slug))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_active_buyer_can_still_add_a_public_product_to_cart(): void
    {
        $product = $this->product('Buyer preview');
        $buyer = $this->user('buyer');

        $this->actingAs($buyer)
            ->post(route('buyer.cart.add', $product->slug), ['quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_slug' => $product->slug,
            'quantity' => 1,
        ]);
    }

    public function test_inactive_pending_or_suspended_seller_products_are_not_publicly_viewable(): void
    {
        $inactive = $this->product('Inactive preview');
        $inactive->update(['is_active' => false]);

        $pending = $this->product('Pending preview');
        $pending->update(['moderation_status' => 'PENDING']);

        $suspended = $this->product('Suspended seller preview');
        $suspended->seller->owner->update(['is_suspended' => true]);

        foreach ([$inactive, $pending, $suspended] as $product) {
            $this->get(route('buyer.product', $product->slug))->assertNotFound();
        }
    }

    public function test_guest_landing_and_shop_link_to_real_public_previews(): void
    {
        $product = $this->product('Landing preview');
        $this->get(route('landing'))->assertOk()
            ->assertSee('Landing preview')
            ->assertSee(route('buyer.product', $product->slug), false)
            ->assertSee('data-authenticated="0"', false);
        $this->get(route('buyer.shop'))->assertOk()->assertSee('Landing preview');
        $this->get(route('buyer.checkout'))->assertRedirect(route('login'));
    }

    private function product(string $name, string $description = 'Product preview description.'): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'public-preview'],
            ['name' => 'Public Preview', 'is_active' => true]
        );
        $seller = $this->seller();
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => str($name)->slug()->append('-'.$seller->id),
            'description' => $description,
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PREVIEW-'.$product->id,
            'name' => 'Default',
            'price_minor' => 129900,
            'stock' => 3,
            'is_active' => true,
        ]);

        return $product;
    }

    private function seller(): Seller
    {
        $owner = $this->user('seller');

        return Seller::create([
            'user_id' => $owner->id,
            'name' => 'Preview Store '.$owner->id,
            'slug' => 'preview-store-'.$owner->id,
            'status' => 'approved',
        ]);
    }

    private function user(string $role): User
    {
        $user = User::forceCreate([
            'name' => 'Preview '. $role,
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
        ]);
        $user->roles()->syncWithoutDetaching([
            Role::firstOrCreate(['name' => $role])->id,
        ]);

        return $user;
    }
}
