@extends('admin.layout')


@section('title', 'Dashboard')

@section('page-heading', 'Dashboard')


@section('content')


@php

    $maxTrend = max(
        1,
        collect($orderTrend)->max('count')
    );


    $chartWidth = 1000;

    $chartTop = 25;

    $chartBottom = 185;

    $chartLeft = 45;

    $chartRight = 965;

    $chartHeight =
        $chartBottom - $chartTop;


    $pointCount =
        count($orderTrend);


    $chartPoints = [];


    foreach ($orderTrend as $index => $point) {

        $x = $pointCount > 1

            ? $chartLeft
                + (
                    $index
                    * (
                        ($chartRight - $chartLeft)
                        /
                        ($pointCount - 1)
                    )
                )

            : ($chartLeft + $chartRight) / 2;


        $y =
            $chartBottom
            -
            (
                ($point['count'] / $maxTrend)
                *
                $chartHeight
            );


        $chartPoints[] = [
            'x' => $x,
            'y' => $y,
            'count' => $point['count'],
            'label' => $point['label'],
            'date' => $point['date'],
        ];
    }


    $polylinePoints =
        collect($chartPoints)
            ->map(
                fn ($point) =>
                    $point['x']
                    . ','
                    . $point['y']
            )
            ->implode(' ');


    $completionRate =
        $totalOrders > 0

            ? round(
                ($completedOrders / $totalOrders)
                * 100
            )

            : 0;

@endphp



