<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BuyerController;
use App\Http\Controllers\Api\V1\BuyerAddressController;
use App\Http\Controllers\Api\V1\BuyerOrdersController;
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
        Route::get('/addresses', [BuyerAddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [BuyerAddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses/{address}', [BuyerAddressController::class, 'show'])->whereNumber('address')->name('addresses.show');
        Route::match(['put', 'patch'], '/addresses/{address}', [BuyerAddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
        Route::delete('/addresses/{address}', [BuyerAddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
        Route::match(['post', 'patch'], '/addresses/{address}/default', [BuyerAddressController::class, 'setDefault'])->whereNumber('address')->name('addresses.default');

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

        Route::get('/orders', [BuyerOrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [BuyerOrdersController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/tracking', [BuyerOrdersController::class, 'tracking'])->name('orders.tracking');
        Route::get('/orders/{order}/seller-orders/{sellerOrder}/tracking', [BuyerOrdersController::class, 'sellerOrderTracking'])
            ->whereNumber('sellerOrder')->name('orders.seller-orders.tracking');
        Route::post('/orders/{order}/cancel', [BuyerOrdersController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/received', [BuyerOrdersController::class, 'received'])->name('orders.received');
        Route::post('/orders/{order}/confirm-receipt', [BuyerOrdersController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
        Route::post('/orders/{order}/buy-again', [BuyerOrdersController::class, 'buyAgain'])->name('orders.buy-again');
    });
});
