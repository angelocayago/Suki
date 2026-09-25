@extends('layouts.logistics')

@section('content')

@php
    $orders = session('orders', []);

    $monitoringOrders = collect($orders)
        ->filter(function ($order) {
            return in_array(
                $order['status'] ?? '',
                [
                    'assigned_to_rider',
                    'out_for_delivery',
                    'delivered',
                    'delivery_failed',
                    'returned',
                ],
                true
            );
        })
        ->sortByDesc(function ($order) {
            return $order['updated_at'] ?? '';
        });

    $assignedCount = $monitoringOrders
        ->where('status', 'assigned_to_rider')
        ->count();

    $outForDeliveryCount = $monitoringOrders
        ->where('status', 'out_for_delivery')
        ->count();

    $deliveredCount = $monitoringOrders
        ->where('status', 'delivered')
        ->count();

    $exceptionCount = $monitoringOrders
        ->filter(function ($order) {
            return in_array(
                $order['status'] ?? '',
                ['delivery_failed', 'returned'],
                true
            );
        })
        ->count();


    $statusLabels = [

        'assigned_to_rider' =>
            'Assigned to Rider',

        'out_for_delivery' =>
            'Out for Delivery',

        'delivered' =>
            'Delivered',

        'delivery_failed' =>
            'Delivery Failed',

        'returned' =>
            'Returned',

    ];


    $statusClasses = [

        'assigned_to_rider' =>
            'bg-cyan-50 text-cyan-700 border-cyan-200',

        'out_for_delivery' =>
            'bg-orange-50 text-orange-700 border-orange-200',

        'delivered' =>
            'bg-green-50 text-green-700 border-green-200',

        'delivery_failed' =>
            'bg-red-50 text-red-700 border-red-200',

        'returned' =>
            'bg-purple-50 text-purple-700 border-purple-200',

    ];
@endphp


