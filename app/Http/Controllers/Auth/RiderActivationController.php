<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Services\AccountVerificationService;
use App\Services\RiderActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class RiderActivationController extends Controller
{
    public function show(string $token, RiderActivationService $service): View { $service->userFor($token); return view('auth.rider-activation', compact('token')); }
    public function store(Request $request, string $token, RiderActivationService $activation, AccountVerificationService $verification): RedirectResponse
    {
        $data = $request->validate(['password' => ['required','string','min:8','confirmed']]);
        $user = $activation->activate($token, $data['password']); $verification->send($user, 'sms', false);
        $request->session()->put('verification_user_id', $user->id);
        return redirect()->route('verification.contact.show')->with('success', 'Password saved. Verify your mobile number to activate Rider access.');
    }
}
