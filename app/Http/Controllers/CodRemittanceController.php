<?php
namespace App\Http\Controllers;
use App\Models\{RiderCodRemittance,LogisticsCodRemittance};
use App\Services\CodRemittanceService;
use Illuminate\Http\{Request,RedirectResponse};
use Illuminate\View\View;

class CodRemittanceController extends Controller
{
 public function rider(Request $request): View { $rider=$request->user()->rider; abort_unless($rider,403); $rows=RiderCodRemittance::where('rider_id',$rider->id)->with('shipment.sellerOrder.order')->latest()->get(); return view('rider.cod-remittances',['rows'=>$rows,'summary'=>$this->sum($rows)]); }
 public function riderSubmit(Request $request,RiderCodRemittance $remittance,CodRemittanceService $service): RedirectResponse { $data=$request->validate(['reference'=>['nullable','string','max:100']]); $service->submitRider($remittance,$request->user(),$data['reference']??null); return back()->with('success','COD remittance submitted for Logistics review.'); }
 public function logistics(Request $request): View { $provider=$request->user()->logisticsProvider; abort_unless($provider,403); $riders=RiderCodRemittance::where('logistics_provider_id',$provider->id)->with('rider.user','shipment.sellerOrder.order')->latest()->get(); $upstream=LogisticsCodRemittance::where('logistics_provider_id',$provider->id)->with('items.riderRemittance.shipment')->latest()->get(); return view('logistics.cod-remittances',compact('riders','upstream')); }
 public function logisticsReview(Request $request,RiderCodRemittance $remittance,CodRemittanceService $service): RedirectResponse { $data=$request->validate(['status'=>['required','in:ACCEPTED,REJECTED'],'reason'=>['nullable','string','max:1000']]); $service->reviewRider($remittance,$request->user(),$data['status'],$data['reason']??null); return back()->with('success','Rider remittance reviewed.'); }
 public function logisticsSubmit(Request $request,CodRemittanceService $service): RedirectResponse { $data=$request->validate(['remittances'=>['required','array','min:1'],'remittances.*'=>['integer'],'reference'=>['nullable','string','max:100']]); $service->submitLogistics($request->user(),$data['remittances'],$data['reference']??null); return back()->with('success','COD remittance submitted to Admin.'); }
 public function admin(): View { return view('admin.cod-remittances.index',['rows'=>LogisticsCodRemittance::with('provider','items.riderRemittance.shipment.sellerOrder')->latest()->paginate(30)]); }
 public function adminReview(Request $request,LogisticsCodRemittance $remittance,CodRemittanceService $service): RedirectResponse { $data=$request->validate(['status'=>['required','in:ACCEPTED,REJECTED'],'reason'=>['nullable','string','max:1000']]); $service->reviewLogistics($remittance,$request->user(),$data['status'],$data['reason']??null); return back()->with('success','Logistics COD remittance reviewed.'); }
 private function sum($rows): array { return ['outstanding'=>(int)$rows->where('status','OUTSTANDING')->sum('expected_minor'),'submitted'=>(int)$rows->where('status','SUBMITTED')->sum('submitted_minor'),'accepted'=>(int)$rows->where('status','ACCEPTED')->sum('accepted_minor')]; }
}
