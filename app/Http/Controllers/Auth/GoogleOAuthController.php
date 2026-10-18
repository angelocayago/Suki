<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IdentityNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $intent = $request->string('intent')->toString();
        abort_unless(in_array($intent, ['buyer', 'seller', 'logistics'], true), 404);
        $request->session()->put('google_oauth_intent', $intent);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['login' => 'Google sign-in could not be completed. Please try again.']);
        }

        $raw = $google->user ?? [];
        $verified = filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $email = IdentityNormalizer::email((string) $google->getEmail());
        if (! $verified || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $google->getId()) {
            return redirect()->route('login')->withErrors(['login' => 'Google did not return a verified email address.']);
        }

        $linked = User::query()->where('oauth_provider', 'google')->where('oauth_provider_user_id', (string) $google->getId())->first();
        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
        if ($linked && $byEmail && $linked->id !== $byEmail->id) {
            return redirect()->route('login')->withErrors(['login' => 'This Google account is already linked to another account.']);
        }

        $user = $linked ?: $byEmail;
        if (! $user) {
            return $this->beginOnboarding($request, $email, (string) $google->getId(), $raw);
        }

        if (! $linked && $user->oauth_provider !== null) {
            return redirect()->route('login')->withErrors(['login' => 'This account is already linked to another identity.']);
        }

        if ($user->isRider() || $user->is_suspended || in_array($user->status, ['inactive', 'suspended'], true)) {
            return redirect()->route('login')->withErrors(['login' => 'This account is suspended or inactive and cannot use Google sign-in.']);
        }

        $intent = $request->session()->get('google_oauth_intent');
        if (! $linked && $intent && ! $this->matchesIntent($user, $intent)) {
            return redirect()->route('login')->withErrors(['login' => 'This email already belongs to a different account type. Please use that account\'s sign-in flow.']);
        }

        if (! $linked && $user->isBuyer() && ($user->status !== 'active' || ! $user->hasVerifiedContacts() || ! $this->buyerProfileIsComplete($user))) {
            return $this->beginOnboarding($request, $email, (string) $google->getId(), $raw, $user->id);
        }

        if (! $linked && ($user->isSeller() || $user->isLogistics()) && ($user->status !== 'active' || ! $user->hasVerifiedContacts())) {
            $user = $this->linkExisting($user, (string) $google->getId());
            if (! $user) {
                return redirect()->route('login')->withErrors(['login' => 'This Google account is already linked to another account.']);
            }
            $request->session()->forget('google_oauth_intent');
            return redirect()->route('login')->with('success', 'Google verified your email. Your application remains pending administrator approval.');
        }

        if ($user->status !== 'active' || ! $user->hasVerifiedContacts()) {
            return redirect()->route('login')->withErrors(['login' => 'This account is not eligible for Google sign-in.']);
        }

        if (! $linked) {
            $user = $this->linkExisting($user, (string) $google->getId());
            if (! $user) {
                return redirect()->route('login')->withErrors(['login' => 'This Google account is already linked to another account.']);
            }
        }
        auth()->login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route($user->isBuyer() ? 'buyer.home' : ($user->isSeller() ? 'seller.dashboard' : 'logistics.dashboard')));
    }

    private function beginOnboarding(Request $request, string $email, string $providerUserId, array $raw, ?int $resumeUserId = null): RedirectResponse
    {
        $intent = $request->session()->pull('google_oauth_intent', 'buyer');
        if (! in_array($intent, ['buyer', 'seller', 'logistics'], true)) {
            return redirect()->route('login')->withErrors(['login' => 'Google sign-in could not determine a valid registration type.']);
        }
        $identity = [
            'intent' => $intent, 'email' => $email, 'provider_user_id' => $providerUserId,
            'given_name' => (string) ($raw['given_name'] ?? ''), 'family_name' => (string) ($raw['family_name'] ?? ''),
            'issued_at' => now()->timestamp,
        ];
        if ($resumeUserId) $identity['resume_user_id'] = $resumeUserId;
        $request->session()->put('google_registration', $identity);
        return redirect()->route($intent === 'seller' ? 'seller.register' : ($intent === 'logistics' ? 'logistics.register' : 'register.buyer'))
            ->with('success', $resumeUserId ? 'Google verified your email. Complete your Buyer account to continue.' : 'Google verified your email. Complete the required registration details.');
    }

    private function matchesIntent(User $user, string $intent): bool
    {
        return match ($intent) {
            'buyer' => $user->isBuyer(),
            'seller' => $user->isSeller(),
            'logistics' => $user->isLogistics(),
            default => false,
        };
    }

    private function buyerProfileIsComplete(User $user): bool
    {
        return filled($user->first_name) && filled($user->last_name) && filled($user->sex)
            && $user->birthday !== null && filled($user->phone) && filled($user->government_id)
            && $user->addresses()->exists();
    }

    private function linkExisting(User $user, string $providerUserId): ?User
    {
        try {
            return DB::transaction(function () use ($user, $providerUserId) {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($locked->oauth_provider !== null && ($locked->oauth_provider !== 'google' || $locked->oauth_provider_user_id !== $providerUserId)) return null;
                $locked->update(['oauth_provider' => 'google', 'oauth_provider_user_id' => $providerUserId, 'email_verified_at' => $locked->email_verified_at ?? now()]);
                return $locked;
            });
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'users_oauth_identity_unique') && ! str_contains($exception->getMessage(), 'users.oauth_provider, users.oauth_provider_user_id')) throw $exception;
            return null;
        }
    }
}
