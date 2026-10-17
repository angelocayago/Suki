<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Seller;
use App\Models\User;
use App\Support\PhilippineAddress;
use App\Support\SellerAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MyProfileController extends Controller
{
    public function show()
    {
        $store = SellerAccount::current();
        SellerAccount::sync($store);

        return view('seller.my-profile', [
            'owner' => $store->owner,
            'store' => $store,
            'personalAddress' => $this->personalAddress($store->owner),
        ]);
    }

    public function update(Request $request)
    {
        $store = SellerAccount::current();
        $owner = $store->owner;
        $data = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/\A\p{L}+(?:[\x20\x27\x2D]\p{L}+)*\z/u"],
            'middle_initial' => ['nullable', 'string', 'max:2', 'regex:/\A\p{L}+\z/u'],
            'phone' => ['bail', 'required', 'string', 'regex:/\A(?:09[0-9]{9}|\+639[0-9]{9})\z/', function ($attribute, $value, $fail) use ($owner) {
                $local = $this->phone($value);
                if (User::whereKeyNot($owner->id)->whereIn('phone', [$local, '+63'.substr($local, 1)])->exists()) {
                    $fail('This phone number has already been taken.');
                }
            }],
            'email' => ['sometimes', 'required', 'email', function ($attribute, $value, $fail) use ($owner) {
                if ($value !== $owner->email) {
                    $fail('Email changes require a verified email-change workflow and are not available here.');
                }
            }],
        ]);
        DB::transaction(function () use ($owner, $data) {
            $locked = User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $locked->first_name = $data['first_name'];
            $locked->last_name = $data['last_name'];
            if (array_key_exists('middle_initial', $data)) {
                $locked->middle_initial = $data['middle_initial'];
            }
            $locked->name = collect([$locked->first_name, $locked->middle_initial, $locked->last_name])->filter()->implode(' ');
            $locked->phone = $this->phone($data['phone']);
            $locked->save();
        });
        $this->sync($store);

        return redirect()->route('seller.profile')->with('success', 'Personal information updated successfully.');
    }

    public function updatePhoto(Request $request)
    {
        $store = SellerAccount::current();
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $request->file('photo')->store('seller-accounts/'.$store->user_id.'/photos', 'public');
        if (! $path) {
            throw new \RuntimeException('The personal profile photo could not be saved.');
        }
        try {
            $old = DB::transaction(function () use ($store, $path) {
                $owner = User::whereKey($store->user_id)->lockForUpdate()->firstOrFail();
                $old = $owner->profile_photo_path;
                $owner->profile_photo_path = $path;
                $owner->save();

                return $old;
            });
        } catch (\Throwable $exception) {
            $this->deleteUnusedPhoto($store->user_id, $path);
            throw $exception;
        }
        $this->deleteUnusedPhoto($store->user_id, $old);
        $this->sync($store);

        return redirect(route('seller.profile').'#profile-photo')->with('success', 'Personal profile photo updated successfully.');
    }

    public function removePhoto()
    {
        $store = SellerAccount::current();
        $old = DB::transaction(function () use ($store) {
            $owner = User::whereKey($store->user_id)->lockForUpdate()->firstOrFail();
            $old = $owner->profile_photo_path;
            $owner->profile_photo_path = null;
            $owner->save();

            return $old;
        });
        $this->deleteUnusedPhoto($store->user_id, $old);
        $this->sync($store);

        return redirect(route('seller.profile').'#profile-photo')->with('success', 'Personal profile photo removed successfully.');
    }

    public function updateAddress(Request $request, callable $fetchPsgcData)
    {
        $store = SellerAccount::current();
        $data = $request->validate([
            'region' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
            'province' => ['nullable', 'string', 'regex:/\A[0-9]{10}\z/'],
            'municipality' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
            'barangay' => ['required', 'string', 'regex:/\A[0-9]{10}\z/'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'regex:/\A[0-9]{4}\z/'],
        ]);
        $location = PhilippineAddress::resolve($data, $fetchPsgcData);
        DB::transaction(function () use ($store, $data, $location) {
            $owner = User::whereKey($store->user_id)->lockForUpdate()->firstOrFail();
            $address = $this->personalAddress($owner) ?? new Address([
                'user_id' => $owner->id, 'label' => 'Home', 'is_default' => false,
            ]);
            $address->fill($location + [
                'line1' => $data['address'], 'postal_code' => $data['postal_code'],
                'recipient' => $owner->name, 'phone' => $owner->phone,
            ])->save();
        });

        return redirect(route('seller.profile').'#personal-address')->with('success', 'Personal address updated successfully.');
    }

    private function personalAddress(User $owner): ?Address
    {
        $addresses = $owner->addresses()->where('label', 'Home')
            ->whereNotIn('id', Seller::whereNotNull('pickup_address_id')->select('pickup_address_id'))
            ->limit(2)->get();
        abort_if($addresses->count() > 1, 409, 'Multiple Home addresses require an address selection workflow. Contact support.');

        return $addresses->first();
    }

    private function sync(Seller $store): void
    {
        auth()->user()->refresh();
        SellerAccount::sync($store->fresh(['owner', 'pickupAddress', 'documents']));
    }

    private function phone(string $value): string
    {
        return str_starts_with($value, '+63') ? '0'.substr($value, 3) : $value;
    }

    private function deleteUnusedPhoto(int $ownerId, ?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'seller-accounts/'.$ownerId.'/photos/') || str_contains($path, '..')) {
            return;
        }
        try {
            if (! User::where('profile_photo_path', $path)->exists() && ! Seller::where('logo_path', $path)->orWhere('banner_path', $path)->exists()) {
                if (! Storage::disk('public')->delete($path)) {
                    report(new \RuntimeException('An unused personal photo could not be removed.'));
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
