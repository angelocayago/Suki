@extends('layouts.logistics')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <!-- LOGO -->
            <a
                href="{{ route('logistics.dashboard') }}"
                class="flex items-center gap-3"
            >

                <img
                    src="{{ asset('images/suki-logistics.jpg') }}"
                    alt="SUKI SHOP Logistics"
                    class="h-10 w-auto object-contain"
                >

                <div class="hidden sm:block">

                    <p class="text-sm font-bold text-gray-900">
                        SUKI SHOP Logistics
                    </p>

                    <p class="text-xs text-gray-500">
                        Sorting Center
                    </p>

                </div>

            </a>


            <!-- LOGISTICS USER -->
            <div class="flex items-center gap-3">

                <div class="hidden text-right sm:block">

                    <p class="text-sm font-semibold text-gray-900">
                        Logistics Center
                    </p>

                    <p class="text-xs text-gray-500">
                        Management Portal
                    </p>

                </div>


                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#E6F4EE] text-[#1F6F5B]">

                    <i
                        data-lucide="building-2"
                        class="h-5 w-5"
                    ></i>

                </div>

            </div>

        </div>

    </header>



    <div class="mx-auto flex max-w-7xl">


        <!-- SIDEBAR -->
        <aside class="hidden min-h-screen w-64 border-r border-gray-200 bg-white lg:block">

            <div class="p-5">


                <!-- DASHBOARD -->
                <a
                    href="{{ route('logistics.dashboard') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="layout-dashboard" class="h-5 w-5"></i>

                    Dashboard

                </a>


                <!-- RIDER MANAGEMENT -->
                <a
                    href="{{ route('logistics.riders') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="bike" class="h-5 w-5"></i>

                    Rider Management

                </a>


                <!-- INCOMING PARCELS -->
                <a
                    href="{{ route('logistics.parcels') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl bg-[#E6F4EE] px-4 py-3 text-sm font-semibold text-[#1F6F5B]"
                >

                    <i data-lucide="package" class="h-5 w-5"></i>

                    Incoming Parcels

                </a>


                <!-- PARCEL SORTING -->
                <a
                    href="{{ route('logistics.sorting') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="arrow-down-up" class="h-5 w-5"></i>

                    Parcel Sorting

                </a>


                <!-- DELIVERY ASSIGNMENT -->
                <a
                    href="{{ route('logistics.assignments') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="map-pin" class="h-5 w-5"></i>

                    Delivery Assignment

                </a>


                <!-- DELIVERY MONITORING -->
                <a
                    href="{{ route('logistics.monitoring') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="truck" class="h-5 w-5"></i>

                    Delivery Monitoring

                </a>


                <div class="my-5 border-t border-gray-200"></div>


                <!-- REPORTS -->
                <a
                    href="{{ route('logistics.reports') }}"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="bar-chart-3" class="h-5 w-5"></i>

                    Reports

                </a>


                <!-- SETTINGS -->
                <a
                    href="#"
                    class="mb-2 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                >

                    <i data-lucide="settings" class="h-5 w-5"></i>

                    Account Settings

                </a>

            </div>

        </aside>



        <!-- MAIN CONTENT -->
        <main class="min-w-0 flex-1 p-5 sm:p-8 lg:p-10">


            <!-- PAGE HEADER -->
            <div class="mb-8">

                <div class="mb-2 flex items-center gap-2 text-sm text-[#1F6F5B]">

                    <i data-lucide="package" class="h-4 w-4"></i>

                    SUKI SHOP Logistics

                </div>


                <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">

                    Incoming Parcels

                </h1>


                <p class="mt-2 text-sm text-gray-500">

                    Receive and verify parcels delivered to the sorting center.

                </p>

            </div>



            <!-- PROCESS INFO -->
            <div class="mb-8 rounded-2xl border border-[#CFE9DD] bg-[#EEF8F3] p-5">

                <div class="flex gap-4">

                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                        <i data-lucide="scan-line" class="h-5 w-5"></i>

                    </div>


                    <div>

                        <h2 class="font-semibold text-[#155244]">

                            Parcel Receiving Process

                        </h2>


                        <p class="mt-1 text-sm leading-6 text-[#155244]">

                            Verify the parcel received from the pickup rider before
                            sending it to the parcel sorting process.

                        </p>

                    </div>

                </div>

            </div>



            <!-- STATS -->
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">


                <!-- TOTAL -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Incoming Parcels
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">

                                {{ isset($orders) ? count($orders) : 0 }}

                            </p>

                        </div>


                        <div class="rounded-xl bg-blue-50 p-3 text-blue-600">

                            <i data-lucide="package" class="h-6 w-6"></i>

                        </div>

                    </div>

                </div>



                <!-- READY TO RECEIVE -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Ready to Receive
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">

                                {{ isset($orders)
                                    ? collect($orders)
                                        ->where('status', 'picked_up')
                                        ->count()
                                    : 0
                                }}

                            </p>

                        </div>


                        <div class="rounded-xl bg-yellow-50 p-3 text-yellow-600">

                            <i data-lucide="clock" class="h-6 w-6"></i>

                        </div>

                    </div>

                </div>



                <!-- RECEIVED -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                At Sorting Center
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">

                                {{ isset($orders)
                                    ? collect($orders)
                                        ->where('status', 'at_sorting_center')
                                        ->count()
                                    : 0
                                }}

                            </p>

                        </div>


                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i data-lucide="circle-check" class="h-6 w-6"></i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- PARCEL TABLE -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">


                <!-- TABLE HEADER -->
                <div class="flex flex-col justify-between gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center">

                    <div>

                        <h2 class="font-semibold text-gray-900">

                            Parcel Receiving Queue

                        </h2>


                        <p class="mt-1 text-sm text-gray-500">

                            Parcels picked up by riders will appear here.

                        </p>

                    </div>


                    <!-- SEARCH -->
                    <div class="relative">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        ></i>


                        <input
                            type="text"
                            placeholder="Search parcel..."
                            class="w-full rounded-xl border border-gray-300 py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 sm:w-64"
                        >

                    </div>

                </div>



                <!-- TABLE -->
                <div class="overflow-x-auto">

                    <table class="w-full min-w-[850px] text-left">


                        <thead class="border-b border-gray-200 bg-gray-50">

                            <tr>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Order
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Customer
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Delivery Address
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Pickup Rider
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Status
                                </th>

                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Action
                                </th>

                            </tr>

                        </thead>



                        <tbody class="divide-y divide-gray-100">


                            @php

                                $incomingOrders = isset($orders)

                                    ? collect($orders)
                                        ->filter(function ($order) {

                                            return in_array(
                                                $order['status'] ?? '',
                                                [
                                                    'picked_up',
                                                    'at_sorting_center',
                                                ]
                                            );

                                        })

                                    : collect();

                            @endphp



                            @if($incomingOrders->count() > 0)


                                @foreach($incomingOrders as $index => $order)


                                    <tr class="transition hover:bg-gray-50">


                                        <!-- ORDER -->
                                        <td class="px-6 py-5">

                                            <div>

                                                <p class="text-sm font-semibold text-gray-900">

                                                    #{{ $order['order_number'] ?? ($index + 1) }}

                                                </p>


                                                <p class="mt-1 text-xs text-gray-500">

                                                    Parcel Order

                                                </p>

                                            </div>

                                        </td>



                                        <!-- CUSTOMER -->
                                        <td class="px-6 py-5">

                                            <p class="text-sm font-medium text-gray-800">

                                                {{ $order['customer_name']
                                                    ?? $order['buyer_name']
                                                    ?? 'Customer'
                                                }}

                                            </p>

                                        </td>



                                        <!-- ADDRESS -->
                                        <td class="px-6 py-5">

                                            <p class="max-w-xs text-sm text-gray-600">

                                                {{ $order['address']
                                                    ?? $order['delivery_address']
                                                    ?? 'Address not available'
                                                }}

                                            </p>

                                        </td>



                                        <!-- RIDER -->
                                        <td class="px-6 py-5">

                                            @if(isset($order['rider']))

                                                <div class="flex items-center gap-2">

                                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#E6F4EE] text-xs font-semibold text-[#1F6F5B]">

                                                        {{ strtoupper(
                                                            substr(
                                                                $order['rider']['first_name'] ?? 'R',
                                                                0,
                                                                1
                                                            )
                                                        ) }}

                                                    </div>


                                                    <div>

                                                        <p class="text-sm text-gray-700">

                                                            {{ $order['rider']['first_name'] ?? '' }}
                                                            {{ $order['rider']['last_name'] ?? '' }}

                                                        </p>


                                                        <p class="text-xs text-gray-500">

                                                            Pickup Rider

                                                        </p>

                                                    </div>

                                                </div>


                                            @else

                                                <span class="text-sm text-gray-400">

                                                    Not assigned

                                                </span>

                                            @endif

                                        </td>



                                        <!-- STATUS -->
                                        <td class="px-6 py-5">


                                            @if(($order['status'] ?? '') === 'picked_up')

                                                <span class="inline-flex rounded-full bg-yellow-50 px-3 py-1 text-xs font-semibold text-yellow-700">

                                                    Ready to Receive

                                                </span>


                                            @elseif(($order['status'] ?? '') === 'at_sorting_center')

                                                <span class="inline-flex rounded-full bg-[#E6F4EE] px-3 py-1 text-xs font-semibold text-[#1F6F5B]">

                                                    Received

                                                </span>

                                            @endif


                                        </td>



                                        <!-- ACTION -->
                                        <td class="px-6 py-5 text-right">


                                            @if(($order['status'] ?? '') === 'picked_up')

                                                <form
                                                    method="POST"
                                                    action="{{ route('logistics.parcel.receive', $index) }}"
                                                >

                                                    @csrf


                                                    <button
                                                        type="submit"
                                                        class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#155244]"
                                                    >

                                                        Receive Parcel

                                                    </button>

                                                </form>


                                            @else


                                                <a
                                                    href="{{ route('logistics.sorting') }}"
                                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                                >

                                                    Proceed to Sorting

                                                </a>


                                            @endif


                                        </td>


                                    </tr>


                                @endforeach



                            @else


                                <!-- EMPTY STATE -->
                                <tr>

                                    <td
                                        colspan="6"
                                        class="px-6 py-16 text-center"
                                    >

                                        <div class="mx-auto flex max-w-sm flex-col items-center">


                                            <div class="mb-4 rounded-2xl bg-[#E6F4EE] p-4 text-[#1F6F5B]">

                                                <i
                                                    data-lucide="package-open"
                                                    class="h-8 w-8"
                                                ></i>

                                            </div>


                                            <h3 class="font-semibold text-gray-900">

                                                No incoming parcels yet

                                            </h3>


                                            <p class="mt-2 text-sm leading-6 text-gray-500">

                                                Parcels picked up by SUKI SHOP Riders will
                                                automatically appear here for receiving
                                                and verification.

                                            </p>

                                        </div>

                                    </td>

                                </tr>


                            @endif


                        </tbody>

                    </table>

                </div>

            </section>


        </main>

    </div>

</div>

@endsection