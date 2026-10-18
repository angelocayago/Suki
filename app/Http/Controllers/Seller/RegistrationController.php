<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\Category;
use App\Services\SellerOnboardingService;
use App\Services\AccountVerificationService;
use App\Services\GoogleRegistrationService;
use App\Services\PhilippineAddressService;
use App\Exceptions\VerificationDeliveryException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function create(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return view('seller.register', compact('categories'));
    }

    public function store(
        SellerRegistrationRequest $request,
        SellerOnboardingService $onboarding,
        AccountVerificationService $verification,
        GoogleRegistrationService $google,
        PhilippineAddressService $addresses
    ): RedirectResponse {
        $data = $request->validated();
        try {
            $data = array_merge($data, $addresses->resolve($data));
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        $google->assertMatches($request, $data['email'], 'seller');
        $application = $onboarding->register($data, fn ($user) => $google->linkIfPending($request, $user, 'seller'));
        $request->session()->put('verification_user_id', $application->user_id);
        if ($application->user->oauth_provider === 'google') {
            $google->clearPending($request);
            $request->session()->forget('verification_user_id');
            return redirect()->route('login')->with('success', 'Google verified your email. Your application remains pending administrator approval.');
        }
        try {
            $verification->send($application->user, 'email', false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your application was saved, but we could not deliver the verification code. Please use Resend code.');
        }

        return redirect()
            ->route('verification.contact.show')
            ->with('success', 'Application saved. Verify your email before administrator review.');
    }
}
