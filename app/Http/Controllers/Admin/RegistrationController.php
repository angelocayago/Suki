<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationDecision;
use App\Models\Logistic;
use App\Models\Notification;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    private function createNotification(
        $userId,
        $title,
        $message,
        $type = 'order',
        $link = null
    ) {
        Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    private function notifyByEmail(
        User $user,
        string $decision,
        ?string $reason = null
    ) {
        try {
            Mail::to($user->email)->send(
                new RegistrationDecision(
                    $user,
                    $decision,
                    $reason
                )
            );
        } catch (\Throwable $e) {
            logger()->error(
                'Failed to send registration email to '
                    . $user->email
                    . ': '
                    . $e->getMessage()
            );
        }
    }

    public function index(Request $request)
    {
        $query = User::where(
            'status',
            User::STATUS_PENDING
        )->whereHas('roles', function ($q) {
            $q->whereIn(
                'name',
                ['buyer', 'seller', 'logistics']
            );
        });

        if ($request->filled('role')) {
            $requestedRole = match ($request->role) {
                'customer' => 'buyer',
                'logistic_owner',
                'logistic' => 'logistics',
                default => $request->role,
            };

            $query->whereHas(
                'roles',
                fn($q) => $q->where(
                    'name',
                    $requestedRole
                )
            );
        }

        // Buyers and Sellers are reviewable only after email OTP verification.
        $query->where(function ($q) {
            $q->whereHas('roles', fn($roles) => $roles->where('name', 'logistics'))
                ->orWhere(function ($verified) {
                    $verified->whereNotNull('email_verified_at')
                        ->whereHas('roles', fn($roles) => $roles->whereIn('name', ['buyer', 'seller']));
                });
        });

        $applications = $query
            ->latest()
            ->paginate(15);

        return view(
            'admin.registrations',
            compact('applications')
        );
    }

    public function show(User $user)
    {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications are reviewed by the logistics company.'
                );
        }

        if (!$user->hasAnyRole(['buyer', 'seller', 'logistics'])) {
            return redirect()
                ->route('admin.registrations.index')
                ->with('error', 'Only Buyer, Seller and Logistics applications require Admin approval.');
        }

        if (!$user->isPending()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'This application is no longer pending.'
                );
        }

        if ($user->hasAnyRole(['buyer', 'seller']) && $user->email_verified_at === null) {
            return redirect()->route('admin.registrations.index')
                ->with('error', 'This applicant must verify their email before Admin review.');
        }

        return view(
            'admin.registrations-show',
            compact('user')
        );
    }

    public function approve(
        Request $request,
        User $user
    ) {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications must be approved by the logistics company.'
                );
        }

        if (!$user->isPending()) {
            return back()->with(
                'error',
                'This application has already been processed.'
            );
        }

        $roleName = $user
            ->roles()
            ->value('name');

        if (in_array($roleName, ['buyer', 'seller'], true) && $user->email_verified_at === null) {
            return back()->with('error', 'This applicant must verify their email before approval.');
        }

        if (
            !in_array(
                $roleName,
                ['buyer', 'seller', 'logistics'],
                true
            )
        ) {
            return back()->with(
                'error',
                'This role cannot be approved from the registrations page.'
            );
        }

        DB::transaction(
            function () use ($user, $roleName) {

                // Recheck the latest account state while holding a database lock.
                $lockedUser = User::whereKey($user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$lockedUser->isPending()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'approval' => 'This application has already been processed.',
                    ]);
                }

                // Repeat authorization and OTP checks inside the database lock.
                $lockedRole = $lockedUser->roles()->whereIn('name', ['buyer', 'seller', 'logistics'])->value('name');
                if (!$lockedRole || $lockedRole !== $roleName) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'approval' => 'This account is not eligible for Admin approval.',
                    ]);
                }
                if (in_array($lockedRole, ['buyer', 'seller'], true) && $lockedUser->email_verified_at === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'approval' => 'This applicant must verify their email before approval.',
                    ]);
                }

                $user = $lockedUser;
                /*
                |--------------------------------------------------------------------------
                | Activate approved user
                |--------------------------------------------------------------------------
                */
                $user->update([
                    'status' => User::STATUS_ACTIVE,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Seller approval
                |--------------------------------------------------------------------------
                | Create/update the canonical sellers table record so products
                | created by this seller receive a valid seller_id.
                */
                if ($roleName === 'seller') {
                    $shopName = $user->store_name
                        ?: $user->business_name
                        ?: $user->name . ' Shop';

                    $baseSlug = Str::slug($shopName);

                    if ($baseSlug === '') {
                        $baseSlug = 'seller-' . $user->id;
                    }

                    $slug = $baseSlug;
                    $counter = 1;

                    while (
                        Seller::where('slug', $slug)
                        ->where(
                            'user_id',
                            '!=',
                            $user->id
                        )
                        ->exists()
                    ) {
                        $slug = $baseSlug
                            . '-'
                            . $counter;

                        $counter++;
                    }

                    $seller = Seller::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'name' => $shopName,
                            'slug' => $slug,
                            'description' => null,
                            'logo_path' => $user->logo,
                            'banner_path' => null,
                            'status' => 'approved',
                            'rejection_reason' => null,
                            'commission_bps' => 0,
                        ]
                    );

                    // Preserve an existing pickup address.
                    if (!$seller->pickup_address_id) {
                        $addressLine1 = collect([
                            $user->house_number,
                            $user->street_address,
                            $user->barangay_name ?: $user->barangay,
                        ])->filter()->implode(', ');

                        $city = $user->municipality_name ?: $user->municipality;
                        $province = $user->province_name ?: $user->province;

                        if ($addressLine1 !== '' && $city && $province) {
                            $pickupAddress = \App\Models\Address::create([
                                'user_id' => $user->id,
                                'label' => 'Seller Pickup',
                                'address_line1' => $addressLine1,
                                'city' => $city,
                                'province' => $province,
                                'country' => 'Philippines',
                                'phone' => $user->mobile_number ?: $user->phone,
                                'is_default' => true,
                                'latitude' => $user->latitude,
                                'longitude' => $user->longitude,
                            ]);

                            $seller->update([
                                'pickup_address_id' => $pickupAddress->id,
                            ]);
                        }
                    }
                } // End seller approval

                /*
                |--------------------------------------------------------------------------
                | Logistics approval
                |--------------------------------------------------------------------------
                */
                if ($roleName === 'logistics') {
                    Logistic::where(
                        'owner_user_id',
                        $user->id
                    )->update([
                        'status' => 'active',
                        'approved_at' => now(),
                        'rejection_reason' => null,
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | In-app notification
        |--------------------------------------------------------------------------
        */
        $this->createNotification(
            $user->id,
            'Registration Approved',
            'Your '
                . ucfirst($roleName ?? 'user')
                . ' account application has been approved. You can now log in.',
            'account',
            route('login')
        );

        /*
        |--------------------------------------------------------------------------
        | Email notification
        |--------------------------------------------------------------------------
        */
        $this->notifyByEmail(
            $user,
            'approved'
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with(
                'success',
                ucfirst($roleName ?? 'user')
                    . ' application approved and notified via email.'
            );
    }

    public function reject(
        Request $request,
        User $user
    ) {
        if ($user->isRider()) {
            return redirect()
                ->route('admin.registrations.index')
                ->with(
                    'error',
                    'Rider applications must be rejected by the logistics company.'
                );
        }

        if (!$user->isPending()) {
            return back()->with(
                'error',
                'This application has already been processed.'
            );
        }

        $request->validate([
            'rejection_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $roleName = $user
            ->roles()
            ->value('name');

        if (
            !in_array(
                $roleName,
                ['buyer', 'seller', 'logistics'],
                true
            )
        ) {
            return back()->with(
                'error',
                'This role cannot be rejected from the registrations page.'
            );
        }

        if (in_array($roleName, ['buyer', 'seller'], true) && $user->email_verified_at === null) {
            return back()->with('error', 'This applicant must verify their email before review.');
        }

        DB::transaction(
            function () use (
                $user,
                $roleName,
                $request
            ) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if (
                    !$user->isPending() || !$user->roles()->where('name', $roleName)->exists()
                    || (in_array($roleName, ['buyer', 'seller'], true) && $user->email_verified_at === null)
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'rejection_reason' => 'This application is no longer eligible for review.',
                    ]);
                }
                $user->update([
                    'status' => User::STATUS_REJECTED,
                    'rejection_reason' =>
                    $request->rejection_reason,
                    'approved_at' => null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Seller rejection
                |--------------------------------------------------------------------------
                | If a Seller record already exists for any reason,
                | keep its status synchronized.
                */
                if ($roleName === 'seller') {
                    Seller::where(
                        'user_id',
                        $user->id
                    )->update([
                        'status' => 'rejected',
                        'rejection_reason' =>
                        $request->rejection_reason,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Logistics rejection
                |--------------------------------------------------------------------------
                */
                if ($roleName === 'logistics') {
                    Logistic::where(
                        'owner_user_id',
                        $user->id
                    )->update([
                        'status' => 'rejected',
                        'rejection_reason' =>
                        $request->rejection_reason,
                        'approved_at' => null,
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | In-app notification
        |--------------------------------------------------------------------------
        */
        $this->createNotification(
            $user->id,
            'Registration Declined',
            'Your '
                . ucfirst($roleName ?? 'user')
                . ' account application was declined. Reason: '
                . $request->rejection_reason,
            'account'
        );

        /*
        |--------------------------------------------------------------------------
        | Email notification
        |--------------------------------------------------------------------------
        */
        $this->notifyByEmail(
            $user,
            'rejected',
            $request->rejection_reason
        );

        return redirect()
            ->route('admin.registrations.index')
            ->with(
                'success',
                ucfirst($roleName ?? 'user')
                    . ' application rejected and notified via email.'
            );
    }
}
