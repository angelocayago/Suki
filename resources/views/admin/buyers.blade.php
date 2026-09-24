@extends('admin.layout')

@section('title', 'Buyer Applications')
@section('page-heading', 'Buyer Applications')

@section('content')

@php

    $totalBuyers = $buyers->count();

    $pendingBuyers = $buyers
        ->where('status', 'pending')
        ->count();

    $activeBuyers = $buyers
        ->where('status', 'active')
        ->count();

    $rejectedBuyers = $buyers
        ->where('status', 'rejected')
        ->count();

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

        <div>
            <p class="text-xs font-semibold text-emerald-800">
                Success
            </p>

            <p class="mt-0.5 text-xs leading-5 text-emerald-700">
                {{ session('success') }}
            </p>
        </div>

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

        <div>
            <p class="text-xs font-semibold text-red-800">
                Action failed
            </p>

            <p class="mt-0.5 text-xs leading-5 text-red-700">
                {{ session('error') }}
            </p>
        </div>

    </div>

@endif


{{-- =========================================================
    INTRODUCTION
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
                href="{{ route('admin.dashboard') }}"
                class="transition hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>

            <span class="text-[#52635B]">
                Buyer Applications
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Buyer Account Management
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Review buyer registrations and manage
            marketplace account approval status.
        </p>

    </div>


    @if($pendingBuyers > 0)

        <div
            class="inline-flex
                   items-center gap-2
                   self-start
                   rounded-xl
                   border border-amber-200
                   bg-amber-50
                   px-4 py-2.5
                   text-[11px]
                   font-semibold
                   text-amber-700
                   lg:self-auto"
        >

            <i
                data-lucide="user-round-clock"
                class="h-4 w-4"
            ></i>

            {{ $pendingBuyers }}
            pending
            {{ $pendingBuyers === 1 ? 'application' : 'applications' }}

        </div>

    @else

        <div
            class="inline-flex
                   items-center gap-2
                   self-start
                   rounded-xl
                   border border-emerald-200
                   bg-emerald-50
                   px-4 py-2.5
                   text-[11px]
                   font-semibold
                   text-emerald-700
                   lg:self-auto"
        >

            <i
                data-lucide="circle-check"
                class="h-4 w-4"
            ></i>

            No pending applications

        </div>

    @endif

</div>


{{-- =========================================================
    SUMMARY
========================================================= --}}

<div
    class="mb-6
           grid grid-cols-2
           gap-4
           xl:grid-cols-4"
>

    {{-- TOTAL --}}
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
                    Total Buyers
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $totalBuyers }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-[#EEF5F1]
                       text-[#173F35]"
            >

                <i
                    data-lucide="users-round"
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
            All registered buyer accounts
        </p>

    </div>


    {{-- PENDING --}}
    <div
        class="rounded-2xl
               border
               {{ $pendingBuyers > 0
                    ? 'border-amber-200'
                    : 'border-[#E1E8E4]' }}
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
                    Pending
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $pendingBuyers }}
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
            Waiting for administrator review
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
                    Active
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $activeBuyers }}
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
                    data-lucide="user-check"
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
            Approved marketplace accounts
        </p>

    </div>


    {{-- REJECTED --}}
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
                    Rejected
                </p>

                <p
                    class="mt-3
                           text-2xl
                           font-semibold
                           tracking-[-0.04em]
                           text-[#24312C]"
                >
                    {{ $rejectedBuyers }}
                </p>

            </div>


            <div
                class="flex h-10 w-10
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-red-50
                       text-red-600"
            >

                <i
                    data-lucide="user-x"
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
            Applications not approved
        </p>

    </div>

</div>


{{-- =========================================================
    BUYER MANAGEMENT PANEL
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
                Registered Buyers
            </h3>

            <p
                class="mt-0.5
                       text-[11px]
                       text-[#7C8983]"
            >
                Search, filter, approve, or reject buyer registrations.
            </p>

        </div>


        <div
            class="flex items-center gap-2
                   text-[10px]
                   text-[#849089]"
        >

            <span
                class="h-2 w-2
                       rounded-full
                       bg-[#1F6F5B]"
            ></span>

            {{ $totalBuyers }} account(s)

        </div>

    </div>


    {{-- =====================================================
        FILTERS
    ====================================================== --}}

    <div
        class="border-b border-[#EDF1EF]
               bg-[#FBFCFB]
               p-4"
    >

        <div
            class="grid gap-3
                   lg:grid-cols-[minmax(0,1fr)_220px_auto]"
        >

            {{-- SEARCH --}}
            <div class="relative">

                <i
                    data-lucide="search"
                    class="pointer-events-none
                           absolute left-3.5 top-1/2
                           h-4 w-4
                           -translate-y-1/2
                           text-[#91A099]"
                ></i>

                <input
                    type="text"
                    id="buyerSearch"
                    placeholder="Search name, email, phone, or buyer ID"
                    class="h-10 w-full
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           pl-10 pr-4
                           text-xs
                           text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

            </div>


            {{-- STATUS --}}
            <div class="relative">

                <select
                    id="buyerStatusFilter"
                    class="h-10 w-full
                           appearance-none
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-3 pr-9
                           text-xs
                           font-medium
                           text-[#52635B]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

                    <option value="all">
                        All statuses
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="active">
                        Active
                    </option>

                    <option value="rejected">
                        Rejected
                    </option>

                </select>


                <i
                    data-lucide="chevron-down"
                    class="pointer-events-none
                           absolute right-3 top-1/2
                           h-3.5 w-3.5
                           -translate-y-1/2
                           text-[#87958E]"
                ></i>

            </div>


            {{-- CLEAR --}}
            <button
                type="button"
                id="clearBuyerFilters"
                class="inline-flex h-10
                       items-center justify-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-3.5
                       text-[11px]
                       font-semibold
                       text-[#68776F]
                       transition
                       hover:bg-[#F3F7F5]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="rotate-ccw"
                    class="h-3.5 w-3.5"
                ></i>

                Clear

            </button>

        </div>


        {{-- QUICK STATUS FILTER --}}
        <div
            class="mt-4
                   overflow-x-auto"
        >

            <div
                class="flex min-w-max
                       items-center gap-2"
            >

                <button
                    type="button"
                    data-buyer-tab="all"
                    class="buyer-tab
                           rounded-full
                           bg-[#173F35]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-white
                           transition"
                >
                    All
                    <span class="ml-1">
                        {{ $totalBuyers }}
                    </span>
                </button>


                <button
                    type="button"
                    data-buyer-tab="pending"
                    class="buyer-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Pending
                    <span class="ml-1">
                        {{ $pendingBuyers }}
                    </span>
                </button>


                <button
                    type="button"
                    data-buyer-tab="active"
                    class="buyer-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Active
                    <span class="ml-1">
                        {{ $activeBuyers }}
                    </span>
                </button>


                <button
                    type="button"
                    data-buyer-tab="rejected"
                    class="buyer-tab
                           rounded-full
                           bg-[#F1F4F2]
                           px-3.5 py-2
                           text-[10px]
                           font-semibold
                           text-[#68776F]
                           transition
                           hover:bg-[#E7EEE9]"
                >
                    Rejected
                    <span class="ml-1">
                        {{ $rejectedBuyers }}
                    </span>
                </button>

            </div>

        </div>

    </div>


    {{-- =====================================================
        EMPTY STATE
    ====================================================== --}}

    @if($buyers->isEmpty())

        <div
            class="px-6 py-16
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-14 w-14
                       items-center justify-center
                       rounded-2xl
                       bg-[#EEF5F1]
                       text-[#1F6F5B]"
            >

                <i
                    data-lucide="users"
                    class="h-6 w-6"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No buyer registrations
            </h3>


            <p
                class="mx-auto mt-1
                       max-w-sm
                       text-xs
                       leading-5
                       text-[#849089]"
            >
                New buyer applications will appear here
                once users begin registering.
            </p>

        </div>


    @else


        {{-- =================================================
            DESKTOP TABLE
        ================================================== --}}

        <div class="hidden overflow-x-auto lg:block">

            <table class="w-full">

                <thead>

                    <tr
                        class="border-b
                               border-[#EDF1EF]
                               bg-[#F7F9F8]"
                    >

                        <th class="px-5 py-3 text-left">
                            Buyer
                        </th>

                        <th class="px-5 py-3 text-left">
                            Contact
                        </th>

                        <th class="px-5 py-3 text-left">
                            Registered
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

                    @foreach($buyers as $buyer)

                        @php

                            $buyerStatus =
                                strtolower(
                                    $buyer->status
                                    ?? 'pending'
                                );

                            $searchValue =
                                strtolower(
                                    ($buyer->id ?? '')
                                    . ' '
                                    . ($buyer->full_name ?? '')
                                    . ' '
                                    . ($buyer->email ?? '')
                                    . ' '
                                    . ($buyer->phone ?? '')
                                );

                        @endphp


                        <tr
                            class="buyer-row
                                   border-b border-[#F0F3F1]
                                   transition
                                   last:border-b-0
                                   hover:bg-[#FAFCFB]"
                            data-search="{{ $searchValue }}"
                            data-status="{{ $buyerStatus }}"
                        >

                            {{-- BUYER --}}
                            <td class="px-5 py-4">

                                <div class="flex items-center gap-3">

                                    <div
                                        class="flex h-11 w-11
                                               shrink-0
                                               items-center justify-center
                                               rounded-xl
                                               bg-[#DDF3EC]
                                               text-sm
                                               font-bold
                                               text-[#173F35]"
                                    >
                                        {{ strtoupper(
                                            substr(
                                                $buyer->first_name
                                                    ?? 'B',
                                                0,
                                                1
                                            )
                                        ) }}
                                    </div>


                                    <div class="min-w-0">

                                        <p
                                            class="max-w-[220px]
                                                   truncate
                                                   text-xs
                                                   font-semibold
                                                   text-[#34483F]"
                                        >
                                            {{ $buyer->full_name }}
                                        </p>

                                        <p
                                            class="mt-1
                                                   text-[9px]
                                                   font-medium
                                                   text-[#98A39D]"
                                        >
                                            Buyer #{{ $buyer->id }}
                                        </p>

                                    </div>

                                </div>

                            </td>


                            {{-- CONTACT --}}
                            <td class="px-5 py-4">

                                <p
                                    class="max-w-[240px]
                                           truncate
                                           text-[11px]
                                           font-medium
                                           text-[#52635B]"
                                >
                                    {{ $buyer->email }}
                                </p>


                                <p
                                    class="mt-1
                                           text-[10px]
                                           text-[#8A9791]"
                                >
                                    {{ $buyer->phone ?: 'No phone number' }}
                                </p>

                            </td>


                            {{-- REGISTERED --}}
                            <td class="px-5 py-4">

                                <p
                                    class="text-[11px]
                                           font-medium
                                           text-[#52635B]"
                                >
                                    {{ $buyer->created_at->format('M d, Y') }}
                                </p>

                                <p
                                    class="mt-1
                                           text-[9px]
                                           text-[#98A39D]"
                                >
                                    {{ $buyer->created_at->format('h:i A') }}
                                </p>

                            </td>


                            {{-- STATUS --}}
                            <td class="px-5 py-4">

                                @if($buyerStatus === 'pending')

                                    <span
                                        class="inline-flex
                                               items-center gap-1.5
                                               rounded-full
                                               border border-amber-200
                                               bg-amber-50
                                               px-2.5 py-1
                                               text-[9px]
                                               font-semibold
                                               text-amber-700"
                                    >

                                        <span
                                            class="h-1.5 w-1.5
                                                   rounded-full
                                                   bg-amber-500"
                                        ></span>

                                        Pending

                                    </span>


                                @elseif($buyerStatus === 'active')

                                    <span
                                        class="inline-flex
                                               items-center gap-1.5
                                               rounded-full
                                               border border-emerald-200
                                               bg-emerald-50
                                               px-2.5 py-1
                                               text-[9px]
                                               font-semibold
                                               text-emerald-700"
                                    >

                                        <span
                                            class="h-1.5 w-1.5
                                                   rounded-full
                                                   bg-emerald-500"
                                        ></span>

                                        Active

                                    </span>


                                @else

                                    <span
                                        class="inline-flex
                                               items-center gap-1.5
                                               rounded-full
                                               border border-red-200
                                               bg-red-50
                                               px-2.5 py-1
                                               text-[9px]
                                               font-semibold
                                               text-red-600"
                                    >

                                        <span
                                            class="h-1.5 w-1.5
                                                   rounded-full
                                                   bg-red-500"
                                        ></span>

                                        Rejected

                                    </span>

                                @endif

                            </td>


                            {{-- ACTIONS --}}
                            <td class="px-5 py-4">

                                @if($buyerStatus === 'pending')

                                    <div
                                        class="flex items-center
                                               justify-end gap-2"
                                    >

                                        {{-- APPROVE --}}
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'admin.buyers.approve',
                                                $buyer
                                            ) }}"
                                            class="approve-buyer-form"
                                            data-buyer-name="{{ $buyer->full_name }}"
                                        >

                                            @csrf


                                            <button
                                                type="submit"
                                                class="inline-flex h-9
                                                       items-center gap-2
                                                       rounded-xl
                                                       bg-[#173F35]
                                                       px-3.5
                                                       text-[10px]
                                                       font-semibold
                                                       text-white
                                                       transition
                                                       hover:bg-[#1F6F5B]"
                                            >

                                                <i
                                                    data-lucide="check"
                                                    class="h-3.5 w-3.5"
                                                ></i>

                                                Approve

                                            </button>

                                        </form>


                                        {{-- REJECT --}}
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'admin.buyers.reject',
                                                $buyer
                                            ) }}"
                                            class="reject-buyer-form"
                                            data-buyer-name="{{ $buyer->full_name }}"
                                        >

                                            @csrf


                                            <button
                                                type="submit"
                                                class="inline-flex h-9
                                                       items-center gap-2
                                                       rounded-xl
                                                       border border-red-200
                                                       bg-white
                                                       px-3.5
                                                       text-[10px]
                                                       font-semibold
                                                       text-red-600
                                                       transition
                                                       hover:bg-red-50"
                                            >

                                                <i
                                                    data-lucide="x"
                                                    class="h-3.5 w-3.5"
                                                ></i>

                                                Reject

                                            </button>

                                        </form>

                                    </div>


                                @elseif($buyerStatus === 'active')

                                    <div
                                        class="flex items-center
                                               justify-end gap-2
                                               text-[10px]
                                               font-medium
                                               text-emerald-700"
                                    >

                                        <i
                                            data-lucide="circle-check"
                                            class="h-3.5 w-3.5"
                                        ></i>

                                        Approved

                                    </div>


                                @else

                                    <div
                                        class="flex items-center
                                               justify-end gap-2
                                               text-[10px]
                                               font-medium
                                               text-[#87958E]"
                                    >

                                        <i
                                            data-lucide="circle-x"
                                            class="h-3.5 w-3.5"
                                        ></i>

                                        No action required

                                    </div>

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
            class="divide-y
                   divide-[#EDF1EF]
                   lg:hidden"
        >

            @foreach($buyers as $buyer)

                @php

                    $buyerStatus =
                        strtolower(
                            $buyer->status
                            ?? 'pending'
                        );

                    $searchValue =
                        strtolower(
                            ($buyer->id ?? '')
                            . ' '
                            . ($buyer->full_name ?? '')
                            . ' '
                            . ($buyer->email ?? '')
                            . ' '
                            . ($buyer->phone ?? '')
                        );

                @endphp


                <article
                    class="buyer-card
                           p-4 sm:p-5"
                    data-search="{{ $searchValue }}"
                    data-status="{{ $buyerStatus }}"
                >

                    {{-- TOP --}}
                    <div
                        class="flex items-start
                               justify-between gap-4"
                    >

                        <div
                            class="flex min-w-0
                                   items-center gap-3"
                        >

                            <div
                                class="flex h-11 w-11
                                       shrink-0
                                       items-center justify-center
                                       rounded-xl
                                       bg-[#DDF3EC]
                                       text-sm
                                       font-bold
                                       text-[#173F35]"
                            >
                                {{ strtoupper(
                                    substr(
                                        $buyer->first_name
                                            ?? 'B',
                                        0,
                                        1
                                    )
                                ) }}
                            </div>


                            <div class="min-w-0">

                                <p
                                    class="truncate
                                           text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ $buyer->full_name }}
                                </p>

                                <p
                                    class="mt-1
                                           text-[9px]
                                           text-[#8A9791]"
                                >
                                    Buyer #{{ $buyer->id }}
                                </p>

                            </div>

                        </div>


                        @if($buyerStatus === 'pending')

                            <span
                                class="shrink-0
                                       rounded-full
                                       border border-amber-200
                                       bg-amber-50
                                       px-2.5 py-1
                                       text-[9px]
                                       font-semibold
                                       text-amber-700"
                            >
                                Pending
                            </span>


                        @elseif($buyerStatus === 'active')

                            <span
                                class="shrink-0
                                       rounded-full
                                       border border-emerald-200
                                       bg-emerald-50
                                       px-2.5 py-1
                                       text-[9px]
                                       font-semibold
                                       text-emerald-700"
                            >
                                Active
                            </span>


                        @else

                            <span
                                class="shrink-0
                                       rounded-full
                                       border border-red-200
                                       bg-red-50
                                       px-2.5 py-1
                                       text-[9px]
                                       font-semibold
                                       text-red-600"
                            >
                                Rejected
                            </span>

                        @endif

                    </div>


                    {{-- CONTACT --}}
                    <div
                        class="mt-4
                               rounded-xl
                               bg-[#F7F9F8]
                               p-3"
                    >

                        <div
                            class="flex items-start gap-3"
                        >

                            <i
                                data-lucide="mail"
                                class="mt-0.5
                                       h-4 w-4
                                       shrink-0
                                       text-[#1F6F5B]"
                            ></i>

                            <div class="min-w-0">

                                <p
                                    class="break-all
                                           text-[10px]
                                           font-medium
                                           text-[#52635B]"
                                >
                                    {{ $buyer->email }}
                                </p>

                                <p
                                    class="mt-1
                                           text-[10px]
                                           text-[#8A9791]"
                                >
                                    {{ $buyer->phone ?: 'No phone number' }}
                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- REGISTRATION --}}
                    <div
                        class="mt-3
                               flex items-center
                               justify-between
                               rounded-xl
                               bg-[#F7F9F8]
                               px-3 py-2.5"
                    >

                        <span
                            class="text-[9px]
                                   font-medium
                                   text-[#8A9791]"
                        >
                            Registered
                        </span>

                        <span
                            class="text-[10px]
                                   font-semibold
                                   text-[#52635B]"
                        >
                            {{ $buyer->created_at->format('M d, Y') }}
                        </span>

                    </div>


                    {{-- ACTIONS --}}
                    @if($buyerStatus === 'pending')

                        <div
                            class="mt-4
                                   grid grid-cols-2
                                   gap-2"
                        >

                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.buyers.approve',
                                    $buyer
                                ) }}"
                                class="approve-buyer-form"
                                data-buyer-name="{{ $buyer->full_name }}"
                            >

                                @csrf


                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full
                                           items-center
                                           justify-center gap-2
                                           rounded-xl
                                           bg-[#173F35]
                                           text-[10px]
                                           font-semibold
                                           text-white
                                           transition
                                           hover:bg-[#1F6F5B]"
                                >

                                    <i
                                        data-lucide="check"
                                        class="h-4 w-4"
                                    ></i>

                                    Approve

                                </button>

                            </form>


                            <form
                                method="POST"
                                action="{{ route(
                                    'admin.buyers.reject',
                                    $buyer
                                ) }}"
                                class="reject-buyer-form"
                                data-buyer-name="{{ $buyer->full_name }}"
                            >

                                @csrf


                                <button
                                    type="submit"
                                    class="inline-flex h-10
                                           w-full
                                           items-center
                                           justify-center gap-2
                                           rounded-xl
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

                                    Reject

                                </button>

                            </form>

                        </div>


                    @elseif($buyerStatus === 'active')

                        <div
                            class="mt-4
                                   flex h-10
                                   items-center
                                   justify-center gap-2
                                   rounded-xl
                                   bg-emerald-50
                                   text-[10px]
                                   font-semibold
                                   text-emerald-700"
                        >

                            <i
                                data-lucide="circle-check"
                                class="h-4 w-4"
                            ></i>

                            Buyer account approved

                        </div>


                    @else

                        <div
                            class="mt-4
                                   flex h-10
                                   items-center
                                   justify-center gap-2
                                   rounded-xl
                                   bg-[#F3F5F4]
                                   text-[10px]
                                   font-semibold
                                   text-[#68776F]"
                        >

                            <i
                                data-lucide="circle-x"
                                class="h-4 w-4"
                            ></i>

                            Application rejected

                        </div>

                    @endif

                </article>

            @endforeach

        </div>


        {{-- =================================================
            NO FILTER RESULTS
        ================================================== --}}

        <div
            id="noBuyerResults"
            class="hidden
                   px-6 py-14
                   text-center"
        >

            <div
                class="mx-auto
                       flex h-12 w-12
                       items-center justify-center
                       rounded-2xl
                       bg-[#F1F4F2]
                       text-[#87958E]"
            >

                <i
                    data-lucide="search-x"
                    class="h-5 w-5"
                ></i>

            </div>


            <h3
                class="mt-4
                       text-sm
                       font-semibold
                       text-[#34483F]"
            >
                No matching buyers
            </h3>


            <p
                class="mt-1
                       text-xs
                       text-[#849089]"
            >
                Try another search or change the selected status.
            </p>


            <button
                type="button"
                id="clearBuyerResults"
                class="mt-4
                       inline-flex h-9
                       items-center gap-2
                       rounded-xl
                       border border-[#DDE6E1]
                       bg-white
                       px-4
                       text-[11px]
                       font-semibold
                       text-[#52635B]
                       transition
                       hover:bg-[#F5F8F6]
                       hover:text-[#173F35]"
            >

                <i
                    data-lucide="rotate-ccw"
                    class="h-3.5 w-3.5"
                ></i>

                Clear filters

            </button>

        </div>

    @endif

</section>


{{-- =========================================================
    CONFIRMATION MODAL
========================================================= --}}

<div
    id="buyerActionModal"
    class="fixed inset-0 z-[100]
           hidden
           items-center justify-center
           bg-[#102C25]/45
           px-4
           backdrop-blur-[2px]"
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
                id="buyerModalIcon"
                class="flex h-11 w-11
                       items-center justify-center
                       rounded-xl"
            ></div>


            <h3
                id="buyerModalTitle"
                class="mt-4
                       text-lg
                       font-semibold
                       tracking-[-0.03em]
                       text-[#24312C]"
            ></h3>


            <p
                id="buyerModalMessage"
                class="mt-2
                       text-xs
                       leading-6
                       text-[#728078]"
            ></p>


            <div
                class="mt-6
                       flex flex-col-reverse
                       gap-2
                       sm:flex-row
                       sm:justify-end"
            >

                <button
                    type="button"
                    id="cancelBuyerAction"
                    class="inline-flex h-10
                           items-center justify-center
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           px-4
                           text-xs
                           font-semibold
                           text-[#52635B]
                           transition
                           hover:bg-[#F5F8F6]"
                >
                    Cancel
                </button>


                <button
                    type="button"
                    id="confirmBuyerAction"
                    class="inline-flex h-10
                           items-center justify-center gap-2
                           rounded-xl
                           px-4
                           text-xs
                           font-semibold
                           text-white
                           transition"
                >
                    Confirm
                </button>

            </div>

        </div>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /* =====================================================
           SEARCH + FILTER
        ====================================================== */

        const searchInput =
            document.getElementById(
                'buyerSearch'
            );

        const statusFilter =
            document.getElementById(
                'buyerStatusFilter'
            );

        const clearButton =
            document.getElementById(
                'clearBuyerFilters'
            );

        const clearResultsButton =
            document.getElementById(
                'clearBuyerResults'
            );

        const tabs =
            Array.from(
                document.querySelectorAll(
                    '.buyer-tab'
                )
            );

        const desktopRows =
            Array.from(
                document.querySelectorAll(
                    '.buyer-row'
                )
            );

        const mobileCards =
            Array.from(
                document.querySelectorAll(
                    '.buyer-card'
                )
            );

        const noResults =
            document.getElementById(
                'noBuyerResults'
            );

        let activeStatus =
            'all';


        function matchesBuyer(
            element,
            search,
            status
        ) {

            const searchable =
                (
                    element.dataset.search || ''
                ).toLowerCase();

            const buyerStatus =
                element.dataset.status || '';


            const matchesSearch =
                search === '' ||
                searchable.includes(search);

            const matchesStatus =
                status === 'all' ||
                buyerStatus === status;


            return (
                matchesSearch &&
                matchesStatus
            );

        }


        function updateTabs() {

            tabs.forEach(
                function (tab) {

                    const isActive =
                        tab.dataset.buyerTab ===
                        activeStatus;


                    tab.classList.toggle(
                        'bg-[#173F35]',
                        isActive
                    );

                    tab.classList.toggle(
                        'text-white',
                        isActive
                    );

                    tab.classList.toggle(
                        'bg-[#F1F4F2]',
                        !isActive
                    );

                    tab.classList.toggle(
                        'text-[#68776F]',
                        !isActive
                    );

                }
            );

        }


        function filterBuyers() {

            const search =
                (
                    searchInput?.value || ''
                )
                .toLowerCase()
                .trim();


            const status =
                activeStatus !== 'all'
                    ? activeStatus
                    : (
                        statusFilter?.value
                        || 'all'
                    );


            let desktopVisible = 0;
            let mobileVisible = 0;


            desktopRows.forEach(
                function (row) {

                    const visible =
                        matchesBuyer(
                            row,
                            search,
                            status
                        );

                    row.classList.toggle(
                        'hidden',
                        !visible
                    );

                    if (visible) {
                        desktopVisible++;
                    }

                }
            );


            mobileCards.forEach(
                function (card) {

                    const visible =
                        matchesBuyer(
                            card,
                            search,
                            status
                        );

                    card.classList.toggle(
                        'hidden',
                        !visible
                    );

                    if (visible) {
                        mobileVisible++;
                    }

                }
            );


            const hasBuyers =
                desktopRows.length > 0 ||
                mobileCards.length > 0;


            const hasVisible =
                desktopVisible > 0 ||
                mobileVisible > 0;


            noResults?.classList.toggle(
                'hidden',
                !hasBuyers ||
                hasVisible
            );

        }


        function clearFilters() {

            activeStatus = 'all';

            if (searchInput) {
                searchInput.value = '';
            }

            if (statusFilter) {
                statusFilter.value = 'all';
            }

            updateTabs();
            filterBuyers();

        }


        searchInput?.addEventListener(
            'input',
            filterBuyers
        );


        statusFilter?.addEventListener(
            'change',
            function () {

                activeStatus = 'all';

                updateTabs();
                filterBuyers();

            }
        );


        tabs.forEach(
            function (tab) {

                tab.addEventListener(
                    'click',
                    function () {

                        activeStatus =
                            this.dataset.buyerTab
                            || 'all';

                        if (statusFilter) {
                            statusFilter.value = 'all';
                        }

                        updateTabs();
                        filterBuyers();

                    }
                );

            }
        );


        clearButton?.addEventListener(
            'click',
            clearFilters
        );


        clearResultsButton?.addEventListener(
            'click',
            clearFilters
        );


        /* =====================================================
           APPROVE / REJECT CONFIRMATION
        ====================================================== */

        const modal =
            document.getElementById(
                'buyerActionModal'
            );

        const modalIcon =
            document.getElementById(
                'buyerModalIcon'
            );

        const modalTitle =
            document.getElementById(
                'buyerModalTitle'
            );

        const modalMessage =
            document.getElementById(
                'buyerModalMessage'
            );

        const cancelButton =
            document.getElementById(
                'cancelBuyerAction'
            );

        const confirmButton =
            document.getElementById(
                'confirmBuyerAction'
            );

        let pendingForm = null;


        function openModal(
            form,
            type,
            buyerName
        ) {

            pendingForm = form;


            modal?.classList.remove(
                'hidden'
            );

            modal?.classList.add(
                'flex'
            );

            document.body.classList.add(
                'overflow-hidden'
            );


            if (type === 'approve') {

                modalIcon.className =
                    'flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700';

                modalIcon.innerHTML =
                    '<i data-lucide="user-check" class="h-5 w-5"></i>';

                modalTitle.textContent =
                    'Approve buyer account?';

                modalMessage.textContent =
                    `${buyerName} will be approved and allowed to use the buyer marketplace account.`;

                confirmButton.className =
                    'inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#173F35] px-4 text-xs font-semibold text-white transition hover:bg-[#1F6F5B]';

                confirmButton.innerHTML =
                    '<i data-lucide="check" class="h-4 w-4"></i> Approve Buyer';

            } else {

                modalIcon.className =
                    'flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-600';

                modalIcon.innerHTML =
                    '<i data-lucide="user-x" class="h-5 w-5"></i>';

                modalTitle.textContent =
                    'Reject buyer application?';

                modalMessage.textContent =
                    `${buyerName}'s buyer registration will be marked as rejected.`;

                confirmButton.className =
                    'inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 text-xs font-semibold text-white transition hover:bg-red-700';

                confirmButton.innerHTML =
                    '<i data-lucide="x" class="h-4 w-4"></i> Reject Buyer';

            }


            if (
                typeof lucide !== 'undefined'
            ) {
                lucide.createIcons();
            }

        }


        function closeModal() {

            pendingForm = null;

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


        document
            .querySelectorAll(
                '.approve-buyer-form'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function (event) {

                            event.preventDefault();

                            openModal(
                                form,
                                'approve',
                                form.dataset.buyerName
                                || 'This buyer'
                            );

                        }
                    );

                }
            );


        document
            .querySelectorAll(
                '.reject-buyer-form'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function (event) {

                            event.preventDefault();

                            openModal(
                                form,
                                'reject',
                                form.dataset.buyerName
                                || 'This buyer'
                            );

                        }
                    );

                }
            );


        cancelButton?.addEventListener(
            'click',
            closeModal
        );


        modal?.addEventListener(
            'click',
            function (event) {

                if (event.target === modal) {
                    closeModal();
                }

            }
        );


        confirmButton?.addEventListener(
            'click',
            function () {

                if (!pendingForm) {
                    return;
                }


                confirmButton.disabled = true;

                pendingForm.submit();

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
                    closeModal();
                }

            }
        );


        updateTabs();
        filterBuyers();


        if (
            typeof lucide !== 'undefined'
        ) {
            lucide.createIcons();
        }

    }
);

</script>

@endpush

@endsection