@extends('layouts.seller')


@section('title', 'Dashboard')

@section('page-title', 'Dashboard')


@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD DATA
    |--------------------------------------------------------------------------
    */

    $orders = session('orders', []);

    $products = session('seller_products', []);


    /*
    |--------------------------------------------------------------------------
    | ORDER COUNTS
    |--------------------------------------------------------------------------
    */

    $totalOrders = count($orders);

    $toConfirm = collect($orders)
        ->where('status', 'placed')
        ->count();

    $preparing = collect($orders)
        ->whereIn('status', [
            'confirmed',
            'preparing'
        ])
        ->count();

    $readyForPickup = collect($orders)
        ->where(
            'status',
            'ready_for_pickup'
        )
        ->count();

    $completedOrders = collect($orders)
        ->whereIn('status', [
            'delivered',
            'completed'
        ])
        ->count();

    $cancelledOrders = collect($orders)
        ->where(
            'status',
            'cancelled'
        )
        ->count();


    /*
    |--------------------------------------------------------------------------
    | REVENUE
    |--------------------------------------------------------------------------
    */

    $totalRevenue = collect($orders)
        ->whereIn('status', [
            'delivered',
            'completed'
        ])
        ->sum(function ($order) {

            return (float) (
                $order['total']
                ?? $order['grand_total']
                ?? 0
            );

        });


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS
    |--------------------------------------------------------------------------
    */

    $totalProducts = count($products);

    $lowStockProducts = collect($products)
        ->filter(function ($product) {

            return (int) (
                $product['stock']
                ?? 0
            ) <= 5;

        })
        ->count();


    /*
    |--------------------------------------------------------------------------
    | RECENT ORDERS
    |--------------------------------------------------------------------------
    */

    $recentOrders = collect($orders)
        ->sortByDesc(function ($order) {

            return $order['created_at'] ?? '';

        })
        ->take(5);


    /*
    |--------------------------------------------------------------------------
    | LOW STOCK
    |--------------------------------------------------------------------------
    */

    $lowStockList = collect($products)
        ->filter(function ($product) {

            return (int) (
                $product['stock']
                ?? 0
            ) <= 5;

        })
        ->take(5);


    /*
    |--------------------------------------------------------------------------
    | STATUS LABELS
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


    /*
    |--------------------------------------------------------------------------
    | STATUS CLASSES
    |--------------------------------------------------------------------------
    */

    $statusClasses = [

        'placed'
            => 'bg-amber-50 text-amber-700 border-amber-200',

        'confirmed'
            => 'bg-blue-50 text-blue-700 border-blue-200',

        'preparing'
            => 'bg-violet-50 text-violet-700 border-violet-200',

        'ready_for_pickup'
            => 'bg-indigo-50 text-indigo-700 border-indigo-200',

        'picked_up'
            => 'bg-cyan-50 text-cyan-700 border-cyan-200',

        'at_sorting_center'
            => 'bg-sky-50 text-sky-700 border-sky-200',

        'sorted'
            => 'bg-teal-50 text-teal-700 border-teal-200',

        'assigned_to_rider'
            => 'bg-violet-50 text-violet-700 border-violet-200',

        'out_for_delivery'
            => 'bg-orange-50 text-orange-700 border-orange-200',

        'delivered'
            => 'bg-emerald-50 text-emerald-700 border-emerald-200',

        'completed'
            => 'bg-emerald-50 text-emerald-700 border-emerald-200',

        'delivery_failed'
            => 'bg-red-50 text-red-700 border-red-200',

        'returned'
            => 'bg-rose-50 text-rose-700 border-rose-200',

        'cancelled'
            => 'bg-gray-100 text-gray-600 border-gray-200',

    ];


    /*
    |--------------------------------------------------------------------------
    | SALES VISUAL
    |--------------------------------------------------------------------------
    */

    $salesBars = [
        30,
        42,
        38,
        54,
        47,
        66,
        57,
        74,
        68,
        86,
        78,
        94
    ];

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

            <p
                class="text-xs
                       font-semibold
                       text-emerald-800"
            >
                Success
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


        <p
            class="pt-1
                   text-xs
                   leading-5
                   text-red-700"
        >
            {{ session('error') }}
        </p>

    </div>

@endif



