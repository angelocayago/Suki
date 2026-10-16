@extends('layouts.logistics')
@section('title', 'Parcel Lookup')
@section('content')
<div class="max-w-2xl space-y-5">
 <div><h1 class="text-2xl font-bold text-[#24312C]">Parcel lookup</h1><p class="mt-1 text-sm text-[#728078]">Enter or scan a tracking number for a parcel handled by your provider.</p></div>
 <form method="GET" class="flex gap-2 rounded-xl border border-[#E5ECE8] bg-white p-4"><input autofocus name="tracking_code" value="{{ request('tracking_code') }}" placeholder="Tracking number" class="flex-1 rounded-lg border-[#DDE6E1] text-sm" required><button class="rounded-lg bg-[#173F35] px-4 py-2 text-sm font-semibold text-white">Find parcel</button></form>
 @if($shipment)<article class="rounded-xl border border-[#E5ECE8] bg-white p-5"><p class="font-mono font-bold">{{ $shipment->tracking_code }}</p><p class="mt-2 text-sm">Status: {{ $shipment->workflow_status?->value }}</p><p class="mt-1 text-sm">Order: {{ $shipment->sellerOrder->order->order_number }}</p><p class="mt-1 text-sm">Seller: {{ $shipment->sellerOrder->seller->name }}</p></article>@elseif(request()->filled('tracking_code'))<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">No parcel from your logistics provider matches that tracking number.</div>@endif
</div>
@endsection
