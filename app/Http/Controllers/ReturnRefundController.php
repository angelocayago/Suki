<?php
namespace App\Http\Controllers;
use App\Models\OrderItem; use App\Models\ReturnRefundEvidence; use App\Models\ReturnRefundRequest; use App\Services\ReturnRefundService; use Illuminate\Http\Request; use Illuminate\Support\Facades\Storage;
class ReturnRefundController extends Controller {
 public function buyerIndex(Request $q){$this->buyer($q);return response()->json(ReturnRefundRequest::where('buyer_id',$q->user()->id)->latest()->get());}
 public function store(Request $q,OrderItem $item,ReturnRefundService $service){
     $this->buyer($q);abort_unless((int)$item->order->buyer_id===(int)$q->user()->id,403);
     if(!app(\App\Services\ReturnRefundWorkflowService::class)->available())throw \Illuminate\Validation\ValidationException::withMessages(['request'=>'Return/refund actions are unavailable pending schema and financial activation review.']);
     $d=$q->validate(['type'=>'required|in:RETURN,REFUND','quantity'=>'required|integer|in:'.$item->quantity,'reason'=>'required|string|max:100','description'=>'required|string|max:5000','confirmed'=>'accepted','file'=>'nullable|file|mimes:jpg,jpeg,png,mp4,webm|mimetypes:image/jpeg,image/png,video/mp4,video/webm|extensions:jpg,jpeg,png,mp4,webm|max:25600']);$path=null;
     try{$r=\Illuminate\Support\Facades\DB::transaction(function()use($q,$item,$d,&$path){
         $r=app(ReturnRefundService::class)->submitForSeller($q->user(),$item,$d);
         if($q->hasFile('file')){$f=$q->file('file');$path=$f->store('return-refund-evidence/'.$r->id,'local');if(!$path)throw new \RuntimeException('Evidence upload failed.');ReturnRefundEvidence::create(['return_refund_request_id'=>$r->id,'uploaded_by'=>$q->user()->id,'path'=>$path,'mime'=>$f->getMimeType(),'size'=>$f->getSize(),'description'=>'Buyer submission photo']);}
         return $r;
     });}catch(\Throwable $e){
         if($path){
             try {if(!Storage::disk('local')->delete($path))\Illuminate\Support\Facades\Log::warning('Private refund submission evidence needs cleanup.');}
             catch(\Throwable $cleanup){\Illuminate\Support\Facades\Log::warning('Private refund submission evidence cleanup failed.');}
         }
         throw $e;
     }
     return $q->expectsJson()?response()->json($r,201):redirect()->route('buyer.return-refunds.show',$r)->with('success','Return / Refund request submitted.');
 }
 public function sellerRespond(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundService $service){$d=$q->validate(['recommendation'=>'required|in:APPROVE,REJECT','note'=>'required|string|max:5000']);$r=$service->sellerRespond($returnRefundRequest,$q->user(),$d);return $q->expectsJson()?response()->json($r):back()->with('success','Seller confirmation/dispute recorded for Admin review.');}
 public function decide(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundService $service){$d=$q->validate(['decision'=>'required|in:APPROVED,REJECTED','reason'=>'required|string|max:5000']);abort_unless($q->user()->isAdmin(),403);if($d['decision']==='APPROVED' && config('returns.backend_enabled',false)===true)app(\App\Services\ReturnRefundWorkflowService::class)->assertApprovable($returnRefundRequest,true);$r=$service->decide($returnRefundRequest,$q->user(),$d['decision'],$d['reason']);return $q->expectsJson()?response()->json($r):back()->with('success','Admin decision recorded. Approval is Refund Due, not payment.');}
 public function review(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundService $service){$d=$q->validate(['action'=>'required|in:EVIDENCE_REQUESTED,ESCALATED','reason'=>'required|string|max:5000']);abort_unless($q->user()->isAdmin(),403);$r=$d['action']==='EVIDENCE_REQUESTED'?$service->requestMoreEvidence($returnRefundRequest,$q->user(),$d['reason']):$service->escalate($returnRefundRequest,$q->user(),$d['reason']);return $q->expectsJson()?response()->json($r):back()->with('success','Return/refund review status updated.');}
 public function authorizeReturn(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundService $service){$d=$q->validate(['reason'=>'required|string|max:5000']);$r=$service->authorizePhysicalReturn($returnRefundRequest,$q->user(),$d['reason']);return $q->expectsJson()?response()->json($r):back()->with('success','Physical return authorized.');}
 public function hubReceive(Request $q,ReturnRefundRequest $returnRefundRequest){$d=$q->validate(['hub_id'=>'required|integer','return_reference'=>'required|string|max:100','note'=>'nullable|string|max:2000']);$provider=\App\Models\LogisticsProvider::where('user_id',$q->user()->id)->firstOrFail();$hub=\App\Models\LogisticsHub::findOrFail($d['hub_id']);$w=app(\App\Services\ReturnRefundWorkflowService::class)->receiveAtHub($returnRefundRequest,$q->user(),$provider,$hub,$d['return_reference'],$d['note']??null);return $q->expectsJson()?response()->json($w):back()->with('success','Return received at barangay hub.');}
 public function hubDispatch(Request $q,ReturnRefundRequest $returnRefundRequest){$d=$q->validate(['hub_id'=>'required|integer','note'=>'nullable|string|max:2000']);$provider=\App\Models\LogisticsProvider::where('user_id',$q->user()->id)->firstOrFail();$hub=\App\Models\LogisticsHub::findOrFail($d['hub_id']);$w=app(\App\Services\ReturnRefundWorkflowService::class)->dispatchToSeller($returnRefundRequest,$q->user(),$provider,$hub,$d['note']??null);return $q->expectsJson()?response()->json($w):back()->with('success','Return dispatched to seller.');}
 public function sellerReceiveReturn(Request $q,ReturnRefundRequest $returnRefundRequest){$w=app(\App\Services\ReturnRefundWorkflowService::class)->receiveBySeller($returnRefundRequest,$q->user(),$q->string('note')->toString());return $q->expectsJson()?response()->json($w):back()->with('success','Returned item received.');}
 public function sellerInspectReturn(Request $q,ReturnRefundRequest $returnRefundRequest){$d=$q->validate(['outcome'=>'required|in:RESTOCKABLE,DAMAGED,INCOMPLETE,WRONG_ITEM,REJECTED_UNDER_POLICY','note'=>'required|string|max:2000']);$w=app(\App\Services\ReturnRefundWorkflowService::class)->inspect($returnRefundRequest,$q->user(),$d['outcome'],$d['note']);return $q->expectsJson()?response()->json($w):back()->with('success','Return inspection recorded.');}
 public function sellerDecide(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundService $service){
     $d=$q->validate(['decision'=>'required|in:APPROVED,REJECTED','reason'=>'required|string|max:5000']);
     $r=$service->sellerDecide($returnRefundRequest,$q->user(),$d['decision'],$d['reason']);
     return $q->expectsJson()?response()->json($r):back()->with('success','Seller decision recorded. Approval does not confirm payment; use Chat to coordinate.');
 }
 public function details(Request $q,ReturnRefundRequest $returnRefundRequest){
     $this->access($q,$returnRefundRequest);abort_unless((int)$returnRefundRequest->buyer_id===(int)$q->user()->id,403);
     $validator=\Illuminate\Support\Facades\Validator::make($q->all(),['method'=>'prohibited','holder'=>'prohibited','account'=>'prohibited','bank'=>'prohibited','parcel_status'=>'required|in:PENDING,SHIPPED','courier'=>'required_if:parcel_status,SHIPPED|nullable|string|max:100','tracking_number'=>'required_if:parcel_status,SHIPPED|nullable|string|max:100','shipped_on'=>'nullable|date|before_or_equal:today']);
     if($validator->fails())return back()->withErrors($validator);
     $d=$validator->validated();
     try{app(\App\Services\ReturnRefundWorkflowService::class)->details($q->user(),$returnRefundRequest,$d);}catch(\Illuminate\Validation\ValidationException $e){return back()->withErrors($e->errors());}
     return back()->with('success','Private refund destination and return parcel details saved. No payment has been made.');
 }
 public function receive(Request $q,ReturnRefundRequest $returnRefundRequest){$d=$q->validate(['inspection_result'=>'required|in:ACCEPTED,MISSING,INCORRECT,DAMAGED','inspection_note'=>'required|string|max:2000']);app(\App\Services\ReturnRefundWorkflowService::class)->receive($q->user(),$returnRefundRequest,$d);return back()->with('success','Returned parcel receipt and inspection recorded; refund remains subject to Admin review.');}
 public function evidence(Request $q,ReturnRefundRequest $returnRefundRequest){
     $this->access($q,$returnRefundRequest);
     $d=$q->validate(['file'=>'required|file|mimes:jpg,jpeg,png,mp4,webm|mimetypes:image/jpeg,image/png,video/mp4,video/webm|extensions:jpg,jpeg,png,mp4,webm|max:25600','description'=>'nullable|string|max:500']);
     $path=null;
     try {
         $evidence=\Illuminate\Support\Facades\DB::transaction(function()use($q,$returnRefundRequest,$d,&$path){
             // Upload holds only this request lock; it never acquires Seller or
             // order locks. Admin finalization must wait and then recheck it.
             $locked=ReturnRefundRequest::lockForUpdate()->findOrFail($returnRefundRequest->id);
             $this->access($q,$locked);
             abort_unless(in_array($locked->status,ReturnRefundRequest::ACTIVE_STATUSES,true),422,'Evidence cannot be added after a final decision.');
             $f=$d['file'];$path=$f->store('return-refund-evidence/'.$locked->id,'local');
             if(!$path)throw new \RuntimeException('Private evidence storage failed.');
             return ReturnRefundEvidence::create(['return_refund_request_id'=>$locked->id,'uploaded_by'=>$q->user()->id,'path'=>$path,'mime'=>$f->getMimeType(),'size'=>$f->getSize(),'description'=>$d['description']??null]);
         }); // No retry: a file write is an external side effect.
         return $q->expectsJson()?response()->json($evidence,201):back()->with('success','Private evidence uploaded.');
     } catch(\Throwable $exception){
         if($path){
             try {if(!Storage::disk('local')->delete($path))\Illuminate\Support\Facades\Log::warning('Private refund evidence needs cleanup.',['request_id'=>$returnRefundRequest->id]);}
             catch(\Throwable $cleanup){report($cleanup);}
         }
         throw $exception;
     }
 }
 public function showEvidence(Request $q,ReturnRefundRequest $returnRefundRequest,ReturnRefundEvidence $evidence){
     $this->access($q,$returnRefundRequest);abort_unless($evidence->return_refund_request_id===$returnRefundRequest->id,404);
     if($returnRefundRequest->isSellerReview())return Storage::disk('local')->response($evidence->path,null,['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
     return Storage::disk('local')->download($evidence->path);
 }
 private function buyer(Request $q):void{abort_unless($q->user()?->isBuyer(),403);} private function access(Request $q,ReturnRefundRequest $r):void{$u=$q->user();abort_unless($u&&((int)$r->buyer_id===(int)$u->id||((int)$r->seller->user_id===(int)$u->id)||$u->isAdmin()),403);}
}
