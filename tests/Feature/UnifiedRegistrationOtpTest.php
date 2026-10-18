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

    public function test_email_challenge_verifies_buyer_without_marking_phone_verified(): void
    {
        $user = $this->user('buyer');
        $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false);

        $mailCode = $this->mailCode();
        $this->assertCount(0, $this->sms->messages);
        $this->assertDatabaseCount('account_verifications', 1);
        $this->assertDatabaseHas('account_verifications', ['user_id' => $user->id, 'channel' => 'email']);
        $this->assertNotSame($mailCode, AccountVerification::query()->value('code_hash'));

        $this->assertTrue($service->verify($user, 'email', $mailCode));
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame('active', $user->status);
    }

    public function test_seller_and_logistics_remain_pending_after_email_verification(): void
    {
        foreach (['seller', 'logistics'] as $role) {
            $user = $this->user($role);
            $service = app(AccountVerificationService::class);
            $service->send($user, 'email', false);
            $service->verify($user, 'email', $this->mailCode());
            $this->assertSame('pending', $user->fresh()->status);
            $this->assertTrue($user->fresh()->hasVerifiedContacts());
        }
    }

    public function test_email_resend_invalidates_old_code(): void
    {
        $user = $this->user();
        $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false);
        $oldCode = $this->mailCode();
        $oldHash = AccountVerification::query()->value('code_hash');

        $service->send($user, 'email', false);
        $newCode = $this->mailCode();
        $this->assertNotSame($oldCode, $newCode);
        $this->assertNotSame($oldHash, AccountVerification::query()->value('code_hash'));
        try { $service->verify($user, 'email', $oldCode); $this->fail('Old code was accepted.'); } catch (ValidationException) {}
        $this->assertTrue($service->verify($user, 'email', $newCode));
    }

    public function test_email_code_is_one_time_and_counts_attempts(): void
    {
        $user = $this->user(); $service = app(AccountVerificationService::class);
        $service->send($user, 'email', false); $code = $this->mailCode();
        try { $service->verify($user, 'email', '000000'); $this->fail('Wrong code accepted.'); } catch (ValidationException) {}
        $this->assertSame(1, AccountVerification::query()->value('attempts'));
        $service->verify($user, 'email', $code);
        $this->expectException(ValidationException::class);
        $service->verify($user, 'email', $code);
    }

    private function user(string $role = 'buyer'): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'pending', 'phone' => '0917'.random_int(1000000, 9999999), 'verification_required_at' => now(), 'email_verified_at' => null, 'phone_verified_at' => null]);
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
