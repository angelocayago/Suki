@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <div class="mx-auto max-w-5xl px-4 py-8">

        {{-- BACK --}}
        <a
            href="{{ route('buyer.my-orders') }}"
            class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition hover:text-[#1F6F5B]"
        >
            <i data-lucide="arrow-left" class="h-4 w-4"></i>
            Back to My Orders
        </a>


        {{-- SUCCESS --}}
        @if(session('success'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">

                <i data-lucide="check-circle" class="h-5 w-5 shrink-0"></i>

                {{ session('success') }}

            </div>
        @endif


        {{-- ERROR --}}
        @if(session('error'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                <i data-lucide="alert-circle" class="h-5 w-5 shrink-0"></i>

                {{ session('error') }}

            </div>
        @endif


        {{-- PAGE TITLE --}}
        <div class="mb-6">

            <h1 class="text-2xl font-bold text-gray-900">
                Order Details
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Order #{{ $order['id'] }}
            </p>

        </div>


        {{-- ========================================================= --}}
        {{-- STATUS DATA --}}
        {{-- ========================================================= --}}

        @php

            $status = $order['status'] ?? 'order_placed';

            $statusLabel = match($status) {
                'order_placed' => 'Order Placed',
                'seller_preparing' => 'Seller is Preparing',
                'ready_to_ship' => 'Ready to Ship',
                'picked_up' => 'Picked Up',
                'in_transit' => 'In Transit',
                'out_for_delivery' => 'Out for Delivery',
                'delivered' => 'Delivered',
                'cancelled' => 'Cancelled',
                default => 'Order Placed',
            };


            $trackingSteps = [

                [
                    'key' => 'order_placed',
                    'label' => 'Order Placed',
                    'description' => 'Your order has been placed successfully.',
                    'icon' => 'clipboard-check',
                ],

                [
                    'key' => 'seller_preparing',
                    'label' => 'Seller is Preparing',
                    'description' => 'The seller is preparing your items.',
                    'icon' => 'package',
                ],

                [
                    'key' => 'ready_to_ship',
                    'label' => 'Ready to Ship',
                    'description' => 'Your order is packed and ready for pickup.',
                    'icon' => 'package-check',
                ],

                [
                    'key' => 'picked_up',
                    'label' => 'Picked Up',
                    'description' => 'Your package has been picked up by the courier.',
                    'icon' => 'truck',
                ],

                [
                    'key' => 'in_transit',
                    'label' => 'In Transit',
                    'description' => 'Your package is on its way to the destination.',
                    'icon' => 'route',
                ],

                [
                    'key' => 'out_for_delivery',
                    'label' => 'Out for Delivery',
                    'description' => 'Your package is out for delivery.',
                    'icon' => 'bike',
                ],

                [
                    'key' => 'delivered',
                    'label' => 'Delivered',
                    'description' => 'Your order has been delivered successfully.',
                    'icon' => 'check-circle',
                ],

            ];


            $statusIndex = collect($trackingSteps)
                ->search(fn ($step) => $step['key'] === $status);

            if ($statusIndex === false) {
                $statusIndex = 0;
            }

        @endphp


        {{-- ========================================================= --}}
        {{-- CURRENT STATUS --}}
        {{-- ========================================================= --}}

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>

                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Current Status
                    </p>

                    <div class="mt-2 flex items-center gap-2">

                        @if($status === 'cancelled')

                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-2 text-sm font-semibold text-red-600">

                                <i
                                    data-lucide="x-circle"
                                    class="h-4 w-4"
                                ></i>

                                {{ $statusLabel }}

                            </span>

                        @elseif($status === 'delivered')

                            <span class="inline-flex items-center gap-2 rounded-full bg-green-50 px-4 py-2 text-sm font-semibold text-green-600">

                                <i
                                    data-lucide="check-circle"
                                    class="h-4 w-4"
                                ></i>

                                {{ $statusLabel }}

                            </span>

                        @else

                            <span class="inline-flex items-center gap-2 rounded-full bg-[#DDF3EC] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4"
                                ></i>

                                {{ $statusLabel }}

                            </span>

                        @endif

                    </div>

                </div>


                <div class="text-left sm:text-right">

                    <p class="text-xs text-gray-400">
                        Order Date
                    </p>

                    <p class="mt-1 text-sm font-medium text-gray-700">
                        {{ $order['created_at'] }}
                    </p>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- ORDER TRACKING --}}
        {{-- ========================================================= --}}

        @if($status !== 'cancelled')

            <section class="mb-6 rounded-2xl border border-gray-200 bg-white">

                <div class="border-b border-gray-100 px-5 py-5">

                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#DDF3EC]">

                            <i
                                data-lucide="map"
                                class="h-5 w-5 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold text-gray-900">
                                Order Tracking
                            </h2>

                            <p class="text-xs text-gray-500">
                                Track the progress of your order.
                            </p>

                        </div>

                    </div>

                </div>


                <div class="px-5 py-6 sm:px-8">

                    {{-- DESKTOP / TABLET TRACKING --}}
                    <div class="hidden md:block">

                        <div class="relative">

                            {{-- TRACKING LINE --}}
                            <div class="absolute left-0 right-0 top-5 h-1 rounded-full bg-gray-200"></div>

                            @if($statusIndex > 0)

                                <div
                                    class="absolute left-0 top-5 h-1 rounded-full bg-[#1F6F5B] transition-all"
                                    style="width: {{ ($statusIndex / (count($trackingSteps) - 1)) * 100 }}%;"
                                ></div>

                            @endif


                            <div class="relative grid grid-cols-7 gap-2">

                                @foreach($trackingSteps as $index => $step)

                                    @php
                                        $isCompleted = $index <= $statusIndex;
                                        $isCurrent = $index === $statusIndex;
                                    @endphp

                                    <div class="flex flex-col items-center text-center">

                                        {{-- ICON --}}
                                        <div
                                            class="
                                                flex h-10 w-10 items-center justify-center rounded-full border-4 border-white
                                                {{ $isCompleted
                                                    ? 'bg-[#1F6F5B] text-white'
                                                    : 'bg-gray-200 text-gray-400' }}
                                                {{ $isCurrent ? 'ring-4 ring-[#DDF3EC]' : '' }}
                                            "
                                        >

                                            @if($isCompleted)

                                                <i
                                                    data-lucide="{{ $isCurrent ? $step['icon'] : 'check' }}"
                                                    class="h-4 w-4"
                                                ></i>

                                            @else

                                                <i
                                                    data-lucide="{{ $step['icon'] }}"
                                                    class="h-4 w-4"
                                                ></i>

                                            @endif

                                        </div>


                                        {{-- LABEL --}}
                                        <p
                                            class="
                                                mt-3 text-xs font-semibold
                                                {{ $isCompleted
                                                    ? 'text-[#1F6F5B]'
                                                    : 'text-gray-400' }}
                                            "
                                        >
                                            {{ $step['label'] }}
                                        </p>

                                    </div>

                                @endforeach

                            </div>

                        </div>


                        {{-- CURRENT STEP DESCRIPTION --}}
                        <div class="mt-8 rounded-xl bg-[#F8FAF8] p-4">

                            <div class="flex items-start gap-3">

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                                    <i
                                        data-lucide="{{ $trackingSteps[$statusIndex]['icon'] }}"
                                        class="h-4 w-4 text-[#1F6F5B]"
                                    ></i>

                                </div>

                                <div>

                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $trackingSteps[$statusIndex]['label'] }}
                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        {{ $trackingSteps[$statusIndex]['description'] }}
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- MOBILE TRACKING --}}
                    <div class="md:hidden">

                        <div class="space-y-0">

                            @foreach($trackingSteps as $index => $step)

                                @php
                                    $isCompleted = $index <= $statusIndex;
                                    $isCurrent = $index === $statusIndex;
                                    $isLast = $index === count($trackingSteps) - 1;
                                @endphp

                                <div class="flex gap-4">

                                    {{-- TIMELINE --}}
                                    <div class="flex w-8 shrink-0 flex-col items-center">

                                        <div
                                            class="
                                                flex h-9 w-9 items-center justify-center rounded-full border-4 border-white
                                                {{ $isCompleted
                                                    ? 'bg-[#1F6F5B] text-white'
                                                    : 'bg-gray-200 text-gray-400' }}
                                                {{ $isCurrent ? 'ring-4 ring-[#DDF3EC]' : '' }}
                                            "
                                        >

                                            @if($isCompleted)

                                                <i
                                                    data-lucide="{{ $isCurrent ? $step['icon'] : 'check' }}"
                                                    class="h-4 w-4"
                                                ></i>

                                            @else

                                                <i
                                                    data-lucide="{{ $step['icon'] }}"
                                                    class="h-4 w-4"
                                                ></i>

                                            @endif

                                        </div>


                                        @if(!$isLast)

                                            <div
                                                class="
                                                    my-1 min-h-[45px] w-0.5
                                                    {{ $index < $statusIndex
                                                        ? 'bg-[#1F6F5B]'
                                                        : 'bg-gray-200' }}
                                                "
                                            ></div>

                                        @endif

                                    </div>


                                    {{-- CONTENT --}}
                                    <div class="pb-6">

                                        <p
                                            class="
                                                text-sm font-semibold
                                                {{ $isCompleted
                                                    ? 'text-gray-900'
                                                    : 'text-gray-400' }}
                                            "
                                        >
                                            {{ $step['label'] }}
                                        </p>

                                        <p
                                            class="
                                                mt-1 text-xs leading-5
                                                {{ $isCompleted
                                                    ? 'text-gray-500'
                                                    : 'text-gray-400' }}
                                            "
                                        >
                                            {{ $step['description'] }}
                                        </p>

                                        @if($isCurrent)

                                            <span class="mt-2 inline-flex rounded-full bg-[#DDF3EC] px-2.5 py-1 text-[11px] font-semibold text-[#1F6F5B]">
                                                Current Status
                                            </span>

                                        @endif

                                    </div>

                                </div>

                            @endforeach

                        </div>

                    </div>

                </div>

            </section>

        @endif


        {{-- ========================================================= --}}
        {{-- CANCELLED INFORMATION --}}
        {{-- ========================================================= --}}

        @if($status === 'cancelled')

            @php

                $reasonLabels = [
                    'changed_mind' => 'I changed my mind',
                    'ordered_by_mistake' => 'I ordered by mistake',
                    'found_better_price' => 'I found a better price',
                    'wrong_product' => 'Wrong product or variation',
                    'seller_requested' => 'Seller requested cancellation',
                    'other' => 'Other',
                ];

            @endphp

            <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 p-5">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="circle-x"
                        class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                    ></i>

                    <div>

                        <h2 class="text-sm font-semibold text-red-700">
                            This order has been cancelled
                        </h2>

                        <p class="mt-2 text-sm text-red-600">

                            Reason:

                            <span class="font-medium">
                                {{ $reasonLabels[$order['cancel_reason'] ?? 'other'] ?? $order['cancel_reason'] }}
                            </span>

                        </p>

                        @if(!empty($order['cancelled_at']))

                            <p class="mt-1 text-xs text-red-500">
                                Cancelled on {{ $order['cancelled_at'] }}
                            </p>

                        @endif

                    </div>

                </div>

            </div>

        @endif


        <div class="grid gap-6 lg:grid-cols-3">


            {{-- ===================================================== --}}
            {{-- LEFT CONTENT --}}
            {{-- ===================================================== --}}

            <div class="space-y-6 lg:col-span-2">


                {{-- ================================================= --}}
                {{-- DELIVERY ADDRESS --}}
                {{-- ================================================= --}}

                @if(!empty($order['shipping_address']))

                    @php
                        $address = $order['shipping_address'];
                    @endphp

                    <section class="rounded-2xl border border-gray-200 bg-white">

                        <div class="border-b border-gray-100 px-5 py-4">

                            <div class="flex items-center gap-2">

                                <i
                                    data-lucide="map-pin"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>

                                <h2 class="font-semibold text-gray-900">
                                    Delivery Address
                                </h2>

                            </div>

                        </div>


                        <div class="p-5">

                            <div class="flex flex-wrap items-center gap-3">

                                <span class="font-semibold text-gray-900">
                                    {{ $address['name'] }}
                                </span>

                                <span class="text-sm text-gray-500">
                                    {{ $address['phone'] }}
                                </span>

                                @if(!empty($address['label']))

                                    <span class="rounded-md bg-[#DDF3EC] px-2.5 py-1 text-xs font-medium text-[#1F6F5B]">
                                        {{ $address['label'] }}
                                    </span>

                                @endif

                            </div>


                            <p class="mt-2 text-sm leading-6 text-gray-600">

                                @if(!empty($address['house_number']))
                                    {{ $address['house_number'] }},
                                @endif

                                {{ $address['street'] }},
                                Barangay {{ $address['barangay'] }},
                                {{ $address['municipality'] }},
                                {{ $address['province'] }},
                                {{ $address['postal_code'] }}

                            </p>

                        </div>

                    </section>

                @endif


                {{-- ================================================= --}}
                {{-- PRODUCTS --}}
                {{-- ================================================= --}}

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

                    <div class="border-b border-gray-100 px-5 py-4">

                        <div class="flex items-center justify-between">

                            <h2 class="font-semibold text-gray-900">
                                Order Items
                            </h2>

                            <span class="text-xs text-gray-400">
                                {{ count($order['items']) }} item{{ count($order['items']) !== 1 ? 's' : '' }}
                            </span>

                        </div>

                    </div>


                    <div class="divide-y divide-gray-100">

                        @foreach($order['items'] as $item)

                            <div class="flex gap-4 p-5">

                                <img
                                    src="{{ $item['image'] }}"
                                    alt="{{ $item['name'] }}"
                                    class="h-24 w-24 shrink-0 rounded-xl object-cover"
                                >


                                <div class="min-w-0 flex-1">

                                    <h3 class="text-sm font-semibold text-gray-900">
                                        {{ $item['name'] }}
                                    </h3>

                                    <p class="mt-2 text-sm text-gray-500">
                                        Quantity: {{ $item['quantity'] }}
                                    </p>

                                    <p class="mt-2 font-semibold text-gray-900">
                                        ₱{{ number_format($item['price'], 2) }}
                                    </p>

                                </div>


                                <div class="text-right">

                                    <p class="text-xs text-gray-400">
                                        Item Total
                                    </p>

                                    <p class="mt-1 text-sm font-bold text-[#1F6F5B]">
                                        ₱{{ number_format($item['price'] * $item['quantity'], 2) }}
                                    </p>

                                </div>

                            </div>

                        @endforeach

                    </div>

                </div>


                {{-- ================================================= --}}
                {{-- SHIPPING & PAYMENT --}}
                {{-- ================================================= --}}

                <div class="rounded-2xl border border-gray-200 bg-white">

                    <div class="border-b border-gray-100 px-5 py-4">

                        <h2 class="font-semibold text-gray-900">
                            Shipping & Payment
                        </h2>

                    </div>


                    <div class="grid gap-5 p-5 sm:grid-cols-2">

                        {{-- SHIPPING --}}
                        <div>

                            <p class="text-xs text-gray-400">
                                Shipping Method
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="truck"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>

                                <span class="text-sm font-semibold text-gray-700">
                                    {{ match($order['shipping_method'] ?? '') {
                                        'jnt' => 'J&T Express',
                                        'flash' => 'Flash Express',
                                        'lbc' => 'LBC Express',
                                        default => strtoupper($order['shipping_method'] ?? 'N/A'),
                                    } }}
                                </span>

                            </div>

                        </div>


                        {{-- PAYMENT --}}
                        <div>

                            <p class="text-xs text-gray-400">
                                Payment Method
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="credit-card"
                                    class="h-4 w-4 text-[#1F6F5B]"
                                ></i>

                                <span class="text-sm font-semibold text-gray-700">
                                    {{ match($order['payment_method'] ?? '') {
                                        'cod' => 'Cash on Delivery',
                                        'gcash' => 'GCash',
                                        default => strtoupper($order['payment_method'] ?? 'N/A'),
                                    } }}
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ===================================================== --}}
            {{-- RIGHT SUMMARY --}}
            {{-- ===================================================== --}}

            <div>

                <div class="sticky top-24 rounded-2xl border border-gray-200 bg-white p-5">

                    <h2 class="font-semibold text-gray-900">
                        Order Summary
                    </h2>


                    <div class="mt-5 space-y-3">

                        <div class="flex justify-between text-sm">

                            <span class="text-gray-500">
                                Subtotal
                            </span>

                            <span class="font-medium text-gray-700">
                                ₱{{ number_format($order['subtotal'], 2) }}
                            </span>

                        </div>


                        <div class="flex justify-between text-sm">

                            <span class="text-gray-500">
                                Shipping
                            </span>

                            <span class="font-medium text-gray-700">
                                ₱{{ number_format($order['shipping'], 2) }}
                            </span>

                        </div>


                        <div class="border-t border-gray-100 pt-3">

                            <div class="flex items-center justify-between">

                                <span class="font-semibold text-gray-900">
                                    Total
                                </span>

                                <span class="text-xl font-bold text-[#1F6F5B]">
                                    ₱{{ number_format($order['total'], 2) }}
                                </span>

                            </div>

                        </div>

                    </div>


                    {{-- BUY AGAIN --}}
                    @if($status === 'cancelled' || $status === 'delivered')

                        <form
                            action="{{ route('buyer.order.buy-again', $order['id']) }}"
                            method="POST"
                            class="mt-6"
                        >

                            @csrf

                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
                            >

                                <i
                                    data-lucide="shopping-cart"
                                    class="h-4 w-4"
                                ></i>

                                Buy Again

                            </button>

                        </form>

                    @endif


                    {{-- CANCEL ORDER --}}
                    @if($status === 'order_placed')

                        <button
                            type="button"
                            onclick="openCancelModal()"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                        >

                            <i
                                data-lucide="x"
                                class="h-4 w-4"
                            ></i>

                            Cancel Order

                        </button>

                    @endif


                    {{-- PREPARING NOTICE --}}
                    @if($status === 'seller_preparing')

                        <div class="mt-5 rounded-xl bg-gray-50 p-4">

                            <div class="flex items-start gap-3">

                                <i
                                    data-lucide="info"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                                ></i>

                                <p class="text-xs leading-5 text-gray-500">
                                    The seller has started preparing your order.
                                    Cancellation is no longer available.
                                </p>

                            </div>

                        </div>

                    @endif


                    {{-- DELIVERY NOTICE --}}
                    @if($status === 'out_for_delivery')

                        <div class="mt-5 rounded-xl bg-[#F8FAF8] p-4">

                            <div class="flex items-start gap-3">

                                <i
                                    data-lucide="bike"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"
                                ></i>

                                <p class="text-xs leading-5 text-gray-500">
                                    Your package is currently out for delivery.
                                    Please make sure someone is available to receive it.
                                </p>

                            </div>

                        </div>

                    @endif


                    {{-- DELIVERED NOTICE --}}
                    @if($status === 'delivered')

                        <div class="mt-5 rounded-xl bg-green-50 p-4">

                            <div class="flex items-start gap-3">

                                <i
                                    data-lucide="check-circle"
                                    class="mt-0.5 h-4 w-4 shrink-0 text-green-600"
                                ></i>

                                <p class="text-xs leading-5 text-green-700">
                                    Your order has been delivered successfully.
                                </p>

                            </div>

                        </div>

                    @endif

                </div>

            </div>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- CANCEL ORDER MODAL --}}
{{-- ========================================================= --}}

