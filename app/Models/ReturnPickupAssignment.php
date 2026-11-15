<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnPickupAssignment extends Model
{
    protected $fillable = ['return_refund_request_id','logistics_provider_id','return_hub_id','rider_id','assigned_by','status','close_reason','responded_at','picked_up_at','completed_at'];
    protected function casts(): array { return ['responded_at'=>'datetime','picked_up_at'=>'datetime','completed_at'=>'datetime']; }
    public function request(): BelongsTo { return $this->belongsTo(ReturnRefundRequest::class, 'return_refund_request_id'); }
    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function provider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
    public function hub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'return_hub_id'); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function scopeActive($query) { return $query->whereIn('status', ['ASSIGNED','ACCEPTED']); }
}
