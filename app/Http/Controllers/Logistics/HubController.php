<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Services\PhilippineAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HubController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $this->provider($request);
        return view('logistics.hubs.index', [
            'hubs' => $provider->hubs()->withCount('riders')->orderByDesc('is_active')->orderBy('name')->get(),
            'riders' => $provider->riders()->with(['user', 'logisticsHub'])->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, PhilippineAddressService $addresses): RedirectResponse
    {
        $provider = $this->provider($request);
        $data = $request->validate(['name' => ['nullable','string','max:150'], 'region' => ['required','string','max:20'], 'province' => ['nullable','string','max:20'], 'municipality' => ['required','string','max:20'], 'barangay' => ['required','string','max:20']]);
        $resolved = $addresses->resolve($data);
        LogisticsHub::updateOrCreate(
            ['logistics_provider_id' => $provider->id, 'barangay_code' => $resolved['barangay_code']],
            ['name' => filled($data['name'] ?? null) ? trim($data['name']) : $resolved['barangay'].' Hub', 'region' => $resolved['region'], 'region_code' => $resolved['region_code'], 'province' => $resolved['province'], 'province_code' => $resolved['province_code'], 'city' => $resolved['municipality'], 'city_municipality_code' => $resolved['city_municipality_code'], 'barangay' => $resolved['barangay'], 'postal_code' => $resolved['postal_code'] ?? null, 'is_active' => true]
        );
        return back()->with('success', 'Barangay hub saved.');
    }

    public function toggle(Request $request, LogisticsHub $hub): RedirectResponse
    {
        abort_unless($hub->logistics_provider_id === $this->provider($request)->id, 404);
        $hub->update(['is_active' => ! $hub->is_active]);
        return back()->with('success', 'Hub status updated.');
    }

    public function assignRider(Request $request, Rider $rider): RedirectResponse
    {
        $provider = $this->provider($request);
        abort_unless($rider->logistics_provider_id === $provider->id, 404);
        $data = $request->validate(['logistics_hub_id' => ['nullable', Rule::exists('logistics_hubs','id')]]);
        $hub = filled($data['logistics_hub_id'] ?? null) ? $provider->hubs()->whereKey($data['logistics_hub_id'])->where('is_active', true)->firstOrFail() : null;
        $rider->update(['logistics_hub_id' => $hub?->id]);
        return back()->with('success', 'Rider hub updated.');
    }

    private function provider(Request $request)
    {
        return $request->user()->logisticsProvider()->where('status', 'approved')->firstOrFail();
    }
}
