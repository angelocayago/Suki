@extends('layouts.rider')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    $orders =
        session('orders', []);

    if (!is_array($orders)) {
        $orders = [];
    }


    /*
    |--------------------------------------------------------------------------
    | RIDER INFORMATION
    |--------------------------------------------------------------------------
    */

    $riderName = trim(
        ($application['first_name'] ?? '') . ' ' .
        ($application['last_name'] ?? '')
    );

    if ($riderName === '') {
        $riderName = 'Rider';
    }

    $riderIndex =
        session('logged_in_rider_index');


    /*
    |--------------------------------------------------------------------------
    | AVAILABLE PICKUPS
    |--------------------------------------------------------------------------
    */

    $availablePickups =
        collect($orders)
            ->filter(
                function ($order) {

                    return
                        ($order['status'] ?? '')
                            === 'ready_for_pickup'
                        &&
                        empty(
                            $order['rider_name'] ?? null
                        )
                        &&
                        !isset(
                            $order['rider_index']
                        );

                }
            );


    $availablePickupCount =
        $availablePickups->count();


    /*
    |--------------------------------------------------------------------------
    | MY ORDERS
    |--------------------------------------------------------------------------
    */

    $myOrders =
        collect($orders)
            ->filter(
                function ($order)
                use (
                    $riderIndex,
                    $riderName
                ) {

                    if (
                        $riderIndex !== null &&
                        isset(
                            $order['rider_index']
                        )
                    ) {

                        return
                            (string)
                            $order['rider_index']
                            ===
                            (string)
                            $riderIndex;

                    }


                    return
                        ($order['rider_name'] ?? '')
                        ===
                        $riderName;

                }
            );


    /*
    |--------------------------------------------------------------------------
    | STATUS COUNTS
    |--------------------------------------------------------------------------
    */

    $assigned =
        $myOrders
            ->where(
                'status',
                'assigned_to_rider'
            )
            ->count();


    $pickedUp =
        $myOrders
            ->where(
                'status',
                'picked_up'
            )
            ->count();


    $atSortingCenter =
        $myOrders
            ->where(
                'status',
                'at_sorting_center'
            )
            ->count();


    $outForDelivery =
        $myOrders
            ->where(
                'status',
                'out_for_delivery'
            )
            ->count();


    $delivered =
        $myOrders
            ->whereIn(
                'status',
                [
                    'delivered',
                    'completed'
                ]
            )
            ->count();


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    $activeDeliveries =
        $assigned
        + $pickedUp
        + $atSortingCenter
        + $outForDelivery;


    $totalCompleted =
        $delivered;


    /*
    |--------------------------------------------------------------------------
    | EARNINGS
    |--------------------------------------------------------------------------
    */

    $deliveryFee = 50;

    $todayEarnings =
        $totalCompleted
        * $deliveryFee;


    /*
    |--------------------------------------------------------------------------
    | RECENT ACTIVE TASKS
    |--------------------------------------------------------------------------
    */

    $recentOrders =
        $myOrders
            ->filter(
                function ($order) {

                    return in_array(
                        $order['status'] ?? '',
                        [
                            'assigned_to_rider',
                            'picked_up',
                            'at_sorting_center',
                            'out_for_delivery'
                        ]
                    );

                }
            )
            ->reverse()
            ->take(4);


    /*
    |--------------------------------------------------------------------------
    | STATUS LABELS
    |--------------------------------------------------------------------------
    */

    $statusLabels = [

        'assigned_to_rider' =>
            'Assigned',

        'picked_up' =>
            'Picked Up',

        'at_sorting_center' =>
            'Sorting Center',

        'out_for_delivery' =>
            'Out for Delivery',

        'delivered' =>
            'Delivered',

        'completed' =>
            'Completed',

    ];


    $statusClasses = [

        'assigned_to_rider' =>
            'border-violet-200 bg-violet-50 text-violet-700',

        'picked_up' =>
            'border-cyan-200 bg-cyan-50 text-cyan-700',

        'at_sorting_center' =>
            'border-sky-200 bg-sky-50 text-sky-700',

        'out_for_delivery' =>
            'border-orange-200 bg-orange-50 text-orange-700',

        'delivered' =>
            'border-emerald-200 bg-emerald-50 text-emerald-700',

        'completed' =>
            'border-emerald-200 bg-emerald-50 text-emerald-700',

    ];


    /*
    |--------------------------------------------------------------------------
    | GREETING
    |--------------------------------------------------------------------------
    */

    $hour =
        (int) now()->format('H');

    if ($hour < 12) {

        $greeting =
            'Good morning';

    } elseif ($hour < 18) {

        $greeting =
            'Good afternoon';

    } else {

        $greeting =
            'Good evening';

    }

