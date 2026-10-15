<?php

namespace App\Models;

use App\Enums\ProductModerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'brand',
        'slug',
        'description',
        'shipping_options',
        'legacy_reference',
        'is_active',
        'commission_bps',
        'moderation_status',
        'moderation_reason',
        'moderated_by',
        'moderated_at',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_bps' => 'integer',
            'shipping_options' => 'array',
            'moderation_status' => ProductModerationStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    /**
     * Seller that owns this product.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Category of this product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Variants of this product.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Images of this product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('position');
    }

    /**
     * Order items that reference this product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function moderationEvents(): HasMany
    {
        return $this->hasMany(ProductModerationEvent::class)->latest();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function sellerViolations(): HasMany
    {
        return $this->hasMany(SellerViolation::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
