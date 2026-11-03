<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Voucher;
use App\Support\SellerVouchers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request, SellerVouchers $forms)
    {
        return view('seller.vouchers.index',$forms->listing($this->seller($request),$request));
    }

    public function create(Request $request)
    {
        $seller = $this->seller($request);
        $token = Str::random(40);
        $tokens = $request->session()->get('seller_voucher_forms',[]);
        $tokens[$token] = ['seller_id'=>$seller->id,'voucher_id'=>null];
        $request->session()->put('seller_voucher_forms',array_slice($tokens,-20,null,true));
        return view('seller.vouchers.form',['voucher'=>null,'creationToken'=>$token]);
    }

    public function generate(Request $request, SellerVouchers $forms)
    {
        $this->seller($request);
        return response()->json(['code'=>$forms->generate()])->header('Cache-Control','no-store, private');
    }

    public function store(Request $request, SellerVouchers $forms)
    {
        $seller = $this->seller($request);
        $token = $request->input('creation_token');
        if ($token !== null) {
            if (!is_string($token) || !preg_match('/^[a-zA-Z0-9]{40}$/',$token)) throw ValidationException::withMessages(['creation_token'=>'Please reopen Create Voucher and try again.']);
            $submission = $request->session()->get('seller_voucher_forms.'.$token);
            if (!$submission || (int)$submission['seller_id'] !== (int)$seller->id) throw ValidationException::withMessages(['creation_token'=>'This form has expired. Please reopen Create Voucher.']);
            if ($submission['voucher_id']) return redirect()->route('seller.vouchers.index')->with('success','This voucher was already created.');
        }
        $voucher = $forms->create($seller,$forms->attributes($request));
        if ($token !== null) $request->session()->put('seller_voucher_forms.'.$token.'.voucher_id',$voucher->id);
        return redirect()->route('seller.vouchers.index')->with('success','Shop voucher created successfully.');
    }

    public function edit(Request $request, string $voucher)
    {
        return view('seller.vouchers.form',['voucher'=>$this->owned($request,$voucher),'creationToken'=>null]);
    }

    public function update(Request $request, string $voucher, SellerVouchers $forms)
    {
        $record = $this->owned($request,$voucher);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($record, $forms, $request) {
                $locked = Voucher::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $locked->update($forms->attributes($request,$locked));
                app(\App\Services\ShopVoucherService::class)->notifyFollowers($locked);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code'=>'This voucher code was just taken. Choose a different code.']);
        }
        return redirect()->route('seller.vouchers.index')->with('success','Voucher updated successfully.');
    }

    public function archive(Request $request, string $voucher)
    {
        $record = $this->owned($request,$voucher);
        if (!$record->archived_at) $record->update(['is_active'=>false,'archived_at'=>now()]);
        return redirect()->route('seller.vouchers.index')->with('success','Voucher archived. Existing redemption records are retained.');
    }

    private function seller(Request $request): Seller
    {
        return $request->user()->sellers()->where('status','approved')->firstOrFail();
    }

    private function owned(Request $request, string $voucher): Voucher
    {
        return Voucher::query()->where('scope','SELLER')->where('seller_id',$this->seller($request)->id)->findOrFail($voucher);
    }
}