@endphp


{{-- =========================================================
    ALERTS
========================================================= --}}

@if(session('success'))

    <div
        class="mb-6
               flex items-start gap-3
               rounded-2xl
               border border-emerald-200
               bg-emerald-50
               px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-white"
        >

            <i
                data-lucide="check"
                class="h-4 w-4 text-emerald-600"
            ></i>

        </div>


        <p
            class="pt-1
                   text-xs
                   leading-5
                   text-emerald-700"
        >
            {{ session('success') }}
        </p>

    </div>

@endif


@if(session('error'))

    <div
        class="mb-6
               flex items-start gap-3
               rounded-2xl
               border border-red-200
               bg-red-50
               px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8
                   shrink-0
                   items-center justify-center
                   rounded-xl
                   bg-white"
        >

            <i
                data-lucide="triangle-alert"
                class="h-4 w-4 text-red-600"
            ></i>

        </div>


        <p
            class="pt-1
                   text-xs
                   leading-5
                   text-red-700"
        >
            {{ session('error') }}
        </p>

    </div>

@endif


{{-- =========================================================
    INTRO
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           lg:flex-row
           lg:items-end
           lg:justify-between"
>

    <div>

        <p
            class="text-[11px]
                   font-semibold
                   uppercase
                   tracking-[0.12em]
                   text-[#1F6F5B]"
        >
            Delivery workspace
        </p>


        <h2
            class="mt-1
                   text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            {{ $greeting }}, {{ $riderName }}
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Review available pickups, active delivery tasks,
            and your completed delivery activity.
        </p>

    </div>


    <a
        href="{{ route('rider.deliveries') }}"
        class="inline-flex h-10
               items-center justify-center gap-2
               self-start
               rounded-xl
               bg-[#173F35]
               px-4
               text-xs
               font-semibold
               text-white
               transition
               hover:bg-[#1F6F5B]
               lg:self-auto"
    >

        <i
            data-lucide="route"
            class="h-4 w-4"
        ></i>

        View Deliveries

    </a>

</div>


{{-- =========================================================
    AVAILABLE PICKUP NOTICE
========================================================= --}}

@if($availablePickupCount > 0)

    <section
        class="mb-6
               overflow-hidden
               rounded-2xl
               border border-amber-200
               bg-amber-50"
    >

        <div
            class="flex flex-col gap-4
                   p-5
                   sm:flex-row
                   sm:items-center
                   sm:justify-between"
        >

            <div
                class="flex items-start gap-4"
            >

                <div
                    class="flex h-11 w-11
                           shrink-0
                           items-center justify-center
                           rounded-xl
                           bg-white
                           text-amber-700"
                >

                    <i
                        data-lucide="package-plus"
                        class="h-5 w-5"
                    ></i>

                </div>


                <div>

                    <p
                        class="text-sm
                               font-semibold
                               text-amber-900"
                    >
                        Pickup requests available
                    </p>


                    <p
                        class="mt-1
                               text-xs
                               leading-5
                               text-amber-700"
                    >
                        {{ $availablePickupCount }}
                        {{ $availablePickupCount === 1 ? 'order is' : 'orders are' }}
                        currently ready for rider pickup.
                    </p>

                </div>

            </div>


            <a
                href="{{ route('rider.deliveries') }}"
                class="inline-flex h-9
                       shrink-0
                       items-center
                       justify-center gap-2
                       self-start
                       rounded-xl
                       bg-amber-600
                       px-4
                       text-[11px]
                       font-semibold
                       text-white
                       transition
                       hover:bg-amber-700
                       sm:self-auto"
            >

                View Requests

                <i
                    data-lucide="arrow-right"
                    class="h-3.5 w-3.5"
                ></i>

            </a>

        </div>

    </section>

@endif


{{-- =========================================================
    SUMMARY CARDS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    {{-- AVAILABLE PICKUPS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Available Pickups
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $availablePickupCount }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-amber-50
                       text-amber-700"
            >

                <i
                    data-lucide="package-plus"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Ready for rider acceptance
        </p>

    </div>


    {{-- ACTIVE --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Active Deliveries
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $activeDeliveries }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#DDF3EC]
                       text-[#173F35]"
            >

                <i
                    data-lucide="bike"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Current rider tasks
        </p>

    </div>


    {{-- COMPLETED --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Completed
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalCompleted }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-emerald-50
                       text-emerald-700"
            >

                <i
                    data-lucide="badge-check"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <p
            class="mt-5
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   text-[#7B8982]"
        >
            Successfully delivered orders
        </p>

    </div>


    {{-- EARNINGS --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div class="flex items-start justify-between gap-3">

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Earnings
                </p>


                <p
                    class="mt-3
                           text-xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]
                           sm:text-2xl"
                >
                    ₱{{ number_format($todayEarnings, 2) }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-[18px] w-[18px]"
                ></i>

            </div>

        </div>


        <a
            href="{{ route('rider.earnings') }}"
            class="mt-5
                   flex items-center
                   justify-between
                   border-t border-[#EEF2F0]
                   pt-3
                   text-[10px]
                   font-semibold
                   text-[#1F6F5B]"
        >

            View earnings

            <i
                data-lucide="arrow-right"
                class="h-3.5 w-3.5"
            ></i>

        </a>

    </div>

</div>


{{-- =========================================================
    DELIVERY STATUS
========================================================= --}}

<section
    class="mb-6
           overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>

    <div
        class="border-b border-[#EDF1EF]
               px-5 py-4"
    >

        <h3
            class="text-sm
                   font-semibold
                   text-[#24312C]"
        >
            Delivery Activity
        </h3>

        <p
            class="mt-0.5
                   text-[11px]
                   text-[#7C8983]"
        >
            Current distribution of your delivery tasks
        </p>

    </div>


    <div
        class="grid
               divide-y divide-[#EDF1EF]
               sm:grid-cols-2
               sm:divide-x
               sm:divide-y-0
               xl:grid-cols-4"
    >


        {{-- ASSIGNED --}}
        <div class="p-5">

            <div
                class="flex items-center
                       justify-between"
            >

                <div
                    class="flex h-9 w-9
                           items-center justify-center
                           rounded-xl
                           bg-violet-50
                           text-violet-700"
                >

                    <i
                        data-lucide="user-check"
                        class="h-4 w-4"
                    ></i>

                </div>


                <span
                    class="text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $assigned }}
                </span>

            </div>


            <p
                class="mt-4
                       text-xs
                       font-semibold
                       text-[#34483F]"
            >
                Assigned
            </p>

            <p
                class="mt-1
                       text-[10px]
                       text-[#849089]"
            >
                Waiting for delivery pickup
            </p>

        </div>


        {{-- PICKED UP --}}
        <div class="p-5">

            <div
                class="flex items-center
                       justify-between"
            >

                <div
                    class="flex h-9 w-9
                           items-center justify-center
                           rounded-xl
                           bg-cyan-50
                           text-cyan-700"
                >

                    <i
                        data-lucide="package-check"
                        class="h-4 w-4"
                    ></i>

                </div>


                <span
                    class="text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $pickedUp }}
                </span>

            </div>


            <p
                class="mt-4
                       text-xs
                       font-semibold
                       text-[#34483F]"
            >
                Picked Up
            </p>

            <p
                class="mt-1
                       text-[10px]
                       text-[#849089]"
            >
                Parcel currently with rider
            </p>

        </div>


        {{-- SORTING --}}
        <div class="p-5">

            <div
                class="flex items-center
                       justify-between"
            >

                <div
                    class="flex h-9 w-9
                           items-center justify-center
                           rounded-xl
                           bg-sky-50
                           text-sky-700"
                >

                    <i
                        data-lucide="warehouse"
                        class="h-4 w-4"
                    ></i>

                </div>


                <span
                    class="text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $atSortingCenter }}
                </span>

            </div>


            <p
                class="mt-4
                       text-xs
                       font-semibold
                       text-[#34483F]"
            >
                Sorting Center
            </p>

            <p
                class="mt-1
                       text-[10px]
                       text-[#849089]"
            >
                Parcel handed to sorting facility
            </p>

        </div>


        {{-- OUT FOR DELIVERY --}}
        <div class="p-5">

            <div
                class="flex items-center
                       justify-between"
            >

                <div
                    class="flex h-9 w-9
                           items-center justify-center
                           rounded-xl
                           bg-orange-50
                           text-orange-700"
                >

                    <i
                        data-lucide="navigation"
                        class="h-4 w-4"
                    ></i>

                </div>


                <span
                    class="text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $outForDelivery }}
                </span>

            </div>


            <p
                class="mt-4
                       text-xs
                       font-semibold
                       text-[#34483F]"
            >
                Out for Delivery
            </p>

            <p
                class="mt-1
                       text-[10px]
                       text-[#849089]"
            >
                On the way to the customer
            </p>

        </div>

    </div>

</section>


{{-- =========================================================
    ACTIVE TASKS + RIDER PROFILE
========================================================= --}}

<div
    class="grid grid-cols-1
           gap-6
           xl:grid-cols-[1.4fr_.6fr]"
>


    {{-- =====================================================
        ACTIVE TASKS
    ====================================================== --}}

    <section
        class="overflow-hidden
               rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="flex items-center
                   justify-between gap-4
                   border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <div>

                <h3
                    class="text-sm
                           font-semibold
                           text-[#24312C]"
                >
                    Active Delivery Tasks
                </h3>

                <p
                    class="mt-0.5
                           text-[11px]
                           text-[#7C8983]"
                >
                    Your most recent active assignments
                </p>

            </div>


            <a
                href="{{ route('rider.deliveries') }}"
                class="inline-flex
                       items-center gap-1
                       text-[10px]
                       font-semibold
                       text-[#1F6F5B]"
            >
                View all

                <i
                    data-lucide="arrow-right"
                    class="h-3.5 w-3.5"
                ></i>
            </a>

        </div>


        @if($recentOrders->count())


            <div
                class="divide-y
                       divide-[#EDF1EF]"
            >

                @foreach($recentOrders as $orderId => $order)

                    @php

                        $status =
                            $order['status']
                            ?? 'assigned_to_rider';

                        $statusLabel =
                            $statusLabels[$status]
                            ?? ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $status
                                )
                            );

                        $statusClass =
                            $statusClasses[$status]
                            ?? 'border-gray-200 bg-gray-100 text-gray-600';

                        $resolvedOrderId =
                            $order['id']
                            ?? $orderId;

                        $customer =
                            $order['customer_name']
                            ?? $order['shipping_address']['name']
                            ?? 'Customer';

                        $address =
                            $order['address']
                            ?? $order['shipping_address']['address']
                            ?? $order['shipping_address']['full_address']
                            ?? 'Address unavailable';

                    @endphp


                    <div
                        class="flex flex-col gap-4
                               px-5 py-4
                               sm:flex-row
                               sm:items-center"
                    >

                        <div
                            class="flex h-11 w-11
                                   shrink-0
                                   items-center justify-center
                                   rounded-xl
                                   bg-[#EEF5F1]
                                   text-[#1F6F5B]"
                        >

                            <i
                                data-lucide="package"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <div class="min-w-0 flex-1">

                            <div
                                class="flex flex-wrap
                                       items-center gap-2"
                            >

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    Order #{{ $resolvedOrderId }}
                                </p>


                                <span
                                    class="
                                        inline-flex
                                        rounded-full
                                        border
                                        px-2 py-1
                                        text-[9px]
                                        font-semibold
                                        {{ $statusClass }}
                                    "
                                >
                                    {{ $statusLabel }}
                                </span>

                            </div>


                            <p
                                class="mt-1
                                       text-[10px]
                                       font-medium
                                       text-[#617169]"
                            >
                                {{ $customer }}
                            </p>


                            <p
                                class="mt-0.5
                                       truncate
                                       text-[10px]
                                       text-[#8A9791]"
                            >
                                {{ $address }}
                            </p>

                        </div>


                        <a
                            href="{{ route('rider.deliveries') }}"
                            class="inline-flex h-9
                                   shrink-0
                                   items-center
                                   justify-center gap-2
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-3
                                   text-[10px]
                                   font-semibold
                                   text-[#52635B]
                                   transition
                                   hover:bg-[#EEF5F1]
                                   hover:text-[#173F35]"
                        >

                            Manage

                            <i
                                data-lucide="arrow-up-right"
                                class="h-3.5 w-3.5"
                            ></i>

                        </a>

                    </div>

                @endforeach

            </div>


        @else

            <div
                class="px-6 py-14
                       text-center"
            >

                <div
                    class="mx-auto
                           flex h-12 w-12
                           items-center justify-center
                           rounded-2xl
                           bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >

                    <i
                        data-lucide="route"
                        class="h-5 w-5"
                    ></i>

                </div>


                <p
                    class="mt-4
                           text-sm
                           font-semibold
                           text-[#34483F]"
                >
                    No active deliveries
                </p>


                <p
                    class="mx-auto mt-1
                           max-w-sm
                           text-xs
                           leading-5
                           text-[#849089]"
                >
                    New pickup requests and assigned
                    deliveries will appear here.
                </p>

            </div>

        @endif

    </section>


    {{-- =====================================================
        RIDER INFORMATION
    ====================================================== --}}

    <section
        class="overflow-hidden
               rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="border-b border-[#EDF1EF]
                   px-5 py-4"
        >

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Rider Information
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Current rider account details
            </p>

        </div>


        <div class="p-5">


            <div
                class="flex items-center gap-4"
            >

                <div
                    class="flex h-12 w-12
                           shrink-0
                           items-center justify-center
                           rounded-2xl
                           bg-[#173F35]
                           text-white"
                >

                    <i
                        data-lucide="bike"
                        class="h-5 w-5"
                    ></i>

                </div>


                <div class="min-w-0">

                    <p
                        class="truncate
                               text-sm
                               font-semibold
                               text-[#34483F]"
                    >
                        {{ $riderName }}
                    </p>

                    <p
                        class="mt-0.5
                               text-[10px]
                               text-[#8A9791]"
                    >
                        Approved SUKI Rider
                    </p>

                </div>

            </div>


            <div
                class="mt-5
                       space-y-4
                       border-t border-[#EDF1EF]
                       pt-5"
            >


                <div
                    class="flex items-center
                           justify-between gap-4"
                >

                    <span
                        class="text-[10px]
                               text-[#849089]"
                    >
                        Vehicle
                    </span>

                    <span
                        class="text-right
                               text-[11px]
                               font-semibold
                               text-[#52635B]"
                    >
                        {{ $application['vehicle_type']
                            ?? 'Not specified' }}
                    </span>

                </div>


                <div
                    class="flex items-center
                           justify-between gap-4"
                >

                    <span
                        class="text-[10px]
                               text-[#849089]"
                    >
                        Plate Number
                    </span>

                    <span
                        class="text-right
                               text-[11px]
                               font-semibold
                               text-[#52635B]"
                    >
                        {{ $application['plate_number']
                            ?? 'Not specified' }}
                    </span>

                </div>


                <div
                    class="flex items-center
                           justify-between gap-4"
                >

                    <span
                        class="text-[10px]
                               text-[#849089]"
                    >
                        Contact
                    </span>

                    <span
                        class="text-right
                               text-[11px]
                               font-semibold
                               text-[#52635B]"
                    >
                        {{ $application['phone']
                            ?? 'Not specified' }}
                    </span>

                </div>


                <div
                    class="flex items-center
                           justify-between gap-4"
                >

                    <span
                        class="text-[10px]
                               text-[#849089]"
                    >
                        Delivery Fee
                    </span>

                    <span
                        class="text-right
                               text-[11px]
                               font-semibold
                               text-[#1F6F5B]"
                    >
                        ₱{{ number_format(
                            $deliveryFee,
                            2
                        ) }}
                        / delivery
                    </span>

                </div>

            </div>


            <a
                href="{{ route('rider.earnings') }}"
                class="mt-5
                       inline-flex h-10
                       w-full
                       items-center
                       justify-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       text-[11px]
                       font-semibold
                       text-[#52635B]
                       transition
                       hover:bg-[#EEF5F1]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-4 w-4"
                ></i>

                View Earnings

            </a>

        </div>

    </section>

</div>

@endsection