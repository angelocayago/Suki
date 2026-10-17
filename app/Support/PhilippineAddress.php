<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PhilippineAddress
{
    /** Resolve submitted PSGC codes through the existing live proxy data source. */
    public static function resolve(array $data, callable $fetchPsgcData): array
    {
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

        return [
            'region' => $region['name'], 'province' => $province['name'] ?? '',
            'city' => $city['name'], 'barangay' => $barangay['name'],
        ];
    }
}
