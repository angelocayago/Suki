<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Canonical persisted Buyer-address rules shared by web and versioned APIs. */
class BuyerAddressService
{
    public function __construct(private readonly PhilippineAddressService $locations) {}

    public function all(User $buyer): Collection
    {
        return $buyer->addresses()->orderByDesc('is_default')->oldest('id')->get();
    }

    public function data(array $input): array
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'region' => ['required', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:20'],
            'municipality' => ['required', 'string', 'max:20'],
            'barangay' => ['required', 'string', 'max:20'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:50'],
        ])->validate();
        $resolved = $this->locations->resolve($data);

        return [
            'label' => $data['label'], 'recipient' => $data['name'], 'phone' => $data['phone'],
            'line1' => $data['street'], 'region' => $resolved['region'], 'region_code' => $resolved['region_code'],
            'barangay' => $resolved['barangay'], 'barangay_code' => $resolved['barangay_code'],
            'city' => $resolved['municipality'], 'city_municipality_code' => $resolved['city_municipality_code'],
            'province' => $resolved['province'], 'province_code' => $resolved['province_code'],
            'postal_code' => $resolved['postal_code'] ?? null,
        ];
    }

    public function create(User $buyer, array $data, bool $requestedDefault): Address
    {
        return DB::transaction(function () use ($buyer, $data, $requestedDefault): Address {
            $hasAddresses = $buyer->addresses()->lockForUpdate()->exists();
            $isDefault = $requestedDefault || ! $hasAddresses;
            if ($isDefault) $buyer->addresses()->update(['is_default' => false]);

            return $buyer->addresses()->create($data + ['is_default' => $isDefault]);
        }, 3);
    }

    public function update(User $buyer, int $addressId, array $data, bool $makeDefault = false): Address
    {
        return DB::transaction(function () use ($buyer, $addressId, $data, $makeDefault): Address {
            $address = $buyer->addresses()->lockForUpdate()->findOrFail($addressId);
            if ($makeDefault) $buyer->addresses()->update(['is_default' => false]);
            $address->update($data + ($makeDefault ? ['is_default' => true] : []));

            return $address->fresh();
        }, 3);
    }

    public function setDefault(User $buyer, int $addressId): Address
    {
        return DB::transaction(function () use ($buyer, $addressId): Address {
            $address = $buyer->addresses()->lockForUpdate()->findOrFail($addressId);
            $buyer->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);

            return $address->fresh();
        }, 3);
    }

    public function delete(User $buyer, int $addressId): void
    {
        DB::transaction(function () use ($buyer, $addressId): void {
            $address = $buyer->addresses()->lockForUpdate()->findOrFail($addressId);
            $wasDefault = $address->is_default;
            $address->delete();
            if ($wasDefault && ($next = $buyer->addresses()->lockForUpdate()->oldest('id')->first())) {
                $next->update(['is_default' => true]);
            }
        }, 3);
    }

    public function present(Address $address): array
    {
        return [
            'id' => $address->id, 'name' => $address->recipient, 'phone' => $address->phone,
            'region' => $address->region, 'region_code' => $address->region_code,
            'province' => $address->province, 'province_code' => $address->province_code,
            'municipality' => $address->city, 'city_municipality_code' => $address->city_municipality_code,
            'barangay' => $address->barangay, 'barangay_code' => $address->barangay_code,
            'street' => $address->line1, 'postal_code' => $address->postal_code,
            'label' => $address->label, 'is_default' => (bool) $address->is_default,
        ];
    }
}
