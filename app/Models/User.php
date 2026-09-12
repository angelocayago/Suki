<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mga fields na pwedeng i-save sa users table.
     */
    protected $fillable = [
        'role',
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'status',
        'password',
    ];

    /**
     * Mga fields na hindi dapat ipakita.
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
        ];
    }

    /**
     * Buyer checker.
     */
    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    /**
     * Seller checker.
     */
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Rider checker.
     */
    public function isRider(): bool
    {
        return $this->role === 'rider';
    }

    /**
     * Logistics checker.
     */
    public function isLogistics(): bool
    {
        return $this->role === 'logistics';
    }

    /**
     * Buong pangalan ng user.
     */
    public function getFullNameAttribute(): string
    {
        return trim(
            ($this->first_name ?? '') . ' ' .
            ($this->last_name ?? '')
        );
    }

    public function cartItems(): HasMany
    {   
    return $this->hasMany(CartItem::class);
    }

    public function wishlistItems(): HasMany
    {
    return $this->hasMany(WishlistItem::class);
    }

}