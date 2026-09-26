<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }


        if (!auth()->user()->hasRole('superadmin')) {
            abort(403, 'Unauthorized Access');
        }


        return $next($request);
    }
}