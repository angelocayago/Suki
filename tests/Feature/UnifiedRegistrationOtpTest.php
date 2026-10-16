<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Exceptions\VerificationDeliveryException;
use App\Mail\AccountVerificationOtpMail;
use App\Models\AccountVerification;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnifiedRegistrationOtpTest extends TestCase
{
    use RefreshDatabase;

    private UnifiedOtpSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new UnifiedOtpSmsSender();
        $this->app->instance(SmsSender::class, $this->sms);
        Mail::fake();
    }

    public function test_public_registration_uses_one_same_code_for_email_and_sms_and_verifies_both_contacts(): void
    {
        $user = $this->user('buyer');
        $service = app(AccountVerificationService::class);
        $service->sendUnified($user, false);

        $mailCode = $this->mailCode();
        $this->assertSame($mailCode, $this->sms->lastCode());
        $this->assertDatabaseCount('account_verifications', 1);
        $this->assertDatabaseHas('account_verifications', ['user_id' => $user->id, 'channel' => 'unified']);

        $this->assertTrue($service->verifyUnified($user, $mailCode));
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame('active', $user->status);
    }

    public function test_seller_and_logistics_remain_pending_after_unified_verification(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            $user = $this->user($role);
            $service = app(AccountVerificationService::class);
            $service->sendUnified($user, false);
            $service->verifyUnified($user, $this->mailCode());
            $this->assertSame('pending', $user->fresh()->status);
            $this->assertTrue($user->fresh()->hasVerifiedContacts());
        }
    }

    public function test_unified_resend_replaces_only_after_both_deliveries_succeed_and_old_code_cannot_be_reused(): void
    {
        $user = $this->user();
        $service = app(AccountVerificationService::class);
        $service->sendUnified($user, false);
        $oldCode = $this->mailCode();
        $oldHash = AccountVerification::query()->value('code_hash');

        $this->sms->fail = true;
        try { $service->sendUnified($user, false); $this->fail('Failed delivery replaced the code.'); } catch (VerificationDeliveryException) {}
        $this->assertSame($oldHash, AccountVerification::query()->value('code_hash'));

        $this->sms->fail = false;
        $service->sendUnified($user, false);
        $newCode = $this->mailCode();
        $this->assertNotSame($oldCode, $newCode);
        try { $service->verifyUnified($user, $oldCode); $this->fail('Old code was accepted.'); } catch (ValidationException) {}
        $this->assertTrue($service->verifyUnified($user, $newCode));
    }

    public function test_unified_code_is_expiring_one_time_and_attempt_limited(): void
    {
        $user = $this->user(); $service = app(AccountVerificationService::class);
        $service->sendUnified($user, false); $code = $this->mailCode();
        try { $service->verifyUnified($user, '000000'); $this->fail('Wrong code accepted.'); } catch (ValidationException) {}
        $this->assertSame(1, AccountVerification::query()->value('attempts'));
        $service->verifyUnified($user, $code);
        $this->expectException(ValidationException::class);
        $service->verifyUnified($user, $code);
    }

    private function user(string $role = 'buyer'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'pending', 'phone' => '0917'.random_int(1000000, 9999999), 'verification_required_at' => now()]);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => $role]));
        return $user;
    }

    private function mailCode(): string
    {
        $code = '';
        Mail::assertSent(AccountVerificationOtpMail::class, function (AccountVerificationOtpMail $mail) use (&$code): bool { $code = $mail->code; return true; });
        return $code;
    }
}

class UnifiedOtpSmsSender implements SmsSender
{
    public array $messages = [];
    public bool $fail = false;
    public function send(string $phone, string $message): void { if ($this->fail) throw new \RuntimeException('SMS unavailable'); $this->messages[] = compact('phone', 'message'); }
    public function lastCode(): string { preg_match('/\b(\d{6})\b/', end($this->messages)['message'], $matches); return $matches[1]; }
}
