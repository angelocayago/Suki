<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Mail\AccountVerificationOtpMail;
use App\Models\{AccountVerification, LogisticsProvider, Rider, RiderActivationToken, User};
use App\Services\{AccountVerificationService, RiderActivationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Log, Mail, RateLimiter, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiderDualChannelOtpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Rider $rider;
    private RiderOtpSmsFake $sms;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:' || getenv('DB_URL')) throw new \RuntimeException('Memory-only OTP tests required.');
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertStringStartsWith(strtolower(str_replace('\\', '/', sys_get_temp_dir())).'/', strtolower(str_replace('\\', '/', storage_path())));
        Mail::fake(); Storage::fake('local');
        $this->sms = new RiderOtpSmsFake();
        $this->app->instance(SmsSender::class, $this->sms);
        config(['services.sms.driver' => 'null', 'services.sms.endpoint' => null, 'services.sms.token' => null]);
        $this->freezeSecond();
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'is_suspended' => false]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'OTP Provider', 'slug' => 'otp-provider', 'status' => 'approved']);
        // Simulate a pre-fix activation-set email timestamp. It is NOT OTP proof.
        $this->user = User::factory()->create(['role' => 'rider', 'email' => 'rider@example.test', 'phone' => '09170000001', 'status' => 'pending',
            'is_suspended' => false, 'email_verified_at' => now(), 'phone_verified_at' => null, 'verification_required_at' => now(), 'password' => 'password']);
        $this->rider = Rider::create(['user_id' => $this->user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Bike', 'plate_no' => 'TEST-OTP', 'approved_at' => now(), 'is_active' => false]);
        RiderActivationToken::create(['user_id' => $this->user->id, 'token_hash' => hash('sha256', 'consumed-fixture'), 'expires_at' => now()->addDay(), 'used_at' => now(), 'last_sent_at' => now()]);
        $this->pendingSession();
    }

    protected function tearDown(): void { $this->travelBack(); parent::tearDown(); }

    private function pendingSession(): void
    {
        $this->withSession(['verification_user_id' => $this->user->id, 'rider_verification_started_at' => now()->getTimestamp(), 'rider_verification_channel' => 'email']);
    }

    private function send(string $channel = 'email')
    {
        return $this->from(route('verification.contact.show'))->post(route('verification.contact.resend'), ['channel' => $channel]);
    }

    private function emailCode(): string
    {
        $mails = Mail::sent(AccountVerificationOtpMail::class);
        $this->assertNotEmpty($mails);
        return $mails->last()->code;
    }

    private function configuredSms(): void
    {
        config(['services.sms.driver' => 'http', 'services.sms.endpoint' => 'https://sms.example.test/send', 'services.sms.token' => 'fixture-token']);
    }

    private function verify(string $code)
    {
        return $this->from(route('verification.contact.show'))->post(route('verification.contact.verify'), ['code' => $code]);
    }

    public function test_email_is_default_and_success_requires_real_challenge_before_rider_access(): void
    {
        $this->assertFalse($this->user->fresh()->hasVerifiedContacts());
        $this->get(route('verification.contact.show'))->assertOk()->assertSee('Email OTP (recommended)')->assertSee('SMS verification is unavailable')->assertDontSee($this->user->email);
        $this->send()->assertSessionHas('success');
        $code = $this->emailCode();
        $challenge = AccountVerification::where('user_id', $this->user->id)->sole();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertSame('email', $challenge->channel);
        $this->assertEquals(300, now()->diffInSeconds($challenge->expires_at));
        $this->get(route('verification.contact.show'))->assertDontSee($code);
        $this->assertEmpty($this->sms->messages);
        $this->verify($code)->assertRedirect(route('rider.login'))->assertSessionMissing('verification_user_id')->assertSessionMissing('rider_verification_started_at')->assertSessionMissing('rider_verification_channel');
        $this->assertTrue($this->user->fresh()->hasVerifiedContacts());
        $this->assertNull($this->user->fresh()->phone_verified_at);
        $this->assertTrue($this->rider->fresh()->is_active);
        $this->assertSame('active', $this->user->fresh()->status);
        $this->assertGuest();
        $this->post(route('rider.login.submit'), ['login' => $this->user->email, 'password' => 'password'])->assertRedirect(route('rider.dashboard'));
    }

    public function test_configured_sms_sends_only_sms_and_records_only_phone_verification(): void
    {
        $this->configuredSms();
        $this->user->update(['email_verified_at' => null]);
        $this->get(route('verification.contact.show'))->assertOk()->assertViewHas('smsAvailable', true);
        $this->send('sms')->assertSessionHas('success')->assertSessionHas('rider_verification_channel', 'sms');
        Mail::assertNothingSent();
        $this->verify($this->sms->code())->assertRedirect(route('rider.login'));
        $this->assertNotNull($this->user->fresh()->phone_verified_at);
        $this->assertNull($this->user->fresh()->email_verified_at);
        $this->assertTrue($this->user->fresh()->hasVerifiedContacts());
    }

    public static function unavailableSms(): array
    {
        return [['null', null, null], ['http', null, 'token'], ['http', 'https://sms.example.test', null], ['http', 'invalid', 'token']];
    }

    #[DataProvider('unavailableSms')]
    public function test_unusable_sms_cannot_be_selected_or_falsely_report_delivery(string $driver, ?string $endpoint, ?string $token): void
    {
        config(['services.sms.driver' => $driver, 'services.sms.endpoint' => $endpoint, 'services.sms.token' => $token]);
        $this->send('sms')->assertSessionHasErrors('channel')->assertSessionMissing('success');
        $this->get(route('verification.contact.show'))->assertViewHas('smsAvailable', false)->assertSee('SMS verification is unavailable');
        $this->assertEmpty($this->sms->messages);
        $this->assertDatabaseCount('account_verifications', 0);
    }

    public function test_wrong_expired_exhausted_and_consumed_codes_are_rejected(): void
    {
        $this->send(); $code = $this->emailCode();
        $wrong = $code === '111111' ? '222222' : '111111';
        $this->verify($wrong)->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
        $this->assertSame(1, AccountVerification::sole()->attempts);
        AccountVerification::sole()->update(['expires_at' => now()->subSecond()]);
        $this->verify($code)->assertSessionHasErrors('code');
        AccountVerification::sole()->update(['expires_at' => now()->addMinutes(5), 'attempts' => 5]);
        $this->verify($code)->assertSessionHasErrors('code');
        AccountVerification::sole()->update(['attempts' => 0]);
        $this->verify($code)->assertRedirect(route('rider.login'));
        $this->pendingSession();
        $this->verify($code)->assertSessionHasErrors('code');
        $this->assertTrue($this->user->fresh()->hasVerifiedContacts());
    }

    public function test_resend_and_switching_share_cooldown_and_invalidate_only_after_delivery(): void
    {
        $this->configuredSms();
        $this->send(); $oldCode = $this->emailCode();
        $this->send('sms')->assertSessionHasErrors('code');
        $this->assertEmpty($this->sms->messages);
        $this->travel(61)->seconds();
        $this->send('sms')->assertSessionHas('success');
        $this->assertDatabaseMissing('account_verifications', ['user_id' => $this->user->id, 'channel' => 'email']);
        $smsCode = $this->sms->code();
        if ($oldCode !== $smsCode) $this->verify($oldCode)->assertSessionHasErrors('code');
        $this->verify($smsCode)->assertRedirect(route('rider.login'));
    }

    public function test_resend_on_same_channel_replaces_the_old_hash(): void
    {
        $this->send(); $before = AccountVerification::sole()->code_hash;
        $this->travel(61)->seconds();
        $this->send()->assertSessionHas('success');
        $this->assertNotSame($before, AccountVerification::sole()->code_hash);
        $this->verify($this->emailCode())->assertRedirect(route('rider.login'));
    }

    public function test_sms_delivery_failure_preserves_email_challenge_and_does_not_log_the_code(): void
    {
        $this->configuredSms(); $this->send(); $oldHash = AccountVerification::sole()->code_hash;
        $this->travel(61)->seconds(); $this->sms->fail = true;
        Log::shouldReceive('warning')->once()->withArgs(fn ($message, $context) => !str_contains($message.json_encode($context), $this->sms->code()));
        $this->send('sms')->assertSessionHas('error')->assertSessionHas('rider_verification_channel', 'email');
        $this->get(route('verification.contact.show'))->assertSee('We could not deliver')->assertDontSee($this->sms->code());
        $this->assertSame($oldHash, AccountVerification::sole()->code_hash);
        $this->verify($this->emailCode())->assertRedirect(route('rider.login'));
    }

    public function test_email_failure_is_visible_sanitized_and_recoverable_after_cooldown(): void
    {
        config(['mail.default' => 'smtp']);
        $captured = '';
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnUsing(function ($mail) use (&$captured) { $captured = $mail->code; throw new \RuntimeException('Provider error '.$captured); });
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) use (&$captured) { return !str_contains($message.json_encode($context), $captured); });
        $this->send()->assertSessionHas('error');
        $this->get(route('verification.contact.show'))->assertSee('We could not deliver')->assertDontSee($captured);
        $this->assertDatabaseCount('account_verifications', 0);
        $this->assertFalse($this->user->fresh()->hasVerifiedContacts());
        $this->send()->assertSessionHasErrors('code');
        Mail::swap($mailFake); $this->travel(61)->seconds();
        $this->send()->assertSessionHas('success');
        $this->verify($this->emailCode())->assertRedirect(route('rider.login'));
    }

    public function test_logging_mail_transports_and_failover_to_log_are_not_used_for_rider_otp(): void
    {
        config(['mail.default' => 'log']);
        $this->send()->assertSessionHasErrors('channel');
        config(['mail.default' => 'failover']);
        $this->send()->assertSessionHasErrors('channel');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('account_verifications', 0);
    }

    public static function blockedStates(): array { return [['unapproved'], ['unactivated'], ['suspended'], ['rejected'], ['provider-pending']]; }

    #[DataProvider('blockedStates')]
    public function test_rider_prerequisites_are_required_for_every_otp_endpoint(string $state): void
    {
        match ($state) {
            'unapproved' => $this->rider->update(['approved_at' => null]),
            'unactivated' => $this->user->riderActivationToken->update(['used_at' => null]),
            'suspended' => $this->user->update(['is_suspended' => true]),
            'rejected' => $this->user->update(['status' => 'rejected']),
            'provider-pending' => $this->rider->logisticsProvider->update(['status' => 'pending']),
        };
        $this->get(route('verification.contact.show'))->assertForbidden();
        $this->send()->assertForbidden();
        $this->verify('123456')->assertForbidden();
        Mail::assertNothingSent();
        $this->assertFalse($this->rider->fresh()->is_active);
    }

    public function test_session_expiry_missing_session_and_cross_account_access_are_rejected(): void
    {
        $this->travel(1800)->seconds();
        $this->get(route('verification.contact.show'))->assertForbidden()->assertSessionMissing('verification_user_id');
        $this->send()->assertForbidden();
        $this->pendingSession();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($other)->get(route('verification.contact.show'))->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_otp_is_bound_to_account_and_current_destination(): void
    {
        $this->send(); $code = $this->emailCode();
        $this->user->update(['email' => 'changed@example.test']);
        $this->verify($code)->assertSessionHasErrors('code');
        $this->assertFalse($this->user->fresh()->hasVerifiedContacts());
        $this->assertFalse($this->rider->fresh()->is_active);
    }

    public function test_a_code_from_another_account_cannot_verify_the_session_account(): void
    {
        $this->send(); $code = $this->emailCode();
        $other = User::factory()->create(['role' => 'rider', 'status' => 'pending', 'verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null]);
        Rider::create(['user_id' => $other->id, 'logistics_provider_id' => $this->rider->logistics_provider_id, 'vehicle_type' => 'Bike', 'plate_no' => 'OTHER', 'approved_at' => now(), 'is_active' => false]);
        RiderActivationToken::create(['user_id' => $other->id, 'token_hash' => hash('sha256', 'other-fixture'), 'expires_at' => now()->addDay(), 'used_at' => now(), 'last_sent_at' => now()]);
        $this->withSession(['verification_user_id' => $other->id]);
        $this->post(route('verification.contact.verify'), ['code' => $code, 'user_id' => $this->user->id, 'channel' => 'email'])->assertSessionHasErrors('code');
        $this->assertFalse($other->fresh()->hasVerifiedContacts());
        $this->assertFalse($this->user->fresh()->hasVerifiedContacts());
        $this->assertNull(AccountVerification::sole()->verified_at);
    }

    public function test_activation_sets_password_but_sends_no_otp_until_the_rider_chooses(): void
    {
        $activation = app(RiderActivationService::class);
        $activation->send($this->user, false);
        $token = '';
        Mail::assertSent(\App\Mail\RiderActivationMail::class, function ($mail) use (&$token) { $token = basename($mail->activationUrl); return true; });
        $this->user->update(['email_verified_at' => null]);
        $this->post(route('rider.activation.store', $token), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertRedirect(route('verification.contact.show'));
        Mail::assertNotSent(AccountVerificationOtpMail::class);
        $this->assertEmpty($this->sms->messages);
        $this->assertNull($this->user->fresh()->email_verified_at);
        $this->assertFalse($this->user->fresh()->hasVerifiedContacts());
        $this->send()->assertSessionHas('success');
        $this->verify($this->emailCode())->assertRedirect(route('rider.login'));
        $this->post(route('rider.activation.store', $token), ['password' => 'new-password', 'password_confirmation' => 'new-password'])->assertForbidden();
    }

    public function test_pending_rider_can_resume_from_both_login_routes_after_delivery_failure(): void
    {
        $this->post(route('login.submit'), ['login' => $this->user->email, 'password' => 'password'])->assertRedirect(route('verification.contact.show'));
        $this->assertGuest();
        $this->get(route('verification.contact.show'))->assertOk();
        $this->post(route('rider.login.submit'), ['login' => $this->user->email, 'password' => 'password'])->assertRedirect(route('verification.contact.show'));
        $this->assertGuest();
        $this->send()->assertSessionHas('success');
    }

    public function test_legacy_rider_and_genuinely_sms_verified_rider_remain_compatible(): void
    {
        $this->user->update(['verification_required_at' => null, 'status' => 'active']);
        $this->rider->update(['is_active' => true]);
        $this->assertTrue($this->user->fresh()->hasVerifiedContacts());
        $this->post(route('rider.login.submit'), ['login' => $this->user->email, 'password' => 'password'])->assertRedirect(route('rider.dashboard'));
        $this->user->update(['verification_required_at' => now(), 'phone_verified_at' => now()]);
        AccountVerification::create(['user_id' => $this->user->id, 'channel' => 'sms', 'code_hash' => str_repeat('a', 64), 'attempts' => 0, 'max_attempts' => 5, 'expires_at' => now()->subDay(), 'last_sent_at' => now()->subDay(), 'verified_at' => now()]);
        $this->assertTrue($this->user->fresh()->hasVerifiedContacts());
    }

    public function test_route_throttle_blocks_fourth_resend(): void
    {
        for ($i = 0; $i < 3; $i++) $this->send('invalid')->assertSessionHasErrors('channel');
        $this->send('invalid')->assertStatus(429);
    }

    public function test_route_throttle_blocks_eleventh_verification(): void
    {
        for ($i = 0; $i < 10; $i++) $this->verify('123456')->assertSessionHasErrors('code');
        $this->verify('123456')->assertStatus(429);
    }

    public function test_login_verify_and_resend_use_separate_route_limit_budgets(): void
    {
        $this->post(route('rider.login.submit'), ['login' => $this->user->email, 'password' => 'password'])->assertRedirect(route('verification.contact.show'));
        for ($i = 0; $i < 3; $i++) {
            $this->send()->assertSessionHas('success');
            $this->travel(61)->seconds();
        }
        $this->send()->assertStatus(429);
        $this->verify($this->emailCode())->assertRedirect(route('rider.login'));
    }
}

class RiderOtpSmsFake implements SmsSender
{
    public array $messages = [];
    public bool $fail = false;
    public function send(string $phone, string $message): void
    {
        $this->messages[] = $message;
        if ($this->fail) throw new \RuntimeException('Provider error '.$message);
    }
    public function code(): string { preg_match('/\b(\d{6})\b/', end($this->messages), $matches); return $matches[1]; }
}
