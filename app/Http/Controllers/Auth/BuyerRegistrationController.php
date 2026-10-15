<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyerRegistrationRequest;
use App\Exceptions\VerificationDeliveryException;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuyerRegistrationController extends Controller
{
    public function store(BuyerRegistrationRequest $request, AccountVerificationService $verification): RedirectResponse
    {
        $data = $request->validated();
        $path = $request->file('government_id')->store('application-documents/buyers', 'local');

        try {
            $user = DB::transaction(function () use ($data, $path): User {
                $name = trim($data['first_name'].' '.($data['middle_initial'] ?? '').' '.$data['last_name']);
                $user = User::query()->create([
                    'role' => 'buyer', 'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                    'middle_initial' => $data['middle_initial'] ?? null, 'sex' => $data['sex'], 'birthday' => $data['birthday'],
                    'name' => $name, 'email' => $data['email'], 'phone' => $data['phone'], 'status' => 'pending',
                    'is_suspended' => false, 'government_id' => $path, 'verification_required_at' => now(), 'password' => $data['password'],
                ]);
                $user->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => 'buyer'])->id]);
                $user->addresses()->create([
                    'label' => 'Home', 'recipient' => $name, 'phone' => $data['phone'], 'line1' => $data['street_address'],
                    'barangay' => $data['barangay'], 'city' => $data['municipality'], 'province' => $data['province'],
                    'postal_code' => null, 'is_default' => true,
                ]);
                return $user;
            }, 3);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        $request->session()->put('verification_user_id', $user->id);
        try {
            $verification->send($user, 'email', false);
        } catch (VerificationDeliveryException) {
            return redirect()->route('verification.contact.show')
                ->with('error', 'Your account was created, but we could not send the email code. Please use Resend code.');
        }
        return redirect()->route('verification.contact.show')->with('success', 'Account created. Verify your email, then your mobile number.');
    }
}
