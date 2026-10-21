<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyerRegistrationRequest;
use App\Exceptions\VerificationDeliveryException;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\GoogleRegistrationService;
use App\Services\PhilippineAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuyerRegistrationController extends Controller
{
    public function store(BuyerRegistrationRequest $request, AccountVerificationService $verification, GoogleRegistrationService $google, PhilippineAddressService $addresses): RedirectResponse
    {
        $data = $request->validated();
        $data = array_merge($data, $addresses->resolve($data));
        $google->assertMatches($request, $data['email'], 'buyer');
        $resumeUser = $google->resumeUser($request, 'buyer');
        $path = $request->file('government_id')->store('application-documents/buyers', 'local');

        try {
            $user = DB::transaction(function () use ($data, $path, $google, $request, $resumeUser): User {
                $name = trim($data['first_name'].' '.($data['middle_initial'] ?? '').' '.$data['last_name']);
                if ($resumeUser) {
                    $user = User::query()->lockForUpdate()->findOrFail($resumeUser->id);
                    if ($user->is_suspended || in_array($user->status, ['inactive', 'suspended'], true)) {
                        abort(403);
                    }
                    $user->update([
                        'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                        'middle_initial' => $data['middle_initial'] ?? null, 'sex' => $data['sex'], 'birthday' => $data['birthday'],
                        'name' => $name, 'phone' => $data['phone'], 'government_id' => $path, 'password' => $data['password'],
                    ]);
                    $address = $user->addresses()->where('is_default', true)->first();
                    ($address ?? $user->addresses()->make(['label' => 'Home', 'is_default' => true]))->fill([
                        'recipient' => $name, 'phone' => $data['phone'], 'line1' => $data['street_address'],
                        'region' => $data['region'], 'region_code' => $data['region_code'], 'barangay' => $data['barangay'], 'barangay_code' => $data['barangay_code'], 'city' => $data['municipality'], 'city_municipality_code' => $data['city_municipality_code'], 'province' => $data['province'], 'province_code' => $data['province_code'], 'postal_code' => null,
                    ])->save();
                    $google->linkIfPending($request, $user, 'buyer');
                    $user->update(['status' => 'active']);
                    return $user;
                }

                $user = User::query()->create([
                    'role' => 'buyer', 'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                    'middle_initial' => $data['middle_initial'] ?? null, 'sex' => $data['sex'], 'birthday' => $data['birthday'],
                    'name' => $name, 'email' => $data['email'], 'phone' => $data['phone'], 'status' => 'pending',
                    'is_suspended' => false, 'government_id' => $path, 'verification_required_at' => now(), 'password' => $data['password'],
                ]);
                $user->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => 'buyer'])->id]);
                $user->addresses()->create([
                    'label' => 'Home', 'recipient' => $name, 'phone' => $data['phone'], 'line1' => $data['street_address'],
                    'region' => $data['region'], 'region_code' => $data['region_code'], 'barangay' => $data['barangay'], 'barangay_code' => $data['barangay_code'], 'city' => $data['municipality'], 'city_municipality_code' => $data['city_municipality_code'], 'province' => $data['province'], 'province_code' => $data['province_code'],
                    'postal_code' => null, 'is_default' => true,
                ]);
                if ($google->linkIfPending($request, $user, 'buyer')) {
                    $user->update(['status' => 'active']);
                }
                return $user;
            }, 3);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $request->session()->put('verification_user_id', $user->id);
        if ($user->oauth_provider === 'google') {
            $google->clearPending($request);
            $request->session()->forget('verification_user_id');
            if ($resumeUser) {
                auth()->login($user, true);
                $request->session()->regenerate();
                return redirect()->route('buyer.home')->with('success', 'Google verified your email. Your Buyer account is active.');
            }
            auth()->login($user, true);
            $request->session()->regenerate();
            return redirect()->route('buyer.home')->with('success', 'Google verified your email. Your Buyer account is active.');
        }
        try {
            $verification->send($user, 'email', false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your account was created, but we could not deliver the verification code. Please use Resend code.');
        }
        return redirect()->route('verification.contact.show')->with('success', 'Account created. A verification code was sent to your email address.');
    }
}
