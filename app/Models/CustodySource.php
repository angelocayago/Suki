<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An internal accounting observation, not a bank deposit or spendable reserve. */
class CustodySource extends Model
{
    public $timestamps=false;
    protected $guarded=['*'];
    protected function casts(): array
    {
        return ['accepted_minor'=>'integer','unreconciled_minor'=>'integer','allocatable_minor'=>'integer',
            'committed_minor'=>'integer','consumed_minor'=>'integer','components'=>'array','accepted_at'=>'datetime','recorded_at'=>'datetime'];
    }
}
