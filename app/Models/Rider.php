<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'logistics_provider_id',
        'vehicle_type',
        'plate_no',
        'license_number',
        'government_id_path',
        'drivers_license_path',
        'vehicle_document_path',
        'is_active',
        'availability',
        'approved_by',
        'approved_at',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * User account of this rider.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logistics provider this rider belongs to.
     */
    public function logisticsProvider(): BelongsTo
    {
        return $this->belongsTo(LogisticsProvider::class);
    }

    /**
     * Shipments assigned to this rider.
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function pickupShipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'pickup_rider_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function serviceAreas(): HasMany
    {
        return $this->hasMany(RiderServiceArea::class);
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function isOperationallyAvailable(): bool
    {
        return ($this->availability ?? 'ONLINE') === 'ONLINE'
            && $this->isOperationallyEligible();
    }

    /**
     * Account approval/activation and operational availability are separate.
     * A missing availability value is treated as ONLINE by
     * isOperationallyAvailable(), so pre-migration riders retain their
     * existing assignment semantics.
     */
    public function isOperationallyEligible(): bool
    {
        return $this->is_active
            && $this->user?->hasRole('rider')
            && $this->user?->status === 'active'
            && ! $this->user?->is_suspended
            && $this->user?->hasVerifiedContacts()
            && $this->logisticsProvider?->status === 'approved';
    }
}
