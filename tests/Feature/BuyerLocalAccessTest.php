<?php

namespace Tests\Feature;

use App\Models\BuyerEmailVerification;
use App\Models\Seller;
use App\Models\SellerConversation;
use App\Models\SellerMessageAttachment;
use App\Models\User;
use App\Support\BuyerAccess;
use App\Support\SellerMessaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuyerLocalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Mail::fake();
        Storage::fake('public');
        Storage::fake('chat_images');
    }

    private function buyer(array $changes = []): User
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'pending', 'is_suspended' => false, 'password' => 'Password@1']);
        $user->forceFill($changes + ['email_verified_at' => null])->save();

        return $user;
    }

    private function postCsrf(string $url, array $data = [])
    {
        return $this->withSession(['_token' => 'local-test-token'])->post($url, $data + ['_token' => 'local-test-token']);
    }

    public function test_local_registration_skips_otp_but_keeps_pending_unverified_records_and_can_login(): void
    {
        $this->app->instance('env', 'local');
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '0100000000', 'name' => 'Region I']]]),
            'https://psgc.cloud/api/v2/regions/0100000000/provinces' => Http::response(['data' => [['code' => '0101000000', 'name' => 'Province']]]),
            'https://psgc.cloud/api/v2/provinces/0101000000/cities-municipalities' => Http::response(['data' => [['code' => '0101010000', 'name' => 'City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/0101010000/barangays' => Http::response(['data' => [['code' => '0101010001', 'name' => 'Barangay']]]),
        ]);
        $this->postCsrf(route('register.submit'), [
            'first_name' => 'Maria', 'last_name' => 'DelaCruz', 'middle_initial' => 'L.', 'sex' => 'Female',
            'email' => 'new-local@example.com', 'phone' => '09171234567', 'birthday' => '1996-01-01',
            'region' => '0100000000', 'province' => '0101000000', 'municipality' => '0101010000', 'barangay' => '0101010001',
            'street_address' => '123 Test Street', 'government_id' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
            'password' => 'Password@1', 'password_confirmation' => 'Password@1', 'terms' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $user = User::where('email', 'new-local@example.com')->sole();
        $this->assertSame('pending', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('buyer_email_verifications', 0);
        Mail::assertNothingOutgoing();
        $this->postCsrf(route('login.submit'), ['login' => $user->email, 'password' => 'Password@1'])->assertRedirect(route('buyer.home'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('buyer.home'))->assertOk();
        $this->get(route('buyer.messages.index'))->assertOk();
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_local_pending_buyer_uses_messaging_and_private_images_without_changing_otp_or_users(): void
    {
        $this->app->instance('env', 'local');
        $buyer = $this->buyer();
        $otp = BuyerEmailVerification::create(['user_id' => $buyer->id, 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        $before = $otp->fresh()->getAttributes();
        $shop = Seller::create(['user_id' => User::factory()->create(['role' => 'seller', 'status' => 'active'])->id, 'name' => 'Shop', 'slug' => 'local-chat-shop', 'status' => 'approved']);
        $this->actingAs($buyer);
        $this->postCsrf(route('buyer.messages.start', ['kind' => 'shops', 'id' => $shop->id]), ['body' => 'Local hello', 'submission_key' => (string) Str::uuid(), 'images' => [UploadedFile::fake()->image('picture.png')]])->assertRedirect();
        $thread = SellerConversation::sole();
        $image = SellerMessageAttachment::sole();
        $this->get(route('buyer.messages.show', $thread->id))->assertOk()->assertSee('Local hello');
        $this->get(route('messages.images.show', $image->id))->assertOk();
        $this->postCsrf(route('buyer.messages.send', $thread->id), ['body' => 'Another message', 'submission_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame($before, $otp->fresh()->getAttributes());
        $this->assertSame('pending', $buyer->fresh()->status);
        $this->assertNull($buyer->fresh()->email_verified_at);
        $other = $this->buyer();
        $this->actingAs($other)->get(route('buyer.messages.show', $thread->id))->assertNotFound();
        $this->get(route('messages.images.show', $image->id))->assertForbidden();
        $this->actingAs($buyer)->get(route('seller.messages.index'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->app->instance('env', 'production');
        $this->get(route('buyer.messages.show', $thread->id))->assertForbidden();
        $this->get(route('messages.images.show', $image->id))->assertForbidden();
    }

    public static function environments(): array
    {
        return [['production'], ['staging'], ['testing']];
    }

    #[DataProvider('environments')]
    public function test_nonlocal_environments_require_otp_and_approval_and_ignore_client_flags(string $environment): void
    {
        $buyer = $this->buyer(['status' => 'active']);
        BuyerEmailVerification::create(['user_id' => $buyer->id, 'otp_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)]);
        $this->app->instance('env', $environment);
        $this->assertFalse(BuyerAccess::localException($buyer));
        $this->postCsrf(route('login.submit'), ['login' => $buyer->email, 'password' => 'Password@1', 'APP_ENV' => 'local', 'bypass_verification' => true])->assertRedirect(route('buyer.verification.show'));
        $this->assertGuest();
        $this->assertDatabaseCount('buyer_email_verifications', 1);
        $buyer->forceFill(['status' => 'pending', 'email_verified_at' => now()])->save();
        $this->postCsrf(route('login.submit'), ['login' => $buyer->email, 'password' => 'Password@1'])->assertSessionHasErrors('login');
        $this->assertGuest();
        $buyer->update(['status' => 'active']);
        $this->postCsrf(route('login.submit'), ['login' => $buyer->email, 'password' => 'Password@1'])->assertRedirect(route('buyer.home'));
        $this->assertAuthenticatedAs($buyer);
        $this->get(route('buyer.messages.index'))->assertOk();
    }

    public static function restricted(): array
    {
        return [[['is_suspended' => true]], [['status' => 'rejected']], [['status' => 'inactive']], [['role' => 'seller']], [['role' => 'admin']], [['role' => 'rider']], [['role' => 'logistics']]];
    }

    #[DataProvider('restricted')]
    public function test_local_exception_rejects_restricted_accounts(array $changes): void
    {
        $this->app->instance('env', 'local');
        $user = $this->buyer($changes);
        $this->assertFalse(BuyerAccess::localException($user));
        $this->assertFalse(SellerMessaging::eligibleBuyer($user));
        $this->postCsrf(route('login.submit'), ['login' => $user->email, 'password' => 'Password@1'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }
}
