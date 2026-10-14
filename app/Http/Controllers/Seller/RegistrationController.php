<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRegistrationRequest;
use App\Models\Category;
use App\Services\SellerOnboardingService;
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
        SellerOnboardingService $onboarding
    ): RedirectResponse {
        $onboarding->register($request->validated());

        return redirect()
            ->route('login')
            ->with('success', 'Your seller application has been submitted. Please wait for administrator approval.');
    }
}
