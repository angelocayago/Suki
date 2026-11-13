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
    public function create(Request $request,\App\Models\OrderItem $item)
    {
        abort_unless((int)$item->order->buyer_id===(int)$request->user()->id,403);
        $existing=ReturnRefundRequest::where('order_item_id',$item->id)->first();
        if($existing)return redirect()->route('buyer.return-refunds.show',$existing);
        $workflow=app(\App\Services\ReturnRefundWorkflowService::class);$available=$workflow->available();$calculation=null;$eligibility=null;
        try{$calculation=app(\App\Services\ReturnRefundService::class)->inspectPurchase($request->user(),$item);}catch(\Illuminate\Validation\ValidationException $e){$eligibility=collect($e->errors())->flatten()->first();}
        return view('buyer.returns.create',compact('item','available','calculation','eligibility'));
    }

    private function journey(ReturnRefundRequest $r,string $role): array
    {
        $service=app(\App\Services\ReturnRefundWorkflowService::class);$workflow=$service->find($r);$ready=$service->available($r->isSimplified() ? $r->workflow_mode : ReturnRefundRequest::FINANCIAL);$approvable=false;
        if($ready){try{$service->assertApprovable($r,true);$approvable=true;}catch(\Illuminate\Validation\ValidationException $e){}}
        $refundQuote=null;
        if($r->item){try{$refundQuote=app(\App\Services\ReturnRefundAllocationService::class)->calculate($r->item,(int)$r->quantity)['buyer_refund_due_minor'];}catch(\Illuminate\Validation\ValidationException $e){}}
        return compact('workflow','role','ready','approvable','refundQuote');
    }
    public function buyerIndex(Request $request)
    {
        $requests = $this->requests()->where('buyer_id', $request->user()->id)->latest()->get();

        return view('buyer.returns.index', compact('requests'));
    }

    public function buyerShow(Request $request, ReturnRefundRequest $returnRefundRequest)
    {
        $returnRefundRequest = $this->requests()->where('buyer_id', $request->user()->id)->findOrFail($returnRefundRequest->id);
        $evidence = $this->evidence($returnRefundRequest);

        return response()->view('buyer.returns.show', compact('returnRefundRequest', 'evidence')+$this->journey($returnRefundRequest,'buyer'))->header('Cache-Control','private, no-store');
    }

    public function sellerIndex(Request $request)
    {
        $seller = $this->seller($request);
        $criteria = SellerReturnFilters::criteria($request);
        $owned = $this->requests()->where('seller_id', $seller->id)
            ->whereHas('sellerOrder', fn ($query) => $query->where('seller_id', $seller->id));
        $counts = (clone $owned)->reorder()->select('status')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $sellerCounts = \Illuminate\Support\Facades\Schema::hasColumn('return_refund_requests','workflow_mode')
            ? (clone $owned)->where('workflow_mode',ReturnRefundRequest::SELLER_REVIEW)->reorder()->select('status')->selectRaw('COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate','status') : collect();
        $summary = [
            'Total requests' => (int) $counts->sum(),
            'Pending review' => (int) $counts->only(['SUBMITTED', 'SELLER_RESPONDED', 'UNDER_REVIEW'])->sum(),
            'Approved by Seller' => (int) ($sellerCounts['ADMIN_APPROVED'] ?? 0),
            'Rejected by Seller' => (int) ($sellerCounts['ADMIN_REJECTED'] ?? 0),
            'Approved by Admin' => (int) ($counts['ADMIN_APPROVED'] ?? 0) - (int) ($sellerCounts['ADMIN_APPROVED'] ?? 0),
            'Rejected by Admin' => (int) ($counts['ADMIN_REJECTED'] ?? 0) - (int) ($sellerCounts['ADMIN_REJECTED'] ?? 0),
        ];
        $workflows=collect();
        if(app(\App\Services\ReturnRefundWorkflowService::class)->schemaReady()){
            $pending=\App\Models\ReturnRefundWorkflow::where('parcel_status','SHIPPED')->whereIn('return_refund_request_id',(clone $owned)->whereIn('status',ReturnRefundRequest::ACTIVE_STATUSES)->reorder()->select('return_refund_requests.id'));
            $summary['Awaiting parcel confirmation']=$pending->count();
        }
        $requests = SellerReturnFilters::apply($owned, $criteria)->latest()->orderByDesc('id')
            ->paginate(15)->appends(array_filter($criteria, fn ($value, $key) => $value !== '' && $value !== 'all' && $value !== 'any', ARRAY_FILTER_USE_BOTH));
        $statuses = SellerReturnFilters::STATUSES;
        $dates = SellerReturnFilters::DATES;
        if(app(\App\Services\ReturnRefundWorkflowService::class)->schemaReady())$workflows=\App\Models\ReturnRefundWorkflow::whereIn('return_refund_request_id',$requests->pluck('id'))->get(['id','return_refund_request_id','parcel_status'])->keyBy('return_refund_request_id');

        return view('seller.returns.index', compact('requests', 'criteria', 'summary', 'statuses', 'dates','workflows'));
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

        return response()->view('seller.returns.show', compact('returnRefundRequest', 'evidence', 'statuses')+$this->journey($returnRefundRequest,'seller'))->header('Cache-Control','private, no-store');
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
        $accounting = $returnRefundRequest->isAdminReview() ? null : DB::table('return_refund_accounting')->where('return_refund_request_id', $returnRefundRequest->id)->first();

        return response()->view('admin.returns.show', compact('returnRefundRequest', 'evidence', 'accounting')+$this->journey($returnRefundRequest,'admin'))->header('Cache-Control','private, no-store');
    }

    private function requests()
    {
        return ReturnRefundRequest::query()->with(['buyer', 'seller', 'order', 'sellerOrder', 'item'])->withCount('evidence');
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
