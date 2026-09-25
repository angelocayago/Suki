@extends('layouts.rider')

@section('title', 'Earnings')
@section('page-heading', 'Earnings & Profit')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE DATA
    |--------------------------------------------------------------------------
    */

    $earningOrders =
        collect($orders ?? []);


    $totalDeliveries =
        $totalDeliveries
        ?? $earningOrders->count();


    $deliveryFee =
        (float) (
            $deliveryFee
            ?? 0
        );


    $totalEarnings =
        (float) (
            $totalEarnings
            ?? (
                $totalDeliveries
                * $deliveryFee
            )
        );


    /*
    |--------------------------------------------------------------------------
    | DELIVERY STATUS COUNTS
    |--------------------------------------------------------------------------
    |
    | DELIVERED:
    | Rider has successfully delivered the parcel,
    | but Buyer has not confirmed receipt yet.
    |
    | COMPLETED:
    | Buyer already confirmed receipt.
    |
    */

    $awaitingConfirmation =
        $earningOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'delivered';

            })
            ->count();


    $completedDeliveries =
        $earningOrders
            ->filter(function ($order) {

                return strtolower(
                    $order['status']
                    ?? ''
                ) === 'completed';

            })
            ->count();


    /*
    |--------------------------------------------------------------------------
    | TODAY
    |--------------------------------------------------------------------------
    */

    $todayDate =
        now()->format('Y-m-d');


    $todayDeliveries =
        $earningOrders
            ->filter(function ($order)
            use ($todayDate) {

                $date =
                    $order['delivered_at']
                    ?? $order['completed_at']
                    ?? null;


                if (!$date) {
                    return false;
                }


                return substr(
                    (string) $date,
                    0,
                    10
                ) === $todayDate;

            })
            ->count();


    $todayEarnings =
        $todayDeliveries
        * $deliveryFee;


    /*
    |--------------------------------------------------------------------------
    | CURRENT MONTH
    |--------------------------------------------------------------------------
    */

    $currentMonth =
        now()->format('Y-m');


    $monthlyDeliveries =
        $earningOrders
            ->filter(function ($order)
            use ($currentMonth) {

                $date =
                    $order['delivered_at']
                    ?? $order['completed_at']
                    ?? null;


                if (!$date) {
                    return false;
                }


                return substr(
                    (string) $date,
                    0,
                    7
                ) === $currentMonth;

            })
            ->count();


    $monthlyEarnings =
        $monthlyDeliveries
        * $deliveryFee;


    /*
    |--------------------------------------------------------------------------
    | STATUS DESIGN
    |--------------------------------------------------------------------------
    */

    $statusConfig = [

        'delivered' => [

            'label' =>
                'Awaiting Buyer Confirmation',

            'class' =>
                'border-amber-200 bg-amber-50 text-amber-700',

            'icon' =>
                'clock-3',
        ],


        'completed' => [

            'label' =>
                'Completed',

            'class' =>
                'border-emerald-200 bg-emerald-50 text-emerald-700',

            'icon' =>
                'badge-check',
        ],

    ];

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
                class="h-4 w-4
                       text-emerald-600"
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
                Earnings
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Earnings & Profit
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Track your successful deliveries,
            rider earnings, and delivery transaction history.
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

        View Deliveries

    </a>

</div>


{{-- =========================================================
    MAIN EARNINGS HERO
========================================================= --}}

