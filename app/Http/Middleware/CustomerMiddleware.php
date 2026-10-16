<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->isCustomer()) {
            abort(403, 'Only buyers can access this page.');
        }

        if (!$user->isActive()) {
            abort(403, 'Your buyer account is not active.');
        }

        if ($user->email_verified_at === null) {
            abort(403, 'Please verify your email before accessing your account.');
        }

        return $next($request);
    }
}
