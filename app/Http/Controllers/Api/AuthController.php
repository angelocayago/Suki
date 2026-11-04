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

        if (! $user->isBuyer()) {
            return response()->json(['success' => false, 'message' => 'This account does not have Buyer access.'], 403);
        }

        if ($user->is_suspended || $user->status !== 'active' || ! $user->hasVerifiedContacts()) {
            return response()->json(['success' => false, 'message' => 'Verify your account and wait for approval before signing in.'], 403);
        }

        return response()->json([
            'success' => true,
            'token' => $user->createToken('mobile-buyer')->plainTextToken,
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

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id, 'name' => $user->full_name,
            'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'phone' => $user->phone, 'role' => $user->role,
        ];
    }
}
