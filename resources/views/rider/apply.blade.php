@extends('layouts.rider-public')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">


    <!-- =====================================================
    | TOP NAVIGATION
    ====================================================== -->
    <header class="sticky top-0 z-50 border-b border-gray-200/80 bg-white/95 backdrop-blur">

        <div class="mx-auto flex max-w-[1440px] items-center justify-between px-5 py-3 sm:px-8">


            <!-- LEFT: BRAND -->
            <div class="flex items-center gap-6">


                <!-- SUKI SHOP RIDER BRAND -->
                <a
                    href="{{ route('buyer.home') }}"
                    class="group flex items-center gap-3"
                >

                    <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition group-hover:shadow-md">

                        <img
                            src="{{ asset('images/logistics-logo.png') }}"
                            alt="SUKI SHOP"
                            class="h-10 w-auto object-contain"
                         >

                    </div>


                    <div class="hidden sm:block">

                        <div class="flex items-center gap-2">

                            <p class="text-sm font-bold tracking-tight text-gray-900">

                                SUKI SHOP Rider

                            </p>


                            <span class="rounded-full bg-[#E6F4EE] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-[#1F6F5B]">

                                Apply

                            </span>

                        </div>


                        <p class="mt-0.5 text-[11px] text-gray-500">

                            Deliver. Earn. Grow.

                        </p>

                    </div>

                </a>



                <!-- DESKTOP NAV -->
                <nav class="hidden items-center gap-1 border-l border-gray-200 pl-6 lg:flex">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-[#1F6F5B]"
                    >

                        <i
                            data-lucide="store"
                            class="h-4 w-4"
                        ></i>

                        Marketplace

                    </a>


                    <span class="mx-2 h-5 border-l border-gray-200"></span>


                    <div class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-[#1F6F5B]">

                        <i
                            data-lucide="bike"
                            class="h-4 w-4"
                        ></i>

                        Become a Rider

                    </div>

                </nav>

            </div>



            <!-- RIGHT NAVIGATION -->
            <div class="flex items-center gap-3">


                <div class="hidden text-right md:block">

                    <p class="text-xs font-medium text-gray-500">

                        Already a SUKI SHOP Rider?

                    </p>

                </div>


                <a
                    href="{{ route('login') }}"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-[#CFE9DD] hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <i
                        data-lucide="log-in"
                        class="h-4 w-4"
                    ></i>

                    Log In

                </a>

            </div>

        </div>

    </header>



    <!-- =====================================================
    | MAIN CONTENT
    ====================================================== -->
    <main class="mx-auto max-w-[1280px] px-5 py-8 sm:px-8 sm:py-12 lg:py-14">


        <!-- =====================================================
        | PAGE INTRO
        ====================================================== -->
        <div class="mb-10 max-w-3xl">


            <!-- BREADCRUMB -->
            <div class="mb-5 flex items-center gap-2 text-xs font-medium text-gray-500">

                <a
                    href="{{ route('buyer.home') }}"
                    class="transition hover:text-[#1F6F5B]"
                >

                    SUKI SHOP Marketplace

                </a>


                <i
                    data-lucide="chevron-right"
                    class="h-3.5 w-3.5"
                ></i>


                <span class="text-[#1F6F5B]">

                    Rider Application

                </span>

            </div>



            <!-- BADGE -->
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-[#CFE9DD] bg-[#E6F4EE] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">

                <i
                    data-lucide="bike"
                    class="h-4 w-4"
                ></i>

                Join the SUKI SHOP Delivery Team

            </div>



            <h1 class="text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">

                Become a SUKI SHOP Rider

            </h1>


            <p class="mt-4 max-w-2xl text-sm leading-7 text-gray-600 sm:text-base">

                Join our delivery community and help connect sellers with buyers.
                Complete your application and submit the required information
                for verification.

            </p>


            <!-- BENEFITS -->
            <div class="mt-6 flex flex-wrap gap-3">

                <div class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm">

                    <i
                        data-lucide="wallet"
                        class="h-4 w-4 text-[#1F6F5B]"
                    ></i>

                    Earn from deliveries

                </div>


                <div class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm">

                    <i
                        data-lucide="clock-3"
                        class="h-4 w-4 text-[#1F6F5B]"
                    ></i>

                    Flexible opportunities

                </div>


                <div class="inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm">

                    <i
                        data-lucide="shield-check"
                        class="h-4 w-4 text-[#1F6F5B]"
                    ></i>

                    Secure verification

                </div>

            </div>

        </div>



        <!-- =====================================================
        | APPLICATION FORM
        ====================================================== -->
        <form
            method="POST"
            action="{{ route('rider.apply.submit') }}"
            enctype="multipart/form-data"
            class="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_340px]"
        >

            @csrf



            <!-- =====================================================
            | LEFT FORM
            ====================================================== -->
            <div class="space-y-6">


                <!-- PERSONAL INFORMATION -->
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    <div class="border-b border-gray-100 px-6 py-5 sm:px-7">

                        <div class="flex items-start gap-4">

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="user"
                                    class="h-5 w-5"
                                ></i>

                            </div>


                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Personal Information

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Enter your basic personal details.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2 sm:p-7">


                        <!-- FIRST NAME -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                First Name

                            </label>


                            <input
                                type="text"
                                name="first_name"
                                value="{{ old('first_name') }}"
                                required
                                placeholder="Enter first name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >


                            @error('first_name')

                                <p class="mt-2 text-xs text-red-600">

                                    {{ $message }}

                                </p>

                            @enderror

                        </div>



                        <!-- LAST NAME -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Last Name

                            </label>


                            <input
                                type="text"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                required
                                placeholder="Enter last name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >


                            @error('last_name')

                                <p class="mt-2 text-xs text-red-600">

                                    {{ $message }}

                                </p>

                            @enderror

                        </div>



                        <!-- PHONE -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Phone Number

                            </label>


                            <div class="relative">

                                <i
                                    data-lucide="phone"
                                    class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>


                                <input
                                    type="tel"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    required
                                    placeholder="09XXXXXXXXX"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                                >

                            </div>


                            @error('phone')

                                <p class="mt-2 text-xs text-red-600">

                                    {{ $message }}

                                </p>

                            @enderror

                        </div>



                        <!-- EMAIL -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Email Address

                            </label>


                            <div class="relative">

                                <i
                                    data-lucide="mail"
                                    class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>


                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                                >

                            </div>


                            @error('email')

                                <p class="mt-2 text-xs text-red-600">

                                    {{ $message }}

                                </p>

                            @enderror

                        </div>

                    </div>

                </section>



                <!-- ACCOUNT SECURITY -->
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    <div class="border-b border-gray-100 px-6 py-5 sm:px-7">

                        <div class="flex items-start gap-4">

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="lock-keyhole"
                                    class="h-5 w-5"
                                ></i>

                            </div>


                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Account Security

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Create a secure password for your rider account.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2 sm:p-7">


                        <!-- PASSWORD -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Password

                            </label>


                            <input
                                type="password"
                                name="password"
                                required
                                placeholder="Minimum 8 characters"
                                class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >


                            @error('password')

                                <p class="mt-2 text-xs text-red-600">

                                    {{ $message }}

                                </p>

                            @enderror

                        </div>



                        <!-- CONFIRM PASSWORD -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Confirm Password

                            </label>


                            <input
                                type="password"
                                name="password_confirmation"
                                required
                                placeholder="Confirm your password"
                                class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >

                        </div>

                    </div>

                </section>



                <!-- ADDRESS -->
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    <div class="border-b border-gray-100 px-6 py-5 sm:px-7">

                        <div class="flex items-start gap-4">

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="map-pin"
                                    class="h-5 w-5"
                                ></i>

                            </div>


                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Residential Address

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Provide your current residential address.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="p-6 sm:p-7">

                        <label class="mb-2 block text-sm font-medium text-gray-700">

                            Complete Address

                        </label>


                        <textarea
                            name="address"
                            rows="4"
                            required
                            placeholder="House/Unit No., Street, Barangay, Municipality/City, Province"
                            class="w-full resize-none rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                        >{{ old('address') }}</textarea>


                        @error('address')

                            <p class="mt-2 text-xs text-red-600">

                                {{ $message }}

                            </p>

                        @enderror

                    </div>

                </section>



                <!-- VEHICLE INFORMATION -->
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    <div class="border-b border-gray-100 px-6 py-5 sm:px-7">

                        <div class="flex items-start gap-4">

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="bike"
                                    class="h-5 w-5"
                                ></i>

                            </div>


                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Vehicle Information

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Tell us about the vehicle you will use for deliveries.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2 sm:p-7">


                        <!-- VEHICLE TYPE -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Vehicle Type

                            </label>


                            <select
                                name="vehicle_type"
                                required
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >

                                <option value="">

                                    Select vehicle

                                </option>


                                <option
                                    value="Motorcycle"
                                    @selected(old('vehicle_type') === 'Motorcycle')
                                >

                                    Motorcycle

                                </option>


                                <option
                                    value="E-bike"
                                    @selected(old('vehicle_type') === 'E-bike')
                                >

                                    E-bike

                                </option>


                                <option
                                    value="Bicycle"
                                    @selected(old('vehicle_type') === 'Bicycle')
                                >

                                    Bicycle

                                </option>

                            </select>

                        </div>



                        <!-- PLATE NUMBER -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Plate Number

                                <span class="font-normal text-gray-400">

                                    (if applicable)

                                </span>

                            </label>


                            <input
                                type="text"
                                name="plate_number"
                                value="{{ old('plate_number') }}"
                                placeholder="e.g. ABC 1234"
                                class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm uppercase outline-none transition focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >

                        </div>



                        <!-- LICENSE -->
                        <div class="sm:col-span-2">

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Driver's License Number

                                <span class="font-normal text-gray-400">

                                    (required for motor vehicles)

                                </span>

                            </label>


                            <input
                                type="text"
                                name="license_number"
                                value="{{ old('license_number') }}"
                                placeholder="Enter driver's license number"
                                class="w-full rounded-xl border border-gray-300 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-4 focus:ring-[#1F6F5B]/10"
                            >

                        </div>

                    </div>

                </section>



                <!-- DOCUMENTS -->
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                    <div class="border-b border-gray-100 px-6 py-5 sm:px-7">

                        <div class="flex items-start gap-4">

                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="file-check"
                                    class="h-5 w-5"
                                ></i>

                            </div>


                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Verification Documents

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Upload the required documents for verification.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="space-y-4 p-6 sm:p-7">


                        <!-- GOVERNMENT ID -->
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-5">

                            <div class="flex items-start gap-4">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                    <i
                                        data-lucide="id-card"
                                        class="h-5 w-5"
                                    ></i>

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="font-medium text-gray-900">

                                        Valid Government ID

                                    </p>


                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        Upload a clear photo or scan of a valid government-issued ID.

                                    </p>


                                    <input
                                        type="file"
                                        name="government_id"
                                        accept="image/*,.pdf"
                                        required
                                        class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                    >

                                </div>

                            </div>

                        </div>



                        <!-- DRIVER LICENSE -->
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-5">

                            <div class="flex items-start gap-4">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                    <i
                                        data-lucide="file-check-2"
                                        class="h-5 w-5"
                                    ></i>

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="font-medium text-gray-900">

                                        Driver's License

                                    </p>


                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        Required for riders using motorcycles or other motor vehicles.

                                    </p>


                                    <input
                                        type="file"
                                        name="drivers_license"
                                        accept="image/*,.pdf"
                                        class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                    >

                                </div>

                            </div>

                        </div>



                        <!-- VEHICLE DOCUMENT -->
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/50 p-5">

                            <div class="flex items-start gap-4">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                    <i
                                        data-lucide="file-text"
                                        class="h-5 w-5"
                                    ></i>

                                </div>


                                <div class="min-w-0 flex-1">

                                    <p class="font-medium text-gray-900">

                                        Vehicle Document

                                        <span class="font-normal text-gray-400">

                                            (if applicable)

                                        </span>

                                    </p>


                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        Upload vehicle registration or another supporting document.

                                    </p>


                                    <input
                                        type="file"
                                        name="vehicle_document"
                                        accept="image/*,.pdf"
                                        class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                </section>



                <!-- AGREEMENT -->
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-7">

                    <label class="flex cursor-pointer items-start gap-4">

                        <input
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                        >


                        <span class="text-sm leading-6 text-gray-600">

                            I confirm that the information and documents I provided
                            are accurate. I agree to the SUKI SHOP Rider terms,
                            verification process, and application requirements.

                        </span>

                    </label>


                    @error('terms')

                        <p class="mt-3 text-xs text-red-600">

                            {{ $message }}

                        </p>

                    @enderror

                </section>

            </div>



            <!-- =====================================================
            | RIGHT APPLICATION PANEL
            ====================================================== -->
            <aside class="lg:sticky lg:top-24 lg:self-start">


                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">


                    <!-- PANEL HEADER -->
                    <div class="border-b border-gray-100 p-6">

                        <div class="flex items-center justify-between">

                            <div>

                                <h2 class="font-semibold text-gray-900">

                                    Your Application

                                </h2>


                                <p class="mt-1 text-sm text-gray-500">

                                    Complete the steps below.

                                </p>

                            </div>


                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E6F4EE] text-[#1F6F5B]">

                                <i
                                    data-lucide="clipboard-check"
                                    class="h-5 w-5"
                                ></i>

                            </div>

                        </div>

                    </div>



                    <!-- STEPS -->
                    <div class="space-y-6 p-6">


                        <!-- STEP 1 -->
                        <div class="flex gap-3">

                            <div class="flex flex-col items-center">

                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#1F6F5B] text-xs font-bold text-white">

                                    1

                                </div>


                                <div class="mt-2 h-8 border-l border-dashed border-gray-200"></div>

                            </div>


                            <div>

                                <p class="text-sm font-semibold text-gray-900">

                                    Submit Application

                                </p>


                                <p class="mt-1 text-xs leading-5 text-gray-500">

                                    Complete your information and upload the required documents.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 2 -->
                        <div class="flex gap-3">

                            <div class="flex flex-col items-center">

                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">

                                    2

                                </div>


                                <div class="mt-2 h-8 border-l border-dashed border-gray-200"></div>

                            </div>


                            <div>

                                <p class="text-sm font-semibold text-gray-800">

                                    Verification

                                </p>


                                <p class="mt-1 text-xs leading-5 text-gray-500">

                                    The SUKI SHOP Logistics team reviews your application.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 3 -->
                        <div class="flex gap-3">

                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">

                                3

                            </div>


                            <div>

                                <p class="text-sm font-semibold text-gray-800">

                                    Rider Access

                                </p>


                                <p class="mt-1 text-xs leading-5 text-gray-500">

                                    Once approved, you can access your SUKI SHOP Rider dashboard.

                                </p>

                            </div>

                        </div>

                    </div>



                    <!-- SECURITY INFO -->
                    <div class="mx-6 mb-6 rounded-xl border border-[#CFE9DD] bg-[#EEF8F3] p-4">

                        <div class="flex items-start gap-3">

                            <i
                                data-lucide="shield-check"
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#1F6F5B]"
                            ></i>


                            <div>

                                <p class="text-xs font-semibold text-[#155244]">

                                    Application Review

                                </p>


                                <p class="mt-1 text-xs leading-5 text-[#1F6F5B]">

                                    Rider access will only be granted after your application has been reviewed and approved.

                                </p>

                            </div>

                        </div>

                    </div>



                    <!-- ACTIONS -->
                    <div class="border-t border-gray-100 p-6">


                        <!-- SUBMIT -->
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#155244] hover:shadow active:scale-[0.99]"
                        >

                            <i
                                data-lucide="send"
                                class="h-4 w-4"
                            ></i>

                            Submit Application

                        </button>



                        <!-- LOGIN -->
                        <a
                            href="{{ route('login') }}"
                            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 hover:text-[#1F6F5B]"
                        >

                            <i
                                data-lucide="log-in"
                                class="h-4 w-4"
                            ></i>

                            I already have an account

                        </a>



                        <!-- BACK -->
                        <a
                            href="{{ route('buyer.home') }}"
                            class="mt-4 flex items-center justify-center gap-2 text-sm font-medium text-gray-500 transition hover:text-[#1F6F5B]"
                        >

                            <i
                                data-lucide="arrow-left"
                                class="h-4 w-4"
                            ></i>

                            Back to Marketplace

                        </a>

                    </div>

                </div>

            </aside>

        </form>

    </main>



    <!-- =====================================================
    | FOOTER
    ====================================================== -->
    <footer class="border-t border-gray-200 bg-white">

        <div class="mx-auto flex max-w-[1280px] flex-col gap-3 px-5 py-6 text-center text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:text-left">

            <p>

                © {{ date('Y') }} SUKI SHOP Marketplace. All rights reserved.

            </p>


            <div class="flex justify-center gap-4">

                <span class="flex items-center gap-1.5">

                    <i
                        data-lucide="shield-check"
                        class="h-3.5 w-3.5 text-[#1F6F5B]"
                    ></i>

                    Secure Application

                </span>


                <span>

                    SUKI SHOP Rider Program

                </span>

            </div>

        </div>

    </footer>


</div>

@endsection