{{-- ===================================================== --}}
{{-- HEADER --}}
{{-- ===================================================== --}}

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">

    <div>

        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-[#1F6F5B]">
            Overview
        </p>

        <h1 class="mt-2 text-2xl font-semibold tracking-[-0.025em] text-[#202421] sm:text-[28px]">
            Marketplace performance
        </h1>

        <p class="mt-2 text-sm text-[#707872]">
            Orders, applications and fulfillment activity across SUKI SHOP.
        </p>

    </div>


    <div class="flex items-center gap-2">

        <div class="rounded-lg border border-[#E1E5E2] bg-white px-3 py-2 text-xs font-medium text-[#606863]">

            {{ now()->format('M d, Y') }}

        </div>


        <a
            href="{{ route('admin.buyers') }}"
            class="inline-flex items-center rounded-lg bg-[#173F35] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#205548]"
        >
            Review applications

            @if($pendingBuyers > 0)

                <span class="ml-2 rounded-full bg-white/15 px-2 py-0.5 text-[10px]">
                    {{ $pendingBuyers }}
                </span>

            @endif

        </a>

    </div>

</div>



{{-- ===================================================== --}}
{{-- ATTENTION --}}
{{-- ===================================================== --}}

@if($pendingBuyers > 0 || $placedOrders > 0)

    <section class="mb-6 overflow-hidden rounded-xl border border-[#DCE3DF] bg-white">

        <div class="flex items-center justify-between border-b border-[#EEF0EF] px-5 py-4">

            <div>

                <h2 class="text-sm font-semibold text-[#2B312D]">
                    Needs attention
                </h2>

                <p class="mt-0.5 text-xs text-[#8A918C]">
                    Tasks that may require action
                </p>

            </div>


            <span class="rounded-md bg-[#FFF6DC] px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#8A6410]">
                Action
            </span>

        </div>


        <div class="divide-y divide-[#EEF0EF]">

            @if($pendingBuyers > 0)

                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#EFF6F2] text-[#1F6F5B]">

                        <svg
                            class="h-[18px] w-[18px]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/>
                            <path d="m17 11 2 2 4-4"/>
                        </svg>

                    </div>


                    <div class="min-w-0 flex-1">

                        <p class="text-sm font-medium text-[#333935]">
                            {{ $pendingBuyers }}
                            buyer
                            {{ $pendingBuyers === 1 ? 'application needs' : 'applications need' }}
                            review
                        </p>

                        <p class="mt-0.5 text-xs text-[#8A918C]">
                            Review registrations before they can access buyer checkout.
                        </p>

                    </div>


                    <a
                        href="{{ route('admin.buyers') }}"
                        class="text-xs font-semibold text-[#1F6F5B] hover:underline"
                    >
                        Review now
                    </a>

                </div>

            @endif


            @if($placedOrders > 0)

                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#FFF7E7] text-[#9A701A]">

                        <svg
                            class="h-[18px] w-[18px]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path d="M6 2h12l2 5H4l2-5Z"/>
                            <path d="M5 7h14l-1 14H6L5 7Z"/>
                        </svg>

                    </div>


                    <div class="min-w-0 flex-1">

                        <p class="text-sm font-medium text-[#333935]">
                            {{ $placedOrders }}
                            {{ $placedOrders === 1 ? 'order is' : 'orders are' }}
                            awaiting processing
                        </p>

                        <p class="mt-0.5 text-xs text-[#8A918C]">
                            Newly placed orders are waiting for the next fulfillment step.
                        </p>

                    </div>

                </div>

            @endif

        </div>

    </section>

@endif



{{-- ===================================================== --}}
{{-- METRICS --}}
{{-- ===================================================== --}}

<section class="overflow-hidden rounded-xl border border-[#E1E5E2] bg-white">

    <div class="grid sm:grid-cols-2 xl:grid-cols-4">


        {{-- BUYERS --}}
        <div class="border-b border-[#EDF0EE] p-5 sm:border-r xl:border-b-0">

            <p class="text-xs font-medium text-[#858D87]">
                Total buyers
            </p>

            <div class="mt-3 flex items-end justify-between gap-4">

                <p class="text-[28px] font-semibold tracking-tight text-[#202421]">
                    {{ number_format($totalBuyers) }}
                </p>

                <span class="mb-1 text-[11px] font-medium text-[#1F6F5B]">
                    {{ $activeBuyers }} active
                </span>

            </div>

        </div>


        {{-- ORDERS --}}
        <div class="border-b border-[#EDF0EE] p-5 xl:border-b-0 xl:border-r">

            <p class="text-xs font-medium text-[#858D87]">
                Total orders
            </p>

            <div class="mt-3 flex items-end justify-between gap-4">

                <p class="text-[28px] font-semibold tracking-tight text-[#202421]">
                    {{ number_format($totalOrders) }}
                </p>

                <span class="mb-1 text-[11px] font-medium text-[#6B736D]">
                    {{ $processingOrders }} processing
                </span>

            </div>

        </div>


        {{-- ACTIVE DELIVERY --}}
        <div class="border-b border-[#EDF0EE] p-5 sm:border-r sm:border-b-0">

            <p class="text-xs font-medium text-[#858D87]">
                Active deliveries
            </p>

            <div class="mt-3 flex items-end justify-between gap-4">

                <p class="text-[28px] font-semibold tracking-tight text-[#202421]">
                    {{ number_format($deliveryOrders) }}
                </p>

                <span class="mb-1 text-[11px] font-medium text-[#6B736D]">
                    SUKI Logistics
                </span>

            </div>

        </div>


        {{-- COMPLETED VALUE --}}
        <div class="p-5">

            <p class="text-xs font-medium text-[#858D87]">
                Completed order value
            </p>

            <div class="mt-3 flex items-end justify-between gap-4">

                <p class="text-[28px] font-semibold tracking-tight text-[#202421]">
                    ₱{{ number_format($completedOrderValue, 2) }}
                </p>

                <span class="mb-1 text-[11px] font-medium text-[#1F6F5B]">
                    {{ $completionRate }}% complete
                </span>

            </div>

        </div>

    </div>

</section>



{{-- ===================================================== --}}
{{-- ANALYTICS --}}
{{-- ===================================================== --}}

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(290px,0.7fr)]">


    {{-- ORDER TREND --}}
    <section class="overflow-hidden rounded-xl border border-[#E1E5E2] bg-white">

        <div class="flex items-center justify-between border-b border-[#EEF0EF] px-5 py-4 sm:px-6">

            <div>

                <h2 class="text-sm font-semibold text-[#2B312D]">
                    Order activity
                </h2>

                <p class="mt-1 text-xs text-[#8A918C]">
                    Orders created over the last 7 days
                </p>

            </div>


            <div class="flex items-center gap-2">

                <span class="h-2 w-2 rounded-full bg-[#1F6F5B]"></span>

                <span class="text-[11px] text-[#858D87]">
                    Orders
                </span>

            </div>

        </div>


        <div class="px-3 py-5 sm:px-5">

            <div class="w-full overflow-hidden">

                <svg
                    viewBox="0 0 1000 235"
                    class="h-[250px] w-full"
                    preserveAspectRatio="none"
                    role="img"
                    aria-label="Orders during the last 7 days"
                >

                    {{-- GRID --}}
                    <line
                        x1="45"
                        y1="25"
                        x2="965"
                        y2="25"
                        stroke="#EEF1EF"
                        stroke-width="1"
                    />

                    <line
                        x1="45"
                        y1="105"
                        x2="965"
                        y2="105"
                        stroke="#EEF1EF"
                        stroke-width="1"
                    />

                    <line
                        x1="45"
                        y1="185"
                        x2="965"
                        y2="185"
                        stroke="#DDE2DF"
                        stroke-width="1"
                    />


                    {{-- Y LABELS --}}
                    <text
                        x="10"
                        y="30"
                        fill="#9AA19C"
                        font-size="11"
                    >
                        {{ $maxTrend }}
                    </text>

                    <text
                        x="10"
                        y="110"
                        fill="#9AA19C"
                        font-size="11"
                    >
                        {{ round($maxTrend / 2) }}
                    </text>

                    <text
                        x="18"
                        y="190"
                        fill="#9AA19C"
                        font-size="11"
                    >
                        0
                    </text>


                    {{-- LINE --}}
                    @if(count($chartPoints) > 1)

                        <polyline
                            points="{{ $polylinePoints }}"
                            fill="none"
                            stroke="#1F6F5B"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            vector-effect="non-scaling-stroke"
                        />

                    @endif


                    {{-- POINTS --}}
                    @foreach($chartPoints as $point)

                        <circle
                            cx="{{ $point['x'] }}"
                            cy="{{ $point['y'] }}"
                            r="5"
                            fill="white"
                            stroke="#1F6F5B"
                            stroke-width="3"
                            vector-effect="non-scaling-stroke"
                        >
                            <title>
                                {{ $point['date'] }}:
                                {{ $point['count'] }}
                                order{{ $point['count'] !== 1 ? 's' : '' }}
                            </title>
                        </circle>


                        <text
                            x="{{ $point['x'] }}"
                            y="218"
                            text-anchor="middle"
                            fill="#838B85"
                            font-size="11"
                        >
                            {{ $point['label'] }}
                        </text>

                    @endforeach

                </svg>

            </div>

        </div>

    </section>



    {{-- ACCOUNT HEALTH --}}
    <section class="rounded-xl border border-[#E1E5E2] bg-white">

        <div class="border-b border-[#EEF0EF] px-5 py-4">

            <h2 class="text-sm font-semibold text-[#2B312D]">
                Buyer accounts
            </h2>

            <p class="mt-1 text-xs text-[#8A918C]">
                Registration overview
            </p>

        </div>


        <div class="p-5">

            {{-- ACTIVE --}}
            <div class="flex items-center justify-between py-3">

                <div class="flex items-center gap-3">

                    <span class="h-2.5 w-2.5 rounded-full bg-[#1F6F5B]"></span>

                    <span class="text-sm text-[#606863]">
                        Active
                    </span>

                </div>

                <span class="text-sm font-semibold text-[#2B312D]">
                    {{ $activeBuyers }}
                </span>

            </div>


            {{-- PENDING --}}
            <div class="flex items-center justify-between border-t border-[#F0F2F1] py-3">

                <div class="flex items-center gap-3">

                    <span class="h-2.5 w-2.5 rounded-full bg-[#E2AA38]"></span>

                    <span class="text-sm text-[#606863]">
                        Pending
                    </span>

                </div>

                <span class="text-sm font-semibold text-[#2B312D]">
                    {{ $pendingBuyers }}
                </span>

            </div>


            {{-- REJECTED --}}
            <div class="flex items-center justify-between border-t border-[#F0F2F1] py-3">

                <div class="flex items-center gap-3">

                    <span class="h-2.5 w-2.5 rounded-full bg-[#CF6868]"></span>

                    <span class="text-sm text-[#606863]">
                        Rejected
                    </span>

                </div>

                <span class="text-sm font-semibold text-[#2B312D]">
                    {{ $rejectedBuyers }}
                </span>

            </div>


            {{-- TOTAL --}}
            <div class="mt-4 rounded-lg bg-[#F5F7F5] p-4">

                <div class="flex items-end justify-between">

                    <div>

                        <p class="text-[11px] text-[#8A918C]">
                            Total accounts
                        </p>

                        <p class="mt-1 text-xl font-semibold text-[#29302B]">
                            {{ $totalBuyers }}
                        </p>

                    </div>


                    <a
                        href="{{ route('admin.buyers') }}"
                        class="text-xs font-semibold text-[#1F6F5B] hover:underline"
                    >
                        Manage
                    </a>

                </div>

            </div>

        </div>

    </section>

