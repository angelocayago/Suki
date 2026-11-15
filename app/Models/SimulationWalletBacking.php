<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationWalletBacking extends Model
{
    protected $primaryKey='simulation_commitment_id';
    public $incrementing=false;
    public $timestamps=false;
    protected $guarded=['*'];
    protected function casts(): array { return ['amount_minor'=>'integer','seller_minor'=>'integer','platform_minor'=>'integer','created_at'=>'datetime']; }
}
