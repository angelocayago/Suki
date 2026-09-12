@extends('layouts.logistics')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <!-- BRAND -->
            <a href="{{ route('logistics.dashboard') }}" class="flex items-center gap-3">

                <img
                    src="{{ asset('images/logistics-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-10 w-auto object-contain sm:h-11"
                >

                <div class="hidden border-l border-gray-200 pl-3 sm:block">

                    <p class="text-sm font-semibold text-gray-900">
                        SUKI SHOP Logistics
                    </p>

                    <p class="text-xs text-gray-500">
                        Sorting Center Portal
                    </p>

                </div>

            </a>


            <!-- RIGHT -->
            <div class="flex items-center gap-4">

                <!-- NOTIFICATION -->
                <button
                    class="relative rounded-xl p-2 text-gray-500 transition hover:bg-gray-100 hover:text-[#1F6F5B]"
                >

                    <i data-lucide="bell" class="h-5 w-5"></i>

                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-red-500"></span>

                </button>


                <!-- PROFILE -->
                <div class="flex items-center gap-3">

                    <div class="hidden text-right sm:block">

                        <p class="text-sm font-semibold text-gray-900">
                            Logistics Center
                        </p>

                        <p class="text-xs text-gray-500">
                            SUKI SHOP Partner
                        </p>

                    </div>


                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#1F6F5B] text-sm font-semibold text-white">

                        SL

                    </div>

                </div>

            </div>

        </div>

    </header>



    <!-- MAIN -->
    <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">


        <!-- PAGE HEADER -->
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            <div>

                <div class="mb-2 inline-flex items-center gap-2 rounded-full bg-[#E6F4EE] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">

                    <i data-lucide="warehouse" class="h-4 w-4"></i>

                    Logistics Management System

                </div>


                <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">

                    Logistics Dashboard

                </h1>


                <p class="mt-2 text-sm text-gray-600">

                    Monitor parcels, manage rider applications, and oversee
                    sorting and delivery operations.

                </p>

            </div>


            <!-- QUICK ACTION -->
            <a
                href="#quick-actions"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
            >

                <i data-lucide="zap" class="h-4 w-4"></i>

                Quick Actions

            </a>

        </div>



        <!-- STATS -->
        <section class="grid grid-cols-2 gap-4 lg:grid-cols-5">


            <!-- INCOMING -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Incoming Parcels
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            0
                        </p>

                    </div>


                    <div class="rounded-xl bg-blue-50 p-2.5 text-blue-600">

                        <i data-lucide="package" class="h-5 w-5"></i>

                    </div>

                </div>


                <p class="mt-3 text-xs text-gray-500">
                    Parcels received today
                </p>

            </div>



            <!-- FOR SORTING -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            For Sorting
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            0
                        </p>

                    </div>


                    <div class="rounded-xl bg-amber-50 p-2.5 text-amber-600">

                        <i data-lucide="scan-line" class="h-5 w-5"></i>

                    </div>

                </div>


                <p class="mt-3 text-xs text-gray-500">
                    Waiting for destination sorting
                </p>

            </div>



            <!-- ASSIGN RIDER -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Assign Rider
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            0
                        </p>

                    </div>


                    <div class="rounded-xl bg-purple-50 p-2.5 text-purple-600">

                        <i data-lucide="user-round-check" class="h-5 w-5"></i>

                    </div>

                </div>


                <p class="mt-3 text-xs text-gray-500">
                    Parcels waiting for rider
                </p>

            </div>



            <!-- OUT FOR DELIVERY -->
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Out for Delivery
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            0
                        </p>

                    </div>


                    <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                        <i data-lucide="truck" class="h-5 w-5"></i>

                    </div>

                </div>


                <p class="mt-3 text-xs text-gray-500">
                    Currently being delivered
                </p>

            </div>



            <!-- PENDING RIDERS -->
            <div class="col-span-2 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-1">

                <div class="flex items-start justify-between">

                    <div>

                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Rider Applications
                        </p>

                        <p class="mt-2 text-2xl font-bold text-gray-900">
                            0
                        </p>

                    </div>


                    <div class="rounded-xl bg-red-50 p-2.5 text-red-500">

                        <i data-lucide="bike" class="h-5 w-5"></i>

                    </div>

                </div>


                <p class="mt-3 text-xs text-gray-500">
                    Pending approval
                </p>

            </div>


        </section>



        <!-- QUICK ACTIONS -->
        <section id="quick-actions" class="mt-8">

            <div class="mb-4">

                <h2 class="text-lg font-semibold text-gray-900">

                    Quick Actions

                </h2>

                <p class="mt-1 text-sm text-gray-500">

                    Manage the most important logistics operations.

                </p>

            </div>


            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">


                <!-- RIDER MANAGEMENT -->
                <a
                    href="#"
                    class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-[#1F6F5B]/30 hover:shadow-md"
                >

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                        <i data-lucide="users-round" class="h-5 w-5"></i>

                    </div>


                    <h3 class="mt-4 font-semibold text-gray-900">

                        Rider Management

                    </h3>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Review, approve, and manage SUKI SHOP Rider applications.

                    </p>


                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-[#1F6F5B]">

                        Manage Riders

                        <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1"></i>

                    </div>

                </a>



                <!-- PARCELS -->
                <a
                    href="#"
                    class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-[#1F6F5B]/30 hover:shadow-md"
                >

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">

                        <i data-lucide="package-check" class="h-5 w-5"></i>

                    </div>


                    <h3 class="mt-4 font-semibold text-gray-900">

                        Incoming Parcels

                    </h3>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Receive and verify parcels from pickup riders.

                    </p>


                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-[#1F6F5B]">

                        View Parcels

                        <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1"></i>

                    </div>

                </a>



                <!-- SORTING -->
                <a
                    href="#"
                    class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-[#1F6F5B]/30 hover:shadow-md"
                >

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600">

                        <i data-lucide="arrow-down-up" class="h-5 w-5"></i>

                    </div>


                    <h3 class="mt-4 font-semibold text-gray-900">

                        Sort Parcels

                    </h3>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Sort incoming parcels according to destination area.

                    </p>


                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-[#1F6F5B]">

                        Start Sorting

                        <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1"></i>

                    </div>

                </a>



                <!-- DELIVERY ASSIGNMENT -->
                <a
                    href="#"
                    class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-[#1F6F5B]/30 hover:shadow-md"
                >

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-600">

                        <i data-lucide="map-pin-check" class="h-5 w-5"></i>

                    </div>


                    <h3 class="mt-4 font-semibold text-gray-900">

                        Assign Deliveries

                    </h3>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Assign sorted parcels to riders based on delivery area.

                    </p>


                    <div class="mt-4 flex items-center gap-1 text-sm font-medium text-[#1F6F5B]">

                        Assign Rider

                        <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1"></i>

                    </div>

                </a>


            </div>

        </section>



        <!-- OPERATIONS OVERVIEW -->
        <section class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">


            <!-- DELIVERY FLOW -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:col-span-2">

                <div class="flex items-center justify-between">

                    <div>

                        <h2 class="font-semibold text-gray-900">

                            Parcel Operations Flow

                        </h2>

                        <p class="mt-1 text-sm text-gray-500">

                            Standard SUKI SHOP sorting and delivery workflow.

                        </p>

                    </div>


                    <div class="rounded-xl bg-[#E6F4EE] p-2 text-[#1F6F5B]">

                        <i data-lucide="workflow" class="h-5 w-5"></i>

                    </div>

                </div>



                <div class="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">


                    <div class="rounded-xl bg-gray-50 p-4">

                        <div class="text-xs font-semibold text-[#1F6F5B]">
                            STEP 1
                        </div>

                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            Receive Parcel
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Confirm parcel arrival.
                        </p>

                    </div>


                    <div class="rounded-xl bg-gray-50 p-4">

                        <div class="text-xs font-semibold text-[#1F6F5B]">
                            STEP 2
                        </div>

                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            Sort by Area
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Identify destination area.
                        </p>

                    </div>


                    <div class="rounded-xl bg-gray-50 p-4">

                        <div class="text-xs font-semibold text-[#1F6F5B]">
                            STEP 3
                        </div>

                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            Assign Rider
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Match parcel to rider area.
                        </p>

                    </div>


                    <div class="rounded-xl bg-gray-50 p-4">

                        <div class="text-xs font-semibold text-[#1F6F5B]">
                            STEP 4
                        </div>

                        <p class="mt-2 text-sm font-semibold text-gray-900">
                            Monitor Delivery
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Track parcel delivery.
                        </p>

                    </div>


                </div>

            </div>



            <!-- RIDER STATUS -->
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

                <div class="flex items-center justify-between">

                    <div>

                        <h2 class="font-semibold text-gray-900">

                            Rider Status

                        </h2>

                        <p class="mt-1 text-sm text-gray-500">

                            Current rider overview.

                        </p>

                    </div>


                    <i data-lucide="bike" class="h-5 w-5 text-[#1F6F5B]"></i>

                </div>



                <div class="mt-6 space-y-4">


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-600">

                            Active Riders

                        </span>

                        <span class="font-semibold text-gray-900">

                            0

                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-600">

                            Available

                        </span>

                        <span class="font-semibold text-gray-900">

                            0

                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-600">

                            On Delivery

                        </span>

                        <span class="font-semibold text-gray-900">

                            0

                        </span>

                    </div>


                    <div class="flex items-center justify-between">

                        <span class="text-sm text-gray-600">

                            Pending Applications

                        </span>

                        <span class="font-semibold text-red-500">

                            0

                        </span>

                    </div>


                </div>

            </div>


        </section>



        <!-- RECENT ACTIVITY -->
        <section class="mt-8 rounded-2xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-100 px-6 py-5">

                <div class="flex items-center justify-between">

                    <div>

                        <h2 class="font-semibold text-gray-900">

                            Recent Activity

                        </h2>

                        <p class="mt-1 text-sm text-gray-500">

                            Latest logistics and parcel activity.

                        </p>

                    </div>


                    <i data-lucide="history" class="h-5 w-5 text-gray-400"></i>

                </div>

            </div>



            <!-- EMPTY STATE -->
            <div class="flex flex-col items-center justify-center px-6 py-14 text-center">

                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400">

                    <i data-lucide="clipboard-list" class="h-7 w-7"></i>

                </div>


                <h3 class="mt-4 font-semibold text-gray-900">

                    No recent activity yet

                </h3>


                <p class="mt-2 max-w-sm text-sm leading-6 text-gray-500">

                    Parcel movements, rider approvals, and logistics activities
                    will appear here.

                </p>

            </div>

        </section>


    </main>

</div>

@endsection