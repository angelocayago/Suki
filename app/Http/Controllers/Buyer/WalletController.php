<?php
namespace App\Http\Controllers\Buyer;
use App\Http\Controllers\Controller;
use App\Models\{BuyerRefundFunding,BuyerWalletEntry,ReturnRefundRequest};
use App\Services\BuyerWalletService;
use Illuminate\Http\Request;

class WalletController extends Controller {
    public function index(Request $request,BuyerWalletService $wallet){
        if(config('suki_wallet.enabled',false)!==true || !$wallet->schemaReady())return response()->view('buyer.wallet.unavailable',[],503)->header('Cache-Control','private, no-store');
        $request->validate(['page'=>'nullable|integer|min:1','funding_page'=>'nullable|integer|min:1','historical_page'=>'nullable|integer|min:1']);$buyer=$request->user();
        $entries=BuyerWalletEntry::where('buyer_id',$buyer->id)->with(['order'=>fn($q)=>$q->where('buyer_id',$buyer->id),'returnRequest'=>fn($q)=>$q->where('buyer_id',$buyer->id)->with(['order'=>fn($o)=>$o->where('buyer_id',$buyer->id)])])->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $fundingQuery=BuyerRefundFunding::where('buyer_id',$buyer->id)->with(['returnRequest'=>fn($q)=>$q->where('buyer_id',$buyer->id),'postedCredit'=>fn($q)=>$q->where('buyer_id',$buyer->id)]);
        if(\Illuminate\Support\Facades\Schema::hasTable('simulation_wallet_backings'))$fundingQuery->with(['simulationBacking'=>fn($q)=>$q->where('buyer_id',$buyer->id)]);
        $fundings=$fundingQuery->orderByDesc('created_at')->orderByDesc('return_refund_request_id')->paginate(15,['*'],'funding_page')->withQueryString();
        // Historical/untracked obligations are display-only. No funding record or credit is inferred.
        $historical=ReturnRefundRequest::where('buyer_id',$buyer->id)->where('status','ADMIN_APPROVED')
            ->whereHas('order',fn($q)=>$q->where('buyer_id',$buyer->id))
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('return_refund_accounting')->whereColumn('return_refund_request_id','return_refund_requests.id')->where('refund_status','REFUND_DUE'))
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('buyer_refund_fundings')->whereColumn('return_refund_request_id','return_refund_requests.id'))
            ->with(['order'=>fn($q)=>$q->where('buyer_id',$buyer->id)])->orderByDesc('decided_at')->orderByDesc('id')->paginate(15,['*'],'historical_page')->withQueryString();
        return response()->view('buyer.wallet.index',['wallet'=>$wallet->summary($buyer),'entries'=>$entries,'fundings'=>$fundings,'historical'=>$historical])->header('Cache-Control','private, no-store');
    }
}
