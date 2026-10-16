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

                @if(($order['status'] ?? '') === 'ready_for_pickup')
                    <form method="POST" action="{{ route('logistics.database.pickup-rider.assign', $order['id']) }}" class="flex flex-wrap gap-2" onsubmit="return confirm('{{ !empty($order['pickup_rider_id']) ? 'Change the assigned pickup rider?' : 'Assign this pickup rider?' }}');">
                        @csrf
                        <select name="pickup_rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs">
                            <option value="">Select pickup rider</option>
                            @foreach($riders as $rider)
                                <option value="{{ $rider['id'] }}" @selected(($order['pickup_rider_id'] ?? null) == $rider['id'])>{{ $rider['name'] }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">{{ !empty($order['pickup_rider_id']) ? 'Change Pickup Rider' : 'Assign Pickup Rider' }}</button>
                    </form>
                @elseif(($order['status'] ?? '') === 'sorted')
                    <form method="POST" action="{{ route('logistics.database.order.status', $order['id']) }}" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="status" value="assigned_to_rider">
                        <select name="rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs">
                            <option value="">Select rider</option>
                            @foreach($riders as $rider)
                                <option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl bg-[#173F35] px-4 py-2 text-xs font-semibold text-white">Assign Rider</button>
                    </form>
                @elseif(($order['status'] ?? '') === 'assigned_to_rider' && empty($order['rider_index']))
                    <form method="POST" action="{{ route('logistics.database.delivery-rider.reassign', $order['id']) }}" class="flex gap-2">
                        @csrf
                        <select name="rider_id" required class="rounded-xl border border-[#DDE6E1] px-3 py-2 text-xs"><option value="">Reassign delivery rider</option>@foreach($riders as $rider)<option value="{{ $rider['id'] }}">{{ $rider['name'] }}</option>@endforeach</select>
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
                <details class="mt-3 text-xs text-[#52615a]"><summary class="cursor-pointer font-semibold">Assignment history</summary><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($order['assignment_history'] as $event)<li>{{ $event->occurred_at?->format('M j, Y g:i A') }} — {{ $event->note ?? str($event->status)->replace('_', ' ')->headline() }}</li>@endforeach</ul></details>
            @endif
        </div>
    @empty
        <div class="rounded-2xl border border-[#E5ECE8] bg-white p-8 text-center text-sm text-[#728078]">
            No sorted parcels are waiting for assignment.
        </div>
    @endforelse
</div>
@endsection
