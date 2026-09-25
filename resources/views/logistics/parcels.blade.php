@extends('layouts.logistics')

@section('title', 'Incoming Parcels')
@section('page-heading', 'Incoming Parcels')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE ORDERS
    |--------------------------------------------------------------------------
    */

    $allOrders =
        collect($orders ?? []);


    /*
    |--------------------------------------------------------------------------
    | INCOMING PARCEL QUEUE
    |--------------------------------------------------------------------------
    |
    | PICKED_UP
    | Parcel is currently being transported by the pickup rider
    | from Seller → Sorting Center.
    |
    | AT_SORTING_CENTER
    | Parcel already arrived at the Sorting Center and is ready
    | for Logistics sorting.
    |
    */

    $incomingOrders =
        $allOrders
            ->filter(function ($order) {

                return in_array(
                    strtolower(
                        $order['status']
                        ?? ''
                    ),
                    [
                        'picked_up',
                        'at_sorting_center',
                    ]
                );

            })
            ->reverse();


    $inTransitCount =
        $incomingOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'picked_up';

            })
            ->count();


    $receivedCount =
        $incomingOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'at_sorting_center';

            })
            ->count();


    /*
    |--------------------------------------------------------------------------
    | ADDRESS HELPER
    |--------------------------------------------------------------------------
    */

    $formatAddress =
        function ($order) {

            $address =
                $order['shipping_address']
                ?? [];


            if (!is_array($address)) {
                $address = [];
            }


            $parts =
                array_filter([
                    $address['house_number']
                        ?? null,

                    $address['street']
                        ?? null,

                    $address['barangay']
                        ?? null,

                    $address['municipality']
                        ?? null,

                    $address['province']
                        ?? null,

                    $address['postal_code']
                        ?? null,
                ]);


            if (!empty($parts)) {

                return implode(
                    ', ',
                    $parts
                );

            }


            return
                $order['delivery_address']
                ?? $order['address']
                ?? $order['buyer_address']
                ?? 'Delivery address unavailable.';

        };


    /*
    |--------------------------------------------------------------------------
    | CUSTOMER HELPER
    |--------------------------------------------------------------------------
    */

    $getCustomerName =
        function ($order) {

            return
                $order['shipping_address']['name']
                ?? $order['customer_name']
                ?? $order['buyer_name']
                ?? 'Customer';

        };


    /*
    |--------------------------------------------------------------------------
    | PICKUP RIDER HELPER
    |--------------------------------------------------------------------------
    |
    | pickup_rider_* is intentionally separate from rider_*.
    |
    | pickup rider:
    | Seller → Sorting Center
    |
    | final rider:
    | Sorting Center → Buyer
    |
    */

    $getPickupRiderName =
        function ($order) {

            if (
                !empty(
                    $order['pickup_rider_name']
                )
            ) {

                return
                    $order['pickup_rider_name'];

            }


            $pickupRider =
                $order['pickup_rider']
                ?? [];


            if (
                is_array($pickupRider)
            ) {

                $name =
                    trim(
                        (
                            $pickupRider['first_name']
                            ?? ''
                        )
                        . ' ' .
                        (
                            $pickupRider['last_name']
                            ?? ''
                        )
                    );


                if ($name !== '') {

                    return $name;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | LEGACY FALLBACK
            |--------------------------------------------------------------------------
            */

            $legacyRider =
                $order['rider']
                ?? [];


            if (
                is_array($legacyRider)
            ) {

                $name =
                    trim(
                        (
                            $legacyRider['first_name']
                            ?? ''
                        )
                        . ' ' .
                        (
                            $legacyRider['last_name']
                            ?? ''
                        )
                    );


                if ($name !== '') {

                    return $name;

                }

            }


            return 'Pickup Rider';

        };


    /*
    |--------------------------------------------------------------------------
    | SELLER HELPER
    |--------------------------------------------------------------------------
    */

    $getSellerName =
        function ($order) {

            return
                $order['seller_name']
                ?? $order['store_name']
                ?? $order['shop_name']
                ?? $order['business_name']
                ?? 'Seller';

        };

@endphp


{{-- =========================================================
    ALERTS
========================================================= --}}

@if(session('success'))

    <div
        class="
            mb-6
            flex items-start gap-3
            rounded-2xl
            border border-emerald-200
            bg-emerald-50
            px-4 py-3.5
        "
    >

        <div
            class="
                flex h-8 w-8
                shrink-0
                items-center justify-center
                rounded-xl
                bg-white
            "
        >

            <i
                data-lucide="check"
                class="
                    h-4 w-4
                    text-emerald-600
                "
            ></i>

        </div>


        <div>

            <p
                class="
                    text-xs
                    font-semibold
                    text-emerald-800
                "
            >
                Parcel updated
            </p>


            <p
                class="
                    mt-0.5
                    text-xs
                    leading-5
                    text-emerald-700
                "
            >
                {{ session('success') }}
            </p>

        </div>

    </div>

@endif


@if(session('error'))

    <div
        class="
            mb-6
            flex items-start gap-3
            rounded-2xl
            border border-red-200
            bg-red-50
            px-4 py-3.5
        "
    >

        <div
            class="
                flex h-8 w-8
                shrink-0
                items-center justify-center
                rounded-xl
                bg-white
            "
        >

            <i
                data-lucide="triangle-alert"
                class="
                    h-4 w-4
                    text-red-600
                "
            ></i>

        </div>


        <p
            class="
                pt-1
                text-xs
                leading-5
                text-red-700
            "
        >
            {{ session('error') }}
        </p>

    </div>

@endif


{{-- =========================================================
    PAGE INTRO
========================================================= --}}

<div
    class="
        mb-7
        flex flex-col gap-4
        lg:flex-row
        lg:items-end
        lg:justify-between
    "
>

    <div>

        <div
            class="
                mb-2
                flex items-center gap-2
                text-[10px]
                font-medium
                text-[#8A9791]
            "
        >

            <a
                href="{{ route('logistics.dashboard') }}"
                class="
                    transition
                    hover:text-[#1F6F5B]
                "
            >
                Dashboard
            </a>


            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>


            <span class="text-[#52635B]">
                Incoming Parcels
            </span>

        </div>


        <h2
            class="
                text-2xl
                font-semibold
                tracking-[-0.04em]
                text-[#24312C]
                sm:text-[28px]
            "
        >
            Incoming Parcels
        </h2>


        <p
            class="
                mt-1.5
                max-w-2xl
                text-sm
                leading-6
                text-[#728078]
            "
        >
            Track parcels traveling from sellers
            to the Sorting Center and verify which
            parcels are ready for destination sorting.
        </p>

    </div>


    <a
        href="{{ route('logistics.sorting') }}"
        class="
            inline-flex h-10
            items-center justify-center
            gap-2
            self-start
            rounded-xl
            bg-[#173F35]
            px-4
            text-[11px]
            font-semibold
            text-white
            transition
            hover:bg-[#1F6F5B]
            lg:self-auto
        "
    >

        <i
            data-lucide="scan-line"
            class="h-4 w-4"
        ></i>

        Open Parcel Sorting

    </a>

</div>


{{-- =========================================================
    RECEIVING FLOW
========================================================= --}}

<section
    class="
        mb-6
        overflow-hidden
        rounded-2xl
        border border-[#D9E6DF]
        bg-[#F1F8F4]
    "
>

    <div
        class="
            flex flex-col gap-5
            p-5
            lg:flex-row
            lg:items-center
            lg:justify-between
        "
    >

        <div
            class="
                flex items-start gap-4
            "
        >

            <div
                class="
                    flex h-10 w-10
                    shrink-0
                    items-center justify-center
                    rounded-xl
                    bg-white
                    text-[#1F6F5B]
                "
            >

                <i
                    data-lucide="package-check"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>


            <div>

                <p
                    class="
                        text-xs
                        font-semibold
                        text-[#294C42]
                    "
                >
                    Sorting Center Receiving Flow
                </p>


                <p
                    class="
                        mt-1
                        max-w-xl
                        text-[10px]
                        leading-5
                        text-[#6B8178]
                    "
                >
                    Pickup Rider brings the parcel
                    to the Sorting Center. Once it
                    arrives, Logistics can proceed
                    with destination checking and
                    parcel sorting.
                </p>

            </div>

        </div>


        <div
            class="
                flex flex-wrap
                items-center gap-2
            "
        >

            <span
                class="
                    rounded-full
                    border border-[#D3E4DB]
                    bg-white
                    px-2.5 py-1
                    text-[9px]
                    font-semibold
                    text-[#587067]
                "
            >
                Rider Pickup
            </span>


            <i
                data-lucide="arrow-right"
                class="
                    h-3 w-3
                    text-[#90A39A]
                "
            ></i>


            <span
                class="
                    rounded-full
                    border border-[#D3E4DB]
                    bg-white
                    px-2.5 py-1
                    text-[9px]
                    font-semibold
                    text-[#587067]
                "
            >
                Sorting Center
            </span>


            <i
                data-lucide="arrow-right"
                class="
                    h-3 w-3
                    text-[#90A39A]
                "
            ></i>


            <span
                class="
                    rounded-full
                    border border-[#D3E4DB]
                    bg-white
                    px-2.5 py-1
                    text-[9px]
                    font-semibold
                    text-[#587067]
                "
            >
                Read Address
            </span>


            <i
                data-lucide="arrow-right"
                class="
                    h-3 w-3
                    text-[#90A39A]
                "
            ></i>


            <span
                class="
                    rounded-full
                    border border-[#D3E4DB]
                    bg-white
                    px-2.5 py-1
                    text-[9px]
                    font-semibold
                    text-[#587067]
                "
            >
                Sort
            </span>

        </div>

    </div>

</section>


{{-- =========================================================
    SUMMARY
========================================================= --}}

<div
    class="
        mb-6
        grid grid-cols-1
        gap-4
        sm:grid-cols-3
    "
>


    {{-- QUEUE --}}
    <div
        class="
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
        "
    >

        <div
            class="
                flex items-start
                justify-between
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.12em]
                        text-[#839189]
                    "
                >
                    Incoming Queue
                </p>


                <p
                    class="
                        mt-3
                        text-2xl
                        font-semibold
                        tracking-[-0.04em]
                        text-[#24312C]
                    "
                >
                    {{ $incomingOrders->count() }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-[#EEF5F1]
                    text-[#1F6F5B]
                "
            >

                <i
                    data-lucide="packages"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="
                mt-5
                border-t border-[#EEF2F0]
                pt-3
                text-[10px]
                text-[#7B8982]
            "
        >
            Active incoming parcel records
        </p>

    </div>


    {{-- IN TRANSIT --}}
    <div
        class="
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
        "
    >

        <div
            class="
                flex items-start
                justify-between
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.12em]
                        text-[#839189]
                    "
                >
                    En Route to Center
                </p>


                <p
                    class="
                        mt-3
                        text-2xl
                        font-semibold
                        tracking-[-0.04em]
                        text-[#24312C]
                    "
                >
                    {{ $inTransitCount }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-cyan-50
                    text-cyan-700
                "
            >

                <i
                    data-lucide="bike"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="
                mt-5
                border-t border-[#EEF2F0]
                pt-3
                text-[10px]
                text-[#7B8982]
            "
        >
            Status: PICKED_UP
        </p>

    </div>


    {{-- RECEIVED --}}
    <div
        class="
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
        "
    >

        <div
            class="
                flex items-start
                justify-between
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.12em]
                        text-[#839189]
                    "
                >
                    Ready for Sorting
                </p>


                <p
                    class="
                        mt-3
                        text-2xl
                        font-semibold
                        tracking-[-0.04em]
                        text-[#24312C]
                    "
                >
                    {{ $receivedCount }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-emerald-50
                    text-emerald-700
                "
            >

                <i
                    data-lucide="warehouse"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="
                mt-5
                border-t border-[#EEF2F0]
                pt-3
                text-[10px]
                text-[#7B8982]
            "
        >
            Status: AT_SORTING_CENTER
        </p>

    </div>

</div>


{{-- =========================================================
    PARCEL QUEUE
========================================================= --}}

<section
    class="
        overflow-hidden
        rounded-2xl
        border border-[#E1E8E4]
        bg-white
    "
>


    {{-- HEADER --}}
    <div
        class="
            flex flex-col gap-4
            border-b border-[#EDF1EF]
            px-5 py-5
            lg:flex-row
            lg:items-center
            lg:justify-between
        "
    >

        <div>

            <h3
                class="
                    text-sm
                    font-semibold
                    text-[#24312C]
                "
            >
                Parcel Receiving Queue
            </h3>


            <p
                class="
                    mt-0.5
                    text-[10px]
                    text-[#7C8983]
                "
            >
                Parcels currently traveling to or
                already received by the Sorting Center.
            </p>

        </div>


        <div
            class="
                grid gap-2
                sm:grid-cols-[230px_160px]
            "
        >

            {{-- SEARCH --}}
            <div class="relative">

                <i
                    data-lucide="search"
                    class="
                        pointer-events-none
                        absolute left-3 top-1/2
                        h-3.5 w-3.5
                        -translate-y-1/2
                        text-[#91A099]
                    "
                ></i>


                <input
                    id="parcelSearch"
                    type="text"
                    placeholder="Search parcel..."
                    class="
                        h-9 w-full
                        rounded-xl
                        border border-[#DDE6E1]
                        bg-white
                        pl-9 pr-3
                        text-[10px]
                        text-[#34483F]
                        placeholder:text-[#9AA69F]
                        focus:border-[#1F6F5B]
                        focus:ring-4
                        focus:ring-[#DDF3EC]/60
                    "
                >

            </div>


            {{-- STATUS --}}
            <select
                id="parcelStatusFilter"
                class="
                    h-9
                    rounded-xl
                    border border-[#DDE6E1]
                    bg-white
                    px-3
                    text-[10px]
                    font-medium
                    text-[#52635B]
                    focus:border-[#1F6F5B]
                    focus:ring-4
                    focus:ring-[#DDF3EC]/60
                "
            >

                <option value="all">
                    All Parcels
                </option>

                <option value="picked_up">
                    En Route
                </option>

                <option value="at_sorting_center">
                    Received
                </option>

            </select>

        </div>

    </div>


    @if($incomingOrders->count())


        {{-- =================================================
            DESKTOP TABLE
        ================================================== --}}

        <div
            class="
                hidden
                overflow-x-auto
                lg:block
            "
        >

            <table class="w-full">

                <thead>

                    <tr
                        class="
                            border-b border-[#EDF1EF]
                            bg-[#F7F9F8]
                        "
                    >

                        <th class="px-5 py-3 text-left">
                            Parcel
                        </th>

                        <th class="px-5 py-3 text-left">
                            Seller
                        </th>

                        <th class="px-5 py-3 text-left">
                            Destination
                        </th>

                        <th class="px-5 py-3 text-left">
                            Pickup Rider
                        </th>

                        <th class="px-5 py-3 text-left">
                            Status
                        </th>

                        <th class="px-5 py-3 text-right">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach(
                        $incomingOrders
                        as $orderId => $order
                    )

                        @php

                            $resolvedOrderId =
                                $order['id']
                                ?? $orderId;


                            $displayOrderNumber =
                                $order['order_number']
                                ?? $resolvedOrderId;


                            $status =
                                strtolower(
                                    $order['status']
                                    ?? ''
                                );


                            $customerName =
                                $getCustomerName(
                                    $order
                                );


                            $sellerName =
                                $getSellerName(
                                    $order
                                );


                            $pickupRiderName =
                                $getPickupRiderName(
                                    $order
                                );


                            $address =
                                $formatAddress(
                                    $order
                                );


                            $searchValue =
                                strtolower(
                                    $displayOrderNumber
                                    . ' '
                                    . $customerName
                                    . ' '
                                    . $sellerName
                                    . ' '
                                    . $pickupRiderName
                                    . ' '
                                    . $address
                                );

                        @endphp


                        <tr
                            class="
                                parcel-row
                                border-b border-[#F0F3F1]
                                transition
                                last:border-b-0
                                hover:bg-[#FAFCFB]
                            "
                            data-search="{{ $searchValue }}"
                            data-status="{{ $status }}"
                        >

                            {{-- PARCEL --}}
                            <td class="px-5 py-4">

                                <div
                                    class="
                                        flex items-center gap-3
                                    "
                                >

                                    <div
                                        class="
                                            flex h-9 w-9
                                            shrink-0
                                            items-center justify-center
                                            rounded-xl
                                            bg-[#EEF5F1]
                                            text-[#1F6F5B]
                                        "
                                    >

                                        <i
                                            data-lucide="package"
                                            class="h-4 w-4"
                                        ></i>

                                    </div>


                                    <div>

                                        <p
                                            class="
                                                text-[11px]
                                                font-semibold
                                                text-[#34483F]
                                            "
                                        >
                                            #{{ $displayOrderNumber }}
                                        </p>


                                        <p
                                            class="
                                                mt-0.5
                                                text-[9px]
                                                text-[#98A39D]
                                            "
                                        >
                                            {{ $customerName }}
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- SELLER --}}
                            <td class="px-5 py-4">

                                <div
                                    class="
                                        flex items-center gap-2
                                    "
                                >

                                    <i
                                        data-lucide="store"
                                        class="
                                            h-3.5 w-3.5
                                            text-[#1F6F5B]
                                        "
                                    ></i>


                                    <span
                                        class="
                                            max-w-[170px]
                                            truncate
                                            text-[10px]
                                            font-medium
                                            text-[#52635B]
                                        "
                                    >
                                        {{ $sellerName }}
                                    </span>

                                </div>

                            </td>


                            {{-- DESTINATION --}}
                            <td class="px-5 py-4">

                                <div
                                    class="
                                        flex max-w-[270px]
                                        items-start gap-2
                                    "
                                >

                                    <i
                                        data-lucide="map-pin"
                                        class="
                                            mt-0.5
                                            h-3.5 w-3.5
                                            shrink-0
                                            text-[#1F6F5B]
                                        "
                                    ></i>


                                    <p
                                        class="
                                            text-[10px]
                                            leading-5
                                            text-[#74827B]
                                        "
                                    >
                                        {{ $address }}
                                    </p>

                                </div>

                            </td>


                            {{-- PICKUP RIDER --}}
                            <td class="px-5 py-4">

                                <div
                                    class="
                                        flex items-center gap-2.5
                                    "
                                >

                                    <div
                                        class="
                                            flex h-8 w-8
                                            shrink-0
                                            items-center justify-center
                                            rounded-full
                                            bg-[#DDF3EC]
                                            text-[10px]
                                            font-semibold
                                            text-[#173F35]
                                        "
                                    >
                                        {{ strtoupper(
                                            substr(
                                                $pickupRiderName,
                                                0,
                                                1
                                            )
                                        ) }}
                                    </div>


                                    <div>

                                        <p
                                            class="
                                                max-w-[150px]
                                                truncate
                                                text-[10px]
                                                font-semibold
                                                text-[#52635B]
                                            "
                                        >
                                            {{ $pickupRiderName }}
                                        </p>


                                        <p
                                            class="
                                                mt-0.5
                                                text-[8px]
                                                text-[#98A39D]
                                            "
                                        >
                                            Seller → Sorting Center
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- STATUS --}}
                            <td class="px-5 py-4">

                                @if(
                                    $status ===
                                    'picked_up'
                                )

                                    <span
                                        class="
                                            inline-flex
                                            items-center gap-1.5
                                            rounded-full
                                            border border-cyan-200
                                            bg-cyan-50
                                            px-2.5 py-1
                                            text-[9px]
                                            font-semibold
                                            text-cyan-700
                                        "
                                    >

                                        <i
                                            data-lucide="bike"
                                            class="h-3 w-3"
                                        ></i>

                                        En Route

                                    </span>


                                @elseif(
                                    $status ===
                                    'at_sorting_center'
                                )

                                    <span
                                        class="
                                            inline-flex
                                            items-center gap-1.5
                                            rounded-full
                                            border border-emerald-200
                                            bg-emerald-50
                                            px-2.5 py-1
                                            text-[9px]
                                            font-semibold
                                            text-emerald-700
                                        "
                                    >

                                        <i
                                            data-lucide="warehouse"
                                            class="h-3 w-3"
                                        ></i>

                                        At Sorting Center

                                    </span>

                                @endif

                            </td>


                            {{-- ACTION --}}
                            <td
                                class="
                                    px-5 py-4
                                    text-right
                                "
                            >

                                @if(
                                    $status ===
                                    'at_sorting_center'
                                )

                                    <a
                                        href="{{ route('logistics.sorting') }}"
                                        class="
                                            inline-flex h-9
                                            items-center justify-center
                                            gap-2
                                            rounded-xl
                                            bg-[#173F35]
                                            px-3
                                            text-[9px]
                                            font-semibold
                                            text-white
                                            transition
                                            hover:bg-[#1F6F5B]
                                        "
                                    >

                                        Sort Parcel

                                        <i
                                            data-lucide="arrow-right"
                                            class="h-3 w-3"
                                        ></i>

                                    </a>


                                @else

                                    <span
                                        class="
                                            inline-flex h-9
                                            items-center justify-center
                                            gap-2
                                            rounded-xl
                                            border border-[#E2E8E4]
                                            bg-[#F7F9F8]
                                            px-3
                                            text-[9px]
                                            font-medium
                                            text-[#87948E]
                                        "
                                    >

                                        <i
                                            data-lucide="clock-3"
                                            class="h-3 w-3"
                                        ></i>

                                        Awaiting Arrival

                                    </span>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{-- =================================================
            MOBILE / TABLET CARDS
        ================================================== --}}

        <div
            class="
                divide-y divide-[#EDF1EF]
                lg:hidden
            "
        >

            @foreach(
                $incomingOrders
                as $orderId => $order
            )

                @php

                    $resolvedOrderId =
                        $order['id']
                        ?? $orderId;


                    $displayOrderNumber =
                        $order['order_number']
                        ?? $resolvedOrderId;


                    $status =
                        strtolower(
                            $order['status']
                            ?? ''
                        );


                    $customerName =
                        $getCustomerName(
                            $order
                        );


                    $sellerName =
                        $getSellerName(
                            $order
                        );


                    $pickupRiderName =
                        $getPickupRiderName(
                            $order
                        );


                    $address =
                        $formatAddress(
                            $order
                        );


                    $searchValue =
                        strtolower(
                            $displayOrderNumber
                            . ' '
                            . $customerName
                            . ' '
                            . $sellerName
                            . ' '
                            . $pickupRiderName
                            . ' '
                            . $address
                        );

                @endphp


                <article
                    class="
                        parcel-card
                        p-4
                        sm:p-5
                    "
                    data-search="{{ $searchValue }}"
                    data-status="{{ $status }}"
                >

                    <div
                        class="
                            flex
                            items-start
                            justify-between
                            gap-4
                        "
                    >

                        <div
                            class="
                                flex min-w-0
                                items-center gap-3
                            "
                        >

                            <div
                                class="
                                    flex h-10 w-10
                                    shrink-0
                                    items-center justify-center
                                    rounded-xl
                                    bg-[#EEF5F1]
                                    text-[#1F6F5B]
                                "
                            >

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <div class="min-w-0">

                                <p
                                    class="
                                        text-xs
                                        font-semibold
                                        text-[#34483F]
                                    "
                                >
                                    Parcel #{{ $displayOrderNumber }}
                                </p>


                                <p
                                    class="
                                        mt-1 truncate
                                        text-[10px]
                                        text-[#7C8983]
                                    "
                                >
                                    {{ $customerName }}
                                </p>

                            </div>

                        </div>


                        @if(
                            $status ===
                            'picked_up'
                        )

                            <span
                                class="
                                    inline-flex
                                    shrink-0
                                    items-center gap-1
                                    rounded-full
                                    border border-cyan-200
                                    bg-cyan-50
                                    px-2 py-1
                                    text-[8px]
                                    font-semibold
                                    text-cyan-700
                                "
                            >
                                En Route
                            </span>


                        @else

                            <span
                                class="
                                    inline-flex
                                    shrink-0
                                    items-center gap-1
                                    rounded-full
                                    border border-emerald-200
                                    bg-emerald-50
                                    px-2 py-1
                                    text-[8px]
                                    font-semibold
                                    text-emerald-700
                                "
                            >
                                Received
                            </span>

                        @endif

                    </div>


                    {{-- INFO --}}
                    <div
                        class="
                            mt-4
                            grid gap-2
                            sm:grid-cols-2
                        "
                    >

                        <div
                            class="
                                rounded-xl
                                bg-[#F7F9F8]
                                p-3
                            "
                        >

                            <p
                                class="
                                    text-[8px]
                                    uppercase
                                    tracking-[0.08em]
                                    text-[#98A39D]
                                "
                            >
                                Seller
                            </p>


                            <p
                                class="
                                    mt-1
                                    text-[10px]
                                    font-semibold
                                    text-[#52635B]
                                "
                            >
                                {{ $sellerName }}
                            </p>

                        </div>


                        <div
                            class="
                                rounded-xl
                                bg-[#F7F9F8]
                                p-3
                            "
                        >

                            <p
                                class="
                                    text-[8px]
                                    uppercase
                                    tracking-[0.08em]
                                    text-[#98A39D]
                                "
                            >
                                Pickup Rider
                            </p>


                            <p
                                class="
                                    mt-1
                                    text-[10px]
                                    font-semibold
                                    text-[#52635B]
                                "
                            >
                                {{ $pickupRiderName }}
                            </p>

                        </div>

                    </div>


                    {{-- ADDRESS --}}
                    <div
                        class="
                            mt-3
                            rounded-xl
                            border border-[#EDF1EF]
                            p-3
                        "
                    >

                        <div
                            class="
                                flex items-start gap-2
                            "
                        >

                            <i
                                data-lucide="map-pin"
                                class="
                                    mt-0.5
                                    h-3.5 w-3.5
                                    shrink-0
                                    text-[#1F6F5B]
                                "
                            ></i>


                            <p
                                class="
                                    text-[10px]
                                    leading-5
                                    text-[#65756D]
                                "
                            >
                                {{ $address }}
                            </p>

                        </div>

                    </div>


                    {{-- ACTION --}}
                    <div class="mt-4">

                        @if(
                            $status ===
                            'at_sorting_center'
                        )

                            <a
                                href="{{ route('logistics.sorting') }}"
                                class="
                                    inline-flex h-10
                                    w-full
                                    items-center justify-center
                                    gap-2
                                    rounded-xl
                                    bg-[#173F35]
                                    text-[10px]
                                    font-semibold
                                    text-white
                                    transition
                                    hover:bg-[#1F6F5B]
                                "
                            >

                                <i
                                    data-lucide="scan-line"
                                    class="h-4 w-4"
                                ></i>

                                Proceed to Parcel Sorting

                            </a>


                        @else

                            <div
                                class="
                                    flex h-10
                                    items-center justify-center
                                    gap-2
                                    rounded-xl
                                    border border-[#E2E8E4]
                                    bg-[#F7F9F8]
                                    text-[10px]
                                    font-medium
                                    text-[#87948E]
                                "
                            >

                                <i
                                    data-lucide="clock-3"
                                    class="h-4 w-4"
                                ></i>

                                Waiting for Rider Arrival

                            </div>

                        @endif

                    </div>

                </article>

            @endforeach

        </div>


        {{-- FILTER EMPTY --}}
        <div
            id="noParcelResults"
            class="
                hidden
                px-6 py-14
                text-center
            "
        >

            <div
                class="
                    mx-auto
                    flex h-12 w-12
                    items-center justify-center
                    rounded-2xl
                    bg-[#F1F4F2]
                    text-[#87958E]
                "
            >

                <i
                    data-lucide="search-x"
                    class="h-5 w-5"
                ></i>

            </div>


            <p
                class="
                    mt-4
                    text-sm
                    font-semibold
                    text-[#34483F]
                "
            >
                No matching parcels
            </p>


            <p
                class="
                    mt-1
                    text-xs
                    text-[#849089]
                "
            >
                Try another parcel, rider,
                seller, or status.
            </p>

        </div>


    @else

        {{-- EMPTY --}}
        <div
            class="
                px-6 py-16
                text-center
            "
        >

            <div
                class="
                    mx-auto
                    flex h-14 w-14
                    items-center justify-center
                    rounded-2xl
                    bg-[#EEF5F1]
                    text-[#1F6F5B]
                "
            >

                <i
                    data-lucide="package-open"
                    class="h-6 w-6"
                ></i>

            </div>


            <h3
                class="
                    mt-4
                    text-sm
                    font-semibold
                    text-[#34483F]
                "
            >
                No incoming parcels
            </h3>


            <p
                class="
                    mx-auto mt-1
                    max-w-md
                    text-xs
                    leading-5
                    text-[#849089]
                "
            >
                Parcels will automatically appear
                here after a pickup rider collects
                them from a seller.
            </p>

        </div>

    @endif

</section>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const search =
            document.getElementById(
                'parcelSearch'
            );


        const status =
            document.getElementById(
                'parcelStatusFilter'
            );


        const rows =
            Array.from(
                document.querySelectorAll(
                    '.parcel-row'
                )
            );


        const cards =
            Array.from(
                document.querySelectorAll(
                    '.parcel-card'
                )
            );


        const noResults =
            document.getElementById(
                'noParcelResults'
            );


        function itemMatches(
            item,
            searchValue,
            statusValue
        ) {

            const searchable =
                (
                    item.dataset.search
                    || ''
                )
                .toLowerCase();


            const itemStatus =
                item.dataset.status
                || '';


            const searchMatches =
                searchValue === ''
                ||
                searchable.includes(
                    searchValue
                );


            const statusMatches =
                statusValue === 'all'
                ||
                itemStatus ===
                    statusValue;


            return (
                searchMatches
                &&
                statusMatches
            );

        }


        function filterParcels() {

            const searchValue =
                (
                    search?.value
                    || ''
                )
                .trim()
                .toLowerCase();


            const statusValue =
                status?.value
                || 'all';


            let visibleRows = 0;
            let visibleCards = 0;


            rows.forEach(
                function (row) {

                    const visible =
                        itemMatches(
                            row,
                            searchValue,
                            statusValue
                        );


                    row.classList.toggle(
                        'hidden',
                        !visible
                    );


                    if (visible) {
                        visibleRows++;
                    }

                }
            );


            cards.forEach(
                function (card) {

                    const visible =
                        itemMatches(
                            card,
                            searchValue,
                            statusValue
                        );


                    card.classList.toggle(
                        'hidden',
                        !visible
                    );


                    if (visible) {
                        visibleCards++;
                    }

                }
            );


            const hasData =
                rows.length > 0
                ||
                cards.length > 0;


            const hasVisible =
                visibleRows > 0
                ||
                visibleCards > 0;


            noResults?.classList.toggle(
                'hidden',
                !hasData
                ||
                hasVisible
            );

        }


        search?.addEventListener(
            'input',
            filterParcels
        );


        status?.addEventListener(
            'change',
            filterParcels
        );


        if (
            typeof lucide !== 'undefined'
            &&
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