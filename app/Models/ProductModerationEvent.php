<?php

namespace App\Models;

use App\Enums\ProductModerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductModerationEvent extends Model
{
    protected $fillable = [
        'product_id',
        'seller_id',
        'actor_id',
        'previous_status',
        'new_status',
        'reason',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'previous_status' => ProductModerationStatus::class,
            'new_status' => ProductModerationStatus::class,
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
