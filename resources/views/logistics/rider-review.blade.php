@extends('layouts.logistics')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <!-- LOGO -->
            <a
                href="{{ route('logistics.dashboard') }}"
                class="flex items-center gap-3"
            >

                <img
                    src="{{ asset('images/logistics-logo.png') }}"
                    alt="SUKI SHOP Logistics"
                    class="h-10 w-auto object-contain"
                >

                <div class="hidden sm:block">

                    <p class="text-sm font-bold text-gray-900">
                        SUKI SHOP Logistics
                    </p>

                    <p class="text-xs text-gray-500">
                        Sorting Center
                    </p>

                </div>

            </a>


            <!-- LOGISTICS USER -->
            <div class="flex items-center gap-3">

                <div class="hidden text-right sm:block">

                    <p class="text-sm font-semibold text-gray-900">
                        Logistics Center
                    </p>

                    <p class="text-xs text-gray-500">
                        Management Portal
                    </p>

                </div>

                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#E6F4EE] text-[#1F6F5B]">

                    <i
                        data-lucide="building-2"
                        class="h-5 w-5"
                    ></i>

                </div>

            </div>

        </div>

    </header>



    <!-- MAIN -->
    <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 lg:py-12">


        <!-- BACK -->
        <a
            href="{{ route('logistics.riders') }}"
            class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-gray-600 transition hover:text-[#1F6F5B]"
        >

            <i
                data-lucide="arrow-left"
                class="h-4 w-4"
            ></i>

            Back to Rider Management

        </a>



        <!-- PAGE HEADER -->
        <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">

            <div>

                <div class="mb-2 flex items-center gap-2 text-sm text-[#1F6F5B]">

                    <i
                        data-lucide="clipboard-check"
                        class="h-4 w-4"
                    ></i>

                    Rider Application Review

                </div>


                <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">

                    {{ $rider['first_name'] ?? '' }}
                    {{ $rider['last_name'] ?? '' }}

                </h1>


                <p class="mt-2 text-sm text-gray-500">

                    Review the rider's information and submitted verification documents.

                </p>

            </div>


            <!-- STATUS -->
            <div>

                @if(($rider['status'] ?? '') === 'pending')

                    <span class="inline-flex rounded-full bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-700">

                        Pending Review

                    </span>

                @elseif(($rider['status'] ?? '') === 'approved')

                    <span class="inline-flex rounded-full bg-[#E6F4EE] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">

                        Approved

                    </span>

                @else

                    <span class="inline-flex rounded-full bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-600">

                        {{ ucfirst($rider['status'] ?? 'Unknown') }}

                    </span>

                @endif

            </div>

        </div>



        <!-- MAIN GRID -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">


            <!-- LEFT CONTENT -->
            <div class="space-y-6 lg:col-span-2">


                <!-- PERSONAL INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

                    <div class="mb-6 flex items-center gap-3">

                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i
                                data-lucide="user"
                                class="h-5 w-5"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold text-gray-900">

                                Personal Information

                            </h2>

                            <p class="text-sm text-gray-500">

                                Rider registration details

                            </p>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">


                        <div>

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Full Name
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['first_name'] ?? '' }}
                                {{ $rider['last_name'] ?? '' }}

                            </p>

                        </div>


                        <div>

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Phone Number
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['phone'] ?? '—' }}

                            </p>

                        </div>


                        <div class="sm:col-span-2">

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Email Address
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['email'] ?? '—' }}

                            </p>

                        </div>

                    </div>

                </section>



                <!-- ADDRESS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

                    <div class="mb-6 flex items-center gap-3">

                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i
                                data-lucide="map-pin"
                                class="h-5 w-5"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold text-gray-900">

                                Address

                            </h2>

                            <p class="text-sm text-gray-500">

                                Registered residential address

                            </p>

                        </div>

                    </div>


                    <p class="text-sm leading-6 text-gray-700">

                        {{ $rider['address'] ?? 'No address provided.' }}

                    </p>

                </section>



                <!-- VEHICLE INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

                    <div class="mb-6 flex items-center gap-3">

                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i
                                data-lucide="bike"
                                class="h-5 w-5"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold text-gray-900">

                                Vehicle Information

                            </h2>

                            <p class="text-sm text-gray-500">

                                Delivery vehicle details

                            </p>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">


                        <div>

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Vehicle Type
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['vehicle_type'] ?? '—' }}

                            </p>

                        </div>


                        <div>

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Plate Number
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['plate_number'] ?? '—' }}

                            </p>

                        </div>


                        <div class="sm:col-span-2">

                            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                                Driver's License Number
                            </p>

                            <p class="mt-2 text-sm font-medium text-gray-900">

                                {{ $rider['license_number'] ?? '—' }}

                            </p>

                        </div>

                    </div>

                </section>



                <!-- DOCUMENTS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">

                    <div class="mb-6 flex items-center gap-3">

                        <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                            <i
                                data-lucide="files"
                                class="h-5 w-5"
                            ></i>

                        </div>

                        <div>

                            <h2 class="font-semibold text-gray-900">

                                Verification Documents

                            </h2>

                            <p class="text-sm text-gray-500">

                                Submitted rider requirements

                            </p>

                        </div>

                    </div>


                    <div class="space-y-4">


                        <!-- GOVERNMENT ID -->
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 p-4">

                            <div class="flex items-center gap-3">

                                <div class="rounded-lg bg-gray-100 p-2 text-gray-600">

                                    <i
                                        data-lucide="id-card"
                                        class="h-5 w-5"
                                    ></i>

                                </div>


                                <div>

                                    <p class="text-sm font-medium text-gray-900">
                                        Valid Government ID
                                    </p>

                                    <p class="text-xs text-gray-500">
                                        Submitted for verification
                                    </p>

                                </div>

                            </div>


                            @if(!empty($rider['government_id']))

                                <a
                                    href="{{ asset('storage/' . $rider['government_id']) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#E6F4EE] px-3 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-[#DDF3EC]"
                                >

                                    <i
                                        data-lucide="eye"
                                        class="h-4 w-4"
                                    ></i>

                                    View

                                </a>

                            @else

                                <span class="text-xs font-medium text-gray-400">
                                    No document
                                </span>

                            @endif

                        </div>



                        <!-- DRIVER LICENSE -->
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 p-4">

                            <div class="flex items-center gap-3">

                                <div class="rounded-lg bg-gray-100 p-2 text-gray-600">

                                    <i
                                        data-lucide="file-check-2"
                                        class="h-5 w-5"
                                    ></i>

                                </div>


                                <div>

                                    <p class="text-sm font-medium text-gray-900">

                                        Driver's License

                                    </p>

                                    <p class="text-xs text-gray-500">

                                        Rider license verification

                                    </p>

                                </div>

                            </div>


                            @if(!empty($rider['drivers_license']))

                                <a
                                    href="{{ asset('storage/' . $rider['drivers_license']) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#E6F4EE] px-3 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-[#DDF3EC]"
                                >

                                    <i
                                        data-lucide="eye"
                                        class="h-4 w-4"
                                    ></i>

                                    View

                                </a>

                            @else

                                <span class="text-xs font-medium text-gray-400">

                                    No document

                                </span>

                            @endif

                        </div>



                        <!-- VEHICLE DOCUMENT -->
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 p-4">

                            <div class="flex items-center gap-3">

                                <div class="rounded-lg bg-gray-100 p-2 text-gray-600">

                                    <i
                                        data-lucide="file-text"
                                        class="h-5 w-5"
                                    ></i>

                                </div>

                                <div>

                                    <p class="text-sm font-medium text-gray-900">

                                        Vehicle Document

                                    </p>

                                    <p class="text-xs text-gray-500">

                                        Vehicle registration document

                                    </p>

                                </div>

                            </div>


                            @if(!empty($rider['vehicle_document']))

                                <a
                                    href="{{ asset('storage/' . $rider['vehicle_document']) }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#E6F4EE] px-3 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-[#DDF3EC]"
                                >

                                    <i
                                        data-lucide="eye"
                                        class="h-4 w-4"
                                    ></i>

                                    View

                                </a>

                            @else

                                <span class="text-xs font-medium text-gray-400">

                                    No document

                                </span>

                            @endif

                        </div>


                    </div>

                </section>

            </div>
            <!-- END LEFT CONTENT -->



            <!-- RIGHT SIDEBAR -->
            <aside>

                <div class="sticky top-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">


                    <div class="mb-6">

                        <h2 class="font-semibold text-gray-900">

                            Application Decision

                        </h2>

                        <p class="mt-1 text-sm leading-6 text-gray-500">

                            Verify the rider information before making a decision.

                        </p>

                    </div>



                    @if(($rider['status'] ?? '') === 'pending')

                        <div class="space-y-3">


                           <!-- APPROVE -->
<form
    method="POST"
    action="{{ route('logistics.riders.approve', $riderId) }}"
>

    @csrf

    <button
        type="submit"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
    >

        <i
            data-lucide="check"
            class="h-4 w-4"
        ></i>

        Approve Rider

    </button>

</form>>



                            <!-- DISAPPROVE -->
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-100"
                            >

                                <i
                                    data-lucide="x"
                                    class="h-4 w-4"
                                ></i>

                                Disapprove Application

                            </button>


                        </div>

                    @else

                        <div class="rounded-xl bg-gray-50 p-4 text-center">

                            <p class="text-sm font-medium text-gray-700">

                                This application has already been reviewed.

                            </p>

                        </div>

                    @endif


                    <!-- APPLICATION DATE -->
                    <div class="mt-6 border-t border-gray-100 pt-5">

                        <p class="text-xs text-gray-400">

                            Application submitted

                        </p>

                        <p class="mt-1 text-sm font-medium text-gray-700">

                            {{ $rider['created_at'] ?? '—' }}

                        </p>

                    </div>


                </div>

            </aside>


        </div>
        <!-- END MAIN GRID -->

    </main>

</div>

@endsection