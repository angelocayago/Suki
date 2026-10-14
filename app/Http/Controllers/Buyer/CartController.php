<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CheckoutService $checkout): View
    {
        return view('buyer.cart', $checkout->cartSummary($request->user()));
    }
}
