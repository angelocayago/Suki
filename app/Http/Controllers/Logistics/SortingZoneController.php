<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsHub;
use App\Models\LogisticsSortingZone;
use App\Models\LogisticsSortingZoneArea;
use App\Services\SortingZoneResolver;
use App\Services\PhilippineAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SortingZoneController extends Controller
{
    public function index(Request $request, PhilippineAddressService $addresses): View
    {
        $provider = $this->provider($request);
        $zones = $provider->sortingZones()->with(['hub', 'areas'])->orderBy('priority')->orderBy('zone_code')->get();
        foreach ($zones as $zone) {
            foreach ($zone->areas as $area) {
                try {
                    $location = $addresses->resolveArea([
                        'region' => $area->region_code,
                        'province' => $area->province_code,
                        'municipality' => $area->city_municipality_code,
                        'barangay' => $area->barangay_code,
                    ], $area->barangay_code === null);
                    $area->setAttribute('location_display', collect([$location['region'], $location['province'], $location['municipality'], $location['barangay']])->filter()->implode(' · '));
                } catch (\Illuminate\Validation\ValidationException) {
                    $area->setAttribute('location_display', null);
                }
            }
        }
        return view('logistics.sorting-zones.index', [
            'zones' => $zones,
            'hubs' => $provider->hubs()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $this->provider($request);
        $request->merge(['zone_code' => strtoupper(trim((string) $request->input('zone_code', '')))]);
        $data = $request->validate([
            'zone_code' => ['required', 'string', 'max:40', Rule::unique('logistics_sorting_zones')->where('logistics_provider_id', $provider->id)],
            'name' => ['required', 'string', 'max:150'],
            'logistics_hub_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
        $hub = filled($data['logistics_hub_id'] ?? null)
            ? $provider->hubs()->where('is_active', true)->findOrFail($data['logistics_hub_id'])
            : null;
        $provider->sortingZones()->create([
            'zone_code' => strtoupper(trim($data['zone_code'])),
            'name' => trim($data['name']),
            'logistics_hub_id' => $hub?->id,
            'priority' => (int) ($data['priority'] ?? 100),
            'is_active' => true,
        ]);
        return back()->with('success', 'Sorting zone created. Add its destination codes below.');
    }

    public function storeArea(Request $request, LogisticsSortingZone $zone, SortingZoneResolver $resolver, PhilippineAddressService $addresses): RedirectResponse
    {
        $provider = $this->provider($request);
        abort_unless($zone->logistics_provider_id === $provider->id, 404);
        $data = $request->validate([
            'region_code' => ['required', 'string', 'regex:/^\d+$/', 'max:20'],
            'province_code' => ['nullable', 'string', 'regex:/^\d+$/', 'max:20'],
            'city_municipality_code' => ['required', 'string', 'regex:/^\d+$/', 'max:20'],
            'barangay_code' => ['nullable', 'string', 'regex:/^\d+$/', 'max:20'],
            'city_wide' => ['sometimes', 'boolean'],
        ]);
        $cityWide = $request->boolean('city_wide');
        if ($cityWide && filled($data['barangay_code'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['barangay_code' => 'A city-wide mapping cannot also select a barangay.']);
        }
        $resolved = $addresses->resolveArea([
            'region' => trim($data['region_code']),
            'province' => filled($data['province_code'] ?? null) ? trim($data['province_code']) : null,
            'municipality' => trim($data['city_municipality_code']),
            'barangay' => filled($data['barangay_code'] ?? null) ? trim($data['barangay_code']) : null,
        ], $cityWide);
        $area = [
            'region_code' => $resolved['region_code'],
            'province_code' => $resolved['province_code'],
            'city_municipality_code' => $resolved['city_municipality_code'],
            'barangay_code' => $resolved['barangay_code'],
        ];
        $area['area_key'] = $resolver->areaKey($area['region_code'], $area['province_code'], $area['city_municipality_code'], $area['barangay_code']);
        $zone->areas()->updateOrCreate(['area_key' => $area['area_key']], $area);
        return back()->with('success', $cityWide ? 'City-wide destination mapping saved.' : 'Barangay destination mapping saved.');
    }

    public function deleteArea(Request $request, LogisticsSortingZone $zone, LogisticsSortingZoneArea $area): RedirectResponse
    {
        abort_unless($zone->logistics_provider_id === $this->provider($request)->id && $area->logistics_sorting_zone_id === $zone->id, 404);
        $area->delete();
        return back()->with('success', 'Destination mapping removed.');
    }

    public function toggle(Request $request, LogisticsSortingZone $zone): RedirectResponse
    {
        abort_unless($zone->logistics_provider_id === $this->provider($request)->id, 404);
        $zone->update(['is_active' => ! $zone->is_active]);
        return back()->with('success', 'Sorting zone status updated.');
    }

    private function provider(Request $request)
    {
        return $request->user()->logisticsProvider()->where('status', 'approved')->firstOrFail();
    }
}
