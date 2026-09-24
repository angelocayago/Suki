@extends('layouts.rider')

@section('title', 'Delivery History')
@section('page-heading', 'Delivery History')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE ORDERS
    |--------------------------------------------------------------------------
    */

    $historyOrders =
        collect($orders ?? []);


    /*
    |--------------------------------------------------------------------------
    | COUNTS
    |--------------------------------------------------------------------------
    */

    $deliveredCount =
        $historyOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'delivered';

            })
            ->count();


    $completedCount =
        $historyOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'completed';

            })
            ->count();


    $failedCount =
        $historyOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'delivery_failed';

            })
            ->count();


    $returnedCount =
        $historyOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'returned';

            })
            ->count();


    /*
    |--------------------------------------------------------------------------
    | STATUS DESIGN
    |--------------------------------------------------------------------------
    */

    $statusConfig = [

        'delivered' => [

            'label' =>
                'Delivered',

            'description' =>
                'Waiting for buyer confirmation.',

            'class' =>
                'border-emerald-200 bg-emerald-50 text-emerald-700',

            'icon' =>
                'map-pin-check',
        ],


        'completed' => [

            'label' =>
                'Completed',

            'description' =>
                'Buyer confirmed receipt.',

            'class' =>
                'border-[#BFE5D5] bg-[#EAF7F1] text-[#176149]',

            'icon' =>
                'badge-check',
        ],


        'delivery_failed' => [

            'label' =>
                'Delivery Failed',

            'description' =>
                'Delivery attempt was unsuccessful.',

            'class' =>
                'border-red-200 bg-red-50 text-red-700',

            'icon' =>
                'triangle-alert',
        ],


        'returned' => [

            'label' =>
                'Returned',

            'description' =>
                'Parcel processed for return.',

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
                $order['address']
                ?? 'Address unavailable.';

        };

@endphp


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

        <div
            class="mb-2
                   flex items-center gap-2
                   text-[11px]
                   font-medium
                   text-[#8A9791]"
        >

            <a
                href="{{ route('rider.dashboard') }}"
                class="transition
                       hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>


            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>


            <span class="text-[#52635B]">
                Delivery History
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Delivery History
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Review your past delivery assignments,
            successful deliveries, failed attempts,
            and returned parcels.
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
            data-lucide="bike"
            class="h-4 w-4"
        ></i>

        Active Deliveries

    </a>

</div>


{{-- =========================================================
    SUMMARY CARDS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    {{-- DELIVERED --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Delivered
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $deliveredCount }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-emerald-50
                       text-emerald-700"
            >

                <i
                    data-lucide="map-pin-check"
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
            Waiting for buyer confirmation
        </p>

    </div>


    {{-- COMPLETED --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

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
                    {{ $completedCount }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-[#EAF7F1]
                       text-[#176149]"
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
            Buyer-confirmed deliveries
        </p>

    </div>


    {{-- FAILED --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Failed
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $failedCount }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-red-50
                       text-red-600"
            >

                <i
                    data-lucide="triangle-alert"
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
            Unsuccessful delivery attempts
        </p>

    </div>


    {{-- RETURNED --}}
    <div
        class="rounded-2xl
               border border-[#E1E8E4]
               bg-white
               p-5"
    >

        <div
            class="flex items-start
                   justify-between gap-3"
        >

            <div>

                <p
                    class="text-[9px]
                           font-semibold
                           uppercase
                           tracking-[0.12em]
                           text-[#839189]"
                >
                    Returned
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $returnedCount }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       items-center justify-center
                       rounded-xl
                       bg-rose-50
                       text-rose-700"
            >

                <i
                    data-lucide="rotate-ccw"
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
            Parcels processed for return
        </p>

    </div>

</div>


{{-- =========================================================
    HISTORY PANEL
========================================================= --}}

<section
    class="overflow-hidden
           rounded-2xl
           border border-[#E1E8E4]
           bg-white"
>


    {{-- HEADER --}}
    <div
        class="flex flex-col gap-4
               border-b border-[#EDF1EF]
               px-5 py-5
               lg:flex-row
               lg:items-center
               lg:justify-between"
    >

        <div>

            <h3
                class="text-sm
                       font-semibold
                       text-[#24312C]"
            >
                Delivery Records
            </h3>


            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                {{ $historyOrders->count() }}
                historical delivery record(s)
            </p>

        </div>


        <div
            class="grid gap-2
                   sm:grid-cols-[230px_170px]"
        >

            {{-- SEARCH --}}
            <div class="relative">

                <i
                    data-lucide="search"
                    class="pointer-events-none
                           absolute left-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#91A099]"
                ></i>


                <input
                    id="historySearch"
                    type="text"
                    placeholder="Search order or customer"
                    class="h-9 w-full
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           pl-9 pr-3
                           text-[10px]
                           text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/60"
                >

            </div>


            {{-- FILTER --}}
            <select
                id="historyStatus"
                class="h-9
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-3
                       text-[10px]
                       font-medium
                       text-[#52635B]
                       focus:border-[#1F6F5B]
                       focus:ring-4
                       focus:ring-[#DDF3EC]/60"
            >

                <option value="all">
                    All Statuses
                </option>

                <option value="delivered">
                    Delivered
                </option>

                <option value="completed">
                    Completed
                </option>

                <option value="delivery_failed">
                    Delivery Failed
                </option>

                <option value="returned">
                    Returned
                </option>

            </select>

        </div>

    </div>


    @if($historyOrders->count())


        {{-- =================================================
            DESKTOP
        ================================================== --}}

        <div
            class="hidden
                   overflow-x-auto
                   lg:block"
        >

            <table class="w-full">

                <thead>

                    <tr
                        class="border-b
                               border-[#EDF1EF]
                               bg-[#F7F9F8]"
                    >

                        <th class="px-5 py-3 text-left">
                            Order
                        </th>

                        <th class="px-5 py-3 text-left">
                            Customer
                        </th>

                        <th class="px-5 py-3 text-left">
                            Delivery Address
                        </th>

                        <th class="px-5 py-3 text-left">
                            Status
                        </th>

                        <th class="px-5 py-3 text-left">
                            Date
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach(
                        $historyOrders
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

                                    'description' =>
                                        '',

                                    'class' =>
                                        'border-gray-200 bg-gray-50 text-gray-600',

                                    'icon' =>
                                        'circle',
                                ];


                            $customerName =
                                $order['shipping_address']['name']
                                ?? $order['customer_name']
                                ?? 'Customer';


                            $customerPhone =
                                $order['shipping_address']['phone']
                                ?? $order['customer_phone']
                                ?? null;


                            $address =
                                $formatAddress(
                                    $order
                                );


                            $historyDate =
                                $order['completed_at']
                                ?? $order['delivered_at']
                                ?? $order['delivery_failed_at']
                                ?? $order['returned_at']
                                ?? $order['updated_at']
                                ?? null;


                            $searchValue =
                                strtolower(
                                    $resolvedOrderId
                                    . ' '
                                    . $customerName
                                    . ' '
                                    . $address
                                    . ' '
                                    . $status
                                );

                        @endphp


                        <tr
                            class="history-row
                                   border-b
                                   border-[#F0F3F1]
                                   transition
                                   last:border-b-0
                                   hover:bg-[#FAFCFB]"
                            data-search="{{ $searchValue }}"
                            data-status="{{ $status }}"
                        >

                            {{-- ORDER --}}
                            <td class="px-5 py-4">

                                <div
                                    class="flex
                                           items-center gap-3"
                                >

                                    <div
                                        class="flex h-9 w-9
                                               shrink-0
                                               items-center
                                               justify-center
                                               rounded-xl
                                               bg-[#EEF5F1]
                                               text-[#1F6F5B]"
                                    >
                                        <i
                                            data-lucide="package"
                                            class="h-4 w-4"
                                        ></i>
                                    </div>


                                    <div>

                                        <p
                                            class="text-xs
                                                   font-semibold
                                                   text-[#34483F]"
                                        >
                                            #{{ $resolvedOrderId }}
                                        </p>


                                        @if(
                                            !empty(
                                                $order['assigned_area']
                                            )
                                        )

                                            <p
                                                class="mt-1
                                                       text-[9px]
                                                       text-[#98A39D]"
                                            >
                                                {{ $order['assigned_area'] }}
                                            </p>

                                        @endif

                                    </div>

                                </div>

                            </td>


                            {{-- CUSTOMER --}}
                            <td class="px-5 py-4">

                                <p
                                    class="max-w-[190px]
                                           truncate
                                           text-[11px]
                                           font-semibold
                                           text-[#52635B]"
                                >
                                    {{ $customerName }}
                                </p>


                                @if($customerPhone)

                                    <p
                                        class="mt-1
                                               text-[9px]
                                               text-[#98A39D]"
                                    >
                                        {{ $customerPhone }}
                                    </p>

                                @endif

                            </td>


                            {{-- ADDRESS --}}
                            <td class="px-5 py-4">

                                <p
                                    class="max-w-[270px]
                                           text-[10px]
                                           leading-5
                                           text-[#74827B]"
                                >
                                    {{ $address }}
                                </p>

                            </td>


                            {{-- STATUS --}}
                            <td class="px-5 py-4">

                                <span
                                    class="
                                        inline-flex
                                        items-center gap-1.5
                                        rounded-full
                                        border
                                        px-2.5 py-1
                                        text-[9px]
                                        font-semibold
                                        {{ $statusData['class'] }}
                                    "
                                >

                                    <i
                                        data-lucide="{{ $statusData['icon'] }}"
                                        class="h-3 w-3"
                                    ></i>

                                    {{ $statusData['label'] }}

                                </span>


                                @if(
                                    $status ===
                                    'delivery_failed'
                                    &&
                                    !empty(
                                        $order[
                                            'delivery_failure_reason'
                                        ]
                                    )
                                )

                                    <p
                                        class="mt-2
                                               max-w-[220px]
                                               text-[9px]
                                               leading-4
                                               text-red-600"
                                    >
                                        {{ $order[
                                            'delivery_failure_reason'
                                        ] }}
                                    </p>

                                @endif

                            </td>


                            {{-- DATE --}}
                            <td class="px-5 py-4">

                                @if($historyDate)

                                    <p
                                        class="text-[10px]
                                               font-medium
                                               text-[#52635B]"
                                    >
                                        {{ \Carbon\Carbon::parse(
                                            $historyDate
                                        )->format('M d, Y') }}
                                    </p>


                                    <p
                                        class="mt-1
                                               text-[9px]
                                               text-[#9AA59F]"
                                    >
                                        {{ \Carbon\Carbon::parse(
                                            $historyDate
                                        )->format('h:i A') }}
                                    </p>

                                @else

                                    <span
                                        class="text-[10px]
                                               text-[#9AA59F]"
                                    >
                                        —
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{-- =================================================
            MOBILE / TABLET
        ================================================== --}}

        <div
            class="divide-y
                   divide-[#EDF1EF]
                   lg:hidden"
        >

            @foreach(
                $historyOrders
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

                            'description' =>
                                '',

                            'class' =>
                                'border-gray-200 bg-gray-50 text-gray-600',

                            'icon' =>
                                'circle',
                        ];


                    $customerName =
                        $order['shipping_address']['name']
                        ?? $order['customer_name']
                        ?? 'Customer';


                    $address =
                        $formatAddress(
                            $order
                        );


                    $historyDate =
                        $order['completed_at']
                        ?? $order['delivered_at']
                        ?? $order['delivery_failed_at']
                        ?? $order['returned_at']
                        ?? $order['updated_at']
                        ?? null;


                    $searchValue =
                        strtolower(
                            $resolvedOrderId
                            . ' '
                            . $customerName
                            . ' '
                            . $address
                            . ' '
                            . $status
                        );

                @endphp


                <article
                    class="history-card
                           p-4 sm:p-5"
                    data-search="{{ $searchValue }}"
                    data-status="{{ $status }}"
                >

                    <div
                        class="flex
                               items-start
                               justify-between
                               gap-4"
                    >

                        <div
                            class="flex min-w-0
                                   items-center gap-3"
                        >

                            <div
                                class="flex h-10 w-10
                                       shrink-0
                                       items-center
                                       justify-center
                                       rounded-xl
                                       bg-[#EEF5F1]
                                       text-[#1F6F5B]"
                            >

                                <i
                                    data-lucide="package"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <div class="min-w-0">

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    Order #{{ $resolvedOrderId }}
                                </p>


                                <p
                                    class="mt-1
                                           truncate
                                           text-[10px]
                                           text-[#7C8983]"
                                >
                                    {{ $customerName }}
                                </p>

                            </div>

                        </div>


                        <span
                            class="
                                inline-flex shrink-0
                                items-center gap-1
                                rounded-full border
                                px-2 py-1
                                text-[8px]
                                font-semibold
                                {{ $statusData['class'] }}
                            "
                        >
                            {{ $statusData['label'] }}
                        </span>

                    </div>


                    <div
                        class="mt-4
                               rounded-xl
                               bg-[#F7F9F8]
                               p-3"
                    >

                        <div
                            class="flex
                                   items-start gap-2"
                        >

                            <i
                                data-lucide="map-pin"
                                class="mt-0.5
                                       h-3.5 w-3.5
                                       shrink-0
                                       text-[#1F6F5B]"
                            ></i>


                            <p
                                class="text-[10px]
                                       leading-5
                                       text-[#65756D]"
                            >
                                {{ $address }}
                            </p>

                        </div>

                    </div>


                    @if(
                        $status ===
                        'delivery_failed'
                        &&
                        !empty(
                            $order[
                                'delivery_failure_reason'
                            ]
                        )
                    )

                        <div
                            class="mt-3
                                   rounded-xl
                                   border border-red-200
                                   bg-red-50
                                   p-3"
                        >

                            <p
                                class="text-[9px]
                                       font-semibold
                                       text-red-700"
                            >
                                Failure Reason
                            </p>


                            <p
                                class="mt-1
                                       text-[9px]
                                       leading-5
                                       text-red-600"
                            >
                                {{ $order[
                                    'delivery_failure_reason'
                                ] }}
                            </p>

                        </div>

                    @endif


                    <div
                        class="mt-3
                               flex items-center
                               justify-between
                               border-t
                               border-[#EDF1EF]
                               pt-3"
                    >

                        <span
                            class="text-[9px]
                                   text-[#98A39D]"
                        >
                            {{ $statusData['description'] }}
                        </span>


                        <span
                            class="text-[9px]
                                   font-medium
                                   text-[#65756D]"
                        >

                            @if($historyDate)

                                {{ \Carbon\Carbon::parse(
                                    $historyDate
                                )->format('M d, Y') }}

                            @else

                                —

                            @endif

                        </span>

                    </div>

                </article>

            @endforeach

        </div>


        {{-- =================================================
            FILTER EMPTY
        ================================================== --}}

        <div
            id="noHistoryResults"
            class="hidden
                   px-6 py-14
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-12 w-12
                       items-center
                       justify-center
                       rounded-2xl
                       bg-[#F1F4F2]
                       text-[#87958E]"
            >

                <i
                    data-lucide="search-x"
                    class="h-5 w-5"
                ></i>

            </div>


            <p
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No matching delivery records
            </p>


            <p
                class="mt-1
                       text-xs
                       text-[#849089]"
            >
                Try changing your search
                or selected status.
            </p>

        </div>


    @else

        {{-- =================================================
            EMPTY HISTORY
        ================================================== --}}

        <div
            class="px-6 py-16
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-14 w-14
                       items-center
                       justify-center
                       rounded-2xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="history"
                    class="h-6 w-6"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No delivery history yet
            </h3>


            <p
                class="mx-auto mt-1
                       max-w-md
                       text-xs
                       leading-5
                       text-[#849089]"
            >
                Finished, failed, and returned
                delivery assignments will appear here.
            </p>


            <a
                href="{{ route('rider.deliveries') }}"
                class="mt-5
                       inline-flex h-10
                       items-center
                       justify-center gap-2
                       rounded-xl
                       bg-[#173F35]
                       px-4
                       text-[11px]
                       font-semibold
                       text-white
                       transition
                       hover:bg-[#1F6F5B]"
            >

                <i
                    data-lucide="bike"
                    class="h-4 w-4"
                ></i>

                View Deliveries

            </a>

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
                'historySearch'
            );


        const status =
            document.getElementById(
                'historyStatus'
            );


        const rows =
            Array.from(
                document.querySelectorAll(
                    '.history-row'
                )
            );


        const cards =
            Array.from(
                document.querySelectorAll(
                    '.history-card'
                )
            );


        const noResults =
            document.getElementById(
                'noHistoryResults'
            );


        function matchesHistory(
            element,
            searchValue,
            statusValue
        ) {

            const searchable =
                (
                    element.dataset.search
                    || ''
                ).toLowerCase();


            const itemStatus =
                element.dataset.status
                || '';


            const matchesSearch =
                searchValue === '' ||
                searchable.includes(
                    searchValue
                );


            const matchesStatus =
                statusValue === 'all' ||
                itemStatus === statusValue;


            return (
                matchesSearch &&
                matchesStatus
            );

        }


        function filterHistory() {

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
                        matchesHistory(
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
                        matchesHistory(
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
                rows.length > 0 ||
                cards.length > 0;


            const hasVisible =
                visibleRows > 0 ||
                visibleCards > 0;


            noResults?.classList.toggle(
                'hidden',
                !hasData ||
                hasVisible
            );

        }


        search?.addEventListener(
            'input',
            filterHistory
        );


        status?.addEventListener(
            'change',
            filterHistory
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