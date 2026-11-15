<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VersionedBuyerApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated API tests only.');
        }

        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->mock(DomainNotificationService::class)
            ->shouldReceive('afterCommit', 'adminsAfterCommit')
            ->andReturnNull();
    }

    public function test_v1_catalog_pagination_is_bounded_without_changing_legacy_api_pages(): void
    {
        $seller = $this->seller();
        $category = $this->category();
        for ($number = 1; $number <= 101; $number++) {
            $this->product($seller, $category, 'v1-page-'.$number, 'V1 page '.$number);
        }

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.per_page', 24)
            ->assertJsonPath('data.total', 101)
            ->assertJsonCount(24, 'data.data');
        $this->getJson('/api/v1/products?per_page=100&page=2')
            ->assertOk()
            ->assertJsonPath('data.per_page', 100)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonCount(1, 'data.data');
        $this->getJson('/api/v1/products?per_page=101')->assertUnprocessable();

        $this->getJson('/api/products?per_page=999')
            ->assertOk()
            ->assertJsonPath('data.per_page', 48);
    }

    public function test_v1_login_enforces_buyer_eligibility_and_logout_revokes_the_current_token(): void
    {
        $buyer = $this->account();
        $login = $this->postJson('/api/v1/login', ['login' => $buyer->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $buyer->id);
        $token = $login->json('token');

        $this->postJson('/api/v1/login', ['login' => $buyer->email, 'password' => 'wrong-password'])->assertUnauthorized();
        $this->postJson('/api/v1/login', ['login' => $this->account('seller')->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/v1/login', ['login' => $this->account('rider')->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/v1/login', ['login' => $this->account(attributes: ['is_suspended' => true])->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/v1/login', ['login' => $this->account(attributes: ['status' => 'pending'])->email, 'password' => 'password'])->assertForbidden();
        $this->postJson('/api/v1/login', ['login' => $this->account(attributes: ['verification_required_at' => now(), 'email_verified_at' => null])->email, 'password' => 'password'])->assertForbidden();

        $this->tokenRequest($token, 'GET', '/api/v1/buyer/cart')->assertOk();
        $this->tokenRequest($token, 'POST', '/api/v1/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->tokenRequest($token, 'GET', '/api/v1/buyer/cart')->assertUnauthorized();
    }

    public function test_v1_cart_and_wishlist_are_scoped_to_the_authenticated_buyer_and_stock_is_enforced(): void
    {
        $buyerA = $this->account();
        $buyerB = $this->account();
        $product = $this->product();
        $variant = $product->variants->sole();
        $aItem = $this->cartItem($buyerA, $product, 1);
        $bItem = $this->cartItem($buyerB, $product, 2, 'Buyer B exclusive item');
        $token = $buyerA->createToken('buyer-a')->plainTextToken;

        $this->tokenRequest($token, 'PATCH', '/api/v1/buyer/cart/items/'.$bItem->id, ['quantity' => 1])->assertNotFound();
        $this->tokenRequest($token, 'DELETE', '/api/v1/buyer/cart/items/'.$bItem->id)->assertNotFound();
        $this->assertDatabaseHas('cart_items', ['id' => $bItem->id, 'user_id' => $buyerB->id, 'quantity' => 2]);

        $this->tokenRequest($token, 'GET', '/api/v1/buyer/cart')
            ->assertOk()
            ->assertSee($product->name)
            ->assertDontSee('Buyer B exclusive item');

        WishlistItem::create(['user_id' => $buyerB->id, 'product_slug' => $product->slug, 'product_name' => 'Buyer B wishlist', 'price' => '99.00']);
        $this->tokenRequest($token, 'GET', '/api/v1/buyer/wishlist')
            ->assertOk()
            ->assertJsonPath('data.items', []);
        $this->tokenRequest($token, 'POST', '/api/v1/buyer/wishlist/'.$product->slug.'/toggle')->assertOk();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $buyerA->id, 'product_slug' => $product->slug]);
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $buyerB->id, 'product_slug' => $product->slug]);

        $this->tokenRequest($token, 'PATCH', '/api/v1/buyer/cart/items/'.$aItem->id, ['quantity' => $variant->stock + 1])->assertUnprocessable();
        $this->assertDatabaseHas('cart_items', ['id' => $aItem->id, 'quantity' => 1]);
        $this->tokenRequest($token, 'POST', '/api/v1/buyer/cart/items', ['product_variant_id' => 999999, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('cart_items', 2);
    }

    public function test_v1_cod_checkout_uses_owned_cart_and_address_without_changing_cod_accounting(): void
    {
        $buyer = $this->account();
        $otherBuyer = $this->account();
        $product = $this->product();
        $this->cartItem($buyer, $product, 2);
        $address = $this->address($buyer, 'Buyer Recipient');
        $foreignAddress = $this->address($otherBuyer, 'Foreign Recipient');
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->tokenRequest($token, 'POST', '/api/v1/buyer/checkout', [
            'address_id' => $foreignAddress->id,
            'payment_method' => 'cod',
        ])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);

        $this->tokenRequest($token, 'POST', '/api/v1/buyer/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertCreated();

        $order = Order::sole();
        $this->assertSame($buyer->id, $order->buyer_id);
        $this->assertSame('cod', $order->payment_method);
        $this->assertSame('UNPAID', $order->payment_status);
        $this->assertSame('Buyer Recipient', $order->recipient_name);
        $this->assertSame('Street', $order->street_address);
        $this->assertSame(24800, $order->total_minor);
        $this->assertSame(2, $order->items()->sole()->quantity);
        $this->assertSame($product->variants->sole()->id, $order->items()->sole()->product_variant_id);
        $this->assertSame(3, $product->variants->sole()->fresh()->stock);
        $this->assertSame(24800, $order->sellerOrders()->sole()->shipment->cod_amount_minor);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_v1_failed_checkout_never_creates_partial_orders(): void
    {
        $buyer = $this->account();
        $product = $this->product();
        $address = $this->address($buyer, 'Buyer Recipient');
        $token = $buyer->createToken('buyer')->plainTextToken;

        $this->cartItem($buyer, $product, 1);
        $this->tokenRequest($token, 'POST', '/api/v1/buyer/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'card',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'quantity' => 1]);

        $product->variants->sole()->update(['stock' => 0]);
        $this->tokenRequest($token, 'POST', '/api/v1/buyer/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cod',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('seller_orders', 0);
        $this->assertDatabaseCount('shipments', 0);
        $this->assertDatabaseHas('cart_items', ['user_id' => $buyer->id, 'quantity' => 1]);
    }

    private function account(string $role = 'buyer', array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'status' => 'active',
            'is_suspended' => false,
            'verification_required_at' => null,
        ], $attributes));
    }

    private function seller(): Seller
    {
        $owner = $this->account('seller');

        return Seller::create([
            'user_id' => $owner->id,
            'name' => 'V1 API Shop',
            'slug' => 'v1-api-shop',
            'status' => 'approved',
        ]);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'V1 API', 'slug' => 'v1-api', 'is_active' => true]);
    }

    private function product(?Seller $seller = null, ?Category $category = null, string $slug = 'v1-api-product', string $name = 'V1 API Product'): Product
    {
        $seller ??= $this->seller();
        $category ??= $this->category();
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'Fixture',
            'is_active' => true,
            'moderation_status' => 'APPROVED',
        ]);
        $product->variants()->create([
            'sku' => 'V1-'.strtoupper(str_replace('-', '', $slug)),
            'name' => 'One',
            'price_minor' => 9900,
            'stock' => 5,
            'is_active' => true,
        ]);
        $product->images()->create(['path' => 'images/demo-products/lahat-meron/lm001.webp', 'position' => 1]);

        return $product->fresh('variants');
    }

    private function cartItem(User $buyer, Product $product, int $quantity, ?string $name = null): CartItem
    {
        return CartItem::create([
            'user_id' => $buyer->id,
            'product_variant_id' => $product->variants->sole()->id,
            'product_slug' => $product->slug,
            'product_name' => $name ?? $product->name,
            'price' => '99.00',
            'quantity' => $quantity,
        ]);
    }

    private function address(User $buyer, string $recipient): Address
    {
        return Address::create([
            'user_id' => $buyer->id,
            'recipient' => $recipient,
            'phone' => '09123456789',
            'line1' => 'Street',
            'city' => 'City',
            'province' => 'Province',
            'barangay' => 'Barangay',
            'country_code' => 'PH',
            'is_default' => true,
        ]);
    }

    private function tokenRequest(string $token, string $method, string $url, array $data = [])
    {
        Auth::forgetGuards();
        Auth::setDefaultDriver('web');

        return $this->json($method, $url, $data, ['Authorization' => 'Bearer '.$token]);
    }
}
