<?php
namespace App\Http\Controllers\Buyer;
use App\Http\Controllers\Controller;
use App\Models\{BuyerRefundFunding,BuyerWalletEntry};
use App\Services\BuyerWalletService;
use Illuminate\Http\Request;

class WalletController extends Controller {
    public function index(Request $request,BuyerWalletService $wallet){
        if(config('suki_wallet.enabled',false)!==true || !$wallet->schemaReady())return response()->view('buyer.wallet.unavailable',[],503)->header('Cache-Control','private, no-store');
        $request->validate(['page'=>'nullable|integer|min:1','funding_page'=>'nullable|integer|min:1']);$buyer=$request->user();
        $entries=BuyerWalletEntry::where('buyer_id',$buyer->id)->with(['order'=>fn($q)=>$q->where('buyer_id',$buyer->id),'returnRequest'=>fn($q)=>$q->where('buyer_id',$buyer->id)])->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $fundings=BuyerRefundFunding::where('buyer_id',$buyer->id)->with(['returnRequest'=>fn($q)=>$q->where('buyer_id',$buyer->id)])->orderByDesc('created_at')->orderByDesc('return_refund_request_id')->paginate(15,['*'],'funding_page')->withQueryString();
        return response()->view('buyer.wallet.index',['wallet'=>$wallet->summary($buyer),'entries'=>$entries,'fundings'=>$fundings])->header('Cache-Control','private, no-store');
    }
}
