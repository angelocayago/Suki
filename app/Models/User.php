<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'middle_initial',
        'sex',
        'birthday',
        'name',
        'email',
        'phone',
        'status',
        'is_suspended',
        'government_id',
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
            'birthday' => 'date',
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

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
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
        return $this->hasAnyRole(['admin', 'superadmin']);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function accountStatusEvents(): HasMany
    {
        return $this->hasMany(AccountStatusEvent::class);
    }

    public function accountStatusActions(): HasMany
    {
        return $this->hasMany(AccountStatusEvent::class, 'actor_id');
    }

    public function createdSellerViolations(): HasMany
    {
        return $this->hasMany(SellerViolation::class, 'created_by');
    }

    public function resolvedSellerViolations(): HasMany
    {
        return $this->hasMany(SellerViolation::class, 'resolved_by');
    }

    public function issuedSellerWarnings(): HasMany
    {
        return $this->hasMany(SellerWarning::class, 'issued_by');
    }

    public function sellerComplianceActions(): HasMany
    {
        return $this->hasMany(SellerComplianceEvent::class, 'actor_id');
    }

    public function uploadedSellerViolationEvidence(): HasMany
    {
        return $this->hasMany(SellerViolationEvidence::class, 'uploaded_by');
    }

    public function filedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'complainant_id');
    }

    public function buyerComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'buyer_id');
    }

    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function resolvedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'resolved_by');
    }

    public function complaintEvents(): HasMany
    {
        return $this->hasMany(ComplaintEvent::class, 'actor_id');
    }

    public function uploadedComplaintEvidence(): HasMany
    {
        return $this->hasMany(ComplaintEvidence::class, 'uploaded_by');
    }

    public function createdConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function logisticsProvider(): HasOne
    {
        return $this->hasOne(LogisticsProvider::class);
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
            ($this->first_name ?? '').' '.
            ($this->last_name ?? '')
        );
    }
}
