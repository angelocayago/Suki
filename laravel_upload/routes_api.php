<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerController;
use App\Http\Controllers\Api\BuyerOrdersController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Middleware\EnsureBuyerApi;
use App\Http\Middleware\EnsureRiderApi;
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

        Route::get('/orders', [BuyerOrdersController::class, 'index']);
        Route::get('/orders/{order}', [BuyerOrdersController::class, 'show']);
        Route::get('/orders/{order}/seller-orders/{sellerOrder}/tracking', [BuyerOrdersController::class, 'tracking']);
        Route::post('/orders/{order}/cancel', [BuyerOrdersController::class, 'cancel']);
        Route::post('/orders/{order}/confirm-receipt', [BuyerOrdersController::class, 'confirmReceipt']);
        Route::post('/orders/{order}/buy-again', [CartController::class, 'buyAgain']);
    });

    Route::prefix('rider')->middleware(EnsureRiderApi::class)->group(function (): void {
        Route::get('/dashboard', [RiderController::class, 'dashboard']);
        Route::get('/deliveries', [RiderController::class, 'deliveries']);
        Route::get('/history', [RiderController::class, 'history']);
        Route::get('/earnings', [RiderController::class, 'earnings']);
        Route::get('/reports', [RiderController::class, 'reports']);
        Route::get('/money', [RiderController::class, 'money']);
        Route::get('/cod-remittances', [RiderController::class, 'codRemittances']);
        Route::post('/cod-remittances/{remittance}', [RiderController::class, 'submitRemittance'])->whereNumber('remittance');
        Route::get('/profile', [RiderController::class, 'profile']);
        Route::patch('/profile', [RiderController::class, 'updateProfile']);
        Route::post('/profile/password', [RiderController::class, 'changePassword']);
        Route::post('/availability', [RiderController::class, 'availability']);

        Route::post('/erp-orders/{sellerOrder}/accept-pickup', [RiderController::class, 'acceptPickup'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/decline-pickup', [RiderController::class, 'declinePickup'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/accept-delivery', [RiderController::class, 'acceptDelivery'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/decline-delivery', [RiderController::class, 'declineDelivery'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/status', [RiderController::class, 'updateStatus'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/delivery-proof', [RiderController::class, 'completeDelivery'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/failed-delivery', [RiderController::class, 'failedDelivery'])->whereNumber('sellerOrder');
        Route::post('/erp-orders/{sellerOrder}/delivery-issue', [RiderController::class, 'deliveryIssue'])->whereNumber('sellerOrder');

        Route::post('/shipment-scanner/resolve', [RiderController::class, 'resolveShipment']);
        Route::post('/shipment-scanner/{shipment}/action', [RiderController::class, 'scannerAction'])->whereNumber('shipment');
        Route::post('/shipments/{shipment}/location', [RiderController::class, 'location'])->whereNumber('shipment');
    });
});