<div class="space-y-6">


    {{-- =====================================================
         PAGE HEADER
    ====================================================== --}}

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <p class="text-sm font-medium text-[#1F6F5B]">
                Logistics Center
            </p>

            <h1 class="mt-1 text-2xl font-bold text-gray-900">
                Delivery Monitoring
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Monitor assigned shipments and delivery progress.
            </p>

        </div>

    </div>


    {{-- =====================================================
         ALERTS
    ====================================================== --}}

    @if(session('success'))

        <div class="flex items-start gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">

            <i
                data-lucide="circle-check"
                class="mt-0.5 h-5 w-5 shrink-0"
            ></i>

            <span>
                {{ session('success') }}
            </span>

        </div>

    @endif


    @if(session('error'))

        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">

            <i
                data-lucide="circle-alert"
                class="mt-0.5 h-5 w-5 shrink-0"
            ></i>

            <span>
                {{ session('error') }}
            </span>

        </div>

    @endif


    {{-- =====================================================
         SUMMARY CARDS
    ====================================================== --}}

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">


        {{-- ASSIGNED --}}

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Assigned to Rider
                    </p>

                    <p class="mt-1 text-2xl font-bold text-gray-900">
                        {{ $assignedCount }}
                    </p>

                    <p class="mt-2 text-xs text-cyan-600">
                        Waiting for delivery
                    </p>

                </div>

                <div class="rounded-xl bg-cyan-50 p-3">

                    <i
                        data-lucide="user-check"
                        class="h-5 w-5 text-cyan-600"
                    ></i>

                </div>

            </div>

        </div>


        {{-- OUT FOR DELIVERY --}}

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Out for Delivery
                    </p>

                    <p class="mt-1 text-2xl font-bold text-gray-900">
                        {{ $outForDeliveryCount }}
                    </p>

                    <p class="mt-2 text-xs text-orange-600">
                        Currently delivering
                    </p>

                </div>

                <div class="rounded-xl bg-orange-50 p-3">

                    <i
                        data-lucide="truck"
                        class="h-5 w-5 text-orange-600"
                    ></i>

                </div>

            </div>

        </div>


        {{-- DELIVERED --}}

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Delivered
                    </p>

                    <p class="mt-1 text-2xl font-bold text-gray-900">
                        {{ $deliveredCount }}
                    </p>

                    <p class="mt-2 text-xs text-[#1F6F5B]">
                        Successfully delivered
                    </p>

                </div>

                <div class="rounded-xl bg-[#EEF8F3] p-3">

                    <i
                        data-lucide="circle-check"
                        class="h-5 w-5 text-[#1F6F5B]"
                    ></i>

                </div>

            </div>

        </div>


        {{-- EXCEPTIONS --}}

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="flex items-start justify-between gap-3">

                <div>

                    <p class="text-sm text-gray-500">
                        Exceptions
                    </p>

                    <p class="mt-1 text-2xl font-bold text-red-600">
                        {{ $exceptionCount }}
                    </p>

                    <p class="mt-2 text-xs text-red-600">
                        Failed or returned
                    </p>

                </div>

                <div class="rounded-xl bg-red-50 p-3">

                    <i
                        data-lucide="triangle-alert"
                        class="h-5 w-5 text-red-600"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         DELIVERY MONITORING
    ====================================================== --}}

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">


        {{-- SECTION HEADER --}}

        <div class="border-b border-gray-100 px-5 py-4">

            <h2 class="font-semibold text-gray-900">
                Shipment Delivery Monitoring
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Track the current status of shipments assigned to delivery riders.
            </p>

        </div>


        {{-- EMPTY STATE --}}

        @if($monitoringOrders->isEmpty())

            <div class="px-5 py-16 text-center">

                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">

                    <i
                        data-lucide="truck"
                        class="h-7 w-7 text-gray-400"
                    ></i>

                </div>

                <h3 class="mt-4 font-semibold text-gray-900">
                    No shipments to monitor
                </h3>

                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">
                    Assigned shipments will appear here once Logistics assigns them to a delivery rider.
                </p>

            </div>


        @else


            {{-- =================================================
                 DESKTOP TABLE
            ================================================== --}}

            <div class="hidden overflow-x-auto lg:block">

                <table class="min-w-full">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Order
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Buyer
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Destination
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Rider
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Updated
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        @foreach($monitoringOrders as $orderId => $order)

                            @php

                                $orderNumber =
                                    $order['order_number']
                                    ?? $order['id']
                                    ?? $orderId;


                                $buyerName =
                                    $order['buyer_name']
                                    ?? $order['customer_name']
                                    ?? 'Buyer';


                                $address =
                                    $order['address']
                                    ?? $order['shipping_address']
                                    ?? $order['delivery_address']
                                    ?? 'No delivery address available';


                                $area =
                                    $order['assigned_area']
                                    ?? $order['area']
                                    ?? 'Not specified';


                                $riderName =
                                    $order['rider_name']
                                    ?? 'Not assigned';


                                $riderPhone =
                                    $order['rider']['phone']
                                    ?? '';


                                $status =
                                    $order['status']
                                    ?? 'assigned_to_rider';


                                $statusLabel =
                                    $statusLabels[$status]
                                    ?? ucfirst(str_replace('_', ' ', $status));


                                $statusClass =
                                    $statusClasses[$status]
                                    ?? 'bg-gray-50 text-gray-600 border-gray-200';


                                $updatedAt =
                                    $order['updated_at']
                                    ?? 'Not available';

                            @endphp


                            <tr class="hover:bg-gray-50 transition">


                                {{-- ORDER --}}

                                <td class="px-5 py-4">

                                    <p class="font-semibold text-gray-900">
                                        #{{ $orderNumber }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ $orderId }}
                                    </p>

                                </td>


                                {{-- BUYER --}}

                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-2">

                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100">

                                            <i
                                                data-lucide="user"
                                                class="h-4 w-4 text-gray-500"
                                            ></i>

                                        </div>

                                        <span class="text-sm font-medium text-gray-800">
                                            {{ $buyerName }}
                                        </span>

                                    </div>

                                </td>


                                {{-- DESTINATION --}}

                                <td class="max-w-xs px-5 py-4">

                                    <div class="flex items-start gap-2">

                                        <i
                                            data-lucide="map-pin"
                                            class="mt-0.5 h-4 w-4 shrink-0 text-[#1F6F5B]"
                                        ></i>

                                        <div>

                                            <p class="text-sm text-gray-800">
                                                {{ $address }}
                                            </p>

                                            <p class="mt-1 text-xs font-medium text-[#1F6F5B]">
                                                {{ $area }}
                                            </p>

                                        </div>

                                    </div>

                                </td>


                                {{-- RIDER --}}

                                <td class="px-5 py-4">

                                    <div>

                                        <p class="text-sm font-medium text-gray-800">
                                            {{ $riderName }}
                                        </p>

                                        @if($riderPhone)

                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $riderPhone }}
                                            </p>

                                        @endif

                                    </div>

                                </td>


                                {{-- STATUS --}}

                                <td class="px-5 py-4">

                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">

                                        {{ $statusLabel }}

                                    </span>

                                </td>


                                {{-- UPDATED --}}

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500">

                                    {{ $updatedAt }}

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>


            {{-- =================================================
                 MOBILE CARDS
            ================================================== --}}

            <div class="divide-y divide-gray-100 lg:hidden">

                @foreach($monitoringOrders as $orderId => $order)

                    @php

                        $orderNumber =
                            $order['order_number']
                            ?? $order['id']
                            ?? $orderId;


                        $buyerName =
                            $order['buyer_name']
                            ?? $order['customer_name']
                            ?? 'Buyer';


                        $address =
                            $order['address']
                            ?? $order['shipping_address']
                            ?? $order['delivery_address']
                            ?? 'No delivery address available';


                        $area =
                            $order['assigned_area']
                            ?? $order['area']
                            ?? 'Not specified';


                        $riderName =
                            $order['rider_name']
                            ?? 'Not assigned';


                        $riderPhone =
                            $order['rider']['phone']
                            ?? '';


                        $status =
                            $order['status']
                            ?? 'assigned_to_rider';


                        $statusLabel =
                            $statusLabels[$status]
                            ?? ucfirst(str_replace('_', ' ', $status));


                        $statusClass =
                            $statusClasses[$status]
                            ?? 'bg-gray-50 text-gray-600 border-gray-200';


                        $updatedAt =
                            $order['updated_at']
                            ?? 'Not available';

                    @endphp


                    <div class="p-5">


                        {{-- TOP --}}

                        <div class="flex items-start justify-between gap-4">

                            <div>

                                <p class="font-semibold text-gray-900">
                                    Order #{{ $orderNumber }}
                                </p>

                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $buyerName }}
                                </p>

                            </div>


                            <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">

                                {{ $statusLabel }}

                            </span>

                        </div>


                        {{-- DETAILS --}}

                        <div class="mt-5 space-y-4">


                            {{-- ADDRESS --}}

                            <div class="flex items-start gap-3">

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50">

                                    <i
                                        data-lucide="map-pin"
                                        class="h-4 w-4 text-gray-500"
                                    ></i>

                                </div>

                                <div>

                                    <p class="text-xs text-gray-400">
                                        Destination
                                    </p>

                                    <p class="mt-1 text-sm text-gray-800">
                                        {{ $address }}
                                    </p>

                                    <p class="mt-1 text-xs font-medium text-[#1F6F5B]">
                                        {{ $area }}
                                    </p>

                                </div>

                            </div>


                            {{-- RIDER --}}

                            <div class="flex items-start gap-3">

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50">

                                    <i
                                        data-lucide="bike"
                                        class="h-4 w-4 text-gray-500"
                                    ></i>

                                </div>

                                <div>

                                    <p class="text-xs text-gray-400">
                                        Delivery Rider
                                    </p>

                                    <p class="mt-1 text-sm font-medium text-gray-800">
                                        {{ $riderName }}
                                    </p>

                                    @if($riderPhone)

                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ $riderPhone }}
                                        </p>

                                    @endif

                                </div>

                            </div>


                            {{-- UPDATED --}}

                            <div class="flex items-center gap-3">

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-50">

                                    <i
                                        data-lucide="clock-3"
                                        class="h-4 w-4 text-gray-500"
                                    ></i>

                                </div>

                                <div>

                                    <p class="text-xs text-gray-400">
                                        Last Updated
                                    </p>

                                    <p class="mt-1 text-sm text-gray-700">
                                        {{ $updatedAt }}
                                    </p>

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


@push('scripts')

<script>
    document.addEventListener('DOMContentLoaded', function () {

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

    });
</script>

@endpush