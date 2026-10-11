<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }


        if (!$user instanceof User || !$user->hasRole('superadmin')) {
            abort(403, 'Unauthorized Access');
        }


        return $next($request);
    }
}