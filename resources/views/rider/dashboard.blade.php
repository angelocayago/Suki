@extends('layouts.rider')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    $orders = session('orders', []);


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
    | AVAILABLE PICKUP REQUESTS
    |--------------------------------------------------------------------------
    |
    | Orders ready for pickup that have not yet been accepted
    | by another rider.
    |
    */

    $availablePickups = collect($orders)
        ->filter(function ($order) {

            return ($order['status'] ?? '') === 'ready_for_pickup'
                && empty($order['rider_name'];

        });


    $availablePickupCount = $availablePickups->count();


    /*
    |--------------------------------------------------------------------------
    | MY RIDER ORDERS
    |--------------------------------------------------------------------------
    */

    $myOrders = collect($orders)
        ->filter(function ($order) use ($riderName) {

            return ($order['rider_name'] ?? '') === $riderName;

        });


    /*
    |--------------------------------------------------------------------------
    | DELIVERY STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $assigned = $myOrders
        ->where('status', 'assigned_to_rider')
        ->count();


    $pickedUp = $myOrders
        ->where('status', 'picked_up')
        ->count();


    $atSortingCenter = $myOrders
        ->where('status', 'at_sorting_center')
        ->count();


    $outForDelivery = $myOrders
        ->where('status', 'out_for_delivery')
        ->count();


    $delivered = $myOrders
        ->where('status', 'delivered')
        ->count();


    /*
    |--------------------------------------------------------------------------
    | DASHBOARD SUMMARY
    |--------------------------------------------------------------------------
    */

    $activeDeliveries =
        $assigned +
        $pickedUp +
        $atSortingCenter +
        $outForDelivery;


    $totalCompleted = $delivered;


    /*
    |--------------------------------------------------------------------------
    | EARNINGS
    |--------------------------------------------------------------------------
    */

    $deliveryFee = 50;

    $todayEarnings =
        $totalCompleted * $deliveryFee;


    /*
    |--------------------------------------------------------------------------
    | RECENT ACTIVE TASKS
    |--------------------------------------------------------------------------
    */

    $recentOrders = $myOrders
        ->filter(function ($order) {

            return in_array(
                $order['status'] ?? '',
                [
                    'assigned_to_rider',
                    'picked_up',
                    'at_sorting_center',
                    'out_for_delivery',
                ]
            );

        })
        ->reverse()
        ->take(3);


    /*
    |--------------------------------------------------------------------------
    | GREETING
    |--------------------------------------------------------------------------
    */

    $hour = now()->format('H');

    if ($hour < 12) {
        $greeting = 'Good morning';
    } elseif ($hour < 18) {
        $greeting = 'Good afternoon';
    } else {
        $greeting = 'Good evening';
    }

@endphp


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

    {{-- =====================================================
         WELCOME HEADER
    ====================================================== --}}

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 sm:mb-8">

        <div>

            <div class="flex items-center gap-2">

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                    {{ $greeting }}, {{ $riderName }}!
                </h1>

                <span class="text-2xl">
                    👋
                </span>

            </div>

            <p class="text-sm sm:text-base text-gray-500 mt-2">
                Here's what's happening with your deliveries today.
            </p>

        </div>


        {{-- ONLINE STATUS --}}

        <div class="inline-flex items-center gap-2 self-start sm:self-auto px-4 py-2.5 rounded-full bg-[#EEF8F3] border border-[#D7EFE5]">

            <span class="relative flex w-2.5 h-2.5">

                <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-60 animate-ping"></span>

                <span class="relative inline-flex rounded-full w-2.5 h-2.5 bg-green-500"></span>

            </span>

            <span class="text-sm font-semibold text-[#1F6F5B]">
                You're Online
            </span>

        </div>

    </div>


    {{-- =====================================================
         AVAILABLE PICKUP ALERT
    ====================================================== --}}

    @if($availablePickupCount > 0)

        <div class="mb-6 rounded-2xl bg-gradient-to-r from-[#FFF8E8] to-[#FFFDF7] border border-[#FDE7B2] p-4 sm:p-5">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div class="flex items-start gap-4">

                    <div class="w-12 h-12 shrink-0 rounded-xl bg-amber-100 flex items-center justify-center">

                        <i
                            data-lucide="package-check"
                            class="w-6 h-6 text-amber-600"
                        ></i>

                    </div>


                    <div>

                        <p class="font-semibold text-gray-900">
                            New pickup requests available!
                        </p>

                        <p class="text-sm text-gray-600 mt-1">

                            {{ $availablePickupCount }}

                            {{ $availablePickupCount === 1 ? 'order is' : 'orders are' }}

                            waiting for a rider.

                        </p>

                    </div>

                </div>


                <a
                    href="{{ route('rider.deliveries') }}"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-[#F59E0B] text-white text-sm font-semibold hover:bg-amber-600 transition shadow-sm"
                >

                    View Requests

                    <i
                        data-lucide="arrow-right"
                        class="w-4 h-4"
                    ></i>

                </a>

            </div>

        </div>

    @endif


    {{-- =====================================================
         SUMMARY CARDS
    ====================================================== --}}

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-6 sm:mb-8">


        {{-- AVAILABLE PICKUPS --}}

        <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-xs sm:text-sm text-gray-500">
                        Available Pickups
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $availablePickupCount }}
                    </p>

                    <p class="text-xs text-amber-600 mt-2">
                        Ready to accept
                    </p>

                </div>


                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">

                    <i
                        data-lucide="package-plus"
                        class="w-5 h-5 text-amber-600"
                    ></i>

                </div>

            </div>

        </div>


        {{-- ACTIVE DELIVERIES --}}

        <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-xs sm:text-sm text-gray-500">
                        Active Deliveries
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $activeDeliveries }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Currently active
                    </p>

                </div>


                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center shrink-0">

                    <i
                        data-lucide="truck"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- COMPLETED --}}

        <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-xs sm:text-sm text-gray-500">
                        Completed
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $totalCompleted }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Successfully delivered
                    </p>

                </div>


                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center shrink-0">

                    <i
                        data-lucide="circle-check"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- EARNINGS --}}

        <div class="bg-white border border-gray-200 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-md transition">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-xs sm:text-sm text-gray-500">
                        Today's Earnings
                    </p>

                    <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2">
                        ₱{{ number_format($todayEarnings, 2) }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Completed deliveries
                    </p>

                </div>


                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center shrink-0">

                    <i
                        data-lucide="wallet"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         MAIN GRID
    ====================================================== --}}

    <div class="grid grid-cols-1 xl:grid-cols-[1.45fr_1fr] gap-5 mb-5">


        {{-- =================================================
             DELIVERY OVERVIEW
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-bold text-gray-900">
                        Delivery Overview
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Track the status of your delivery tasks.
                    </p>

                </div>


                <a
                    href="{{ route('rider.deliveries') }}"
                    class="text-sm font-semibold text-[#1F6F5B] hover:text-[#155244]"
                >
                    View All
                </a>

            </div>


            <div class="p-5 sm:p-6 space-y-3">


                {{-- STATUS ITEM --}}

                @php

                    $overviewStatuses = [

                        [
                            'label' => 'Available Pickups',
                            'description' => 'Waiting for a rider to accept',
                            'count' => $availablePickupCount,
                            'icon' => 'package-check',
                            'iconBg' => 'bg-amber-50',
                            'iconColor' => 'text-amber-600',
                        ],

                        [
                            'label' => 'Assigned',
                            'description' => 'Accepted delivery assignments',
                            'count' => $assigned,
                            'icon' => 'clipboard-check',
                            'iconBg' => 'bg-[#EEF8F3]',
                            'iconColor' => 'text-[#1F6F5B]',
                        ],

                        [
                            'label' => 'Picked Up',
                            'description' => 'Collected from seller',
                            'count' => $pickedUp,
                            'icon' => 'package',
                            'iconBg' => 'bg-[#EEF8F3]',
                            'iconColor' => 'text-[#1F6F5B]',
                        ],

                        [
                            'label' => 'At Sorting Center',
                            'description' => 'Being processed',
                            'count' => $atSortingCenter,
                            'icon' => 'warehouse',
                            'iconBg' => 'bg-blue-50',
                            'iconColor' => 'text-blue-600',
                        ],

                        [
                            'label' => 'Out for Delivery',
                            'description' => 'Heading to customer',
                            'count' => $outForDelivery,
                            'icon' => 'truck',
                            'iconBg' => 'bg-[#EEF8F3]',
                            'iconColor' => 'text-[#1F6F5B]',
                        ],

                        [
                            'label' => 'Delivered',
                            'description' => 'Successfully completed',
                            'count' => $delivered,
                            'icon' => 'circle-check',
                            'iconBg' => 'bg-[#EEF8F3]',
                            'iconColor' => 'text-[#1F6F5B]',
                        ],

                    ];

                @endphp


                @foreach($overviewStatuses as $item)

                    <div class="flex items-center justify-between gap-4 p-3 rounded-xl hover:bg-gray-50 transition">

                        <div class="flex items-center gap-4 min-w-0">

                            <div class="w-10 h-10 rounded-xl {{ $item['iconBg'] }} flex items-center justify-center shrink-0">

                                <i
                                    data-lucide="{{ $item['icon'] }}"
                                    class="w-5 h-5 {{ $item['iconColor'] }}"
                                ></i>

                            </div>


                            <div class="min-w-0">

                                <p class="text-sm font-semibold text-gray-900">
                                    {{ $item['label'] }}
                                </p>

                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $item['description'] }}
                                </p>

                            </div>

                        </div>


                        <span class="min-w-9 h-9 px-3 rounded-full bg-gray-100 inline-flex items-center justify-center text-sm font-bold text-gray-700">

                            {{ $item['count'] }}

                        </span>

                    </div>

                @endforeach


                <a
                    href="{{ route('rider.deliveries') }}"
                    class="mt-3 w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                >

                    <i
                        data-lucide="package-search"
                        class="w-4 h-4"
                    ></i>

                    Manage Deliveries

                </a>

            </div>

        </div>


        {{-- =================================================
             EARNINGS SUMMARY
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-bold text-gray-900">
                        Earnings Summary
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Your earnings for today.
                    </p>

                </div>


                <div class="w-10 h-10 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="wallet"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>


            <div class="p-5 sm:p-6">

                <p class="text-sm text-gray-500">
                    Today's Earnings
                </p>

                <p class="text-4xl font-bold text-gray-900 mt-2">
                    ₱{{ number_format($todayEarnings, 2) }}
                </p>


                <div class="flex items-center gap-2 mt-3 text-sm text-[#1F6F5B]">

                    <i
                        data-lucide="trending-up"
                        class="w-4 h-4"
                    ></i>

                    <span>
                        {{ $totalCompleted }}
                        {{ $totalCompleted === 1 ? 'delivery' : 'deliveries' }}
                        completed
                    </span>

                </div>


                <div class="border-t border-gray-100 my-6"></div>


                <div class="space-y-5">

                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Completed Deliveries
                        </span>

                        <span class="text-sm font-bold text-gray-900">
                            {{ $totalCompleted }}
                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Rate per Delivery
                        </span>

                        <span class="text-sm font-bold text-gray-900">
                            ₱{{ number_format($deliveryFee, 2) }}
                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Active Deliveries
                        </span>

                        <span class="text-sm font-bold text-gray-900">
                            {{ $activeDeliveries }}
                        </span>

                    </div>

                </div>


                <div class="border-t border-gray-100 my-6"></div>


                <div class="flex items-center justify-between">

                    <span class="text-sm font-semibold text-gray-600">
                        Total
                    </span>

                    <span class="text-2xl font-bold text-[#1F6F5B]">
                        ₱{{ number_format($todayEarnings, 2) }}
                    </span>

                </div>


                <a
                    href="{{ route('rider.earnings') }}"
                    class="mt-6 w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-[#1F6F5B] text-[#1F6F5B] text-sm font-semibold hover:bg-[#EEF8F3] transition"
                >

                    <i
                        data-lucide="wallet-cards"
                        class="w-4 h-4"
                    ></i>

                    View Earnings

                </a>

            </div>

        </div>

    </div>


    {{-- =====================================================
         BOTTOM GRID
    ====================================================== --}}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">


        {{-- =================================================
             MY ACTIVE TASKS
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-bold text-gray-900">
                        My Active Tasks
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Your current delivery assignments.
                    </p>

                </div>


                <i
                    data-lucide="list-checks"
                    class="w-5 h-5 text-gray-400"
                ></i>

            </div>


            <div class="divide-y divide-gray-100">

                @if($recentOrders->count() > 0)

                    @foreach($recentOrders as $orderId => $order)

                        @php

                            $status = $order['status'] ?? 'assigned_to_rider';

                            $statusLabels = [

                                'assigned_to_rider' => 'New Delivery Assignment',

                                'picked_up' => 'Order Picked Up',

                                'at_sorting_center' => 'At Sorting Center',

                                'out_for_delivery' => 'Out for Delivery',

                            ];


                            $statusLabel =
                                $statusLabels[$status]
                                ?? 'Delivery Order';


                            $customerName =
                                $order['customer_name']
                                ?? $order['buyer_name']
                                ?? 'Customer';


                            $address =
                                $order['shipping_address']
                                ?? $order['address']
                                ?? 'Delivery address unavailable';

                        @endphp


                        <a
                            href="{{ route('rider.deliveries') }}"
                            class="p-5 flex items-center gap-4 hover:bg-gray-50 transition"
                        >

                            <div class="w-11 h-11 shrink-0 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="map-pin"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <div class="flex-1 min-w-0">

                                <p class="text-sm font-semibold text-gray-900 truncate">

                                    {{ $statusLabel }}

                                    <span class="text-gray-400">
                                        #{{ $orderId }}
                                    </span>

                                </p>


                                <p class="text-xs text-gray-500 mt-1 truncate">
                                    {{ $customerName }}
                                </p>


                                <p class="text-xs text-gray-400 mt-1 truncate">
                                    {{ $address }}
                                </p>

                            </div>


                            <i
                                data-lucide="chevron-right"
                                class="w-4 h-4 text-gray-400 shrink-0"
                            ></i>

                        </a>

                    @endforeach

                @else

                    <div class="px-5 py-12 text-center">

                        <div class="w-14 h-14 mx-auto rounded-2xl bg-gray-50 flex items-center justify-center mb-4">

                            <i
                                data-lucide="clipboard-list"
                                class="w-7 h-7 text-gray-400"
                            ></i>

                        </div>


                        <p class="text-sm font-semibold text-gray-900">
                            No active tasks yet
                        </p>


                        <p class="text-xs text-gray-500 mt-2 max-w-xs mx-auto">
                            Accept an available pickup request to start managing deliveries.
                        </p>


                        @if($availablePickupCount > 0)

                            <a
                                href="{{ route('rider.deliveries') }}"
                                class="inline-flex items-center gap-2 mt-5 text-sm font-semibold text-[#1F6F5B]"
                            >

                                View available pickups

                                <i
                                    data-lucide="arrow-right"
                                    class="w-4 h-4"
                                ></i>

                            </a>

                        @endif

                    </div>

                @endif

            </div>

        </div>


        {{-- =================================================
             NOTIFICATIONS
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-bold text-gray-900">
                        Notifications
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Important rider updates.
                    </p>

                </div>


                <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center">

                    <i
                        data-lucide="bell"
                        class="w-5 h-5 text-gray-500"
                    ></i>

                </div>

            </div>


            <div class="divide-y divide-gray-100">


                {{-- ACCOUNT STATUS --}}

                <div class="p-5 flex items-start gap-4">

                    <div class="w-10 h-10 shrink-0 rounded-full bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="circle-check"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>


                    <div>

                        <p class="text-sm font-semibold text-gray-900">
                            Your rider account is active
                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            You can accept pickup requests and manage your deliveries.
                        </p>

                    </div>

                </div>


                {{-- PICKUP NOTIFICATION --}}

                @if($availablePickupCount > 0)

                    <div class="p-5 flex items-start gap-4">

                        <div class="w-10 h-10 shrink-0 rounded-full bg-amber-50 flex items-center justify-center">

                            <i
                                data-lucide="package-check"
                                class="w-5 h-5 text-amber-600"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-semibold text-gray-900">
                                New pickup request available
                            </p>

                            <p class="text-xs text-gray-500 mt-1">

                                {{ $availablePickupCount }}

                                {{ $availablePickupCount === 1 ? 'order is' : 'orders are' }}

                                waiting to be accepted.

                            </p>

                        </div>

                    </div>

                @else

                    <div class="p-5 flex items-start gap-4">

                        <div class="w-10 h-10 shrink-0 rounded-full bg-blue-50 flex items-center justify-center">

                            <i
                                data-lucide="info"
                                class="w-5 h-5 text-blue-600"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-semibold text-gray-900">
                                No new pickup requests
                            </p>

                            <p class="text-xs text-gray-500 mt-1">
                                New requests will appear when sellers prepare orders.
                            </p>

                        </div>

                    </div>

                @endif


                {{-- ACTIVE DELIVERIES --}}

                <div class="p-5 flex items-start gap-4">

                    <div class="w-10 h-10 shrink-0 rounded-full bg-gray-50 flex items-center justify-center">

                        <i
                            data-lucide="truck"
                            class="w-5 h-5 text-gray-500"
                        ></i>

                    </div>


                    <div>

                        <p class="text-sm font-semibold text-gray-900">

                            {{ $activeDeliveries }}

                            active

                            {{ $activeDeliveries === 1 ? 'delivery' : 'deliveries' }}

                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            Keep your delivery status updated as you complete each task.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection