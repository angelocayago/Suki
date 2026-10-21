<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LogisticsCodRemittance extends Model { protected $fillable=['logistics_provider_id','expected_minor','submitted_minor','accepted_minor','status','submitted_at','reviewed_at','reviewed_by','reason','reference','evidence_metadata']; protected function casts(): array{return ['expected_minor'=>'integer','submitted_minor'=>'integer','accepted_minor'=>'integer','submitted_at'=>'datetime','reviewed_at'=>'datetime','evidence_metadata'=>'array'];} public function provider(){return $this->belongsTo(LogisticsProvider::class,'logistics_provider_id');} public function items(){return $this->hasMany(LogisticsCodRemittanceItem::class);} }
