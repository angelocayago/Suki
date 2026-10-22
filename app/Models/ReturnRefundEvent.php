<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ReturnRefundEvent extends Model { protected $fillable=['return_refund_request_id','actor_id','action','data']; protected function casts():array{return ['data'=>'array'];} public function request():BelongsTo{return $this->belongsTo(ReturnRefundRequest::class,'return_refund_request_id');} }
