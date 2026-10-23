<?php

namespace App\Support;

use App\Models\User;

class BuyerAccess
{
    public static function localException(?User $user): bool
    {
        return app()->environment('local') && $user && $user->isBuyer()
            && ! $user->is_suspended && in_array($user->status, ['pending', 'active'], true)
            && ! $user->isSeller() && ! $user->hasRole('admin') && ! $user->hasRole('superadmin')
            && ! $user->isRider() && ! $user->isLogistics();
    }

    public static function approved(User $user): bool
    {
        return $user->status === 'active' || self::localException($user);
    }

    public static function verified(User $user): bool
    {
        return $user->email_verified_at !== null || self::localException($user);
    }
}
