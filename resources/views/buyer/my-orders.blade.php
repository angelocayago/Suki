@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    {{-- PAGE HEADER --}}
    <div class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

            <div class="flex items-center gap-3">

                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#DDF3EC]">
                    <i
                        data-lucide="clipboard-list"
                        class="h-5 w-5 text-[#1F6F5B]"
                    ></i>
                </div>

                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        My Orders
                    </h1>

                    <p class="mt-1 text-sm text-gray-500">
                        Track and manage your SUKI orders.
                    </p>
                </div>

            </div>

        </div>
    </div>


    {{-- CONTENT --}}
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">


        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))

            <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">

                <i
                    data-lucide="check-circle"
                    class="mt-0.5 h-5 w-5 shrink-0"
                ></i>

                <span>
                    {{ session('success') }}
                </span>

            </div>

        @endif


        {{-- ERROR MESSAGE --}}
        @if(session('error'))

            <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                <i
                    data-lucide="circle-alert"
                    class="mt-0.5 h-5 w-5 shrink-0"
                ></i>

                <span>
                    {{ session('error') }}
                </span>

            </div>

        @endif



        @if(empty($orders))

            {{-- ========================================================= --}}
            {{-- EMPTY STATE --}}
            {{-- ========================================================= --}}

            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-16 text-center">

                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#DDF3EC]">

                    <i
                        data-lucide="package-open"
                        class="h-8 w-8 text-[#1F6F5B]"
                    ></i>

                </div>

                <h2 class="mt-5 text-lg font-semibold text-gray-900">
                    No orders yet
                </h2>

                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500">
                    You haven't placed any orders yet.
                    Start shopping and your purchases will appear here.
                </p>

                <a
                    href="{{ route('buyer.shop') }}"
                    class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#1F6F5B] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
                >

                    <i
                        data-lucide="shopping-bag"
                        class="h-4 w-4"
                    ></i>

                    Start Shopping

                </a>

            </div>


        @else


            {{-- ========================================================= --}}
            {{-- ORDERS --}}
            {{-- ========================================================= --}}

            <div class="space-y-5">

                @foreach($orders as $order)

                    @php

                        $status = $order['status'] ?? 'order_placed';

                        $statusData = match($status) {

                            'order_placed' => [
                                'label' => 'Order Placed',
                                'icon' => 'package',
                                'class' => 'bg-[#DDF3EC] text-[#1F6F5B]',
                            ],

                            'seller_preparing' => [
                                'label' => 'Preparing Order',
                                'icon' => 'package-check',
                                'class' => 'bg-[#DDF3EC] text-[#1F6F5B]',
                            ],

                            'ready_to_ship' => [
                                'label' => 'Ready to Ship',
                                'icon' => 'box',
                                'class' => 'bg-[#DDF3EC] text-[#1F6F5B]',
                            ],

                            'picked_up' => [
                                'label' => 'Picked Up',
                                'icon' => 'truck',
                                'class' => 'bg-blue-50 text-blue-600',
                            ],

                            'in_transit' => [
                                'label' => 'In Transit',
                                'icon' => 'truck',
                                'class' => 'bg-blue-50 text-blue-600',
                            ],

                            'out_for_delivery' => [
                                'label' => 'Out for Delivery',
                                'icon' => 'map-pin',
                                'class' => 'bg-orange-50 text-orange-600',
                            ],

                            'delivered' => [
                                'label' => 'Delivered',
                                'icon' => 'circle-check',
                                'class' => 'bg-green-50 text-green-600',
                            ],

                            'cancelled' => [
                                'label' => 'Cancelled',
                                'icon' => 'circle-x',
                                'class' => 'bg-red-50 text-red-600',
                            ],

                            default => [
                                'label' => 'Order Placed',
                                'icon' => 'package',
                                'class' => 'bg-[#DDF3EC] text-[#1F6F5B]',
                            ],

                        };

                    @endphp


                    {{-- ORDER CARD --}}
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">


                        {{-- ================================================= --}}
                        {{-- ORDER HEADER --}}
                        {{-- ================================================= --}}

                        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">

                            <div>

                                <div class="flex flex-wrap items-center gap-2">

                                    <span class="text-sm font-semibold text-gray-900">
                                        {{ $order['id'] }}
                                    </span>

                                    <span class="hidden text-gray-300 sm:inline">
                                        •
                                    </span>

                                    <span class="text-xs text-gray-500">
                                        {{ $order['created_at'] }}
                                    </span>

                                </div>

                                <p class="mt-1 text-xs text-gray-500">

                                    {{ count($order['items']) }}

                                    {{ count($order['items']) === 1 ? 'item' : 'items' }}

                                </p>

                            </div>


                            {{-- STATUS --}}
                            <span class="inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold {{ $statusData['class'] }}">

                                <i
                                    data-lucide="{{ $statusData['icon'] }}"
                                    class="h-3.5 w-3.5"
                                ></i>

                                {{ $statusData['label'] }}

                            </span>

                        </div>



                        {{-- ================================================= --}}
                        {{-- ORDER ITEMS --}}
                        {{-- ================================================= --}}

                        <div class="divide-y divide-gray-100">

                            @foreach($order['items'] as $index => $item)

                                <div class="flex gap-4 px-5 py-4 sm:px-6">

                                    <img
                                        src="{{ $item['image'] }}"
                                        alt="{{ $item['name'] }}"
                                        class="h-20 w-20 shrink-0 rounded-xl object-cover"
                                    >


                                    <div class="min-w-0 flex-1">

                                        <h3 class="line-clamp-2 text-sm font-medium text-gray-900">
                                            {{ $item['name'] }}
                                        </h3>

                                        <p class="mt-1 text-xs text-gray-500">
                                            Qty: {{ $item['quantity'] }}
                                        </p>

                                        <p class="mt-2 text-sm font-semibold text-gray-900">
                                            ₱{{ number_format($item['price'], 2) }}
                                        </p>

                                    </div>


                                    <div class="shrink-0 text-right">

                                        <p class="text-xs text-gray-400">
                                            Item Total
                                        </p>

                                        <p class="mt-1 text-sm font-semibold text-gray-900">
                                            ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                        </p>

                                    </div>

                                </div>

                            @endforeach

                        </div>



                        {{-- ================================================= --}}
                        {{-- ORDER FOOTER --}}
                        {{-- ================================================= --}}

                        <div class="border-t border-gray-100 bg-gray-50/60 px-5 py-4 sm:px-6">

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">


                                {{-- TOTAL --}}
                                <div>

                                    <p class="text-xs text-gray-500">
                                        Total Order
                                    </p>

                                    <p class="mt-1 text-xl font-bold text-[#1F6F5B]">
                                        ₱{{ number_format($order['total'], 2) }}
                                    </p>

                                </div>


                                {{-- ACTIONS --}}
                                <div class="flex flex-wrap gap-2">


                                    {{-- CANCEL --}}
                                    @if($status === 'order_placed')

                                        <form
                                            action="{{ route('buyer.order.cancel', $order['id']) }}"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to cancel this order?');"
                                        >

                                            @csrf

                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                            >

                                                <i
                                                    data-lucide="x"
                                                    class="h-4 w-4"
                                                ></i>

                                                Cancel Order

                                            </button>

                                        </form>

                                    @endif


                                    {{-- VIEW ORDER --}}
                                    <a
                                        href="{{ route('buyer.order-details', $order['id']) }}"
                                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#155244]"
                                    >

                                        <i
                                            data-lucide="eye"
                                            class="h-4 w-4"
                                        ></i>

                                        View Order

                                    </a>

                                </div>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        @endif

    </div>

</div>

@endsection