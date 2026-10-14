<?php

namespace App\Services;

use App\Mail\BuyerEmailVerificationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class BuyerEmailVerificationService
{
    public function issue(User $user): void
    {
        $expiresInMinutes = (int) config('auth.buyer_otp_expiration_minutes');

        if ($expiresInMinutes < 1) {
            throw new RuntimeException(
                'Buyer email verification expiration must be at least one minute.'
            );
        }

        $otp = DB::transaction(function () use ($user, $expiresInMinutes): string {
            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $verification = $user->emailVerification()
                ->lockForUpdate()
                ->first();

            do {
                $otp = (string) random_int(100000, 999999);
            } while (
                $verification !== null &&
                Hash::check($otp, $verification->otp_hash)
            );

            $attributes = [
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes($expiresInMinutes),
            ];

            if ($verification === null) {
                $user->emailVerification()->create($attributes);
            } else {
                $verification->update($attributes);
            }

            return $otp;
        });

        Mail::to($user->email)->send(
            new BuyerEmailVerificationMail($otp, $expiresInMinutes)
        );
    }

    public function verify(User $user, string $otp): ?bool
    {
        return DB::transaction(function () use ($user, $otp): ?bool {
            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $verification = $user->emailVerification()
                ->lockForUpdate()
                ->first();

            if (
                $verification === null ||
                $verification->expires_at->lessThanOrEqualTo(now())
            ) {
                return null;
            }

            if (! Hash::check($otp, $verification->otp_hash)) {
                return false;
            }

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            $verification->delete();

            return true;
        });
    }
}
