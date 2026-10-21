<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PhilippineAddressService
{
    private const BASE = 'https://psgc.cloud/api/v2/';

    public function locations(string $path): ?array
    {
        try {
            $response = Http::acceptJson()->timeout(5)->get(self::BASE.$path);
        } catch (\Throwable) {
            return null;
        }
        $items = $response->successful() ? $response->json('data') : null;
        if (! is_array($items)) return null;
        return array_values(array_filter($items, fn ($item) => is_array($item) && is_string($item['code'] ?? null) && is_string($item['name'] ?? null)));
    }

    public function resolve(array $address): array
    {
        $get = function (string $path, string $field): array {
            $items = $this->locations($path);
            if ($items === null) throw ValidationException::withMessages([$field => 'Address data could not be verified. Please try again later.']);
            return $items;
        };
        $pick = static function (array $items, ?string $code, string $field): array {
            foreach ($items as $item) if (($item['code'] ?? null) === $code) return $item;
            throw ValidationException::withMessages([$field => 'Select a location belonging to the selected administrative area.']);
        };
        $region = $pick($get('regions', 'region'), $address['region'] ?? null, 'region');
        $provinces = $get('regions/'.rawurlencode($region['code']).'/provinces', 'province');
        $province = null;
        $citiesPath = 'regions/'.rawurlencode($region['code']).'/cities-municipalities';
        if ($provinces !== []) {
            $province = $pick($provinces, $address['province'] ?? null, 'province');
            $citiesPath = 'provinces/'.rawurlencode($province['code']).'/cities-municipalities';
        } elseif (! empty($address['province'])) {
            throw ValidationException::withMessages(['province' => 'This region does not have provinces.']);
        }
        $city = $pick($get($citiesPath, 'municipality'), $address['municipality'] ?? null, 'municipality');
        $barangay = $pick($get('cities-municipalities/'.rawurlencode($city['code']).'/barangays', 'barangay'), $address['barangay'] ?? null, 'barangay');
        return [
            'region' => $region['name'], 'province' => $province['name'] ?? null,
            'municipality' => $city['name'], 'barangay' => $barangay['name'],
            'region_code' => $region['code'], 'province_code' => $province['code'] ?? null,
            'city_municipality_code' => $city['code'], 'barangay_code' => $barangay['code'],
        ];
    }
}