<section
    class="relative mb-6
           overflow-hidden
           rounded-[22px]
           bg-[#173F35]
           p-6 text-white
           sm:p-7"
>

    {{-- DECORATION --}}
    <div
        class="pointer-events-none
               absolute -right-12 -top-16
               h-52 w-52
               rounded-full
               border-[35px]
               border-white/[0.04]"
    ></div>


    <div
        class="relative
               grid gap-6
               lg:grid-cols-[1fr_auto]
               lg:items-end"
    >

        <div>

            <div
                class="mb-5
                       flex h-11 w-11
                       items-center justify-center
                       rounded-xl
                       bg-white/10"
            >

                <i
                    data-lucide="wallet-cards"
                    class="h-5 w-5
                           text-[#DDF3EC]"
                ></i>

            </div>


            <p
                class="text-[10px]
                       font-semibold
                       uppercase
                       tracking-[0.14em]
                       text-white/45"
            >
                Total Rider Earnings
            </p>


            <p
                class="mt-2
                       text-3xl
                       font-semibold
                       tracking-[-0.05em]
                       sm:text-[38px]"
            >
                ₱{{ number_format(
                    $totalEarnings,
                    2
                ) }}
            </p>


            <p
                class="mt-2
                       text-[11px]
                       leading-5
                       text-white/50"
            >
                Earnings calculated from your
                successful delivery assignments.
            </p>

        </div>


        <div
            class="grid grid-cols-2
                   gap-3
                   sm:min-w-[320px]"
        >

            <div
                class="rounded-xl
                       border border-white/10
                       bg-white/[0.06]
                       p-4"
            >

                <p
                    class="text-[9px]
                           uppercase
                           tracking-[0.1em]
                           text-white/40"
                >
                    Today
                </p>

                <p
                    class="mt-2
                           text-lg
                           font-semibold"
                >
                    ₱{{ number_format(
                        $todayEarnings,
                        2
                    ) }}
                </p>

                <p
                    class="mt-1
                           text-[9px]
                           text-white/40"
                >
                    {{ $todayDeliveries }}
                    {{ $todayDeliveries === 1
                        ? 'delivery'
                        : 'deliveries' }}
                </p>

            </div>


            <div
                class="rounded-xl
                       border border-white/10
                       bg-white/[0.06]
                       p-4"
            >

                <p
                    class="text-[9px]
                           uppercase
                           tracking-[0.1em]
                           text-white/40"
                >
                    This Month
                </p>

                <p
                    class="mt-2
                           text-lg
                           font-semibold"
                >
                    ₱{{ number_format(
                        $monthlyEarnings,
                        2
                    ) }}
                </p>

                <p
                    class="mt-1
                           text-[9px]
                           text-white/40"
                >
                    {{ $monthlyDeliveries }}
                    {{ $monthlyDeliveries === 1
                        ? 'delivery'
                        : 'deliveries' }}
                </p>

            </div>

        </div>

    </div>

</section>


{{-- =========================================================
    SUMMARY CARDS
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>


    {{-- SUCCESSFUL DELIVERIES --}}
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
                    Successful Deliveries
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalDeliveries }}
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
                    data-lucide="package-check"
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
            Delivered and buyer-confirmed orders
        </p>

    </div>


    {{-- RATE --}}
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
                    Rate Per Delivery
                </p>


                <p
                    class="mt-3
                           text-xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]
                           sm:text-2xl"
                >
                    ₱{{ number_format(
                        $deliveryFee,
                        2
                    ) }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-blue-50
                       text-blue-700"
            >

                <i
                    data-lucide="banknote"
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
            Current prototype delivery rate
        </p>

    </div>


    {{-- AWAITING BUYER --}}
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
                    Awaiting Confirmation
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $awaitingConfirmation }}
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
                    data-lucide="clock-3"
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
            Delivered, waiting for Buyer receipt confirmation
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
                    Buyer Confirmed
                </p>


                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $completedDeliveries }}
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
            Orders already marked Completed
        </p>

    </div>

</div>


