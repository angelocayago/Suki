<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderServiceArea extends Model
{
    protected $fillable = ['rider_id', 'logistics_provider_id', 'region_code', 'province_code', 'city_municipality_code', 'barangay_code', 'area_key', 'is_active', 'deactivated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'deactivated_at' => 'datetime'];
    }

    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function logisticsProvider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class); }

    public static function key(string $region, ?string $province, string $city, ?string $barangay): string
    {
        return implode(':', [$region, $province ?: '-', $city, $barangay ?: '-']);
    }
}
