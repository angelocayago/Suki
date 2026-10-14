<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BuyerEmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class BuyerEmailVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->buyerFromSession($request);

        if ($user === null) {
            return redirect()
                ->route('register.buyer')
                ->withErrors([
                    'email' => 'Start buyer registration to verify an email address.',
                ]);
        }

        if ($user->email_verified_at !== null) {
            $request->session()->forget([
                'buyer_verification_user_id',
                'buyer_verification_email',
            ]);

            return redirect()
                ->route('login')
                ->with('success', 'This email address has already been verified.');
        }

        return view('auth.verify-buyer-email', [
            'email' => $user->email,
        ]);
    }

    public function verify(
        Request $request,
        BuyerEmailVerificationService $verificationService,
    ): RedirectResponse {
        $user = $this->buyerFromSession($request);

        if ($user === null) {
            return redirect()->route('register.buyer');
        }

        $validated = $request->validate([
            'otp' => ['bail', 'required', 'digits:6', 'regex:/\A[0-9]{6}\z/'],
        ], [
            'otp.required' => 'Enter the 6-digit verification code.',
            'otp.digits' => 'The verification code must be exactly 6 digits.',
            'otp.regex' => 'The verification code must contain numbers only.',
        ]);

        $result = $verificationService->verify($user, $validated['otp']);

        if ($result === false) {
            return redirect()
                ->route('buyer.verification.show')
                ->withErrors([
                    'otp' => 'That verification code is incorrect.',
                ]);
        }

        if ($result === null) {
            return redirect()
                ->route('buyer.verification.show')
                ->withErrors([
                    'otp' => 'That verification code is invalid or expired. Request a new code.',
                ]);
        }

        $request->session()->forget([
            'buyer_verification_user_id',
            'buyer_verification_email',
        ]);

        $message = $user->status === 'pending'
            ? 'Your email is verified. Your account is awaiting administrator approval before you can log in.'
            : 'Your email has been verified. You may now log in.';

        return redirect()
            ->route('login')
            ->with('success', $message);
    }

    public function resend(
        Request $request,
        BuyerEmailVerificationService $verificationService,
    ): RedirectResponse {
        $user = $this->buyerFromSession($request);

        if ($user === null) {
            return redirect()->route('register.buyer');
        }

        if ($user->email_verified_at !== null) {
            $request->session()->forget([
                'buyer_verification_user_id',
                'buyer_verification_email',
            ]);

            return redirect()
                ->route('login')
                ->with('success', 'This email address has already been verified.');
        }

        try {
            $verificationService->issue($user);
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return redirect()
                ->route('buyer.verification.show')
                ->withErrors([
                    'email' => 'We could not send a verification code. Please try again.',
                ]);
        }

        return redirect()
            ->route('buyer.verification.show')
            ->with('status', 'A new verification code has been sent to your email address.');
    }

    private function buyerFromSession(Request $request): ?User
    {
        $userId = $request->session()->get('buyer_verification_user_id');

        if (is_numeric($userId) && (int) $userId > 0) {
            $user = User::query()
                ->where('role', 'buyer')
                ->find((int) $userId);

            if ($user !== null) {
                return $user;
            }
        }

        $email = $request->session()->get('buyer_verification_email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        $user = User::query()
            ->where('role', 'buyer')
            ->where('email', $email)
            ->first();

        if ($user !== null) {
            $request->session()->put('buyer_verification_user_id', $user->id);
            $request->session()->forget('buyer_verification_email');
        }

        return $user;
    }
}
