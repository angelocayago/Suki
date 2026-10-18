<?php

namespace App\Services;

use App\Models\User;
use App\Support\IdentityNormalizer;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class GoogleRegistrationService
{
    /**
     * Returns the existing account that the OAuth callback explicitly allowed
     * to resume. The id is server-side session state; the form never selects it.
     */
    public function resumeUser(Request $request, string $intent): ?User
    {
        $identity = $request->session()->get('google_registration');
        if (! is_array($identity) || empty($identity['resume_user_id'])) {
            return null;
        }

        $this->assertMatches($request, (string) ($identity['email'] ?? ''), $intent);

        $user = User::query()->find($identity['resume_user_id']);
        if (! $user
            || ! $user->hasRole($intent)
            || $user->isRider()
            || $user->is_suspended
            || in_array($user->status, ['inactive', 'suspended'], true)
            || $user->oauth_provider !== null
            || IdentityNormalizer::email($user->email) !== IdentityNormalizer::email($identity['email'] ?? null)) {
            throw ValidationException::withMessages(['email' => 'This Google registration session is no longer eligible to resume. Please start again.']);
        }

        return $user;
    }

    public function assertMatches(Request $request, string $email, string $intent): void
    {
        $identity = $request->session()->get('google_registration');
        if (! is_array($identity)) return;
        if (($identity['intent'] ?? null) !== $intent || IdentityNormalizer::email($identity['email'] ?? null) !== IdentityNormalizer::email($email) || empty($identity['provider_user_id']) || ! is_int($identity['issued_at'] ?? null) || $identity['issued_at'] > now()->timestamp || $identity['issued_at'] < now()->subMinutes(15)->timestamp) {
            throw ValidationException::withMessages(['email' => 'Google registration details no longer match this form. Please start again.']);
        }
        if (User::query()->where('oauth_provider', 'google')->where('oauth_provider_user_id', $identity['provider_user_id'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This Google account is already linked to another account.']);
        }
    }

    /** Links only the short-lived, server-side identity issued by the OAuth callback. */
    public function linkIfPending(Request $request, User $user, string $intent): bool
    {
        $identity = $request->session()->get('google_registration');
        if (! is_array($identity)) return false;

        $collision = User::query()->where('oauth_provider', 'google')->where('oauth_provider_user_id', $identity['provider_user_id'])->whereKeyNot($user->id)->exists();
        if ($collision) throw ValidationException::withMessages(['email' => 'This Google account is already linked to another account.']);

        try {
            $user->update(['oauth_provider' => 'google', 'oauth_provider_user_id' => $identity['provider_user_id'], 'email_verified_at' => now()]);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'users_oauth_identity_unique') || str_contains($exception->getMessage(), 'users.oauth_provider, users.oauth_provider_user_id')) {
                throw ValidationException::withMessages(['email' => 'This Google account is already linked to another account.']);
            }
            throw $exception;
        }
        return true;
    }

    public function clearPending(Request $request): void
    {
        $request->session()->forget(['google_registration', 'google_oauth_intent']);
    }
}