{{-- =========================================================
    EARNINGS HISTORY
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
                Earnings Transactions
            </h3>


            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Successful delivery transactions
                credited to your rider account.
            </p>

        </div>


        <div
            class="flex items-center gap-2"
        >

            <div
                class="relative
                       w-full
                       sm:w-[230px]"
            >

                <i
                    data-lucide="search"
                    class="pointer-events-none
                           absolute left-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#91A099]"
                ></i>


                <input
                    id="earningSearch"
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

        </div>

    </div>


    @if($earningOrders->count())


        {{-- =================================================
            DESKTOP TABLE
        ================================================== --}}

        <div
            class="hidden
                   overflow-x-auto
                   md:block"
        >

            <table class="w-full">

                <thead>

                    <tr
                        class="border-b
                               border-[#EDF1EF]
                               bg-[#F7F9F8]"
                    >

                        <th
                            class="px-5 py-3
                                   text-left"
                        >
                            Delivery
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left"
                        >
                            Customer
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left"
                        >
                            Status
                        </th>

                        <th
                            class="px-5 py-3
                                   text-left"
                        >
                            Date
                        </th>

                        <th
                            class="px-5 py-3
                                   text-right"
                        >
                            Earnings
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @foreach(
                        $earningOrders
                        as $orderId => $order
                    )

                        @php

                            $resolvedOrderId =
                                $order['id']
                                ?? $orderId;


                            $customerName =
                                $order['shipping_address']['name']
                                ?? $order['customer_name']
                                ?? $order['buyer_name']
                                ?? 'Customer';


                            $status =
                                strtolower(
                                    $order['status']
                                    ?? 'delivered'
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
                                        'border-gray-200 bg-gray-100 text-gray-600',

                                    'icon' =>
                                        'circle',
                                ];


                            $transactionDate =
                                $order['delivered_at']
                                ?? $order['completed_at']
                                ?? $order['updated_at']
                                ?? null;


                            $searchValue =
                                strtolower(
                                    $resolvedOrderId
                                    . ' '
                                    . $customerName
                                    . ' '
                                    . $status
                                );

                        @endphp


                        <tr
                            class="earning-row
                                   border-b
                                   border-[#F0F3F1]
                                   transition
                                   last:border-b-0
                                   hover:bg-[#FAFCFB]"
                            data-search="{{ $searchValue }}"
                        >

                            {{-- ORDER --}}
                            <td class="px-5 py-4">

                                <div
                                    class="flex items-center
                                           gap-3"
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
                                            data-lucide="package-check"
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


                                        <p
                                            class="mt-0.5
                                                   text-[9px]
                                                   text-[#96A29C]"
                                        >
                                            SUKI Delivery
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- CUSTOMER --}}
                            <td class="px-5 py-4">

                                <p
                                    class="max-w-[220px]
                                           truncate
                                           text-[11px]
                                           font-medium
                                           text-[#52635B]"
                                >
                                    {{ $customerName }}
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

                            </td>


                            {{-- DATE --}}
                            <td
                                class="px-5 py-4
                                       text-[10px]
                                       text-[#74827B]"
                            >

                                @if($transactionDate)

                                    {{ \Carbon\Carbon::parse(
                                        $transactionDate
                                    )->format('M d, Y') }}

                                    <p
                                        class="mt-0.5
                                               text-[9px]
                                               text-[#A0AAA5]"
                                    >
                                        {{ \Carbon\Carbon::parse(
                                            $transactionDate
                                        )->format('h:i A') }}
                                    </p>

                                @else

                                    —

                                @endif

                            </td>


                            {{-- EARNINGS --}}
                            <td
                                class="px-5 py-4
                                       text-right"
                            >

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#1F6F5B]"
                                >
                                    +₱{{ number_format(
                                        $deliveryFee,
                                        2
                                    ) }}
                                </p>


                                <p
                                    class="mt-0.5
                                           text-[9px]
                                           text-[#98A39D]"
                                >
                                    Delivery earning
                                </p>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        {{-- =================================================
            MOBILE
        ================================================== --}}

        <div
            class="divide-y
                   divide-[#EDF1EF]
                   md:hidden"
        >

            @foreach(
                $earningOrders
                as $orderId => $order
            )

                @php

                    $resolvedOrderId =
                        $order['id']
                        ?? $orderId;


                    $customerName =
                        $order['shipping_address']['name']
                        ?? $order['customer_name']
                        ?? $order['buyer_name']
                        ?? 'Customer';


                    $status =
                        strtolower(
                            $order['status']
                            ?? 'delivered'
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
                                'border-gray-200 bg-gray-100 text-gray-600',

                            'icon' =>
                                'circle',
                        ];


                    $transactionDate =
                        $order['delivered_at']
                        ?? $order['completed_at']
                        ?? $order['updated_at']
                        ?? null;


                    $searchValue =
                        strtolower(
                            $resolvedOrderId
                            . ' '
                            . $customerName
                            . ' '
                            . $status
                        );

                @endphp


                <article
                    class="earning-card
                           p-4"
                    data-search="{{ $searchValue }}"
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
                                    data-lucide="package-check"
                                    class="h-4 w-4"
                                ></i>

                            </div>


                            <div class="min-w-0">

                                <p
                                    class="text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    #{{ $resolvedOrderId }}
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
                                inline-flex
                                shrink-0
                                items-center gap-1
                                rounded-full
                                border
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
                               grid grid-cols-2
                               gap-2"
                    >

                        <div
                            class="rounded-xl
                                   bg-[#F7F9F8]
                                   px-3 py-2.5"
                        >

                            <p
                                class="text-[8px]
                                       uppercase
                                       tracking-[0.08em]
                                       text-[#9AA59F]"
                            >
                                Delivered
                            </p>


                            <p
                                class="mt-1
                                       text-[10px]
                                       font-medium
                                       text-[#52635B]"
                            >

                                @if($transactionDate)

                                    {{ \Carbon\Carbon::parse(
                                        $transactionDate
                                    )->format('M d, Y') }}

                                @else

                                    —

                                @endif

                            </p>

                        </div>


                        <div
                            class="rounded-xl
                                   bg-[#EEF8F3]
                                   px-3 py-2.5
                                   text-right"
                        >

                            <p
                                class="text-[8px]
                                       uppercase
                                       tracking-[0.08em]
                                       text-[#739083]"
                            >
                                Earnings
                            </p>


                            <p
                                class="mt-1
                                       text-xs
                                       font-semibold
                                       text-[#1F6F5B]"
                            >
                                +₱{{ number_format(
                                    $deliveryFee,
                                    2
                                ) }}
                            </p>

                        </div>

                    </div>

                </article>

            @endforeach

        </div>


        {{-- NO SEARCH RESULT --}}
        <div
            id="noEarningResults"
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
                No matching transactions
            </p>


            <p
                class="mt-1
                       text-xs
                       text-[#849089]"
            >
                Try another order number
                or customer name.
            </p>

        </div>


    @else

        {{-- EMPTY --}}
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
                    data-lucide="wallet-cards"
                    class="h-6 w-6"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No rider earnings yet
            </h3>


            <p
                class="mx-auto mt-1
                       max-w-md
                       text-xs
                       leading-5
                       text-[#849089]"
            >
                Earnings will appear here after
                you successfully complete delivery assignments.
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


{{-- =========================================================
    RATE INFORMATION
========================================================= --}}

<div
    class="mt-6
           rounded-2xl
           border border-[#D9E6DF]
           bg-[#F1F8F4]
           p-5"
>

    <div
        class="flex items-start gap-4"
    >

        <div
            class="flex h-10 w-10
                   shrink-0
                   items-center
                   justify-center
                   rounded-xl
                   bg-white
                   text-[#1F6F5B]"
        >

            <i
                data-lucide="info"
                class="h-[18px] w-[18px]"
            ></i>

        </div>


        <div>

            <p
                class="text-xs
                       font-semibold
                       text-[#294C42]"
            >
                Current Earnings Calculation
            </p>


            <p
                class="mt-1
                       max-w-3xl
                       text-[10px]
                       leading-5
                       text-[#6B8178]"
            >
                The current project uses a prototype
                delivery rate of
                <strong class="text-[#294C42]">
                    ₱{{ number_format(
                        $deliveryFee,
                        2
                    ) }}
                </strong>
                per successful delivery.
                This can later be connected to the
                final SUKI Logistics delivery-area
                and rider compensation system.
            </p>

        </div>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const searchInput =
            document.getElementById(
                'earningSearch'
            );


        const rows =
            Array.from(
                document.querySelectorAll(
                    '.earning-row'
                )
            );


        const cards =
            Array.from(
                document.querySelectorAll(
                    '.earning-card'
                )
            );


        const noResults =
            document.getElementById(
                'noEarningResults'
            );


        function filterTransactions() {

            const search =
                (
                    searchInput?.value
                    || ''
                )
                .toLowerCase()
                .trim();


            let visibleRows = 0;
            let visibleCards = 0;


            rows.forEach(
                function (row) {

                    const matches =
                        (
                            row.dataset.search
                            || ''
                        )
                        .includes(search);


                    row.classList.toggle(
                        'hidden',
                        !matches
                    );


                    if (matches) {
                        visibleRows++;
                    }

                }
            );


            cards.forEach(
                function (card) {

                    const matches =
                        (
                            card.dataset.search
                            || ''
                        )
                        .includes(search);


                    card.classList.toggle(
                        'hidden',
                        !matches
                    );


                    if (matches) {
                        visibleCards++;
                    }

                }
            );


            const hasItems =
                rows.length > 0 ||
                cards.length > 0;


            const hasVisible =
                visibleRows > 0 ||
                visibleCards > 0;


            noResults?.classList.toggle(
                'hidden',
                !hasItems ||
                hasVisible
            );

        }


        searchInput?.addEventListener(
            'input',
            filterTransactions
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