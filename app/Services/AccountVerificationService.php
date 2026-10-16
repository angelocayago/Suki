<?php

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\VerificationDeliveryException;
use App\Mail\AccountVerificationOtpMail;
use App\Models\AccountVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountVerificationService
{
    public const EXPIRES_MINUTES = 5;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Issue one registration challenge through both required channels. The
     * challenge is persisted only after both transports accept the same code.
     */
    public function sendUnified(User $user, bool $enforceCooldown = true): void
    {
        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $enforceCooldown): void {
            $verification = $this->challenge($user, 'unified', true);
            if ($enforceCooldown && $verification?->last_sent_at?->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
                throw ValidationException::withMessages(['code' => 'Please wait before requesting another code.']);
            }
        }, 3);

        try {
            Mail::to($user->email)->send(new AccountVerificationOtpMail($code));
            $this->sms->send($user->phone, "Your SUKI SHOP verification code is {$code}. It expires in 5 minutes.");
        } catch (\Throwable $exception) {
            Log::warning('Unified verification code delivery failed.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            // A provider cannot retract a partial external delivery. By not
            // persisting the candidate, it can never be used to verify.
            throw new VerificationDeliveryException('contact');
        }

        DB::transaction(function () use ($user, $code): void {
            AccountVerification::query()->updateOrCreate(
                ['user_id' => $user->id, 'channel' => 'unified'],
                [
                    'code_hash' => $this->hash($user, 'unified', $code),
                    'attempts' => 0,
                    'max_attempts' => self::MAX_ATTEMPTS,
                    'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
                    'last_sent_at' => now(),
                    'verified_at' => null,
                ]
            );
        }, 3);
    }

    /** Atomically verifies both contact fields for public registrations. */
    public function verifyUnified(User $user, string $code): bool
    {
        $result = DB::transaction(function () use ($user, $code): string {
            $verification = $this->challenge($user, 'unified', true);
            if (! $verification || $verification->verified_at) return 'invalid';
            if ($verification->expires_at->isPast()) return 'expired';
            if ($verification->attempts >= $verification->max_attempts) return 'limited';
            if (! hash_equals($verification->code_hash, $this->hash($user, 'unified', $code))) {
                $verification->increment('attempts');
                return 'wrong';
            }

            $now = now();
            $verification->update(['verified_at' => $now]);
            $user->update(['email_verified_at' => $now, 'phone_verified_at' => $now]);
            if ($user->hasRole('buyer')) $user->update(['status' => 'active']);

            return 'complete';
        }, 3);

        $this->throwForResult($result);

        return true;
    }

    public function send(User $user, string $channel, bool $enforceCooldown = true): void
    {
        $this->assertChannel($channel);
        $code = (string) random_int(100000, 999999);

        // Check cooldown before transport. A failed delivery deliberately leaves
        // any prior usable code intact instead of locking the user out.
        DB::transaction(function () use ($user, $channel, $enforceCooldown): void {
            $verification = $this->challenge($user, $channel, true);

            if ($enforceCooldown && $verification?->last_sent_at?->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
                throw ValidationException::withMessages(['code' => 'Please wait before requesting another code.']);
            }

        }, 3);

        try {
            if ($channel === 'email') {
                Mail::to($user->email)->send(new AccountVerificationOtpMail($code));
            } else {
                $this->sms->send($user->phone, "Your SUKI SHOP verification code is {$code}. It expires in 5 minutes.");
            }
        } catch (\Throwable $exception) {
            Log::warning('Verification code delivery failed.', [
                'user_id' => $user->id,
                'channel' => $channel,
                'exception' => $exception::class,
            ]);

            throw new VerificationDeliveryException($channel);
        }

        DB::transaction(function () use ($user, $channel, $code): void {
            AccountVerification::query()->whereBelongsTo($user)->where('channel', $channel)->lockForUpdate()->first();

            AccountVerification::query()->updateOrCreate(
                ['user_id' => $user->id, 'channel' => $channel],
                [
                    'code_hash' => $this->hash($user, $channel, $code),
                    'attempts' => 0,
                    'max_attempts' => self::MAX_ATTEMPTS,
                    'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
                    'last_sent_at' => now(),
                    'verified_at' => null,
                ]
            );
        }, 3);
    }

    public function verify(User $user, string $channel, string $code): bool
    {
        $this->assertChannel($channel);

        $result = DB::transaction(function () use ($user, $channel, $code): string {
            $verification = $this->challenge($user, $channel, true);

            if (! $verification || $verification->verified_at) {
                return 'invalid';
            }
            if ($verification->expires_at->isPast()) {
                return 'expired';
            }
            if ($verification->attempts >= $verification->max_attempts) {
                return 'limited';
            }

            if (! hash_equals($verification->code_hash, $this->hash($user, $channel, $code))) {
                $verification->increment('attempts');
                return 'wrong';
            }

            $verification->update(['verified_at' => now()]);
            $user->update([$channel === 'email' ? 'email_verified_at' : 'phone_verified_at' => now()]);
            $user->refresh();

            if ($user->hasVerifiedContacts() && $user->hasRole('buyer')) {
                $user->update(['status' => 'active']);
            }

            if ($user->hasVerifiedContacts() && $user->hasRole('rider') && $user->rider?->approved_at) {
                $user->update(['status' => 'active']);
                $user->rider->update(['is_active' => true]);
            }

            return $user->fresh()->hasVerifiedContacts() ? 'complete' : 'verified';
        }, 3);

        $this->throwForResult($result);

        return $result === 'complete';
    }

    private function throwForResult(string $result): void
    {
        $messages = [
            'invalid' => 'This verification code is invalid or has already been used.',
            'expired' => 'This verification code has expired.',
            'limited' => 'Too many incorrect attempts. Request a new code.',
            'wrong' => 'The verification code is incorrect.',
        ];
        if (isset($messages[$result])) {
            throw ValidationException::withMessages(['code' => $messages[$result]]);
        }
    }

    private function challenge(User $user, string $channel, bool $lock = false): ?AccountVerification
    {
        $query = AccountVerification::query()->whereBelongsTo($user)->where('channel', $channel);
        return $lock ? $query->lockForUpdate()->first() : $query->first();
    }

    private function hash(User $user, string $channel, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$channel.'|'.$code, (string) config('app.key'));
    }

    private function assertChannel(string $channel): void
    {
        abort_unless(in_array($channel, ['email', 'sms'], true), 404);
    }
}
