<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\BuyerEmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SellerEmailVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    // Buyer Email OTP Verification
    Route::get('buyer/verify-email', [
        BuyerEmailVerificationController::class,
        'show'
    ])->name('buyer.email.verify');

    Route::post('buyer/verify-email', [
        BuyerEmailVerificationController::class,
        'verify'
    ])->middleware('throttle:10,1')->name('buyer.email.verify.submit');

    Route::post('buyer/resend-otp', [
        BuyerEmailVerificationController::class,
        'resend'
    ])->middleware('throttle:3,5')->name('buyer.email.otp.resend');


    // Seller Email OTP Verification
    Route::get('seller/verify-email', [
        SellerEmailVerificationController::class,
        'show'
    ])->name('seller.email.verify');

    Route::post('seller/verify-email', [
        SellerEmailVerificationController::class,
        'verify'
    ])->middleware('throttle:10,1')->name('seller.email.verify.submit');

    Route::post('seller/resend-otp', [
        SellerEmailVerificationController::class,
        'resend'
    ])->middleware('throttle:3,5')->name('seller.email.otp.resend');


    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
