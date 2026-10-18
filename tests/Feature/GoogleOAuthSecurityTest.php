<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function mockGoogle(string $id, string $email): void
    {
        $google = (new SocialiteUser())->map(['id' => $id, 'email' => $email, 'name' => 'Test Person']);
        $google->user = ['email_verified' => true, 'given_name' => 'Test', 'family_name' => 'Person'];
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    public function test_callback_preserves_verified_identity_and_role_in_session(): void
    {
        $this->mockGoogle('subject-1', '  NEW@Example.com ');
        $this->withSession(['google_oauth_intent' => 'seller'])->get(route('auth.google.callback', ['email' => 'attacker@example.com']))
            ->assertRedirect(route('seller.register'))
            ->assertSessionHas('google_registration', fn ($v) => $v['email'] === 'new@example.com' && $v['provider_user_id'] === 'subject-1' && $v['intent'] === 'seller');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_different_google_subject_cannot_be_replaced(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'role' => 'buyer', 'status' => 'active', 'oauth_provider' => 'google', 'oauth_provider_user_id' => 'original']);
        $this->mockGoogle('replacement', 'user@example.com');
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertSame('original', $user->fresh()->oauth_provider_user_id);
        $this->assertGuest();
    }

    public function test_rider_intent_is_rejected_before_redirect(): void
    {
        $this->get(route('auth.google.redirect', ['intent' => 'rider']))->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_verified_matching_email_links_existing_active_buyer(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com', 'role' => 'buyer', 'status' => 'active', 'email_verified_at' => now(),
            'first_name' => 'Test', 'last_name' => 'Buyer', 'sex' => 'Female', 'birthday' => '1990-01-01',
            'phone' => '09171234567', 'government_id' => 'existing-id.pdf',
        ]);
        $user->addresses()->create(['label' => 'Home', 'recipient' => 'Test Buyer', 'phone' => '09171234567', 'line1' => '1 Main St', 'barangay' => 'Poblacion', 'city' => 'Santa Cruz', 'province' => 'Laguna', 'is_default' => true]);
        $this->mockGoogle('stable-subject', ' USER@example.com ');
        $this->get(route('auth.google.callback'))->assertRedirect(route('buyer.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google', $user->fresh()->oauth_provider);
        $this->assertSame('stable-subject', $user->fresh()->oauth_provider_user_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_incomplete_buyer_resumes_onboarding_without_duplicate_or_activation(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com', 'role' => 'buyer', 'status' => 'pending', 'email_verified_at' => null]);
        $this->mockGoogle('resume-subject', 'buyer@example.com');

        $this->withSession(['google_oauth_intent' => 'buyer'])->get(route('auth.google.callback'))
            ->assertRedirect(route('register.buyer'))
            ->assertSessionHas('google_registration', fn ($identity) => $identity['intent'] === 'buyer'
                && $identity['resume_user_id'] === $user->id && $identity['provider_user_id'] === 'resume-subject');

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertNull($user->fresh()->oauth_provider);
    }

    public function test_suspended_or_inactive_buyer_is_not_linked_or_reactivated(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.com', 'role' => 'buyer', 'status' => 'inactive', 'is_suspended' => true]);
        $this->mockGoogle('blocked-subject', 'blocked@example.com');

        $this->withSession(['google_oauth_intent' => 'buyer'])->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');

        $this->assertSame('inactive', $user->fresh()->status);
        $this->assertNull($user->fresh()->oauth_provider);
    }

    public function test_existing_pending_seller_and_logistics_link_without_role_or_approval_changes(): void
    {
        $seller = User::factory()->create(['email' => 'seller@example.com', 'role' => 'seller', 'status' => 'pending', 'email_verified_at' => null]);
        $this->mockGoogle('seller-subject', 'seller@example.com');
        $this->withSession(['google_oauth_intent' => 'seller'])->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));
        $this->assertSame('pending', $seller->fresh()->status);
        $this->assertSame('seller-subject', $seller->fresh()->oauth_provider_user_id);

        $logistics = User::factory()->create(['email' => 'logistics@example.com', 'role' => 'logistics', 'status' => 'pending', 'email_verified_at' => null]);
        $this->mockGoogle('logistics-subject', 'logistics@example.com');
        $this->withSession(['google_oauth_intent' => 'logistics'])->get(route('auth.google.callback'))
            ->assertRedirect(route('login'));
        $this->assertSame('pending', $logistics->fresh()->status);
        $this->assertSame('logistics-subject', $logistics->fresh()->oauth_provider_user_id);
    }

    public function test_google_registration_intent_cannot_convert_an_existing_role(): void
    {
        $seller = User::factory()->create(['email' => 'seller@example.com', 'role' => 'seller', 'status' => 'pending']);
        $this->mockGoogle('seller-subject', 'seller@example.com');
        $this->withSession(['google_oauth_intent' => 'buyer'])->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('login');

        $this->assertSame('seller', $seller->fresh()->role);
        $this->assertNull($seller->fresh()->oauth_provider);
    }

    public function test_provider_subject_owned_by_another_account_is_not_reassigned(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'oauth_provider' => 'google', 'oauth_provider_user_id' => 'existing-subject']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $this->mockGoogle('existing-subject', 'other@example.com');
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('login');
        $this->assertSame('existing-subject', $owner->fresh()->oauth_provider_user_id);
        $this->assertNull($other->fresh()->oauth_provider_user_id);
        $this->assertGuest();
    }

    public function test_expired_onboarding_state_cannot_be_used(): void
    {
        $identity = ['intent' => 'buyer', 'email' => 'new@example.com', 'provider_user_id' => 'subject', 'issued_at' => now()->subMinutes(16)->timestamp];
        $request = \Illuminate\Http\Request::create('/');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('google_registration', $identity);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\GoogleRegistrationService::class)->assertMatches($request, 'new@example.com', 'buyer');
    }
}
