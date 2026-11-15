<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\DomainNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesCheckoutLocations;
use Tests\TestCase;

class VersionedBuyerAddressApiTest extends TestCase
{
    use FakesCheckoutLocations;
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) {
            throw new \RuntimeException('Isolated API tests only.');
        }
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('public');
        $this->fakeCheckoutLocations();
        $this->mock(DomainNotificationService::class)->shouldReceive('afterCommit', 'adminsAfterCommit')->andReturnNull();
    }

    public function test_buyer_can_manage_only_own_addresses_and_default_is_unique(): void
    {
        $buyer = $this->buyer();
        $token = $buyer->createToken('buyer')->plainTextToken;
        $first = $this->api($token, 'POST', '/api/v1/buyer/addresses', $this->payload())
            ->assertCreated()->assertJsonPath('data.address.name', 'API Recipient')
            ->assertJsonPath('data.address.street', 'First Street')
            ->assertJsonPath('data.address.is_default', true)->json('data.address');
        $second = $this->api($token, 'POST', '/api/v1/buyer/addresses', $this->payload(['street' => 'Second Street', 'label' => 'Work']))
            ->assertCreated()->assertJsonPath('data.address.is_default', false)->json('data.address');

        $this->api($token, 'GET', '/api/v1/buyer/addresses')->assertOk()->assertJsonCount(2, 'data.addresses');
        $this->api($token, 'PATCH', '/api/v1/buyer/addresses/'.$second['id'], $this->payload(['name' => 'Updated Recipient', 'street' => 'Edited Street', 'label' => 'Work', 'is_default' => true]))
            ->assertOk()->assertJsonPath('data.address.name', 'Updated Recipient')
            ->assertJsonPath('data.address.is_default', true);
        $this->assertDatabaseHas('addresses', ['id' => $first['id'], 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $second['id'], 'is_default' => true, 'line1' => 'Edited Street']);

        $this->api($token, 'POST', '/api/v1/buyer/addresses/'.$first['id'].'/default')->assertOk();
        $this->assertDatabaseHas('addresses', ['id' => $first['id'], 'is_default' => true]);
        $this->api($token, 'DELETE', '/api/v1/buyer/addresses/'.$first['id'])->assertOk();
        $this->assertDatabaseMissing('addresses', ['id' => $first['id']]);
        $this->assertDatabaseHas('addresses', ['id' => $second['id'], 'is_default' => true]);

        $other = $this->buyer();
        $otherToken = $other->createToken('buyer')->plainTextToken;
        $this->api($otherToken, 'GET', '/api/v1/buyer/addresses/'.$second['id'])->assertNotFound();
        $this->api($otherToken, 'DELETE', '/api/v1/buyer/addresses/'.$second['id'])->assertNotFound();
    }

    public function test_address_api_requires_an_eligible_buyer_and_valid_psgc_payload(): void
    {
        $this->getJson('/api/v1/buyer/addresses')->assertUnauthorized();
        $pending = $this->buyer(['status' => 'pending']);
        $this->api($pending->createToken('buyer')->plainTextToken, 'GET', '/api/v1/buyer/addresses')->assertForbidden();
        $token = $this->buyer()->createToken('buyer')->plainTextToken;
        $this->api($token, 'POST', '/api/v1/buyer/addresses', ['name' => 'Missing fields'])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'region', 'municipality', 'barangay', 'street', 'label']);
        $this->api($token, 'POST', '/api/v1/buyer/addresses', $this->payload(['barangay' => 'not-a-real-code']))
            ->assertUnprocessable()->assertJsonValidationErrors('barangay');
    }

    public function test_owned_address_is_used_for_cod_snapshot_without_mutating_history(): void
    {
        $buyer = $this->buyer();
        $other = $this->buyer();
        $token = $buyer->createToken('buyer')->plainTextToken;
        $address = $this->api($token, 'POST', '/api/v1/buyer/addresses', $this->payload())->assertCreated()->json('data.address');
        $foreign = $this->api($other->createToken('buyer')->plainTextToken, 'POST', '/api/v1/buyer/addresses', $this->payload(['street' => 'Foreign Street']))->assertCreated()->json('data.address');
        $product = $this->product();
        CartItem::create(['user_id' => $buyer->id, 'product_variant_id' => $product->variants()->sole()->id, 'product_slug' => $product->slug, 'product_name' => $product->name, 'price' => '99.00', 'quantity' => 1]);

        $this->api($token, 'GET', '/api/v1/buyer/checkout')->assertOk()->assertJsonPath('data.addresses.0.id', $address['id']);
        $this->api($token, 'POST', '/api/v1/buyer/checkout', ['address_id' => $foreign['id'], 'payment_method' => 'cod'])->assertNotFound();
        $this->api($token, 'POST', '/api/v1/buyer/checkout', ['address_id' => $address['id'], 'payment_method' => 'cod'])->assertCreated();
        $order = Order::sole();
        $this->assertSame('API Recipient', $order->recipient_name);
        $this->assertSame('First Street', $order->street_address);
        $this->assertSame('010101001', $order->barangay_code);

        $this->api($token, 'PATCH', '/api/v1/buyer/addresses/'.$address['id'], $this->payload(['street' => 'Changed Street']))->assertOk();
        $this->api($token, 'DELETE', '/api/v1/buyer/addresses/'.$address['id'])->assertOk();
        $this->assertSame('First Street', $order->fresh()->street_address);
        $this->assertSame('API Recipient', $order->fresh()->recipient_name);
    }

    private function buyer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => 'buyer', 'status' => 'active', 'is_suspended' => false, 'verification_required_at' => null]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'API Recipient', 'phone' => '09123456789', 'region' => '01', 'province' => '0101', 'municipality' => '010101', 'barangay' => '010101001', 'street' => 'First Street', 'label' => 'Home'];
    }

    private function product(): Product
    {
        $owner = User::factory()->create(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $seller = Seller::create(['user_id' => $owner->id, 'name' => 'Address API Store', 'slug' => 'address-api-store', 'status' => 'approved']);
        $category = Category::create(['name' => 'Address API', 'slug' => 'address-api', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Address Product', 'slug' => 'address-product', 'description' => 'Fixture', 'is_active' => true, 'moderation_status' => 'APPROVED']);
        $product->variants()->create(['sku' => 'ADDRESS-API', 'name' => 'One', 'price_minor' => 9900, 'stock' => 5, 'is_active' => true]);
        Storage::disk('public')->put('products/address-api.webp', 'image');
        $product->images()->create(['path' => 'products/address-api.webp', 'position' => 1]);

        return $product->fresh('variants');
    }

    private function api(string $token, string $method, string $url, array $data = [])
    {
        Auth::forgetGuards();
        Auth::setDefaultDriver('web');

        return $this->json($method, $url, $data, ['Authorization' => 'Bearer '.$token]);
    }
}
