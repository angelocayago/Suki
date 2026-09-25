@extends('layouts.rider')

@section('title', 'Deliveries')
@section('page-heading', 'Deliveries')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | RIDER
    |--------------------------------------------------------------------------
    */

    $riders =
        session('rider_applications', []);

    $riderIndex =
        session('logged_in_rider_index');

    $rider = [];

    if (
        $riderIndex !== null &&
        isset($riders[$riderIndex])
    ) {
        $rider =
            $riders[$riderIndex];
    }

    $riderName = trim(
        ($rider['first_name'] ?? '') . ' ' .
        ($rider['last_name'] ?? '')
    );

    if ($riderName === '') {
        $riderName = 'SUKI Rider';
    }


    /*
    |--------------------------------------------------------------------------
    | ORDERS
    |--------------------------------------------------------------------------
    */

    $ordersCollection =
        collect($orders ?? []);


    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    $formatAddress = function ($order) {

        $address =
            $order['shipping_address']
            ?? [];

        if (!is_array($address)) {
            $address = [];
        }

        $parts = array_filter([
            $address['house_number'] ?? null,
            $address['street'] ?? null,
            $address['barangay'] ?? null,
            $address['municipality'] ?? null,
            $address['province'] ?? null,
            $address['postal_code'] ?? null,
        ]);

        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return $order['address']
            ?? 'Delivery address is not available.';
    };


    $getCustomerName = function ($order) {

        return
            $order['shipping_address']['name']
            ?? $order['customer_name']
            ?? 'Customer';

    };


    $getCustomerPhone = function ($order) {

        return
            $order['shipping_address']['phone']
            ?? $order['customer_phone']
            ?? null;

    };


    $belongsToPickupRider =
        function ($order)
        use ($riderIndex) {

            if ($riderIndex === null) {
                return false;
            }

            return
                isset(
                    $order['pickup_rider_index']
                )
                &&
                (string)
                $order['pickup_rider_index']
                ===
                (string)
                $riderIndex;

        };


    $belongsToDeliveryRider =
        function ($order)
        use ($riderIndex) {

            if ($riderIndex === null) {
                return false;
            }

            return
                isset($order['rider_index'])
                &&
                (string)
                $order['rider_index']
                ===
                (string)
                $riderIndex;

        };


    /*
    |--------------------------------------------------------------------------
    | AVAILABLE PICKUPS
    |--------------------------------------------------------------------------
    */

    $availablePickups =
        $ordersCollection
            ->filter(function ($order) {

                return
                    ($order['status'] ?? '')
                    === 'ready_for_pickup'
                    &&
                    !isset(
                        $order['pickup_rider_index']
                    );

            });


    /*
    |--------------------------------------------------------------------------
    | MY PICKUP TASKS
    |--------------------------------------------------------------------------
    */

    $myPickupTasks =
        $ordersCollection
            ->filter(
                function ($order)
                use ($belongsToPickupRider) {

                    return
                        $belongsToPickupRider($order)
                        &&
                        in_array(
                            $order['status']
                                ?? '',
                            [
                                'ready_for_pickup',
                                'picked_up',
                                'at_sorting_center',
                            ]
                        );

                }
            );


    /*
    |--------------------------------------------------------------------------
    | MY DELIVERY TASKS
    |--------------------------------------------------------------------------
    */

    $myDeliveryTasks =
        $ordersCollection
            ->filter(
                function ($order)
                use ($belongsToDeliveryRider) {

                    return
                        $belongsToDeliveryRider($order)
                        &&
                        in_array(
                            $order['status']
                                ?? '',
                            [
                                'assigned_to_rider',
                                'out_for_delivery',
                                'delivered',
                                'completed',
                                'delivery_failed',
                                'returned',
                            ]
                        );

                }
            );


    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    $acceptedPickupCount =
        $myPickupTasks
            ->where(
                'status',
                'ready_for_pickup'
            )
            ->count();

    $pickupInTransitCount =
        $myPickupTasks
            ->where(
                'status',
                'picked_up'
            )
            ->count();

    $deliveryAssignedCount =
        $myDeliveryTasks
            ->where(
                'status',
                'assigned_to_rider'
            )
            ->count();

    $outForDeliveryCount =
        $myDeliveryTasks
            ->where(
                'status',
                'out_for_delivery'
            )
            ->count();

    $deliveredCount =
        $myDeliveryTasks
            ->whereIn(
                'status',
                [
                    'delivered',
                    'completed',
                ]
            )
            ->count();


    /*
    |--------------------------------------------------------------------------
    | STATUS DESIGN
    |--------------------------------------------------------------------------
    */

    $statusConfig = [

        'ready_for_pickup' => [
            'label' =>
                'Pickup Accepted',

            'class' =>
                'border-amber-200 bg-amber-50 text-amber-700',

            'icon' =>
                'package-plus',
        ],

        'picked_up' => [
            'label' =>
                'Picked Up',

            'class' =>
                'border-cyan-200 bg-cyan-50 text-cyan-700',

            'icon' =>
                'package-check',
        ],

        'at_sorting_center' => [
            'label' =>
                'At Sorting Center',

            'class' =>
                'border-sky-200 bg-sky-50 text-sky-700',

            'icon' =>
                'warehouse',
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

        'completed' => [
            'label' =>
                'Completed',

            'class' =>
                'border-emerald-200 bg-emerald-50 text-emerald-700',

            'icon' =>
                'badge-check',
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

@endphp


{{-- =========================================================
    ALERTS
========================================================= --}}

@if(session('success'))

    <div
        class="mb-6 flex items-start gap-3
               rounded-2xl border border-emerald-200
               bg-emerald-50 px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8 shrink-0
                   items-center justify-center
                   rounded-xl bg-white"
        >
            <i
                data-lucide="check"
                class="h-4 w-4 text-emerald-600"
            ></i>
        </div>

        <div>
            <p
                class="text-xs font-semibold
                       text-emerald-800"
            >
                Delivery updated
            </p>

            <p
                class="mt-0.5 text-xs leading-5
                       text-emerald-700"
            >
                {{ session('success') }}
            </p>
        </div>

    </div>

@endif


@if(session('error'))

    <div
        class="mb-6 flex items-start gap-3
               rounded-2xl border border-red-200
               bg-red-50 px-4 py-3.5"
    >

        <div
            class="flex h-8 w-8 shrink-0
                   items-center justify-center
                   rounded-xl bg-white"
        >
            <i
                data-lucide="triangle-alert"
                class="h-4 w-4 text-red-600"
            ></i>
        </div>

        <div>
            <p
                class="text-xs font-semibold
                       text-red-800"
            >
                Action unavailable
            </p>

            <p
                class="mt-0.5 text-xs leading-5
                       text-red-700"
            >
                {{ session('error') }}
            </p>
        </div>

    </div>

@endif


@if($errors->any())

    <div
        class="mb-6 rounded-2xl
               border border-red-200
               bg-red-50 p-4"
    >

        <div class="flex items-start gap-3">

            <i
                data-lucide="circle-alert"
                class="mt-0.5 h-4 w-4
                       shrink-0 text-red-600"
            ></i>

            <div>
                @foreach($errors->all() as $error)
                    <p
                        class="text-xs leading-5
                               text-red-700"
                    >
                        {{ $error }}
                    </p>
                @endforeach
            </div>

        </div>

    </div>

@endif


{{-- =========================================================
    PAGE INTRO
========================================================= --}}

<div
    class="mb-7 flex flex-col gap-4
           lg:flex-row lg:items-end
           lg:justify-between"
>

    <div>

        <p
            class="text-[11px] font-semibold
                   uppercase tracking-[0.12em]
                   text-[#1F6F5B]"
        >
            Rider operations
        </p>

        <h2
            class="mt-1 text-2xl font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Pickup & Delivery Tasks
        </h2>

        <p
            class="mt-1.5 max-w-2xl
                   text-sm leading-6
                   text-[#728078]"
        >
            Collect seller parcels, hand them to the
            Sorting Center, and complete delivery
            assignments issued by SUKI Logistics.
        </p>

    </div>


    <div
        class="inline-flex items-center gap-2
               self-start rounded-xl
               border border-emerald-200
               bg-emerald-50
               px-3.5 py-2.5
               text-[10px] font-semibold
               text-emerald-700
               lg:self-auto"
    >
        <span
            class="h-2 w-2 rounded-full
                   bg-emerald-500"
        ></span>

        {{ $riderName }}

    </div>

</div>


{{-- =========================================================
    SUMMARY
========================================================= --}}

<div
    class="mb-6 grid grid-cols-2
           gap-4 xl:grid-cols-5"
>

    {{-- AVAILABLE --}}
    <div
        class="rounded-2xl border
               border-[#E1E8E4]
               bg-white p-5"
    >
        <div
            class="flex items-start
                   justify-between gap-3"
        >
            <div>
                <p
                    class="text-[9px] font-semibold
                           uppercase tracking-[0.12em]
                           text-[#839189]"
                >
                    Available Pickup
                </p>

                <p
                    class="mt-3 text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $availablePickups->count() }}
                </p>
            </div>

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl bg-amber-50
                       text-amber-700"
            >
                <i
                    data-lucide="package-plus"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>
        </div>
    </div>


    {{-- ACCEPTED --}}
    <div
        class="rounded-2xl border
               border-[#E1E8E4]
               bg-white p-5"
    >
        <div
            class="flex items-start
                   justify-between gap-3"
        >
            <div>
                <p
                    class="text-[9px] font-semibold
                           uppercase tracking-[0.12em]
                           text-[#839189]"
                >
                    Pickup Accepted
                </p>

                <p
                    class="mt-3 text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $acceptedPickupCount }}
                </p>
            </div>

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl bg-cyan-50
                       text-cyan-700"
            >
                <i
                    data-lucide="package-check"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>
        </div>
    </div>


    {{-- TO SORTING --}}
    <div
        class="rounded-2xl border
               border-[#E1E8E4]
               bg-white p-5"
    >
        <div
            class="flex items-start
                   justify-between gap-3"
        >
            <div>
                <p
                    class="text-[9px] font-semibold
                           uppercase tracking-[0.12em]
                           text-[#839189]"
                >
                    To Sorting Center
                </p>

                <p
                    class="mt-3 text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $pickupInTransitCount }}
                </p>
            </div>

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl bg-sky-50
                       text-sky-700"
            >
                <i
                    data-lucide="warehouse"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>
        </div>
    </div>


    {{-- DELIVERY ASSIGNMENTS --}}
    <div
        class="rounded-2xl border
               border-[#E1E8E4]
               bg-white p-5"
    >
        <div
            class="flex items-start
                   justify-between gap-3"
        >
            <div>
                <p
                    class="text-[9px] font-semibold
                           uppercase tracking-[0.12em]
                           text-[#839189]"
                >
                    Delivery Assigned
                </p>

                <p
                    class="mt-3 text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $deliveryAssignedCount }}
                </p>
            </div>

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl bg-violet-50
                       text-violet-700"
            >
                <i
                    data-lucide="user-check"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>
        </div>
    </div>


    {{-- OUT FOR DELIVERY --}}
    <div
        class="col-span-2 rounded-2xl
               border border-[#E1E8E4]
               bg-white p-5
               xl:col-span-1"
    >
        <div
            class="flex items-start
                   justify-between gap-3"
        >
            <div>
                <p
                    class="text-[9px] font-semibold
                           uppercase tracking-[0.12em]
                           text-[#839189]"
                >
                    Out for Delivery
                </p>

                <p
                    class="mt-3 text-2xl
                           font-semibold
                           text-[#24312C]"
                >
                    {{ $outForDeliveryCount }}
                </p>

                <p
                    class="mt-1 text-[9px]
                           text-[#8A9791]"
                >
                    {{ $deliveredCount }} delivered/completed
                </p>
            </div>

            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl bg-orange-50
                       text-orange-700"
            >
                <i
                    data-lucide="bike"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>
        </div>
    </div>

</div>


{{-- =========================================================
    WORKFLOW SELECTOR
========================================================= --}}

<div
    class="mb-6 inline-flex w-full
           rounded-2xl border
           border-[#DDE6E1]
           bg-white p-1.5
           sm:w-auto"
>

    <button
        type="button"
        data-workflow-tab="pickup"
        class="workflow-tab flex h-10 flex-1
               items-center justify-center gap-2
               rounded-xl bg-[#173F35]
               px-5 text-[11px] font-semibold
               text-white transition
               sm:flex-none"
    >
        <i
            data-lucide="package-plus"
            class="h-4 w-4"
        ></i>

        Pickup Tasks

        <span
            class="rounded-full
                   bg-white/15
                   px-2 py-0.5
                   text-[9px]"
        >
            {{ $availablePickups->count() + $myPickupTasks->count() }}
        </span>
    </button>


    <button
        type="button"
        data-workflow-tab="delivery"
        class="workflow-tab flex h-10 flex-1
               items-center justify-center gap-2
               rounded-xl px-5
               text-[11px] font-semibold
               text-[#68776F]
               transition hover:bg-[#F3F7F5]
               sm:flex-none"
    >
        <i
            data-lucide="bike"
            class="h-4 w-4"
        ></i>

        Delivery Tasks

        <span
            class="rounded-full
                   bg-[#EEF2F0]
                   px-2 py-0.5
                   text-[9px]"
        >
            {{ $myDeliveryTasks->count() }}
        </span>
    </button>

</div>


{{-- =========================================================
    PICKUP WORKFLOW
========================================================= --}}

<div
    id="pickupWorkflow"
    class="workflow-panel space-y-6"
>


    {{-- FLOW GUIDE --}}
    <section
        class="overflow-hidden rounded-2xl
               border border-[#D9E6DF]
               bg-[#F1F8F4]"
    >

        <div
            class="flex items-start gap-4 p-5"
        >

            <div
                class="flex h-10 w-10 shrink-0
                       items-center justify-center
                       rounded-xl bg-white
                       text-[#1F6F5B]"
            >
                <i
                    data-lucide="route"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

            <div class="min-w-0">

                <p
                    class="text-xs font-semibold
                           text-[#294C42]"
                >
                    Seller Pickup Workflow
                </p>

                <p
                    class="mt-1 text-[10px]
                           leading-5 text-[#6B8178]"
                >
                    Accept pickup → go to seller →
                    collect parcel → scan/confirm →
                    deliver parcel to the Sorting Center.
                </p>


                <div
                    class="mt-4 flex flex-wrap
                           items-center gap-2"
                >

                    @foreach([
                        'Accept',
                        'Seller',
                        'Scan',
                        'Picked Up',
                        'Sorting Center'
                    ] as $step)

                        <span
                            class="rounded-full
                                   border border-[#D3E4DB]
                                   bg-white
                                   px-2.5 py-1
                                   text-[9px]
                                   font-semibold
                                   text-[#587067]"
                        >
                            {{ $step }}
                        </span>

                        @if(!$loop->last)
                            <i
                                data-lucide="arrow-right"
                                class="h-3 w-3
                                       text-[#90A39A]"
                            ></i>
                        @endif

                    @endforeach

                </div>

            </div>

        </div>

    </section>


    {{-- =====================================================
        AVAILABLE PICKUPS
    ====================================================== --}}

    <section
        class="overflow-hidden rounded-2xl
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
                    class="text-sm font-semibold
                           text-[#24312C]"
                >
                    Available Pickup Requests
                </h3>

                <p
                    class="mt-0.5 text-[11px]
                           text-[#7C8983]"
                >
                    Parcels marked Ready for Pickup
                    by sellers.
                </p>
            </div>


            <span
                class="rounded-full
                       bg-amber-50
                       px-2.5 py-1
                       text-[9px] font-semibold
                       text-amber-700"
            >
                {{ $availablePickups->count() }}
                available
            </span>

        </div>


        @forelse($availablePickups as $orderId => $order)

            @php

                $resolvedOrderId =
                    $order['id']
                    ?? $orderId;

                $items =
                    collect(
                        $order['items'] ?? []
                    );

                $sellerName =
                    $order['seller_name']
                    ?? $order['shop_name']
                    ?? 'Seller';

            @endphp


            <article
                class="border-b border-[#EDF1EF]
                       p-5 last:border-b-0"
            >

                <div
                    class="flex flex-col gap-5
                           lg:flex-row
                           lg:items-center"
                >

                    <div
                        class="flex h-12 w-12
                               shrink-0
                               items-center justify-center
                               rounded-2xl
                               bg-amber-50
                               text-amber-700"
                    >
                        <i
                            data-lucide="package-plus"
                            class="h-5 w-5"
                        ></i>
                    </div>


                    <div class="min-w-0 flex-1">

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >
                            <h4
                                class="text-sm font-semibold
                                       text-[#34483F]"
                            >
                                Order #{{ $resolvedOrderId }}
                            </h4>

                            <span
                                class="rounded-full
                                       border border-amber-200
                                       bg-amber-50
                                       px-2.5 py-1
                                       text-[9px] font-semibold
                                       text-amber-700"
                            >
                                Ready for Pickup
                            </span>
                        </div>


                        <div
                            class="mt-3 grid gap-2
                                   text-[10px]
                                   text-[#74827B]
                                   sm:grid-cols-2"
                        >

                            <div
                                class="flex items-center gap-2"
                            >
                                <i
                                    data-lucide="store"
                                    class="h-3.5 w-3.5
                                           text-[#1F6F5B]"
                                ></i>

                                {{ $sellerName }}
                            </div>

                            <div
                                class="flex items-center gap-2"
                            >
                                <i
                                    data-lucide="package"
                                    class="h-3.5 w-3.5
                                           text-[#1F6F5B]"
                                ></i>

                                {{ $items->sum('quantity') ?: $items->count() }}
                                item(s)
                            </div>

                        </div>

                    </div>


                    <form
                        method="POST"
                        action="{{ route(
                            'rider.pickup.accept',
                            $resolvedOrderId
                        ) }}"
                        class="accept-pickup-form"
                    >

                        @csrf


                        <button
                            type="submit"
                            class="inline-flex h-10
                                   w-full items-center
                                   justify-center gap-2
                                   rounded-xl
                                   bg-[#173F35]
                                   px-4
                                   text-[11px] font-semibold
                                   text-white transition
                                   hover:bg-[#1F6F5B]
                                   lg:w-auto"
                        >
                            <i
                                data-lucide="hand"
                                class="h-4 w-4"
                            ></i>

                            Accept Pickup
                        </button>

                    </form>

                </div>

            </article>


        @empty

            <div
                class="px-6 py-12 text-center"
            >

                <div
                    class="mx-auto flex h-12 w-12
                           items-center justify-center
                           rounded-2xl bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >
                    <i
                        data-lucide="package-search"
                        class="h-5 w-5"
                    ></i>
                </div>

                <p
                    class="mt-4 text-sm font-semibold
                           text-[#34483F]"
                >
                    No available pickup requests
                </p>

                <p
                    class="mt-1 text-xs
                           text-[#849089]"
                >
                    New seller pickup requests
                    will appear here.
                </p>

            </div>

        @endforelse

    </section>


    {{-- =====================================================
        MY PICKUP TASKS
    ====================================================== --}}

    <section
        class="overflow-hidden rounded-2xl
               border border-[#E1E8E4]
               bg-white"
    >

        <div
            class="border-b border-[#EDF1EF]
                   px-5 py-4"
        >
            <h3
                class="text-sm font-semibold
                       text-[#24312C]"
            >
                My Pickup Tasks
            </h3>

            <p
                class="mt-0.5 text-[11px]
                       text-[#7C8983]"
            >
                Pickups you have accepted from sellers.
            </p>
        </div>


        @forelse($myPickupTasks as $orderId => $order)

            @php

                $resolvedOrderId =
                    $order['id']
                    ?? $orderId;

                $status =
                    $order['status']
                    ?? 'ready_for_pickup';

                $data =
                    $statusConfig[$status]
                    ?? [
                        'label' => ucfirst(
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

                $sellerName =
                    $order['seller_name']
                    ?? $order['shop_name']
                    ?? 'Seller';

            @endphp


            <article
                class="border-b border-[#EDF1EF]
                       p-5 last:border-b-0"
            >

                <div
                    class="flex flex-col gap-5
                           xl:flex-row"
                >

                    <div class="min-w-0 flex-1">

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >

                            <h4
                                class="text-sm font-semibold
                                       text-[#34483F]"
                            >
                                Order #{{ $resolvedOrderId }}
                            </h4>

                            <span
                                class="
                                    inline-flex items-center
                                    gap-1.5 rounded-full
                                    border px-2.5 py-1
                                    text-[9px] font-semibold
                                    {{ $data['class'] }}
                                "
                            >
                                <i
                                    data-lucide="{{ $data['icon'] }}"
                                    class="h-3 w-3"
                                ></i>

                                {{ $data['label'] }}
                            </span>

                        </div>


                        <div
                            class="mt-4 rounded-xl
                                   bg-[#F7F9F8]
                                   p-4"
                        >

                            <div
                                class="flex items-start gap-3"
                            >
                                <i
                                    data-lucide="store"
                                    class="mt-0.5 h-4 w-4
                                           shrink-0 text-[#1F6F5B]"
                                ></i>

                                <div>
                                    <p
                                        class="text-[9px]
                                               uppercase
                                               tracking-[0.08em]
                                               text-[#96A29C]"
                                    >
                                        Pickup From
                                    </p>

                                    <p
                                        class="mt-1 text-xs
                                               font-semibold
                                               text-[#52635B]"
                                    >
                                        {{ $sellerName }}
                                    </p>
                                </div>
                            </div>

                        </div>


                        {{-- PICKUP PROGRESS --}}
                        <div
                            class="mt-4 grid grid-cols-3
                                   gap-2"
                        >

                            @php
                                $acceptedDone =
                                    true;

                                $pickedDone =
                                    in_array(
                                        $status,
                                        [
                                            'picked_up',
                                            'at_sorting_center'
                                        ]
                                    );

                                $sortingDone =
                                    $status ===
                                    'at_sorting_center';
                            @endphp


                            <div
                                class="rounded-xl
                                       {{ $acceptedDone
                                            ? 'bg-[#EEF8F3]'
                                            : 'bg-[#F5F6F5]' }}
                                       p-3"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-4 w-4
                                           {{ $acceptedDone
                                                ? 'text-[#1F6F5B]'
                                                : 'text-[#9AA59F]' }}"
                                ></i>

                                <p
                                    class="mt-2 text-[9px]
                                           font-semibold
                                           text-[#52635B]"
                                >
                                    Accepted
                                </p>
                            </div>


                            <div
                                class="rounded-xl
                                       {{ $pickedDone
                                            ? 'bg-[#EEF8F3]'
                                            : 'bg-[#F5F6F5]' }}
                                       p-3"
                            >
                                <i
                                    data-lucide="scan-line"
                                    class="h-4 w-4
                                           {{ $pickedDone
                                                ? 'text-[#1F6F5B]'
                                                : 'text-[#9AA59F]' }}"
                                ></i>

                                <p
                                    class="mt-2 text-[9px]
                                           font-semibold
                                           text-[#52635B]"
                                >
                                    Scan Pickup
                                </p>
                            </div>


                            <div
                                class="rounded-xl
                                       {{ $sortingDone
                                            ? 'bg-[#EEF8F3]'
                                            : 'bg-[#F5F6F5]' }}
                                       p-3"
                            >
                                <i
                                    data-lucide="warehouse"
                                    class="h-4 w-4
                                           {{ $sortingDone
                                                ? 'text-[#1F6F5B]'
                                                : 'text-[#9AA59F]' }}"
                                ></i>

                                <p
                                    class="mt-2 text-[9px]
                                           font-semibold
                                           text-[#52635B]"
                                >
                                    Sorting Center
                                </p>
                            </div>

                        </div>

                    </div>


                    <div
                        class="w-full xl:w-[250px]"
                    >

                        @if($status === 'ready_for_pickup')

                            <div
                                class="mb-3 rounded-xl
                                       border border-[#DDE6E1]
                                       bg-[#FAFCFB]
                                       p-3"
                            >
                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-[#73827A]"
                                >
                                    Go to the seller, collect the
                                    parcel, then scan or confirm it
                                    before leaving.
                                </p>
                            </div>


                            <form
                                method="POST"
                                action="{{ route(
                                    'rider.order.status',
                                    $resolvedOrderId
                                ) }}"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="picked_up"
                                >

                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           text-[11px]
                                           font-semibold
                                           text-white transition
                                           hover:bg-[#1F6F5B]"
                                >
                                    <i
                                        data-lucide="scan-line"
                                        class="h-4 w-4"
                                    ></i>

                                    Scan & Confirm Pickup
                                </button>
                            </form>


                        @elseif($status === 'picked_up')

                            <div
                                class="mb-3 rounded-xl
                                       border border-sky-200
                                       bg-sky-50 p-3"
                            >
                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-sky-700"
                                >
                                    Parcel collected. Deliver it
                                    to the SUKI Sorting Center.
                                </p>
                            </div>


                            <form
                                method="POST"
                                action="{{ route(
                                    'rider.order.status',
                                    $resolvedOrderId
                                ) }}"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="at_sorting_center"
                                >

                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           text-[11px]
                                           font-semibold
                                           text-white transition
                                           hover:bg-[#1F6F5B]"
                                >
                                    <i
                                        data-lucide="warehouse"
                                        class="h-4 w-4"
                                    ></i>

                                    Confirm Sorting Center Arrival
                                </button>
                            </form>


                        @elseif($status === 'at_sorting_center')

                            <div
                                class="rounded-xl
                                       border border-emerald-200
                                       bg-emerald-50 p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="circle-check"
                                        class="mt-0.5 h-4 w-4
                                               shrink-0
                                               text-emerald-700"
                                    ></i>

                                    <div>
                                        <p
                                            class="text-[10px]
                                                   font-semibold
                                                   text-emerald-800"
                                        >
                                            Pickup completed
                                        </p>

                                        <p
                                            class="mt-1 text-[9px]
                                                   leading-5
                                                   text-emerald-700"
                                        >
                                            Logistics now handles
                                            parcel sorting and final
                                            rider assignment.
                                        </p>
                                    </div>
                                </div>
                            </div>

                        @endif

                    </div>

                </div>

            </article>


        @empty

            <div
                class="px-6 py-12 text-center"
            >
                <div
                    class="mx-auto flex h-12 w-12
                           items-center justify-center
                           rounded-2xl bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >
                    <i
                        data-lucide="clipboard-check"
                        class="h-5 w-5"
                    ></i>
                </div>

                <p
                    class="mt-4 text-sm font-semibold
                           text-[#34483F]"
                >
                    No active pickup tasks
                </p>

                <p
                    class="mt-1 text-xs
                           text-[#849089]"
                >
                    Accepted pickup requests will
                    appear here.
                </p>
            </div>

        @endforelse

    </section>

</div>


{{-- =========================================================
    DELIVERY WORKFLOW
========================================================= --}}

<div
    id="deliveryWorkflow"
    class="workflow-panel hidden space-y-6"
>


    {{-- FLOW GUIDE --}}
    <section
        class="overflow-hidden rounded-2xl
               border border-[#D9E6DF]
               bg-[#F1F8F4]"
    >

        <div
            class="flex items-start gap-4 p-5"
        >

            <div
                class="flex h-10 w-10 shrink-0
                       items-center justify-center
                       rounded-xl bg-white
                       text-[#1F6F5B]"
            >
                <i
                    data-lucide="bike"
                    class="h-[18px] w-[18px]"
                ></i>
            </div>

            <div>
                <p
                    class="text-xs font-semibold
                           text-[#294C42]"
                >
                    Customer Delivery Workflow
                </p>

                <p
                    class="mt-1 text-[10px]
                           leading-5 text-[#6B8178]"
                >
                    Receive Logistics assignment →
                    view address → pick up from
                    Sorting Center → Out for Delivery →
                    deliver to customer.
                </p>

                <div
                    class="mt-4 flex flex-wrap
                           items-center gap-2"
                >

                    @foreach([
                        'Assigned',
                        'Sorting Center',
                        'Out for Delivery',
                        'Deliver',
                        'Buyer Confirm'
                    ] as $step)

                        <span
                            class="rounded-full
                                   border border-[#D3E4DB]
                                   bg-white
                                   px-2.5 py-1
                                   text-[9px] font-semibold
                                   text-[#587067]"
                        >
                            {{ $step }}
                        </span>

                        @if(!$loop->last)
                            <i
                                data-lucide="arrow-right"
                                class="h-3 w-3
                                       text-[#90A39A]"
                            ></i>
                        @endif

                    @endforeach

                </div>

            </div>

        </div>

    </section>


    {{-- DELIVERY TASKS --}}
    <section
        class="overflow-hidden rounded-2xl
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
                    class="text-sm font-semibold
                           text-[#24312C]"
                >
                    My Delivery Assignments
                </h3>

                <p
                    class="mt-0.5 text-[11px]
                           text-[#7C8983]"
                >
                    Parcels assigned to you by
                    the Sorting Center.
                </p>
            </div>

            <span
                class="rounded-full bg-[#EEF5F1]
                       px-2.5 py-1 text-[9px]
                       font-semibold text-[#1F6F5B]"
            >
                {{ $myDeliveryTasks->count() }}
                task(s)
            </span>
        </div>


        @forelse($myDeliveryTasks as $orderId => $order)

            @php

                $resolvedOrderId =
                    $order['id']
                    ?? $orderId;

                $status =
                    $order['status']
                    ?? 'assigned_to_rider';

                $data =
                    $statusConfig[$status]
                    ?? [
                        'label' => ucfirst(
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

                $customerName =
                    $getCustomerName($order);

                $customerPhone =
                    $getCustomerPhone($order);

                $deliveryAddress =
                    $formatAddress($order);

                $area =
                    $order['assigned_area']
                    ?? $order['area']
                    ?? null;

            @endphp


            <article
                class="border-b border-[#EDF1EF]
                       p-5 last:border-b-0"
            >

                {{-- TOP --}}
                <div
                    class="flex flex-col gap-5
                           xl:flex-row"
                >

                    <div class="min-w-0 flex-1">

                        <div
                            class="flex flex-wrap
                                   items-center gap-2"
                        >

                            <h4
                                class="text-sm font-semibold
                                       text-[#34483F]"
                            >
                                Order #{{ $resolvedOrderId }}
                            </h4>

                            <span
                                class="
                                    inline-flex items-center
                                    gap-1.5 rounded-full
                                    border px-2.5 py-1
                                    text-[9px] font-semibold
                                    {{ $data['class'] }}
                                "
                            >
                                <i
                                    data-lucide="{{ $data['icon'] }}"
                                    class="h-3 w-3"
                                ></i>

                                {{ $data['label'] }}
                            </span>

                        </div>


                        {{-- CUSTOMER / ADDRESS --}}
                        <div
                            class="mt-4 grid gap-3
                                   md:grid-cols-2"
                        >

                            <div
                                class="rounded-xl
                                       bg-[#F7F9F8]
                                       p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="user-round"
                                        class="mt-0.5 h-4 w-4
                                               shrink-0
                                               text-[#1F6F5B]"
                                    ></i>

                                    <div class="min-w-0">
                                        <p
                                            class="text-[9px]
                                                   uppercase
                                                   tracking-[0.08em]
                                                   text-[#96A29C]"
                                        >
                                            Customer
                                        </p>

                                        <p
                                            class="mt-1 text-xs
                                                   font-semibold
                                                   text-[#52635B]"
                                        >
                                            {{ $customerName }}
                                        </p>

                                        @if($customerPhone)
                                            <p
                                                class="mt-1 text-[10px]
                                                       text-[#849089]"
                                            >
                                                {{ $customerPhone }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>


                            <div
                                class="rounded-xl
                                       bg-[#F7F9F8]
                                       p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="map-pin"
                                        class="mt-0.5 h-4 w-4
                                               shrink-0
                                               text-[#1F6F5B]"
                                    ></i>

                                    <div class="min-w-0">
                                        <p
                                            class="text-[9px]
                                                   uppercase
                                                   tracking-[0.08em]
                                                   text-[#96A29C]"
                                        >
                                            Delivery Address
                                        </p>

                                        <p
                                            class="mt-1 text-[10px]
                                                   leading-5
                                                   text-[#52635B]"
                                        >
                                            {{ $deliveryAddress }}
                                        </p>

                                        @if($area)
                                            <span
                                                class="mt-2 inline-flex
                                                       rounded-full
                                                       bg-[#DDF3EC]
                                                       px-2 py-1
                                                       text-[9px]
                                                       font-semibold
                                                       text-[#173F35]"
                                            >
                                                {{ $area }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        </div>


                        {{-- AMOUNT --}}
                        <div
                            class="mt-3 flex flex-wrap
                                   gap-3"
                        >

                            <div
                                class="rounded-lg
                                       border border-[#E5EBE7]
                                       bg-white px-3 py-2"
                            >
                                <p
                                    class="text-[8px]
                                           uppercase
                                           text-[#98A39E]"
                                >
                                    Order Total
                                </p>

                                <p
                                    class="mt-0.5 text-[11px]
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    ₱{{ number_format(
                                        (float) (
                                            $order['total']
                                            ?? 0
                                        ),
                                        2
                                    ) }}
                                </p>
                            </div>


                            <div
                                class="rounded-lg
                                       border border-[#E5EBE7]
                                       bg-white px-3 py-2"
                            >
                                <p
                                    class="text-[8px]
                                           uppercase
                                           text-[#98A39E]"
                                >
                                    Payment
                                </p>

                                <p
                                    class="mt-0.5 text-[11px]
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ strtoupper(
                                        $order['payment_method']
                                        ?? 'COD'
                                    ) }}
                                </p>
                            </div>

                        </div>

                    </div>


                    {{-- ACTION --}}
                    <div
                        class="w-full xl:w-[270px]"
                    >

                        @if($status === 'assigned_to_rider')

                            <div
                                class="mb-3 rounded-xl
                                       border border-violet-200
                                       bg-violet-50 p-3"
                            >
                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-violet-700"
                                >
                                    Pick up this parcel from the
                                    Sorting Center before starting
                                    customer delivery.
                                </p>
                            </div>


                            <form
                                method="POST"
                                action="{{ route(
                                    'rider.order.status',
                                    $resolvedOrderId
                                ) }}"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="status"
                                    value="out_for_delivery"
                                >

                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           text-[11px]
                                           font-semibold
                                           text-white transition
                                           hover:bg-[#1F6F5B]"
                                >
                                    <i
                                        data-lucide="navigation"
                                        class="h-4 w-4"
                                    ></i>

                                    Pick Up & Start Delivery
                                </button>
                            </form>


                        @elseif($status === 'out_for_delivery')

                            <div
                                class="mb-3 rounded-xl
                                       border border-orange-200
                                       bg-orange-50 p-3"
                            >
                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-orange-700"
                                >
                                    Deliver the parcel to the
                                    customer, then record whether
                                    delivery was successful.
                                </p>
                            </div>


                            <div class="grid grid-cols-2 gap-2">

                                <form
                                    method="POST"
                                    action="{{ route(
                                        'rider.order.status',
                                        $resolvedOrderId
                                    ) }}"
                                    class="delivered-form"
                                >
                                    @csrf

                                    <input
                                        type="hidden"
                                        name="status"
                                        value="delivered"
                                    >

                                    <button
                                        type="submit"
                                        class="inline-flex h-10
                                               w-full items-center
                                               justify-center gap-2
                                               rounded-xl
                                               bg-[#173F35]
                                               text-[10px]
                                               font-semibold
                                               text-white transition
                                               hover:bg-[#1F6F5B]"
                                    >
                                        <i
                                            data-lucide="check"
                                            class="h-4 w-4"
                                        ></i>

                                        Delivered
                                    </button>
                                </form>


                                <button
                                    type="button"
                                    data-failed-order="{{ $resolvedOrderId }}"
                                    class="delivery-failed-button
                                           inline-flex h-10
                                           items-center justify-center
                                           gap-2 rounded-xl
                                           border border-red-200
                                           bg-white
                                           text-[10px]
                                           font-semibold
                                           text-red-600
                                           transition
                                           hover:bg-red-50"
                                >
                                    <i
                                        data-lucide="x"
                                        class="h-4 w-4"
                                    ></i>

                                    Failed
                                </button>

                            </div>


                        @elseif($status === 'delivered')

                            <div
                                class="rounded-xl
                                       border border-emerald-200
                                       bg-emerald-50 p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="map-pin-check"
                                        class="mt-0.5 h-4 w-4
                                               shrink-0
                                               text-emerald-700"
                                    ></i>

                                    <div>
                                        <p
                                            class="text-[10px]
                                                   font-semibold
                                                   text-emerald-800"
                                        >
                                            Delivery successful
                                        </p>

                                        <p
                                            class="mt-1 text-[9px]
                                                   leading-5
                                                   text-emerald-700"
                                        >
                                            Waiting for the buyer to
                                            confirm receipt. The Rider
                                            cannot mark this order as
                                            Completed.
                                        </p>
                                    </div>
                                </div>
                            </div>


                        @elseif($status === 'completed')

                            <div
                                class="rounded-xl
                                       border border-emerald-200
                                       bg-emerald-50 p-4"
                            >
                                <div
                                    class="flex items-center gap-3"
                                >
                                    <i
                                        data-lucide="badge-check"
                                        class="h-4 w-4
                                               text-emerald-700"
                                    ></i>

                                    <div>
                                        <p
                                            class="text-[10px]
                                                   font-semibold
                                                   text-emerald-800"
                                        >
                                            Order Completed
                                        </p>

                                        <p
                                            class="mt-1 text-[9px]
                                                   text-emerald-700"
                                        >
                                            Buyer confirmed receipt.
                                        </p>
                                    </div>
                                </div>
                            </div>


                        @elseif($status === 'delivery_failed')

                            <div
                                class="rounded-xl
                                       border border-red-200
                                       bg-red-50 p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="triangle-alert"
                                        class="mt-0.5 h-4 w-4
                                               shrink-0
                                               text-red-600"
                                    ></i>

                                    <div>
                                        <p
                                            class="text-[10px]
                                                   font-semibold
                                                   text-red-800"
                                        >
                                            Delivery Failed
                                        </p>

                                        <p
                                            class="mt-1 text-[9px]
                                                   leading-5
                                                   text-red-700"
                                        >
                                            {{ $order['delivery_failure_reason']
                                                ?? 'No reason recorded.' }}
                                        </p>

                                        <p
                                            class="mt-2 text-[9px]
                                                   leading-5
                                                   text-red-600"
                                        >
                                            Waiting for Logistics to
                                            reschedule delivery or
                                            process parcel return.
                                        </p>
                                    </div>
                                </div>
                            </div>


                        @elseif($status === 'returned')

                            <div
                                class="rounded-xl
                                       border border-rose-200
                                       bg-rose-50 p-4"
                            >
                                <div
                                    class="flex items-start gap-3"
                                >
                                    <i
                                        data-lucide="rotate-ccw"
                                        class="mt-0.5 h-4 w-4
                                               text-rose-700"
                                    ></i>

                                    <div>
                                        <p
                                            class="text-[10px]
                                                   font-semibold
                                                   text-rose-800"
                                        >
                                            Parcel Returned
                                        </p>

                                        <p
                                            class="mt-1 text-[9px]
                                                   text-rose-700"
                                        >
                                            Logistics processed this
                                            parcel for return.
                                        </p>
                                    </div>
                                </div>
                            </div>

                        @endif

                    </div>

                </div>

            </article>


        @empty

            <div
                class="px-6 py-14 text-center"
            >

                <div
                    class="mx-auto flex h-12 w-12
                           items-center justify-center
                           rounded-2xl bg-[#EEF5F1]
                           text-[#1F6F5B]"
                >
                    <i
                        data-lucide="bike"
                        class="h-5 w-5"
                    ></i>
                </div>

                <p
                    class="mt-4 text-sm font-semibold
                           text-[#34483F]"
                >
                    No delivery assignments
                </p>

                <p
                    class="mx-auto mt-1 max-w-sm
                           text-xs leading-5
                           text-[#849089]"
                >
                    After Logistics sorts a parcel
                    and assigns your delivery area,
                    it will appear here.
                </p>

            </div>

        @endforelse

    </section>

</div>


{{-- =========================================================
    DELIVERY FAILED MODAL
========================================================= --}}

<div
    id="deliveryFailedModal"
    class="fixed inset-0 z-[100]
           hidden items-center justify-center
           bg-[#102C25]/45
           px-4 backdrop-blur-[2px]"
>

    <div
        class="w-full max-w-md
               rounded-2xl
               border border-[#E1E8E4]
               bg-white
               shadow-[0_24px_80px_rgba(23,63,53,.18)]"
    >

        <div class="p-5 sm:p-6">

            <div
                class="flex h-11 w-11
                       items-center justify-center
                       rounded-xl bg-red-50
                       text-red-600"
            >
                <i
                    data-lucide="triangle-alert"
                    class="h-5 w-5"
                ></i>
            </div>


            <h3
                class="mt-4 text-lg
                       font-semibold
                       tracking-[-0.03em]
                       text-[#24312C]"
            >
                Record Delivery Failure
            </h3>


            <p
                class="mt-2 text-xs
                       leading-6 text-[#728078]"
            >
                Record why the delivery was
                unsuccessful. Logistics will use
                this information for rescheduling
                or parcel return.
            </p>


            <form
                id="deliveryFailedForm"
                method="POST"
                action=""
                class="mt-5"
            >

                @csrf

                <input
                    type="hidden"
                    name="status"
                    value="delivery_failed"
                >


                <label
                    for="failure_reason"
                    class="mb-2 block
                           text-xs font-semibold
                           text-[#34483F]"
                >
                    Failure Reason
                </label>


                <textarea
                    id="failure_reason"
                    name="failure_reason"
                    rows="4"
                    maxlength="500"
                    required
                    placeholder="Example: Customer unavailable, incorrect address, customer requested reschedule..."
                    class="w-full resize-none
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-4 py-3
                           text-xs leading-6
                           text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                ></textarea>


                <div
                    class="mt-5 flex
                           flex-col-reverse gap-2
                           sm:flex-row
                           sm:justify-end"
                >

                    <button
                        type="button"
                        id="cancelDeliveryFailure"
                        class="inline-flex h-10
                               items-center justify-center
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white px-4
                               text-xs font-semibold
                               text-[#52635B]
                               transition
                               hover:bg-[#F5F8F6]"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="inline-flex h-10
                               items-center justify-center
                               gap-2 rounded-xl
                               bg-red-600 px-4
                               text-xs font-semibold
                               text-white transition
                               hover:bg-red-700"
                    >
                        <i
                            data-lucide="triangle-alert"
                            class="h-4 w-4"
                        ></i>

                        Record Failure
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /* =====================================================
           WORKFLOW TABS
        ====================================================== */

        const tabs =
            document.querySelectorAll(
                '.workflow-tab'
            );

        const pickupPanel =
            document.getElementById(
                'pickupWorkflow'
            );

        const deliveryPanel =
            document.getElementById(
                'deliveryWorkflow'
            );


        function setWorkflow(
            workflow
        ) {

            const pickupActive =
                workflow === 'pickup';


            pickupPanel?.classList.toggle(
                'hidden',
                !pickupActive
            );

            deliveryPanel?.classList.toggle(
                'hidden',
                pickupActive
            );


            tabs.forEach(
                function (tab) {

                    const active =
                        tab.dataset.workflowTab
                        === workflow;


                    tab.classList.toggle(
                        'bg-[#173F35]',
                        active
                    );

                    tab.classList.toggle(
                        'text-white',
                        active
                    );

                    tab.classList.toggle(
                        'text-[#68776F]',
                        !active
                    );

                }
            );

        }


        tabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        setWorkflow(
                            this.dataset.workflowTab
                        );

                    }
                );

            }
        );


        /* =====================================================
           DELIVERY FAILED MODAL
        ====================================================== */

        const modal =
            document.getElementById(
                'deliveryFailedModal'
            );

        const failureForm =
            document.getElementById(
                'deliveryFailedForm'
            );

        const cancelFailure =
            document.getElementById(
                'cancelDeliveryFailure'
            );

        const failureReason =
            document.getElementById(
                'failure_reason'
            );


        document
            .querySelectorAll(
                '.delivery-failed-button'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            const orderId =
                                this.dataset.failedOrder;


                            failureForm.action =
                                `/rider/orders/${orderId}/status`;


                            if (failureReason) {
                                failureReason.value = '';
                            }


                            modal.classList.remove(
                                'hidden'
                            );

                            modal.classList.add(
                                'flex'
                            );

                            document.body.classList.add(
                                'overflow-hidden'
                            );


                            setTimeout(
                                function () {

                                    failureReason
                                        ?.focus();

                                },
                                100
                            );

                        }
                    );

                }
            );


        function closeFailureModal() {

            modal?.classList.add(
                'hidden'
            );

            modal?.classList.remove(
                'flex'
            );

            document.body.classList.remove(
                'overflow-hidden'
            );

        }


        cancelFailure?.addEventListener(
            'click',
            closeFailureModal
        );


        modal?.addEventListener(
            'click',
            function (event) {

                if (event.target === modal) {
                    closeFailureModal();
                }

            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape' &&
                    !modal?.classList.contains(
                        'hidden'
                    )
                ) {
                    closeFailureModal();
                }

            }
        );


        if (
            typeof lucide !== 'undefined' &&
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