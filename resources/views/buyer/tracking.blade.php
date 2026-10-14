@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#F8FAF8]"><div class="mx-auto max-w-5xl px-4 py-8">
    <a href="{{ route('buyer.order-details', $order->order_number) }}" class="text-sm font-medium text-[#1F6F5B]">← Back to order</a>
    <div class="mt-5 rounded-2xl border border-gray-200 bg-white p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-[#1F6F5B]">Live delivery tracking</p>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $sellerOrder->seller->name }} · {{ $sellerOrder->shipment->tracking_code ?? 'Shipment pending' }}</h1>
        <p id="tracking-status" class="mt-2 text-sm text-gray-600">{{ str($sellerOrder->shipment?->workflow_status?->value ?? 'PENDING')->replace('_', ' ')->headline() }}</p>
        <div id="tracking-map" class="mt-5 flex h-72 items-center justify-center rounded-xl bg-[#EEF8F3] text-sm text-gray-600">Waiting for the rider's live location.</div>
        <p id="tracking-updated" class="mt-3 text-xs text-gray-500">Location updates appear while the assigned rider is on an active final-mile delivery.</p>
    </div>
</div></div>
@push('scripts')
@if(config('services.maptiler.style_url'))
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
@endif
<script>
const trackingUrl = @json(route('buyer.seller-order.tracking-data', [$order, $sellerOrder]));
const mapElement = document.getElementById('tracking-map');
const updated = document.getElementById('tracking-updated');
const mapStyleUrl = @json(config('services.maptiler.style_url'));
let liveMap = null;
let riderMarker = null;

function updateMap(latitude, longitude) {
  if (!mapStyleUrl || !window.maplibregl) {
    mapElement.textContent = `Rider location: ${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
    return;
  }
  if (!liveMap) {
    mapElement.textContent = '';
    liveMap = new maplibregl.Map({container: mapElement, style: mapStyleUrl, center: [longitude, latitude], zoom: 14});
    liveMap.addControl(new maplibregl.NavigationControl(), 'top-right');
    riderMarker = new maplibregl.Marker({color: '#1F6F5B'}).setLngLat([longitude, latitude]).addTo(liveMap);
    return;
  }
  riderMarker.setLngLat([longitude, latitude]);
  liveMap.easeTo({center: [longitude, latitude], duration: 500});
}
async function refreshTracking() {
  const response = await fetch(trackingUrl, {headers:{Accept:'application/json'}});
  if (!response.ok) return;
  const data = await response.json();
  document.getElementById('tracking-status').textContent = (data.status || 'PENDING').replaceAll('_', ' ');
  if (data.latitude === null || data.longitude === null) return;
  updateMap(data.latitude, data.longitude);
  updated.textContent = `Last updated: ${new Date(data.updated_at).toLocaleString()}`;
}
refreshTracking(); setInterval(refreshTracking, 15000);
</script>
@endpush
@endsection
