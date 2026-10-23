<?php

use App\Http\Controllers\Admin\RiderWithdrawalController;
use App\Http\Controllers\Rider\WalletController as RiderWalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'rider'])->prefix('rider')->name('rider.')->group(function () {
    Route::get('/wallet', [RiderWalletController::class, 'index'])
        ->name('wallet.index');

    Route::post('/wallet/withdraw', [RiderWalletController::class, 'withdraw'])
        ->name('wallet.withdraw');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/rider-withdrawals', [RiderWithdrawalController::class, 'index'])
        ->name('rider-withdrawals.index');

    Route::post('/rider-withdrawals/{withdrawal}/paid', [RiderWithdrawalController::class, 'markPaid'])
        ->name('rider-withdrawals.paid');

    Route::post('/rider-withdrawals/{withdrawal}/reject', [RiderWithdrawalController::class, 'reject'])
        ->name('rider-withdrawals.reject');
});
