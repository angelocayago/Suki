<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessage;
use App\Models\SellerMessageAttachment;
use App\Models\User;
use App\Support\SellerCategories;
use App\Support\SellerMessagingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerMessagingLocalShopTest extends TestCase
{
    use RefreshDatabase;

    private Seller $shop;

    private Seller $other;

    private Seller $approved;

    private User $buyer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->app->instance('env', 'local');
        config(['seller-messaging.local_pending_shop_id' => 9]);
        Storage::fake('chat_images');
        SellerCategories::populate();
        $owner = User::factory()->make(['role' => 'seller', 'status' => 'active', 'is_suspended' => false]);
        $owner->forceFill(['id' => 17])->save();
        $this->shop = new Seller(['user_id' => $owner->id, 'name' => 'KWEK-KWEK', 'slug' => 'kwek-kwek', 'status' => 'pending', 'business_category' => 'Pet Supplies']);
        $this->shop->forceFill(['id' => 9])->save();
        $this->other = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Other pending shop', 'slug' => 'other-pending', 'status' => 'pending', 'business_category' => 'Pet Supplies']);
        $this->approved = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Approved shop', 'slug' => 'approved-shop', 'status' => 'approved', 'business_category' => 'Pet Supplies']);
        $this->product = $this->product($this->shop, 'Designated product');
        $this->product($this->other, 'Hidden pending product');
        $this->product($this->approved, 'Approved product');
        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'pending', 'is_suspended' => false]);
        $this->buyer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->buyer);
    }

    private function product(Seller $shop, string $name): Product
    {
        $product = Product::create(['seller_id' => $shop->id, 'category_id' => SellerCategories::main($shop)->id, 'name' => $name, 'slug' => Str::slug($name), 'is_active' => true]);
        $product->variants()->create(['name' => 'Default', 'sku' => 'LOCAL-'.Str::uuid(), 'price_minor' => 100, 'stock' => 10, 'is_active' => true]);

        return $product;
    }

    private function send(string $url, array $data = [])
    {
        return $this->withSession(['_token' => 'shop-test-token'])->post($url, $data + ['_token' => 'shop-test-token', 'body' => 'Local question', 'submission_key' => (string) Str::uuid()]);
    }

    private function startUrl(Seller $shop): string
    {
        return route('buyer.messages.start', ['kind' => 'shops', 'id' => $shop->id]);
    }

    private function snapshot(): array
    {
        return collect(['users', 'sellers', 'products', 'product_variants'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_directory_and_contacts_label_only_the_designated_pending_shop_and_preserve_product_rules(): void
    {
        $inactive = $this->product($this->shop, 'Inactive product');
        $inactive->update(['is_active' => false]);
        $noVariant = $this->product($this->shop, 'Inactive variant product');
        $noVariant->variants()->update(['is_active' => false]);
        $before = $this->snapshot();
        $this->get(route('buyer.messages.index'))->assertOk()->assertSee('Designated product')->assertSee('Approved product')
            ->assertSee('KWEK-KWEK')->assertSee('Local Test Shop — Pending Approval')->assertDontSee('Other pending shop')->assertDontSee('Hidden pending product')->assertDontSee('Inactive product')->assertDontSee('Inactive variant product');
        $this->get(route('buyer.messages.contact', ['kind' => 'products', 'id' => $this->product->id]))->assertOk()->assertSee('Local Test Shop — Pending Approval');
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->shop->id]))->assertOk()->assertSee('Local Test Shop — Pending Approval');
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->approved->id]))->assertOk()->assertDontSee('Local Test Shop');
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->other->id]))->assertNotFound();
        $this->send($this->startUrl($this->other))->assertNotFound();
        $this->assertDatabaseCount('seller_conversations', 0);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_full_local_http_flow_with_images_is_owned_idempotent_and_never_approves_accounts(): void
    {
        $before = $this->snapshot();
        $key = (string) Str::uuid();
        $imageBytes = UploadedFile::fake()->image('buyer.png')->getContent();
        $payload = fn () => ['submission_key' => $key, 'images' => [UploadedFile::fake()->createWithContent('buyer.png', $imageBytes)]];
        $this->send($this->startUrl($this->shop), $payload())->assertRedirect();
        $this->send($this->startUrl($this->shop), $payload())->assertRedirect();
        $thread = SellerConversation::sole();
        $image = SellerMessageAttachment::sole();
        $this->assertDatabaseCount('seller_messages', 1);
        $this->assertCount(1, Storage::disk('chat_images')->allFiles());
        $this->get(route('messages.images.show', $image->id))->assertOk();
        $this->actingAs($this->shop->owner)->get(route('seller.messages.show', $thread->id))->assertOk()->assertSee('Local question');
        $this->get(route('messages.images.show', $image->id))->assertOk();
        $this->send(route('seller.messages.reply', $thread->id), ['body' => 'Local seller reply', 'images' => [UploadedFile::fake()->image('seller.webp')]])->assertRedirect();
        $sellerImage = SellerMessageAttachment::where('id', '!=', $image->id)->sole();
        $this->actingAs($this->buyer)->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Local seller reply')->assertSee('Local Test Shop — Pending Approval');
        $this->get(route('messages.images.show', $sellerImage->id))->assertOk();
        $this->send(route('buyer.messages.send', $thread->id), ['body' => 'Buyer continuation'])->assertRedirect();
        $this->assertDatabaseCount('seller_messages', 3);
        $this->assertNotNull(SellerMessage::where('sender_role', 'seller')->sole()->read_at);
        $foreign = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($foreign)->get(route('buyer.messages.show', $thread->id))->assertNotFound();
        $this->get(route('messages.images.show', $image->id))->assertForbidden();
        $this->actingAs($this->other->owner)->get(route('messages.images.show', $image->id))->assertNotFound();
        // Exclude only the deliberately added foreign test account from the snapshot.
        $after = $this->snapshot();
        $after['users'] = DB::table('users')->where('id', '!=', $foreign->id)->orderBy('id')->get()->toJson();
        $this->assertSame($before, $after);
        $this->assertSame('pending', $this->shop->fresh()->status);
        $this->assertNull($this->buyer->fresh()->email_verified_at);
    }

    public static function nonlocal(): array
    {
        return [['production'], ['staging'], ['testing']];
    }

    #[DataProvider('nonlocal')]
    public function test_nonlocal_rejects_pending_contacts_send_reply_and_images_even_with_configuration(string $environment): void
    {
        $this->send($this->startUrl($this->shop), ['images' => [UploadedFile::fake()->image('one.png')]])->assertRedirect();
        $thread = SellerConversation::sole();
        $image = SellerMessageAttachment::sole();
        $this->buyer->forceFill(['status' => 'active', 'email_verified_at' => now()])->save();
        $this->actingAs($this->buyer->fresh());
        $this->app->instance('env', $environment);
        $this->get(route('buyer.messages.index'))->assertOk()->assertViewHas('shops', fn ($shops) => $shops->modelKeys() === [$this->approved->id]);
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->shop->id]))->assertNotFound();
        $this->send($this->startUrl($this->shop), ['APP_ENV' => 'local', 'local_pending_shop_id' => 9])->assertNotFound();
        $this->send(route('buyer.messages.send', $thread->id))->assertForbidden();
        $this->get(route('messages.images.show', $image->id))->assertForbidden();
        $this->actingAs($this->shop->owner);
        $this->send(route('seller.messages.reply', $thread->id))->assertForbidden();
        $this->assertDatabaseCount('seller_messages', 1);
        $this->assertSame('pending', $this->shop->fresh()->status);
    }

    public static function badOwners(): array
    {
        return [[['is_suspended' => true]], [['status' => 'inactive']], [['role' => 'buyer']]];
    }

    #[DataProvider('badOwners')]
    public function test_designated_shop_still_requires_eligible_owner_for_directory_and_sends(array $changes): void
    {
        $this->send($this->startUrl($this->shop))->assertRedirect();
        $thread = SellerConversation::sole();
        $this->shop->owner->update($changes);
        $this->get(route('buyer.messages.index'))->assertOk()->assertViewHas('shops', fn ($shops) => ! $shops->contains('id', 9));
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => 9]))->assertNotFound();
        $this->send(route('buyer.messages.send', $thread->id))->assertForbidden();
        $this->assertDatabaseCount('seller_messages', 1);
    }

    public static function invalidConfiguration(): array
    {
        return [[0], [null], ['9junk'], [-9], [999]];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_exception_can_be_disabled_and_does_not_accept_invalid_configuration(mixed $id): void
    {
        config(['seller-messaging.local_pending_shop_id' => $id]);
        $this->assertFalse(SellerMessagingPolicy::eligible($this->shop));
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => 9]))->assertNotFound();
        $this->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => $this->approved->id]))->assertOk();
    }

    public function test_self_contact_and_rejected_designated_shop_are_not_enabled(): void
    {
        $this->shop->update(['user_id' => $this->buyer->id]);
        $this->buyer->update(['status' => 'active', 'role' => 'seller']);
        // Preserve a real Buyer role as well to verify explicit self-exclusion.
        $role = Role::firstOrCreate(['name' => 'buyer']);
        $this->buyer->roles()->attach($role);
        $this->buyer->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($this->buyer->fresh())->get(route('buyer.messages.contact', ['kind' => 'shops', 'id' => 9]))->assertNotFound();
        $this->shop->update(['status' => 'rejected']);
        $this->assertFalse(SellerMessagingPolicy::eligible($this->shop->fresh()));
    }
}
