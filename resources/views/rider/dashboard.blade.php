@extends('layouts.rider')

@section('content')

@php
    $orders = session('orders', []);

    $readyForPickup = collect($orders)
        ->where('status', 'ready_for_pickup')
        ->count();

    $pickedUp = collect($orders)
        ->where('status', 'picked_up')
        ->count();

    $atSortingCenter = collect($orders)
        ->where('status', 'at_sorting_center')
        ->count();

    $outForDelivery = collect($orders)
        ->where('status', 'out_for_delivery')
        ->count();

    $delivered = collect($orders)
        ->where('status', 'delivered')
        ->count();

    $assigned = collect($orders)
        ->where('status', 'assigned_to_rider')
        ->count();

    $activeDeliveries = $assigned + $outForDelivery;

    $totalCompleted = $delivered;

    $deliveryFee = 50;

    $todayEarnings = $totalCompleted * $deliveryFee;

    $riderName = trim(
        ($application['first_name'] ?? '') . ' ' .
        ($application['last_name'] ?? '')
    );

    if ($riderName === '') {
        $riderName = 'Rider';
    }

    $recentOrders = collect($orders)
        ->filter(function ($order) {
            return in_array(
                $order['status'] ?? '',
                [
                    'ready_for_pickup',
                    'picked_up',
                    'at_sorting_center',
                    'assigned_to_rider',
                    'out_for_delivery',
                    'delivered',
                ]
            );
        })
        ->reverse()
        ->take(4);
