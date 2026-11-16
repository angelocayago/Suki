<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Rider tokens are issued with only the "rider" ability; legacy
        // wildcard Buyer tokens continue to satisfy can('buyer').
        $token = $user?->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken && ! $token->can('buyer')) {
            return new JsonResponse(['message' => 'This token is not authorized for Buyer services.'], 403);
        }

        if (! $user || ! $user->isBuyer()) {
            return new JsonResponse(['message' => 'Buyer access is required.'], 403);
        }

        if ($user->is_suspended || $user->status !== 'active' || ! $user->hasVerifiedContacts()) {
            return new JsonResponse(['message' => 'This account cannot access Buyer services.'], 403);
        }

        return $next($request);
    }
}
