@extends('layouts.rider')

@section('title', 'My Deliveries')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | RIDER INFORMATION
    |--------------------------------------------------------------------------
    */

    $riderName = session('logged_in_rider_name', 'SUKI Rider');

    /*
    |--------------------------------------------------------------------------
    | ALL ORDERS
    |--------------------------------------------------------------------------
    */

    $allOrders = session('suki_orders', collect());

    if (!($allOrders instanceof \Illuminate\Support\Collection)) {
        $allOrders = collect($allOrders);
    }

    /*
    |--------------------------------------------------------------------------
    | AVAILABLE PICKUPS
    |--------------------------------------------------------------------------
    |
    | Orders that are ready for pickup and do not have a rider assigned yet.
    |
    */

    $availablePickups = $allOrders
        ->filter(function ($order) {
            return ($order['status'] ?? '') === 'ready_for_pickup'
                && empty($order['rider_name']);
        });

    /*
    |--------------------------------------------------------------------------
    | MY DELIVERIES
    |--------------------------------------------------------------------------
    |
    | Only show orders assigned to the currently logged-in rider.
    | Logistics saves the rider_index when assigning a delivery.
    |
    */

    $riderIndex = session('logged_in_rider_index');

    $myOrders = $allOrders
        ->filter(function ($order) use ($riderIndex, $riderName) {

            // Primary check: actual rider index
            if (
                $riderIndex !== null &&
                isset($order['rider_index'])
            ) {
                return (string) $order['rider_index']
                    === (string) $riderIndex;
            }

            // Fallback for older orders created before rider_index
            return ($order['rider_name'] ?? '') === $riderName;
        });

    /*
    |--------------------------------------------------------------------------
    | STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $assignedCount = $myOrders
        ->where('status', 'assigned_to_rider')
        ->count();

    $pickedUpCount = $myOrders
        ->where('status', 'picked_up')
        ->count();

    $sortingCount = $myOrders
        ->where('status', 'at_sorting_center')
        ->count();

    $outForDeliveryCount = $myOrders
        ->where('status', 'out_for_delivery')
        ->count();

    $deliveredCount = $myOrders
        ->whereIn('status', ['delivered', 'completed'])
        ->count();
@endphp


{{-- ============================================================
     PAGE HEADER
============================================================ --}}

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">
        My Deliveries
    </h1>

    <p class="mt-1 text-sm text-gray-500">
        Manage your assigned deliveries and available pickups.
    </p>
</div>


{{-- ============================================================
     STATUS SUMMARY
============================================================ --}}

<div class="grid grid-cols-2 gap-4 mb-8 md:grid-cols-5">

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Assigned</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $assignedCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Picked Up</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $pickedUpCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Sorting Center</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $sortingCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Out for Delivery</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $outForDeliveryCount }}
        </p>
    </div>

    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm">
        <p class="text-xs text-gray-500">Delivered</p>
        <p class="mt-1 text-2xl font-bold text-gray-800">
            {{ $deliveredCount }}
        </p>
    </div>

</div>


{{-- ============================================================
     AVAILABLE PICKUPS
============================================================ --}}

<div class="mb-8">

    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-800">
                Available Pickups
            </h2>

            <p class="text-sm text-gray-500">
                Accept a delivery that is waiting for a rider.
            </p>
        </div>

        <span class="px-3 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
            {{ $availablePickups->count() }} Available
        </span>
    </div>


    @if ($availablePickups->count())

        <div class="space-y-4">

            @foreach ($availablePickups as $order)

                @php
                    $orderId = $order['id'] ?? $loop->index;
                @endphp

                <div class="p-5 bg-white border border-gray-100 rounded-xl shadow-sm">

                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">

                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-gray-800">
                                    Order #{{ $orderId }}
                                </h3>

                                <span class="px-2 py-1 text-xs font-medium text-yellow-700 bg-yellow-100 rounded-full">
                                    Ready for Pickup
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-500">
                                {{ $order['customer_name'] ?? 'Customer' }}
                            </p>

                            @if (!empty($order['address']))
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $order['address'] }}
                                </p>
                            @endif
                        </div>


                        {{-- Accept Pickup --}}

                        <form
                            method="POST"
                            action="{{ route('rider.order.status', $orderId) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="status"
                                value="assigned_to_rider"
                            >

                            <button
                                type="submit"
                                class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition md:w-auto"
                            >
                                Accept Pickup
                            </button>
                        </form>

                    </div>

                </div>

            @endforeach

        </div>

    @else

        <div class="p-8 text-center bg-white border border-gray-100 rounded-xl">

            <div class="flex items-center justify-center w-14 h-14 mx-auto mb-3 rounded-full bg-gray-100">
                <svg
                    class="w-7 h-7 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4"
                    />
                </svg>
            </div>

            <h3 class="font-semibold text-gray-700">
                No available pickups
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                There are currently no orders waiting for a rider.
            </p>

        </div>

    @endif

</div>


{{-- ============================================================
     MY ASSIGNED DELIVERIES
============================================================ --}}

<div>

    <div class="mb-4">
        <h2 class="text-lg font-bold text-gray-800">
            My Assigned Deliveries
        </h2>

        <p class="text-sm text-gray-500">
            Track and update your current deliveries.
        </p>
    </div>


    @if ($myOrders->count())

        <div class="space-y-4">

            @foreach ($myOrders as $order)

                @php
                    $orderId = $order['id'] ?? $loop->index;
                    $status = $order['status'] ?? 'assigned_to_rider';

                    $statusLabels = [
                        'assigned_to_rider' => 'Assigned to Rider',
                        'picked_up' => 'Picked Up',
                        'at_sorting_center' => 'At Sorting Center',
                        'out_for_delivery' => 'Out for Delivery',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                    ];

                    $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                @endphp


                <div class="p-5 bg-white border border-gray-100 rounded-xl shadow-sm">

                    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

                        {{-- Order Details --}}

                        <div class="flex-1">

                            <div class="flex flex-wrap items-center gap-2">

                                <h3 class="font-bold text-gray-800">
                                    Order #{{ $orderId }}
                                </h3>

                                <span class="px-2.5 py-1 text-xs font-semibold text-green-700 bg-green-100 rounded-full">
                                    {{ $statusLabel }}
                                </span>

                            </div>


                            <div class="mt-3 space-y-1">

                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Customer:</span>
                                    {{ $order['customer_name'] ?? 'Customer' }}
                                </p>

                                @if (!empty($order['address']))
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Address:</span>
                                        {{ $order['address'] }}
                                    </p>
                                @endif

                                @if (isset($order['total']))
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium">Total:</span>
                                        ₱{{ number_format((float) $order['total'], 2) }}
                                    </p>
                                @endif

                            </div>

                        </div>


                        {{-- Status Action --}}

                        <div class="w-full lg:w-auto">

                            @if ($status === 'assigned_to_rider')

                                <form
                                    method="POST"
                                    action="{{ route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="picked_up"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Mark as Picked Up
                                    </button>
                                </form>


                            @elseif ($status === 'picked_up')

                                <form
                                    method="POST"
                                    action="{{ route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="at_sorting_center"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Send to Sorting Center
                                    </button>
                                </form>


                            @elseif ($status === 'at_sorting_center')

                                <span class="inline-flex px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg">
                                    Processing at Sorting Center
                                </span>


                            @elseif ($status === 'out_for_delivery')

                                <form
                                    method="POST"
                                    action="{{ route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="delivered"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-[#1F6F5B] rounded-lg hover:bg-[#155244] transition lg:w-auto"
                                    >
                                        Mark as Delivered
                                    </button>
                                </form>


                            @elseif ($status === 'delivered')

                                <form
                                    method="POST"
                                    action="{{ route('rider.order.status', $orderId) }}"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="completed"
                                    >

                                    <button
                                        type="submit"
                                        class="w-full px-5 py-2.5 text-sm font-semibold text-white bg-gray-700 rounded-lg hover:bg-gray-800 transition lg:w-auto"
                                    >
                                        Complete Delivery
                                    </button>
                                </form>


                            @elseif ($status === 'completed')

                                <span class="inline-flex px-4 py-2 text-sm font-semibold text-green-700 bg-green-100 rounded-lg">
                                    Delivery Completed
                                </span>

                            @endif

                        </div>

                    </div>

                </div>

            @endforeach

        </div>

    @else

        <div class="p-8 text-center bg-white border border-gray-100 rounded-xl">

            <h3 class="font-semibold text-gray-700">
                No assigned deliveries
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                Your accepted deliveries will appear here.
            </p>

        </div>

    @endif

</div>

@endsection