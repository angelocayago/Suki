@extends('layouts.logistics')

@section('content')

@php

    /*
    |--------------------------------------------------------------------------
    | GET RIDERS FROM ROUTE
    |--------------------------------------------------------------------------
    */

    $sourceRiders = $riders ?? $applications ?? [];


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE RIDER DATA
    |--------------------------------------------------------------------------
    */

    $riders = [];


    /*
    |--------------------------------------------------------------------------
    | CHECK OLD / BROKEN SESSION FORMAT
    |--------------------------------------------------------------------------
    */

    if (isset($sourceRiders['first_name'])) {

        $firstRider = $sourceRiders;


        foreach ($firstRider as $key => $value) {

            if (is_int($key)) {

                unset($firstRider[$key]);

            }

        }


        $riders[] = $firstRider;


        foreach ($sourceRiders as $key => $value) {

            if (is_int($key) && is_array($value)) {

                $riders[] = $value;

            }

        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | NORMAL RIDER ARRAY
        |--------------------------------------------------------------------------
        */

        foreach ($sourceRiders as $rider) {

            if (is_array($rider)) {

                $riders[] = $rider;

            }

        }

    }

@endphp


<div class="min-h-screen bg-[#F8FAF8]">


    <!-- =====================================================
    | HEADER
    ====================================================== -->
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">

        <div class="mx-auto flex max-w-[1440px] items-center justify-between px-5 py-4 sm:px-8">


            <!-- LOGO -->
            <a
                href="{{ route('logistics.dashboard') }}"
                class="flex items-center gap-3"
            >

                <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">

                    <img
                        src="{{ asset('images/suki-logistcs.jpg') }}"
                        alt="SUKI SHOP Logistics"
                        class="h-full w-full object-contain"
                    >

                </div>


                <div>

                    <div class="flex items-center gap-2">

                        <p class="text-base font-bold tracking-tight text-gray-900">
                            SUKI SHOP Logistics
                        </p>

                        <span class="hidden rounded-full bg-[#E6F4EE] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#1F6F5B] sm:inline-flex">
                            Portal
                        </span>

                    </div>


                    <p class="text-xs text-gray-500">
                        Sorting Center Management
                    </p>

                </div>

            </a>


            <!-- LOGISTICS USER -->
            <div class="flex items-center gap-3">


                <div class="hidden text-right sm:block">

                    <p class="text-sm font-semibold text-gray-900">
                        Logistics Center
                    </p>

                    <div class="mt-0.5 flex items-center justify-end gap-1.5">

                        <span class="h-2 w-2 rounded-full bg-[#1F6F5B]"></span>

                        <p class="text-xs text-gray-500">
                            Management Portal
                        </p>

                    </div>

                </div>


                <div class="flex h-11 w-11 items-center justify-center rounded-full border border-[#CFE9DD] bg-[#E6F4EE] text-[#1F6F5B]">

                    <i
                        data-lucide="building-2"
                        class="h-5 w-5"
                    ></i>

                </div>

            </div>

        </div>

    </header>



    <!-- DASHBOARD LAYOUT -->
    <div class="mx-auto flex max-w-[1440px]">


        <!-- SIDEBAR -->
        <aside class="sticky top-[76px] hidden h-[calc(100vh-76px)] w-[270px] shrink-0 border-r border-gray-200 bg-white lg:flex lg:flex-col">


            <!-- NAVIGATION -->
            <div class="flex-1 overflow-y-auto px-4 py-6">


                <p class="mb-3 px-3 text-[11px] font-bold uppercase tracking-[0.12em] text-gray-400">
                    Main Menu
                </p>


                <!-- DASHBOARD -->
                <a
                    href="{{ route('logistics.dashboard') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="layout-dashboard"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Dashboard</span>

                </a>



                <!-- RIDER MANAGEMENT -->
                <a
                    href="{{ route('logistics.riders') }}"
                    class="group relative mb-1 flex items-center gap-3 rounded-xl bg-[#E6F4EE] px-3 py-3 text-sm font-semibold text-[#1F6F5B]"
                >

                    <span class="absolute left-0 h-8 w-1 rounded-r-full bg-[#1F6F5B]"></span>


                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-[#1F6F5B] shadow-sm">

                        <i
                            data-lucide="bike"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>


                    <span>
                        Rider Management
                    </span>


                    @php

                        $pendingCount = collect($riders)
                            ->where('status', 'pending')
                            ->count();

                    @endphp


                    @if($pendingCount > 0)

                        <span class="ml-auto flex min-w-[22px] items-center justify-center rounded-full bg-[#1F6F5B] px-2 py-1 text-[10px] font-bold text-white">

                            {{ $pendingCount }}

                        </span>

                    @endif

                </a>



                <p class="mb-3 mt-7 px-3 text-[11px] font-bold uppercase tracking-[0.12em] text-gray-400">
                    Operations
                </p>


                <!-- INCOMING PARCELS -->
                <a
                    href="{{ route('logistics.parcels') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="package"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Incoming Parcels</span>

                </a>



                <!-- PARCEL SORTING -->
                <a
                    href="{{ route('logistics.sorting') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="arrow-down-up"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Parcel Sorting</span>

                </a>



                <!-- DELIVERY ASSIGNMENT -->
                <a
                    href="{{ route('logistics.assignments') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="map-pin"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Delivery Assignment</span>

                </a>



                <!-- DELIVERY MONITORING -->
                <a
                    href="{{ route('logistics.monitoring') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="truck"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Delivery Monitoring</span>

                </a>



                <p class="mb-3 mt-7 px-3 text-[11px] font-bold uppercase tracking-[0.12em] text-gray-400">
                    Management
                </p>


                <!-- REPORTS -->
                <a
                    href="{{ route('logistics.reports') }}"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="bar-chart-3"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Reports</span>

                </a>



                <!-- SETTINGS -->
                <a
                    href="#"
                    class="group mb-1 flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition group-hover:bg-[#E6F4EE] group-hover:text-[#1F6F5B]">

                        <i
                            data-lucide="settings"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>

                    <span>Account Settings</span>

                </a>

            </div>



            <!-- SIDEBAR FOOTER -->
            <div class="border-t border-gray-100 p-4">

                <div class="rounded-2xl bg-[#F4F8F6] p-4">

                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                            <i
                                data-lucide="shield-check"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <div>

                            <p class="text-xs font-semibold text-gray-800">
                                SUKI SHOP Logistics
                            </p>

                            <p class="mt-0.5 text-[11px] text-gray-500">
                                Secure Management Portal
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </aside>



        <!-- MAIN CONTENT -->
        <main class="min-w-0 flex-1 p-5 sm:p-8 lg:p-10">


            <!-- SUCCESS MESSAGE -->
            @if(session('success'))

                <div class="mb-6 flex items-start gap-3 rounded-xl border border-[#BFE3D3] bg-[#E6F4EE] px-5 py-4">

                    <div class="mt-0.5 text-[#1F6F5B]">

                        <i
                            data-lucide="circle-check"
                            class="h-5 w-5"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-semibold text-[#155244]">
                            Success
                        </p>

                        <p class="mt-1 text-sm text-[#1F6F5B]">
                            {{ session('success') }}
                        </p>

                    </div>

                </div>

            @endif


            <!-- ERROR MESSAGE -->
            @if(session('error'))

                <div class="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4">

                    <div class="mt-0.5 text-red-500">

                        <i
                            data-lucide="circle-alert"
                            class="h-5 w-5"
                        ></i>

                    </div>

                    <div>

                        <p class="text-sm font-semibold text-red-700">
                            Error
                        </p>

                        <p class="mt-1 text-sm text-red-600">
                            {{ session('error') }}
                        </p>

                    </div>

                </div>

            @endif



            <!-- PAGE HEADER -->
            <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

                <div>

                    <div class="mb-2 flex items-center gap-2 text-sm text-[#1F6F5B]">

                        <i
                            data-lucide="bike"
                            class="h-4 w-4"
                        ></i>

                        SUKI SHOP Logistics

                    </div>


                    <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                        Rider Management
                    </h1>


                    <p class="mt-2 text-sm text-gray-500">
                        Review rider applications and manage approved SUKI SHOP Riders.
                    </p>

                </div>


                <!-- TOTAL RIDERS -->
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-3 shadow-sm">

                    <p class="text-xs font-medium text-gray-500">
                        Total Riders
                    </p>

                    <p class="mt-1 text-xl font-bold text-gray-900">
                        {{ count($riders) }}
                    </p>

                </div>

            </div>



            <!-- STATS -->
            <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">


                <!-- PENDING -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Pending Applications
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">
                                {{ collect($riders)->where('status', 'pending')->count() }}
                            </p>

                        </div>


                        <div class="rounded-xl bg-yellow-50 p-3 text-yellow-600">

                            <i
                                data-lucide="clock"
                                class="h-6 w-6"
                            ></i>

                        </div>

                    </div>

                </div>



                <!-- APPROVED -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Active Riders
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">
                                {{ collect($riders)->where('status', 'approved')->count() }}
                            </p>

                        </div>


                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i
                                data-lucide="circle-check"
                                class="h-6 w-6"
                            ></i>

                        </div>

                    </div>

                </div>



                <!-- DISAPPROVED -->
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm text-gray-500">
                                Disapproved
                            </p>

                            <p class="mt-2 text-2xl font-bold text-gray-900">
                                {{ collect($riders)->where('status', 'disapproved')->count() }}
                            </p>

                        </div>


                        <div class="rounded-xl bg-red-50 p-3 text-red-500">

                            <i
                                data-lucide="user-x"
                                class="h-6 w-6"
                            ></i>

                        </div>

                    </div>

                </div>

            </div>



            <!-- RIDER APPLICATIONS -->
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">


                <!-- TABLE HEADER -->
                <div class="flex flex-col justify-between gap-4 border-b border-gray-200 p-5 sm:flex-row sm:items-center">

                    <div>

                        <h2 class="font-semibold text-gray-900">
                            Rider Applications
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Review and manage rider registrations.
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
                            id="riderSearch"
                            placeholder="Search rider..."
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
                                    Rider
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Contact
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Vehicle
                                </th>

                                <th class="px-6 py-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Application
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


                            @if(count($riders) > 0)

                                @foreach($riders as $index => $rider)

                                    <tr
                                        class="rider-row transition hover:bg-gray-50"
                                        data-search="{{ strtolower(
                                            ($rider['first_name'] ?? '') . ' ' .
                                            ($rider['last_name'] ?? '') . ' ' .
                                            ($rider['email'] ?? '') . ' ' .
                                            ($rider['phone'] ?? '') . ' ' .
                                            ($rider['vehicle_type'] ?? '')
                                        ) }}"
                                    >


                                        <!-- RIDER -->
                                        <td class="px-6 py-5">

                                            <div class="flex items-center gap-3">

                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#E6F4EE] font-semibold text-[#1F6F5B]">

                                                    {{ strtoupper(substr($rider['first_name'] ?? 'R', 0, 1)) }}

                                                </div>


                                                <div>

                                                    <p class="text-sm font-semibold text-gray-900">

                                                        {{ $rider['first_name'] ?? '' }}
                                                        {{ $rider['last_name'] ?? '' }}

                                                    </p>


                                                    <p class="text-xs text-gray-500">
                                                        Rider Applicant
                                                    </p>

                                                </div>

                                            </div>

                                        </td>



                                        <!-- CONTACT -->
                                        <td class="px-6 py-5">

                                            <p class="text-sm text-gray-700">
                                                {{ $rider['email'] ?? '—' }}
                                            </p>


                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ $rider['phone'] ?? '—' }}
                                            </p>

                                        </td>



                                        <!-- VEHICLE -->
                                        <td class="px-6 py-5">

                                            <div class="flex items-center gap-2">

                                                <i
                                                    data-lucide="bike"
                                                    class="h-4 w-4 text-[#1F6F5B]"
                                                ></i>


                                                <span class="text-sm text-gray-700">
                                                    {{ $rider['vehicle_type'] ?? '—' }}
                                                </span>

                                            </div>

                                        </td>



                                        <!-- APPLICATION DATE -->
                                        <td class="px-6 py-5">

                                            <p class="text-sm text-gray-700">
                                                {{ $rider['created_at'] ?? '—' }}
                                            </p>

                                        </td>



                                        <!-- STATUS -->
                                        <td class="px-6 py-5">

                                            @if(($rider['status'] ?? '') === 'pending')

                                                <span class="inline-flex rounded-full bg-yellow-50 px-3 py-1 text-xs font-semibold text-yellow-700">
                                                    Pending
                                                </span>

                                            @elseif(($rider['status'] ?? '') === 'approved')

                                                <span class="inline-flex rounded-full bg-[#E6F4EE] px-3 py-1 text-xs font-semibold text-[#1F6F5B]">
                                                    Approved
                                                </span>

                                            @elseif(($rider['status'] ?? '') === 'disapproved')

                                                <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-600">
                                                    Disapproved
                                                </span>

                                            @else

                                                <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                                                    {{ ucfirst($rider['status'] ?? 'Unknown') }}
                                                </span>

                                            @endif

                                        </td>



                                        <!-- ACTION -->
                                        <td class="px-6 py-5 text-right">

                                            @if(($rider['status'] ?? '') === 'pending')

                                                <a
                                                    href="{{ route('logistics.riders.review', $index) }}"
                                                    class="inline-flex rounded-lg bg-[#1F6F5B] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#155244]"
                                                >
                                                    Review
                                                </a>

                                            @else

                                                <a
                                                    href="{{ route('logistics.riders.review', $index) }}"
                                                    class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                                >
                                                    View
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
                                                    data-lucide="bike"
                                                    class="h-8 w-8"
                                                ></i>

                                            </div>


                                            <h3 class="font-semibold text-gray-900">
                                                No rider applications yet
                                            </h3>


                                            <p class="mt-2 text-sm leading-6 text-gray-500">

                                                Rider applications submitted through the
                                                SUKI SHOP Rider registration page will appear here.

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


<!-- RIDER SEARCH FUNCTIONALITY -->
<script>

    document.addEventListener('DOMContentLoaded', function () {

        const searchInput = document.getElementById('riderSearch');

        const riderRows = document.querySelectorAll('.rider-row');


        if (searchInput) {

            searchInput.addEventListener('input', function () {

                const searchValue = this.value.toLowerCase().trim();


                riderRows.forEach(function (row) {

                    const riderData =
                        row.getAttribute('data-search').toLowerCase();


                    if (riderData.includes(searchValue)) {

                        row.style.display = '';

                    } else {

                        row.style.display = 'none';

                    }

                });

            });

        }

    });

</script>

@endsection