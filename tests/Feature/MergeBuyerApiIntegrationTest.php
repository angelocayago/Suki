<?php

namespace Tests\Feature;

use App\Models\{Address,CartItem,Category,Order,Product,Seller,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeBuyerApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if(getenv('DB_CONNECTION')!=='sqlite'||getenv('DB_DATABASE')!==':memory:'||getenv('DB_URL'))throw new \RuntimeException('Isolated API tests only.');
        parent::setUp();
        $this->assertSame(':memory:',DB::connection()->getDatabaseName());
        $this->mock(\App\Services\DomainNotificationService::class)->shouldReceive('afterCommit','adminsAfterCommit')->andReturnNull();
    }

    private function account(string $role='buyer'): User
    {
        return User::factory()->create(['role'=>$role,'status'=>'active','is_suspended'=>false,'verification_required_at'=>null]);
    }
    private function requestToken(string $token,string $method,string $url,array $data=[])
    {
        Auth::forgetGuards();Auth::setDefaultDriver('web');
        return $this->json($method,$url,$data,['Authorization'=>'Bearer '.$token]);
    }
    private function product(): Product
    {
        $owner=$this->account('seller');$seller=Seller::create(['user_id'=>$owner->id,'name'=>'API Shop','slug'=>'api-shop','status'=>'approved']);
        $category=Category::create(['name'=>'Everyday','slug'=>'api-everyday','is_active'=>true]);
        $product=Product::create(['seller_id'=>$seller->id,'category_id'=>$category->id,'name'=>'API Product','slug'=>'api-product','description'=>'Fixture','is_active'=>true,'moderation_status'=>'APPROVED']);
        foreach([['Vanilla',9900],['Chocolate',14900]] as [$flavor,$price])$product->variants()->create(['sku'=>'API-'.$flavor,'name'=>$flavor,'options'=>['Flavor'=>$flavor,'Pack Size'=>'Single'],'price_minor'=>$price,'stock'=>5,'is_active'=>true]);
        return $product->fresh('variants');
    }

    public function test_real_personal_tokens_are_hashed_role_checked_and_revoked_individually(): void
    {
        $buyer=$this->account();
        $login=$this->postJson('/api/login',['login'=>strtoupper($buyer->email),'password'=>'password'])->assertOk()->assertJsonPath('user.id',$buyer->id);
        $token=$login->json('token');$stored=$buyer->tokens()->sole();
        $this->assertSame(hash('sha256',explode('|',$token,2)[1]),$stored->token);$this->assertNotSame($token,$stored->token);
        $second=$buyer->createToken('second')->plainTextToken;
        $this->requestToken($token,'GET','/api/user')->assertOk()->assertJsonPath('id',$buyer->id);
        $this->requestToken($token,'POST','/api/logout')->assertOk();
        $this->requestToken($token,'GET','/api/buyer/cart')->assertUnauthorized();
        $this->requestToken($second,'GET','/api/buyer/cart')->assertOk();
        $this->assertSame(1,$buyer->tokens()->count());
        Auth::forgetGuards();Auth::setDefaultDriver('web');
        $this->withHeaders(['Authorization'=>'Bearer '.$second])->getJson(route('seller.products'))->assertUnauthorized();
    }

    public function test_guest_and_non_buyer_tokens_and_restricted_accounts_cannot_mutate_buyer_data(): void
    {
        $this->getJson('/api/buyer/cart')->assertUnauthorized();
        foreach(['seller','rider','admin'] as $role){
            $user=$this->account($role);$this->postJson('/api/login',['login'=>$user->email,'password'=>'password'])->assertForbidden();
            $this->requestToken($user->createToken('other-role')->plainTextToken,'GET','/api/buyer/cart')->assertForbidden();
        }
        $buyer=$this->account();$token=$buyer->createToken('buyer')->plainTextToken;$buyer->update(['is_suspended'=>true]);
        $this->requestToken($token,'GET','/api/buyer/cart')->assertForbidden();
        $buyer->update(['is_suspended'=>false,'status'=>'pending']);$this->requestToken($token,'GET','/api/buyer/cart')->assertForbidden();
        $buyer->update(['status'=>'active','verification_required_at'=>now(),'email_verified_at'=>null]);$this->requestToken($token,'GET','/api/buyer/cart')->assertForbidden();
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_api_query_validation_and_archived_product_handling_return_client_errors(): void
    {
        $product=$this->product();
        $this->getJson('/api/products?'.http_build_query(['q'=>['invalid']]))->assertUnprocessable();
        $this->getJson('/api/products?per_page=999')->assertOk()->assertJsonPath('data.per_page',48);
        $this->getJson('/api/products?per_page=invalid')->assertUnprocessable();
        $buyer=$this->account();$token=$buyer->createToken('buyer')->plainTextToken;$product->delete();
        $this->requestToken($token,'POST','/api/buyer/cart/items',['product_variant_id'=>$product->variants[0]->id,'quantity'=>1])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items',0);
    }

    public function test_api_exact_variant_cart_and_checkout_reuse_the_canonical_backend(): void
    {
        $product=$this->product();$buyer=$this->account();$token=$buyer->createToken('buyer')->plainTextToken;
        $this->getJson('/api/products/api-product')->assertOk()->assertJsonPath('data.old_price',null)->assertJsonPath('data.discount',null);
        $this->getJson('/api/buyer/home')->assertOk()->assertJsonPath('data.flash_deals',[]);
        foreach($product->variants as $variant)$this->requestToken($token,'POST','/api/buyer/cart/items',['product_variant_id'=>$variant->id,'quantity'=>1])->assertCreated();
        $this->assertDatabaseCount('cart_items',2);
        $foreign=CartItem::create(['user_id'=>$this->account()->id,'product_slug'=>$product->slug,'product_name'=>$product->name,'product_variant_id'=>$product->variants[0]->id,'price'=>99,'quantity'=>1]);
        $this->requestToken($token,'PATCH','/api/buyer/cart/items/'.$foreign->id,['quantity'=>2])->assertNotFound();
        $this->requestToken($token,'DELETE','/api/buyer/cart/items/'.$foreign->id)->assertNotFound();
        $this->requestToken($token,'POST','/api/buyer/wishlist/api-product/toggle')->assertOk()->assertJsonPath('data.is_wishlisted',true);
        $this->requestToken($token,'GET','/api/buyer/wishlist')->assertOk()->assertJsonPath('data.slugs.0','api-product');
        $address=Address::create(['user_id'=>$buyer->id,'recipient'=>'Buyer','phone'=>'09123456789','line1'=>'Street','city'=>'City','province'=>'Province','barangay'=>'Barangay','country_code'=>'PH','is_default'=>true]);
        $this->requestToken($token,'GET','/api/buyer/checkout')->assertOk()->assertJsonPath('data.addresses.0.id',$address->id);
        $this->requestToken($token,'POST','/api/buyer/checkout',['address_id'=>$address->id,'payment_method'=>'cod','price'=>0,'discount'=>999999])->assertCreated();
        $order=Order::sole();$this->assertSame(29800,$order->total_minor);$this->assertSame($product->variants->pluck('id')->all(),$order->items->pluck('product_variant_id')->all());
        $this->assertSame([4,4],$product->variants()->orderBy('id')->pluck('stock')->all());
        $this->requestToken($token,'POST','/api/buyer/checkout',['address_id'=>$address->id,'payment_method'=>'cod'])->assertUnprocessable();$this->assertDatabaseCount('orders',1);
    }

    public function test_api_logout_handles_sanctum_web_session_fallback_without_a_token_delete_error(): void
    {
        $buyer=$this->account();
        $this->actingAs($buyer)->postJson('/api/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_role_registration_forms_load_one_wizard_asset_and_keep_original_routes(): void
    {
        $this->get(route('register'))->assertOk()->assertSee(route('register.buyer'),false)->assertSee(route('seller.register'),false)->assertSee(route('logistics.register'),false);
        $manifest=json_decode(file_get_contents(public_path('build/manifest.json')),true);
        foreach(['register.buyer'=>'buyer','seller.register'=>'seller','logistics.register'=>'logistics','rider.apply'=>'rider'] as $route=>$kind){
            $page=$this->get(route($route))->assertOk()->assertSee('data-registration-wizard',false)->assertSee('data-wizard-kind="'.$kind.'"',false);
            preg_match_all('/<script\b[^>]*src="[^"]*'.preg_quote($manifest['resources/js/registration-wizard.js']['file'],'/').'"[^>]*>/',$page->getContent(),$scripts);
            $this->assertCount(1,$scripts[0]);
            $page->assertSee('name="_token"',false);
        }
        $this->get(route('logistics.register'))->assertSee(route('rider.apply'),false);
        $this->get(route('logout'))->assertStatus(405);
    }
}
