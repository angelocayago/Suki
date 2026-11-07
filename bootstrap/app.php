<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureAccountApproved;
use App\Http\Middleware\EnsureAccountNotSuspended;
use App\Http\Middleware\RequireRole;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'approved' => EnsureAccountApproved::class,
            'not_suspended' => EnsureAccountNotSuspended::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Format only Buyer mutation JSON; keep authorization and HTTP statuses intact.
        $exceptions->render(function (\Throwable $error, \Illuminate\Http\Request $request) {
            if (!$request->expectsJson() || !$request->routeIs('buyer.cart.add', 'buyer.cart.update', 'buyer.cart.increase', 'buyer.cart.decrease', 'buyer.cart.remove', 'buyer.cart.clear', 'buyer.wishlist.add', 'buyer.wishlist.remove', 'buyer.wishlist.toggle', 'buyer.wishlist.move-to-cart')) return null;
            $status = $error instanceof \Illuminate\Auth\AuthenticationException ? 401 : ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $error->getStatusCode() : 500);
            if ($error instanceof \Illuminate\Validation\ValidationException) return null;
            [$message, $code] = match ($status) {
                401 => ['Please log in to add items to your cart.', 'auth_required'],
                403 => ['This account does not have access to the Buyer portal.', 'forbidden'],
                404 => ['Not found.', 'not_found'],
                419 => ['Your session expired. Please refresh the page or log in again.', 'session_expired'],
                default => ['Something went wrong. Please try again.', 'server_error'],
            };
            return response()->json(['success' => false, 'message' => $message, 'errors' => (object) [], 'code' => $code], $status);
        });
    })->create();
