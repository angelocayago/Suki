<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentAssignment extends Model
{
    protected $fillable = [
        'shipment_id', 'logistics_provider_id', 'assignment_type', 'status', 'rider_id',
        'origin_hub_id', 'destination_hub_id', 'assigned_by', 'vehicle_reference',
        'batch_reference', 'close_reason', 'responded_at', 'dispatched_at', 'arrived_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'dispatched_at' => 'datetime', 'arrived_at' => 'datetime'];
    }

    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function provider(): BelongsTo { return $this->belongsTo(LogisticsProvider::class, 'logistics_provider_id'); }
    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function originHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destinationHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
    public function assigner(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ASSIGNED', 'ACCEPTED']);
    }
}
