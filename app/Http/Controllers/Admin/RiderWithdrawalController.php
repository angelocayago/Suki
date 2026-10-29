<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\RiderWithdrawal;
use App\Services\RiderWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class RiderWithdrawalController extends Controller {
    public function index(){ $withdrawals=RiderWithdrawal::with(['rider','wallet'])->latest()->paginate(25); return view('admin.rider-withdrawals.index',compact('withdrawals')); }
    public function markPaid(Request $request,RiderWithdrawal $withdrawal,RiderWalletService $service){ $d=$request->validate(['transaction_reference'=>['required','string','max:200'],'admin_notes'=>['nullable','string','max:1000'],'confirmed_sent'=>['accepted']]); $service->markWithdrawalPaid($withdrawal,$d['transaction_reference'],$d['admin_notes']??null,Auth::id()); return back()->with('success','Rider withdrawal marked as paid.'); }
    public function reject(Request $request,RiderWithdrawal $withdrawal,RiderWalletService $service){ $d=$request->validate(['admin_notes'=>['required','string','max:1000']]); $service->rejectWithdrawal($withdrawal,$d['admin_notes'],Auth::id()); return back()->with('success','Rider withdrawal rejected and funds returned.'); }
}
