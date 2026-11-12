<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticsSortingZone extends Model
{
    protected $fillable = ['logistics_provider_id', 'logistics_hub_id', 'zone_code', 'name', 'is_active', 'priority'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function provider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
    public function hub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'logistics_hub_id'); }
    public function areas(): HasMany { return $this->hasMany(LogisticsSortingZoneArea::class); }
}
