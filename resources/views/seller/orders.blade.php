@extends('layouts.seller')

@section('title', 'Orders')
@section('page-title', 'Orders')

@section('content')

@php

    $allOrders = array_values($orders ?? []);

    /*
    |--------------------------------------------------------------------------
    | STATUS CONFIGURATION
    |--------------------------------------------------------------------------
    */

    $statusLabels = [
        'placed' => 'To Confirm',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready_for_pickup' => 'Ready for Pickup',
        'picked_up' => 'Picked Up',
        'at_sorting_center' => 'At Sorting Center',
        'sorted' => 'Sorted',
        'assigned_to_rider' => 'Assigned to Rider',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'delivery_failed' => 'Delivery Failed',
        'returned' => 'Returned',
        'cancelled' => 'Cancelled',
    ];

    $statusIcons = [
        'placed' => 'clock-3',
        'confirmed' => 'circle-check',
        'preparing' => 'package-open',
        'ready_for_pickup' => 'package-check',
        'picked_up' => 'truck',
        'at_sorting_center' => 'warehouse',
        'sorted' => 'boxes',
        'assigned_to_rider' => 'user-check',
        'out_for_delivery' => 'bike',
        'delivered' => 'map-pin-check',
        'completed' => 'badge-check',
        'delivery_failed' => 'triangle-alert',
        'returned' => 'rotate-ccw',
        'cancelled' => 'circle-x',
    ];

    $statusClasses = [
        'placed' =>
            'border-amber-200 bg-amber-50 text-amber-700',

        'confirmed' =>
            'border-blue-200 bg-blue-50 text-blue-700',

        'preparing' =>
            'border-violet-200 bg-violet-50 text-violet-700',

        'ready_for_pickup' =>
            'border-indigo-200 bg-indigo-50 text-indigo-700',

        'picked_up' =>
            'border-cyan-200 bg-cyan-50 text-cyan-700',

        'at_sorting_center' =>
            'border-sky-200 bg-sky-50 text-sky-700',

        'sorted' =>
            'border-teal-200 bg-teal-50 text-teal-700',

        'assigned_to_rider' =>
            'border-violet-200 bg-violet-50 text-violet-700',

        'out_for_delivery' =>
            'border-orange-200 bg-orange-50 text-orange-700',

        'delivered' =>
            'border-emerald-200 bg-emerald-50 text-emerald-700',

        'completed' =>
            'border-emerald-200 bg-emerald-50 text-emerald-700',

        'delivery_failed' =>
            'border-red-200 bg-red-50 text-red-700',

        'returned' =>
            'border-rose-200 bg-rose-50 text-rose-700',

        'cancelled' =>
            'border-gray-200 bg-gray-100 text-gray-600',
    ];


    /*
    |--------------------------------------------------------------------------
    | COUNTS
    |--------------------------------------------------------------------------
    */

    $totalOrders = count($allOrders);

    $toConfirmOrders = collect($allOrders)
        ->where('status', 'placed')
        ->count();

    $preparingOrders = collect($allOrders)
        ->whereIn(
            'status',
            ['confirmed', 'preparing']
        )
        ->count();

    $readyOrders = collect($allOrders)
        ->where('status', 'ready_for_pickup')
        ->count();

    $completedOrders = collect($allOrders)
        ->whereIn(
            'status',
            ['delivered', 'completed']
        )
        ->count();


    /*
    |--------------------------------------------------------------------------
    | REVENUE
    |--------------------------------------------------------------------------
    */

    $completedRevenue = collect($allOrders)
        ->whereIn(
            'status',
            ['delivered', 'completed']
        )
        ->sum(function ($order) {

            return (float) (
                $order['total'] ?? 0
            );

        });

@endphp


{{-- =========================================================
    ALERTS
========================================================= --}}

@if(session('success'))

    <div
        class="mb-6
               flex items-start gap-3
               rounded-2xl
               border border-emerald-200
               bg-emerald-50
               px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-white"
        >

            <i
                data-lucide="check"
                class="h-4 w-4 text-emerald-600"
            ></i>

        </div>


        <div>

            <p class="text-xs font-semibold text-emerald-800">
                Order updated
            </p>

            <p
                class="mt-0.5
                       text-xs
                       leading-5
                       text-emerald-700"
            >
                {{ session('success') }}
            </p>

        </div>

    </div>

@endif


@if(session('error'))

    <div
        class="mb-6
               flex items-start gap-3
               rounded-2xl
               border border-red-200
               bg-red-50
               px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-white"
        >

            <i
                data-lucide="triangle-alert"
                class="h-4 w-4 text-red-600"
            ></i>

        </div>


        <div>

            <p class="text-xs font-semibold text-red-800">
                Unable to update order
            </p>

            <p
                class="mt-0.5
                       text-xs
                       leading-5
                       text-red-700"
            >
                {{ session('error') }}
            </p>

        </div>

    </div>

