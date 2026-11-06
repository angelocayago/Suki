@extends('layouts.logistics')

@section('title', 'Delivery Assignments')
@section('page-heading', 'Delivery Assignments')

@section('content')
@php
    $assignmentOrders = collect($orders ?? []);
@endphp

<div class="mb-7">
    <h1 class="text-2xl font-bold text-[#24312C]">Delivery Assignments</h1>
    <p class="mt-1 text-sm text-[#728078]">Assign ready pickups and sorted parcels to active riders in your logistics provider.</p>
</div>

<div class="space-y-4">
    @if(session('success'))<p class="rounded-xl bg-green-50 p-4 text-sm text-green-900" role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p class="rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert">{{ session('error') }}</p>@endif
    @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-900" role="alert"><p class="font-semibold">The assignment could not be saved.</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
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
                    @if(empty($order['pickup_eligible_riders']))
                        <p class="max-w-md rounded-xl bg-amber-50 p-3 text-xs text-amber-900" role="status">No eligible pickup riders. A rider must be approved, activated and verified, active and online, belong to your provider, and have an active hub and service area covering the Seller's verified pickup address. Review Rider Management and service-area setup before assigning.</p>
                    @else
                    <form method="POST" action="{{ route('logistics.database.pickup-rider.assign', $order['id']) }}" class="flex flex-wrap gap-2" data-pickup-assignment data-order="{{ $order['order_number'] ?? '#'.$order['id'] }}" data-pickup-location="{{ $order['pickup_address'] ?? '' }}">
                        @csrf
                        <select name="pickup_rider_id" aria-label="Pickup Rider for {{ $order['order_number'] ?? '#'.$order['id'] }}" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs">
                            <option value="">Select pickup rider</option>
                            @foreach($order['pickup_eligible_riders'] as $rider)
                                <option value="{{ $rider['id'] }}" @selected(($order['pickup_rider_id'] ?? null) == $rider['id'])>{{ $rider['name'] }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">{{ !empty($order['pickup_rider_id']) ? 'Change Pickup Rider' : 'Assign Pickup Rider' }}</button>
                    </form>
                    @endif
                @elseif(($order['status'] ?? '') === 'sorted')
                    <form method="POST" action="{{ route('logistics.database.order.status', $order['id']) }}" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="status" value="assigned_to_rider">
                        <select name="rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs">
                            <option value="">Select rider</option>
                            @foreach($order['delivery_eligible_riders'] as $rider)
                                <option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Assign Rider</button>
                    </form>
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
            No sorted parcels are waiting for assignment.
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