{{-- =========================================================
    PAGE INTRODUCTION
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        <p
            class="text-[11px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#1F6F5B]"
        >
            Store overview
        </p>

        <h2
            class="mt-1
                   text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Good day, Seller.
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Here is a quick overview of your store,
            orders, products, and current inventory.
        </p>

    </div>


    <a
        href="{{ route('seller.orders') }}"
        class="inline-flex h-10
               items-center justify-center gap-2
               self-start
               rounded-xl
               border border-[#DDE6E1]
               bg-white
               px-4
               text-xs font-semibold
               text-[#52635B]
               transition
               hover:border-[#BFD2C9]
               hover:text-[#173F35]
               sm:self-auto"
    >

        View all orders

        <i
            data-lucide="arrow-up-right"
            class="h-4 w-4"
        ></i>

    </a>

</div>



{{-- =========================================================
    SUMMARY
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-1
           gap-4
           sm:grid-cols-2
           xl:grid-cols-4"
>


    {{-- REVENUE --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-4"
        >

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Total Revenue
                </p>

                <p
                    class="mt-3
                           text-[25px]
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    ₱{{ number_format($totalRevenue, 2) }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#DDF3EC]
                       text-[#173F35]"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <div
            class="mt-5
                   flex items-center gap-2
                   border-t border-[#EEF2F0]
                   pt-3"
        >

            <i
                data-lucide="circle-check"
                class="h-3.5 w-3.5 text-[#1F6F5B]"
            ></i>

            <p
                class="text-[11px]
                       text-[#7B8982]"
            >
                From completed orders
            </p>

        </div>

    </div>



    {{-- ORDERS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-4"
        >

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Total Orders
                </p>

                <p
                    class="mt-3
                           text-[25px]
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalOrders }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="shopping-bag"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <div
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3"
        >

            <p
                class="text-[11px]
                       text-[#7B8982]"
            >
                {{ $completedOrders }}
                completed order(s)
            </p>

        </div>

    </div>



    {{-- TO PROCESS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-4"
        >

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    To Process
                </p>

                <p
                    class="mt-3
                           text-[25px]
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $toConfirm + $preparing + $readyForPickup }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-amber-50
                       text-amber-700"
            >

                <i
                    data-lucide="clock-3"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <div
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3"
        >

            <p
                class="text-[11px]
                       text-[#7B8982]"
            >
                {{ $toConfirm }}
                waiting for confirmation
            </p>

        </div>

    </div>



    {{-- PRODUCTS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-4"
        >

            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Products
                </p>

                <p
                    class="mt-3
                           text-[25px]
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalProducts }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#F0F2F8]
                       text-[#5E6475]"
            >

                <i
                    data-lucide="package"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <div
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3"
        >

            <p
                class="text-[11px]
                       {{ $lowStockProducts > 0
                            ? 'text-amber-700'
                            : 'text-[#7B8982]' }}"
            >
                {{ $lowStockProducts }}
                low stock item(s)
            </p>

        </div>

    </div>

</div>



{{-- =========================================================
    QUICK ACTIONS
========================================================= --}}

<div
    class="mb-6
           overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>

    <div
        class="flex items-center justify-between
               border-b border-[#EDF1EF]
               px-5 py-4"
    >

        <div>

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Quick Actions
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Common store management tasks
            </p>

        </div>

    </div>


    <div
        class="grid
               divide-y divide-[#EDF1EF]
               sm:grid-cols-3
               sm:divide-x
               sm:divide-y-0"
    >


        <a
            href="{{ route('seller.products.create') }}"
            class="group
                   flex items-center gap-4
                   px-5 py-5
                   transition
                   hover:bg-[#F8FAF8]"
        >

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-[#DDF3EC]
                       text-[#173F35]
                       transition
                       group-hover:bg-[#173F35]
                       group-hover:text-white"
            >

                <i
                    data-lucide="plus"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>


            <div>

                <p
                    class="text-[13px]
                           font-semibold
                           text-[#2F3E37]"
                >
                    Add Product
                </p>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#849089]"
                >
                    Create a new listing
                </p>

            </div>

        </a>



        <a
            href="{{ route('seller.orders') }}"
            class="group
                   flex items-center gap-4
                   px-5 py-5
                   transition
                   hover:bg-[#F8FAF8]"
        >

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]
                       transition
                       group-hover:bg-[#173F35]
                       group-hover:text-white"
            >

                <i
                    data-lucide="clipboard-list"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>


            <div>

                <p
                    class="text-[13px]
                           font-semibold
                           text-[#2F3E37]"
                >
                    Manage Orders
                </p>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#849089]"
                >
                    {{ $toConfirm }}
                    awaiting confirmation
                </p>

            </div>

        </a>



        <a
            href="{{ route('seller.inventory') }}"
            class="group
                   flex items-center gap-4
                   px-5 py-5
                   transition
                   hover:bg-[#F8FAF8]"
        >

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-[#F3F5F4]
                       text-[#5F6E67]
                       transition
                       group-hover:bg-[#173F35]
                       group-hover:text-white"
            >

                <i
                    data-lucide="boxes"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>


            <div>

                <p
                    class="text-[13px]
                           font-semibold
                           text-[#2F3E37]"
                >
                    Inventory
                </p>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#849089]"
                >
                    Monitor product stock
                </p>

            </div>

        </a>

    </div>

</div>



{{-- =========================================================
    PERFORMANCE + STATUS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-1
           gap-6
           xl:grid-cols-[1.65fr_1fr]"
>


    {{-- PERFORMANCE --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="flex items-center
                   justify-between gap-4
                   border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <div>

                <h3
                    class="text-sm
                           font-semibold
                           text-[#24312C]"
                >
                    Sales Overview
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Store performance overview
                </p>

            </div>


            <a
                href="{{ route('seller.reports') }}"
                class="inline-flex
                       items-center gap-1.5
                       text-[11px]
                       font-semibold
                       text-[#1F6F5B]
                       hover:text-[#173F35]"
            >
                Reports

                <i
                    data-lucide="arrow-up-right"
                    class="h-3.5 w-3.5"
                ></i>

            </a>

        </div>


        <div class="p-5">

            <div
                class="mb-7
                       grid grid-cols-2
                       gap-3"
            >

                <div
                    class="rounded-xl
                           bg-[#F5F8F6]
                           p-4"
                >

                    <p
                        class="text-[10px]
                               font-medium
                               uppercase
                               tracking-[0.08em]
                               text-[#839189]"
                    >
                        Completed Sales
                    </p>

                    <p
                        class="mt-2
                               text-xl
                               font-semibold
                               tracking-[-0.03em]
                               text-[#24312C]"
                    >
                        ₱{{ number_format($totalRevenue, 2) }}
                    </p>

                </div>


                <div
                    class="rounded-xl
                           bg-[#F5F8F6]
                           p-4"
                >

                    <p
                        class="text-[10px]
                               font-medium
                               uppercase
                               tracking-[0.08em]
                               text-[#839189]"
                    >
                        Completed Orders
                    </p>

                    <p
                        class="mt-2
                               text-xl
                               font-semibold
                               tracking-[-0.03em]
                               text-[#24312C]"
                    >
                        {{ $completedOrders }}
                    </p>

                </div>

            </div>


            {{-- SALES BARS --}}
            <div
                class="flex h-44
                       items-end gap-2
                       sm:gap-3"
            >

                @foreach($salesBars as $index => $height)

                    <div
                        class="group
                               flex h-full flex-1
                               items-end"
                        title="Sales period {{ $index + 1 }}"
                    >

                        <div
                            class="w-full
                                   rounded-t-[6px]
                                   bg-[#D9EDE6]
                                   transition
                                   duration-200
                                   group-hover:bg-[#1F6F5B]"
                            style="height: {{ $height }}%;"
                        ></div>

                    </div>

                @endforeach

            </div>


            <div
                class="mt-3
                       flex items-center
                       justify-between
                       text-[9px]
                       font-medium
                       uppercase
                       tracking-[0.08em]
                       text-[#A0AAA5]"
            >

                <span>Earlier</span>

                <span>Current</span>

            </div>

        </div>

    </div>



    {{-- ORDER STATUS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Order Status
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Current fulfillment progress
            </p>

        </div>


        <div class="p-3">

            @php

                $orderStatusItems = [

                    [
                        'label' => 'To Confirm',
                        'count' => $toConfirm,
                        'icon' => 'clock-3',
                        'iconClass' => 'bg-amber-50 text-amber-700',
                    ],

                    [
                        'label' => 'Preparing',
                        'count' => $preparing,
                        'icon' => 'package-open',
                        'iconClass' => 'bg-violet-50 text-violet-700',
                    ],

                    [
                        'label' => 'Ready for Pickup',
                        'count' => $readyForPickup,
                        'icon' => 'package-check',
                        'iconClass' => 'bg-blue-50 text-blue-700',
                    ],

                    [
                        'label' => 'Completed',
                        'count' => $completedOrders,
                        'icon' => 'circle-check',
                        'iconClass' => 'bg-emerald-50 text-emerald-700',
                    ],

                    [
                        'label' => 'Cancelled',
                        'count' => $cancelledOrders,
                        'icon' => 'circle-x',
                        'iconClass' => 'bg-gray-100 text-gray-600',
                    ],

                ];

            @endphp


            @foreach($orderStatusItems as $item)

                <a
                    href="{{ route('seller.orders') }}"
                    class="flex items-center
                           justify-between gap-4
                           rounded-xl
                           px-3 py-3
                           transition
                           hover:bg-[#F7F9F8]"
                >

                    <div
                        class="flex min-w-0
                               items-center gap-3"
                    >

                        <div
                            class="
                                flex h-9 w-9
                                shrink-0
                                items-center justify-center
                                rounded-xl
                                {{ $item['iconClass'] }}
                            "
                        >

                            <i
                                data-lucide="{{ $item['icon'] }}"
                                class="h-4 w-4"
                            ></i>

                        </div>


                        <span
                            class="truncate
                                   text-xs
                                   font-medium
                                   text-[#52635B]"
                        >
                            {{ $item['label'] }}
                        </span>

                    </div>


                    <span
                        class="text-sm
                               font-semibold
                               text-[#24312C]"
                    >
                        {{ $item['count'] }}
                    </span>

                </a>

            @endforeach

        </div>

    </div>

</div>



{{-- =========================================================
    RECENT ORDERS
========================================================= --}}

<div
    class="mb-6
           overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>

    <div
        class="flex items-center
               justify-between gap-4
               border-b border-[#EDF1EF]
               px-5 py-4"
    >

        <div>

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Recent Orders
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Latest orders received by your store
            </p>

        </div>


        <a
            href="{{ route('seller.orders') }}"
            class="inline-flex
                   items-center gap-1
                   text-[11px]
                   font-semibold
                   text-[#1F6F5B]
                   hover:text-[#173F35]"
        >
            View all

            <i
                data-lucide="arrow-right"
                class="h-3.5 w-3.5"
            ></i>

        </a>

    </div>


    @if($recentOrders->count())


        {{-- DESKTOP --}}
        <div class="hidden overflow-x-auto md:block">

            <table class="w-full">

                <thead>

                    <tr
                        class="border-b
                               border-[#EDF1EF]
                               bg-[#F8FAF8]"
                    >

                        <th class="px-5 py-3 text-left">
                            Order
                        </th>

                        <th class="px-5 py-3 text-left">
                            Buyer
                        </th>

                        <th class="px-5 py-3 text-left">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left">
                            Total
                        </th>

                        <th class="px-5 py-3 text-left">
                            Status
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($recentOrders as $orderId => $order)

                        @php

                            $status =
                                $order['status']
                                ?? 'placed';

                            $statusLabel =
                                $statusLabels[$status]
                                ?? ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $status
                                    )
                                );

                            $statusClass =
                                $statusClasses[$status]
                                ?? 'bg-gray-100 text-gray-600 border-gray-200';

                            $orderTotal =
                                (float) (
                                    $order['total']
                                    ?? $order['grand_total']
                                    ?? 0
                                );

                            $buyerName =
                                $order['buyer_name']
                                ?? $order['customer_name']
                                ?? 'Buyer';

                            $orderDate =
                                $order['created_at']
                                ?? null;

                        @endphp


                        <tr
                            class="border-b
                                   border-[#F0F3F1]
                                   last:border-b-0"
                        >

                            <td
                                class="px-5 py-4
                                       text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                #{{ $order['order_number'] ?? $orderId }}
                            </td>


                            <td
                                class="px-5 py-4
                                       text-xs
                                       text-[#617169]"
                            >
                                {{ $buyerName }}
                            </td>


                            <td
                                class="px-5 py-4
                                       text-xs
                                       text-[#7B8982]"
                            >

                                @if($orderDate)

                                    {{ \Carbon\Carbon::parse($orderDate)->format('M d, Y') }}

                                @else

                                    —

                                @endif

                            </td>


                            <td
                                class="px-5 py-4
                                       text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                ₱{{ number_format($orderTotal, 2) }}
                            </td>


                            <td class="px-5 py-4">

                                <span
                                    class="
                                        inline-flex
                                        rounded-full
                                        border
                                        px-2.5 py-1
                                        text-[10px]
                                        font-semibold
                                        {{ $statusClass }}
                                    "
                                >
                                    {{ $statusLabel }}
                                </span>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>



        {{-- MOBILE --}}
        <div
            class="divide-y
                   divide-[#EDF1EF]
                   md:hidden"
        >

            @foreach($recentOrders as $orderId => $order)

                @php

                    $status =
                        $order['status']
                        ?? 'placed';

                    $statusLabel =
                        $statusLabels[$status]
                        ?? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $status
                            )
                        );

                    $statusClass =
                        $statusClasses[$status]
                        ?? 'bg-gray-100 text-gray-600 border-gray-200';

                    $orderTotal =
                        (float) (
                            $order['total']
                            ?? $order['grand_total']
                            ?? 0
                        );

                    $buyerName =
                        $order['buyer_name']
                        ?? $order['customer_name']
                        ?? 'Buyer';

                @endphp


                <div class="p-4">

                    <div
                        class="flex items-start
                               justify-between gap-4"
                    >

                        <div class="min-w-0">

                            <p
                                class="text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                #{{ $order['order_number'] ?? $orderId }}
                            </p>

                            <p
                                class="mt-1 truncate
                                       text-[11px]
                                       text-[#7C8983]"
                            >
                                {{ $buyerName }}
                            </p>

                        </div>


                        <span
                            class="
                                inline-flex
                                shrink-0
                                rounded-full
                                border
                                px-2 py-1
                                text-[9px]
                                font-semibold
                                {{ $statusClass }}
                            "
                        >
                            {{ $statusLabel }}
                        </span>

                    </div>


                    <div
                        class="mt-4
                               flex items-center
                               justify-between
                               border-t border-[#F0F3F1]
                               pt-3"
                    >

                        <span
                            class="text-[10px]
                                   text-[#8A9791]"
                        >
                            Order total
                        </span>

                        <span
                            class="text-xs
                                   font-semibold
                                   text-[#24312C]"
                        >
                            ₱{{ number_format($orderTotal, 2) }}
                        </span>

                    </div>

                </div>

            @endforeach

        </div>


    @else

        <div class="px-6 py-14 text-center">

            <div
                class="mx-auto
                       flex h-12 w-12
                       items-center justify-center
                       rounded-2xl
                       bg-[#F1F5F3]
                       text-[#87958E]"
            >

                <i
                    data-lucide="shopping-bag"
                    class="h-5 w-5"
                ></i>

            </div>


            <p
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No orders yet
            </p>

            <p
                class="mx-auto mt-1
                       max-w-sm
                       text-xs
                       leading-5
                       text-[#87958E]"
            >
                New customer orders will appear here
                once your store starts receiving purchases.
            </p>

        </div>

    @endif

</div>



{{-- =========================================================
    PRODUCTS + STOCK
========================================================= --}}

<div
    class="grid grid-cols-1
           gap-6
           xl:grid-cols-2"
>


    {{-- PRODUCTS --}}
    <div
        class="overflow-hidden
               rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="flex items-center
                   justify-between gap-4
                   border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <div>

                <h3
                    class="text-sm
                           font-semibold
                           text-[#24312C]"
                >
                    Products
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Product performance at a glance
                </p>

            </div>


            <a
                href="{{ route('seller.products') }}"
                class="text-[11px]
                       font-semibold
                       text-[#1F6F5B]
                       hover:text-[#173F35]"
            >
                Manage
            </a>

        </div>


        @if(collect($products)->count())

            <div
                class="divide-y
                       divide-[#EDF1EF]"
            >

                @foreach(collect($products)->take(5) as $productIndex => $product)

                    <div
                        class="flex items-center gap-4
                               px-5 py-4"
                    >

                        <div
                            class="flex h-11 w-11
                                   shrink-0
                                   items-center justify-center
                                   overflow-hidden
                                   rounded-xl
                                   bg-[#F1F4F2]"
                        >

                            @if(!empty($product['image']))

                                <img
                                    src="{{ $product['image'] }}"
                                    alt="{{ $product['name'] ?? 'Product' }}"
                                    class="h-full w-full object-cover"
                                >

                            @else

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4 text-[#91A099]"
                                ></i>

                            @endif

                        </div>


                        <div class="min-w-0 flex-1">

                            <p
                                class="truncate
                                       text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                {{ $product['name'] ?? 'Product ' . ($productIndex + 1) }}
                            </p>

                            <p
                                class="mt-1
                                       text-[10px]
                                       text-[#8A9791]"
                            >
                                {{ $product['sold'] ?? $product['sales'] ?? 0 }}
                                sold
                            </p>

                        </div>


                        <p
                            class="shrink-0
                                   text-xs
                                   font-semibold
                                   text-[#24312C]"
                        >
                            ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                        </p>

                    </div>

                @endforeach

            </div>

        @else

            <div class="px-6 py-12 text-center">

                <div
                    class="mx-auto
                           flex h-11 w-11
                           items-center justify-center
                           rounded-xl
                           bg-[#F1F4F2]
                           text-[#91A099]"
                >

                    <i
                        data-lucide="package-plus"
                        class="h-5 w-5"
                    ></i>

                </div>


                <p
                    class="mt-3
                           text-xs
                           font-semibold
                           text-[#34483F]"
                >
                    No products yet
                </p>


                <a
                    href="{{ route('seller.products.create') }}"
                    class="mt-2
                           inline-flex
                           text-[11px]
                           font-semibold
                           text-[#1F6F5B]"
                >
                    Add your first product
                </a>

            </div>

        @endif

    </div>



    {{-- LOW STOCK --}}
    <div
        class="overflow-hidden
               rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="flex items-center
                   justify-between gap-4
                   border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <div>

                <h3
                    class="text-sm
                           font-semibold
                           text-[#24312C]"
                >
                    Stock Attention
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Products requiring inventory review
                </p>

            </div>


            <span
                class="rounded-full
                       bg-amber-50
                       px-2.5 py-1
                       text-[10px]
                       font-semibold
                       text-amber-700"
            >
                {{ $lowStockProducts }}
                items
            </span>

        </div>


        @if($lowStockList->count())

            <div
                class="divide-y
                       divide-[#EDF1EF]"
            >

                @foreach($lowStockList as $productIndex => $product)

                    @php

                        $stock =
                            (int) (
                                $product['stock']
                                ?? 0
                            );

                    @endphp


                    <div
                        class="flex items-center gap-4
                               px-5 py-4"
                    >

                        <div
                            class="flex h-11 w-11
                                   shrink-0
                                   items-center justify-center
                                   overflow-hidden
                                   rounded-xl
                                   bg-[#F1F4F2]"
                        >

                            @if(!empty($product['image']))

                                <img
                                    src="{{ $product['image'] }}"
                                    alt="{{ $product['name'] ?? 'Product' }}"
                                    class="h-full w-full object-cover"
                                >

                            @else

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4 text-[#91A099]"
                                ></i>

                            @endif

                        </div>


                        <div class="min-w-0 flex-1">

                            <p
                                class="truncate
                                       text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                {{ $product['name'] ?? 'Product' }}
                            </p>

                            <p
                                class="mt-1
                                       text-[10px]
                                       text-[#8A9791]"
                            >
                                Current inventory
                            </p>

                        </div>


                        <span
                            class="
                                shrink-0
                                rounded-full
                                border
                                px-2.5 py-1
                                text-[10px]
                                font-semibold

                                {{ $stock <= 0
                                    ? 'border-red-200 bg-red-50 text-red-700'
                                    : 'border-amber-200 bg-amber-50 text-amber-700' }}
                            "
                        >

                            {{ $stock <= 0
                                ? 'Out of stock'
                                : $stock . ' left' }}

                        </span>

                    </div>

                @endforeach

            </div>

        @else

            <div class="px-6 py-12 text-center">

                <div
                    class="mx-auto
                           flex h-11 w-11
                           items-center justify-center
                           rounded-xl
                           bg-emerald-50
                           text-emerald-700"
                >

                    <i
                        data-lucide="package-check"
                        class="h-5 w-5"
                    ></i>

                </div>


                <p
                    class="mt-3
                           text-xs
                           font-semibold
                           text-[#34483F]"
                >
                    Stock levels look good
                </p>

                <p
                    class="mt-1
                           text-[11px]
                           text-[#8A9791]"
                >
                    No products currently need restocking.
                </p>

            </div>

        @endif

    </div>

</div>


@endsection