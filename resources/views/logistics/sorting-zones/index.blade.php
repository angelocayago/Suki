@extends('layouts.logistics')
@section('title', 'Sorting Zones')
@section('content')
@php($field = 'mt-1 block w-full rounded-lg border border-[#DDE6E1] bg-white px-3 py-2 text-sm focus:border-[#1F6F5B] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/20')
<main class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-[#173F35]">Sorting Zones</h1><p class="mt-1 text-sm text-[#617169]">Map delivery destinations to your active sorting zones using verified Philippine location choices.</p></div>
        <a class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-sm font-semibold text-[#1F6F5B]" href="{{ route('logistics.sorting') }}">Parcel Sorting</a>
    </header>
    @if(session('success'))<p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <section class="rounded-xl border border-[#E1E8E4] bg-white p-4 shadow-sm">
        <h2 class="font-semibold text-[#173F35]">Create a sorting zone</h2>
        <form method="POST" action="{{ route('logistics.sorting-zones.store') }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
            <label class="text-xs font-medium">Zone code<input name="zone_code" required maxlength="40" class="{{ $field }}" placeholder="SC-01"></label>
            <label class="text-xs font-medium">Zone name<input name="name" required maxlength="150" class="{{ $field }}" placeholder="Santa Cruz Central"></label>
            <label class="text-xs font-medium">Linked hub<select name="logistics_hub_id" class="{{ $field }}"><option value="">No linked hub</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((string) old('logistics_hub_id') === (string) $hub->id)>{{ $hub->name }}</option>@endforeach</select></label>
            <label class="text-xs font-medium">Priority <span class="font-normal text-[#728078]">(lower wins a tie)</span><input name="priority" type="number" min="0" max="65535" value="{{ old('priority', 100) }}" class="{{ $field }}"></label>
            <div class="sm:col-span-2 lg:col-span-4"><button class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1F6F5B]">Create zone</button></div>
        </form>
    </section>

    <section class="space-y-3" aria-label="Your sorting zones">
        @forelse($zones as $zone)
            <article class="rounded-xl border border-[#E1E8E4] bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="font-semibold text-[#173F35]">{{ $zone->zone_code }} — {{ $zone->name }}</h2><p class="mt-1 text-xs text-[#617169]">Hub: {{ $zone->hub?->name ?? 'Not linked' }} · Priority {{ $zone->priority }} · {{ $zone->is_active ? 'Active' : 'Inactive' }}</p></div>
                    <form method="POST" action="{{ route('logistics.sorting-zones.toggle', $zone) }}">@csrf @method('PATCH')<button class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">{{ $zone->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                </div>

                <form method="POST" action="{{ route('logistics.sorting-zones.areas.store', $zone) }}"
                      data-sorting-zone-area-form data-zone-id="{{ $zone->id }}"
                      data-initial-region="{{ (string) old('zone_id') === (string) $zone->id ? old('region_code') : '' }}"
                      data-initial-province="{{ (string) old('zone_id') === (string) $zone->id ? old('province_code') : '' }}"
                      data-initial-city="{{ (string) old('zone_id') === (string) $zone->id ? old('city_municipality_code') : '' }}"
                      data-initial-barangay="{{ (string) old('zone_id') === (string) $zone->id ? old('barangay_code') : '' }}"
                      data-initial-city-wide="{{ (string) old('zone_id') === (string) $zone->id && old('city_wide') ? '1' : '0' }}"
                      class="mt-4 space-y-3 border-t border-[#EDF1EF] pt-4">
                    @csrf<input type="hidden" name="zone_id" value="{{ $zone->id }}">
                    <p class="text-xs font-semibold text-[#34483F]">Add destination coverage</p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-xs font-medium text-[#34483F]">Region<select required name="region_code" data-address-region class="{{ $field }}" disabled><option value="">Loading regions…</option></select></label>
                        <label class="text-xs font-medium text-[#34483F]">Province <span class="font-normal text-[#728078]">(when applicable)</span><select name="province_code" data-address-province class="{{ $field }}" disabled><option value="">Select region first</option></select></label>
                        <label class="text-xs font-medium text-[#34483F]">City / Municipality<select required name="city_municipality_code" data-address-city class="{{ $field }}" disabled><option value="">Select region first</option></select></label>
                        <div data-address-barangay-wrap><label class="text-xs font-medium text-[#34483F]">Barangay<select required name="barangay_code" data-address-barangay class="{{ $field }}" disabled><option value="">Select city first</option></select></label></div>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-[#34483F]"><input type="checkbox" name="city_wide" value="1" data-address-city-wide class="mt-0.5 rounded border-[#DDE6E1] text-[#1F6F5B]" disabled><span><span class="font-medium">Apply to the entire city/municipality</span><span class="mt-0.5 block text-xs text-[#728078]">Use this when the zone covers every barangay in the selected city.</span></span></label>
                    <p data-address-status role="status" aria-live="polite" class="text-xs text-[#617169]">Loading location choices…</p>
                    <div data-address-error role="alert" hidden class="rounded-lg bg-red-50 p-3 text-sm text-red-800">Location choices could not be loaded. <button type="button" data-address-retry class="font-semibold underline">Retry</button></div>
                    <noscript><p class="text-sm text-red-700">Enable JavaScript to load verified Philippine location choices.</p></noscript>
                    <button type="submit" data-address-save disabled class="rounded-lg bg-[#1F6F5B] px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Add destination mapping</button>
                </form>

                <ul class="mt-3 divide-y divide-[#EDF1EF]">
                    @forelse($zone->areas as $area)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-medium text-[#34483F]">{{ $area->location_display ?: 'Location names temporarily unavailable' }}</p>
                                <p class="mt-0.5 text-xs text-[#617169]">{{ $area->barangay_code ? 'Barangay-specific mapping' : 'Entire city/municipality' }}</p>
                                <details class="mt-1 text-[11px] text-[#839189]"><summary class="cursor-pointer">PSGC codes</summary><p class="mt-1">Region {{ $area->region_code }} · Province {{ $area->province_code ?? 'N/A' }} · City/Municipality {{ $area->city_municipality_code }} · Barangay {{ $area->barangay_code ?? 'All' }}</p></details>
                            </div>
                            <form method="POST" action="{{ route('logistics.sorting-zones.areas.delete', [$zone, $area]) }}">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-700 underline">Remove mapping</button></form>
                        </li>
                    @empty<li class="py-2 text-xs text-amber-800">No destination mappings yet. This zone will not be recommended until a mapping is added.</li>@endforelse
                </ul>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-[#DDE6E1] bg-white p-5 text-sm text-[#617169]">No sorting zones configured. Create a zone and map its destination coverage.</p>
        @endforelse
    </section>
</main>
@vite('resources/js/sorting-zone-address.js')
@endsection
