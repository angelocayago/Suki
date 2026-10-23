<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Seller extends Model
{
    use Notifiable;

    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'business_category',
        'seller_type',
        'tin',
        'description',
        'logo_path',
        'banner_path',
        'status',
        'rejection_reason',
        'commission_bps',
        'pickup_address_id',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'commission_bps' => 'integer',
        ];
    }

    /** Read-only shop display code; the persisted primary key remains unchanged. */
    public function getShopCodeAttribute(): ?string
    {
        $id = $this->getKey();

        return $id === null ? null : 'SHOP-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * User who owns this shop.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Pickup address of this shop.
     */
    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(
            Address::class,
            'pickup_address_id'
        );
    }

    /**
     * Products owned by this shop.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Seller orders belonging to this shop.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }
}
