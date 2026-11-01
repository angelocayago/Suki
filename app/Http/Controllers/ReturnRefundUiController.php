<?php

namespace App\Http\Controllers;

use App\Models\ReturnRefundEvidence;
use App\Models\ReturnRefundRequest;
use App\Models\Seller;
use App\Support\SellerReturnFilters;
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
        $criteria = SellerReturnFilters::criteria($request);
        $owned = $this->requests()->where('seller_id', $seller->id)
            ->whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id));
        $counts = (clone $owned)->reorder()->select('status')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $summary = [
            'Total requests' => (int) $counts->sum(),
            'Pending review' => (int) $counts->only(['SUBMITTED', 'SELLER_RESPONDED', 'UNDER_REVIEW'])->sum(),
            'Approved by Admin' => (int) ($counts['ADMIN_APPROVED'] ?? 0),
            'Rejected by Admin' => (int) ($counts['ADMIN_REJECTED'] ?? 0),
        ];
        $requests = SellerReturnFilters::apply($owned, $criteria)->latest()->orderByDesc('id')
            ->paginate(15)->appends(array_filter($criteria, fn ($value, $key) => $value !== '' && $value !== 'all' && $value !== 'any', ARRAY_FILTER_USE_BOTH));
        $statuses = SellerReturnFilters::STATUSES;
        $dates = SellerReturnFilters::DATES;

        return view('seller.returns.index', compact('requests', 'criteria', 'summary', 'statuses', 'dates'));
    }

    public function sellerShow(Request $request, ReturnRefundRequest $returnRefundRequest)
    {
        $seller = $this->seller($request);
        $returnRefundRequest = $this->requests()->where('seller_id', $seller->id)
            ->whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id))
            ->with(['events' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->findOrFail($returnRefundRequest->id);
        $evidence = $this->evidence($returnRefundRequest);
        $statuses = SellerReturnFilters::STATUSES;

        return view('seller.returns.show', compact('returnRefundRequest', 'evidence', 'statuses'));
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
