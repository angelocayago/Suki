<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Label metadata only; no shipment, assignment, charge or tracking lifecycle. */
class ReturnWaybill extends Model
{
    protected $primaryKey='return_refund_request_id';
    public $incrementing=false;
    public $timestamps=false;
    protected $guarded=[];
    protected $hidden=['buyer_pickup','seller_return'];
    protected function casts(): array { return ['buyer_pickup'=>'array','seller_return'=>'array','created_at'=>'datetime']; }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id'); }
}
