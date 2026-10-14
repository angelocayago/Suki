<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'position',
        'is_active',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'parent_id'
        );
    }

    /**
     * Child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            Category::class,
            'parent_id'
        );
    }

    /**
     * Products under this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Sellers for which this is the authoritative assigned category.
     */
    public function assignedSellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function allowedSellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
