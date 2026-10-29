<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        if (!$user->isActive()) {
            abort(403, 'Your administrator account is not active.');
        }

        return $next($request);
    }
}
