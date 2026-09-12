@extends('layouts.rider')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | RIDER INFORMATION
    |--------------------------------------------------------------------------
    */

    $riderName = trim(
        ($application['first_name'] ?? '') . ' ' .
        ($application['last_name'] ?? '')
    );

    if ($riderName === '') {
        $riderName = 'Rider';
    }


    /*
    |--------------------------------------------------------------------------
    | ALL ORDERS
    |--------------------------------------------------------------------------
    */

    $allOrders = collect($orders ?? []);


    /*
    |--------------------------------------------------------------------------
    | AVAILABLE PICKUP REQUESTS
    |--------------------------------------------------------------------------
    |
    | Only show orders that:
    | - Are ready for pickup
    | - Have not been accepted by another rider
    |
    */

    $availablePickups = $allOrders
        ->filter(function ($order) {

            return ($order['status'] ?? '') === 'ready_for_pickup'
                && empty($order['rider_name']);

        });


    $availablePickupCount = $availablePickups->count();


    /*
    |--------------------------------------------------------------------------
    | MY DELIVERIES
    |--------------------------------------------------------------------------
    |
    | Only orders assigned to the currently logged-in rider.
    |
    */

    $myOrders = $allOrders
        ->filter(function ($order) use ($riderName) {

            return ($order['rider_name'] ?? '') === $riderName;

        });


    /*
    |--------------------------------------------------------------------------
    | DELIVERY COUNTS
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


    $completedCount = $myOrders
        ->where('status', 'delivered')
        ->count();


    $activeCount =
        $assignedCount +
        $pickedUpCount +
        $sortingCount +
        $outForDeliveryCount;


    /*
    |--------------------------------------------------------------------------
    | STATUS INFORMATION
    |--------------------------------------------------------------------------
    */

    $statusLabels = [

        'assigned_to_rider' => 'Assigned',

        'picked_up' => 'Picked Up',

        'at_sorting_center' => 'At Sorting Center',

        'out_for_delivery' => 'Out for Delivery',

        'delivered' => 'Delivered',

    ];


    $statusClasses = [

        'assigned_to_rider' =>
            'bg-cyan-50 text-cyan-700 border-cyan-200',

        'picked_up' =>
            'bg-blue-50 text-blue-700 border-blue-200',

        'at_sorting_center' =>
            'bg-indigo-50 text-indigo-700 border-indigo-200',

        'out_for_delivery' =>
            'bg-orange-50 text-orange-700 border-orange-200',

        'delivered' =>
            'bg-green-50 text-green-700 border-green-200',

    ];

