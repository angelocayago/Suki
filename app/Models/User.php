<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Fields that can be mass assigned.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'is_suspended',
        'password',
    ];

    /**
     * Fields hidden from serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Automatic data conversion.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_suspended' => 'boolean',
        ];
    }

    /**
     * Roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('name', $role)
            ->exists();
    }

    /**
     * Buyer checker.
     */
    public function isBuyer(): bool
    {
        return $this->hasRole('buyer');
    }

    /**
     * Seller checker.
     */
    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    /**
     * Rider checker.
     */
    public function isRider(): bool
    {
        return $this->hasRole('rider');
    }

    /**
     * Logistics checker.
     */
    public function isLogistics(): bool
    {
        return $this->hasRole('logistics');
    }

    /**
     * Admin checker.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Shops owned by this user.
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    /**
     * Saved addresses of this user.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Rider profile of this user.
     */
    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    /**
     * Buyer's cart.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Buyer's wishlist items.
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * Orders placed by this user.
     */
    public function buyerOrders(): HasMany
    {
        return $this->hasMany(
            Order::class,
            'buyer_id'
        );
    }

    /**
     * User's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') . ' ' .
            ($this->last_name ?? '')
        );
    }
}