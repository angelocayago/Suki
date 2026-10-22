<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReturnRefundEvidence extends Model { protected $fillable=['return_refund_request_id','uploaded_by','path','mime','size','description']; public function request():BelongsTo{return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');} }
