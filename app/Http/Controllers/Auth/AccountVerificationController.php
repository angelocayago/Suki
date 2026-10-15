<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user->hasVerifiedContacts()) {
            return $this->finished($request, $user);
        }

        return view('auth.verify-contact', [
            'channel' => $user->email_verified_at ? 'sms' : 'email',
            'destination' => $user->email_verified_at ? $this->maskPhone($user->phone) : $this->maskEmail($user->email),
        ]);
    }

    public function verify(Request $request, AccountVerificationService $service): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $channel = $user->email_verified_at ? 'sms' : 'email';
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $complete = $service->verify($user, $channel, $validated['code']);

        if ($complete) {
            return $this->finished($request, $user->fresh());
        }

        $service->send($user->fresh(), 'sms', false);

        return redirect()->route('verification.contact.show')
            ->with('success', 'Email verified. We sent a verification code to your mobile number.');
    }

    public function resend(Request $request, AccountVerificationService $service): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $service->send($user, $user->email_verified_at ? 'sms' : 'email');

        return back()->with('success', 'A new verification code was sent. The previous code is no longer valid.');
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('verification_user_id');
        abort_unless($id, 403, 'Start or resume verification by signing in.');

        return User::query()->findOrFail($id);
    }

    private function finished(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('verification_user_id');
        $message = $user->hasRole('buyer')
            ? 'Your Buyer account is verified and active. You may now log in.'
            : ($user->hasRole('rider')
                ? 'Your Rider account is verified and active. You may now log in.'
                : 'Contact verification is complete. Your application remains pending administrator approval.');

        return redirect()->route($user->hasRole('rider') ? 'rider.login' : 'login')->with('success', $message);
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 1).str_repeat('*', max(2, strlen($name) - 1)).'@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 4).'***'.substr($phone, -4);
    }
}
