<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rider mobile API gate. Requires the rider role, an operationally eligible
 * Rider profile (active, verified, approved provider, active hub), and — for
 * bearer tokens — a token that was explicitly issued with the "rider" ability.
 * Legacy wildcard Buyer tokens therefore never reach Rider routes.
 */
class EnsureRiderApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! in_array('rider', (array) $token->abilities, true)) {
            return new JsonResponse(['success' => false, 'message' => 'This token is not authorized for Rider services.'], 403);
        }

        $rider = $user?->rider()->with(['user', 'logisticsProvider', 'logisticsHub'])->first();
        if (! $user || ! $user->hasRole('rider') || ! $rider || ! $rider->isOperationallyEligible()) {
            return new JsonResponse(['success' => false, 'message' => 'An active, approved rider account is required.'], 403);
        }

        $request->attributes->set('apiRider', $rider);

        return $next($request);
    }
}
