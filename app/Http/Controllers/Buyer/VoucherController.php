<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Services\ShopVoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request, ShopVoucherService $vouchers)
    {
        $tab = $request->validate(['tab' => ['nullable', 'in:available,mine,history']])['tab'] ?? 'available';
        return view('buyer.vouchers.index', $vouchers->listing($request->user(), $tab));
    }

    public function claim(Request $request, int $voucher, ShopVoucherService $vouchers)
    {
        $vouchers->claim($request->user(), $voucher);
        return redirect()->route('buyer.vouchers.index', ['tab' => 'mine'])->with('success', 'Voucher claimed. Checkout will recheck its current availability and limits.');
    }
}
