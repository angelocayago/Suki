<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

trait FakesCheckoutLocations
{
    protected function fakeCheckoutLocations(array $overrides = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake(array_merge([
            'https://psgc.cloud/api/v2/regions' => Http::response(['data' => [['code' => '01', 'name' => 'Region A'], ['code' => '13', 'name' => 'National Capital Region']]]),
            'https://psgc.cloud/api/v2/regions/01/provinces' => Http::response(['data' => [['code' => '0101', 'name' => 'Province A']]]),
            'https://psgc.cloud/api/v2/provinces/0101/cities-municipalities' => Http::response(['data' => [['code' => '010101', 'name' => 'City A']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/010101/barangays' => Http::response(['data' => [['code' => '010101001', 'name' => 'Barangay A']]]),
            'https://psgc.cloud/api/v2/regions/13/provinces' => Http::response(['data' => []]),
            'https://psgc.cloud/api/v2/regions/13/cities-municipalities' => Http::response(['data' => [['code' => '130001', 'name' => 'NCR City']]]),
            'https://psgc.cloud/api/v2/cities-municipalities/130001/barangays' => Http::response(['data' => [['code' => '130001001', 'name' => 'NCR Barangay']]]),
        ], $overrides));
    }

    protected function checkoutLocationCodes(bool $provinceLess = false): array
    {
        return $provinceLess
            ? ['region_code' => '13', 'province_code' => null, 'city_municipality_code' => '130001', 'barangay_code' => '130001001']
            : ['region_code' => '01', 'province_code' => '0101', 'city_municipality_code' => '010101', 'barangay_code' => '010101001'];
    }
}
