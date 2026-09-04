@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <a href="{{ route('buyer.home') }}" class="flex items-center">
                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI"
                    class="h-10 sm:h-12 w-auto object-contain"
                >
            </a>

            <a
                href="{{ route('login') }}"
                class="text-sm font-medium text-gray-600 hover:text-[#1F6F5B] transition"
            >
                Log In
            </a>

        </div>
    </header>


    <!-- MAIN -->
    <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12 lg:py-16">

        <!-- PAGE INTRO -->
        <div class="mb-8 max-w-3xl">
            <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-[#E6F4EE] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">
                <i data-lucide="bike" class="h-4 w-4"></i>
                SUKI Logistics
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl lg:text-4xl">
                Become a SUKI Rider
            </h1>

            <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base">
                Join the SUKI delivery team and help bring orders from sellers
                to buyers. Complete your information and submit the required
                details to apply as a rider.
            </p>
        </div>


        <!-- APPLICATION FORM -->
        <form
            method="POST"
            action="{{ route('rider.apply.submit') }}"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]"
        >

            @csrf


            <!-- LEFT -->
            <div class="space-y-6">

                <!-- PERSONAL INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Personal Information
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Enter your basic personal details.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

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
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                            @error('first_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
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
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                            @error('last_name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
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
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >
                            </div>

                            @error('phone')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
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
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >
                            </div>

                            @error('email')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </section>


                <!-- ADDRESS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Address
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Provide your current residential address.
                        </p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Complete Address
                        </label>

                        <textarea
                            name="address"
                            rows="4"
                            required
                            placeholder="House/Unit No., Street, Barangay, Municipality/City, Province"
                            class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                        >{{ old('address') }}</textarea>

                        @error('address')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </section>


                <!-- VEHICLE INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Vehicle Information
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Tell us about the vehicle you will use for deliveries.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                        <!-- VEHICLE TYPE -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Vehicle Type
                            </label>

                            <select
                                name="vehicle_type"
                                required
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                                <option value="">Select vehicle</option>
                                <option value="Motorcycle" @selected(old('vehicle_type') === 'Motorcycle')>
                                    Motorcycle
                                </option>
                                <option value="E-bike" @selected(old('vehicle_type') === 'E-bike')>
                                    E-bike
                                </option>
                                <option value="Bicycle" @selected(old('vehicle_type') === 'Bicycle')>
                                    Bicycle
                                </option>
                            </select>

                            @error('vehicle_type')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>


                        <!-- PLATE NUMBER -->
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Plate Number
                            </label>

                            <input
                                type="text"
                                name="plate_number"
                                value="{{ old('plate_number') }}"
                                placeholder="e.g. ABC 1234"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm uppercase outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                            @error('plate_number')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>


                        <!-- LICENSE -->
                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Driver's License Number
                            </label>

                            <input
                                type="text"
                                name="license_number"
                                value="{{ old('license_number') }}"
                                required
                                placeholder="Enter driver's license number"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                            @error('license_number')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </section>


                <!-- REQUIREMENTS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Rider Requirements
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Please prepare the required information and documents
                            for rider verification.
                        </p>
                    </div>

                    <div class="space-y-3">

                        <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
                            <div class="mt-0.5 rounded-lg bg-[#E6F4EE] p-2 text-[#1F6F5B]">
                                <i data-lucide="id-card" class="h-4 w-4"></i>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Valid Government ID
                                </p>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    A valid identification document for account verification.
                                </p>
                            </div>
                        </div>


                        <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
                            <div class="mt-0.5 rounded-lg bg-[#E6F4EE] p-2 text-[#1F6F5B]">
                                <i data-lucide="file-check-2" class="h-4 w-4"></i>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Valid Driver's License
                                </p>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    Required for riders using a motorcycle or other motor vehicle.
                                </p>
                            </div>
                        </div>


                        <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-4">
                            <div class="mt-0.5 rounded-lg bg-[#E6F4EE] p-2 text-[#1F6F5B]">
                                <i data-lucide="bike" class="h-4 w-4"></i>
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Delivery Vehicle
                                </p>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    A reliable vehicle suitable for SUKI deliveries.
                                </p>
                            </div>
                        </div>

                    </div>

                </section>


                <!-- AGREEMENT -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <label class="flex cursor-pointer items-start gap-3">

                        <input
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                        >

                        <span class="text-sm leading-6 text-gray-600">
                            I confirm that the information I provided is accurate
                            and I agree to the SUKI Rider terms and requirements.
                        </span>

                    </label>

                    @error('terms')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                </section>

            </div>


            <!-- RIGHT SIDEBAR -->
            <aside class="lg:sticky lg:top-6 lg:self-start">

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">

                    <div class="mb-5">
                        <h2 class="font-semibold text-gray-900">
                            Rider Application
                        </h2>

                        <p class="mt-1 text-sm leading-5 text-gray-500">
                            Review your information before submitting your application.
                        </p>
                    </div>


                    <!-- APPLICATION STEPS -->
                    <div class="mb-6 space-y-4">

                        <div class="flex items-start gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#1F6F5B] text-xs font-semibold text-white">
                                1
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Submit Application
                                </p>

                                <p class="text-xs text-gray-500">
                                    Complete your rider information.
                                </p>
                            </div>
                        </div>


                        <div class="flex items-start gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                                2
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Verification
                                </p>

                                <p class="text-xs text-gray-500">
                                    SUKI reviews your application.
                                </p>
                            </div>
                        </div>


                        <div class="flex items-start gap-3">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">
                                3
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Rider Access
                                </p>

                                <p class="text-xs text-gray-500">
                                    Approved riders can access the dashboard.
                                </p>
                            </div>
                        </div>

                    </div>


                    <div class="mb-5 rounded-xl bg-[#EEF8F3] p-4">
                        <div class="flex items-start gap-3">
                            <i
                                data-lucide="shield-check"
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#1F6F5B]"
                            ></i>

                            <p class="text-xs leading-5 text-[#155244]">
                                Your application will be reviewed before rider
                                access is granted.
                            </p>
                        </div>
                    </div>


                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244] active:scale-[0.99]"
                    >
                        <i data-lucide="send" class="h-4 w-4"></i>
                        Submit Rider Application
                    </button>


                    <a
                        href="{{ route('buyer.home') }}"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Back to SUKI
                    </a>

                </div>

            </aside>

        </form>

    </main>

</div>

@endsection