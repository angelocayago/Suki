<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerController;
use App\Http\Middleware\EnsureBuyerApi;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/buyer/home', [BuyerController::class, 'home']);
Route::get('/products', [BuyerController::class, 'products']);
Route::get('/products/{slug}', [BuyerController::class, 'product']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('buyer')->middleware(EnsureBuyerApi::class)->group(function (): void {
        Route::get('/cart', [BuyerController::class, 'cart']);
        Route::get('/wishlist', [BuyerController::class, 'wishlist']);
        Route::post('/wishlist/{slug}/toggle', [BuyerController::class, 'toggleWishlist']);
        Route::post('/cart/items', [BuyerController::class, 'addCartItem']);
        Route::patch('/cart/items/{item}', [BuyerController::class, 'updateCartItem'])->whereNumber('item');
        Route::delete('/cart/items/{item}', [BuyerController::class, 'removeCartItem'])->whereNumber('item');
        Route::get('/checkout', [BuyerController::class, 'checkoutData']);
        Route::post('/checkout', [BuyerController::class, 'checkout']);
    });
});