</div>



{{-- ===================================================== --}}
{{-- FULFILLMENT PIPELINE --}}
{{-- ===================================================== --}}

<section class="mt-6 overflow-hidden rounded-xl border border-[#E1E5E2] bg-white">

    <div class="flex items-center justify-between border-b border-[#EEF0EF] px-5 py-4 sm:px-6">

        <div>

            <h2 class="text-sm font-semibold text-[#2B312D]">
                Fulfillment
            </h2>

            <p class="mt-1 text-xs text-[#8A918C]">
                Current order movement
            </p>

        </div>


        <p class="hidden text-[11px] text-[#9AA19C] sm:block">
            Seller → Logistics → Rider
        </p>

    </div>


    <div class="grid sm:grid-cols-2 xl:grid-cols-4">

        <div class="border-b border-[#EEF0EF] p-5 sm:border-r xl:border-b-0">

            <div class="flex items-center justify-between">

                <span class="text-xs font-medium text-[#858D87]">
                    Placed
                </span>

                <span class="h-2 w-2 rounded-full bg-[#A1A7A3]"></span>

            </div>

            <p class="mt-3 text-2xl font-semibold text-[#252A27]">
                {{ $placedOrders }}
            </p>

            <p class="mt-2 text-[11px] leading-5 text-[#969D98]">
                Waiting for seller confirmation
            </p>

        </div>


        <div class="border-b border-[#EEF0EF] p-5 xl:border-b-0 xl:border-r">

            <div class="flex items-center justify-between">

                <span class="text-xs font-medium text-[#858D87]">
                    Processing
                </span>

                <span class="h-2 w-2 rounded-full bg-[#D5A442]"></span>

            </div>

            <p class="mt-3 text-2xl font-semibold text-[#252A27]">
                {{ $processingOrders }}
            </p>

            <p class="mt-2 text-[11px] leading-5 text-[#969D98]">
                Preparation, pickup or sorting
            </p>

        </div>


        <div class="border-b border-[#EEF0EF] p-5 sm:border-r sm:border-b-0">

            <div class="flex items-center justify-between">

                <span class="text-xs font-medium text-[#858D87]">
                    Delivery
                </span>

                <span class="h-2 w-2 rounded-full bg-[#4A8474]"></span>

            </div>

            <p class="mt-3 text-2xl font-semibold text-[#252A27]">
                {{ $deliveryOrders }}
            </p>

            <p class="mt-2 text-[11px] leading-5 text-[#969D98]">
                Assigned or out for delivery
            </p>

        </div>


        <div class="p-5">

            <div class="flex items-center justify-between">

                <span class="text-xs font-medium text-[#858D87]">
                    Completed
                </span>

                <span class="h-2 w-2 rounded-full bg-[#1F6F5B]"></span>

            </div>

            <p class="mt-3 text-2xl font-semibold text-[#1F6F5B]">
                {{ $completedOrders }}
            </p>

            <p class="mt-2 text-[11px] leading-5 text-[#969D98]">
                Successfully completed
            </p>

        </div>

    </div>

