<?php

namespace App\Support;

use App\Models\Seller;
use Illuminate\Support\Facades\Auth;

class SellerAccount
{
    public static function current(): Seller
    {
        $user = Auth::user();
        abort_unless($user && $user->isSeller(), 403, 'Sign in with your seller account.');
        abort_unless($user->status === 'active' && ! $user->is_suspended, 403, 'Your account is not active.');

        $shops = $user->sellers()->where('status', 'approved')->limit(2)->get();
        // Local development may use the owner's pending shop without granting approval.
        // APP_DEBUG does not enable this exception, and approved shops retain priority.
        if ($shops->isEmpty() && app()->environment('local')) {
            $shops = $user->sellers()->where('status', 'pending')->limit(2)->get();
        }
        if ($shops->isEmpty()) {
            abort(403, $user->sellers()->exists()
                ? 'Your store must be approved before accessing Seller Centre.'
                : 'No persisted store is linked to your account. Contact support.');
        }
        abort_if($shops->count() !== 1, 409, 'Multiple eligible stores require a store selection workflow. Contact support.');

        return $shops->first()->load(['owner', 'pickupAddress', 'documents']);
    }

    public static function profile(Seller $seller): array
    {
        $address = $seller->pickupAddress;
        // Never expose a corrupt or foreign pickup-address relationship.
        abort_if($address && $address->user_id !== $seller->user_id, 403, 'The pickup address does not belong to this store owner.');

        return [
            'shop_id' => $seller->id,
            'shop_name' => $seller->name,
            'business_category' => SellerCategories::display($seller->business_category),
            'seller_type' => $seller->seller_type,
            'tin' => $seller->tin,
            'description' => $seller->description,
            'profile_photo' => $seller->logo_path,
            'banner_path' => $seller->banner_path,
            'seller_name' => $seller->owner->name,
            'email' => $seller->owner->email,
            'phone' => $address?->phone,
            'region' => $address?->region,
            'province' => $address?->province,
            'municipality' => $address?->city,
            'barangay' => $address?->barangay,
            'address' => $address?->line1,
            'postal_code' => $address?->postal_code,
            'status' => $seller->status,
            'created_at' => $seller->created_at,
            'documents' => $seller->documents->pluck('path', 'document_type')->all(),
        ];
    }

    public static function sync(Seller $seller): array
    {
        $profile = self::profile($seller);
        session()->put('seller_profile', $profile);
        session()->put('seller_logged_in', true);
        SellerProducts::sync($seller);

        return $profile;
    }
}
