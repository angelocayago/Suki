<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerVoucher;
use App\Support\SellerAccount;
use App\Support\SellerVouchers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        return view('seller.vouchers.index', SellerVouchers::page(SellerAccount::current(), $request));
    }

    public function create()
    {
        $seller = SellerAccount::current();

        return view('seller.vouchers.form', ['voucher' => new SellerVoucher, 'products' => SellerVouchers::products($seller)->orderBy('name')->get(), 'selected' => []]);
    }

    public function store(Request $request)
    {
        try {
            $voucher = SellerVouchers::save(SellerAccount::current(), $request);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'This voucher code is already reserved.']);
        }

        return redirect()->route('seller.vouchers.show', $voucher->id)->with('success', 'Voucher created. Buyer redemption is not connected.');
    }

    public function show(Request $request, string $voucher)
    {
        $seller = SellerAccount::current();
        $voucher = SellerVouchers::owned($seller, $voucher);
        $voucher->load(['products' => fn ($q) => $q->where('products.seller_id', $seller->id)]);
        $data = $request->validate(['eligible_subtotal' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/D', 'numeric', 'min:0', 'max:42949672.95']]);
        $subtotal = SellerVouchers::minor($data['eligible_subtotal'] ?? '500');

        return view('seller.vouchers.show', ['voucher' => $voucher, 'availability' => SellerVouchers::availability($voucher), 'subtotal' => $subtotal, 'estimate' => $voucher->estimate($subtotal)]);
    }

    public function edit(string $voucher)
    {
        $seller = SellerAccount::current();
        $voucher = SellerVouchers::owned($seller, $voucher);
        abort_if($voucher->archived_at !== null, 409, 'Archived vouchers are read-only.');
        $saved = $voucher->products()->where('products.seller_id', $seller->id)->get();

        return view('seller.vouchers.form', ['voucher' => $voucher, 'products' => SellerVouchers::products($seller)->get()->merge($saved)->unique('id')->sortBy('name'), 'selected' => $saved->pluck('id')->all()]);
    }

    public function update(Request $request, string $voucher)
    {
        $seller = SellerAccount::current();
        $voucher = SellerVouchers::owned($seller, $voucher);
        try {
            SellerVouchers::save($seller, $request, $voucher);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['code' => 'This voucher code is already reserved.']);
        }

        return redirect()->route('seller.vouchers.show', $voucher->id)->with('success', 'Voucher saved.');
    }

    private function change(string $voucher, string $action)
    {
        $seller = SellerAccount::current();
        SellerVouchers::lifecycle($seller, SellerVouchers::owned($seller, $voucher), $action);

        return back()->with('success', 'Voucher configuration updated.');
    }

    public function activate(string $voucher)
    {
        return $this->change($voucher, 'activate');
    }

    public function pause(string $voucher)
    {
        return $this->change($voucher, 'pause');
    }

    public function archive(string $voucher)
    {
        return $this->change($voucher, 'archive');
    }
}
