<?php

use App\Http\Controllers\Api\Internal\AutoConfirmReceivedCronController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/internal/auto-confirm-received',
    AutoConfirmReceivedCronController::class
)->middleware('throttle:10,1');
