@extends('layouts.logistics')
@section('title', 'Barangay Hubs')
@section('content')
@php
    $activeHubs = $hubs->where('is_active', true);
    $approvedRiders = $riders->filter(fn ($rider) => $rider->approved_at !== null);
    $fieldClass = 'mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm focus:border-[#1F6F5B] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/20 disabled:bg-gray-100 disabled:text-gray-500';
    $buttonClass = 'rounded-xl bg-[#173F35] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#1F6F5B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
@endphp
<div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-[#173F35]">Barangay Hubs</h1><p class="mt-1 text-sm text-gray-600">Set up your provider's hubs and connect Riders to pickup coverage.</p></div>
        <a href="{{ route('logistics.assignments') }}" class="rounded-xl border border-[#1F6F5B] px-4 py-2 text-sm font-semibold text-[#1F6F5B] hover:bg-green-50">Delivery Assignment &rarr;</a>
    </header>
    @if(session('success'))<div role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('success') }}</div>@endif
    @if(session('error'))<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Please review the form. Your changes were not saved.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section aria-labelledby="hub-setup-title" class="rounded-2xl border border-[#DDE6E1] bg-[#EEF8F3] p-5">
        <h2 id="hub-setup-title" class="font-semibold text-[#173F35]">Set up pickup coverage</h2>
        <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-gray-700">
            <li>Create a Hub covering the Seller pickup barangay.</li>
            <li>Ensure the Hub is active.</li>
            <li>Assign an approved Rider to the Hub.</li>
            <li>Configure the Rider's service areas using the links below.</li>
            <li>Return to <a href="{{ route('logistics.assignments') }}" class="font-semibold text-[#1F6F5B] underline">Delivery Assignment</a>.</li>
        </ol>
        <p class="mt-3 text-xs text-gray-600">Hub assignment does not grant service-area coverage or activate a Rider. Pickup still requires approval, activation, contact verification, online availability, and matching service-area and hub coverage.</p>
    </section>
    <section aria-labelledby="create-hub-title" class="rounded-2xl border border-[#DDE6E1] bg-white p-5 sm:p-6">
        <h2 id="create-hub-title" class="text-lg font-semibold text-[#173F35]">Create or update a Hub</h2>
        <p class="mt-1 text-sm text-gray-600">One hub per barangay for your provider. Saving an existing barangay updates that hub and makes it active.</p>
        <form id="hubLocationForm" method="POST" action="{{ route('logistics.hubs.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
              data-initial-region="{{ old('region', '') }}" data-initial-province="{{ old('province', '') }}" data-initial-city="{{ old('municipality', '') }}" data-initial-barangay="{{ old('barangay', '') }}">
            @csrf
            <div><label for="hubName" class="text-sm font-medium">Hub name <span class="font-normal text-gray-500">(optional)</span></label><input id="hubName" name="name" maxlength="150" value="{{ old('name') }}" placeholder="Defaults to the barangay name" class="{{ $fieldClass }}">@error('name')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="hubRegion" class="text-sm font-medium">Region <span aria-hidden="true">*</span></label><select id="hubRegion" name="region" required disabled class="{{ $fieldClass }}"><option value="">Loading regions...</option></select>@error('region')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="hubProvince" class="text-sm font-medium">Province <span class="font-normal text-gray-500">(when applicable)</span></label><select id="hubProvince" name="province" disabled class="{{ $fieldClass }}"><option value="">Select region first</option></select>@error('province')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="hubCity" class="text-sm font-medium">City / Municipality <span aria-hidden="true">*</span></label><select id="hubCity" name="municipality" required disabled class="{{ $fieldClass }}"><option value="">Select province or region first</option></select>@error('municipality')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="hubBarangay" class="text-sm font-medium">Barangay <span aria-hidden="true">*</span></label><select id="hubBarangay" name="barangay" required disabled class="{{ $fieldClass }}"><option value="">Select city first</option></select>@error('barangay')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="hubPostalCode" class="text-sm font-medium">Postal code</label><input id="hubPostalCode" readonly aria-describedby="hubPostalHelp" placeholder="Resolved automatically" class="{{ $fieldClass }}"><p id="hubPostalHelp" class="mt-1 text-xs text-gray-500">Resolved from the selected city; no postal code is invented if unavailable.</p></div>
            <div class="sm:col-span-2 lg:col-span-3">
                <p id="hubLocationStatus" role="status" class="text-xs text-gray-600">Loading location choices...</p>
                <div id="hubLocationError" role="alert" hidden class="mt-2 rounded-lg bg-red-50 p-3 text-sm text-red-800"><span id="hubLocationErrorText"></span><button id="hubLocationRetry" type="button" class="ml-2 font-semibold underline">Retry loading locations</button></div>
                <noscript><p class="mt-2 text-sm text-red-700">Enable JavaScript to load verified location choices.</p></noscript>
                <button id="hubSave" disabled class="{{ $buttonClass }} mt-4">Save hub</button>
            </div>
        </form>
    </section>
    <section aria-labelledby="existing-hubs-title" class="space-y-4">
        <div class="flex items-center justify-between"><h2 id="existing-hubs-title" class="text-lg font-semibold text-[#173F35]">Your Hubs</h2><span class="text-sm text-gray-500">{{ $hubs->count() }} total &middot; {{ $activeHubs->count() }} active</span></div>
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($hubs as $hub)
                @php($associated = $riders->where('logistics_hub_id', $hub->id))
                <article class="min-w-0 rounded-2xl border border-[#DDE6E1] bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="break-words font-semibold text-[#173F35]">{{ $hub->name }}</h3><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-green-50 text-green-800' => $hub->is_active, 'bg-gray-100 text-gray-600' => !$hub->is_active])>{{ $hub->is_active ? 'Active' : 'Inactive' }}</span></div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        @foreach(['Region' => $hub->region, 'Province' => $hub->province ?: 'Not applicable', 'City / Municipality' => $hub->city, 'Barangay' => $hub->barangay, 'Postal code' => $hub->postal_code ?: 'Not available'] as $label => $value)
                            <div class="min-w-0"><dt class="text-xs text-gray-500">{{ $label }}</dt><dd class="mt-1 break-words font-medium">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="mt-4 border-t border-gray-100 pt-3"><p class="text-sm font-semibold">Associated Riders ({{ $hub->riders_count }})</p><ul class="mt-1 space-y-1 text-sm text-gray-600">@forelse($associated as $assignedRider)<li>{{ $assignedRider->user?->full_name ?? 'Rider' }}</li>@empty<li>No Riders assigned to this hub.</li>@endforelse</ul></div>
                    <form method="POST" action="{{ route('logistics.hubs.toggle', $hub) }}" class="mt-4">@csrf @method('PATCH')<button class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-sm font-semibold text-[#1F6F5B] hover:bg-green-50">{{ $hub->is_active ? 'Deactivate' : 'Activate' }} <span class="sr-only">{{ $hub->name }}</span></button></form>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-[#DDE6E1] bg-white p-6 text-sm text-gray-600 md:col-span-2">No hubs created. Use the form above to create a hub for the Seller pickup barangay.</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="rider-hub-title" class="space-y-4">
        <h2 id="rider-hub-title" class="text-lg font-semibold text-[#173F35]">Rider-to-Hub assignment</h2>
        @if($activeHubs->isEmpty())<p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">No active hubs available. Create a hub or activate an existing one before assigning a Rider.</p>@endif
        <div class="grid gap-4 md:grid-cols-2">
            @forelse($approvedRiders as $rider)
                <article class="min-w-0 rounded-2xl border border-[#DDE6E1] bg-white p-5">
                    <h3 class="font-semibold text-[#173F35]">{{ $rider->user?->full_name ?? 'Rider' }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ $rider->vehicle_type }} &middot; {{ $rider->availability ?? 'ONLINE' }}</p>
                    <p class="mt-3 text-sm">Assigned hub: <strong>{{ $rider->logisticsHub?->name ?? 'No hub assigned' }}</strong>@if($rider->logisticsHub && !$rider->logisticsHub->is_active)<span class="ml-1 text-amber-800">(inactive)</span>@endif</p>
                    @if($activeHubs->isNotEmpty())
                        <form method="POST" action="{{ route('logistics.hubs.riders.assign', $rider) }}" class="mt-4 space-y-2">
                            @csrf @method('PATCH')
                            <label for="riderHub{{ $rider->id }}" class="block text-xs font-medium text-gray-600">Choose an active hub</label>
                            <div class="flex flex-col gap-2 sm:flex-row"><select id="riderHub{{ $rider->id }}" name="logistics_hub_id" required class="min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm"><option value="">Select an active hub</option>@foreach($activeHubs as $hub)<option value="{{ $hub->id }}" @selected($rider->logistics_hub_id === $hub->id)>{{ $hub->name }} — {{ $hub->barangay }}, {{ $hub->city }}</option>@endforeach</select><button class="{{ $buttonClass }}">{{ $rider->logistics_hub_id ? 'Update hub' : 'Assign hub' }}</button></div>
                        </form>
                    @endif
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <a href="{{ route('logistics.riders.service-areas', $rider) }}" class="text-sm font-semibold text-[#1F6F5B] underline">Manage Service Areas</a>
                        @if($rider->logistics_hub_id)<form method="POST" action="{{ route('logistics.hubs.riders.assign', $rider) }}">@csrf @method('PATCH')<input type="hidden" name="logistics_hub_id" value=""><button class="text-sm font-medium text-red-700 underline">Remove hub assignment</button></form>@endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[#DDE6E1] bg-white p-6 text-sm text-gray-600 md:col-span-2">No approved Riders available for hub assignment. Complete approval in <a href="{{ route('logistics.riders') }}" class="font-semibold text-[#1F6F5B] underline">Rider Management</a> first.</div>
            @endforelse
        </div>
    </section>
</div>
@include('logistics.hubs.location-script')
@endsection