@endphp


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">


    {{-- =====================================================
         HEADER
    ====================================================== --}}

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5 mb-8">

        <div>

            <p class="text-sm font-semibold text-[#1F6F5B] mb-1">
                SUKI SHOP Rider
            </p>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                Deliveries
            </h1>

            <p class="text-sm text-gray-500 mt-2">
                Accept pickup requests and manage your delivery assignments.
            </p>

        </div>


        <a
            href="{{ route('rider.dashboard') }}"
            class="inline-flex items-center justify-center gap-2 self-start px-4 py-2.5 rounded-lg border border-gray-200 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50 transition"
        >

            <i
                data-lucide="arrow-left"
                class="w-4 h-4"
            ></i>

            Dashboard

        </a>

    </div>


    {{-- =====================================================
         FLASH MESSAGES
    ====================================================== --}}

    @if(session('success'))

        <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-5 py-4">

            <i
                data-lucide="circle-check"
                class="w-5 h-5 text-green-600 shrink-0"
            ></i>

            <p class="text-sm font-medium text-green-700">
                {{ session('success') }}
            </p>

        </div>

    @endif


    @if(session('error'))

        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">

            <i
                data-lucide="circle-alert"
                class="w-5 h-5 text-red-600 shrink-0"
            ></i>

            <p class="text-sm font-medium text-red-700">
                {{ session('error') }}
            </p>

        </div>

    @endif


    {{-- =====================================================
         SUMMARY CARDS
    ====================================================== --}}

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-8">


        {{-- AVAILABLE PICKUPS --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Available Pickups
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $availablePickupCount }}
                    </p>

                    <p class="text-xs text-amber-600 mt-2">
                        Waiting to be accepted
                    </p>

                </div>


                <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">

                    <i
                        data-lucide="package-plus"
                        class="w-5 h-5 text-amber-600"
                    ></i>

                </div>

            </div>

        </div>


        {{-- ACTIVE DELIVERIES --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Active Deliveries
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $activeCount }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Currently in progress
                    </p>

                </div>


                <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="truck"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- OUT FOR DELIVERY --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Out for Delivery
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $outForDeliveryCount }}
                    </p>

                    <p class="text-xs text-orange-600 mt-2">
                        Delivering to customers
                    </p>

                </div>


                <div class="w-11 h-11 rounded-xl bg-orange-50 flex items-center justify-center">

                    <i
                        data-lucide="map-pin"
                        class="w-5 h-5 text-orange-600"
                    ></i>

                </div>

            </div>

        </div>


        {{-- COMPLETED --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Completed
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $completedCount }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Successfully delivered
                    </p>

                </div>


                <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         AVAILABLE PICKUP REQUESTS
    ====================================================== --}}

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden mb-6">


        {{-- SECTION HEADER --}}

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 sm:px-6 py-5 border-b border-gray-100">

            <div>

                <div class="flex items-center gap-2">

                    <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">

                        <i
                            data-lucide="package-check"
                            class="w-4 h-4 text-amber-600"
                        ></i>

                    </div>


                    <div>

                        <h2 class="text-lg font-semibold text-gray-900">
                            Available Pickup Requests
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            First come, first served pickup opportunities.
                        </p>

                    </div>

                </div>

            </div>


            <span class="inline-flex items-center self-start sm:self-auto px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">

                {{ $availablePickupCount }}

                {{ $availablePickupCount === 1 ? 'Request' : 'Requests' }}

            </span>

        </div>


        {{-- PICKUP REQUESTS --}}

        @if($availablePickupCount > 0)

            <div class="divide-y divide-gray-100">

                @foreach($availablePickups as $orderId => $order)

                    @php

                        $customerName =
                            $order['customer_name']
                            ?? $order['buyer_name']
                            ?? 'Customer';


                        $address =
                            $order['shipping_address']
                            ?? $order['address']
                            ?? 'Pickup / delivery address unavailable';


                        $total =
                            $order['total']
                            ?? $order['grand_total']
                            ?? 0;


                        $sellerName =
                            $order['seller_name']
                            ?? $order['shop_name']
                            ?? 'SUKI SHOP Seller';

                    @endphp


                    <div class="p-5 sm:p-6">

                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">


                            {{-- REQUEST INFO --}}

                            <div class="flex-1">


                                <div class="flex flex-wrap items-center gap-2 mb-4">

                                    <span class="font-semibold text-gray-900">
                                        Order #{{ $orderId }}
                                    </span>


                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-xs font-semibold">

                                        <i
                                            data-lucide="clock"
                                            class="w-3.5 h-3.5"
                                        ></i>

                                        Ready for Pickup

                                    </span>

                                </div>


                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">


                                    <div class="flex items-start gap-3">

                                        <div class="w-9 h-9 shrink-0 rounded-lg bg-gray-50 flex items-center justify-center">

                                            <i
                                                data-lucide="store"
                                                class="w-4 h-4 text-gray-500"
                                            ></i>

                                        </div>


                                        <div>

                                            <p class="text-xs text-gray-400">
                                                Pickup From
                                            </p>

                                            <p class="font-medium text-gray-800 mt-1">
                                                {{ $sellerName }}
                                            </p>

                                        </div>

                                    </div>


                                    <div class="flex items-start gap-3">

                                        <div class="w-9 h-9 shrink-0 rounded-lg bg-gray-50 flex items-center justify-center">

                                            <i
                                                data-lucide="map-pin"
                                                class="w-4 h-4 text-gray-500"
                                            ></i>

                                        </div>


                                        <div>

                                            <p class="text-xs text-gray-400">
                                                Deliver To
                                            </p>

                                            <p class="font-medium text-gray-800 mt-1">
                                                {{ $customerName }}
                                            </p>

                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $address }}
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            {{-- ACCEPT PICKUP --}}

                            <div class="w-full lg:w-auto">

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
                                        class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                    >

                                        <i
                                            data-lucide="hand"
                                            class="w-4 h-4"
                                        ></i>

                                        Accept Pickup

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>


        @else


            {{-- EMPTY AVAILABLE REQUESTS --}}

            <div class="px-6 py-14 text-center">

                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-50 flex items-center justify-center">

                    <i
                        data-lucide="package-search"
                        class="w-7 h-7 text-gray-400"
                    ></i>

                </div>


                <h3 class="text-base font-semibold text-gray-900">
                    No pickup requests available
                </h3>


                <p class="text-sm text-gray-500 mt-2 max-w-md mx-auto">
                    New pickup requests will appear here when sellers prepare orders.
                </p>

            </div>

        @endif

    </div>


    {{-- =====================================================
         MY DELIVERIES
    ====================================================== --}}

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">


        {{-- SECTION HEADER --}}

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 px-5 sm:px-6 py-5 border-b border-gray-100">

            <div>

                <div class="flex items-center gap-2">

                    <div class="w-9 h-9 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="truck"
                            class="w-4 h-4 text-[#1F6F5B]"
                        ></i>

                    </div>


                    <div>

                        <h2 class="text-lg font-semibold text-gray-900">
                            My Delivery Assignments
                        </h2>

                        <p class="text-sm text-gray-500 mt-1">
                            Orders currently assigned to you.
                        </p>

                    </div>

                </div>

            </div>


            <span class="inline-flex items-center self-start sm:self-auto px-3 py-1.5 rounded-full bg-[#EEF8F3] text-[#1F6F5B] text-xs font-semibold">

                {{ $myOrders->count() }}

                {{ $myOrders->count() === 1 ? 'Order' : 'Orders' }}

            </span>

        </div>


        {{-- MY ORDERS --}}

        @if($myOrders->count() > 0)

            <div class="divide-y divide-gray-100">

                @foreach($myOrders as $orderId => $order)

                    @php

                        $status = $order['status'] ?? 'assigned_to_rider';


                        $statusLabel =
                            $statusLabels[$status]
                            ?? ucfirst(str_replace('_', ' ', $status));


                        $statusClass =
                            $statusClasses[$status]
                            ?? 'bg-gray-50 text-gray-600 border-gray-200';


                        $customerName =
                            $order['customer_name']
                            ?? $order['buyer_name']
                            ?? 'Customer';


                        $address =
                            $order['shipping_address']
                            ?? $order['address']
                            ?? 'Delivery address unavailable';


                        $total =
                            $order['total']
                            ?? $order['grand_total']
                            ?? 0;

                    @endphp


                    <div class="p-5 sm:p-6">


                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">


                            {{-- DELIVERY INFORMATION --}}

                            <div class="flex-1">


                                <div class="flex flex-wrap items-center gap-2 mb-4">

                                    <span class="font-semibold text-gray-900">
                                        Order #{{ $orderId }}
                                    </span>


                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold {{ $statusClass }}">

                                        {{ $statusLabel }}

                                    </span>

                                </div>


                                <div class="space-y-3 text-sm">


                                    {{-- CUSTOMER --}}

                                    <div class="flex items-start gap-3">

                                        <div class="w-9 h-9 shrink-0 rounded-lg bg-gray-50 flex items-center justify-center">

                                            <i
                                                data-lucide="user"
                                                class="w-4 h-4 text-gray-500"
                                            ></i>

                                        </div>


                                        <div>

                                            <p class="text-xs text-gray-400">
                                                Customer
                                            </p>

                                            <p class="font-medium text-gray-800 mt-1">
                                                {{ $customerName }}
                                            </p>

                                        </div>

                                    </div>


                                    {{-- ADDRESS --}}

                                    <div class="flex items-start gap-3">

                                        <div class="w-9 h-9 shrink-0 rounded-lg bg-gray-50 flex items-center justify-center">

                                            <i
                                                data-lucide="map-pin"
                                                class="w-4 h-4 text-gray-500"
                                            ></i>

                                        </div>


                                        <div>

                                            <p class="text-xs text-gray-400">
                                                Delivery Address
                                            </p>

                                            <p class="text-sm text-gray-600 mt-1">
                                                {{ $address }}
                                            </p>

                                        </div>

                                    </div>


                                    {{-- TOTAL --}}

                                    <div class="flex items-center gap-3">

                                        <div class="w-9 h-9 shrink-0 rounded-lg bg-gray-50 flex items-center justify-center">

                                            <i
                                                data-lucide="wallet"
                                                class="w-4 h-4 text-gray-500"
                                            ></i>

                                        </div>


                                        <div>

                                            <p class="text-xs text-gray-400">
                                                Order Total
                                            </p>

                                            <p class="font-semibold text-gray-900 mt-1">
                                                ₱{{ number_format((float) $total, 2) }}
                                            </p>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            {{-- ACTIONS --}}

                            <div class="w-full lg:w-auto">


                                {{-- ASSIGNED --}}

                                @if($status === 'assigned_to_rider')

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
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >

                                            <i
                                                data-lucide="package-check"
                                                class="w-4 h-4"
                                            ></i>

                                            Confirm Pickup

                                        </button>

                                    </form>


                                {{-- PICKED UP --}}

                                @elseif($status === 'picked_up')

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
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >

                                            <i
                                                data-lucide="warehouse"
                                                class="w-4 h-4"
                                            ></i>

                                            Arrived at Sorting Center

                                        </button>

                                    </form>


                                {{-- AT SORTING CENTER --}}

                                @elseif($status === 'at_sorting_center')

                                    <div class="inline-flex w-full lg:w-auto items-center justify-center gap-2 px-5 py-3 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-semibold">

                                        <i
                                            data-lucide="warehouse"
                                            class="w-4 h-4"
                                        ></i>

                                        Processing at Sorting Center

                                    </div>


                                {{-- OUT FOR DELIVERY --}}

                                @elseif($status === 'out_for_delivery')

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
                                            class="w-full lg:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                                        >

                                            <i
                                                data-lucide="circle-check"
                                                class="w-4 h-4"
                                            ></i>

                                            Mark as Delivered

                                        </button>

                                    </form>


                                {{-- DELIVERED --}}

                                @elseif($status === 'delivered')

                                    <div class="inline-flex w-full lg:w-auto items-center justify-center gap-2 px-5 py-3 rounded-lg bg-green-50 text-green-700 border border-green-200 text-sm font-semibold">

                                        <i
                                            data-lucide="circle-check"
                                            class="w-4 h-4"
                                        ></i>

                                        Delivery Completed

                                    </div>


                                {{-- FALLBACK --}}

                                @else

                                    <div class="inline-flex w-full lg:w-auto items-center justify-center px-5 py-3 rounded-lg bg-gray-50 text-gray-500 border border-gray-200 text-sm font-medium">

                                        No Action Available

                                    </div>

                                @endif

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>


        @else


            {{-- EMPTY STATE --}}

            <div class="px-6 py-16 text-center">

                <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-50 flex items-center justify-center">

                    <i
                        data-lucide="truck"
                        class="w-7 h-7 text-gray-400"
                    ></i>

                </div>


                <h3 class="text-base font-semibold text-gray-900 mb-1">
                    No delivery assignments yet
                </h3>


                <p class="text-sm text-gray-500 max-w-md mx-auto">
                    Accept an available pickup request to start managing your deliveries.
                </p>

            </div>

        @endif

    </div>

</div>

@endsection