<?php

namespace App\Services;

use App\Models\{ReturnRefundRequest,ReturnWaybill,Shipment,User};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnWaybillService
{
    public function canPrint(ReturnRefundRequest $r): bool
    {
        $r->loadMissing(['item','sellerOrder.seller','order']);
        $so=$r->sellerOrder;$item=$r->item;$order=$r->order;$seller=$so?->seller;
        if(!$r->isSellerReview() || $r->status!=='ADMIN_APPROVED' || $r->seller_recommendation!=='APPROVE'
            || $r->admin_decision || !$r->decided_at || !$r->seller_responded_at || !filled($r->seller_note)
            || !$so || !$item || !$order || !$seller || (int)$r->reviewed_by!==(int)$seller->user_id
            || (int)$r->seller_id!==(int)$seller->id || (int)$item->seller_id!==(int)$seller->user_id
            || (int)$item->seller_order_id!==(int)$so->id || (int)$item->order_id!==(int)$order->id
            || (int)$so->order_id!==(int)$order->id || (int)$r->buyer_id!==(int)$order->buyer_id
            || (int)$r->quantity<1 || (int)$r->quantity!==(int)$item->quantity)return false;
        $final=$r->events()->whereIn('action',['SELLER_APPROVED','SELLER_REJECTED','ADMIN_APPROVED','ADMIN_REJECTED'])->get();
        return $final->count()===1 && $final->first()->action==='SELLER_APPROVED' && (int)$final->first()->actor_id===(int)$seller->user_id;
    }

    public function authorize(User $buyer,ReturnRefundRequest $r): void
    {
        abort_unless($buyer->isBuyer() && (int)$r->buyer_id===(int)$buyer->id,404);
        if(!$this->canPrint($r))$this->invalid('A return waybill is available only after the owning Seller approves this request.');
    }

    private function schemaReady(): void
    {
        if(!Schema::hasTable('return_waybills'))$this->invalid('Return waybill printing is unavailable until its database migration is deployed.');
    }

    public function issue(User $buyer,ReturnRefundRequest $request): ReturnWaybill
    {
        // Ownership is checked before schema queries or any attempt to write.
        $this->authorize($buyer,$request);$this->schemaReady();
        for($attempt=0;$attempt<3;$attempt++){
            try{return DB::transaction(function()use($buyer,$request){
                $r=ReturnRefundRequest::lockForUpdate()->findOrFail($request->id);
                $this->authorize($buyer,$r);
                if($existing=ReturnWaybill::find($r->id))return $existing;
                $order=$r->order;$seller=$r->sellerOrder->seller;$address=$seller->pickupAddress;
                if(!$address || (int)$address->user_id!==(int)$seller->user_id)$this->invalid('The Seller return address is unavailable. Contact the Seller through Chat before printing.');
                foreach(['recipient_name','street_address','barangay','municipality']as$field)if(!filled($order->$field))$this->invalid('The saved order pickup address is incomplete. Contact support before printing a return waybill.');
                foreach(['line1','barangay','city']as$field)if(!filled($address->$field))$this->invalid('The Seller return address is incomplete. Contact the Seller through Chat before printing.');
                if(!filled($seller->name))$this->invalid('The Seller store details are incomplete. Contact support before printing.');
                $pickup=$order->only(['recipient_name','house_number','street_address','barangay','municipality','province','postal_code','region_code','province_code','city_municipality_code','barangay_code']);
                $destination=$address->only(['recipient','line1','barangay','city','province','postal_code','region_code','province_code','city_municipality_code','barangay_code']);
                $destination['store_name']=$seller->name;
                // Separate namespace; do not register this as a normal delivery shipment.
                for($tries=0;$tries<10;$tries++){
                    $reference='RET-'.strtoupper((string)Str::uuid());
                    if(!ReturnWaybill::where('tracking_number',$reference)->exists() && !Shipment::where('tracking_code',$reference)->exists())break;
                    $reference=null;
                }
                if(!$reference)$this->invalid('A unique return reference could not be issued. Please try again.');
                return ReturnWaybill::create(['return_refund_request_id'=>$r->id,'tracking_number'=>$reference,'buyer_pickup'=>$pickup,'seller_return'=>$destination,'created_at'=>now()]);
            },3);}catch(QueryException $e){
                if(!in_array($e->errorInfo[0]??null,['23505','23000'],true))throw $e;
                // A database unique constraint is authoritative, including a rare UUID collision.
                if($attempt===2)$this->invalid('A return reference could not be issued. Please try again.');
            }
        }
        throw new \LogicException('Unreachable waybill issuance state.');
    }

    public function view(User $buyer,ReturnRefundRequest $r): ReturnWaybill
    {
        $this->authorize($buyer,$r);$this->schemaReady();
        return ReturnWaybill::findOrFail($r->id);
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['waybill'=>$message]); }
}
