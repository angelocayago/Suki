<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\Category;
use App\Services\SellerOnboardingService;
use App\Services\AccountVerificationService;
use App\Exceptions\VerificationDeliveryException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

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
        AccountVerificationService $verification
    ): RedirectResponse {
        $application = $onboarding->register($request->validated());
        $request->session()->put('verification_user_id', $application->user_id);
        try {
            $verification->sendUnified($application->user, false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your application was saved, but we could not deliver the verification code. Please use Resend code.');
        }

        return redirect()
            ->route('verification.contact.show')
            ->with('success', 'Application saved. Verify your email and mobile number before administrator review.');
    }
}
