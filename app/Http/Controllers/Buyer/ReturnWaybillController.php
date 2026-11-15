<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\ReturnRefundRequest;
use App\Services\ReturnWaybillService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReturnWaybillController extends Controller
{
    public function store(Request $request,ReturnRefundRequest $returnRefundRequest,ReturnWaybillService $service)
    {
        try{$service->issue($request->user(),$returnRefundRequest);}
        catch(ValidationException $e){return redirect()->route('buyer.return-refunds.show',$returnRefundRequest)->withErrors($e->errors());}
        return redirect()->route('buyer.return-refunds.waybill.show',$returnRefundRequest);
    }

    public function show(Request $request,ReturnRefundRequest $returnRefundRequest,ReturnWaybillService $service)
    {
        try{$waybill=$service->view($request->user(),$returnRefundRequest);}
        catch(ValidationException $e){return redirect()->route('buyer.return-refunds.show',$returnRefundRequest)->withErrors($e->errors());}
        return response()->view('buyer.returns.waybill',['waybill'=>$waybill,'return'=>$returnRefundRequest])
            ->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow');
    }
}
