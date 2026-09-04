@extends('layouts.app')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | SELLER REPORTS DATA
    |--------------------------------------------------------------------------
    | Front-end prototype:
    | Uses seller orders/products stored in session.
    */

    $products = session('seller_products', []);
    $orders = session('orders', []);

    $sellerOrders = collect($orders);

    $totalOrders = $sellerOrders->count();

    $completedOrders = $sellerOrders->filter(function ($order) {
        return in_array($order['status'] ?? '', ['delivered', 'completed']);
    });

    $totalSales = $completedOrders->sum(function ($order) {
        return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
    });

    $allOrderSales = $sellerOrders->sum(function ($order) {
        return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
    });

    $productsSold = $completedOrders->sum(function ($order) {
        return collect($order['items'] ?? [])->sum(function ($item) {
            return (int) ($item['quantity'] ?? 0);
        });
    });

    $netEarnings = $totalSales * 0.90;

    $toShip = $sellerOrders->filter(function ($order) {
        return in_array($order['status'] ?? '', [
            'confirmed',
            'preparing',
            'ready_for_pickup'
        ]);
    })->count();

    $cancelledOrders = $sellerOrders->filter(function ($order) {
        return ($order['status'] ?? '') === 'cancelled';
    })->count();

    $deliveryFailed = $sellerOrders->filter(function ($order) {
        return ($order['status'] ?? '') === 'delivery_failed';
    })->count();


    /*
    |--------------------------------------------------------------------------
    | TOP PRODUCTS
    |--------------------------------------------------------------------------
    */

    $topProducts = collect($products)
        ->map(function ($product) {

            return [
                'name' => $product['name'] ?? 'Unnamed Product',
                'sold' => (int) ($product['sold'] ?? 0),
                'stock' => (int) ($product['stock'] ?? 0),
                'price' => (float) ($product['price'] ?? 0),
            ];

        })
        ->sortByDesc('sold')
        ->take(5);


    /*
    |--------------------------------------------------------------------------
    | RECENT SALES
    |--------------------------------------------------------------------------
    */

    $recentOrders = $sellerOrders
        ->sortByDesc(function ($order) {
            return $order['created_at'] ?? '';
        })
        ->take(6);


    /*
    |--------------------------------------------------------------------------
    | ORDER STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $statusCounts = [
        'Placed' => $sellerOrders->where('status', 'placed')->count(),
        'Confirmed' => $sellerOrders->where('status', 'confirmed')->count(),
        'Preparing' => $sellerOrders->where('status', 'preparing')->count(),
        'Ready for Pickup' => $sellerOrders->where('status', 'ready_for_pickup')->count(),
        'Picked Up' => $sellerOrders->where('status', 'picked_up')->count(),
        'In Transit' => $sellerOrders->whereIn('status', [
            'at_sorting_center',
            'sorted',
            'assigned_to_rider'
        ])->count(),
        'Out for Delivery' => $sellerOrders->where('status', 'out_for_delivery')->count(),
        'Delivered' => $sellerOrders->where('status', 'delivered')->count(),
        'Completed' => $sellerOrders->where('status', 'completed')->count(),
        'Cancelled' => $sellerOrders->where('status', 'cancelled')->count(),
    ];


    /*
    |--------------------------------------------------------------------------
    | CHART DATA
    |--------------------------------------------------------------------------
    | Demo values are used when the prototype has no orders yet.
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

@endphp


<div class="min-h-screen bg-[#F8FAF8]">

    {{-- =====================================================
         SELLER HEADER
    ====================================================== --}}
    <div class="bg-white border-b border-gray-200">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="h-16 flex items-center justify-between gap-4">

                <div class="flex items-center gap-3 min-w-0">

                    <div class="w-10 h-10 rounded-xl bg-[#DDF3EC] flex items-center justify-center shrink-0">

                        <i
                            data-lucide="store"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                    <div class="min-w-0">

                        <p class="text-xs text-gray-400">
                            SELLER CENTRE
                        </p>

                        <h1 class="text-sm sm:text-base font-semibold text-gray-900 truncate">
                            Everyday Finds PH
                        </h1>

                    </div>

                </div>


                <a
                    href="{{ route('buyer.home') }}"
                    class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm font-medium text-gray-700 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition shrink-0"
                >

                    <i
                        data-lucide="external-link"
                        class="w-4 h-4"
                    ></i>

                    <span class="hidden sm:inline">
                        View Store
                    </span>

                </a>

            </div>

        </div>

    </div>



    {{-- =====================================================
         MAIN CONTENT
    ====================================================== --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">


        {{-- =================================================
             BREADCRUMB
        ================================================== --}}
        <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500 mb-5">

            <a
                href="{{ route('seller.dashboard') }}"
                class="hover:text-[#1F6F5B] transition"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="w-4 h-4 text-gray-300"
            ></i>

            <span class="text-gray-900 font-medium">
                Reports & Analytics
            </span>

        </div>



        {{-- =================================================
             PAGE HEADER
        ================================================== --}}
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">

            <div>

                <p class="text-sm font-semibold text-[#1F6F5B]">
                    BUSINESS PERFORMANCE
                </p>

                <h2 class="mt-1 text-2xl sm:text-3xl font-bold text-gray-900">
                    Reports & Analytics
                </h2>

                <p class="mt-2 text-sm text-gray-500">
                    Track your sales, orders, products, and overall shop performance.
                </p>

            </div>


            {{-- DATE RANGE --}}
            <div class="flex items-center gap-2">

                <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-white text-xs sm:text-sm text-gray-600">

                    <i
                        data-lucide="calendar-days"
                        class="w-4 h-4 text-gray-400"
                    ></i>

                    Last 7 Days

                </div>

            </div>

        </div>



        {{-- =================================================
             STAT CARDS
        ================================================== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">


            {{-- TOTAL SALES --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-start justify-between gap-3">

                    <div>

                        <p class="text-xs sm:text-sm text-gray-500">
                            Total Sales
                        </p>

                        <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900">
                            ₱{{ number_format($totalSales, 2) }}
                        </p>

                        <p class="mt-1 text-[11px] sm:text-xs text-gray-400">
                            Completed orders
                        </p>

                    </div>

                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-[#DDF3EC] flex items-center justify-center shrink-0">

                        <i
                            data-lucide="banknote"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- TOTAL ORDERS --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-start justify-between gap-3">

                    <div>

                        <p class="text-xs sm:text-sm text-gray-500">
                            Total Orders
                        </p>

                        <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900">
                            {{ $totalOrders }}
                        </p>

                        <p class="mt-1 text-[11px] sm:text-xs text-gray-400">
                            All order statuses
                        </p>

                    </div>

                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">

                        <i
                            data-lucide="shopping-bag"
                            class="w-5 h-5 text-blue-600"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- PRODUCTS SOLD --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-start justify-between gap-3">

                    <div>

                        <p class="text-xs sm:text-sm text-gray-500">
                            Products Sold
                        </p>

                        <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900">
                            {{ $productsSold }}
                        </p>

                        <p class="mt-1 text-[11px] sm:text-xs text-gray-400">
                            Completed purchases
                        </p>

                    </div>

                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">

                        <i
                            data-lucide="package-check"
                            class="w-5 h-5 text-purple-600"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- NET EARNINGS --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-start justify-between gap-3">

                    <div>

                        <p class="text-xs sm:text-sm text-gray-500">
                            Net Earnings
                        </p>

                        <p class="mt-2 text-xl sm:text-2xl font-bold text-gray-900">
                            ₱{{ number_format($netEarnings, 2) }}
                        </p>

                        <p class="mt-1 text-[11px] sm:text-xs text-gray-400">
                            After 10% platform fee
                        </p>

                    </div>

                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">

                        <i
                            data-lucide="wallet"
                            class="w-5 h-5 text-amber-600"
                        ></i>

                    </div>

                </div>

            </div>

        </div>



        {{-- =================================================
             LINE CHARTS
        ================================================== --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-6">


            {{-- SALES OVERVIEW --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-center justify-between mb-5">

                    <div>

                        <h3 class="text-base font-semibold text-gray-900">
                            Sales Overview
                        </h3>

                        <p class="mt-1 text-xs text-gray-500">
                            Revenue performance for the last 7 days
                        </p>

                    </div>

                    <div class="w-9 h-9 rounded-lg bg-[#DDF3EC] flex items-center justify-center">

                        <i
                            data-lucide="trending-up"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                </div>

                <div class="relative h-64 sm:h-72">

                    <canvas id="salesChart"></canvas>

                </div>

            </div>



            {{-- ORDERS OVERVIEW --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">

                <div class="flex items-center justify-between mb-5">

                    <div>

                        <h3 class="text-base font-semibold text-gray-900">
                            Orders Overview
                        </h3>

                        <p class="mt-1 text-xs text-gray-500">
                            Number of orders received per day
                        </p>

                    </div>

                    <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">

                        <i
                            data-lucide="chart-line"
                            class="w-5 h-5 text-blue-600"
                        ></i>

                    </div>

                </div>

                <div class="relative h-64 sm:h-72">

                    <canvas id="ordersChart"></canvas>

                </div>

            </div>

        </div>



        {{-- =================================================
             LOWER ANALYTICS
        ================================================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">


            {{-- =================================================
                 TOP SELLING PRODUCTS
            ================================================== --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">

                <div class="px-4 sm:px-5 py-4 border-b border-gray-100 flex items-center justify-between">

                    <div>

                        <h3 class="text-base font-semibold text-gray-900">
                            Top Selling Products
                        </h3>

                        <p class="mt-1 text-xs text-gray-500">
                            Your best performing products
                        </p>

                    </div>

                    <a
                        href="{{ route('seller.products') }}"
                        class="text-xs sm:text-sm font-medium text-[#1F6F5B] hover:underline"
                    >
                        View Products
                    </a>

                </div>


                @if($topProducts->count())

                    <div class="divide-y divide-gray-100">

                        @foreach($topProducts as $index => $product)

                            <div class="px-4 sm:px-5 py-4 flex items-center gap-3 sm:gap-4">

                                {{-- RANK --}}
                                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">

                                    <span class="text-xs sm:text-sm font-semibold text-gray-500">
                                        {{ $index + 1 }}
                                    </span>

                                </div>


                                {{-- PRODUCT --}}
                                <div class="flex-1 min-w-0">

                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $product['name'] }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-400">
                                        ₱{{ number_format($product['price'], 2) }}
                                    </p>

                                </div>


                                {{-- SOLD --}}
                                <div class="text-right shrink-0">

                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $product['sold'] }}
                                    </p>

                                    <p class="text-[11px] text-gray-400">
                                        sold
                                    </p>

                                </div>

                            </div>

                        @endforeach

                    </div>

                @else

                    <div class="px-5 py-12 text-center">

                        <div class="w-12 h-12 rounded-xl bg-gray-50 mx-auto flex items-center justify-center">

                            <i
                                data-lucide="package"
                                class="w-6 h-6 text-gray-300"
                            ></i>

                        </div>

                        <p class="mt-3 text-sm font-medium text-gray-700">
                            No product data yet
                        </p>

                        <p class="mt-1 text-xs text-gray-400">
                            Add products and start selling to see your analytics.
                        </p>

                    </div>

                @endif

            </div>



            {{-- =================================================
                 ORDER STATUS
            ================================================== --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                <div class="px-4 sm:px-5 py-4 border-b border-gray-100">

                    <h3 class="text-base font-semibold text-gray-900">
                        Order Status
                    </h3>

                    <p class="mt-1 text-xs text-gray-500">
                        Current order distribution
                    </p>

                </div>


                <div class="p-4 sm:p-5 space-y-3">

                    @foreach($statusCounts as $status => $count)

                        @php

                            $statusClass = match($status) {

                                'Placed' => 'bg-gray-100 text-gray-600',

                                'Confirmed' => 'bg-blue-50 text-blue-600',

                                'Preparing' => 'bg-amber-50 text-amber-600',

                                'Ready for Pickup' => 'bg-orange-50 text-orange-600',

                                'Picked Up' => 'bg-indigo-50 text-indigo-600',

                                'In Transit' => 'bg-purple-50 text-purple-600',

                                'Out for Delivery' => 'bg-cyan-50 text-cyan-600',

                                'Delivered' => 'bg-green-50 text-green-600',

                                'Completed' => 'bg-[#DDF3EC] text-[#1F6F5B]',

                                'Cancelled' => 'bg-red-50 text-red-600',

                                default => 'bg-gray-100 text-gray-600'

                            };

                        @endphp

                        <div class="flex items-center justify-between gap-3">

                            <div class="flex items-center gap-2 min-w-0">

                                <span class="w-2 h-2 rounded-full shrink-0 {{ str_contains($statusClass, 'bg-red') ? 'bg-red-500' : 'bg-[#1F6F5B]' }}"></span>

                                <span class="text-xs sm:text-sm text-gray-600 truncate">
                                    {{ $status }}
                                </span>

                            </div>

                            <span class="text-sm font-semibold text-gray-900 shrink-0">
                                {{ $count }}
                            </span>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>



        {{-- =================================================
             RECENT SALES
        ================================================== --}}
        <div class="mt-5 bg-white rounded-xl border border-gray-200 overflow-hidden">

            <div class="px-4 sm:px-5 py-4 border-b border-gray-100 flex items-center justify-between">

                <div>

                    <h3 class="text-base font-semibold text-gray-900">
                        Recent Sales
                    </h3>

                    <p class="mt-1 text-xs text-gray-500">
                        Latest orders from your shop
                    </p>

                </div>

                <a
                    href="{{ route('seller.orders') }}"
                    class="text-xs sm:text-sm font-medium text-[#1F6F5B] hover:underline"
                >
                    View Orders
                </a>

            </div>


            @if($recentOrders->count())

                {{-- DESKTOP TABLE --}}
                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full">

                        <thead>

                            <tr class="bg-gray-50 border-b border-gray-100">

                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                    Order
                                </th>

                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                    Buyer
                                </th>

                                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                    Status
                                </th>

                                <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">
                                    Amount
                                </th>

                            </tr>

                        </thead>


                        <tbody class="divide-y divide-gray-100">

                            @foreach($recentOrders as $order)

                                @php

                                    $orderStatus = $order['status'] ?? 'placed';

                                    $statusLabel = match($orderStatus) {

                                        'placed' => 'Placed',
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

                                        default => ucfirst(str_replace('_', ' ', $orderStatus))

                                    };

                                    $statusStyle = match($orderStatus) {

                                        'completed',
                                        'delivered' => 'bg-green-50 text-green-700',

                                        'cancelled',
                                        'delivery_failed',
                                        'returned' => 'bg-red-50 text-red-700',

                                        'ready_for_pickup',
                                        'preparing' => 'bg-amber-50 text-amber-700',

                                        default => 'bg-blue-50 text-blue-700'

                                    };

                                @endphp


                                <tr class="hover:bg-gray-50 transition">

                                    <td class="px-5 py-4">

                                        <p class="text-sm font-medium text-gray-900">
                                            #{{ $order['order_number'] ?? $order['id'] ?? 'N/A' }}
                                        </p>

                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ $order['created_at'] ?? '' }}
                                        </p>

                                    </td>


                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        {{ $order['buyer_name'] ?? $order['customer_name'] ?? 'Buyer' }}
                                    </td>


                                    <td class="px-5 py-4">

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $statusStyle }}">
                                            {{ $statusLabel }}
                                        </span>

                                    </td>


                                    <td class="px-5 py-4 text-right">

                                        <span class="text-sm font-semibold text-gray-900">
                                            ₱{{ number_format((float) ($order['total'] ?? $order['grand_total'] ?? 0), 2) }}
                                        </span>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- MOBILE CARDS --}}
                <div class="md:hidden divide-y divide-gray-100">

                    @foreach($recentOrders as $order)

                        @php

                            $orderStatus = $order['status'] ?? 'placed';

                            $statusLabel = match($orderStatus) {

                                'placed' => 'Placed',
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

                                default => ucfirst(str_replace('_', ' ', $orderStatus))

                            };

                        @endphp

                        <div class="p-4">

                            <div class="flex items-start justify-between gap-3">

                                <div class="min-w-0">

                                    <p class="text-sm font-semibold text-gray-900">
                                        #{{ $order['order_number'] ?? $order['id'] ?? 'N/A' }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ $order['created_at'] ?? '' }}
                                    </p>

                                </div>

                                <p class="text-sm font-semibold text-gray-900 shrink-0">
                                    ₱{{ number_format((float) ($order['total'] ?? $order['grand_total'] ?? 0), 2) }}
                                </p>

                            </div>


                            <div class="mt-3 flex items-center justify-between gap-3">

                                <span class="text-xs text-gray-500">
                                    {{ $order['buyer_name'] ?? $order['customer_name'] ?? 'Buyer' }}
                                </span>

                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600">
                                    {{ $statusLabel }}
                                </span>

                            </div>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="px-5 py-12 text-center">

                    <div class="w-12 h-12 rounded-xl bg-gray-50 mx-auto flex items-center justify-center">

                        <i
                            data-lucide="receipt"
                            class="w-6 h-6 text-gray-300"
                        ></i>

                    </div>

                    <p class="mt-3 text-sm font-medium text-gray-700">
                        No sales yet
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Your recent sales will appear here once customers place orders.
                    </p>

                </div>

            @endif

        </div>

    </main>

</div>



{{-- =====================================================
     CHART.JS
====================================================== --}}
@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const chartLabels = @json($chartLabels);

    const salesData = @json($salesChart);

    const ordersData = @json($ordersChart);


    /*
    |--------------------------------------------------------------------------
    | SALES LINE CHART
    |--------------------------------------------------------------------------
    */

    const salesCanvas = document.getElementById('salesChart');

    if (salesCanvas) {

        new Chart(salesCanvas, {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [{

                    label: 'Sales',

                    data: salesData,

                    borderColor: '#1F6F5B',

                    backgroundColor: 'rgba(31, 111, 91, 0.08)',

                    borderWidth: 2.5,

                    pointRadius: 4,

                    pointHoverRadius: 6,

                    pointBackgroundColor: '#1F6F5B',

                    pointBorderWidth: 0,

                    fill: true,

                    tension: 0.4

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {

                        backgroundColor: '#1F2937',

                        padding: 10,

                        callbacks: {

                            label: function (context) {

                                return ' ₱' + Number(context.raw).toLocaleString();

                            }

                        }

                    }

                },

                scales: {

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {
                            color: '#9CA3AF',
                            font: {
                                size: 11
                            }
                        }

                    },

                    y: {

                        beginAtZero: true,

                        grid: {
                            color: '#F3F4F6'
                        },

                        ticks: {

                            color: '#9CA3AF',

                            font: {
                                size: 11
                            },

                            callback: function (value) {

                                return '₱' + Number(value).toLocaleString();

                            }

                        }

                    }

                }

            }

        });

    }



    /*
    |--------------------------------------------------------------------------
    | ORDERS LINE CHART
    |--------------------------------------------------------------------------
    */

    const ordersCanvas = document.getElementById('ordersChart');

    if (ordersCanvas) {

        new Chart(ordersCanvas, {

            type: 'line',

            data: {

                labels: chartLabels,

                datasets: [{

                    label: 'Orders',

                    data: ordersData,

                    borderColor: '#2563EB',

                    backgroundColor: 'rgba(37, 99, 235, 0.08)',

                    borderWidth: 2.5,

                    pointRadius: 4,

                    pointHoverRadius: 6,

                    pointBackgroundColor: '#2563EB',

                    pointBorderWidth: 0,

                    fill: true,

                    tension: 0.4

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: 'index'
                },

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {

                        backgroundColor: '#1F2937',

                        padding: 10,

                        callbacks: {

                            label: function (context) {

                                return ' ' + context.raw + ' orders';

                            }

                        }

                    }

                },

                scales: {

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {

                            color: '#9CA3AF',

                            font: {
                                size: 11
                            }

                        }

                    },

                    y: {

                        beginAtZero: true,

                        ticks: {

                            precision: 0,

                            color: '#9CA3AF',

                            font: {
                                size: 11
                            }

                        },

                        grid: {
                            color: '#F3F4F6'
                        }

                    }

                }

            }

        });

    }

});

</script>

@endpush

@endsection