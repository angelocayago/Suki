<?php

namespace App\Services;

use App\Models\{LogisticsHub,LogisticsProvider,ReturnPickupAssignment,ReturnRefundEvent,ReturnRefundRequest,ReturnRefundWorkflow,Rider,Seller,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reverse logistics is request-scoped. It never reuses an outbound shipment assignment. */
class ReturnPickupWorkflowService
{
    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }

    public function assign(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, Rider $rider): ReturnPickupAssignment
    {
        return DB::transaction(function () use ($request,$actor,$provider,$rider) {
            abort_unless($actor->hasRole('logistics') && $actor->status === 'active' && ! $actor->is_suspended && (int) $provider->user_id === (int) $actor->id && $provider->status === 'approved', 403);
            $r = ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
            $w = ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'RETURN_AUTHORIZED' || $w->logistics_state !== 'FOR_PICKUP' || (int) $w->logistics_provider_id !== (int) $provider->id || ! $w->returnHub?->is_active) $this->invalid('request','This return is not ready for rider pickup assignment.');
            $this->assertEligible($r,$w,$provider,$rider);
            $existing = ReturnPickupAssignment::where('return_refund_request_id',$r->id)->active()->lockForUpdate()->first();
            if ($existing && (int) $existing->rider_id === (int) $rider->id) return $existing;
            if ($existing && $existing->status === 'ACCEPTED') $this->invalid('rider_id','Pickup cannot be reassigned after rider acceptance.');
            if ($existing) $existing->update(['status'=>'CANCELLED','close_reason'=>'Reassigned by Logistics.']);
            $assignment = ReturnPickupAssignment::create(['return_refund_request_id'=>$r->id,'logistics_provider_id'=>$provider->id,'return_hub_id'=>$w->return_hub_id,'rider_id'=>$rider->id,'assigned_by'=>$actor->id]);
            $this->event($r,$actor,'RETURN_PICKUP_ASSIGNED',['assignment_id'=>$assignment->id,'rider_id'=>$rider->id]);
            return $assignment->fresh();
        },3);
    }

    public function respond(ReturnPickupAssignment $assignment, User $actor, bool $accept, ?string $reason = null): ReturnPickupAssignment
    {
        return DB::transaction(function () use ($assignment,$actor,$accept,$reason) {
            $a=ReturnPickupAssignment::lockForUpdate()->findOrFail($assignment->id); $r=$a->request()->lockForUpdate()->firstOrFail();
            $this->assignedRider($a, $actor);
            if ($a->status === ($accept?'ACCEPTED':'DECLINED')) return $a;
            if ($a->status !== 'ASSIGNED' || $r->status !== 'RETURN_AUTHORIZED') $this->invalid('assignment','This return pickup assignment can no longer be answered.');
            // A later suspension, provider deactivation, or hub deactivation must
            // not turn a previously assigned Rider into an authorized pickup actor.
            if ($accept) $this->assertAssignedRiderOperational($a, $actor);
            if (! $accept && ! filled($reason)) $this->invalid('reason','A decline reason is required.');
            $a->update(['status'=>$accept?'ACCEPTED':'DECLINED','responded_at'=>now(),'close_reason'=>$accept?null:trim((string)$reason)]);
            $this->event($r,$actor,$accept?'RETURN_PICKUP_ACCEPTED':'RETURN_PICKUP_DECLINED',['assignment_id'=>$a->id,'reason'=>trim((string)$reason)]);
            return $a->fresh();
        },3);
    }

    public function pickup(ReturnRefundRequest $request, User $actor, string $reference): ReturnRefundWorkflow
    {
        return $this->move($request,$actor,'FOR_PICKUP','PICKED_UP',$reference);
    }
    public function receiveAtHub(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, LogisticsHub $hub, string $reference): ReturnRefundWorkflow
    {
        return $this->move($request,$actor,'PICKED_UP','RECEIVED_AT_RETURN_HUB',$reference,$provider,$hub);
    }
    public function dispatch(ReturnRefundRequest $request, User $actor, LogisticsProvider $provider, LogisticsHub $hub): ReturnRefundWorkflow
    {
        return $this->move($request,$actor,'RECEIVED_AT_RETURN_HUB','IN_TRANSIT_TO_SELLER',null,$provider,$hub);
    }
    public function deliverToSeller(ReturnRefundRequest $request, User $actor): ReturnRefundWorkflow
    {
        return $this->move($request,$actor,'IN_TRANSIT_TO_SELLER','DELIVERED_TO_SELLER');
    }

    private function move(ReturnRefundRequest $request, User $actor, string $from, string $to, ?string $reference = null, ?LogisticsProvider $provider = null, ?LogisticsHub $hub = null): ReturnRefundWorkflow
    {
        return DB::transaction(function () use ($request,$actor,$from,$to,$reference,$provider,$hub) {
            $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id); $w=ReturnRefundWorkflow::where('return_refund_request_id',$r->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'RETURN_AUTHORIZED') $this->invalid('request','Return pickup is not authorized.');
            if ($w->logistics_state === $to) return $w;
            if ($w->logistics_state !== $from) $this->invalid('state','Invalid return logistics transition.');
            $a=ReturnPickupAssignment::where('return_refund_request_id',$r->id)->active()->lockForUpdate()->first();
            if (in_array($to,['PICKED_UP','DELIVERED_TO_SELLER'],true)) {
                abort_unless($a && $a->status==='ACCEPTED',403);
                $this->assertAssignedRiderOperational($a, $actor);
            }
            if ($reference !== null && !hash_equals((string)$w->return_reference,trim($reference))) $this->invalid('return_reference','Invalid return reference.');
            if ($provider) abort_unless($actor->hasRole('logistics') && (int)$provider->user_id===(int)$actor->id && (int)$w->logistics_provider_id===(int)$provider->id && $hub && $hub->is_active && (int)$hub->id===(int)$w->return_hub_id && (int)$hub->logistics_provider_id===(int)$provider->id,403);
            $changes=['logistics_state'=>$to];
            if($to==='PICKED_UP') $changes += ['picked_up_by'=>$actor->id,'picked_up_at'=>now()];
            if($to==='RECEIVED_AT_RETURN_HUB') $changes += ['hub_received_by'=>$actor->id,'hub_received_at'=>now()];
            if($to==='IN_TRANSIT_TO_SELLER') $changes += ['dispatched_by'=>$actor->id,'dispatched_at'=>now()];
            if($to==='DELIVERED_TO_SELLER') { $changes += ['delivered_to_seller_by'=>$actor->id,'delivered_to_seller_at'=>now()]; if($a) $a->update(['status'=>'COMPLETED','completed_at'=>now()]); }
            $w->update($changes); $this->event($r,$actor,$to,['from'=>$from,'to'=>$to,'hub_id'=>$hub?->id]); return $w->fresh();
        },3);
    }

    private function assertEligible(ReturnRefundRequest $r, ReturnRefundWorkflow $w, LogisticsProvider $provider, Rider $rider): void
    {
        $rider->loadMissing(['user','logisticsProvider','logisticsHub','serviceAreas']);
        if ((int)$rider->logistics_provider_id !== (int)$provider->id || ! $rider->isOperationallyAvailable() || ! $rider->approved_at || ! $rider->logisticsHub?->is_active || (int)$rider->logistics_hub_id !== (int)$w->return_hub_id || $rider->assignments()->active()->count() >= max(1,(int)($rider->active_assignment_capacity ?: 5)) || ReturnPickupAssignment::where('rider_id',$rider->id)->active()->exists()) $this->invalid('rider_id','The selected rider is not eligible for this return pickup.');
        $order=$r->order; if(!$order || !$rider->serviceAreas()->where('is_active',true)->where('logistics_provider_id',$provider->id)->where('region_code',$order->region_code)->where('city_municipality_code',$order->city_municipality_code)->where(fn($q)=>$q->whereNull('barangay_code')->orWhere('barangay_code',$order->barangay_code))->exists()) $this->invalid('rider_id','The selected rider is not eligible for the buyer pickup service area.');
    }

    private function assignedRider(ReturnPickupAssignment $assignment, User $actor): Rider
    {
        $rider = Rider::query()
            ->with(['user', 'logisticsProvider', 'logisticsHub'])
            ->lockForUpdate()
            ->findOrFail($assignment->rider_id);

        abort_unless($actor->hasRole('rider') && (int) $rider->user_id === (int) $actor->id, 403);

        return $rider;
    }

    private function assertAssignedRiderOperational(ReturnPickupAssignment $assignment, User $actor): Rider
    {
        $rider = $this->assignedRider($assignment, $actor);

        if (! $rider->isOperationallyEligible()
            || (int) $rider->logistics_provider_id !== (int) $assignment->logistics_provider_id
            || (int) $rider->logistics_hub_id !== (int) $assignment->return_hub_id) {
            $this->invalid('assignment', 'This rider is no longer eligible for the return pickup assignment.');
        }

        return $rider;
    }
    private function event(ReturnRefundRequest $r, User $actor, string $action, array $data=[]): void { ReturnRefundEvent::create(['return_refund_request_id'=>$r->id,'actor_id'=>$actor->id,'action'=>$action,'data'=>$data]); }
}
