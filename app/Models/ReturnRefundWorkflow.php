<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRefundWorkflow extends Model
{
    protected $guarded=['id'];
    protected $hidden=['destination'];
    protected function casts(): array { return ['destination'=>'encrypted:array','shipped_on'=>'date','received_at'=>'datetime']; }
}