@endif


{{-- =========================================================
    INTRODUCTION
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        <div
            class="mb-2
                   flex items-center gap-2
                   text-[11px]
                   font-medium
                   text-[#8A9791]"
        >

            <a
                href="{{ route('seller.dashboard') }}"
                class="transition hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>

            <span class="text-[#52635B]">
                Orders
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Order Management
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Review customer orders and prepare them
            for SUKI Logistics fulfillment.
        </p>

    </div>


    <a
        href="{{ route('buyer.home') }}"
        target="_blank"
        class="inline-flex h-10
               items-center justify-center gap-2
               self-start
               rounded-xl
               border border-[#DDE6E1]
               bg-white
               px-4
               text-xs
               font-semibold
               text-[#52635B]
               transition
               hover:border-[#BFD2C9]
               hover:bg-[#F5F8F6]
               hover:text-[#173F35]
               sm:self-auto"
    >

        <i
            data-lucide="external-link"
            class="h-4 w-4"
        ></i>

        View Marketplace

    </a>

</div>


{{-- =========================================================
    SUMMARY
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-5"
>


    {{-- TOTAL --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Total Orders
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalOrders }}
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#173F35]"
            >

                <i
                    data-lucide="shopping-bag"
                    class="h-4 w-4"
                ></i>

            </div>

        </div>

    </div>


    {{-- TO CONFIRM --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    To Confirm
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $toConfirmOrders }}
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-amber-50
                       text-amber-700"
            >

                <i
                    data-lucide="clock-3"
                    class="h-4 w-4"
                ></i>

            </div>

        </div>

    </div>


    {{-- PREPARING --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Processing
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $preparingOrders }}
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-violet-50
                       text-violet-700"
            >

                <i
                    data-lucide="package-open"
                    class="h-4 w-4"
                ></i>

            </div>

        </div>

    </div>


    {{-- READY --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Ready
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $readyOrders }}
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-indigo-50
                       text-indigo-700"
            >

                <i
                    data-lucide="package-check"
                    class="h-4 w-4"
                ></i>

            </div>

        </div>

    </div>


    {{-- SALES --}}
    <div
        class="col-span-2
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5
               xl:col-span-1"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Completed Sales
                </p>

                <p
                    class="mt-3
                           text-xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    ₱{{ number_format($completedRevenue, 2) }}
                </p>

                <p
                    class="mt-1
                           text-[10px]
                           text-[#87958E]"
                >
                    {{ $completedOrders }} orders
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-emerald-50
                       text-emerald-700"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-4 w-4"
                ></i>

            </div>

        </div>

    </div>

</div>


{{-- =========================================================
    ORDERS
========================================================= --}}

<section
    class="overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>


    {{-- =====================================================
        HEADER
    ====================================================== --}}

    <div
        class="flex flex-col gap-4
               border-b border-[#EDF1EF]
               px-5 py-5
               lg:flex-row
               lg:items-center
               lg:justify-between"
    >

        <div>

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Customer Orders
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Process orders before handing them
                over to SUKI Logistics.
            </p>

        </div>


        <div
            class="flex items-center gap-2
                   text-[10px]
                   font-medium
                   text-[#849089]"
        >

            <span
                class="h-2 w-2
                       rounded-full
                       bg-[#1F6F5B]"
            ></span>

            {{ $totalOrders }} order(s)

        </div>

    </div>


    {{-- =====================================================
        FILTERS
    ====================================================== --}}

    <div
        class="border-b border-[#EDF1EF]
               bg-[#FBFCFB]
               p-4"
    >

        <div
            class="grid gap-3
                   lg:grid-cols-[minmax(0,1fr)_230px_auto]"
        >


            {{-- SEARCH --}}
            <div class="relative">

                <i
                    data-lucide="search"
                    class="pointer-events-none
                           absolute left-3.5 top-1/2
                           h-4 w-4
                           -translate-y-1/2
                           text-[#91A099]"
                ></i>


                <input
                    type="text"
                    id="orderSearch"
                    placeholder="Search order, customer, or product"
                    class="h-10 w-full
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           pl-10 pr-4
                           text-xs
                           text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

            </div>


            {{-- FILTER --}}
            <div class="relative">

                <select
                    id="statusFilter"
                    class="h-10 w-full
                           appearance-none
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-3 pr-9
                           text-xs
                           font-medium
                           text-[#52635B]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

                    <option value="all">
                        All statuses
                    </option>

                    @foreach($statusLabels as $status => $label)

                        <option value="{{ $status }}">
                            {{ $label }}
                        </option>

                    @endforeach

                </select>


                <i
                    data-lucide="chevron-down"
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#87958E]"
                ></i>

            </div>


            {{-- RESET --}}
            <button
                type="button"
                id="clearOrderFilters"
                class="inline-flex h-10
                       items-center justify-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-3.5
                       text-[11px]
                       font-semibold
                       text-[#68776F]
                       transition
                       hover:bg-[#F3F7F5]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="rotate-ccw"
                    class="h-3.5 w-3.5"
                ></i>

                Clear

            </button>

        </div>


        {{-- QUICK TABS --}}
        <div
            class="mt-4
                   overflow-x-auto"
        >

            <div
                class="flex min-w-max
                       items-center gap-2"
            >

                <button
                    type="button"
                    data-status-tab="all"
                    class="order-tab
                           rounded-full
                           bg-[#173F35]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-white
                           transition"
                >
                    All
                </button>


                <button
                    type="button"
                    data-status-tab="placed"
                    class="order-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    To Confirm
                    <span class="ml-1">
                        {{ $toConfirmOrders }}
                    </span>
                </button>


                <button
                    type="button"
                    data-status-tab="preparing"
                    class="order-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Preparing
                </button>


                <button
                    type="button"
                    data-status-tab="ready_for_pickup"
                    class="order-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Ready
                </button>


                <button
                    type="button"
                    data-status-tab="completed"
                    class="order-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Completed
                </button>

            </div>

        </div>

    </div>


    @if(empty($orders))


        {{-- =================================================
            EMPTY STATE
        ================================================== --}}

        <div
            class="px-6 py-16
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-14 w-14
                       items-center justify-center
                       rounded-2xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="shopping-bag"
                    class="h-6 w-6"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No orders yet
            </h3>


            <p
                class="mx-auto mt-1
                       max-w-sm
                       text-xs
                       leading-5
                       text-[#849089]"
            >
                Customer orders will appear here once
                purchases are placed through your store.
            </p>

        </div>


    @else


        {{-- =================================================
            DESKTOP TABLE
        ================================================== --}}

        <div class="hidden overflow-x-auto lg:block">

            <table class="w-full">

                <thead>

                    <tr
                        class="border-b
                               border-[#EDF1EF]
                               bg-[#F7F9F8]"
                    >

                        <th class="px-5 py-3 text-left">
                            Order
                        </th>

                        <th class="px-5 py-3 text-left">
                            Customer & Items
                        </th>

                        <th class="px-5 py-3 text-left">
                            Fulfillment
                        </th>

                        <th class="px-5 py-3 text-left">
                            Total
                        </th>

                        <th class="px-5 py-3 text-left">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($orders as $order)

                        @php

                            $status =
                                strtolower(
                                    $order['status'] ?? 'placed'
                                );

                            $orderId =
                                $order['id'] ?? 'N/A';

                            $recipient =
                                $order['shipping_address']['name']
                                ?? 'Customer';

                            $phone =
                                $order['shipping_address']['phone']
                                ?? '';

                            $statusLabel =
                                $statusLabels[$status]
                                ?? ucfirst(
                                    str_replace('_', ' ', $status)
                                );

                            $statusIcon =
                                $statusIcons[$status]
                                ?? 'clock-3';

                            $statusClass =
                                $statusClasses[$status]
                                ?? 'border-gray-200 bg-gray-100 text-gray-600';

                            $itemNames =
                                collect($order['items'] ?? [])
                                ->pluck('name')
                                ->implode(' ');

                            $searchValue =
                                strtolower(
                                    $orderId
                                    . ' '
                                    . $recipient
                                    . ' '
                                    . $phone
                                    . ' '
                                    . $itemNames
                                );

                        @endphp


                        <tr
                            class="order-row
                                   border-b border-[#F0F3F1]
                                   transition
                                   last:border-b-0
                                   hover:bg-[#FAFCFB]"
                            data-search="{{ $searchValue }}"
                            data-status="{{ $status }}"
                        >


                            {{-- ORDER --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#24312C]"
                                >
                                    #{{ $orderId }}
                                </p>


                                <p
                                    class="mt-1
                                           whitespace-nowrap
                                           text-[10px]
                                           text-[#8A9791]"
                                >

                                    @if(!empty($order['created_at']))

                                        {{ \Carbon\Carbon::parse(
                                            $order['created_at']
                                        )->format('M d, Y · h:i A') }}

                                    @else

                                        —

                                    @endif

                                </p>

                            </td>


                            {{-- CUSTOMER / ITEMS --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <div class="mb-3">

                                    <p
                                        class="text-xs
                                               font-semibold
                                               text-[#34483F]"
                                    >
                                        {{ $recipient }}
                                    </p>


                                    @if($phone)

                                        <p
                                            class="mt-0.5
                                                   text-[10px]
                                                   text-[#8A9791]"
                                        >
                                            {{ $phone }}
                                        </p>

                                    @endif

                                </div>


                                <div class="space-y-2">

                                    @foreach(
                                        collect($order['items'] ?? [])
                                            ->take(2)
                                        as $item
                                    )

                                        <div
                                            class="flex
                                                   items-center gap-2.5"
                                        >

                                            <div
                                                class="flex h-9 w-9
                                                       shrink-0
                                                       items-center justify-center
                                                       overflow-hidden
                                                       rounded-lg
                                                       border border-[#E4EAE6]
                                                       bg-[#F1F4F2]"
                                            >

                                                @if(!empty($item['image']))

                                                    <img
                                                        src="{{ $item['image'] }}"
                                                        alt="{{ $item['name'] ?? 'Product' }}"
                                                        class="h-full w-full object-cover"
                                                    >

                                                @else

                                                    <i
                                                        data-lucide="package"
                                                        class="h-4 w-4 text-[#89968F]"
                                                    ></i>

                                                @endif

                                            </div>


                                            <div class="min-w-0">

                                                <p
                                                    class="max-w-[210px]
                                                           truncate
                                                           text-[11px]
                                                           font-medium
                                                           text-[#52635B]"
                                                >
                                                    {{ $item['name'] ?? 'Product' }}
                                                </p>

                                                <p
                                                    class="mt-0.5
                                                           text-[9px]
                                                           text-[#97A29D]"
                                                >
                                                    Qty:
                                                    {{ $item['quantity'] ?? 1 }}
                                                </p>

                                            </div>

                                        </div>

                                    @endforeach


                                    @if(
                                        count($order['items'] ?? []) > 2
                                    )

                                        <p
                                            class="text-[9px]
                                                   font-medium
                                                   text-[#1F6F5B]"
                                        >
                                            +
                                            {{ count($order['items']) - 2 }}
                                            more item(s)
                                        </p>

                                    @endif

                                </div>

                            </td>


                            {{-- FULFILLMENT --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <div
                                    class="flex
                                           items-center gap-2"
                                >

                                    <i
                                        data-lucide="truck"
                                        class="h-4 w-4
                                               text-[#1F6F5B]"
                                    ></i>

                                    <span
                                        class="text-[11px]
                                               font-medium
                                               text-[#52635B]"
                                    >
                                        SUKI Logistics
                                    </span>

                                </div>


                                <div
                                    class="mt-2
                                           flex items-center gap-2"
                                >

                                    <i
                                        data-lucide="credit-card"
                                        class="h-3.5 w-3.5
                                               text-[#8A9791]"
                                    ></i>

                                    <span
                                        class="text-[10px]
                                               text-[#7C8983]"
                                    >

                                        {{ ($order['payment_method'] ?? '') === 'gcash'
                                            ? 'GCash'
                                            : 'Cash on Delivery' }}

                                    </span>

                                </div>

                            </td>


                            {{-- TOTAL --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <p
                                    class="text-sm
                                           font-semibold
                                           tracking-[-0.02em]
                                           text-[#24312C]"
                                >
                                    ₱{{ number_format(
                                        (float) ($order['total'] ?? 0),
                                        2
                                    ) }}
                                </p>

                            </td>


                            {{-- STATUS --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <span
                                    class="
                                        inline-flex
                                        items-center gap-1.5
                                        rounded-full
                                        border
                                        px-2.5 py-1
                                        text-[10px]
                                        font-semibold
                                        {{ $statusClass }}
                                    "
                                >

                                    <i
                                        data-lucide="{{ $statusIcon }}"
                                        class="h-3.5 w-3.5"
                                    ></i>

                                    {{ $statusLabel }}

                                </span>

                            </td>


                            {{-- ACTION --}}
                            <td
                                class="px-5 py-5
                                       align-top"
                            >

                                <div
                                    class="flex
                                           justify-end"
                                >

                                    @if($status === 'placed')

                                        <form
                                            action="{{ route(
                                                'order.status.update',
                                                $orderId
                                            ) }}"
                                            method="POST"
                                        >

                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="confirmed"
                                            >


                                            <button
                                                type="submit"
                                                class="inline-flex h-9
                                                       items-center gap-2
                                                       rounded-xl
                                                       bg-[#173F35]
                                                       px-3.5
                                                       text-[10px]
                                                       font-semibold
                                                       text-white
                                                       transition
                                                       hover:bg-[#1F6F5B]"
                                            >

                                                <i
                                                    data-lucide="check"
                                                    class="h-3.5 w-3.5"
                                                ></i>

                                                Confirm

                                            </button>

                                        </form>


                                    @elseif($status === 'confirmed')

                                        <form
                                            action="{{ route(
                                                'order.status.update',
                                                $orderId
                                            ) }}"
                                            method="POST"
                                        >

                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="preparing"
                                            >


                                            <button
                                                type="submit"
                                                class="inline-flex h-9
                                                       items-center gap-2
                                                       rounded-xl
                                                       bg-[#173F35]
                                                       px-3.5
                                                       text-[10px]
                                                       font-semibold
                                                       text-white
                                                       transition
                                                       hover:bg-[#1F6F5B]"
                                            >

                                                <i
                                                    data-lucide="package-open"
                                                    class="h-3.5 w-3.5"
                                                ></i>

                                                Prepare

                                            </button>

                                        </form>


                                    @elseif($status === 'preparing')

                                        <form
                                            action="{{ route(
                                                'order.status.update',
                                                $orderId
                                            ) }}"
                                            method="POST"
                                        >

                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="ready_for_pickup"
                                            >


                                            <button
                                                type="submit"
                                                class="inline-flex h-9
                                                       items-center gap-2
                                                       rounded-xl
                                                       bg-[#173F35]
                                                       px-3.5
                                                       text-[10px]
                                                       font-semibold
                                                       text-white
                                                       transition
                                                       hover:bg-[#1F6F5B]"
                                            >

                                                <i
                                                    data-lucide="package-check"
                                                    class="h-3.5 w-3.5"
                                                ></i>

                                                Mark Ready

                                            </button>

                                        </form>


                                    @elseif($status === 'ready_for_pickup')

                                        <span
                                            class="inline-flex
                                                   items-center gap-2
                                                   text-[10px]
                                                   font-medium
                                                   text-indigo-600"
                                        >

                                            <i
                                                data-lucide="clock"
                                                class="h-3.5 w-3.5"
                                            ></i>

                                            Awaiting pickup

                                        </span>


                                    @elseif(in_array(
                                        $status,
                                        [
                                            'picked_up',
                                            'at_sorting_center',
                                            'sorted',
                                            'assigned_to_rider',
                                            'out_for_delivery'
                                        ]
                                    ))

                                        <span
                                            class="inline-flex
                                                   items-center gap-2
                                                   text-[10px]
                                                   font-medium
                                                   text-[#7B8982]"
                                        >

                                            <i
                                                data-lucide="truck"
                                                class="h-3.5 w-3.5"
                                            ></i>

                                            In fulfillment

                                        </span>


                                    @elseif($status === 'delivered')

                                        <span
                                            class="text-[10px]
                                                   font-semibold
                                                   text-emerald-700"
                                        >
                                            Delivered
                                        </span>


                                    @elseif($status === 'completed')

                                        <span
                                            class="text-[10px]
                                                   font-semibold
                                                   text-emerald-700"
                                        >
                                            Completed
                                        </span>


                                    @elseif($status === 'delivery_failed')

                                        <span
                                            class="text-[10px]
                                                   font-semibold
                                                   text-red-600"
                                        >
                                            Delivery failed
                                        </span>


                                    @elseif($status === 'returned')

                                        <span
                                            class="text-[10px]
                                                   font-semibold
                                                   text-rose-600"
                                        >
                                            Returned
                                        </span>


                                    @elseif($status === 'cancelled')

                                        <span
                                            class="text-[10px]
                                                   font-semibold
                                                   text-[#87958E]"
                                        >
                                            Cancelled
                                        </span>

                                    @endif

                                </div>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{-- =================================================
            MOBILE / TABLET CARDS
        ================================================== --}}

        <div
            class="divide-y
                   divide-[#EDF1EF]
                   lg:hidden"
        >

            @foreach($orders as $order)

                @php

                    $status =
                        strtolower(
                            $order['status'] ?? 'placed'
                        );

                    $orderId =
                        $order['id'] ?? 'N/A';

                    $recipient =
                        $order['shipping_address']['name']
                        ?? 'Customer';

                    $phone =
                        $order['shipping_address']['phone']
                        ?? '';

                    $statusLabel =
                        $statusLabels[$status]
                        ?? ucfirst(
                            str_replace('_', ' ', $status)
                        );

                    $statusClass =
                        $statusClasses[$status]
                        ?? 'border-gray-200 bg-gray-100 text-gray-600';

                    $statusIcon =
                        $statusIcons[$status]
                        ?? 'clock-3';

                    $itemNames =
                        collect($order['items'] ?? [])
                        ->pluck('name')
                        ->implode(' ');

                    $searchValue =
                        strtolower(
                            $orderId
                            . ' '
                            . $recipient
                            . ' '
                            . $phone
                            . ' '
                            . $itemNames
                        );

                @endphp


                <article
                    class="order-card
                           p-4 sm:p-5"
                    data-search="{{ $searchValue }}"
                    data-status="{{ $status }}"
                >


                    {{-- TOP --}}
                    <div
                        class="flex
                               items-start
                               justify-between gap-4"
                    >

                        <div>

                            <p
                                class="text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                #{{ $orderId }}
                            </p>


                            <p
                                class="mt-1
                                       text-[10px]
                                       text-[#8A9791]"
                            >

                                @if(!empty($order['created_at']))

                                    {{ \Carbon\Carbon::parse(
                                        $order['created_at']
                                    )->format('M d, Y · h:i A') }}

                                @else

                                    —

                                @endif

                            </p>

                        </div>


                        <span
                            class="
                                inline-flex
                                shrink-0
                                items-center gap-1.5
                                rounded-full
                                border
                                px-2.5 py-1
                                text-[9px]
                                font-semibold
                                {{ $statusClass }}
                            "
                        >

                            <i
                                data-lucide="{{ $statusIcon }}"
                                class="h-3 w-3"
                            ></i>

                            {{ $statusLabel }}

                        </span>

                    </div>


                    {{-- CUSTOMER --}}
                    <div
                        class="mt-4
                               rounded-xl
                               bg-[#F7F9F8]
                               p-3"
                    >

                        <div
                            class="flex
                                   items-center gap-3"
                        >

                            <div
                                class="flex h-9 w-9
                                       shrink-0
                                       items-center justify-center
                                       rounded-xl
                                       bg-white
                                       text-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="user-round"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <div class="min-w-0">

                                <p
                                    class="truncate
                                           text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ $recipient }}
                                </p>


                                @if($phone)

                                    <p
                                        class="mt-0.5
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        {{ $phone }}
                                    </p>

                                @endif

                            </div>

                        </div>

                    </div>


                    {{-- ITEMS --}}
                    <div class="mt-4 space-y-3">

                        @foreach(
                            collect($order['items'] ?? [])
                                ->take(3)
                            as $item
                        )

                            <div
                                class="flex
                                       items-center gap-3"
                            >

                                <div
                                    class="flex h-11 w-11
                                           shrink-0
                                           items-center justify-center
                                           overflow-hidden
                                           rounded-xl
                                           border border-[#E4EAE6]
                                           bg-[#F1F4F2]"
                                >

                                    @if(!empty($item['image']))

                                        <img
                                            src="{{ $item['image'] }}"
                                            alt="{{ $item['name'] ?? 'Product' }}"
                                            class="h-full w-full object-cover"
                                        >

                                    @else

                                        <i
                                            data-lucide="package"
                                            class="h-4 w-4 text-[#89968F]"
                                        ></i>

                                    @endif

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p
                                        class="truncate
                                               text-xs
                                               font-medium
                                               text-[#52635B]"
                                    >
                                        {{ $item['name'] ?? 'Product' }}
                                    </p>

                                    <p
                                        class="mt-0.5
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        Qty:
                                        {{ $item['quantity'] ?? 1 }}
                                    </p>

                                </div>

                            </div>

                        @endforeach


                        @if(count($order['items'] ?? []) > 3)

                            <p
                                class="text-[10px]
                                       font-medium
                                       text-[#1F6F5B]"
                            >
                                +
                                {{ count($order['items']) - 3 }}
                                more item(s)
                            </p>

                        @endif

                    </div>


                    {{-- INFO --}}
                    <div
                        class="mt-5
                               grid grid-cols-2
                               gap-3"
                    >

                        <div
                            class="rounded-xl
                                   bg-[#F7F9F8]
                                   p-3"
                        >

                            <p
                                class="text-[9px]
                                       font-semibold
                                       uppercase
                                       tracking-[0.08em]
                                       text-[#95A19B]"
                            >
                                Payment
                            </p>

                            <p
                                class="mt-1
                                       text-[11px]
                                       font-semibold
                                       text-[#52635B]"
                            >
                                {{ ($order['payment_method'] ?? '') === 'gcash'
                                    ? 'GCash'
                                    : 'COD' }}
                            </p>

                        </div>


                        <div
                            class="rounded-xl
                                   bg-[#F7F9F8]
                                   p-3"
                        >

                            <p
                                class="text-[9px]
                                       font-semibold
                                       uppercase
                                       tracking-[0.08em]
                                       text-[#95A19B]"
                            >
                                Total
                            </p>

                            <p
                                class="mt-1
                                       text-sm
                                       font-semibold
                                       text-[#24312C]"
                            >
                                ₱{{ number_format(
                                    (float) ($order['total'] ?? 0),
                                    2
                                ) }}
                            </p>

                        </div>

                    </div>


                    {{-- ACTION --}}
                    <div class="mt-4">

                        @if($status === 'placed')

                            <form
                                action="{{ route(
                                    'order.status.update',
                                    $orderId
                                ) }}"
                                method="POST"
                            >

                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="confirmed"
                                >


                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full
                                           items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           px-4
                                           text-xs
                                           font-semibold
                                           text-white
                                           transition
                                           hover:bg-[#1F6F5B]"
                                >

                                    <i
                                        data-lucide="check"
                                        class="h-4 w-4"
                                    ></i>

                                    Confirm Order

                                </button>

                            </form>


                        @elseif($status === 'confirmed')

                            <form
                                action="{{ route(
                                    'order.status.update',
                                    $orderId
                                ) }}"
                                method="POST"
                            >

                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="preparing"
                                >


                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full
                                           items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           px-4
                                           text-xs
                                           font-semibold
                                           text-white
                                           transition
                                           hover:bg-[#1F6F5B]"
                                >

                                    <i
                                        data-lucide="package-open"
                                        class="h-4 w-4"
                                    ></i>

                                    Start Preparing

                                </button>

                            </form>


                        @elseif($status === 'preparing')

                            <form
                                action="{{ route(
                                    'order.status.update',
                                    $orderId
                                ) }}"
                                method="POST"
                            >

                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="ready_for_pickup"
                                >


                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full
                                           items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           px-4
                                           text-xs
                                           font-semibold
                                           text-white
                                           transition
                                           hover:bg-[#1F6F5B]"
                                >

                                    <i
                                        data-lucide="package-check"
                                        class="h-4 w-4"
                                    ></i>

                                    Mark Ready for Pickup

                                </button>

                            </form>


                        @elseif($status === 'ready_for_pickup')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-indigo-50
                                       text-[11px]
                                       font-semibold
                                       text-indigo-700"
                            >

                                <i
                                    data-lucide="clock"
                                    class="h-4 w-4"
                                ></i>

                                Waiting for Logistics Pickup

                            </div>


                        @elseif(in_array(
                            $status,
                            [
                                'picked_up',
                                'at_sorting_center',
                                'sorted',
                                'assigned_to_rider',
                                'out_for_delivery'
                            ]
                        ))

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-[#F3F6F4]
                                       text-[11px]
                                       font-semibold
                                       text-[#68776F]"
                            >

                                <i
                                    data-lucide="truck"
                                    class="h-4 w-4"
                                ></i>

                                Shipment in Progress

                            </div>


                        @elseif($status === 'delivered')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-emerald-50
                                       text-[11px]
                                       font-semibold
                                       text-emerald-700"
                            >

                                <i
                                    data-lucide="map-pin-check"
                                    class="h-4 w-4"
                                ></i>

                                Order Delivered

                            </div>


                        @elseif($status === 'completed')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-emerald-50
                                       text-[11px]
                                       font-semibold
                                       text-emerald-700"
                            >

                                <i
                                    data-lucide="badge-check"
                                    class="h-4 w-4"
                                ></i>

                                Order Completed

                            </div>


                        @elseif($status === 'delivery_failed')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-red-50
                                       text-[11px]
                                       font-semibold
                                       text-red-700"
                            >

                                <i
                                    data-lucide="triangle-alert"
                                    class="h-4 w-4"
                                ></i>

                                Delivery Failed

                            </div>


                        @elseif($status === 'returned')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-rose-50
                                       text-[11px]
                                       font-semibold
                                       text-rose-700"
                            >

                                <i
                                    data-lucide="rotate-ccw"
                                    class="h-4 w-4"
                                ></i>

                                Order Returned

                            </div>


                        @elseif($status === 'cancelled')

                            <div
                                class="flex h-10
                                       items-center
                                       justify-center gap-2
                                       rounded-xl
                                       bg-gray-100
                                       text-[11px]
                                       font-semibold
                                       text-gray-600"
                            >

                                <i
                                    data-lucide="circle-x"
                                    class="h-4 w-4"
                                ></i>

                                Order Cancelled

                            </div>

                        @endif

                    </div>

                </article>

            @endforeach

        </div>


        {{-- =================================================
            NO FILTER RESULTS
        ================================================== --}}

        <div
            id="noOrdersFound"
            class="hidden
                   px-6 py-14
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-12 w-12
                       items-center justify-center
                       rounded-2xl
                       bg-[#F1F4F2]
                       text-[#87958E]"
            >

                <i
                    data-lucide="search-x"
                    class="h-5 w-5"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No matching orders
            </h3>


            <p
                class="mt-1
                       text-xs
                       text-[#849089]"
            >
                Try another keyword or change the selected status.
            </p>


            <button
                type="button"
                id="noOrdersClear"
                class="mt-4
                       inline-flex h-9
                       items-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-4
                       text-[11px]
                       font-semibold
                       text-[#52635B]
                       transition
                       hover:bg-[#F5F8F6]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="rotate-ccw"
                    class="h-3.5 w-3.5"
                ></i>

                Clear filters

            </button>

        </div>

    @endif

</section>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById(
                'orderSearch'
            );

        const statusFilter =
            document.getElementById(
                'statusFilter'
            );

        const clearButton =
            document.getElementById(
                'clearOrderFilters'
            );

        const noOrdersClear =
            document.getElementById(
                'noOrdersClear'
            );

        const tabs =
            Array.from(
                document.querySelectorAll(
                    '.order-tab'
                )
            );

        const desktopRows =
            Array.from(
                document.querySelectorAll(
                    '.order-row'
                )
            );

        const mobileCards =
            Array.from(
                document.querySelectorAll(
                    '.order-card'
                )
            );

        const noResults =
            document.getElementById(
                'noOrdersFound'
            );

        let activeStatus = 'all';


        function matchesOrder(
            element,
            search,
            status
        ) {

            const searchable =
                (
                    element.dataset.search || ''
                ).toLowerCase();

            const orderStatus =
                element.dataset.status || '';


            const matchesSearch =
                search === '' ||
                searchable.includes(search);

            const matchesStatus =
                status === 'all' ||
                orderStatus === status;


            return (
                matchesSearch &&
                matchesStatus
            );

        }


        function updateTabs() {

            tabs.forEach(
                function (tab) {

                    const isActive =
                        tab.dataset.statusTab
                        === activeStatus;


                    tab.classList.toggle(
                        'bg-[#173F35]',
                        isActive
                    );

                    tab.classList.toggle(
                        'text-white',
                        isActive
                    );

                    tab.classList.toggle(
                        'bg-[#F1F4F2]',
                        !isActive
                    );

                    tab.classList.toggle(
                        'text-[#68776F]',
                        !isActive
                    );

                }
            );

        }


        function filterOrders() {

            const search =
                (
                    searchInput?.value || ''
                )
                .toLowerCase()
                .trim();


            const status =
                activeStatus !== 'all'
                    ? activeStatus
                    : (
                        statusFilter?.value
                        || 'all'
                    );


            let desktopVisible = 0;
            let mobileVisible = 0;


            desktopRows.forEach(
                function (row) {

                    const visible =
                        matchesOrder(
                            row,
                            search,
                            status
                        );


                    row.classList.toggle(
                        'hidden',
                        !visible
                    );


                    if (visible) {
                        desktopVisible++;
                    }

                }
            );


            mobileCards.forEach(
                function (card) {

                    const visible =
                        matchesOrder(
                            card,
                            search,
                            status
                        );


                    card.classList.toggle(
                        'hidden',
                        !visible
                    );


                    if (visible) {
                        mobileVisible++;
                    }

                }
            );


            const hasOrders =
                desktopRows.length > 0 ||
                mobileCards.length > 0;


            const hasVisibleOrders =
                desktopVisible > 0 ||
                mobileVisible > 0;


            noResults?.classList.toggle(
                'hidden',
                !hasOrders ||
                hasVisibleOrders
            );

        }


        function clearFilters() {

            activeStatus = 'all';


            if (searchInput) {
                searchInput.value = '';
            }


            if (statusFilter) {
                statusFilter.value = 'all';
            }


            updateTabs();

            filterOrders();

        }


        searchInput?.addEventListener(
            'input',
            filterOrders
        );


        statusFilter?.addEventListener(
            'change',
            function () {

                activeStatus = 'all';

                updateTabs();

                filterOrders();

            }
        );


        tabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        activeStatus =
                            this.dataset.statusTab
                            || 'all';


                        if (statusFilter) {
                            statusFilter.value = 'all';
                        }


                        updateTabs();

                        filterOrders();

                    }
                );

            }
        );


        clearButton?.addEventListener(
            'click',
            clearFilters
        );


        noOrdersClear?.addEventListener(
            'click',
            clearFilters
        );


        updateTabs();

        filterOrders();


        if (
            typeof lucide !== 'undefined' &&
            typeof lucide.createIcons ===
                'function'
        ) {

            lucide.createIcons();

        }

    }
);

</script>

@endpush

@endsection