@endphp


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- =====================================================
         WELCOME HEADER
    ====================================================== --}}

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5 mb-8">

        <div>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                Good morning, {{ $riderName }}
            </h1>

            <p class="text-gray-500 mt-1">
                Manage your deliveries and earnings from here.
            </p>

        </div>


        {{-- ONLINE STATUS --}}

        <div class="inline-flex items-center gap-2 self-start lg:self-auto px-4 py-2.5 rounded-lg bg-[#EEF8F3] text-[#1F6F5B]">

            <span class="w-2 h-2 rounded-full bg-green-500"></span>

            <span class="text-sm font-semibold">
                Online
            </span>

        </div>

    </div>


    {{-- =====================================================
         SUMMARY CARDS
    ====================================================== --}}

    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-8">


        {{-- TODAY'S DELIVERIES --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Today's Deliveries
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        {{ $activeDeliveries }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Active deliveries
                    </p>

                </div>

                <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="package"
                        class="w-5 h-5 text-[#1F6F5B]"
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
                        {{ $totalCompleted }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Delivered orders
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


        {{-- EARNINGS --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Earnings Today
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        ₱{{ number_format($todayEarnings, 2) }}
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        From completed deliveries
                    </p>

                </div>

                <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="wallet"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- RATING --}}

        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Rating
                    </p>

                    <p class="text-3xl font-bold text-gray-900 mt-2">
                        4.9
                    </p>

                    <p class="text-xs text-[#1F6F5B] mt-2">
                        Excellent performance
                    </p>

                </div>

                <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="star"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         MAIN DASHBOARD GRID
    ====================================================== --}}

    <div class="grid grid-cols-1 xl:grid-cols-[1.5fr_1fr] gap-5 mb-5">


        {{-- =================================================
             DELIVERY OVERVIEW
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-semibold text-gray-900">
                        Delivery Overview
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Current status of your delivery assignments.
                    </p>

                </div>

                <a
                    href="{{ route('rider.deliveries') }}"
                    class="text-sm font-semibold text-[#1F6F5B] hover:text-[#155244]"
                >
                    View All
                </a>

            </div>


            <div class="p-5 sm:p-6 space-y-5">


                {{-- READY FOR PICKUP --}}

                <div>

                    <div class="flex items-center justify-between gap-3 mb-2">

                        <div class="flex items-center gap-3">

                            <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="package-check"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-900">
                                    Ready for Pickup
                                </p>

                                <p class="text-xs text-gray-400">
                                    Waiting for pickup
                                </p>

                            </div>

                        </div>

                        <span class="text-sm font-semibold text-gray-700">
                            {{ $readyForPickup }}
                        </span>

                    </div>

                    <div class="ml-13 h-2 rounded-full bg-gray-100 overflow-hidden">

                        <div
                            class="h-full bg-[#F59E0B] rounded-full"
                            style="width: {{ $readyForPickup > 0 ? '45%' : '0%' }}"
                        ></div>

                    </div>

                </div>


                {{-- PICKED UP --}}

                <div>

                    <div class="flex items-center justify-between gap-3 mb-2">

                        <div class="flex items-center gap-3">

                            <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="package"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-900">
                                    Picked Up
                                </p>

                                <p class="text-xs text-gray-400">
                                    In transit to sorting center
                                </p>

                            </div>

                        </div>

                        <span class="text-sm font-semibold text-gray-700">
                            {{ $pickedUp }}
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">

                        <div
                            class="h-full bg-[#1F6F5B] rounded-full"
                            style="width: {{ $pickedUp > 0 ? '55%' : '0%' }}"
                        ></div>

                    </div>

                </div>


                {{-- SORTING CENTER --}}

                <div>

                    <div class="flex items-center justify-between gap-3 mb-2">

                        <div class="flex items-center gap-3">

                            <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="warehouse"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-900">
                                    At Sorting Center
                                </p>

                                <p class="text-xs text-gray-400">
                                    Being processed
                                </p>

                            </div>

                        </div>

                        <span class="text-sm font-semibold text-gray-700">
                            {{ $atSortingCenter }}
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">

                        <div
                            class="h-full bg-[#2563EB] rounded-full"
                            style="width: {{ $atSortingCenter > 0 ? '65%' : '0%' }}"
                        ></div>

                    </div>

                </div>


                {{-- OUT FOR DELIVERY --}}

                <div>

                    <div class="flex items-center justify-between gap-3 mb-2">

                        <div class="flex items-center gap-3">

                            <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="truck"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-900">
                                    Out for Delivery
                                </p>

                                <p class="text-xs text-gray-400">
                                    Currently delivering
                                </p>

                            </div>

                        </div>

                        <span class="text-sm font-semibold text-gray-700">
                            {{ $outForDelivery }}
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">

                        <div
                            class="h-full bg-[#1F6F5B] rounded-full"
                            style="width: {{ $outForDelivery > 0 ? '75%' : '0%' }}"
                        ></div>

                    </div>

                </div>


                {{-- DELIVERED --}}

                <div>

                    <div class="flex items-center justify-between gap-3 mb-2">

                        <div class="flex items-center gap-3">

                            <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="circle-check"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <div>

                                <p class="text-sm font-medium text-gray-900">
                                    Delivered
                                </p>

                                <p class="text-xs text-gray-400">
                                    Successfully completed
                                </p>

                            </div>

                        </div>

                        <span class="text-sm font-semibold text-gray-700">
                            {{ $delivered }}
                        </span>

                    </div>

                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">

                        <div
                            class="h-full bg-[#1F6F5B] rounded-full"
                            style="width: {{ $delivered > 0 ? '100%' : '0%' }}"
                        ></div>

                    </div>

                </div>


                <a
                    href="{{ route('rider.deliveries') }}"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                >

                    <i
                        data-lucide="package-search"
                        class="w-4 h-4"
                    ></i>

                    Go to Deliveries

                </a>

            </div>

        </div>


        {{-- =================================================
             EARNINGS SUMMARY
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-semibold text-gray-900">
                        Earnings Summary
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Your delivery earnings.
                    </p>

                </div>

                <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                    <i
                        data-lucide="wallet"
                        class="w-5 h-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>


            <div class="p-5 sm:p-6">


                <p class="text-sm text-gray-500">
                    Today
                </p>

                <p class="text-3xl font-bold text-gray-900 mt-1">
                    ₱{{ number_format($todayEarnings, 2) }}
                </p>


                <div class="flex items-center gap-2 mt-2 text-sm text-[#1F6F5B]">

                    <i
                        data-lucide="trending-up"
                        class="w-4 h-4"
                    ></i>

                    <span>
                        {{ $totalCompleted }} completed deliveries
                    </span>

                </div>


                <div class="border-t border-gray-100 my-6"></div>


                <div class="space-y-4">


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Base Earnings
                        </span>

                        <span class="text-sm font-semibold text-gray-900">
                            ₱{{ number_format($todayEarnings, 2) }}
                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Completed Deliveries
                        </span>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $totalCompleted }}
                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-500">
                            Rate per Delivery
                        </span>

                        <span class="text-sm font-semibold text-gray-900">
                            ₱{{ number_format($deliveryFee, 2) }}
                        </span>

                    </div>

                </div>


                <div class="border-t border-gray-100 my-6"></div>


                <div class="flex items-center justify-between">

                    <span class="text-sm font-medium text-gray-600">
                        Total Earnings
                    </span>

                    <span class="text-2xl font-bold text-[#1F6F5B]">
                        ₱{{ number_format($todayEarnings, 2) }}
                    </span>

                </div>


                <a
                    href="{{ route('rider.earnings') }}"
                    class="mt-5 w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg border border-[#1F6F5B] text-[#1F6F5B] text-sm font-semibold hover:bg-[#EEF8F3] transition"
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
             UPCOMING TASKS
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-semibold text-gray-900">
                        Upcoming Tasks
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Your next delivery assignments.
                    </p>

                </div>

                <i
                    data-lucide="list-checks"
                    class="w-5 h-5 text-gray-400"
                ></i>

            </div>


            <div class="divide-y divide-gray-100">

                @if($recentOrders->count() > 0)

                    @foreach($recentOrders->take(3) as $orderId => $order)

                        @php
                            $status = $order['status'] ?? 'placed';

                            $statusLabels = [
                                'ready_for_pickup' => 'Pickup Order',
                                'picked_up' => 'In Transit',
                                'at_sorting_center' => 'Sorting Center',
                                'assigned_to_rider' => 'Delivery Assignment',
                                'out_for_delivery' => 'Deliver Order',
                                'delivered' => 'Completed Order',
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


                        <div class="p-5 flex items-center gap-4">

                            <div class="w-10 h-10 shrink-0 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                                <i
                                    data-lucide="{{ $status === 'ready_for_pickup' ? 'package-check' : 'map-pin' }}"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <div class="flex-1 min-w-0">

                                <p class="text-sm font-semibold text-gray-900 truncate">
                                    {{ $statusLabel }} #{{ $orderId }}
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

                        </div>

                    @endforeach

                @else

                    <div class="px-5 py-12 text-center">

                        <div class="w-12 h-12 mx-auto rounded-xl bg-gray-50 flex items-center justify-center mb-3">

                            <i
                                data-lucide="clipboard-list"
                                class="w-6 h-6 text-gray-400"
                            ></i>

                        </div>

                        <p class="text-sm font-medium text-gray-900">
                            No upcoming tasks
                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            New delivery assignments will appear here.
                        </p>

                    </div>

                @endif

            </div>

        </div>


        {{-- =================================================
             NOTIFICATIONS
        ================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

            <div class="flex items-center justify-between px-5 sm:px-6 py-5 border-b border-gray-100">

                <div>

                    <h2 class="text-lg font-semibold text-gray-900">
                        Notifications
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Important delivery updates.
                    </p>

                </div>

                <span class="text-sm font-semibold text-[#1F6F5B]">
                    View all
                </span>

            </div>


            <div class="divide-y divide-gray-100">


                {{-- NOTIFICATION 1 --}}

                <div class="p-5 flex items-start gap-4">

                    <div class="w-10 h-10 shrink-0 rounded-full bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="check"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-medium text-gray-900">
                            Your rider account is active
                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            You can now manage your delivery assignments.
                        </p>

                        <p class="text-xs text-gray-400 mt-2">
                            Recently
                        </p>

                    </div>

                </div>


                {{-- NOTIFICATION 2 --}}

                @if($readyForPickup > 0)

                    <div class="p-5 flex items-start gap-4">

                        <div class="w-10 h-10 shrink-0 rounded-full bg-amber-50 flex items-center justify-center">

                            <i
                                data-lucide="package-check"
                                class="w-5 h-5 text-amber-600"
                            ></i>

                        </div>

                        <div>

                            <p class="text-sm font-medium text-gray-900">
                                New pickup request available
                            </p>

                            <p class="text-xs text-gray-500 mt-1">
                                {{ $readyForPickup }} order(s) are ready for pickup.
                            </p>

                            <p class="text-xs text-gray-400 mt-2">
                                Check your deliveries
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

                            <p class="text-sm font-medium text-gray-900">
                                No new pickup requests
                            </p>

                            <p class="text-xs text-gray-500 mt-1">
                                New requests will appear when sellers prepare orders.
                            </p>

                            <p class="text-xs text-gray-400 mt-2">
                                Check back later
                            </p>

                        </div>

                    </div>

                @endif


                {{-- NOTIFICATION 3 --}}

                <div class="p-5 flex items-start gap-4">

                    <div class="w-10 h-10 shrink-0 rounded-full bg-gray-50 flex items-center justify-center">

                        <i
                            data-lucide="shield-check"
                            class="w-5 h-5 text-gray-500"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-medium text-gray-900">
                            Keep your rider account active
                        </p>

                        <p class="text-xs text-gray-500 mt-1">
                            Complete assigned deliveries and maintain good service.
                        </p>

                        <p class="text-xs text-gray-400 mt-2">
                            Rider guidelines
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection