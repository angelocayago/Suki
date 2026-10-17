<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Seller;
use App\Support\SellerAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreProfileController extends Controller
{
    public function show()
    {
        $seller = SellerAccount::current();

        return view('seller.store-profile', ['store' => $seller, 'profile' => SellerAccount::sync($seller)]);
    }

    public function update(Request $request)
    {
        $seller = SellerAccount::current();
        $request->merge(['shop_name' => is_string($request->shop_name) ? trim($request->shop_name) : $request->shop_name]);
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'min:3', 'max:30'],
            'phone' => ['required', 'string', 'regex:/\A(?:09[0-9]{9}|\+639[0-9]{9})\z/'],
            'description' => ['nullable', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if (! $seller->pickupAddress) {
            throw ValidationException::withMessages(['phone' => 'Save a pickup address before updating the business contact.']);
        }
        $files = [];
        $oldPaths = [];
        try {
            foreach (['profile_photo' => 'logo_path', 'banner' => 'banner_path'] as $input => $column) {
                if ($request->hasFile($input)) {
                    $path = $request->file($input)->store('seller-profiles/'.$seller->id.'/'.$column, 'public');
                    if (! $path) {
                        throw new \RuntimeException('The store image could not be saved.');
                    }
                    $files[$column] = $path;
                }
            }
            DB::transaction(function () use ($seller, $data, $files, &$oldPaths) {
                $locked = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
                foreach ($files as $column => $path) {
                    $oldPaths[] = $locked->$column;
                    $locked->$column = $path;
                }
                $locked->name = $data['shop_name'];
                if (array_key_exists('description', $data)) {
                    $locked->description = $data['description'];
                }
                $address = $this->editablePickup($locked);
                $address->phone = $this->phone($data['phone']);
                $address->save();
                $locked->pickup_address_id = $address->id;
                $locked->save();
            });
        } catch (\Throwable $exception) {
            foreach ($files as $path) {
                $this->deleteUnusedImage($seller, $path);
            }
            throw $exception;
        }
        foreach ($oldPaths as $path) {
            $this->deleteUnusedImage($seller, $path);
        }
        SellerAccount::sync($seller->fresh(['owner', 'pickupAddress', 'documents']));

        return redirect(route('seller.store.profile').'#shop-profile')->with('success', 'Shop profile updated successfully.');
    }

    public function removeImage(Request $request)
    {
        $seller = SellerAccount::current();
        // Existing clients without a selector continue to remove the logo.
        $data = $request->validate(['image' => ['sometimes', 'required', 'in:logo,banner']]);
        $image = $data['image'] ?? 'logo';
        $column = $image === 'banner' ? 'banner_path' : 'logo_path';
        $path = DB::transaction(function () use ($seller, $column) {
            $locked = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $path = $locked->$column;
            $locked->$column = null;
            $locked->save();

            return $path;
        });
        $this->deleteUnusedImage($seller, $path);
        SellerAccount::sync($seller->fresh(['owner', 'pickupAddress', 'documents']));

        return redirect(route('seller.store.profile').'#shop-profile')->with('success', 'Store '.$image.' removed successfully.');
    }

    public function updateAddress(Request $request, callable $fetchPsgcData)
    {
        $seller = SellerAccount::current();
        $data = $request->validate([
            'region' => ['required', 'regex:/\A[0-9]{10}\z/'],
            'province' => ['nullable', 'regex:/\A[0-9]{10}\z/'],
            'municipality' => ['required', 'regex:/\A[0-9]{10}\z/'],
            'barangay' => ['required', 'regex:/\A[0-9]{10}\z/'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/\A[0-9]{4}\z/'],
            'pickup_phone' => ['required', 'string', 'regex:/\A(?:09[0-9]{9}|\+639[0-9]{9})\z/'],
        ]);
        $get = function (string $path) use ($fetchPsgcData) {
            $items = $fetchPsgcData($path);
            if ($items === null) {
                throw ValidationException::withMessages(['region' => 'Address data could not be verified. Please try again later.']);
            }

            return collect($items);
        };
        $select = function ($items, $code, $field) {
            $item = $items->firstWhere('code', $code);
            if (! $item) {
                throw ValidationException::withMessages([$field => 'Select a location belonging to the selected administrative area.']);
            }

            return $item;
        };
        $region = $select($get('regions'), $data['region'], 'region');
        $provinces = $get('regions/'.$region['code'].'/provinces');
        $province = null;
        if ($provinces->isNotEmpty()) {
            $province = $select($provinces, $data['province'] ?? '', 'province');
            $cities = $get('provinces/'.$province['code'].'/cities-municipalities');
        } else {
            if (! empty($data['province'])) {
                throw ValidationException::withMessages(['province' => 'This region does not have provinces.']);
            }
            $cities = $get('regions/'.$region['code'].'/cities-municipalities');
        }
        $city = $select($cities, $data['municipality'], 'municipality');
        $barangay = $select($get('cities-municipalities/'.$city['code'].'/barangays'), $data['barangay'], 'barangay');
        DB::transaction(function () use ($seller, $data, $region, $province, $city, $barangay) {
            $locked = Seller::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $address = $this->editablePickup($locked);
            $address->fill([
                'region' => $region['name'], 'province' => $province['name'] ?? '',
                'city' => $city['name'], 'barangay' => $barangay['name'],
                'line1' => $data['address'], 'postal_code' => $data['postal_code'],
                'phone' => $this->phone($data['pickup_phone']),
            ])->save();
            $locked->pickup_address_id = $address->id;
            $locked->save();
        });
        SellerAccount::sync($seller->fresh(['owner', 'pickupAddress', 'documents']));

        return redirect(route('seller.store.profile').'#pickup-address')->with('success', 'Pickup address updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $seller = SellerAccount::current();
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if (Hash::check($data['password'], $seller->owner->password)) {
            throw ValidationException::withMessages(['password' => 'The new password must be different from your current password.']);
        }
        $seller->owner->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();

        return redirect(route('seller.store.profile').'#security')->with('success', 'Password updated successfully.');
    }

    private function editablePickup(Seller $seller): Address
    {
        $address = $seller->pickupAddress;
        abort_if($address && $address->user_id !== $seller->user_id, 403);
        // Do not overwrite a personal/default address or another shop's shared address.
        if ($address && ($address->is_default || $address->label !== 'Pickup' || Seller::where('pickup_address_id', $address->id)->whereKeyNot($seller->id)->exists())) {
            $address = $address->replicate();
            $address->is_default = false;
            $address->label = 'Pickup';
        }

        return $address ?? new Address(['user_id' => $seller->user_id, 'label' => 'Pickup', 'recipient' => $seller->name, 'is_default' => false]);
    }

    private function phone(string $value): string
    {
        return str_starts_with($value, '+63') ? '0'.substr($value, 3) : $value;
    }

    private function deleteUnusedImage(Seller $seller, ?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'seller-profiles/'.$seller->id.'/') || str_contains($path, '..')) {
            return;
        }
        try {
            if (! Seller::where('logo_path', $path)->orWhere('banner_path', $path)->exists()) {
                if (! Storage::disk('public')->delete($path)) {
                    report(new \RuntimeException('An unused store image could not be removed.'));
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