</section>



{{-- ===================================================== --}}
{{-- LOWER CONTENT --}}
{{-- ===================================================== --}}

<div class="mt-6 grid gap-6 2xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">


    {{-- RECENT ORDERS --}}
    <section class="overflow-hidden rounded-xl border border-[#E1E5E2] bg-white">

        <div class="flex items-center justify-between border-b border-[#EEF0EF] px-5 py-4 sm:px-6">

            <div>

                <h2 class="text-sm font-semibold text-[#2B312D]">
                    Recent orders
                </h2>

                <p class="mt-1 text-xs text-[#8A918C]">
                    Latest marketplace activity
                </p>

            </div>

        </div>


        @if($recentOrders->isEmpty())

            <div class="px-6 py-14 text-center">

                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-[#F1F4F2]">

                    <svg
                        class="h-5 w-5 text-[#909792]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.6"
                    >
                        <path d="M6 2h12l2 5H4l2-5Z"/>
                        <path d="M5 7h14l-1 14H6L5 7Z"/>
                    </svg>

                </div>

                <p class="mt-3 text-sm font-medium text-[#5D655F]">
                    No orders yet
                </p>

                <p class="mt-1 text-xs text-[#9AA19C]">
                    New buyer orders will appear here.
                </p>

            </div>

        @else

            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-[#FAFBFA]">

                        <tr>

                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-[#9AA19C] sm:px-6">
                                Order
                            </th>

                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-[#9AA19C]">
                                Buyer
                            </th>

                            <th class="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-[#9AA19C]">
                                Status
                            </th>

                            <th class="px-5 py-3 text-right text-[10px] font-semibold uppercase tracking-wider text-[#9AA19C] sm:px-6">
                                Total
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-[#EFF1F0]">

                        @foreach($recentOrders as $order)

                            <tr class="transition hover:bg-[#FAFBFA]">

                                <td class="whitespace-nowrap px-5 py-4 sm:px-6">

                                    <p class="text-xs font-semibold text-[#313733]">
                                        {{ $order->order_number }}
                                    </p>

                                    <p class="mt-1 text-[10px] text-[#9AA19C]">
                                        {{ $order->created_at->format('M d, H:i') }}
                                    </p>

                                </td>


                                <td class="whitespace-nowrap px-5 py-4">

                                    <p class="text-xs text-[#606863]">
                                        {{ $order->buyer?->full_name ?? 'Buyer' }}
                                    </p>

                                </td>


                                <td class="whitespace-nowrap px-5 py-4">

                                    <span class="inline-flex rounded-md bg-[#EEF5F1] px-2.5 py-1 text-[10px] font-semibold text-[#1F6F5B]">

                                        {{ ucwords(
                                            strtolower(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $order->status
                                                )
                                            )
                                        ) }}

                                    </span>

                                </td>


                                <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-semibold text-[#303632] sm:px-6">

                                    ₱{{ number_format($order->total_amount, 2) }}

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </section>



    {{-- RECENT BUYERS --}}
    <section class="overflow-hidden rounded-xl border border-[#E1E5E2] bg-white">

        <div class="flex items-center justify-between border-b border-[#EEF0EF] px-5 py-4">

            <div>

                <h2 class="text-sm font-semibold text-[#2B312D]">
                    Recent buyers
                </h2>

                <p class="mt-1 text-xs text-[#8A918C]">
                    Latest registrations
                </p>

            </div>


            <a
                href="{{ route('admin.buyers') }}"
                class="text-[11px] font-semibold text-[#1F6F5B] hover:underline"
            >
                View all
            </a>

        </div>


        <div class="divide-y divide-[#EFF1F0]">

            @forelse($recentBuyers as $buyer)

                <div class="flex items-center gap-3 px-5 py-4">

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#EDF5F1] text-xs font-semibold text-[#1F6F5B]">

                        {{ strtoupper(
                            substr(
                                $buyer->first_name ?? 'B',
                                0,
                                1
                            )
                        ) }}

                    </div>


                    <div class="min-w-0 flex-1">

                        <p class="truncate text-xs font-semibold text-[#343A36]">
                            {{ $buyer->full_name }}
                        </p>

                        <p class="mt-1 truncate text-[10px] text-[#9AA19C]">
                            {{ $buyer->email }}
                        </p>

                    </div>


                    @if($buyer->status === 'active')

                        <span class="rounded-md bg-[#EDF6F1] px-2 py-1 text-[9px] font-semibold text-[#247257]">
                            Active
                        </span>

                    @elseif($buyer->status === 'pending')

                        <span class="rounded-md bg-[#FFF5DC] px-2 py-1 text-[9px] font-semibold text-[#966B11]">
                            Pending
                        </span>

                    @else

                        <span class="rounded-md bg-red-50 px-2 py-1 text-[9px] font-semibold text-red-600">
                            Rejected
                        </span>

                    @endif

                </div>

            @empty

                <div class="px-5 py-12 text-center text-xs text-[#9AA19C]">
                    No buyer registrations.
                </div>

            @endforelse

        </div>

    </section>

</div>

@endsection