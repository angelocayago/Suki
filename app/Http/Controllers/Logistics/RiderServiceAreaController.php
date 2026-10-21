<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\RiderServiceArea;
use App\Services\PhilippineAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RiderServiceAreaController extends Controller
{
    public function index(Request $request, Rider $rider): View
    {
        $provider = $this->provider($request);
        abort_unless($rider->logistics_provider_id === $provider->id, 403);
        return view('logistics.rider-service-areas', compact('rider', 'provider'));
    }

    public function store(Request $request, Rider $rider, PhilippineAddressService $addresses): RedirectResponse
    {
        $provider = $this->provider($request);
        abort_unless($rider->logistics_provider_id === $provider->id, 403);
        $data = $request->validate([
            'region' => ['required', 'string', 'max:20'], 'province' => ['nullable', 'string', 'max:20'],
            'municipality' => ['required', 'string', 'max:20'], 'barangay' => ['nullable', 'string', 'max:20'],
        ]);
        // A blank barangay creates a city-wide authority; if populated it is PSGC-verified.
        if (! filled($data['barangay'] ?? null)) {
            $resolved = $addresses->resolve(array_merge($data, ['barangay' => $this->firstBarangay($addresses, $data)]));
            $resolved['barangay_code'] = null;
        } else {
            $resolved = $addresses->resolve($data);
        }
        $key = RiderServiceArea::key($resolved['region_code'], $resolved['province_code'], $resolved['city_municipality_code'], $resolved['barangay_code']);
        DB::transaction(function () use ($rider, $provider, $resolved, $key): void {
            $area = RiderServiceArea::query()->lockForUpdate()->firstOrNew(['rider_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'area_key' => $key]);
            $area->fill(['region_code' => $resolved['region_code'], 'province_code' => $resolved['province_code'], 'city_municipality_code' => $resolved['city_municipality_code'], 'barangay_code' => $resolved['barangay_code'], 'is_active' => true, 'deactivated_at' => null]);
            $area->save();
        }, 3);
        return back()->with('success', 'Verified rider service area saved.');
    }

    public function destroy(Request $request, Rider $rider, RiderServiceArea $area): RedirectResponse
    {
        $provider = $this->provider($request);
        abort_unless($rider->id === $area->rider_id && $area->logistics_provider_id === $provider->id && $rider->logistics_provider_id === $provider->id, 403);
        $area->update(['is_active' => false, 'deactivated_at' => now()]);
        return back()->with('success', 'Rider service area deactivated.');
    }

    private function provider(Request $request): LogisticsProvider
    {
        return $request->user()->logisticsProvider()->where('status', 'approved')->firstOrFail();
    }

    private function firstBarangay(PhilippineAddressService $addresses, array $data): string
    {
        $regions = $addresses->locations('regions');
        $region = collect($regions)->firstWhere('code', $data['region']);
        if (! $region) throw ValidationException::withMessages(['region' => 'Select a valid region.']);
        $provinces = $addresses->locations('regions/'.rawurlencode($region['code']).'/provinces');
        $path = $provinces === [] ? 'regions/'.rawurlencode($region['code']).'/cities-municipalities' : 'provinces/'.rawurlencode((string) $data['province']).'/cities-municipalities';
        $cities = $addresses->locations($path);
        if (! collect($cities)->firstWhere('code', $data['municipality'])) throw ValidationException::withMessages(['municipality' => 'Select a valid city or municipality.']);
        $barangay = $addresses->locations('cities-municipalities/'.rawurlencode($data['municipality']).'/barangays')[0]['code'] ?? null;
        if (! $barangay) throw ValidationException::withMessages(['barangay' => 'This city has no verifiable barangay data.']);
        return $barangay;
    }
}
