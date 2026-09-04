@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- HEADER --}}
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">

            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                    <i data-lucide="store" class="w-4 h-4 text-[#1F6F5B]"></i>
                    <span>Seller Center</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    <span>Orders</span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-bold text-[#1F2937]">
                    Orders
                </h1>

                <p class="text-sm text-gray-500 mt-1">
                    Manage, confirm, and prepare your customer orders.
                </p>
            </div>

            <a
                href="{{ route('buyer.home') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:border-[#1F6F5B] hover:text-[#1F6F5B] transition"
            >
                <i data-lucide="store" class="w-4 h-4"></i>
                View Store
            </a>

        </div>
    </div>


    {{-- ALERTS --}}
    @if(session('success'))
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <i data-lucide="circle-check" class="w-5 h-5 shrink-0 mt-0.5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <i data-lucide="circle-alert" class="w-5 h-5 shrink-0 mt-0.5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif


    {{-- ORDER COUNTS --}}
    @php
        $allOrders = array_values($orders ?? []);

        $totalOrders = count($allOrders);

        $toConfirmOrders = count(array_filter($allOrders, function ($order) {
            return ($order['status'] ?? 'placed') === 'placed';
        }));

        $preparingOrders = count(array_filter($allOrders, function ($order) {
            return in_array(($order['status'] ?? ''), ['confirmed', 'preparing']);
        }));

        $readyOrders = count(array_filter($allOrders, function ($order) {
            return ($order['status'] ?? '') === 'ready_for_pickup';
        }));

        $completedOrders = count(array_filter($allOrders, function ($order) {
            return in_array(($order['status'] ?? ''), ['delivered', 'completed']);
        }));

        $cancelledOrders = count(array_filter($allOrders, function ($order) {
            return ($order['status'] ?? '') === 'cancelled';
        }));
    @endphp


    {{-- SUMMARY CARDS --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        {{-- TOTAL --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-[#DDF3EC] flex items-center justify-center">
                    <i data-lucide="shopping-bag" class="w-5 h-5 text-[#1F6F5B]"></i>
                </div>

                <span class="text-xs font-medium text-gray-400">
                    All Orders
                </span>

            </div>

            <p class="text-2xl font-bold text-[#1F2937]">
                {{ $totalOrders }}
            </p>

            <p class="text-sm text-gray-500 mt-1">
                Total orders
            </p>
        </div>


        {{-- TO CONFIRM --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                    <i data-lucide="clock-3" class="w-5 h-5 text-amber-600"></i>
                </div>

                <span class="text-xs font-medium text-gray-400">
                    To Confirm
                </span>

            </div>

            <p class="text-2xl font-bold text-[#1F2937]">
                {{ $toConfirmOrders }}
            </p>

            <p class="text-sm text-gray-500 mt-1">
                Need your action
            </p>
        </div>


        {{-- PREPARING --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i data-lucide="package" class="w-5 h-5 text-blue-600"></i>
                </div>

                <span class="text-xs font-medium text-gray-400">
                    Preparing
                </span>

            </div>

            <p class="text-2xl font-bold text-[#1F2937]">
                {{ $preparingOrders }}
            </p>

            <p class="text-sm text-gray-500 mt-1">
                Orders in preparation
            </p>
        </div>


        {{-- READY --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">

                <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
                    <i data-lucide="package-check" class="w-5 h-5 text-purple-600"></i>
                </div>

                <span class="text-xs font-medium text-gray-400">
                    Ready
                </span>

            </div>

            <p class="text-2xl font-bold text-[#1F2937]">
                {{ $readyOrders }}
            </p>

            <p class="text-sm text-gray-500 mt-1">
                Waiting for pickup
            </p>
        </div>

    </div>


    {{-- ORDERS CONTAINER --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">

        {{-- TOOLBAR --}}
        <div class="px-5 sm:px-6 py-5 border-b border-gray-100">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

                <div>
                    <h2 class="text-lg font-semibold text-[#1F2937]">
                        Customer Orders
                    </h2>

                    <p class="text-sm text-gray-500 mt-1">
                        Process orders from confirmation until pickup.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">

                    {{-- SEARCH --}}
                    <div class="relative">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                        ></i>

                        <input
                            type="text"
                            id="orderSearch"
                            placeholder="Search order..."
                            class="w-full sm:w-64 rounded-xl border border-gray-200 bg-gray-50 pl-10 pr-4 py-2.5 text-sm outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>

                    {{-- STATUS FILTER --}}
                    <select
                        id="statusFilter"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >
                        <option value="all">All Status</option>
                        <option value="placed">To Confirm</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="preparing">Preparing</option>
                        <option value="ready_for_pickup">Ready for Pickup</option>
                        <option value="picked_up">Picked Up</option>
                        <option value="at_sorting_center">At Sorting Center</option>
                        <option value="sorted">Sorted</option>
                        <option value="assigned_to_rider">Assigned to Rider</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="completed">Completed</option>
                        <option value="delivery_failed">Delivery Failed</option>
                        <option value="returned">Returned</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                </div>

            </div>


            {{-- STATUS TABS --}}
            <div class="mt-5 overflow-x-auto">
                <div class="flex items-center gap-2 min-w-max">

                    <button
                        type="button"
                        data-tab="all"
                        class="status-tab active rounded-full px-4 py-2 text-xs font-semibold bg-[#1F6F5B] text-white"
                    >
                        All
                    </button>

                    <button
                        type="button"
                        data-tab="placed"
                        class="status-tab rounded-full px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                    >
                        To Confirm
                    </button>

                    <button
                        type="button"
                        data-tab="preparing"
                        class="status-tab rounded-full px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                    >
                        Preparing
                    </button>

                    <button
                        type="button"
                        data-tab="ready_for_pickup"
                        class="status-tab rounded-full px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                    >
                        Ready for Pickup
                    </button>

                    <button
                        type="button"
                        data-tab="completed"
                        class="status-tab rounded-full px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                    >
                        Completed
                    </button>

                    <button
                        type="button"
                        data-tab="cancelled"
                        class="status-tab rounded-full px-4 py-2 text-xs font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                    >
                        Cancelled
                    </button>

                </div>
            </div>

        </div>


        @if(empty($orders))

            {{-- EMPTY STATE --}}
            <div class="px-6 py-20 text-center">

                <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-5">
                    <i data-lucide="package-open" class="w-7 h-7 text-gray-400"></i>
                </div>

                <h3 class="text-lg font-semibold text-[#1F2937]">
                    No orders yet
                </h3>

                <p class="text-sm text-gray-500 mt-2 max-w-md mx-auto">
                    Orders placed by customers will appear here.
                </p>

            </div>

        @else

            {{-- DESKTOP TABLE --}}
            <div class="hidden lg:block overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-50 border-b border-gray-100">

                        <tr>

                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Order
                            </th>

                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Customer / Items
                            </th>

                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Payment
                            </th>

                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Total
                            </th>

                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Status
                            </th>

                            <th class="text-right px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        @foreach($orders as $order)

                            @php

                                $status = $order['status'] ?? 'placed';

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
                                    'preparing' => 'package',
                                    'ready_for_pickup' => 'package-check',
                                    'picked_up' => 'truck',
                                    'at_sorting_center' => 'warehouse',
                                    'sorted' => 'layers',
                                    'assigned_to_rider' => 'user-check',
                                    'out_for_delivery' => 'bike',
                                    'delivered' => 'circle-check',
                                    'completed' => 'badge-check',
                                    'delivery_failed' => 'triangle-alert',
                                    'returned' => 'rotate-ccw',
                                    'cancelled' => 'circle-x',
                                ];

                                $statusClasses = [
                                    'placed' => 'bg-amber-50 text-amber-700',
                                    'confirmed' => 'bg-blue-50 text-blue-700',
                                    'preparing' => 'bg-indigo-50 text-indigo-700',
                                    'ready_for_pickup' => 'bg-purple-50 text-purple-700',
                                    'picked_up' => 'bg-cyan-50 text-cyan-700',
                                    'at_sorting_center' => 'bg-sky-50 text-sky-700',
                                    'sorted' => 'bg-teal-50 text-teal-700',
                                    'assigned_to_rider' => 'bg-violet-50 text-violet-700',
                                    'out_for_delivery' => 'bg-orange-50 text-orange-700',
                                    'delivered' => 'bg-green-50 text-green-700',
                                    'completed' => 'bg-emerald-50 text-emerald-700',
                                    'delivery_failed' => 'bg-red-50 text-red-700',
                                    'returned' => 'bg-rose-50 text-rose-700',
                                    'cancelled' => 'bg-red-50 text-red-700',
                                ];

                                $statusLabel = $statusLabels[$status] ?? 'To Confirm';
                                $statusIcon = $statusIcons[$status] ?? 'clock-3';
                                $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';

                            @endphp


                            <tr
                                class="order-row hover:bg-gray-50 transition"
                                data-order="{{ strtolower($order['id'] ?? '') }}"
                                data-status="{{ $status }}"
                            >

                                {{-- ORDER --}}
                                <td class="px-6 py-5 align-top">

                                    <p class="font-semibold text-sm text-[#1F2937]">
                                        #{{ $order['id'] ?? 'N/A' }}
                                    </p>

                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $order['created_at'] ?? '' }}
                                    </p>

                                </td>


                                {{-- CUSTOMER / ITEMS --}}
                                <td class="px-6 py-5 align-top">

                                    <div class="space-y-2">

                                        @foreach($order['items'] ?? [] as $item)

                                            <div class="flex items-center gap-3">

                                                <img
                                                    src="{{ $item['image'] ?? '' }}"
                                                    alt="{{ $item['name'] ?? 'Product' }}"
                                                    class="w-10 h-10 rounded-lg object-cover border border-gray-100"
                                                >

                                                <div class="min-w-0">

                                                    <p class="text-sm font-medium text-gray-800 truncate max-w-[220px]">
                                                        {{ $item['name'] ?? 'Product' }}
                                                    </p>

                                                    <p class="text-xs text-gray-500">
                                                        Qty: {{ $item['quantity'] ?? 1 }}
                                                    </p>

                                                </div>

                                            </div>

                                        @endforeach

                                    </div>

                                </td>


                                {{-- PAYMENT --}}
                                <td class="px-6 py-5 align-top">

                                    <p class="text-sm font-medium text-gray-800">
                                        {{ ($order['payment_method'] ?? '') === 'gcash'
                                            ? 'GCash'
                                            : 'Cash on Delivery'
                                        }}
                                    </p>

                                    @php
                                        $shippingLabels = [
                                            'jnt' => 'J&T Express',
                                            'flash' => 'Flash Express',
                                            'lbc' => 'LBC Express',
                                        ];
                                    @endphp

                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $shippingLabels[$order['shipping_method'] ?? ''] ?? strtoupper($order['shipping_method'] ?? '') }}
                                    </p>

                                </td>


                                {{-- TOTAL --}}
                                <td class="px-6 py-5 align-top">

                                    <p class="text-sm font-bold text-[#1F6F5B]">
                                        ₱{{ number_format($order['total'] ?? 0, 2) }}
                                    </p>

                                </td>


                                {{-- STATUS --}}
                                <td class="px-6 py-5 align-top">

                                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">

                                        <i
                                            data-lucide="{{ $statusIcon }}"
                                            class="w-3.5 h-3.5"
                                        ></i>

                                        {{ $statusLabel }}

                                    </span>

                                </td>


                                {{-- ACTION --}}
                                <td class="px-6 py-5 align-top text-right">

                                    @if($status === 'placed')

                                        <form
                                            action="{{ route('order.status.update', $order['id']) }}"
                                            method="POST"
                                            class="inline"
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="confirmed"
                                            >

                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-2.5 text-xs font-semibold text-white hover:bg-[#155244] transition"
                                            >
                                                <i data-lucide="circle-check" class="w-4 h-4"></i>
                                                Confirm Order
                                            </button>

                                        </form>


                                    @elseif($status === 'confirmed')

                                        <form
                                            action="{{ route('order.status.update', $order['id']) }}"
                                            method="POST"
                                            class="inline"
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="preparing"
                                            >

                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-2.5 text-xs font-semibold text-white hover:bg-[#155244] transition"
                                            >
                                                <i data-lucide="package" class="w-4 h-4"></i>
                                                Start Preparing
                                            </button>

                                        </form>


                                    @elseif($status === 'preparing')

                                        <form
                                            action="{{ route('order.status.update', $order['id']) }}"
                                            method="POST"
                                            class="inline"
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="ready_for_pickup"
                                            >

                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-2.5 text-xs font-semibold text-white hover:bg-[#155244] transition"
                                            >
                                                <i data-lucide="package-check" class="w-4 h-4"></i>
                                                Mark Ready for Pickup
                                            </button>

                                        </form>


                                    @elseif($status === 'ready_for_pickup')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-purple-600">
                                            <i data-lucide="clock-3" class="w-4 h-4"></i>
                                            Waiting for Pickup
                                        </span>


                                    @elseif(in_array($status, [
                                        'picked_up',
                                        'at_sorting_center',
                                        'sorted',
                                        'assigned_to_rider',
                                        'out_for_delivery'
                                    ]))

                                        <span class="inline-flex items-center gap-2 text-xs font-medium text-gray-500">
                                            <i data-lucide="truck" class="w-4 h-4"></i>
                                            Shipment in Progress
                                        </span>


                                    @elseif($status === 'delivered')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-green-600">
                                            <i data-lucide="circle-check" class="w-4 h-4"></i>
                                            Delivered
                                        </span>


                                    @elseif($status === 'completed')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-emerald-600">
                                            <i data-lucide="badge-check" class="w-4 h-4"></i>
                                            Completed
                                        </span>


                                    @elseif($status === 'delivery_failed')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-red-600">
                                            <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                                            Delivery Failed
                                        </span>


                                    @elseif($status === 'returned')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600">
                                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                            Returned
                                        </span>


                                    @elseif($status === 'cancelled')

                                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-red-500">
                                            <i data-lucide="circle-x" class="w-4 h-4"></i>
                                            Cancelled
                                        </span>

                                    @endif

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>


            {{-- MOBILE / TABLET --}}
            <div class="lg:hidden divide-y divide-gray-100">

                @foreach($orders as $order)

                    @php

                        $status = $order['status'] ?? 'placed';

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

                        $statusClasses = [
                            'placed' => 'bg-amber-50 text-amber-700',
                            'confirmed' => 'bg-blue-50 text-blue-700',
                            'preparing' => 'bg-indigo-50 text-indigo-700',
                            'ready_for_pickup' => 'bg-purple-50 text-purple-700',
                            'picked_up' => 'bg-cyan-50 text-cyan-700',
                            'at_sorting_center' => 'bg-sky-50 text-sky-700',
                            'sorted' => 'bg-teal-50 text-teal-700',
                            'assigned_to_rider' => 'bg-violet-50 text-violet-700',
                            'out_for_delivery' => 'bg-orange-50 text-orange-700',
                            'delivered' => 'bg-green-50 text-green-700',
                            'completed' => 'bg-emerald-50 text-emerald-700',
                            'delivery_failed' => 'bg-red-50 text-red-700',
                            'returned' => 'bg-rose-50 text-rose-700',
                            'cancelled' => 'bg-red-50 text-red-700',
                        ];

                        $statusLabel = $statusLabels[$status] ?? 'To Confirm';
                        $statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';

                    @endphp


                    <div
                        class="order-card p-5"
                        data-order="{{ strtolower($order['id'] ?? '') }}"
                        data-status="{{ $status }}"
                    >

                        {{-- TOP --}}
                        <div class="flex items-start justify-between gap-4 mb-5">

                            <div class="min-w-0">

                                <p class="font-semibold text-sm text-[#1F2937]">
                                    #{{ $order['id'] ?? 'N/A' }}
                                </p>

                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $order['created_at'] ?? '' }}
                                </p>

                            </div>

                            <span class="shrink-0 inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                        </div>


                        {{-- ITEMS --}}
                        <div class="space-y-3 mb-5">

                            @foreach($order['items'] ?? [] as $item)

                                <div class="flex items-center gap-3">

                                    <img
                                        src="{{ $item['image'] ?? '' }}"
                                        alt="{{ $item['name'] ?? 'Product' }}"
                                        class="w-12 h-12 rounded-xl object-cover border border-gray-100"
                                    >

                                    <div class="flex-1 min-w-0">

                                        <p class="text-sm font-medium text-gray-800 truncate">
                                            {{ $item['name'] ?? 'Product' }}
                                        </p>

                                        <p class="text-xs text-gray-500 mt-1">
                                            Qty: {{ $item['quantity'] ?? 1 }}
                                        </p>

                                    </div>

                                </div>

                            @endforeach

                        </div>


                        {{-- ORDER INFO --}}
                        <div class="grid grid-cols-2 gap-3 mb-5">

                            <div class="rounded-xl bg-gray-50 p-3">

                                <p class="text-xs text-gray-500">
                                    Payment
                                </p>

                                <p class="text-sm font-semibold text-gray-800 mt-1">
                                    {{ ($order['payment_method'] ?? '') === 'gcash'
                                        ? 'GCash'
                                        : 'COD'
                                    }}
                                </p>

                            </div>


                            <div class="rounded-xl bg-gray-50 p-3">

                                <p class="text-xs text-gray-500">
                                    Total
                                </p>

                                <p class="text-sm font-bold text-[#1F6F5B] mt-1">
                                    ₱{{ number_format($order['total'] ?? 0, 2) }}
                                </p>

                            </div>

                        </div>


                        {{-- ACTION --}}
                        @if($status === 'placed')

                            <form
                                action="{{ route('order.status.update', $order['id']) }}"
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
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white hover:bg-[#155244] transition"
                                >
                                    <i data-lucide="circle-check" class="w-4 h-4"></i>
                                    Confirm Order
                                </button>

                            </form>


                        @elseif($status === 'confirmed')

                            <form
                                action="{{ route('order.status.update', $order['id']) }}"
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
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white hover:bg-[#155244] transition"
                                >
                                    <i data-lucide="package" class="w-4 h-4"></i>
                                    Start Preparing
                                </button>

                            </form>


                        @elseif($status === 'preparing')

                            <form
                                action="{{ route('order.status.update', $order['id']) }}"
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
                                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white hover:bg-[#155244] transition"
                                >
                                    <i data-lucide="package-check" class="w-4 h-4"></i>
                                    Mark Ready for Pickup
                                </button>

                            </form>


                        @elseif($status === 'ready_for_pickup')

                            <div class="rounded-xl bg-purple-50 px-4 py-3 text-center text-sm font-medium text-purple-700">
                                Waiting for courier pickup
                            </div>


                        @elseif(in_array($status, [
                            'picked_up',
                            'at_sorting_center',
                            'sorted',
                            'assigned_to_rider',
                            'out_for_delivery'
                        ]))

                            <div class="rounded-xl bg-gray-50 px-4 py-3 text-center text-sm font-medium text-gray-600">
                                Shipment in progress
                            </div>


                        @elseif($status === 'delivered')

                            <div class="rounded-xl bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700">
                                Order delivered
                            </div>


                        @elseif($status === 'completed')

                            <div class="rounded-xl bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700">
                                Order completed
                            </div>


                        @elseif($status === 'delivery_failed')

                            <div class="rounded-xl bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700">
                                Delivery failed
                            </div>


                        @elseif($status === 'returned')

                            <div class="rounded-xl bg-rose-50 px-4 py-3 text-center text-sm font-medium text-rose-700">
                                Order returned
                            </div>


                        @elseif($status === 'cancelled')

                            <div class="rounded-xl bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700">
                                Order cancelled
                            </div>

                        @endif

                    </div>

                @endforeach

            </div>


            {{-- NO SEARCH RESULTS --}}
            <div
                id="noOrdersFound"
                class="hidden px-6 py-16 text-center"
            >
                <div class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                    <i data-lucide="search-x" class="w-6 h-6 text-gray-400"></i>
                </div>

                <h3 class="text-base font-semibold text-[#1F2937]">
                    No matching orders
                </h3>

                <p class="text-sm text-gray-500 mt-1">
                    Try another order number or status.
                </p>
            </div>

        @endif

    </div>

</div>


@push('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('statusFilter');
    const statusTabs = document.querySelectorAll('.status-tab');
    const orderRows = document.querySelectorAll('.order-row');
    const orderCards = document.querySelectorAll('.order-card');
    const noOrdersFound = document.getElementById('noOrdersFound');

    let activeTab = 'all';


    function filterOrders() {

        const searchValue = searchInput
            ? searchInput.value.toLowerCase().trim()
            : '';

        const selectedStatus = statusFilter
            ? statusFilter.value
            : 'all';

        let visibleCount = 0;


        [...orderRows, ...orderCards].forEach(function (order) {

            const orderNumber = order.dataset.order || '';
            const orderStatus = order.dataset.status || '';

            const matchesSearch =
                orderNumber.includes(searchValue);

            const filterStatus =
                activeTab !== 'all'
                    ? activeTab
                    : selectedStatus;

            const matchesStatus =
                filterStatus === 'all' ||
                orderStatus === filterStatus;

            const shouldShow =
                matchesSearch && matchesStatus;

            order.style.display = shouldShow ? '' : 'none';

            if (shouldShow) {
                visibleCount++;
            }

        });


        if (noOrdersFound) {

            noOrdersFound.classList.toggle(
                'hidden',
                visibleCount > 0
            );

        }

    }


    if (searchInput) {
        searchInput.addEventListener('input', filterOrders);
    }


    if (statusFilter) {

        statusFilter.addEventListener('change', function () {

            activeTab = this.value;

            statusTabs.forEach(function (tab) {

                const isActive =
                    tab.dataset.tab === activeTab;

                tab.classList.toggle(
                    'bg-[#1F6F5B]',
                    isActive
                );

                tab.classList.toggle(
                    'text-white',
                    isActive
                );

                tab.classList.toggle(
                    'bg-gray-100',
                    !isActive
                );

                tab.classList.toggle(
                    'text-gray-600',
                    !isActive
                );

            });

            filterOrders();

        });

    }


    statusTabs.forEach(function (tab) {

        tab.addEventListener('click', function () {

            activeTab = this.dataset.tab;

            if (statusFilter) {
                statusFilter.value = activeTab;
            }


            statusTabs.forEach(function (item) {

                const isActive =
                    item === tab;

                item.classList.toggle(
                    'bg-[#1F6F5B]',
                    isActive
                );

                item.classList.toggle(
                    'text-white',
                    isActive
                );

                item.classList.toggle(
                    'bg-gray-100',
                    !isActive
                );

                item.classList.toggle(
                    'text-gray-600',
                    !isActive
                );

            });

            filterOrders();

        });

    });


    filterOrders();

});
</script>

@endpush

@endsection