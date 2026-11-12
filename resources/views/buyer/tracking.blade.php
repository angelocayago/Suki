@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#F8FAF8]"><div class="mx-auto max-w-5xl space-y-5 px-4 py-8">
    <nav class="flex flex-wrap gap-4 text-sm font-medium text-[#155244]" aria-label="Order navigation">
        <a href="{{ route('buyer.my-orders') }}" class="rounded py-2 focus-visible:outline focus-visible:outline-2">My Purchases</a>
        <a href="{{ route('buyer.order-details', $order->order_number) }}" class="rounded py-2 focus-visible:outline focus-visible:outline-2">Back to order</a>
        <a href="{{ route('buyer.seller-order.track', [$order, $sellerOrder]) }}" class="rounded py-2 focus-visible:outline focus-visible:outline-2">Refresh tracking</a>
    </nav>
    <h1 class="text-2xl font-bold text-gray-900">Package tracking</h1>
    <div id="package-tracking">@include('buyer.partials.order-tracking')</div>
    <section class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5" aria-label="Selected package location">
        <h2 class="font-bold text-gray-900">Live delivery tracking</h2>
        <p class="mt-1 break-words text-sm text-gray-700">{{ $sellerOrder->seller?->name ?? 'Store unavailable' }} · {{ $sellerOrder->shipment?->tracking_code ?? 'Shipment pending' }}</p>
        <p id="tracking-status" class="mt-2 text-sm text-gray-700"></p>
        <p id="tracking-error" role="alert" class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-950" hidden></p>
        <div id="tracking-map" class="mt-5 flex h-72 items-center justify-center rounded-xl bg-[#EEF8F3] p-4 text-center text-sm text-gray-700">Live location is not available for this package yet.</div>
        <p id="tracking-updated" class="mt-3 text-xs text-gray-600">Location time not recorded.</p>
        <p id="tracking-location-state" class="mt-2 text-xs text-gray-600">Locations are last reported positions, not continuous live movement.</p>
    </section>
</div></div>
@php
    $initialTracking = ['status' => \App\Support\BuyerOrderTracking::status($sellerOrder->shipment ?? $sellerOrder), 'terminal' => $tracking['cancelled'] || in_array(\App\Support\BuyerOrderTracking::status($sellerOrder->shipment ?? $sellerOrder), ['CANCELLED', 'DELIVERED', 'COMPLETED', 'RTS_COMPLETED'], true), 'status_label' => \App\Support\BuyerOrderTracking::label($tracking['cancelled'] ? 'CANCELLED' : \App\Support\BuyerOrderTracking::status($sellerOrder->shipment ?? $sellerOrder)), 'latitude' => $sellerOrder->shipment?->current_latitude === null ? null : (float) $sellerOrder->shipment?->current_latitude, 'longitude' => $sellerOrder->shipment?->current_longitude === null ? null : (float) $sellerOrder->shipment?->current_longitude, 'updated_at' => $sellerOrder->shipment?->location_updated_at?->toIso8601String()];
@endphp
@push('scripts')
@if(config('services.maptiler.style_url'))
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
@endif
<script>
(() => {
const trackingUrl = @json($sellerOrder->shipment ? route('buyer.seller-order.tracking-data', [$order, $sellerOrder]) : null);
const initialTracking = @json($initialTracking);
const mapStyleUrl = @json(config('services.maptiler.style_url'));
const mapElement = document.getElementById('tracking-map');
const updated = document.getElementById('tracking-updated');
const error = document.getElementById('tracking-error');
const locationState = document.getElementById('tracking-location-state');
let liveMap = null, riderMarker = null, timer = null, inFlight = false, stopped = false, failures = 0;
const sessionMessage = 'Your session expired. Please refresh the page or log in again.';
function schedule() {
    clearTimeout(timer);
    if (!stopped && trackingUrl && !document.hidden) timer = setTimeout(refreshTracking, Math.min(60000, 15000 * (2 ** Math.min(failures, 2))));
}
function updateMap(latitude, longitude) {
    if (!mapStyleUrl || !window.maplibregl) { mapElement.textContent = `Last reported rider location: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`; return; }
    if (!liveMap) {
        mapElement.textContent = '';
        liveMap = new maplibregl.Map({container:mapElement, style:mapStyleUrl, center:[longitude,latitude], zoom:14});
        liveMap.addControl(new maplibregl.NavigationControl(), 'top-right');
        riderMarker = new maplibregl.Marker({color:'#1F6F5B'}).setLngLat([longitude,latitude]).addTo(liveMap);
    } else {
        riderMarker.setLngLat([longitude,latitude]);
        liveMap.easeTo({center:[longitude,latitude], duration:window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : 500});
    }
}
function render(data) {
    // Terminal handling must run even when the response has no coordinates.
    if (data.terminal || ['CANCELLED','DELIVERED','COMPLETED','RTS_COMPLETED'].includes(data.status)) { stopped = true; clearTimeout(timer); }
    document.getElementById('tracking-status').textContent = data.status_label || 'Status unavailable';
    const packages = document.getElementById('package-tracking');
    if (typeof data.tracking_html === 'string' && !packages.contains(document.activeElement)) {
        const expanded = new Map([...packages.querySelectorAll('details')].map(item => [item.dataset.trackingActivity,item.open]));
        packages.innerHTML = data.tracking_html;
        packages.querySelectorAll('details').forEach(item => item.open = Boolean(expanded.get(item.dataset.trackingActivity)));
    }
    locationState.textContent = stopped ? 'Tracking has ended. Any location shown is the last known position.' : 'Locations are last reported positions, not continuous live movement.';
    if (Number.isFinite(data.latitude) && Number.isFinite(data.longitude)) updateMap(data.latitude,data.longitude);
    const date = data.updated_at ? new Date(data.updated_at) : null;
    updated.replaceChildren();
    if (date && !Number.isNaN(date.getTime())) {
        const time = document.createElement('time');
        time.dateTime = date.toISOString();
        time.textContent = new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',year:'numeric',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(date) + ' PHT';
        updated.append('Last location update: ',time);
    } else updated.textContent = 'Location time not recorded.';
}
async function refreshTracking() {
    clearTimeout(timer);
    if (stopped || !trackingUrl || document.hidden || inFlight) return;
    inFlight = true;
    try {
        const response = await fetch(trackingUrl,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
        if (response.status === 401 || response.status === 419) { stopped = true; throw new Error(sessionMessage); }
        if (response.status === 403 || response.status === 404) { stopped = true; throw new Error('Tracking is unavailable. Please return to My Purchases.'); }
        if (!response.ok) throw new Error('Tracking updates are unavailable. We will retry shortly.');
        const data = await response.json();
        if (!data || typeof data.trackable !== 'boolean' || !('status' in data)) throw new Error('Tracking updates are unavailable. We will retry shortly.');
        render(data); failures = 0; error.hidden = true;
    } catch (_) {
        failures++;
        error.textContent = stopped ? (_.message === sessionMessage ? sessionMessage : 'Tracking is unavailable. Please return to My Purchases.') : 'Tracking updates are unavailable. We will retry shortly.';
        error.hidden = false;
        locationState.textContent = 'Location updates are unavailable. Any position shown is the last known location.';
    } finally { inFlight = false; schedule(); }
}
document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) refreshTracking(); });
window.addEventListener('focus', () => { if (!document.hidden) refreshTracking(); });
window.addEventListener('pagehide', () => clearTimeout(timer));
window.addEventListener('pageshow', () => { if (!document.hidden) refreshTracking(); });
render(initialTracking);
refreshTracking();
})();
</script>
@endpush
@endsection
