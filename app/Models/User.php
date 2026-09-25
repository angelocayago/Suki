<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Fields that can be mass assigned.
     *
     * role + status are kept temporarily for compatibility
     * with the existing SUKI application.
     */
    protected $fillable = [
        'role',
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'status',
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
     * New role architecture.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check a role while supporting both:
     * - current legacy users.role
     * - future roles / role_user tables
     */
    public function hasRole(string $role): bool
    {
        // Existing production role system.
        if (($this->role ?? null) === $role) {
            return true;
        }

        // New role system may not exist yet in production.
        if (
            ! Schema::hasTable('roles') ||
            ! Schema::hasTable('role_user')
        ) {
            return false;
        }

        return $this->roles()
            ->where('roles.name', $role)
            ->exists();
    }

    public function isBuyer(): bool
    {
        return $this->hasRole('buyer');
    }

    public function isSeller(): bool
    {
        return $this->hasRole('seller');
    }

    public function isRider(): bool
    {
        return $this->hasRole('rider');
    }

    public function isLogistics(): bool
    {
        return $this->hasRole('logistics');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Legacy cart relationship.
     *
     * Keep this until routes/views have been migrated
     * to the new carts table architecture.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * New cart architecture.
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    public function buyerOrders(): HasMany
    {
        return $this->hasMany(
            Order::class,
            'buyer_id'
        );
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') . ' ' .
            ($this->last_name ?? '')
        );
    }
}