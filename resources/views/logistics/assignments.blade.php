@extends('layouts.logistics')

@section('title', 'Delivery Assignments')
@section('page-heading', 'Delivery Assignments')

@section('content')
@php
    $assignmentOrders = collect($orders ?? []);
@endphp

<div class="mb-7">
    <h1 class="text-2xl font-bold text-[#24312C]">Delivery Assignments</h1>
    <p class="mt-1 text-sm text-[#728078]">Assign sorted parcels to active riders in your logistics provider.</p>
</div>

<div class="space-y-4">
    @forelse($assignmentOrders as $order)
        <div class="rounded-2xl border border-[#E5ECE8] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold text-[#24312C]">{{ $order['order_number'] ?? '#'.$order['id'] }}</p>
                    <p class="mt-1 text-xs text-[#728078]">{{ $order['shipping_address']['address'] ?? 'Delivery address unavailable' }}</p>
                    <p class="mt-1 text-xs text-[#1F6F5B]">{{ $order['delivery_area'] ?? 'Unassigned area' }}</p>
                </div>

                @if(($order['status'] ?? '') === 'sorted')
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
                @else
                    <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">
                        Assigned to {{ $order['rider_name'] ?? 'rider' }}
                    </span>
                @endif
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-[#E5ECE8] bg-white p-8 text-center text-sm text-[#728078]">
            No sorted parcels are waiting for assignment.
        </div>
    @endforelse
</div>
@endsection
