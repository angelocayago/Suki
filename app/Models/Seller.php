<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'name',
        'business_category',
        'category_id',
        'seller_type',
        'tin',
        'slug',
        'description',
        'logo_path',
        'banner_path',
        'valid_id_path',
        'business_permit_path',
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

    public function visibleReviews(): HasMany
    {
        return $this->hasMany(TransactionReview::class)->where('kind', 'seller')->whereNull('hidden_at');
    }

    /**
     * The single category assigned to this seller account.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Private onboarding and verification documents for this seller.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
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

    public function allowedCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function productModerationEvents(): HasMany
    {
        return $this->hasMany(ProductModerationEvent::class)->latest();
    }

    public function violations(): HasMany
    {
        return $this->hasMany(SellerViolation::class)->latest();
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(SellerWarning::class)->latest('issued_at');
    }

    public function complianceEvents(): HasMany
    {
        return $this->hasMany(SellerComplianceEvent::class)->latest();
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Seller orders belonging to this shop.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }
}