@if($status === 'order_placed')

<div
    id="cancelModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4"
>

    <div
        class="w-full max-w-md rounded-2xl bg-white shadow-xl"
        onclick="event.stopPropagation()"
    >

        {{-- HEADER --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">

            <div>

                <h2 class="text-lg font-semibold text-gray-900">
                    Cancel Order
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Please tell us why you want to cancel.
                </p>

            </div>


            <button
                type="button"
                onclick="closeCancelModal()"
                class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
            >

                <i data-lucide="x" class="h-5 w-5"></i>

            </button>

        </div>


        {{-- FORM --}}
        <form
            action="{{ route('buyer.order.cancel', $order['id']) }}"
            method="POST"
        >

            @csrf

            <div class="space-y-2 px-5 py-5">

                {{-- REASON 1 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="changed_mind"
                        required
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        I changed my mind
                    </span>

                </label>


                {{-- REASON 2 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="ordered_by_mistake"
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        I ordered by mistake
                    </span>

                </label>


                {{-- REASON 3 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="found_better_price"
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        I found a better price
                    </span>

                </label>


                {{-- REASON 4 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="wrong_product"
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        Wrong product or variation
                    </span>

                </label>


                {{-- REASON 5 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="seller_requested"
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        Seller requested cancellation
                    </span>

                </label>


                {{-- REASON 6 --}}
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:border-[#1F6F5B] hover:bg-[#F8FAF8]">

                    <input
                        type="radio"
                        name="cancel_reason"
                        value="other"
                        class="h-4 w-4 accent-[#1F6F5B]"
                    >

                    <span class="text-sm text-gray-700">
                        Other
                    </span>

                </label>

            </div>


            {{-- BUTTONS --}}
            <div class="flex gap-3 border-t border-gray-100 px-5 py-4">

                <button
                    type="button"
                    onclick="closeCancelModal()"
                    class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                >
                    Keep Order
                </button>


                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-700"
                >
                    Confirm Cancellation
                </button>

            </div>

        </form>

    </div>

</div>

@endif


{{-- ========================================================= --}}
{{-- MODAL SCRIPT --}}
{{-- ========================================================= --}}

@if($status === 'order_placed')

<script>

    function openCancelModal() {

        const modal = document.getElementById('cancelModal');

        modal.classList.remove('hidden');

        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');

    }


    function closeCancelModal() {

        const modal = document.getElementById('cancelModal');

        modal.classList.add('hidden');

        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');

    }


    document
        .getElementById('cancelModal')
        ?.addEventListener('click', function (event) {

            if (event.target === this) {
                closeCancelModal();
            }

        });

</script>

@endif

@endsection