<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRefundWorkflow extends Model
{
    protected $guarded=['id'];
    protected $hidden=['destination'];
    protected function casts(): array { return ['destination'=>'encrypted:array','shipped_on'=>'date','received_at'=>'datetime','hub_received_at'=>'datetime','dispatched_at'=>'datetime','seller_received_at'=>'datetime','stock_restored'=>'boolean','stock_restored_at'=>'datetime']; }
    public function logisticsProvider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class); }
    public function returnHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'return_hub_id'); }
}
