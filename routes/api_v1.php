<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuyerController;
use App\Http\Middleware\EnsureBuyerApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Buyer API v1
|--------------------------------------------------------------------------
|
| This route file intentionally mirrors the established Buyer mobile
| contract beneath /api/v1 without changing the legacy /api endpoints.
| Mutations remain protected by Sanctum and the buyer eligibility policy.
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('auth.login');

Route::get('/buyer/home', [BuyerController::class, 'home'])->name('buyer.home');
Route::get('/products', [BuyerController::class, 'products'])->name('products.index');
Route::get('/products/{slug}', [BuyerController::class, 'product'])->name('products.show');

Route::middleware(['auth:sanctum', EnsureBuyerApi::class])->group(function (): void {
    Route::get('/user', [AuthController::class, 'user'])->name('auth.user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::prefix('buyer')->name('buyer.')->group(function (): void {
        Route::get('/cart', [BuyerController::class, 'cart'])->name('cart.show');
        Route::post('/cart/items', [BuyerController::class, 'addCartItem'])->name('cart.items.store');
        Route::patch('/cart/items/{item}', [BuyerController::class, 'updateCartItem'])
            ->whereNumber('item')
            ->name('cart.items.update');
        Route::delete('/cart/items/{item}', [BuyerController::class, 'removeCartItem'])
            ->whereNumber('item')
            ->name('cart.items.destroy');

        Route::get('/wishlist', [BuyerController::class, 'wishlist'])->name('wishlist.index');
        Route::post('/wishlist/{slug}/toggle', [BuyerController::class, 'toggleWishlist'])->name('wishlist.toggle');

        Route::get('/checkout', [BuyerController::class, 'checkoutData'])->name('checkout.show');
        Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout.store');
    });
});
