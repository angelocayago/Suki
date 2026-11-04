<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Exceptions\VerificationDeliveryException;
use App\Models\User;
use App\Services\AccountVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AccountVerificationController extends Controller
{
    public const RIDER_SESSION_SECONDS = 1800;

    public static function beginRiderSession(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put(['verification_user_id' => $user->id, 'rider_verification_started_at' => now()->getTimestamp(), 'rider_verification_channel' => 'email']);
    }

    public function show(Request $request, AccountVerificationService $service): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user->hasVerifiedContacts()) {
            return $this->finished($request, $user);
        }

        return view('auth.verify-contact', [
            'riderOnly' => $user->hasRole('rider'),
            'email' => $this->maskEmail($user->email),
            'phone' => $this->maskPhone($user->phone),
            'channel' => $request->session()->get('rider_verification_channel', 'email'),
            'smsAvailable' => $service->smsAvailable() && (bool) preg_match('/^09\d{9}$/', $user->phone ?? ''),
            'emailAvailable' => $service->emailAvailable(),
            'hasChallenge' => $user->accountVerifications()->where('channel', $request->session()->get('rider_verification_channel', 'email'))->whereNull('verified_at')->exists(),
        ]);
    }

    public function verify(Request $request, AccountVerificationService $service): RedirectResponse
    {
        $user = $this->pendingUser($request);
        // Do not flash submitted OTPs into old-input session storage on failure.
        $validator = Validator::make($request->only('code'), ['code' => ['required', 'digits:6']]);
        if ($validator->fails()) return redirect()->route('verification.contact.show')->withErrors($validator);
        $validated = $validator->validated();
        $channel = $user->hasRole('rider') ? $request->session()->get('rider_verification_channel', 'email') : 'email';
        try {
            $complete = $service->verify($user, $channel, $validated['code']);
        } catch (ValidationException $exception) {
            return redirect()->route('verification.contact.show')->withErrors($exception->errors());
        }

        if ($complete) {
            return $this->finished($request, $user->fresh());
        }

        return redirect()->route('verification.contact.show');
    }

    public function resend(Request $request, AccountVerificationService $service): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if ($user->hasRole('rider') && $user->hasVerifiedContacts()) return $this->finished($request, $user);
        $channel = $user->hasRole('rider') ? $request->validate(['channel' => ['required', 'in:email,sms']])['channel'] : 'email';
        try {
            $service->send($user, $channel);
        } catch (VerificationDeliveryException) {
            return back()->with('error', 'We could not deliver a new verification code. Please try again.');
        }

        if ($user->hasRole('rider')) $request->session()->put('rider_verification_channel', $channel);

        return back()->with('success', 'A new verification code was sent. The previous code is no longer valid.');
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('verification_user_id');
        abort_unless($id, 403, 'Start or resume verification by signing in.');

        $user = User::query()->findOrFail($id);
        abort_if($request->user() && (int) $request->user()->id !== (int) $user->id, 403);
        if ($user->hasRole('rider')) {
            $started = $request->session()->get('rider_verification_started_at');
            if (! is_int($started) || $started > now()->getTimestamp() || now()->getTimestamp() - $started >= self::RIDER_SESSION_SECONDS) {
                $request->session()->forget(['verification_user_id', 'rider_verification_started_at', 'rider_verification_channel']);
                abort(403, 'Your verification session expired. Sign in again to resume.');
            }
            abort_if($user->is_suspended || ! in_array($user->status, ['pending', 'active'], true), 403);
            if ($user->requiresContactVerification() || $user->status !== 'active' || ! $user->rider?->is_active) app(AccountVerificationService::class)->assertRiderReady($user);
        }
        return $user;
    }

    private function finished(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(['verification_user_id', 'rider_verification_started_at', 'rider_verification_channel']);
        if ($user->hasRole('rider')) $request->session()->regenerate();
        $message = $user->hasRole('buyer')
            ? 'Your Buyer account is verified and active. You may now log in.'
            : ($user->hasRole('rider')
                ? 'Your Rider account is verified and active. You may now log in.'
                : 'Contact verification is complete. Your application remains pending administrator approval.');

        return redirect()->route($user->hasRole('rider') ? 'rider.login' : 'login')->with('success', $message);
    }

    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) return 'Unavailable';
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 1).str_repeat('*', max(2, strlen($name) - 1)).'@'.$domain;
    }

    private function maskPhone(?string $phone): string
    {
        if (! $phone || strlen($phone) < 7) return 'Unavailable';
        return substr($phone, 0, 2).'*****'.substr($phone, -4);
    }
}
