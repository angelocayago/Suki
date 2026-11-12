@extends('layouts.logistics')

@section('title', 'Rider Assignments')
@section('page-heading', 'Rider Assignments')

@section('content')
@php
    $assignmentOrders = collect($orders ?? []);
@endphp

<div class="mb-7">
    <h1 class="text-2xl font-bold text-[#24312C]">Rider Assignments</h1>
    <p class="mt-1 text-sm text-[#728078]">Assign seller pickups, hub transfers, and destination-hub last-mile deliveries.</p>
</div>

<div class="space-y-4">
    @if(session('success'))<p class="rounded-xl bg-green-50 p-4 text-sm text-green-900" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ session('error') }}</p>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert"><p class="font-semibold">The assignment could not be saved.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="rounded-xl border border-[#DDE6E1] bg-[#F8FAF9] p-4">
        <h2 class="font-semibold text-[#173F35]">Hub Transfers</h2>
        <p class="mt-1 text-xs text-[#728078]">Sorted parcels wait here for transfer to their destination barangay hub.</p>
        <div class="mt-3 space-y-3">
            @forelse(($hubTransfers ?? collect()) as $transfer)
                @php($transferShipment = $transfer->shipment)
                <div class="rounded-lg border border-[#E5ECE8] bg-white p-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><p class="font-semibold">{{ $transferShipment?->sellerOrder?->order?->order_number ?? 'Shipment' }} · {{ $transferShipment?->tracking_code }}</p><p class="text-xs text-[#617169]">{{ $transfer->originHub?->name ?? 'Main Sorting Center' }} → {{ $transfer->destinationHub?->name ?? 'Destination hub missing' }} · {{ $transfer->status }}</p></div>
                        @if($transferShipment?->workflow_status?->value === 'SORTED')
                            @if(!$transfer->rider_id || in_array($transfer->status, ['DECLINED', 'CANCELLED'], true))
                                <form method="POST" action="{{ route('logistics.database.hub-transfer.assign', $transferShipment->seller_order_id) }}" class="flex flex-wrap gap-2">@csrf<select name="rider_id" class="rounded-lg border-[#DDE6E1] text-xs"><option value="">Transfer rider (optional)</option>@foreach(($transferRiders ?? []) as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach</select><input name="vehicle_reference" maxlength="100" aria-label="Transfer vehicle reference" placeholder="Vehicle reference" class="w-36 rounded-lg border-[#DDE6E1] text-xs"><input name="batch_reference" maxlength="100" aria-label="Transfer batch reference" placeholder="Batch reference" class="w-36 rounded-lg border-[#DDE6E1] text-xs"><button class="rounded-lg bg-[#173F35] px-3 py-2 text-xs font-semibold text-white">Assign transfer</button></form>
                            @elseif($transfer->status === 'ACCEPTED')<a class="font-semibold text-[#1F6F5B] underline" href="{{ route('logistics.shipment-scanner', ['tracking_code' => $transferShipment->tracking_code, 'mode' => 'hub_transfer_dispatch']) }}">Scan dispatch</a>
                            @else<span class="text-xs text-amber-800">Awaiting {{ $transfer->rider?->user?->full_name ?? 'transfer carrier' }} acceptance</span>@endif
                        @elseif($transferShipment?->workflow_status?->value === 'IN_TRANSIT_TO_HUB')
                            <a class="font-semibold text-[#1F6F5B] underline" href="{{ route('logistics.shipment-scanner', ['tracking_code' => $transferShipment->tracking_code, 'mode' => 'hub_arrival', 'hub_id' => $transfer->destination_hub_id]) }}">Scan hub arrival</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-[#728078]">No parcels are awaiting or undergoing hub transfer.</p>
            @endforelse
        </div>
    </section>
    @forelse($assignmentOrders as $order)
        <div class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#24312C]">{{ $order['order_number'] ?? '#'.$order['id'] }}</p>
                    <p class="mt-1 text-xs text-[#728078]">{{ $order['shipping_address']['address'] ?? 'Delivery address unavailable' }}</p>
                    <p class="mt-1 text-xs text-[#1F6F5B]">{{ $order['delivery_area'] ?? 'Unassigned area' }}</p>
                    @if(!empty($order['tracking_code']))
                        <p class="mt-1 text-xs font-semibold text-[#52615a]">Tracking: {{ $order['tracking_code'] }}</p>
                    @endif
                    @if(($order['source'] ?? '') === 'database')
                        <a href="{{ route('shipments.waybill', $order['shipment_id'] ?? 0) }}" class="mt-2 inline-block text-xs font-semibold text-[#1F6F5B] underline">Print waybill</a>
                    @endif
                </div>

                @if(($order['status'] ?? '') === 'delivery_failed')
                    <form method="POST" action="{{ route('logistics.database.delivery.reschedule', $order['id']) }}" class="flex flex-wrap gap-2">
                        @csrf
                        <input type="datetime-local" name="scheduled_at" required class="rounded-xl border px-3 py-2 text-xs">
                        <select name="rider_id" required class="rounded-xl border px-3 py-2 text-xs"><option value="">{{ empty($order['delivery_eligible_riders']) ? 'No eligible Rider available for this area' : 'Select rider' }}</option>@foreach($order['delivery_eligible_riders'] as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach</select>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Reschedule</button>
                    </form>
                @elseif(in_array(($order['status'] ?? ''), ['rts_initiated', 'rts_returning'], true))
                    <form method="POST" action="{{ route('logistics.database.delivery.return', $order['id']) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $order['status'] === 'rts_initiated' ? 'RTS_RETURNING' : 'RTS_COMPLETED' }}">
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">{{ $order['status'] === 'rts_initiated' ? 'Mark returning' : 'Mark returned to Seller' }}</button>
                    </form>
                @elseif(($order['status'] ?? '') === 'rts_completed')
                    <span>Returned to Seller</span>
                @elseif(($order['status'] ?? '') === 'ready_for_pickup')
                    @if(empty($order['pickup_rider_id']))
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-amber-800">Unassigned · Assignment Needed</p>
                            @if(empty($order['pickup_eligible_riders']))
                                <p class="max-w-md rounded-xl bg-amber-50 p-3 text-xs text-amber-900" role="status">No eligible pickup rider covers the Seller's verified pickup area.</p>
                                <p class="flex flex-wrap gap-3 text-xs"><a href="{{ route('logistics.riders') }}" class="font-semibold text-[#1F6F5B] underline">Rider Management</a><a href="{{ route('logistics.hubs.index') }}" class="font-semibold text-[#1F6F5B] underline">Hub / Service Area Configuration</a></p>
                            @else
                                <form method="POST" action="{{ route('logistics.database.pickup-rider.assign', $order['id']) }}" class="flex flex-wrap gap-2" data-pickup-assignment data-order="{{ $order['order_number'] ?? '#'.$order['id'] }}" data-pickup-location="{{ $order['pickup_address'] ?? '' }}">
                                    @csrf
                                    <select name="pickup_rider_id" aria-label="Pickup Rider for {{ $order['order_number'] ?? '#'.$order['id'] }}" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs">
                                        <option value="">Select pickup rider</option>
                                        @foreach($order['pickup_eligible_riders'] as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach
                                    </select>
                                    <button type="submit" class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Assign Pickup Rider</button>
                                </form>
                            @endif
                        </div>
                    @else
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-[#34483F]">Pickup Rider: {{ $order['pickup_rider_name'] ?? 'Unassigned' }}</p>
                            @if(!empty($order['pickup_accepted_at']))
                                <p class="text-xs font-semibold text-sky-800">Awaiting Parcel Collection</p>
                            @else
                                <p class="text-xs font-semibold text-amber-800">Awaiting Rider Acceptance</p>
                                @if(!empty($order['pickup_eligible_riders']))
                                    <form method="POST" action="{{ route('logistics.database.pickup-rider.assign', $order['id']) }}" class="flex flex-wrap gap-2" data-pickup-assignment data-order="{{ $order['order_number'] ?? '#'.$order['id'] }}" data-pickup-location="{{ $order['pickup_address'] ?? '' }}">
                                        @csrf
                                        <select name="pickup_rider_id" aria-label="Reassign Pickup Rider for {{ $order['order_number'] ?? '#'.$order['id'] }}" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs"><option value="">Select replacement pickup rider</option>@foreach($order['pickup_eligible_riders'] as $rider)<option value="{{ $rider['id'] }}" @selected(($order['pickup_rider_id'] ?? null) == $rider['id'])>{{ $rider['name'] }}</option>@endforeach</select>
                                        <button type="submit" class="rounded-xl border border-[#1F6F5B] px-4 py-2 text-xs font-semibold text-[#1F6F5B]">Reassign Pickup Rider</button>
                                    </form>
                                @else
                                    <p class="text-xs text-amber-900" role="status">No eligible replacement pickup rider is available.</p>
                                    <p class="flex flex-wrap gap-3 text-xs"><a href="{{ route('logistics.riders') }}" class="font-semibold text-[#1F6F5B] underline">Rider Management</a><a href="{{ route('logistics.hubs.index') }}" class="font-semibold text-[#1F6F5B] underline">Hub / Service Area Configuration</a></p>
                                @endif
                            @endif
                        </div>
                    @endif
                @elseif(($order['status'] ?? '') === 'at_destination_hub')
                    @if(empty($order['delivery_eligible_riders']))
                        <div class="space-y-2"><p class="text-xs text-amber-900" role="status">No eligible delivery rider covers this destination and sorting hub.</p><p class="flex flex-wrap gap-3 text-xs"><a href="{{ route('logistics.riders') }}" class="font-semibold text-[#1F6F5B] underline">Rider Management</a><a href="{{ route('logistics.hubs.index') }}" class="font-semibold text-[#1F6F5B] underline">Hub / Service Area Configuration</a></p></div>
                    @else
                        <form method="POST" action="{{ route('logistics.database.order.status', $order['id']) }}" class="flex gap-2">
                            @csrf<input type="hidden" name="status" value="assigned_to_rider">
                            <select name="rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs"><option value="">Select delivery rider</option>@foreach($order['delivery_eligible_riders'] as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach</select>
                            <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Assign Delivery Rider</button>
                        </form>
                    @endif
                @elseif(($order['status'] ?? '') === 'assigned_to_rider' && empty($order['rider_index']))
                    <form method="POST" action="{{ route('logistics.database.delivery-rider.reassign', $order['id']) }}" class="flex gap-2">
                        @csrf
                        <select name="rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs"><option value="">{{ empty($order['delivery_eligible_riders']) ? 'No eligible Rider available for this area' : 'Reassign delivery rider' }}</option>@foreach($order['delivery_eligible_riders'] as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach</select>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Reassign Rider</button>
                    </form>
                @else
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">
                        Assigned to {{ $order['rider_name'] ?? 'rider' }}
                    </span>
                @endif
            </div>
            @if(!empty($order['pickup_rider_name']))
                <p class="mt-3 text-xs text-[#52615a]">Pickup rider: <span class="font-semibold">{{ $order['pickup_rider_name'] }}</span>{{ !empty($order['pickup_assignment_open']) ? ' · awaiting acceptance' : '' }}</p>
            @endif
            @if(!empty($order['assignment_history']) && count($order['assignment_history']))
                <details class="mt-3 text-xs text-[#52615a]"><summary class="cursor-pointer font-semibold">Assignment history</summary><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($order['assignment_history'] as $event)<li>{{ \App\Support\ManilaTime::format($event->occurred_at, 'M j, Y g:i A') }} PHT — {{ $event->note ?? str($event->status)->replace('_', ' ')->headline() }}</li>@endforeach</ul></details>
            @endif
            @if(!empty($order['failure_history']) && count($order['failure_history']))
                <details class="mt-3 text-xs"><summary>Failed delivery history</summary><ul>@foreach($order['failure_history'] as $event)<li>Attempt {{ $event->attempt }}: {{ $event->note }} — {{ $event->user?->full_name }} ({{ \App\Support\ManilaTime::format($event->occurred_at, 'M j, Y g:i A') }} PHT)</li>@endforeach</ul></details>
            @endif
        </div>
    @empty
        <div class="rounded-2xl border border-[#E5ECE8] bg-white p-8 text-center text-sm text-[#728078]">
        No parcels currently need a rider assignment or return action.
        </div>
    @endforelse
</div>
<dialog id="pickupAssignmentDialog" aria-labelledby="pickupAssignmentTitle" aria-describedby="pickupAssignmentExplanation" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-2xl border border-[#DDE6E1] p-0 text-[#24312C] shadow-xl backdrop:bg-black/50">
    <div class="p-5 sm:p-6">
        <h2 id="pickupAssignmentTitle" class="text-xl font-bold text-[#173F35]">Confirm Pickup Assignment</h2>
        <dl class="mt-5 space-y-3 break-words text-sm">
            <div><dt class="font-semibold">Order / Parcel</dt><dd id="pickupAssignmentOrder"></dd></div>
            <div><dt class="font-semibold">Selected pickup Rider</dt><dd id="pickupAssignmentRider"></dd></div>
            <div><dt class="font-semibold">Pickup location</dt><dd id="pickupAssignmentLocation"></dd></div>
        </dl>
        <p id="pickupAssignmentExplanation" class="mt-5 text-sm text-[#617169]">This assigns responsibility for collecting the parcel. The Rider must still accept the pickup and confirm parcel collection. An existing pickup assignment will be replaced.</p>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" id="pickupAssignmentCancel" autofocus class="rounded-xl border border-[#1F6F5B] px-4 py-3 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Cancel</button>
            <button type="button" id="pickupAssignmentConfirm" class="rounded-xl bg-[#173F35] px-4 py-3 text-sm font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Confirm Assignment</button>
        </div>
    </div>
</dialog>
@include('logistics.pickup-assignment-script')
@endsection
