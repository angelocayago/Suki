<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Exceptions\VerificationDeliveryException;
use App\Mail\AccountVerificationOtpMail;
use App\Mail\RiderActivationMail;
use App\Models\AccountVerification;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\RiderActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountVerificationGapClosureTest extends TestCase
{
    use RefreshDatabase;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new FakeSmsSender();
        $this->app->instance(SmsSender::class, $this->sms);
        Mail::fake();
    }

    public function test_buyer_registration_normalizes_identity_and_requires_verification_without_admin_application(): void
    {
        Storage::fake('local');
        $response = $this->post(route('register.submit'), $this->buyerPayload(['email' => '  NEW@Example.COM ', 'phone' => '+639171234567']));
        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $response->assertRedirect(route('verification.contact.show'));
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->verification_required_at);
        $this->assertFalse($user->applications()->exists());
        Mail::assertSent(AccountVerificationOtpMail::class, 1);
    }

    public function test_case_insensitive_email_and_equivalent_phone_duplicates_are_blocked(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'phone' => '09171234567']);
        $this->post(route('register.submit'), $this->buyerPayload(['email' => ' TAKEN@EXAMPLE.COM ', 'phone' => '09179999999']))->assertSessionHasErrors('email');
        $this->post(route('register.submit'), $this->buyerPayload(['email' => 'other@example.com', 'phone' => '+639171234567']))->assertSessionHasErrors('phone');
        $this->assertSame(1, User::query()->count());
    }

    public function test_valid_email_then_sms_codes_activate_buyer_without_admin_approval(): void
    {
        $user = $this->verificationUser('buyer');
        $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false);
        $emailCode = $this->lastEmailCode();
        $this->assertFalse($service->verify($user, 'email', $emailCode));
        $service->send($user->fresh(), 'sms', false);
        $this->assertTrue($service->verify($user->fresh(), 'sms', $this->sms->lastCode()));
        $this->assertSame('active', $user->fresh()->status);
    }

    public function test_wrong_code_counts_attempt_and_correct_code_cannot_be_reused(): void
    {
        $user = $this->verificationUser(); $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false); $code = $this->lastEmailCode();
        try { $service->verify($user, 'email', '000000'); $this->fail('Wrong OTP accepted.'); } catch (ValidationException) {}
        $this->assertSame(1, AccountVerification::query()->where('user_id', $user->id)->value('attempts'));
        $service->verify($user, 'email', $code);
        $this->expectException(ValidationException::class);
        $service->verify($user, 'email', $code);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->verificationUser(); $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false); $code = $this->lastEmailCode();
        AccountVerification::query()->where('user_id', $user->id)->update(['expires_at' => now()->subSecond()]);
        $this->expectException(ValidationException::class); $service->verify($user, 'email', $code);
    }

    public function test_resend_invalidates_old_email_and_sms_codes(): void
    {
        $user = $this->verificationUser(); $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false); $oldEmail = $this->lastEmailCode();
        $service->send($user, 'email', false); $newEmail = $this->lastEmailCode();
        $this->assertNotSame($oldEmail, $newEmail);
        try { $service->verify($user, 'email', $oldEmail); $this->fail('Old email OTP accepted.'); } catch (ValidationException) {}
        $service->verify($user, 'email', $newEmail);
        $service->send($user->fresh(), 'sms', false); $oldSms = $this->sms->lastCode();
        $service->send($user->fresh(), 'sms', false); $newSms = $this->sms->lastCode();
        try { $service->verify($user->fresh(), 'sms', $oldSms); $this->fail('Old SMS OTP accepted.'); } catch (ValidationException) {}
        $this->assertTrue($service->verify($user->fresh(), 'sms', $newSms));
    }

    public function test_resend_cooldown_and_attempt_limit_are_enforced(): void
    {
        $user = $this->verificationUser(); $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false);
        try { $service->send($user, 'email'); $this->fail('Cooldown not enforced.'); } catch (ValidationException) {}
        for ($i = 0; $i < 5; $i++) { try { $service->verify($user, 'email', '000000'); } catch (ValidationException) {} }
        $this->expectException(ValidationException::class); $service->verify($user, 'email', $this->lastEmailCode());
    }

    public function test_smtp_failure_does_not_persist_or_replace_an_email_code(): void
    {
        $user = $this->verificationUser();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        try {
            app(AccountVerificationService::class)->send($user, 'email', false);
            $this->fail('SMTP failure was treated as a successful send.');
        } catch (VerificationDeliveryException $exception) {
            $this->assertSame('email', $exception->channel);
        }

        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'email']);
    }

    public function test_sms_failure_does_not_persist_or_replace_a_mobile_code(): void
    {
        $user = $this->verificationUser();
        $this->sms->fail = true;

        try {
            app(AccountVerificationService::class)->send($user, 'sms', false);
            $this->fail('SMS failure was treated as a successful send.');
        } catch (VerificationDeliveryException $exception) {
            $this->assertSame('sms', $exception->channel);
        }

        $this->assertDatabaseMissing('account_verifications', ['user_id' => $user->id, 'channel' => 'sms']);
    }

    public function test_unverified_account_is_redirected_to_verification_instead_of_portal(): void
    {
        $user = $this->verificationUser('buyer');
        $this->post(route('login.submit'), ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('verification.contact.show'));
        $this->assertGuest();
    }

    public function test_seller_and_logistics_remain_pending_after_both_contacts_are_verified(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            $user = $this->verificationUser($role); $service = app(AccountVerificationService::class);
            $service->send($user, 'email', false); $service->verify($user, 'email', $this->lastEmailCode());
            $service->send($user->fresh(), 'sms', false); $service->verify($user->fresh(), 'sms', $this->sms->lastCode());
            $this->assertSame('pending', $user->fresh()->status);
        }
    }

    public function test_verification_routes_are_session_scoped(): void
    {
        $this->get(route('verification.contact.show'))->assertForbidden();
        $this->post(route('verification.contact.verify'), ['code' => '123456'])->assertForbidden();
    }

    public function test_rider_activation_is_single_use_and_requires_mobile_verification_before_access(): void
    {
        $user = $this->verificationUser('rider');
        $provider = LogisticsProvider::query()->create(['user_id' => User::factory()->create(['status' => 'active'])->id, 'name' => 'Activation Logistics', 'slug' => 'activation-logistics', 'status' => 'approved']);
        Rider::query()->create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'ACT-100', 'is_active' => false, 'approved_at' => now()]);
        $activation = app(RiderActivationService::class);
        $activation->send($user, false);
        $token = $this->lastActivationToken();
        $activated = $activation->activate($token, 'new-password');
        $this->assertSame('pending', $activated->status);
        $this->assertNotNull($activated->email_verified_at);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $activation->activate($token, 'other-password');
    }

    public function test_expired_rider_activation_token_is_rejected(): void
    {
        $user = $this->verificationUser('rider');
        $provider = LogisticsProvider::query()->create(['user_id' => User::factory()->create(['status' => 'active'])->id, 'name' => 'Expiry Logistics', 'slug' => 'expiry-logistics', 'status' => 'approved']);
        Rider::query()->create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'plate_no' => 'EXP-100', 'is_active' => false, 'approved_at' => now()]);
        $activation = app(RiderActivationService::class); $activation->send($user, false);
        \App\Models\RiderActivationToken::query()->where('user_id', $user->id)->update(['expires_at' => now()->subSecond()]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $activation->userFor($this->lastActivationToken());
    }

    private function verificationUser(string $role = 'buyer'): User
    {
        $user = User::factory()->create(['role'=>$role,'email'=>$role.uniqid().'@example.com','phone'=>'09'.random_int(100000000,999999999),'status'=>'pending','verification_required_at'=>now(),'password'=>'password']);
        $user->roles()->attach(Role::query()->firstOrCreate(['name'=>$role])); return $user;
    }
    private function lastEmailCode(): string
    {
        $code = ''; Mail::assertSent(AccountVerificationOtpMail::class, function ($mail) use (&$code) { $code = $mail->code; return true; }); return $code;
    }
    private function buyerPayload(array $overrides = []): array
    {
        return array_merge(['last_name'=>'Buyer','first_name'=>'New','middle_initial'=>'Q','sex'=>'Female','email'=>'new@example.com','phone'=>'09171234567','birthday'=>'1998-01-01','province'=>'Laguna','municipality'=>'Santa Cruz','barangay'=>'Poblacion','street_address'=>'10 Main St','government_id'=>UploadedFile::fake()->create('id.jpg', 50, 'image/jpeg'),'password'=>'password123','password_confirmation'=>'password123','terms'=>'1'], $overrides);
    }

    private function lastActivationToken(): string
    {
        $url = ''; Mail::assertSent(RiderActivationMail::class, function ($mail) use (&$url) { $url = $mail->activationUrl; return true; });
        return basename(parse_url($url, PHP_URL_PATH));
    }
}

class FakeSmsSender implements SmsSender
{
    public array $messages = [];
    public bool $fail = false;
    public function send(string $phone, string $message): void
    {
        if ($this->fail) throw new \RuntimeException('SMS provider unavailable');
        $this->messages[] = compact('phone','message');
    }
    public function lastCode(): string { preg_match('/\b(\d{6})\b/', end($this->messages)['message'], $matches); return $matches[1]; }
}
