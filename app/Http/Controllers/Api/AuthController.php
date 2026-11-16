<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IdentityNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $identity = trim($credentials['login']);
        $email = IdentityNormalizer::email($identity);
        $phone = IdentityNormalizer::philippinePhone($identity);
        if (preg_match('/^639\d{9}$/', $identity)) {
            $phone = '0'.substr($identity, 2);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])
            ->orWhere('phone', $phone)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'The email/phone or password is incorrect.'], 401);
        }

        $portal = $this->mobilePortal($user);
        if ($portal === null) {
            return response()->json(['success' => false, 'message' => $this->allowsRiderLogin()
                ? 'This account does not have mobile Buyer or Rider access.'
                : 'This account does not have Buyer access.'], 403);
        }

        if ($user->is_suspended || $user->status !== 'active' || ! $user->hasVerifiedContacts()) {
            return response()->json(['success' => false, 'message' => 'Verify your account and wait for approval before signing in.'], 403);
        }

        if ($portal === 'rider') {
            $rider = $user->rider()->with(['user', 'logisticsProvider', 'logisticsHub'])->first();
            if (! $rider || ! $rider->isOperationallyEligible()) {
                return response()->json(['success' => false, 'message' => 'Your rider profile is not active with an approved logistics provider.'], 403);
            }
            // Rider tokens carry only the rider ability and expire, so they can
            // never satisfy Buyer routes and are not valid indefinitely.
            $token = $user->createToken('mobile-rider', ['rider'], now()->addDays(30))->plainTextToken;
        } else {
            $token = $user->createToken('mobile-buyer', ['buyer'])->plainTextToken;
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        } else {
            // Sanctum can also authenticate the existing web session.
            auth('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
        return response()->json(['success' => true, 'message' => 'Signed out successfully.']);
    }

    /** Legacy /api serves Buyer and Rider mobile clients; /api/v1 stays Buyer-only. */
    protected function allowsRiderLogin(): bool
    {
        return true;
    }

    /** The primary role column decides the portal for multi-role accounts. */
    private function mobilePortal(User $user): ?string
    {
        $rider = $this->allowsRiderLogin() && $user->isRider();
        if ($rider && ($user->role === 'rider' || ! $user->isBuyer())) return 'rider';
        return $user->isBuyer() ? 'buyer' : null;
    }

    private function userPayload(User $user): array
    {
        $token = $user->currentAccessToken();
        $role = $token instanceof \Laravel\Sanctum\PersonalAccessToken && in_array('rider', (array) $token->abilities, true)
            ? 'rider'
            : ($token instanceof \Laravel\Sanctum\PersonalAccessToken ? ($user->isBuyer() ? 'buyer' : $user->role) : ($this->mobilePortal($user) ?? $user->role));

        return [
            'id' => $user->id, 'name' => $user->full_name,
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'phone' => $user->phone, 'role' => $role,
        ];
    }
}
