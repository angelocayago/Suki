@extends('layouts.seller')

@section('title', 'Reports & Analytics')
@section('page-title', 'Reports & Analytics')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | SELLER REPORT DATA
    |--------------------------------------------------------------------------
    */

    $products = session('seller_products', []);
    $orders = session('orders', []);

    $sellerOrders = collect($orders);

    $totalOrders = $sellerOrders->count();


    /*
    |--------------------------------------------------------------------------
    | COMPLETED SALES
    |--------------------------------------------------------------------------
    */

    $completedOrders = $sellerOrders
        ->filter(function ($order) {

            return in_array(
                $order['status'] ?? '',
                [
                    'delivered',
                    'completed'
                ]
            );

        });


    $totalSales = $completedOrders
        ->sum(function ($order) {

            return (float) (
                $order['total']
                ?? $order['grand_total']
                ?? 0
            );

        });


    $allOrderSales = $sellerOrders
        ->sum(function ($order) {

            return (float) (
                $order['total']
                ?? $order['grand_total']
                ?? 0
            );

        });


    /*
    |--------------------------------------------------------------------------
    | PRODUCT SALES
    |--------------------------------------------------------------------------
    */

    $productsSold = $completedOrders
        ->sum(function ($order) {

            return collect(
                $order['items'] ?? []
            )
            ->sum(function ($item) {

                return (int) (
                    $item['quantity'] ?? 0
                );

            });

        });


    /*
    |--------------------------------------------------------------------------
    | EXISTING PROTOTYPE EARNINGS
    |--------------------------------------------------------------------------
    */

    $netEarnings = $totalSales * 0.90;


    /*
    |--------------------------------------------------------------------------
    | ORDER COUNTS
    |--------------------------------------------------------------------------
    */

    $toShip = $sellerOrders
        ->filter(function ($order) {

            return in_array(
                $order['status'] ?? '',
                [
                    'confirmed',
                    'preparing',
                    'ready_for_pickup'
                ]
            );

        })
        ->count();


    $cancelledOrders = $sellerOrders
        ->where('status', 'cancelled')
        ->count();


    $deliveryFailed = $sellerOrders
        ->where('status', 'delivery_failed')
        ->count();


    /*
    |--------------------------------------------------------------------------
    | RATES
    |--------------------------------------------------------------------------
    */

    $completionRate = $totalOrders > 0
        ? round(
            ($completedOrders->count() / $totalOrders) * 100,
            1
        )
        : 0;


    /*
    |--------------------------------------------------------------------------
    | TOP PRODUCTS
    |--------------------------------------------------------------------------
    */

    $topProducts = collect($products)
        ->map(function ($product) {

            return [

                'name' =>
                    $product['name']
                    ?? 'Unnamed Product',

                'image' =>
                    $product['image']
                    ?? null,

                'sold' =>
                    (int) (
                        $product['sold']
                        ?? 0
                    ),

                'stock' =>
                    (int) (
                        $product['stock']
                        ?? 0
                    ),

                'price' =>
                    (float) (
                        $product['price']
                        ?? 0
                    ),

            ];

        })
        ->sortByDesc('sold')
        ->take(5);


    /*
    |--------------------------------------------------------------------------
    | RECENT ORDERS
    |--------------------------------------------------------------------------
    */

    $recentOrders = $sellerOrders
        ->sortByDesc(function ($order) {

            return $order['created_at']
                ?? '';

        })
        ->take(6);


    /*
    |--------------------------------------------------------------------------
    | STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $statusCounts = [

        'Placed' =>
            $sellerOrders
                ->where('status', 'placed')
                ->count(),

        'Confirmed' =>
            $sellerOrders
                ->where('status', 'confirmed')
                ->count(),

        'Preparing' =>
            $sellerOrders
                ->where('status', 'preparing')
                ->count(),

        'Ready for Pickup' =>
            $sellerOrders
                ->where('status', 'ready_for_pickup')
                ->count(),

        'Picked Up' =>
            $sellerOrders
                ->where('status', 'picked_up')
                ->count(),

        'In Transit' =>
            $sellerOrders
                ->whereIn(
                    'status',
                    [
                        'at_sorting_center',
                        'sorted',
                        'assigned_to_rider'
                    ]
                )
                ->count(),

        'Out for Delivery' =>
            $sellerOrders
                ->where('status', 'out_for_delivery')
                ->count(),

        'Delivered' =>
            $sellerOrders
                ->where('status', 'delivered')
                ->count(),

        'Completed' =>
            $sellerOrders
                ->where('status', 'completed')
                ->count(),

        'Cancelled' =>
            $sellerOrders
                ->where('status', 'cancelled')
                ->count(),

    ];


    /*
    |--------------------------------------------------------------------------
    | CHART DATA
    |--------------------------------------------------------------------------
    | Existing frontend prototype data retained.
    */

    $chartLabels = [
        'Mon',
        'Tue',
        'Wed',
        'Thu',
        'Fri',
        'Sat',
        'Sun'
    ];


    $salesChart = [
        4200,
        6800,
        5100,
        8900,
        7600,
        11200,
        9800
    ];


    $ordersChart = [
        8,
        12,
        9,
        16,
        13,
        21,
        18
    ];


    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    $orderStatusLabels = [

        'placed' => 'Placed',

        'confirmed' => 'Confirmed',

        'preparing' => 'Preparing',

        'ready_for_pickup' =>
            'Ready for Pickup',

        'picked_up' => 'Picked Up',

        'at_sorting_center' =>
            'At Sorting Center',

        'sorted' => 'Sorted',

        'assigned_to_rider' =>
            'Assigned to Rider',

        'out_for_delivery' =>
            'Out for Delivery',

        'delivered' => 'Delivered',

        'completed' => 'Completed',

        'delivery_failed' =>
            'Delivery Failed',

        'returned' => 'Returned',

        'cancelled' => 'Cancelled',

    ];


    $orderStatusClasses = [

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

@endphp


{{-- =========================================================
    INTRO
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           lg:flex-row
           lg:items-end
           lg:justify-between"
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
                Reports
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Reports & Analytics
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Monitor sales, orders, products,
            and overall store performance.
        </p>

    </div>


    <div
        class="inline-flex h-10
               items-center gap-2
               self-start
               rounded-xl
               border border-[#DDE6E1]
               bg-white
               px-4
               text-[11px]
               font-semibold
               text-[#68776F]
               lg:self-auto"
    >

        <i
            data-lucide="calendar-days"
            class="h-4 w-4 text-[#1F6F5B]"
        ></i>

        Last 7 Days

    </div>

</div>


{{-- =========================================================
    MAIN PERFORMANCE CARDS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    {{-- SALES --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Total Sales
                </p>

                <p
                    class="mt-3
                           text-xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]
                           sm:text-2xl"
                >
                    ₱{{ number_format($totalSales, 2) }}
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
                    data-lucide="banknote"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Completed and delivered orders
        </p>

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
                   justify-between gap-3"
        >

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


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            {{ $toShip }} order(s) to process
        </p>

    </div>


    {{-- PRODUCTS SOLD --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Products Sold
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $productsSold }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-blue-50
                       text-blue-700"
            >

                <i
                    data-lucide="package-check"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Units from completed orders
        </p>

    </div>


    {{-- NET EARNINGS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Net Earnings
                </p>

                <p
                    class="mt-3
                           text-xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]
                           sm:text-2xl"
                >
                    ₱{{ number_format($netEarnings, 2) }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-emerald-50
                       text-emerald-700"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Existing prototype earnings calculation
        </p>

    </div>

</div>


{{-- =========================================================
    SECONDARY METRICS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-1
           gap-4
           sm:grid-cols-2
           xl:grid-cols-4"
>


    {{-- COMPLETION RATE --}}
    <div
        class="flex items-center gap-4
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-4"
    >

        <div
            class="flex h-11 w-11
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-emerald-50
                   text-emerald-700"
        >
            <i
                data-lucide="circle-check-big"
                class="h-5 w-5"
            ></i>
        </div>


        <div>

            <p
                class="text-[10px]
                       font-medium
                       text-[#849089]"
            >
                Completion Rate
            </p>

            <p
                class="mt-0.5
                       text-lg
                       font-semibold
                       text-[#24312C]"
            >
                {{ $completionRate }}%
            </p>

        </div>

    </div>


    {{-- ALL ORDER VALUE --}}
    <div
        class="flex items-center gap-4
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-4"
    >

        <div
            class="flex h-11 w-11
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-[#EEF5F1]
                   text-[#173F35]"
        >
            <i
                data-lucide="receipt-text"
                class="h-5 w-5"
            ></i>
        </div>


        <div class="min-w-0">

            <p
                class="text-[10px]
                       font-medium
                       text-[#849089]"
            >
                Gross Order Value
            </p>

            <p
                class="mt-0.5
                       truncate
                       text-lg
                       font-semibold
                       text-[#24312C]"
            >
                ₱{{ number_format($allOrderSales, 2) }}
            </p>

        </div>

    </div>


    {{-- CANCELLED --}}
    <div
        class="flex items-center gap-4
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-4"
    >

        <div
            class="flex h-11 w-11
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-gray-100
                   text-gray-600"
        >
            <i
                data-lucide="circle-x"
                class="h-5 w-5"
            ></i>
        </div>


        <div>

            <p
                class="text-[10px]
                       font-medium
                       text-[#849089]"
            >
                Cancelled Orders
            </p>

            <p
                class="mt-0.5
                       text-lg
                       font-semibold
                       text-[#24312C]"
            >
                {{ $cancelledOrders }}
            </p>

        </div>

    </div>


    {{-- DELIVERY FAILED --}}
    <div
        class="flex items-center gap-4
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-4"
    >

        <div
            class="flex h-11 w-11
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-red-50
                   text-red-600"
        >
            <i
                data-lucide="triangle-alert"
                class="h-5 w-5"
            ></i>
        </div>


        <div>

            <p
                class="text-[10px]
                       font-medium
                       text-[#849089]"
            >
                Delivery Failed
            </p>

            <p
                class="mt-0.5
                       text-lg
                       font-semibold
                       text-[#24312C]"
            >
                {{ $deliveryFailed }}
            </p>

        </div>

    </div>

</div>


{{-- =========================================================
    CHARTS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-1
           gap-6
           xl:grid-cols-2"
>


    {{-- SALES CHART --}}
    <section
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
                    Sales Performance
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Sales activity during the selected period
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >
                <i
                    data-lucide="chart-no-axes-combined"
                    class="h-4 w-4"
                ></i>
            </div>

        </div>


        <div class="p-5">

            <div
                class="mb-5
                       flex items-end
                       justify-between gap-4"
            >

                <div>

                    <p
                        class="text-[10px]
                               font-medium
                               uppercase
                               tracking-[0.1em]
                               text-[#8D9A94]"
                    >
                        Completed Sales
                    </p>

                    <p
                        class="mt-1
                               text-xl
                               font-semibold
                               tracking-[-0.03em]
                               text-[#24312C]"
                    >
                        ₱{{ number_format($totalSales, 2) }}
                    </p>

                </div>


                <span
                    class="inline-flex
                           items-center gap-1
                           rounded-full
                           bg-[#DDF3EC]
                           px-2.5 py-1
                           text-[9px]
                           font-semibold
                           text-[#173F35]"
                >

                    <i
                        data-lucide="calendar-range"
                        class="h-3 w-3"
                    ></i>

                    7 days

                </span>

            </div>


            <div class="h-[270px]">

                <canvas id="salesChart"></canvas>

            </div>

        </div>

    </section>


    {{-- ORDERS CHART --}}
    <section
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
                    Order Activity
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Number of orders across the period
                </p>

            </div>


            <div
                class="flex h-9 w-9
                       items-center justify-center
                       rounded-xl
                       bg-blue-50
                       text-blue-700"
            >
                <i
                    data-lucide="chart-line"
                    class="h-4 w-4"
                ></i>
            </div>

        </div>


        <div class="p-5">

            <div
                class="mb-5
                       flex items-end
                       justify-between gap-4"
            >

                <div>

                    <p
                        class="text-[10px]
                               font-medium
                               uppercase
                               tracking-[0.1em]
                               text-[#8D9A94]"
                    >
                        Total Orders
                    </p>

                    <p
                        class="mt-1
                               text-xl
                               font-semibold
                               tracking-[-0.03em]
                               text-[#24312C]"
                    >
                        {{ $totalOrders }}
                    </p>

                </div>


                <a
                    href="{{ route('seller.orders') }}"
                    class="inline-flex
                           items-center gap-1
                           text-[10px]
                           font-semibold
                           text-[#1F6F5B]
                           transition
                           hover:text-[#173F35]"
                >
                    View Orders

                    <i
                        data-lucide="arrow-up-right"
                        class="h-3.5 w-3.5"
                    ></i>
                </a>

            </div>


            <div class="h-[270px]">

                <canvas id="ordersChart"></canvas>

            </div>

        </div>

    </section>

</div>


{{-- =========================================================
    PRODUCT PERFORMANCE + ORDER DISTRIBUTION
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-1
           gap-6
           xl:grid-cols-[1.25fr_.75fr]"
>


    {{-- TOP PRODUCTS --}}
    <section
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
                    Top Products
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Best-performing products by units sold
                </p>

            </div>


            <a
                href="{{ route('seller.products') }}"
                class="inline-flex
                       items-center gap-1
                       text-[10px]
                       font-semibold
                       text-[#1F6F5B]
                       transition
                       hover:text-[#173F35]"
            >
                Products

                <i
                    data-lucide="arrow-up-right"
                    class="h-3.5 w-3.5"
                ></i>
            </a>

        </div>


        @if($topProducts->count())

            <div
                class="divide-y
                       divide-[#EDF1EF]"
            >

                @foreach($topProducts as $index => $product)

                    <div
                        class="flex items-center gap-4
                               px-5 py-4"
                    >

                        {{-- RANK --}}
                        <div
                            class="
                                flex h-8 w-8
                                shrink-0
                                items-center justify-center
                                rounded-xl
                                text-[11px]
                                font-semibold

                                {{ $index === 0
                                    ? 'bg-[#173F35] text-white'
                                    : 'bg-[#F1F4F2] text-[#68776F]' }}
                            "
                        >
                            {{ $index + 1 }}
                        </div>


                        {{-- IMAGE --}}
                        <div
                            class="flex h-11 w-11
                                   shrink-0
                                   items-center justify-center
                                   overflow-hidden
                                   rounded-xl
                                   border border-[#E4EAE6]
                                   bg-[#F1F4F2]"
                        >

                            @if(!empty($product['image']))

                                <img
                                    src="{{ $product['image'] }}"
                                    alt="{{ $product['name'] }}"
                                    class="h-full w-full object-cover"
                                >

                            @else

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4 text-[#8B9992]"
                                ></i>

                            @endif

                        </div>


                        {{-- NAME --}}
                        <div class="min-w-0 flex-1">

                            <p
                                class="truncate
                                       text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                {{ $product['name'] }}
                            </p>


                            <p
                                class="mt-1
                                       text-[10px]
                                       text-[#8A9791]"
                            >
                                ₱{{ number_format(
                                    $product['price'],
                                    2
                                ) }}
                            </p>

                        </div>


                        {{-- SOLD --}}
                        <div class="text-right">

                            <p
                                class="text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                {{ $product['sold'] }}
                            </p>

                            <p
                                class="mt-0.5
                                       text-[9px]
                                       uppercase
                                       tracking-[0.08em]
                                       text-[#98A39E]"
                            >
                                sold
                            </p>

                        </div>


                        {{-- STOCK --}}
                        <div
                            class="hidden
                                   min-w-[90px]
                                   text-right
                                   sm:block"
                        >

                            @if($product['stock'] <= 0)

                                <span
                                    class="inline-flex
                                           rounded-full
                                           border border-red-200
                                           bg-red-50
                                           px-2 py-1
                                           text-[9px]
                                           font-semibold
                                           text-red-700"
                                >
                                    Out of stock
                                </span>

                            @elseif($product['stock'] <= 10)

                                <span
                                    class="inline-flex
                                           rounded-full
                                           border border-amber-200
                                           bg-amber-50
                                           px-2 py-1
                                           text-[9px]
                                           font-semibold
                                           text-amber-700"
                                >
                                    {{ $product['stock'] }} left
                                </span>

                            @else

                                <span
                                    class="inline-flex
                                           rounded-full
                                           border border-[#DFE7E2]
                                           bg-[#F4F7F5]
                                           px-2 py-1
                                           text-[9px]
                                           font-semibold
                                           text-[#68776F]"
                                >
                                    {{ $product['stock'] }} stock
                                </span>

                            @endif

                        </div>

                    </div>

                @endforeach

            </div>


        @else

            <div
                class="px-6 py-14
                       text-center"
            >

                <div
                    class="mx-auto
                           flex h-12 w-12
                           items-center justify-center
                           rounded-2xl
                           bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >
                    <i
                        data-lucide="package-search"
                        class="h-5 w-5"
                    ></i>
                </div>


                <p
                    class="mt-4
                           text-sm
                           font-semibold
                           text-[#34483F]"
                >
                    No product data yet
                </p>


                <p
                    class="mt-1
                           text-xs
                           text-[#849089]"
                >
                    Product performance will appear here.
                </p>

            </div>

        @endif

    </section>


    {{-- ORDER STATUS --}}
    <section
        class="overflow-hidden
               rounded-2xl
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
                Order Distribution
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Current fulfillment statuses
            </p>

        </div>


        <div class="p-3">

            @foreach($statusCounts as $status => $count)

                @php

                    $statusIcon = match($status) {

                        'Placed' =>
                            'clock-3',

                        'Confirmed' =>
                            'circle-check',

                        'Preparing' =>
                            'package-open',

                        'Ready for Pickup' =>
                            'package-check',

                        'Picked Up' =>
                            'truck',

                        'In Transit' =>
                            'route',

                        'Out for Delivery' =>
                            'bike',

                        'Delivered' =>
                            'map-pin-check',

                        'Completed' =>
                            'badge-check',

                        'Cancelled' =>
                            'circle-x',

                        default =>
                            'circle',

                    };


                    $iconClass = match($status) {

                        'Placed' =>
                            'bg-amber-50 text-amber-700',

                        'Preparing' =>
                            'bg-violet-50 text-violet-700',

                        'Ready for Pickup' =>
                            'bg-indigo-50 text-indigo-700',

                        'Out for Delivery' =>
                            'bg-orange-50 text-orange-700',

                        'Delivered',
                        'Completed' =>
                            'bg-emerald-50 text-emerald-700',

                        'Cancelled' =>
                            'bg-red-50 text-red-600',

                        default =>
                            'bg-[#EEF5F1] text-[#1F6F5B]',

                    };

                @endphp


                <div
                    class="flex items-center
                           justify-between gap-4
                           rounded-xl
                           px-3 py-2.5"
                >

                    <div
                        class="flex min-w-0
                               items-center gap-3"
                    >

                        <div
                            class="
                                flex h-8 w-8
                                shrink-0
                                items-center justify-center
                                rounded-lg
                                {{ $iconClass }}
                            "
                        >
                            <i
                                data-lucide="{{ $statusIcon }}"
                                class="h-3.5 w-3.5"
                            ></i>
                        </div>


                        <span
                            class="truncate
                                   text-[11px]
                                   font-medium
                                   text-[#617169]"
                        >
                            {{ $status }}
                        </span>

                    </div>


                    <span
                        class="text-xs
                               font-semibold
                               text-[#24312C]"
                    >
                        {{ $count }}
                    </span>

                </div>

            @endforeach

        </div>

    </section>

</div>


{{-- =========================================================
    RECENT SALES
========================================================= --}}

<section
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
                Recent Sales
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Latest transactions from your store
            </p>

        </div>


        <a
            href="{{ route('seller.orders') }}"
            class="inline-flex
                   items-center gap-1
                   text-[10px]
                   font-semibold
                   text-[#1F6F5B]
                   transition
                   hover:text-[#173F35]"
        >
            View Orders

            <i
                data-lucide="arrow-right"
                class="h-3.5 w-3.5"
            ></i>
        </a>

    </div>


    @if($recentOrders->count())


        {{-- =================================================
            DESKTOP
        ================================================== --}}

        <div class="hidden overflow-x-auto md:block">

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
                            Customer
                        </th>

                        <th class="px-5 py-3 text-left">
                            Date
                        </th>

                        <th class="px-5 py-3 text-left">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right">
                            Amount
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach($recentOrders as $order)

                        @php

                            $orderStatus =
                                strtolower(
                                    $order['status']
                                    ?? 'placed'
                                );

                            $statusLabel =
                                $orderStatusLabels[$orderStatus]
                                ?? ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $orderStatus
                                    )
                                );

                            $statusClass =
                                $orderStatusClasses[$orderStatus]
                                ?? 'border-gray-200 bg-gray-100 text-gray-600';

                            $orderNumber =
                                $order['order_number']
                                ?? $order['id']
                                ?? 'N/A';

                            $buyerName =
                                $order['buyer_name']
                                ?? $order['customer_name']
                                ?? $order['shipping_address']['name']
                                ?? 'Buyer';

                            $amount =
                                (float) (
                                    $order['total']
                                    ?? $order['grand_total']
                                    ?? 0
                                );

                        @endphp


                        <tr
                            class="border-b
                                   border-[#F0F3F1]
                                   transition
                                   last:border-b-0
                                   hover:bg-[#FAFCFB]"
                        >

                            <td class="px-5 py-4">

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#24312C]"
                                >
                                    #{{ $orderNumber }}
                                </p>

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
                                       text-[11px]
                                       text-[#7B8982]"
                            >

                                @if(!empty($order['created_at']))

                                    {{ \Carbon\Carbon::parse(
                                        $order['created_at']
                                    )->format('M d, Y') }}

                                @else

                                    —

                                @endif

                            </td>


                            <td class="px-5 py-4">

                                <span
                                    class="
                                        inline-flex
                                        rounded-full
                                        border
                                        px-2.5 py-1
                                        text-[9px]
                                        font-semibold
                                        {{ $statusClass }}
                                    "
                                >
                                    {{ $statusLabel }}
                                </span>

                            </td>


                            <td
                                class="px-5 py-4
                                       text-right
                                       text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                ₱{{ number_format(
                                    $amount,
                                    2
                                ) }}
                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{-- =================================================
            MOBILE
        ================================================== --}}

        <div
            class="divide-y
                   divide-[#EDF1EF]
                   md:hidden"
        >

            @foreach($recentOrders as $order)

                @php

                    $orderStatus =
                        strtolower(
                            $order['status']
                            ?? 'placed'
                        );

                    $statusLabel =
                        $orderStatusLabels[$orderStatus]
                        ?? ucfirst(
                            str_replace(
                                '_',
                                ' ',
                                $orderStatus
                            )
                        );

                    $statusClass =
                        $orderStatusClasses[$orderStatus]
                        ?? 'border-gray-200 bg-gray-100 text-gray-600';

                    $orderNumber =
                        $order['order_number']
                        ?? $order['id']
                        ?? 'N/A';

                    $buyerName =
                        $order['buyer_name']
                        ?? $order['customer_name']
                        ?? $order['shipping_address']['name']
                        ?? 'Buyer';

                    $amount =
                        (float) (
                            $order['total']
                            ?? $order['grand_total']
                            ?? 0
                        );

                @endphp


                <article class="p-4">

                    <div
                        class="flex
                               items-start
                               justify-between gap-4"
                    >

                        <div class="min-w-0">

                            <p
                                class="text-xs
                                       font-semibold
                                       text-[#24312C]"
                            >
                                #{{ $orderNumber }}
                            </p>


                            <p
                                class="mt-1
                                       truncate
                                       text-[10px]
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
                               rounded-xl
                               bg-[#F7F9F8]
                               px-3 py-2.5"
                    >

                        <span
                            class="text-[10px]
                                   text-[#8A9791]"
                        >

                            @if(!empty($order['created_at']))

                                {{ \Carbon\Carbon::parse(
                                    $order['created_at']
                                )->format('M d, Y') }}

                            @else

                                Order total

                            @endif

                        </span>


                        <span
                            class="text-xs
                                   font-semibold
                                   text-[#24312C]"
                        >
                            ₱{{ number_format(
                                $amount,
                                2
                            ) }}
                        </span>

                    </div>

                </article>

            @endforeach

        </div>


    @else

        <div
            class="px-6 py-14
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-12 w-12
                       items-center justify-center
                       rounded-2xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="receipt"
                    class="h-5 w-5"
                ></i>

            </div>


            <p
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No sales yet
            </p>


            <p
                class="mx-auto mt-1
                       max-w-sm
                       text-xs
                       leading-5
                       text-[#849089]"
            >
                Sales information will appear here
                once customers begin placing orders.
            </p>

        </div>

    @endif

</section>


@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const chartLabels =
            @json($chartLabels);

        const salesData =
            @json($salesChart);

        const ordersData =
            @json($ordersChart);


        /* =====================================================
           CHART DEFAULTS
        ====================================================== */

        Chart.defaults.font.family =
            'Poppins, sans-serif';

        Chart.defaults.color =
            '#839189';


        /* =====================================================
           SALES CHART
        ====================================================== */

        const salesCanvas =
            document.getElementById(
                'salesChart'
            );


        if (salesCanvas) {

            new Chart(
                salesCanvas,
                {

                    type: 'line',

                    data: {

                        labels:
                            chartLabels,

                        datasets: [

                            {

                                label:
                                    'Sales',

                                data:
                                    salesData,

                                borderColor:
                                    '#1F6F5B',

                                backgroundColor:
                                    'rgba(31, 111, 91, 0.07)',

                                borderWidth:
                                    2.5,

                                pointRadius:
                                    3,

                                pointHoverRadius:
                                    5,

                                pointBackgroundColor:
                                    '#173F35',

                                pointBorderColor:
                                    '#FFFFFF',

                                pointBorderWidth:
                                    2,

                                fill:
                                    true,

                                tension:
                                    0.38,

                            }

                        ]

                    },


                    options: {

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,


                        interaction: {

                            intersect:
                                false,

                            mode:
                                'index',

                        },


                        plugins: {

                            legend: {
                                display: false
                            },


                            tooltip: {

                                backgroundColor:
                                    '#173F35',

                                titleColor:
                                    '#FFFFFF',

                                bodyColor:
                                    '#DDF3EC',

                                padding:
                                    12,

                                cornerRadius:
                                    10,

                                displayColors:
                                    false,


                                callbacks: {

                                    label:
                                        function (context) {

                                            return (
                                                '₱' +
                                                Number(
                                                    context.raw
                                                )
                                                .toLocaleString()
                                            );

                                        }

                                }

                            }

                        },


                        scales: {

                            x: {

                                grid: {
                                    display: false
                                },

                                border: {
                                    display: false
                                },

                                ticks: {

                                    color:
                                        '#95A19B',

                                    font: {
                                        size: 10
                                    }

                                }

                            },


                            y: {

                                beginAtZero:
                                    true,

                                border: {
                                    display: false
                                },

                                grid: {

                                    color:
                                        '#EEF2F0',

                                    drawTicks:
                                        false

                                },

                                ticks: {

                                    color:
                                        '#95A19B',

                                    padding:
                                        10,

                                    font: {
                                        size: 10
                                    },

                                    callback:
                                        function (value) {

                                            return (
                                                '₱' +
                                                Number(value)
                                                    .toLocaleString()
                                            );

                                        }

                                }

                            }

                        }

                    }

                }
            );

        }


        /* =====================================================
           ORDERS CHART
        ====================================================== */

        const ordersCanvas =
            document.getElementById(
                'ordersChart'
            );


        if (ordersCanvas) {

            new Chart(
                ordersCanvas,
                {

                    type: 'line',

                    data: {

                        labels:
                            chartLabels,

                        datasets: [

                            {

                                label:
                                    'Orders',

                                data:
                                    ordersData,

                                borderColor:
                                    '#467A69',

                                backgroundColor:
                                    'rgba(70, 122, 105, 0.06)',

                                borderWidth:
                                    2.5,

                                pointRadius:
                                    3,

                                pointHoverRadius:
                                    5,

                                pointBackgroundColor:
                                    '#467A69',

                                pointBorderColor:
                                    '#FFFFFF',

                                pointBorderWidth:
                                    2,

                                fill:
                                    true,

                                tension:
                                    0.38,

                            }

                        ]

                    },


                    options: {

                        responsive:
                            true,

                        maintainAspectRatio:
                            false,


                        interaction: {

                            intersect:
                                false,

                            mode:
                                'index',

                        },


                        plugins: {

                            legend: {
                                display: false
                            },


                            tooltip: {

                                backgroundColor:
                                    '#173F35',

                                titleColor:
                                    '#FFFFFF',

                                bodyColor:
                                    '#DDF3EC',

                                padding:
                                    12,

                                cornerRadius:
                                    10,

                                displayColors:
                                    false,


                                callbacks: {

                                    label:
                                        function (context) {

                                            return (
                                                context.raw +
                                                ' orders'
                                            );

                                        }

                                }

                            }

                        },


                        scales: {

                            x: {

                                grid: {
                                    display: false
                                },

                                border: {
                                    display: false
                                },

                                ticks: {

                                    color:
                                        '#95A19B',

                                    font: {
                                        size: 10
                                    }

                                }

                            },


                            y: {

                                beginAtZero:
                                    true,

                                border: {
                                    display: false
                                },

                                grid: {

                                    color:
                                        '#EEF2F0',

                                    drawTicks:
                                        false

                                },

                                ticks: {

                                    precision:
                                        0,

                                    color:
                                        '#95A19B',

                                    padding:
                                        10,

                                    font: {
                                        size: 10
                                    }

                                }

                            }

                        }

                    }

                }
            );

        }


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