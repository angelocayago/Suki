<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $rider = $user?->rider()->with('logisticsProvider', 'logisticsHub')->first();

        if (! $user || ! $user->hasRole('rider') || ! $rider || ! $rider->isOperationallyEligible()) {
            return new JsonResponse(['success' => false, 'message' => 'An active, approved rider account is required.'], 403);
        }

        return $next($request);
    }
}
