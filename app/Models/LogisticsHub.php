<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsHub extends Model
{
    protected $fillable = ['logistics_provider_id', 'name', 'region', 'region_code', 'province', 'province_code', 'city', 'city_municipality_code', 'barangay', 'barangay_code', 'postal_code', 'is_active'];

    protected function casts(): array { return ['is_active' => 'boolean']; }

    public function logisticsProvider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class); }
    public function riders(): HasMany { return $this->hasMany(Rider::class); }
}
