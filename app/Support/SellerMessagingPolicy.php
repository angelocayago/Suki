<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SellerMessagingPolicy
{
    private static function localPendingShopId(): ?int
    {
        if (! app()->environment('local')) {
            return null;
        }
        $id = filter_var(config('seller-messaging.local_pending_shop_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    public static function isLocalTestShop(Seller $shop): bool
    {
        return $shop->status === 'pending' && $shop->id === self::localPendingShopId();
    }

    public static function eligible(Seller $shop, ?User $owner = null): bool
    {
        $owner ??= $shop->owner;

        return ($shop->status === 'approved' || self::isLocalTestShop($shop))
            && $owner && $owner->id === $shop->user_id && $owner->isSeller()
            && $owner->status === 'active' && ! $owner->is_suspended;
    }

    public static function shops(): Builder
    {
        $localId = self::localPendingShopId();

        return Seller::where(function ($query) use ($localId) {
            $query->where('status', 'approved');
            if ($localId !== null) {
                $query->orWhere(fn ($pending) => $pending->where('status', 'pending')->where('id', $localId));
            }
        })->whereHas('owner', function ($query) {
            $query->where('status', 'active')->where('is_suspended', false)->where(function ($roles) {
                $roles->where('role', 'seller');
                if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
                    $roles->orWhereHas('roles', fn ($role) => $role->where('name', 'seller'));
                }
            });
        });
    }
}
