<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\RiderActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class RiderActivationController extends Controller
{
    public function show(string $token, RiderActivationService $service): View { $service->userFor($token); return view('auth.rider-activation', compact('token')); }
    public function store(Request $request, string $token, RiderActivationService $activation): RedirectResponse
    {
        $data = $request->validate(['password' => ['required','string','min:8','confirmed']]);
        abort_if($request->user(), 403, 'Sign out before activating a Rider account.');
        $user = $activation->activate($token, $data['password']);
        AccountVerificationController::beginRiderSession($request, $user);
        return redirect()->route('verification.contact.show')->with('success', 'Password saved. Choose Email OTP or SMS OTP and request a code to finish verification.');
    }
}
