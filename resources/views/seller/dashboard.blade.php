@extends('layouts.app')

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | SELLER DASHBOARD DATA
    |--------------------------------------------------------------------------
    | Uses session data for the current prototype.
    | This can later be replaced with database queries.
    */

    $orders = session('orders', []);
    $products = session('seller_products', []);

    /*
    |--------------------------------------------------------------------------
    | ORDER COUNTS
    |--------------------------------------------------------------------------
    */

    $totalOrders = count($orders);

    $toConfirm = collect($orders)->where('status', 'placed')->count();

    $preparing = collect($orders)->whereIn('status', [
        'confirmed',
        'preparing'
    ])->count();

    $readyForPickup = collect($orders)->where(
        'status',
        'ready_for_pickup'
    )->count();

    $completedOrders = collect($orders)->whereIn('status', [
        'completed',
        'delivered'
    ])->count();

    $cancelledOrders = collect($orders)->where(
        'status',
        'cancelled'
    )->count();


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
            return (float) ($order['total'] ?? $order['grand_total'] ?? 0);
        });


    /*
    |--------------------------------------------------------------------------
    | PRODUCTS
    |--------------------------------------------------------------------------
    */

    $totalProducts = count($products);

    $lowStockProducts = collect($products)
        ->filter(function ($product) {
            return (int) ($product['stock'] ?? 0) <= 5;
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
    | STATUS STYLING
    |--------------------------------------------------------------------------
    */

    $statusClasses = [
        'placed' => 'bg-amber-50 text-amber-700 border-amber-200',
        'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
        'preparing' => 'bg-purple-50 text-purple-700 border-purple-200',
        'ready_for_pickup' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'picked_up' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
        'at_sorting_center' => 'bg-sky-50 text-sky-700 border-sky-200',
        'sorted' => 'bg-teal-50 text-teal-700 border-teal-200',
        'assigned_to_rider' => 'bg-violet-50 text-violet-700 border-violet-200',
        'out_for_delivery' => 'bg-orange-50 text-orange-700 border-orange-200',
        'delivered' => 'bg-green-50 text-green-700 border-green-200',
        'completed' => 'bg-green-50 text-green-700 border-green-200',
        'delivery_failed' => 'bg-red-50 text-red-700 border-red-200',
        'returned' => 'bg-rose-50 text-rose-700 border-rose-200',
        'cancelled' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];


    /*
    |--------------------------------------------------------------------------
    | HELPER FOR ROUTES
    |--------------------------------------------------------------------------
    */

    $ordersRoute = \Illuminate\Support\Facades\Route::has('seller.orders')
        ? route('seller.orders')
        : '#';

    $productsRoute = \Illuminate\Support\Facades\Route::has('seller.products')
        ? route('seller.products')
        : '#';

    $addProductRoute = \Illuminate\Support\Facades\Route::has('seller.products.create')
        ? route('seller.products.create')
        : $productsRoute;

@endphp


<div class="min-h-screen bg-[#F8FAF8]">

    {{-- ========================================================= --}}
    {{-- SELLER HEADER --}}
    {{-- ========================================================= --}}

    <div class="bg-white border-b border-gray-200">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="h-20 flex items-center justify-between gap-4">

                {{-- BRAND --}}
                <div class="flex items-center gap-4">

                    <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">
                        <i
                            data-lucide="store"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500">
                            Seller Centre
                        </p>

                        <h1 class="text-lg font-semibold text-gray-900">
                            Everyday Finds PH
                        </h1>
                    </div>

                </div>


                {{-- HEADER ACTIONS --}}
                <div class="flex items-center gap-3">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="hidden sm:flex items-center gap-2
                               px-4 py-2
                               rounded-lg
                               border border-gray-200
                               bg-white
                               text-sm font-medium text-gray-600
                               hover:bg-gray-50
                               transition"
                    >
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        View Store
                    </a>

                    <a
                        href="{{ $addProductRoute }}"
                        class="flex items-center gap-2
                               px-4 py-2
                               rounded-lg
                               bg-[#1F6F5B]
                               text-white
                               text-sm font-medium
                               hover:bg-[#155244]
                               transition"
                    >
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">
                            Add Product
                        </span>
                    </a>

                </div>

            </div>

        </div>

    </div>


    {{-- ========================================================= --}}
    {{-- SUCCESS / ERROR MESSAGE --}}
    {{-- ========================================================= --}}

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

        @if(session('success'))

            <div
                class="mb-6 flex items-start gap-3
                       rounded-xl border border-green-200
                       bg-green-50 px-4 py-3"
            >

                <i
                    data-lucide="check-circle"
                    class="w-5 h-5 text-green-600 shrink-0 mt-0.5"
                ></i>

                <p class="text-sm text-green-700">
                    {{ session('success') }}
                </p>

            </div>

        @endif


        @if(session('error'))

            <div
                class="mb-6 flex items-start gap-3
                       rounded-xl border border-red-200
                       bg-red-50 px-4 py-3"
            >

                <i
                    data-lucide="alert-circle"
                    class="w-5 h-5 text-red-600 shrink-0 mt-0.5"
                ></i>

                <p class="text-sm text-red-700">
                    {{ session('error') }}
                </p>

            </div>

        @endif

    </div>


    {{-- ========================================================= --}}
    {{-- MAIN CONTENT --}}
    {{-- ========================================================= --}}

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">


        {{-- ===================================================== --}}
        {{-- WELCOME --}}
        {{-- ===================================================== --}}

        <div class="mb-6">

            <h2 class="text-2xl font-semibold text-gray-900">
                Dashboard
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Monitor your store performance and manage your orders.
            </p>

        </div>


        {{-- ===================================================== --}}
        {{-- SUMMARY CARDS --}}
        {{-- ===================================================== --}}

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">


            {{-- TOTAL REVENUE --}}
            <div
                class="bg-white border border-gray-200
                       rounded-xl p-5"
            >

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Total Revenue
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            ₱{{ number_format($totalRevenue, 2) }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="wallet"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                </div>

                <p class="mt-3 text-xs text-gray-500">
                    From completed orders
                </p>

            </div>


            {{-- TOTAL ORDERS --}}
            <div
                class="bg-white border border-gray-200
                       rounded-xl p-5"
            >

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Total Orders
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            {{ $totalOrders }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">

                        <i
                            data-lucide="shopping-bag"
                            class="w-5 h-5 text-blue-600"
                        ></i>

                    </div>

                </div>

                <p class="mt-3 text-xs text-gray-500">
                    All orders received
                </p>

            </div>


            {{-- ORDERS TO PROCESS --}}
            <div
                class="bg-white border border-gray-200
                       rounded-xl p-5"
            >

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Orders to Process
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            {{ $toConfirm + $preparing + $readyForPickup }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">

                        <i
                            data-lucide="clipboard-list"
                            class="w-5 h-5 text-amber-600"
                        ></i>

                    </div>

                </div>

                <p class="mt-3 text-xs text-gray-500">
                    Orders requiring action
                </p>

            </div>


            {{-- TOTAL PRODUCTS --}}
            <div
                class="bg-white border border-gray-200
                       rounded-xl p-5"
            >

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Total Products
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-900">
                            {{ $totalProducts }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">

                        <i
                            data-lucide="package"
                            class="w-5 h-5 text-purple-600"
                        ></i>

                    </div>

                </div>

                <p class="mt-3 text-xs text-gray-500">
                    Products in your store
                </p>

            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- QUICK ACTIONS --}}
        {{-- ===================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl mb-6">

            <div class="px-5 py-4 border-b border-gray-100">

                <h3 class="text-sm font-semibold text-gray-900">
                    Quick Actions
                </h3>

            </div>


            <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">


                {{-- ADD PRODUCT --}}
                <a
                    href="{{ $addProductRoute }}"
                    class="flex items-center gap-4 px-5 py-5
                           hover:bg-gray-50 transition"
                >

                    <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="plus-circle"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-semibold text-gray-900">
                            Add Product
                        </p>

                        <p class="mt-0.5 text-xs text-gray-500">
                            List a new product
                        </p>

                    </div>

                </a>


                {{-- MANAGE ORDERS --}}
                <a
                    href="{{ $ordersRoute }}"
                    class="flex items-center gap-4 px-5 py-5
                           hover:bg-gray-50 transition"
                >

                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">

                        <i
                            data-lucide="clipboard-check"
                            class="w-5 h-5 text-blue-600"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-semibold text-gray-900">
                            Manage Orders
                        </p>

                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $toConfirm }} order(s) to confirm
                        </p>

                    </div>

                </a>


                {{-- MANAGE PRODUCTS --}}
                <a
                    href="{{ $productsRoute }}"
                    class="flex items-center gap-4 px-5 py-5
                           hover:bg-gray-50 transition"
                >

                    <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">

                        <i
                            data-lucide="boxes"
                            class="w-5 h-5 text-purple-600"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-semibold text-gray-900">
                            Manage Products
                        </p>

                        <p class="mt-0.5 text-xs text-gray-500">
                            Update your product listings
                        </p>

                    </div>

                </a>

            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- SALES OVERVIEW + ORDER STATUS --}}
        {{-- ===================================================== --}}

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">


            {{-- SALES OVERVIEW --}}
            <div
                class="lg:col-span-2
                       bg-white border border-gray-200
                       rounded-xl"
            >

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">

                    <div>

                        <h3 class="text-sm font-semibold text-gray-900">
                            Sales Overview
                        </h3>

                        <p class="mt-0.5 text-xs text-gray-500">
                            Your current store performance
                        </p>

                    </div>

                    <a
                        href="#"
                        class="text-xs font-medium text-[#1F6F5B] hover:text-[#155244]"
                    >
                        View Reports
                    </a>

                </div>


                <div class="p-5">

                    <div class="grid grid-cols-2 gap-4 mb-6">

                        <div class="rounded-lg bg-[#F8FAF8] p-4">

                            <p class="text-xs text-gray-500">
                                Completed Sales
                            </p>

                            <p class="mt-1 text-xl font-semibold text-gray-900">
                                ₱{{ number_format($totalRevenue, 2) }}
                            </p>

                        </div>

                        <div class="rounded-lg bg-[#F8FAF8] p-4">

                            <p class="text-xs text-gray-500">
                                Completed Orders
                            </p>

                            <p class="mt-1 text-xl font-semibold text-gray-900">
                                {{ $completedOrders }}
                            </p>

                        </div>

                    </div>


                    {{-- SIMPLE SALES VISUAL --}}
                    <div class="h-40 flex items-end gap-2 sm:gap-4">

                        @php
                            $salesBars = [35, 52, 42, 68, 55, 78, 64, 88, 72, 95, 82, 100];
                        @endphp

                        @foreach($salesBars as $index => $height)

                            <div class="flex-1 h-full flex items-end">

                                <div
                                    class="w-full rounded-t-md bg-[#DDF3EC] hover:bg-[#1F6F5B] transition"
                                    style="height: {{ $height }}%;"
                                    title="Sales period {{ $index + 1 }}"
                                ></div>

                            </div>

                        @endforeach

                    </div>

                    <div class="mt-3 flex justify-between text-[10px] text-gray-400">

                        <span>Earlier</span>

                        <span>Current</span>

                    </div>

                </div>

            </div>


            {{-- ORDER STATUS --}}
            <div
                class="bg-white border border-gray-200
                       rounded-xl"
            >

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Order Status
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Current order breakdown
                    </p>

                </div>


                <div class="p-5 space-y-4">


                    {{-- TO CONFIRM --}}
                    <a
                        href="{{ $ordersRoute }}"
                        class="flex items-center justify-between group"
                    >

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">

                                <i
                                    data-lucide="clock-3"
                                    class="w-4 h-4 text-amber-600"
                                ></i>

                            </div>

                            <span class="text-sm text-gray-700">
                                To Confirm
                            </span>

                        </div>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $toConfirm }}
                        </span>

                    </a>


                    {{-- PREPARING --}}
                    <a
                        href="{{ $ordersRoute }}"
                        class="flex items-center justify-between group"
                    >

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">

                                <i
                                    data-lucide="package-open"
                                    class="w-4 h-4 text-purple-600"
                                ></i>

                            </div>

                            <span class="text-sm text-gray-700">
                                Preparing
                            </span>

                        </div>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $preparing }}
                        </span>

                    </a>


                    {{-- READY FOR PICKUP --}}
                    <a
                        href="{{ $ordersRoute }}"
                        class="flex items-center justify-between group"
                    >

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">

                                <i
                                    data-lucide="package-check"
                                    class="w-4 h-4 text-indigo-600"
                                ></i>

                            </div>

                            <span class="text-sm text-gray-700">
                                Ready for Pickup
                            </span>

                        </div>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $readyForPickup }}
                        </span>

                    </a>


                    {{-- COMPLETED --}}
                    <a
                        href="{{ $ordersRoute }}"
                        class="flex items-center justify-between group"
                    >

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">

                                <i
                                    data-lucide="circle-check"
                                    class="w-4 h-4 text-green-600"
                                ></i>

                            </div>

                            <span class="text-sm text-gray-700">
                                Completed
                            </span>

                        </div>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $completedOrders }}
                        </span>

                    </a>


                    {{-- CANCELLED --}}
                    <a
                        href="{{ $ordersRoute }}"
                        class="flex items-center justify-between group"
                    >

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">

                                <i
                                    data-lucide="x-circle"
                                    class="w-4 h-4 text-gray-500"
                                ></i>

                            </div>

                            <span class="text-sm text-gray-700">
                                Cancelled
                            </span>

                        </div>

                        <span class="text-sm font-semibold text-gray-900">
                            {{ $cancelledOrders }}
                        </span>

                    </a>

                </div>

            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- RECENT ORDERS --}}
        {{-- ===================================================== --}}

        <div class="bg-white border border-gray-200 rounded-xl mb-6">

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">

                <div>

                    <h3 class="text-sm font-semibold text-gray-900">
                        Recent Orders
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Latest orders from your store
                    </p>

                </div>

                <a
                    href="{{ $ordersRoute }}"
                    class="flex items-center gap-1
                           text-xs font-medium
                           text-[#1F6F5B]
                           hover:text-[#155244]"
                >
                    View All
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>

            </div>


            @if($recentOrders->count())

                {{-- DESKTOP TABLE --}}
                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead>

                            <tr class="bg-gray-50 border-b border-gray-100">

                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">
                                    Order
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">
                                    Buyer
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">
                                    Date
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">
                                    Total
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500">
                                    Status
                                </th>

                            </tr>

                        </thead>

                        <tbody class="divide-y divide-gray-100">

                            @foreach($recentOrders as $orderId => $order)

                                @php
                                    $status = $order['status'] ?? 'placed';

                                    $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));

                                    $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-600 border-gray-200';

                                    $orderTotal = (float) ($order['total'] ?? $order['grand_total'] ?? 0);

                                    $buyerName = $order['buyer_name']
                                        ?? $order['customer_name']
                                        ?? 'Buyer';

                                    $orderDate = $order['created_at'] ?? null;
                                @endphp

                                <tr class="hover:bg-gray-50 transition">

                                    <td class="px-5 py-4">

                                        <span class="font-medium text-gray-900">
                                            #{{ $order['order_number'] ?? $orderId }}
                                        </span>

                                    </td>

                                    <td class="px-5 py-4 text-gray-600">
                                        {{ $buyerName }}
                                    </td>

                                    <td class="px-5 py-4 text-gray-500">

                                        @if($orderDate)
                                            {{ \Carbon\Carbon::parse($orderDate)->format('M d, Y') }}
                                        @else
                                            —
                                        @endif

                                    </td>

                                    <td class="px-5 py-4 font-medium text-gray-900">
                                        ₱{{ number_format($orderTotal, 2) }}
                                    </td>

                                    <td class="px-5 py-4">

                                        <span
                                            class="inline-flex items-center
                                                   px-2.5 py-1
                                                   rounded-full
                                                   border
                                                   text-[11px]
                                                   font-medium
                                                   {{ $statusClass }}"
                                        >
                                            {{ $statusLabel }}
                                        </span>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>


                {{-- MOBILE ORDERS --}}
                <div class="md:hidden divide-y divide-gray-100">

                    @foreach($recentOrders as $orderId => $order)

                        @php
                            $status = $order['status'] ?? 'placed';

                            $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));

                            $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-600 border-gray-200';

                            $orderTotal = (float) ($order['total'] ?? $order['grand_total'] ?? 0);

                            $buyerName = $order['buyer_name']
                                ?? $order['customer_name']
                                ?? 'Buyer';
                        @endphp

                        <div class="p-4">

                            <div class="flex items-start justify-between gap-3">

                                <div>

                                    <p class="text-sm font-semibold text-gray-900">
                                        #{{ $order['order_number'] ?? $orderId }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $buyerName }}
                                    </p>

                                </div>

                                <span
                                    class="inline-flex items-center
                                           px-2 py-1
                                           rounded-full
                                           border
                                           text-[10px]
                                           font-medium
                                           {{ $statusClass }}"
                                >
                                    {{ $statusLabel }}
                                </span>

                            </div>


                            <div class="mt-3 flex items-center justify-between">

                                <span class="text-xs text-gray-500">
                                    Order Total
                                </span>

                                <span class="text-sm font-semibold text-gray-900">
                                    ₱{{ number_format($orderTotal, 2) }}
                                </span>

                            </div>

                        </div>

                    @endforeach

                </div>

            @else

                {{-- EMPTY STATE --}}
                <div class="px-5 py-14 text-center">

                    <div class="mx-auto w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center">

                        <i
                            data-lucide="shopping-bag"
                            class="w-6 h-6 text-gray-400"
                        ></i>

                    </div>

                    <h4 class="mt-4 text-sm font-semibold text-gray-900">
                        No orders yet
                    </h4>

                    <p class="mt-1 text-xs text-gray-500">
                        Your recent orders will appear here.
                    </p>

                </div>

            @endif

        </div>


        {{-- ===================================================== --}}
        {{-- BOTTOM TWO COLUMNS --}}
        {{-- ===================================================== --}}

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">


            {{-- TOP SELLING PRODUCTS --}}
            <div class="bg-white border border-gray-200 rounded-xl">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">

                    <div>

                        <h3 class="text-sm font-semibold text-gray-900">
                            Top Selling Products
                        </h3>

                        <p class="mt-0.5 text-xs text-gray-500">
                            Your best performing products
                        </p>

                    </div>

                    <a
                        href="{{ $productsRoute }}"
                        class="text-xs font-medium text-[#1F6F5B] hover:text-[#155244]"
                    >
                        View Products
                    </a>

                </div>


                @if(collect($products)->count())

                    <div class="divide-y divide-gray-100">

                        @foreach(collect($products)->take(5) as $productIndex => $product)

                            <div class="flex items-center gap-4 px-5 py-4">

                                <div
                                    class="w-10 h-10 rounded-lg
                                           bg-gray-100
                                           flex items-center justify-center
                                           shrink-0"
                                >

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['name'] ?? 'Product' }}"
                                            class="w-full h-full object-cover rounded-lg"
                                        >

                                    @else

                                        <i
                                            data-lucide="package"
                                            class="w-5 h-5 text-gray-400"
                                        ></i>

                                    @endif

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $product['name'] ?? 'Product ' . ($productIndex + 1) }}
                                    </p>

                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ $product['sold'] ?? $product['sales'] ?? 0 }} sold
                                    </p>

                                </div>


                                <p class="text-sm font-semibold text-gray-900">
                                    ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                </p>

                            </div>

                        @endforeach

                    </div>

                @else

                    <div class="px-5 py-12 text-center">

                        <div class="mx-auto w-11 h-11 rounded-lg bg-gray-100 flex items-center justify-center">

                            <i
                                data-lucide="package"
                                class="w-5 h-5 text-gray-400"
                            ></i>

                        </div>

                        <p class="mt-3 text-sm font-medium text-gray-900">
                            No products yet
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Add products to start selling.
                        </p>

                    </div>

                @endif

            </div>


            {{-- LOW STOCK --}}
            <div class="bg-white border border-gray-200 rounded-xl">

                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">

                    <div>

                        <h3 class="text-sm font-semibold text-gray-900">
                            Low Stock Products
                        </h3>

                        <p class="mt-0.5 text-xs text-gray-500">
                            Products that need restocking
                        </p>

                    </div>

                    <span
                        class="px-2.5 py-1 rounded-full
                               bg-amber-50 text-amber-700
                               text-[11px] font-medium"
                    >
                        {{ $lowStockProducts }} items
                    </span>

                </div>


                @php
                    $lowStockList = collect($products)
                        ->filter(function ($product) {
                            return (int) ($product['stock'] ?? 0) <= 5;
                        })
                        ->take(5);
                @endphp


                @if($lowStockList->count())

                    <div class="divide-y divide-gray-100">

                        @foreach($lowStockList as $productIndex => $product)

                            @php
                                $stock = (int) ($product['stock'] ?? 0);
                            @endphp

                            <div class="flex items-center gap-4 px-5 py-4">

                                <div
                                    class="w-10 h-10 rounded-lg
                                           bg-gray-100
                                           flex items-center justify-center
                                           shrink-0"
                                >

                                    @if(!empty($product['image']))

                                        <img
                                            src="{{ $product['image'] }}"
                                            alt="{{ $product['name'] ?? 'Product' }}"
                                            class="w-full h-full object-cover rounded-lg"
                                        >

                                    @else

                                        <i
                                            data-lucide="package"
                                            class="w-5 h-5 text-gray-400"
                                        ></i>

                                    @endif

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        {{ $product['name'] ?? 'Product' }}
                                    </p>

                                    <p class="mt-0.5 text-xs text-gray-500">
                                        Stock remaining
                                    </p>

                                </div>


                                <span
                                    class="px-2.5 py-1 rounded-full
                                           border
                                           text-[11px]
                                           font-semibold
                                           {{ $stock <= 0
                                                ? 'bg-red-50 text-red-700 border-red-200'
                                                : 'bg-amber-50 text-amber-700 border-amber-200' }}"
                                >
                                    {{ $stock <= 0 ? 'Out of Stock' : $stock . ' left' }}
                                </span>

                            </div>

                        @endforeach

                    </div>

                @else

                    <div class="px-5 py-12 text-center">

                        <div class="mx-auto w-11 h-11 rounded-lg bg-green-50 flex items-center justify-center">

                            <i
                                data-lucide="package-check"
                                class="w-5 h-5 text-green-600"
                            ></i>

                        </div>

                        <p class="mt-3 text-sm font-medium text-gray-900">
                            Stock levels look good
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            No products currently need restocking.
                        </p>

                    </div>

                @endif

            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- SELLER FOOTER NOTE --}}
        {{-- ===================================================== --}}

        <div class="mt-8 text-center">

            <p class="text-xs text-gray-400">
                SUKI SHOP Seller Centre
                <span class="mx-1">•</span>
                Manage your store with ease
            </p>

        </div>

    </div>

</div>

@endsection