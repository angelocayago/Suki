<?php

namespace App\Http\Controllers;

use App\Models\ReturnRefundEvidence;
use App\Models\ReturnRefundRequest;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnRefundUiController extends Controller
{
    public function buyerIndex(Request $request)
    {
        $requests = $this->requests()->where('buyer_id', $request->user()->id)->latest()->get();

        return view('buyer.returns.index', compact('requests'));
    }

    public function buyerShow(Request $request, ReturnRefundRequest $returnRefundRequest)
    {
        $returnRefundRequest = $this->requests()->where('buyer_id', $request->user()->id)->findOrFail($returnRefundRequest->id);
        $evidence = $this->evidence($returnRefundRequest);

        return view('buyer.returns.show', compact('returnRefundRequest', 'evidence'));
    }

    public function sellerIndex(Request $request)
    {
        $seller = $this->seller($request);
        $requests = $this->requests()->where('seller_id', $seller->id)->latest()->get();

        return view('seller.returns.index', compact('requests'));
    }

    public function sellerShow(Request $request, ReturnRefundRequest $returnRefundRequest)
    {
        $seller = $this->seller($request);
        $returnRefundRequest = $this->requests()->where('seller_id', $seller->id)->findOrFail($returnRefundRequest->id);
        $evidence = $this->evidence($returnRefundRequest);

        return view('seller.returns.show', compact('returnRefundRequest', 'evidence'));
    }

    public function adminIndex()
    {
        $requests = $this->requests()->latest()->get();

        return view('admin.returns.index', compact('requests'));
    }

    public function adminShow(ReturnRefundRequest $returnRefundRequest)
    {
        $returnRefundRequest = $this->requests()->findOrFail($returnRefundRequest->id);
        $evidence = $this->evidence($returnRefundRequest);
        $accounting = DB::table('return_refund_accounting')->where('return_refund_request_id', $returnRefundRequest->id)->first();

        return view('admin.returns.show', compact('returnRefundRequest', 'evidence', 'accounting'));
    }

    private function requests()
    {
        return ReturnRefundRequest::query()->with(['buyer', 'seller', 'order', 'sellerOrder', 'item']);
    }

    private function evidence(ReturnRefundRequest $returnRefundRequest)
    {
        return ReturnRefundEvidence::query()->where('return_refund_request_id', $returnRefundRequest->id)->latest()->get();
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()->where('user_id', $request->user()->id)->firstOrFail();
    }
}
