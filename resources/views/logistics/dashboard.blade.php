@extends('layouts.logistics')

@section('title', 'Dashboard')
@section('page-heading', 'Logistics Dashboard')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    |
    | Current Logistics implementation still reads the shared session orders.
    | No fake dashboard numbers are used here.
    |
    */

    $orders =
        collect(
            session('orders', [])
        );


    /*
    |--------------------------------------------------------------------------
    | RIDER APPLICATIONS
    |--------------------------------------------------------------------------
    */

    $riders =
        collect(
            session(
                'rider_applications',
                []
            )
        )
        ->filter(
            fn ($rider) =>
                is_array($rider)
        );


    /*
    |--------------------------------------------------------------------------
    | PARCEL PIPELINE
    |--------------------------------------------------------------------------
    */

    $atSortingCenter =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'at_sorting_center'
            )
            ->count();


    $sorted =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'sorted'
            )
            ->count();


    $assigned =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'assigned_to_rider'
            )
            ->count();


    $outForDelivery =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'out_for_delivery'
            )
            ->count();


    $delivered =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'delivered'
            )
            ->count();


    $failed =
        $orders
            ->filter(
                fn ($order) =>
                    strtolower(
                        $order['status']
                        ?? ''
                    )
                    === 'delivery_failed'
            )
            ->count();


    /*
    |--------------------------------------------------------------------------
    | RIDERS
    |--------------------------------------------------------------------------
    */

    $pendingRiders =
        $riders
            ->filter(
                fn ($rider) =>
                    strtolower(
                        $rider['status']
                        ?? ''
                    )
                    === 'pending'
            )
            ->count();


    $approvedRiders =
        $riders
            ->filter(
                fn ($rider) =>
                    strtolower(
                        $rider['status']
                        ?? ''
                    )
                    === 'approved'
            )
            ->count();


    $disapprovedRiders =
        $riders
            ->filter(
                fn ($rider) =>
                    strtolower(
                        $rider['status']
                        ?? ''
                    )
                    === 'disapproved'
            )
            ->count();


    /*
    |--------------------------------------------------------------------------
    | RIDERS CURRENTLY ON DELIVERY
    |--------------------------------------------------------------------------
    */

    $busyRiderIndexes =
        $orders
            ->filter(
                fn ($order) =>
                    in_array(
                        strtolower(
                            $order['status']
                            ?? ''
                        ),
                        [
                            'assigned_to_rider',
                            'out_for_delivery',
                        ]
                    )
            )
            ->pluck('rider_index')
            ->filter(
                fn ($index) =>
                    $index !== null
            )
            ->unique();


    $busyRiders =
        $busyRiderIndexes
            ->count();


    $availableRiders =
        max(
            0,
            $approvedRiders
            - $busyRiders
        );


    /*
    |--------------------------------------------------------------------------
    | RECENT LOGISTICS PARCELS
    |--------------------------------------------------------------------------
    */

    $recentOrders =
        $orders
            ->filter(
                function ($order) {

                    return in_array(
                        strtolower(
                            $order['status']
                            ?? ''
                        ),
                        [
                            'at_sorting_center',
                            'sorted',
                            'assigned_to_rider',
                            'out_for_delivery',
                            'delivered',
                            'delivery_failed',
                            'returned',
                        ]
                    );

                }
            )
            ->reverse()
            ->take(6);


    /*
    |--------------------------------------------------------------------------
    | STATUS DESIGN
    |--------------------------------------------------------------------------
    */

    $statusConfig = [

        'at_sorting_center' => [
            'label' =>
                'At Sorting Center',

            'class' =>
                'border-sky-200 bg-sky-50 text-sky-700',

            'icon' =>
                'warehouse',
        ],


        'sorted' => [
            'label' =>
                'Sorted',

            'class' =>
                'border-amber-200 bg-amber-50 text-amber-700',

            'icon' =>
                'scan-line',
        ],


        'assigned_to_rider' => [
            'label' =>
                'Assigned to Rider',

            'class' =>
                'border-violet-200 bg-violet-50 text-violet-700',

            'icon' =>
                'user-check',
        ],


        'out_for_delivery' => [
            'label' =>
                'Out for Delivery',

            'class' =>
                'border-orange-200 bg-orange-50 text-orange-700',

            'icon' =>
                'bike',
        ],


        'delivered' => [
            'label' =>
                'Delivered',

            'class' =>
                'border-emerald-200 bg-emerald-50 text-emerald-700',

            'icon' =>
                'map-pin-check',
        ],


        'delivery_failed' => [
            'label' =>
                'Delivery Failed',

            'class' =>
                'border-red-200 bg-red-50 text-red-700',

            'icon' =>
                'triangle-alert',
        ],


        'returned' => [
            'label' =>
                'Returned',

            'class' =>
                'border-rose-200 bg-rose-50 text-rose-700',

            'icon' =>
                'rotate-ccw',
        ],

    ];


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
                    $address['barangay']
                        ?? null,

                    $address['municipality']
                        ?? null,

                    $address['province']
                        ?? null,
                ]);


            if (!empty($parts)) {

                return implode(
                    ', ',
                    $parts
                );
            }


            return
                $order['address']
                ?? 'Destination unavailable';

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
                Operation updated
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

        <p
            class="
                text-[10px]
                font-semibold
                uppercase
                tracking-[0.13em]
                text-[#1F6F5B]
            "
        >
            Sorting Center Operations
        </p>


        <h2
            class="
                mt-1
                text-2xl
                font-semibold
                tracking-[-0.04em]
                text-[#24312C]
                sm:text-[28px]
            "
        >
            Operations Overview
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
            Monitor incoming parcels, sorting,
            rider assignments, and active deliveries
            from one workspace.
        </p>

    </div>


    <div
        class="
            flex flex-col gap-2
            sm:flex-row
            lg:justify-end
        "
    >

        <a
            href="{{ route('logistics.parcels') }}"
            class="
                inline-flex h-10
                items-center justify-center
                gap-2
                rounded-xl
                border border-[#DDE6E1]
                bg-white
                px-4
                text-[11px]
                font-semibold
                text-[#52635B]
                transition
                hover:bg-[#F3F7F5]
            "
        >

            <i
                data-lucide="package-check"
                class="h-4 w-4"
            ></i>

            Incoming Parcels

        </a>


        <a
            href="{{ route('logistics.assignments') }}"
            class="
                inline-flex h-10
                items-center justify-center
                gap-2
                rounded-xl
                bg-[#173F35]
                px-4
                text-[11px]
                font-semibold
                text-white
                transition
                hover:bg-[#1F6F5B]
            "
        >

            <i
                data-lucide="map-pinned"
                class="h-4 w-4"
            ></i>

            Assign Deliveries

        </a>

    </div>

</div>


{{-- =========================================================
    OPERATIONS ALERT
========================================================= --}}

@if(
    $failed > 0 ||
    $pendingRiders > 0 ||
    $sorted > 0
)

    <div
        class="
            mb-6
            grid gap-3
            md:grid-cols-3
        "
    >

        @if($sorted > 0)

            <a
                href="{{ route('logistics.assignments') }}"
                class="
                    group
                    flex items-start gap-3
                    rounded-2xl
                    border border-amber-200
                    bg-amber-50
                    p-4
                    transition
                    hover:border-amber-300
                "
            >

                <div
                    class="
                        flex h-9 w-9
                        shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-white
                        text-amber-700
                    "
                >

                    <i
                        data-lucide="user-plus"
                        class="h-4 w-4"
                    ></i>

                </div>


                <div class="min-w-0 flex-1">

                    <p
                        class="
                            text-[10px]
                            font-semibold
                            text-amber-800
                        "
                    >
                        Rider assignment needed
                    </p>


                    <p
                        class="
                            mt-1
                            text-[9px]
                            leading-5
                            text-amber-700
                        "
                    >
                        {{ $sorted }}
                        sorted parcel{{ $sorted === 1 ? '' : 's' }}
                        waiting for a delivery rider.
                    </p>

                </div>


                <i
                    data-lucide="arrow-right"
                    class="
                        mt-1 h-4 w-4
                        text-amber-600
                        transition
                        group-hover:translate-x-1
                    "
                ></i>

            </a>

        @endif


        @if($pendingRiders > 0)

            <a
                href="{{ route('logistics.riders') }}"
                class="
                    group
                    flex items-start gap-3
                    rounded-2xl
                    border border-violet-200
                    bg-violet-50
                    p-4
                    transition
                    hover:border-violet-300
                "
            >

                <div
                    class="
                        flex h-9 w-9
                        shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-white
                        text-violet-700
                    "
                >

                    <i
                        data-lucide="bike"
                        class="h-4 w-4"
                    ></i>

                </div>


                <div class="min-w-0 flex-1">

                    <p
                        class="
                            text-[10px]
                            font-semibold
                            text-violet-800
                        "
                    >
                        Rider applications
                    </p>


                    <p
                        class="
                            mt-1
                            text-[9px]
                            leading-5
                            text-violet-700
                        "
                    >
                        {{ $pendingRiders }}
                        application{{ $pendingRiders === 1 ? '' : 's' }}
                        awaiting Logistics review.
                    </p>

                </div>


                <i
                    data-lucide="arrow-right"
                    class="
                        mt-1 h-4 w-4
                        text-violet-600
                        transition
                        group-hover:translate-x-1
                    "
                ></i>

            </a>

        @endif


        @if($failed > 0)

            <a
                href="{{ route('logistics.monitoring') }}"
                class="
                    group
                    flex items-start gap-3
                    rounded-2xl
                    border border-red-200
                    bg-red-50
                    p-4
                    transition
                    hover:border-red-300
                "
            >

                <div
                    class="
                        flex h-9 w-9
                        shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-white
                        text-red-600
                    "
                >

                    <i
                        data-lucide="triangle-alert"
                        class="h-4 w-4"
                    ></i>

                </div>


                <div class="min-w-0 flex-1">

                    <p
                        class="
                            text-[10px]
                            font-semibold
                            text-red-800
                        "
                    >
                        Failed deliveries
                    </p>


                    <p
                        class="
                            mt-1
                            text-[9px]
                            leading-5
                            text-red-700
                        "
                    >
                        {{ $failed }}
                        delivery{{ $failed === 1 ? '' : 'ies' }}
                        require Logistics review.
                    </p>

                </div>


                <i
                    data-lucide="arrow-right"
                    class="
                        mt-1 h-4 w-4
                        text-red-600
                        transition
                        group-hover:translate-x-1
                    "
                ></i>

            </a>

        @endif

    </div>

@endif


{{-- =========================================================
    MAIN STATISTICS
========================================================= --}}

<div
    class="
        mb-6
        grid grid-cols-2
        gap-4
        xl:grid-cols-5
    "
>


    {{-- AT SORTING CENTER --}}
    <a
        href="{{ route('logistics.parcels') }}"
        class="
            group
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
            transition
            hover:border-[#C9D9D1]
        "
    >

        <div
            class="
                flex items-start
                justify-between gap-3
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.11em]
                        text-[#839189]
                    "
                >
                    At Sorting Center
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
                    {{ $atSortingCenter }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-sky-50
                    text-sky-700
                "
            >

                <i
                    data-lucide="warehouse"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <div
            class="
                mt-5
                flex items-center
                justify-between
                border-t
                border-[#EEF2F0]
                pt-3
            "
        >

            <span
                class="
                    text-[9px]
                    text-[#87948E]
                "
            >
                Waiting for sorting
            </span>


            <i
                data-lucide="arrow-up-right"
                class="
                    h-3.5 w-3.5
                    text-[#A2ADA7]
                    transition
                    group-hover:text-[#1F6F5B]
                "
            ></i>

        </div>

    </a>


    {{-- SORTED --}}
    <a
        href="{{ route('logistics.assignments') }}"
        class="
            group
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
            transition
            hover:border-[#C9D9D1]
        "
    >

        <div
            class="
                flex items-start
                justify-between gap-3
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.11em]
                        text-[#839189]
                    "
                >
                    Ready to Assign
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
                    {{ $sorted }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-amber-50
                    text-amber-700
                "
            >

                <i
                    data-lucide="map-pinned"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="
                mt-5
                border-t
                border-[#EEF2F0]
                pt-3
                text-[9px]
                text-[#87948E]
            "
        >
            Sorted by destination
        </p>

    </a>


    {{-- ASSIGNED --}}
    <a
        href="{{ route('logistics.shipments') }}"
        class="
            group
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
            transition
            hover:border-[#C9D9D1]
        "
    >

        <div
            class="
                flex items-start
                justify-between gap-3
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.11em]
                        text-[#839189]
                    "
                >
                    Rider Assigned
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
                    {{ $assigned }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-violet-50
                    text-violet-700
                "
            >

                <i
                    data-lucide="user-check"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="
                mt-5
                border-t
                border-[#EEF2F0]
                pt-3
                text-[9px]
                text-[#87948E]
            "
        >
            Waiting for rider pickup
        </p>

    </a>


    {{-- OUT FOR DELIVERY --}}
    <a
        href="{{ route('logistics.monitoring') }}"
        class="
            group
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
            transition
            hover:border-[#C9D9D1]
        "
    >

        <div
            class="
                flex items-start
                justify-between gap-3
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.11em]
                        text-[#839189]
                    "
                >
                    Out for Delivery
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
                    {{ $outForDelivery }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-orange-50
                    text-orange-700
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
                border-t
                border-[#EEF2F0]
                pt-3
                text-[9px]
                text-[#87948E]
            "
        >
            Currently with riders
        </p>

    </a>


    {{-- RIDER APPLICATIONS --}}
    <a
        href="{{ route('logistics.riders') }}"
        class="
            group
            col-span-2
            rounded-2xl
            border border-[#E1E8E4]
            bg-white
            p-5
            transition
            hover:border-[#C9D9D1]
            xl:col-span-1
        "
    >

        <div
            class="
                flex items-start
                justify-between gap-3
            "
        >

            <div>

                <p
                    class="
                        text-[9px]
                        font-semibold
                        uppercase
                        tracking-[0.11em]
                        text-[#839189]
                    "
                >
                    Rider Applications
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
                    {{ $pendingRiders }}
                </p>

            </div>


            <div
                class="
                    flex h-10 w-10
                    items-center justify-center
                    rounded-xl
                    bg-[#DDF3EC]
                    text-[#173F35]
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
                border-t
                border-[#EEF2F0]
                pt-3
                text-[9px]
                text-[#87948E]
            "
        >
            Awaiting review
        </p>

    </a>

</div>


{{-- =========================================================
    WORKSPACE
========================================================= --}}

<div
    class="
        grid gap-6
        xl:grid-cols-[1.45fr_.8fr]
    "
>


    {{-- =====================================================
        LEFT COLUMN
    ====================================================== --}}

    <div class="space-y-6">


        {{-- =================================================
            OPERATIONS FLOW
        ================================================== --}}

        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-[#E1E8E4]
                bg-white
            "
        >

            <div
                class="
                    flex items-center
                    justify-between gap-4
                    border-b
                    border-[#EDF1EF]
                    px-5 py-4
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
                        Sorting Center Workflow
                    </h3>


                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-[#7C8983]
                        "
                    >
                        Current parcel fulfillment sequence.
                    </p>

                </div>


                <div
                    class="
                        flex h-9 w-9
                        items-center justify-center
                        rounded-xl
                        bg-[#EEF5F1]
                        text-[#1F6F5B]
                    "
                >

                    <i
                        data-lucide="workflow"
                        class="h-4 w-4"
                    ></i>

                </div>

            </div>


            <div class="p-5">

                <div
                    class="
                        grid gap-3
                        sm:grid-cols-2
                        xl:grid-cols-4
                    "
                >

                    {{-- RECEIVE --}}
                    <a
                        href="{{ route('logistics.parcels') }}"
                        class="
                            group
                            rounded-2xl
                            border border-[#E7ECE9]
                            bg-[#FAFCFB]
                            p-4
                            transition
                            hover:border-[#BCD4C8]
                            hover:bg-[#F4F9F6]
                        "
                    >

                        <div
                            class="
                                flex items-center
                                justify-between
                            "
                        >

                            <div
                                class="
                                    flex h-9 w-9
                                    items-center justify-center
                                    rounded-xl
                                    bg-sky-50
                                    text-sky-700
                                "
                            >

                                <i
                                    data-lucide="package-check"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <span
                                class="
                                    text-[9px]
                                    font-semibold
                                    text-[#99A59F]
                                "
                            >
                                01
                            </span>

                        </div>


                        <p
                            class="
                                mt-4
                                text-[11px]
                                font-semibold
                                text-[#34483F]
                            "
                        >
                            Receive Parcel
                        </p>


                        <p
                            class="
                                mt-1
                                text-[9px]
                                leading-5
                                text-[#849089]
                            "
                        >
                            Verify parcel arrival
                            from the pickup rider.
                        </p>

                    </a>


                    {{-- SORT --}}
                    <a
                        href="{{ route('logistics.sorting') }}"
                        class="
                            group
                            rounded-2xl
                            border border-[#E7ECE9]
                            bg-[#FAFCFB]
                            p-4
                            transition
                            hover:border-[#BCD4C8]
                            hover:bg-[#F4F9F6]
                        "
                    >

                        <div
                            class="
                                flex items-center
                                justify-between
                            "
                        >

                            <div
                                class="
                                    flex h-9 w-9
                                    items-center justify-center
                                    rounded-xl
                                    bg-amber-50
                                    text-amber-700
                                "
                            >

                                <i
                                    data-lucide="scan-line"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <span
                                class="
                                    text-[9px]
                                    font-semibold
                                    text-[#99A59F]
                                "
                            >
                                02
                            </span>

                        </div>


                        <p
                            class="
                                mt-4
                                text-[11px]
                                font-semibold
                                text-[#34483F]
                            "
                        >
                            Sort by Area
                        </p>


                        <p
                            class="
                                mt-1
                                text-[9px]
                                leading-5
                                text-[#849089]
                            "
                        >
                            Read destination and
                            determine delivery area.
                        </p>

                    </a>


                    {{-- ASSIGN --}}
                    <a
                        href="{{ route('logistics.assignments') }}"
                        class="
                            group
                            rounded-2xl
                            border border-[#E7ECE9]
                            bg-[#FAFCFB]
                            p-4
                            transition
                            hover:border-[#BCD4C8]
                            hover:bg-[#F4F9F6]
                        "
                    >

                        <div
                            class="
                                flex items-center
                                justify-between
                            "
                        >

                            <div
                                class="
                                    flex h-9 w-9
                                    items-center justify-center
                                    rounded-xl
                                    bg-violet-50
                                    text-violet-700
                                "
                            >

                                <i
                                    data-lucide="user-round-check"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <span
                                class="
                                    text-[9px]
                                    font-semibold
                                    text-[#99A59F]
                                "
                            >
                                03
                            </span>

                        </div>


                        <p
                            class="
                                mt-4
                                text-[11px]
                                font-semibold
                                text-[#34483F]
                            "
                        >
                            Assign Rider
                        </p>


                        <p
                            class="
                                mt-1
                                text-[9px]
                                leading-5
                                text-[#849089]
                            "
                        >
                            Match parcel with an
                            approved rider for the area.
                        </p>

                    </a>


                    {{-- MONITOR --}}
                    <a
                        href="{{ route('logistics.monitoring') }}"
                        class="
                            group
                            rounded-2xl
                            border border-[#E7ECE9]
                            bg-[#FAFCFB]
                            p-4
                            transition
                            hover:border-[#BCD4C8]
                            hover:bg-[#F4F9F6]
                        "
                    >

                        <div
                            class="
                                flex items-center
                                justify-between
                            "
                        >

                            <div
                                class="
                                    flex h-9 w-9
                                    items-center justify-center
                                    rounded-xl
                                    bg-emerald-50
                                    text-emerald-700
                                "
                            >

                                <i
                                    data-lucide="route"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <span
                                class="
                                    text-[9px]
                                    font-semibold
                                    text-[#99A59F]
                                "
                            >
                                04
                            </span>

                        </div>


                        <p
                            class="
                                mt-4
                                text-[11px]
                                font-semibold
                                text-[#34483F]
                            "
                        >
                            Monitor Delivery
                        </p>


                        <p
                            class="
                                mt-1
                                text-[9px]
                                leading-5
                                text-[#849089]
                            "
                        >
                            Track delivery status
                            and failed attempts.
                        </p>

                    </a>

                </div>

            </div>

        </section>


        {{-- =================================================
            RECENT PARCEL ACTIVITY
        ================================================== --}}

        <section
            class="
                overflow-hidden
                rounded-2xl
                border border-[#E1E8E4]
                bg-white
            "
        >

            <div
                class="
                    flex items-center
                    justify-between gap-4
                    border-b
                    border-[#EDF1EF]
                    px-5 py-4
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
                        Recent Parcel Activity
                    </h3>


                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-[#7C8983]
                        "
                    >
                        Latest parcels inside the
                        Logistics fulfillment flow.
                    </p>

                </div>


                <a
                    href="{{ route('logistics.shipments') }}"
                    class="
                        inline-flex
                        items-center gap-1.5
                        text-[10px]
                        font-semibold
                        text-[#1F6F5B]
                        transition
                        hover:text-[#155244]
                    "
                >
                    View all

                    <i
                        data-lucide="arrow-right"
                        class="h-3.5 w-3.5"
                    ></i>
                </a>

            </div>


            @forelse(
                $recentOrders
                as $orderId => $order
            )

                @php

                    $resolvedOrderId =
                        $order['id']
                        ?? $orderId;


                    $status =
                        strtolower(
                            $order['status']
                            ?? ''
                        );


                    $statusData =
                        $statusConfig[$status]
                        ?? [
                            'label' =>
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $status
                                    )
                                ),

                            'class' =>
                                'border-gray-200 bg-gray-50 text-gray-600',

                            'icon' =>
                                'package',
                        ];


                    $destination =
                        $formatAddress(
                            $order
                        );


                    $area =
                        $order['assigned_area']
                        ?? $order['area']
                        ?? null;


                    $riderName =
                        $order['rider_name']
                        ?? null;

                @endphp


                <div
                    class="
                        flex flex-col gap-4
                        border-b
                        border-[#EDF1EF]
                        px-5 py-4
                        last:border-b-0
                        sm:flex-row
                        sm:items-center
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


                    <div class="min-w-0 flex-1">

                        <div
                            class="
                                flex flex-wrap
                                items-center gap-2
                            "
                        >

                            <p
                                class="
                                    text-[11px]
                                    font-semibold
                                    text-[#34483F]
                                "
                            >
                                Order #{{ $resolvedOrderId }}
                            </p>


                            <span
                                class="
                                    inline-flex
                                    items-center gap-1
                                    rounded-full
                                    border
                                    px-2 py-0.5
                                    text-[8px]
                                    font-semibold
                                    {{ $statusData['class'] }}
                                "
                            >

                                <i
                                    data-lucide="{{ $statusData['icon'] }}"
                                    class="h-2.5 w-2.5"
                                ></i>

                                {{ $statusData['label'] }}

                            </span>

                        </div>


                        <div
                            class="
                                mt-2
                                flex flex-wrap
                                gap-x-4 gap-y-1
                                text-[9px]
                                text-[#85928C]
                            "
                        >

                            <span
                                class="
                                    flex items-center gap-1.5
                                "
                            >

                                <i
                                    data-lucide="map-pin"
                                    class="h-3 w-3"
                                ></i>

                                {{ $destination }}

                            </span>


                            @if($area)

                                <span
                                    class="
                                        flex items-center gap-1.5
                                    "
                                >

                                    <i
                                        data-lucide="map"
                                        class="h-3 w-3"
                                    ></i>

                                    {{ $area }}

                                </span>

                            @endif


                            @if($riderName)

                                <span
                                    class="
                                        flex items-center gap-1.5
                                    "
                                >

                                    <i
                                        data-lucide="bike"
                                        class="h-3 w-3"
                                    ></i>

                                    {{ $riderName }}

                                </span>

                            @endif

                        </div>

                    </div>


                    <a
                        href="{{ route('logistics.shipments') }}"
                        class="
                            inline-flex h-9
                            items-center justify-center
                            gap-2
                            rounded-xl
                            border
                            border-[#DDE6E1]
                            bg-white
                            px-3
                            text-[9px]
                            font-semibold
                            text-[#52635B]
                            transition
                            hover:bg-[#F3F7F5]
                        "
                    >

                        View Shipment

                        <i
                            data-lucide="arrow-up-right"
                            class="h-3 w-3"
                        ></i>

                    </a>

                </div>


            @empty

                <div
                    class="
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
                            bg-[#EEF5F1]
                            text-[#1F6F5B]
                        "
                    >

                        <i
                            data-lucide="package-search"
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
                        No logistics activity yet
                    </p>


                    <p
                        class="
                            mx-auto mt-1
                            max-w-sm
                            text-xs
                            leading-5
                            text-[#849089]
                        "
                    >
                        Parcels will appear here after
                        pickup riders deliver them to
                        the Sorting Center.
                    </p>

                </div>

            @endforelse

        </section>

    </div>


    {{-- =====================================================
        RIGHT COLUMN
    ====================================================== --}}

    <div class="space-y-6">


        {{-- =================================================
            RIDER CAPACITY
        ================================================== --}}

        <section
            class="
                rounded-2xl
                border border-[#E1E8E4]
                bg-white
                p-5
            "
        >

            <div
                class="
                    flex items-center
                    justify-between
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
                        Rider Capacity
                    </h3>


                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-[#7C8983]
                        "
                    >
                        Current approved rider pool.
                    </p>

                </div>


                <div
                    class="
                        flex h-9 w-9
                        items-center justify-center
                        rounded-xl
                        bg-[#EEF5F1]
                        text-[#1F6F5B]
                    "
                >

                    <i
                        data-lucide="bike"
                        class="h-4 w-4"
                    ></i>

                </div>

            </div>


            <div class="mt-5 space-y-4">


                {{-- APPROVED --}}
                <div>

                    <div
                        class="
                            mb-2
                            flex items-center
                            justify-between
                        "
                    >

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Approved Riders
                        </span>


                        <span
                            class="
                                text-xs
                                font-semibold
                                text-[#34483F]
                            "
                        >
                            {{ $approvedRiders }}
                        </span>

                    </div>


                    <div
                        class="
                            h-1.5
                            overflow-hidden
                            rounded-full
                            bg-[#EEF2F0]
                        "
                    >

                        <div
                            class="
                                h-full
                                rounded-full
                                bg-[#1F6F5B]
                            "
                            style="
                                width:
                                {{
                                    $approvedRiders > 0
                                        ? '100'
                                        : '0'
                                }}%
                            "
                        ></div>

                    </div>

                </div>


                {{-- AVAILABLE --}}
                <div>

                    <div
                        class="
                            mb-2
                            flex items-center
                            justify-between
                        "
                    >

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Available
                        </span>


                        <span
                            class="
                                text-xs
                                font-semibold
                                text-emerald-700
                            "
                        >
                            {{ $availableRiders }}
                        </span>

                    </div>


                    <div
                        class="
                            h-1.5
                            overflow-hidden
                            rounded-full
                            bg-[#EEF2F0]
                        "
                    >

                        <div
                            class="
                                h-full
                                rounded-full
                                bg-emerald-500
                            "
                            style="
                                width:
                                {{
                                    $approvedRiders > 0
                                        ? min(
                                            100,
                                            round(
                                                (
                                                    $availableRiders
                                                    / $approvedRiders
                                                ) * 100
                                            )
                                        )
                                        : 0
                                }}%
                            "
                        ></div>

                    </div>

                </div>


                {{-- BUSY --}}
                <div>

                    <div
                        class="
                            mb-2
                            flex items-center
                            justify-between
                        "
                    >

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Assigned / On Delivery
                        </span>


                        <span
                            class="
                                text-xs
                                font-semibold
                                text-orange-700
                            "
                        >
                            {{ $busyRiders }}
                        </span>

                    </div>


                    <div
                        class="
                            h-1.5
                            overflow-hidden
                            rounded-full
                            bg-[#EEF2F0]
                        "
                    >

                        <div
                            class="
                                h-full
                                rounded-full
                                bg-orange-500
                            "
                            style="
                                width:
                                {{
                                    $approvedRiders > 0
                                        ? min(
                                            100,
                                            round(
                                                (
                                                    $busyRiders
                                                    / $approvedRiders
                                                ) * 100
                                            )
                                        )
                                        : 0
                                }}%
                            "
                        ></div>

                    </div>

                </div>

            </div>


            <div
                class="
                    mt-5
                    grid grid-cols-2
                    gap-2
                "
            >

                <a
                    href="{{ route('logistics.riders') }}"
                    class="
                        inline-flex h-9
                        items-center justify-center
                        gap-2
                        rounded-xl
                        border
                        border-[#DDE6E1]
                        text-[9px]
                        font-semibold
                        text-[#52635B]
                        transition
                        hover:bg-[#F5F8F6]
                    "
                >
                    Manage Riders
                </a>


                <a
                    href="{{ route('logistics.assignments') }}"
                    class="
                        inline-flex h-9
                        items-center justify-center
                        gap-2
                        rounded-xl
                        bg-[#173F35]
                        text-[9px]
                        font-semibold
                        text-white
                        transition
                        hover:bg-[#1F6F5B]
                    "
                >
                    Assign Rider
                </a>

            </div>

        </section>


        {{-- =================================================
            DELIVERY HEALTH
        ================================================== --}}

        <section
            class="
                rounded-2xl
                border border-[#E1E8E4]
                bg-white
                p-5
            "
        >

            <div
                class="
                    flex items-center
                    justify-between
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
                        Delivery Overview
                    </h3>


                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-[#7C8983]
                        "
                    >
                        Current final-mile status.
                    </p>

                </div>


                <i
                    data-lucide="activity"
                    class="
                        h-[18px] w-[18px]
                        text-[#1F6F5B]
                    "
                ></i>

            </div>


            <div class="mt-5 space-y-2.5">


                <div
                    class="
                        flex items-center
                        justify-between
                        rounded-xl
                        bg-[#F7F9F8]
                        px-3.5 py-3
                    "
                >

                    <div
                        class="
                            flex items-center gap-2.5
                        "
                    >

                        <span
                            class="
                                h-2 w-2
                                rounded-full
                                bg-violet-500
                            "
                        ></span>

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Assigned
                        </span>

                    </div>


                    <span
                        class="
                            text-xs
                            font-semibold
                            text-[#34483F]
                        "
                    >
                        {{ $assigned }}
                    </span>

                </div>


                <div
                    class="
                        flex items-center
                        justify-between
                        rounded-xl
                        bg-[#F7F9F8]
                        px-3.5 py-3
                    "
                >

                    <div
                        class="
                            flex items-center gap-2.5
                        "
                    >

                        <span
                            class="
                                h-2 w-2
                                rounded-full
                                bg-orange-500
                            "
                        ></span>

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Out for Delivery
                        </span>

                    </div>


                    <span
                        class="
                            text-xs
                            font-semibold
                            text-[#34483F]
                        "
                    >
                        {{ $outForDelivery }}
                    </span>

                </div>


                <div
                    class="
                        flex items-center
                        justify-between
                        rounded-xl
                        bg-[#F7F9F8]
                        px-3.5 py-3
                    "
                >

                    <div
                        class="
                            flex items-center gap-2.5
                        "
                    >

                        <span
                            class="
                                h-2 w-2
                                rounded-full
                                bg-emerald-500
                            "
                        ></span>

                        <span
                            class="
                                text-[10px]
                                font-medium
                                text-[#65756D]
                            "
                        >
                            Delivered
                        </span>

                    </div>


                    <span
                        class="
                            text-xs
                            font-semibold
                            text-[#34483F]
                        "
                    >
                        {{ $delivered }}
                    </span>

                </div>


                <div
                    class="
                        flex items-center
                        justify-between
                        rounded-xl
                        {{ $failed > 0
                            ? 'bg-red-50'
                            : 'bg-[#F7F9F8]'
                        }}
                        px-3.5 py-3
                    "
                >

                    <div
                        class="
                            flex items-center gap-2.5
                        "
                    >

                        <span
                            class="
                                h-2 w-2
                                rounded-full
                                {{ $failed > 0
                                    ? 'bg-red-500'
                                    : 'bg-[#AAB4AF]'
                                }}
                            "
                        ></span>

                        <span
                            class="
                                text-[10px]
                                font-medium
                                {{ $failed > 0
                                    ? 'text-red-700'
                                    : 'text-[#65756D]'
                                }}
                            "
                        >
                            Delivery Failed
                        </span>

                    </div>


                    <span
                        class="
                            text-xs
                            font-semibold
                            {{ $failed > 0
                                ? 'text-red-700'
                                : 'text-[#34483F]'
                            }}
                        "
                    >
                        {{ $failed }}
                    </span>

                </div>

            </div>


            <a
                href="{{ route('logistics.monitoring') }}"
                class="
                    mt-4
                    inline-flex h-9
                    w-full
                    items-center justify-center
                    gap-2
                    rounded-xl
                    border
                    border-[#DDE6E1]
                    text-[9px]
                    font-semibold
                    text-[#52635B]
                    transition
                    hover:bg-[#F5F8F6]
                "
            >

                Open Delivery Monitoring

                <i
                    data-lucide="arrow-right"
                    class="h-3.5 w-3.5"
                ></i>

            </a>

        </section>


        {{-- =================================================
            RIDER APPLICATION SUMMARY
        ================================================== --}}

        <section
            class="
                rounded-2xl
                border border-[#E1E8E4]
                bg-white
                p-5
            "
        >

            <div
                class="
                    flex items-center
                    justify-between
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
                        Rider Applications
                    </h3>


                    <p
                        class="
                            mt-0.5
                            text-[10px]
                            text-[#7C8983]
                        "
                    >
                        Logistics approval responsibility.
                    </p>

                </div>


                @if($pendingRiders > 0)

                    <span
                        class="
                            rounded-full
                            bg-amber-50
                            px-2.5 py-1
                            text-[9px]
                            font-semibold
                            text-amber-700
                        "
                    >
                        {{ $pendingRiders }} pending
                    </span>

                @endif

            </div>


            <div
                class="
                    mt-5
                    grid grid-cols-3
                    divide-x
                    divide-[#EDF1EF]
                    rounded-xl
                    border
                    border-[#EDF1EF]
                    bg-[#FAFCFB]
                    py-3
                "
            >

                <div class="text-center">

                    <p
                        class="
                            text-lg
                            font-semibold
                            text-amber-700
                        "
                    >
                        {{ $pendingRiders }}
                    </p>

                    <p
                        class="
                            mt-1
                            text-[8px]
                            uppercase
                            tracking-[0.08em]
                            text-[#98A39D]
                        "
                    >
                        Pending
                    </p>

                </div>


                <div class="text-center">

                    <p
                        class="
                            text-lg
                            font-semibold
                            text-emerald-700
                        "
                    >
                        {{ $approvedRiders }}
                    </p>

                    <p
                        class="
                            mt-1
                            text-[8px]
                            uppercase
                            tracking-[0.08em]
                            text-[#98A39D]
                        "
                    >
                        Approved
                    </p>

                </div>


                <div class="text-center">

                    <p
                        class="
                            text-lg
                            font-semibold
                            text-red-600
                        "
                    >
                        {{ $disapprovedRiders }}
                    </p>

                    <p
                        class="
                            mt-1
                            text-[8px]
                            uppercase
                            tracking-[0.08em]
                            text-[#98A39D]
                        "
                    >
                        Declined
                    </p>

                </div>

            </div>


            <a
                href="{{ route('logistics.riders') }}"
                class="
                    mt-4
                    inline-flex h-9
                    w-full
                    items-center justify-center
                    gap-2
                    rounded-xl
                    bg-[#173F35]
                    text-[9px]
                    font-semibold
                    text-white
                    transition
                    hover:bg-[#1F6F5B]
                "
            >

                Review Rider Applications

                <i
                    data-lucide="arrow-right"
                    class="h-3.5 w-3.5"
                ></i>

            </a>

        </section>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        if (
            typeof lucide !==
                'undefined'
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