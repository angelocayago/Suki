<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleOnboardingAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code'=>'01','name'=>'Region A']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code'=>'0101','name'=>'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code'=>'010101','name'=>'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code'=>'010101001','name'=>'Barangay A']]]),
        ]);
    }

    private function identity(string $role): array
    {
        return ['intent' => $role, 'email' => 'google@example.com', 'provider_user_id' => 'google-subject', 'given_name' => 'Ana', 'family_name' => 'Muñoz', 'issued_at' => now()->timestamp];
    }

    private function common(): array
    {
        return ['first_name' => 'Ana', 'last_name' => 'Muñoz', 'middle_initial' => '', 'sex' => 'Female', 'birthday' => '1995-05-15', 'phone' => '09171234567', 'email' => 'google@example.com', 'password' => 'Password1!', 'password_confirmation' => 'Password1!', 'terms' => '1'];
    }

    private function buyer(): array
    {
        return $this->common() + ['region'=>'01', 'province'=>'0101', 'municipality'=>'010101', 'barangay'=>'010101001', 'street_address' => '10 Main St', 'government_id' => UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg')];
    }

    private function logistics(): array
    {
        return $this->common() + ['business_name' => 'Example Logistics', 'address' => '10 Main St', 'region' => '01', 'province' => '0101', 'municipality' => '010101', 'barangay' => '010101001', 'government_id' => UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg'), 'business_permit' => UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf')];
    }

    private function seller(): array
    {
        $category = Category::query()->create(['name' => 'Apparel', 'slug' => 'apparel', 'position' => 1, 'is_active' => true]);
        return $this->common() + ['region' => '01', 'province' => '0101', 'municipality' => '010101', 'barangay' => '010101001', 'address' => '10 Main St', 'postal_code' => '4009', 'business_name' => 'Example Store', 'category_id' => $category->id, 'seller_type' => 'business', 'tin' => '123-456-789-000', 'valid_id' => UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg'), 'business_permit' => UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf')];
    }

    public function test_google_buyer_onboarding_persists_identity_and_clears_session(): void
    {
        Storage::fake('local'); Mail::fake();
        $this->withSession(['google_registration' => $this->identity('buyer')])->post(route('register.submit'), $this->buyer())
            ->assertRedirect(route('buyer.home'))->assertSessionMissing('google_registration');
        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('google-subject', $user->oauth_provider_user_id);
        $this->assertSame('google', $user->oauth_provider);
        $this->assertDatabaseCount('account_verifications', 0);
        Mail::assertNothingSent();
    }

    public function test_existing_incomplete_google_buyer_completes_atomically_before_activation(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'email' => 'google@example.com', 'role' => 'buyer', 'status' => 'pending',
            'email_verified_at' => null, 'phone_verified_at' => null, 'oauth_provider' => null,
        ]);
        $identity = $this->identity('buyer') + ['resume_user_id' => $user->id];

        $this->withSession(['google_registration' => $identity])->post(route('register.submit'), $this->buyer())
            ->assertRedirect(route('buyer.home'))->assertSessionMissing('google_registration');

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('google-subject', $user->oauth_provider_user_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseCount('account_verifications', 0);
    }

    public function test_google_seller_onboarding_stays_pending(): void
    {
        Storage::fake('seller_documents'); Mail::fake();
        $this->withSession(['google_registration' => $this->identity('seller')])->post(route('seller.register.submit'), $this->seller())
            ->assertRedirect(route('login'))->assertSessionMissing('google_registration');
        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('google-subject', $user->oauth_provider_user_id);
        $this->assertDatabaseHas('applications', ['user_id' => $user->id, 'status' => 'pending']);
        $this->assertDatabaseCount('account_verifications', 0);
    }

    public function test_google_logistics_onboarding_stays_pending(): void
    {
        Storage::fake('local'); Mail::fake();
        $this->withSession(['google_registration' => $this->identity('logistics')])->post(route('logistics.register.submit'), $this->logistics())
            ->assertRedirect(route('login'))->assertSessionMissing('google_registration');
        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('google-subject', $user->oauth_provider_user_id);
        $this->assertDatabaseHas('applications', ['user_id' => $user->id, 'status' => 'pending']);
        $this->assertDatabaseHas('logistics_providers', ['user_id' => $user->id, 'region' => 'Region A', 'province' => 'Province A', 'city' => 'City A', 'barangay' => 'Barangay A']);
        $this->assertDatabaseCount('account_verifications', 0);
    }

    public function test_google_seller_and_logistics_keep_onboarding_state_when_password_is_weak(): void
    {
        Storage::fake('seller_documents');
        Storage::fake('local');

        $this->withSession(['google_registration' => $this->identity('seller')])
            ->post(route('seller.register.submit'), array_merge($this->seller(), [
                'password' => 'password123', 'password_confirmation' => 'password123',
            ]))
            ->assertSessionHasErrors('password')
            ->assertSessionHas('google_registration');

        $this->withSession(['google_registration' => $this->identity('logistics')])
            ->post(route('logistics.register.submit'), array_merge($this->logistics(), [
                'email' => 'google-logistics@example.com',
                'password' => 'password123', 'password_confirmation' => 'password123',
            ]))
            ->assertSessionHasErrors('password')
            ->assertSessionHas('google_registration');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_tampered_email_is_rejected_and_session_remains_available(): void
    {
        Storage::fake('local');
        $data = $this->buyer(); $data['email'] = 'other@example.com';
        $this->withSession(['google_registration' => $this->identity('buyer')])->post(route('register.submit'), $data)
            ->assertSessionHasErrors('email')->assertSessionHas('google_registration');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_identity_collision_rolls_back_new_buyer(): void
    {
        Storage::fake('local');
        User::updating(function (User $user) {
            if ($user->oauth_provider_user_id === 'google-subject') {
                User::factory()->create(['email' => 'competitor@example.com', 'oauth_provider' => 'google', 'oauth_provider_user_id' => 'google-subject']);
            }
        });
        $this->withSession(['google_registration' => $this->identity('buyer')])->post(route('register.submit'), $this->buyer())
            ->assertSessionHasErrors('email')->assertSessionHas('google_registration');
        $this->assertDatabaseMissing('users', ['email' => 'google@example.com']);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_database_collision_rolls_back_seller_application_and_documents(): void
    {
        Storage::fake('seller_documents');
        User::updating(function (User $user) {
            if ($user->oauth_provider_user_id === 'google-subject') {
                User::factory()->create(['email' => 'competitor@example.com', 'oauth_provider' => 'google', 'oauth_provider_user_id' => 'google-subject']);
            }
        });
        $this->withSession(['google_registration' => $this->identity('seller')])->post(route('seller.register.submit'), $this->seller())
            ->assertSessionHasErrors('email')->assertSessionHas('google_registration');
        $this->assertDatabaseMissing('users', ['email' => 'google@example.com']);
        $this->assertDatabaseCount('sellers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('seller_documents')->allFiles());
    }

    public function test_database_collision_rolls_back_logistics_application(): void
    {
        Storage::fake('local');
        User::updating(function (User $user) {
            if ($user->oauth_provider_user_id === 'google-subject') {
                User::factory()->create(['email' => 'competitor@example.com', 'oauth_provider' => 'google', 'oauth_provider_user_id' => 'google-subject']);
            }
        });
        $this->withSession(['google_registration' => $this->identity('logistics')])->post(route('logistics.register.submit'), $this->logistics())
            ->assertSessionHasErrors('email')->assertSessionHas('google_registration');
        $this->assertDatabaseMissing('users', ['email' => 'google@example.com']);
        $this->assertDatabaseCount('logistics_providers', 0);
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('application-documents/logistics'));
    }
}
