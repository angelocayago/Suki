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

            @foreach(($order['seller_orders'] ?? []) as $sellerOrder)
                @if(!empty($sellerOrder['has_proof']))
                    <a href="{{ route('shipments.delivery-proof', $sellerOrder['shipment_id']) }}" class="mt-3 inline-flex rounded-lg border border-[#1F6F5B] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">View delivery proof · {{ $sellerOrder['seller_name'] }}</a>
                @endif
                @if(($sellerOrder['status'] ?? '') !== 'cancelled')
                    <a href="{{ route('buyer.seller-order.track', [$order['order_id'], $sellerOrder['seller_order_id']]) }}" class="mt-3 inline-flex rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Track {{ $sellerOrder['seller_name'] }} delivery</a>
                @endif
            @endforeach
        </div>

        {{-- ========================================================= --}}
        {{-- STATUS DATA --}}
        {{-- ========================================================= --}}

        @php

            $status = $order['status'] ?? 'placed';

            $statusLabel = $tracking['status'];

            $isCancelled = in_array($status, ['cancelled', 'returned', 'delivery_failed']);

        @endphp

        {{-- ========================================================= --}}
        {{-- CURRENT STATUS --}}
        {{-- ========================================================= --}}

        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Overall order status
                    </p>

                    <div class="mt-2 flex items-center gap-2">

                        @if($status === 'cancelled' || $status === 'returned' || $status === 'delivery_failed')

                            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-2 text-sm font-semibold text-red-600">
                                <i data-lucide="x-circle" class="h-4 w-4"></i>
                                {{ $statusLabel }}
                            </span>

                        @elseif($status === 'delivered' || $status === 'completed')

                            <span class="inline-flex items-center gap-2 rounded-full bg-green-50 px-4 py-2 text-sm font-semibold text-green-600">
                                <i data-lucide="check-circle" class="h-4 w-4"></i>
                                {{ $statusLabel }}
                            </span>

                        @else

                            <span class="inline-flex items-center gap-2 rounded-full bg-[#DDF3EC] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">
                                <i data-lucide="package" class="h-4 w-4"></i>
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
                        {{ \App\Support\ManilaTime::format($order['created_at'] ?? null) ?? 'Time not recorded' }}@if($order['created_at'] ?? null) PHT @endif
                    </p>
                </div>

            </div>

        </div>

        {{-- ========================================================= --}}
        {{-- ORDER TRACKING --}}
        {{-- ========================================================= --}}

        <div class="mb-6">
            @include('buyer.partials.order-tracking')
        </div>

        {{-- ========================================================= --}}
        {{-- CANCELLED / FAILED / RETURNED INFORMATION --}}
        {{-- ========================================================= --}}

        @if($isCancelled)

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

                    <i data-lucide="circle-x" class="mt-0.5 h-5 w-5 shrink-0 text-red-500"></i>

                    <div>

                        <h2 class="text-sm font-semibold text-red-700">
                            {{ $statusLabel }}
                        </h2>

                        @if(!empty($order['cancel_reason']))

                            <p class="mt-2 text-sm text-red-600">
                                Reason:
                                <span class="font-medium">
                                    {{ $reasonLabels[$order['cancel_reason']] ?? 'Reason not available.' }}
                                </span>
                            </p>

                        @endif

                        @if(!empty($order['delivery_failed_reason']))

                            <p class="mt-2 text-sm text-red-600">
                                Delivery Reason:
                                <span class="font-medium">
                                    {{ $order['delivery_failed_reason'] }}
                                </span>
                            </p>

                        @endif

                        @if(!empty($order['cancelled_at']))

                            <p class="mt-1 text-xs text-red-500">
                                Cancelled on {{ \App\Support\ManilaTime::format($order['cancelled_at']) }} PHT
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

                {{-- DELIVERY ADDRESS --}}
                @if(!empty($order['shipping_address']))

                    @php
                        $address = $order['shipping_address'];
                    @endphp

                    <section class="rounded-2xl border border-gray-200 bg-white">

                        <div class="border-b border-gray-100 px-5 py-4">

                            <div class="flex items-center gap-2">

                                <i data-lucide="map-pin" class="h-4 w-4 text-[#1F6F5B]"></i>

                                <h2 class="font-semibold text-gray-900">
                                    Delivery Address
                                </h2>

                            </div>

                        </div>

                        <div class="p-5">

                            <div class="flex flex-wrap items-center gap-3">

                                <span class="font-semibold text-gray-900">
                                    {{ $address['name'] ?? 'N/A' }}
                                </span>

                                <span class="text-sm text-gray-500">
                                    {{ $address['phone'] ?? 'N/A' }}
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

                                {{ $address['street'] ?? '' }},
                                Barangay {{ $address['barangay'] ?? '' }},
                                {{ $address['municipality'] ?? '' }},
                                @if(!empty($address['province'])){{ $address['province'] }},@endif
                                {{ $address['postal_code'] ?? '' }}

                            </p>

                        </div>

                    </section>

                @endif

                {{-- PRODUCTS --}}
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">

                    <div class="border-b border-gray-100 px-5 py-4">

                        <div class="flex items-center justify-between">

                            <h2 class="font-semibold text-gray-900">
                                Order Items
                            </h2>

                            <span class="text-xs text-gray-400">
                                {{ count($order['items'] ?? []) }}
                                item{{ count($order['items'] ?? []) !== 1 ? 's' : '' }}
                            </span>

                        </div>

                    </div>

                    <div class="divide-y divide-gray-100">

                        @foreach($order['items'] ?? [] as $item)

                            <div class="flex gap-4 p-5">

                                @include('buyer.partials.purchase-thumbnail', ['thumbnailSize' => 'h-24 w-24'])

                                <div class="min-w-0 flex-1">

                                    <h3 class="text-sm font-semibold text-gray-900">
                                        {{ $item['name'] }}
                                    </h3>

                                    <p class="mt-2 text-sm text-gray-500">
                                        Quantity: {{ $item['quantity'] }}
                                    </p>
                                    @if(!empty($item['variant_name']))<p class="mt-1 text-sm text-gray-600">Purchased variant: {{ $item['variant_name'] }}</p>@endif
                                    @if(is_array($item['variation'] ?? null))<dl class="mt-1 text-sm text-gray-600">@foreach(\Illuminate\Support\Arr::dot($item['variation']) as $name => $value)<div><dt class="inline">{{ $name }}:</dt> <dd class="inline">{{ $value }}</dd></div>@endforeach</dl>@endif
                                    @if($actions = $item['review_actions'] ?? null)
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @if($actions['create'])<a href="{{ $actions['url'] }}" class="rounded-lg bg-[#1F6F5B] px-3 py-2 text-xs font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1F6F5B]">Rate Product</a>@endif
                                            @if($actions['view'])<a href="{{ $actions['url'] }}" class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">View Review</a>@endif
                                            @if($actions['edit'])<a href="{{ $actions['edit_url'] }}" class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">Edit Review</a>@endif
                                        </div>
                                    @endif

                                    @if(!empty($item['return_eligible']))
                                        <a href="{{ route('buyer.return-refunds.create', $item['order_item_id']) }}" class="mt-3 inline-flex rounded-lg border border-[#1F6F5B] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">
                                            Return / Refund
                                        </a>
                                        @if(!app(\App\Services\ReturnRefundWorkflowService::class)->available())<p class="text-xs text-gray-500">Submission is temporarily disabled; view eligibility information.</p>@endif
                                    @elseif(!empty($item['return_request']))
                                        <p class="mt-2 text-xs">Request status: {{ $item['return_request']->isSellerReview() ? $item['return_request']->statusLabel() : str_replace('_',' ',$item['return_request']->status) }}</p><a class="underline" href="{{ route('buyer.return-refunds.show',$item['return_request']) }}">View Request</a>
                                    @endif

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

                {{-- SHIPPING & PAYMENT --}}
                @if(!empty($order['has_review_actions']))
                    <section class="rounded-2xl border border-gray-200 bg-white p-5" aria-label="Seller ratings">
                        <h2 class="font-semibold">Rate your Sellers</h2>
                        <p class="mt-1 text-sm text-gray-600">Seller service ratings are separate from product reviews.</p>
                        @foreach($order['seller_orders'] ?? [] as $sellerOrder)
                            @php($actions = $sellerOrder['review_actions'] ?? null)
                            @if($actions && ($actions['create'] || $actions['view']))
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                                    <span class="break-words text-sm font-semibold">{{ $sellerOrder['seller_name'] }}</span>
                                    <a href="{{ $actions['url'] }}" class="rounded-lg border border-[#1F6F5B] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">{{ $actions['view'] ? 'View Seller Rating' : 'Rate Seller' }}</a>
                                </div>
                            @endif
                        @endforeach
                    </section>
                @endif
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

                                <i data-lucide="truck" class="h-4 w-4 text-[#1F6F5B]"></i>

                                <span class="text-sm font-semibold text-gray-700">

                                    {{ match($order['shipping_method'] ?? '') {
                                        'jnt' => 'J&T Express',
                                        'flash' => 'Flash Express',
                                        'spx' => 'SPX Express',
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

                                <i data-lucide="credit-card" class="h-4 w-4 text-[#1F6F5B]"></i>

                                <span class="text-sm font-semibold text-gray-700">

                                    {{ ($order['payment_method'] ?? '') === 'cod'
                                        ? 'Cash on Delivery'
                                        : strtoupper($order['payment_method'] ?? 'N/A') }}

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
                                ₱{{ number_format($order['subtotal'] ?? 0, 2) }}
                            </span>

                        </div>

                        <div class="flex justify-between text-sm">

                            <span class="text-gray-500">
                                Shipping
                            </span>

                            <span class="font-medium text-gray-700">
                                ₱{{ number_format($order['shipping'] ?? 0, 2) }}
                            </span>

                        </div>

                        <div class="border-t border-gray-100 pt-3">

                            <div class="flex items-center justify-between">

                                <span class="font-semibold text-gray-900">
                                    Total
                                </span>

                                <span class="text-xl font-bold text-[#1F6F5B]">
                                    ₱{{ number_format($order['total'] ?? 0, 2) }}
                                </span>

                            </div>

                        </div>

                    </div>

                    {{-- BUY AGAIN --}}
                    @if(in_array($status, ['cancelled', 'returned', 'completed']))

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

                                <i data-lucide="shopping-cart" class="h-4 w-4"></i>

                                Buy Again

                            </button>

                        </form>

                    @endif

                    @include('buyer.partials.receipt-action')

                    @include('buyer.partials.cancellation-action')

                    @foreach(($order['seller_orders'] ?? []) as $sellerOrder)
                        <form
                            action="{{ route('buyer.messages.context.seller-order.store', $sellerOrder['seller_order_id']) }}"
                            method="POST"
                            class="mt-3"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-5 py-3 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#DDF3EC]"
                            >
                                <i data-lucide="message-circle" class="h-4 w-4"></i>
                                Message {{ $sellerOrder['seller_name'] }}
                            </button>
                        </form>
                    @endforeach

                    <a
                        href="{{ route('buyer.complaints.create', $order['id']) }}"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-[#DCE5E0] bg-white px-5 py-3 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#F6F9F7]"
                    >
                        Submit a complaint
                    </a>

                    {{-- PREPARING NOTICE --}}
                    @if(in_array($status, ['confirmed', 'preparing', 'ready_for_pickup']))

                        <div class="mt-5 rounded-xl bg-gray-50 p-4">

                            <div class="flex items-start gap-3">

                                <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"></i>

                                <p class="text-xs leading-5 text-gray-500">

                                    @if($status === 'confirmed')
                                        The seller has confirmed your order.
                                    @elseif($status === 'preparing')
                                        The seller is currently preparing your order.
                                    @else
                                        Your order is ready and waiting for pickup.
                                    @endif

                                    Cancellation is no longer available.

                                </p>

                            </div>

                        </div>

                    @endif

                    {{-- SORTING NOTICE --}}
                    @if(in_array($status, ['picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider']))

                        <div class="mt-5 rounded-xl bg-[#F8FAF8] p-4">

                            <div class="flex items-start gap-3">

                                <i data-lucide="truck" class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"></i>

                                <p class="text-xs leading-5 text-gray-500">

                                    @if($status === 'picked_up')
                                        Your parcel has been collected and is on its way to the sorting center.
                                    @elseif($status === 'at_sorting_center')
                                        Your parcel has arrived at the sorting center.
                                    @elseif($status === 'sorted')
                                        Your parcel has been sorted according to your delivery area.
                                    @else
                                        A delivery rider has been assigned to your parcel.
                                    @endif

                                </p>

                            </div>

                        </div>

                    @endif

                    {{-- DELIVERY NOTICE --}}
                    @if($status === 'out_for_delivery')

                        <div class="mt-5 rounded-xl bg-[#F8FAF8] p-4">

                            <div class="flex items-start gap-3">

                                <i data-lucide="bike" class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"></i>

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

                                <i data-lucide="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600"></i>

                                <p class="text-xs leading-5 text-green-700">
                                    Your order has been delivered successfully.
                                    Please confirm once you have received it.
                                </p>

                            </div>

                        </div>

                    @endif

                    {{-- COMPLETED NOTICE --}}
                    @if($status === 'completed')

                        <div class="mt-5 rounded-xl bg-green-50 p-4">

                            <div class="flex items-start gap-3">

                                <i data-lucide="badge-check" class="mt-0.5 h-4 w-4 shrink-0 text-green-600"></i>

                                <p class="text-xs leading-5 text-green-700">
                                    This order is now completed.
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
@include('buyer.partials.cancellation-dialog')
@include('buyer.partials.receipt-dialog')

@endsection
