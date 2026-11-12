<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsSortingZoneArea extends Model
{
    protected $fillable = ['logistics_sorting_zone_id', 'region_code', 'province_code', 'city_municipality_code', 'barangay_code', 'area_key'];

    public function zone(): BelongsTo { return $this->belongsTo(LogisticsSortingZone::class, 'logistics_sorting_zone_id'); }
}
