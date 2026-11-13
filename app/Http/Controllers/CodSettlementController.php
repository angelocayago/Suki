<?php
namespace App\Http\Controllers;

use App\Models\{LogisticsHub, Seller, ReturnRefundRequest};
use App\Services\{CodRemittanceService, ReturnRefundService};
use Illuminate\Http\Request;

class CodSettlementController extends Controller
{
    public function settle(Request $request, Seller $seller, CodRemittanceService $service)
    {
        $data=$request->validate(['hub_id'=>'required|integer','amount_minor'=>'required|integer|min:1','receiver_name'=>'required|string|max:150','reference'=>'required|string|max:100','note'=>'nullable|string|max:5000']);
        $provider=$request->user()->logisticsProvider; abort_unless($provider,403);
        $service->settleSeller($seller,$request->user(),$provider,LogisticsHub::findOrFail($data['hub_id']),$data['amount_minor'],$data['receiver_name'],$data['reference'],$data['note']??null);
        return back()->with('success','Offline COD seller settlement recorded.');
    }
    public function refund(Request $request, ReturnRefundRequest $returnRefundRequest, ReturnRefundService $service)
    {
        $data=$request->validate(['reference'=>'required|string|max:100','note'=>'required|string|max:5000']);
        $service->recordOfflineCodRefund($returnRefundRequest,$request->user(),$data['reference'],$data['note']);
        return back()->with('success','Offline COD refund payment recorded.');
    }
}
