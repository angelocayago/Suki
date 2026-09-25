@extends('layouts.logistics')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <!-- LOGO -->
            <a href="{{ route('buyer.home') }}" class="flex items-center gap-3">

                <img
                    src="{{ asset('images/suki-logistics.jpg') }}"
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


            <!-- LOGIN -->
            <a
                href="{{ route('logistics.login') }}"
                class="text-sm font-medium text-gray-600 transition hover:text-[#1F6F5B]"
            >
                Log In
            </a>

        </div>

    </header>



    <!-- MAIN -->
    <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12 lg:py-16">


        <!-- PAGE INTRO -->
        <div class="mb-8 max-w-3xl">

            <!-- BADGE -->
            <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-[#E6F4EE] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">

                <i data-lucide="building-2" class="h-4 w-4"></i>

                SUKI SHOP Logistics Partner

            </div>


            <!-- TITLE -->
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl lg:text-4xl">

                Register Your Logistics Center

            </h1>


            <!-- DESCRIPTION -->
            <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base">

                Join the SUKI SHOP Logistics network and manage parcel receiving,
                sorting, rider assignments, and delivery operations through
                the SUKI SHOP Logistics Management System.

            </p>

        </div>



        <!-- FORM -->
        <form
            method="POST"
            action="{{ route('logistics.register.submit') }}"
            enctype="multipart/form-data"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]"
        >

            @csrf



            <!-- LEFT SIDE -->
            <div class="space-y-6">


                <!-- PERSONAL INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="user" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Account Owner Information

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Enter the information of the logistics center representative.

                                </p>

                            </div>

                        </div>

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

                        </div>



                        <!-- MIDDLE INITIAL -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Middle Initial

                                <span class="text-xs font-normal text-gray-400">
                                    (optional)
                                </span>

                            </label>

                            <input
                                type="text"
                                name="middle_initial"
                                value="{{ old('middle_initial') }}"
                                maxlength="5"
                                placeholder="e.g. A."
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm uppercase outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>



                        <!-- SEX -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Sex

                            </label>

                            <select
                                name="sex"
                                required
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                                <option value="">Select sex</option>

                                <option value="Male">Male</option>

                                <option value="Female">Female</option>

                            </select>

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

                        </div>



                        <!-- CONTACT NUMBER -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Contact Number

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

                        </div>



                        <!-- BIRTHDAY -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Birthday

                            </label>

                            <input
                                type="date"
                                name="birthday"
                                value="{{ old('birthday') }}"
                                required
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>



                        <!-- AGE -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Age

                                <span class="text-xs font-normal text-gray-400">
                                    (automatically calculated)
                                </span>

                            </label>

                            <input
                                type="number"
                                name="age"
                                value="{{ old('age') }}"
                                readonly
                                placeholder="Auto-generated"
                                class="w-full cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 outline-none"
                            >

                        </div>


                    </div>

                </section>



                <!-- LOGISTICS BUSINESS INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="building-2" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Logistics Center Information

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Provide information about your logistics or sorting center.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="space-y-5">


                        <!-- BUSINESS NAME -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Business / Logistics Center Name

                            </label>

                            <input
                                type="text"
                                name="business_name"
                                value="{{ old('business_name') }}"
                                required
                                placeholder="Enter business or logistics center name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>


                    </div>

                </section>



                <!-- ADDRESS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="map-pin" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Logistics Center Address

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Enter the location of your logistics or sorting center.

                                </p>

                            </div>

                        </div>

                    </div>


                    <label class="mb-2 block text-sm font-medium text-gray-700">

                        Complete Address

                    </label>


                    <textarea
                        name="address"
                        rows="4"
                        required
                        placeholder="House/Building No., Street, Barangay, Municipality/City, Province"
                        class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                    >{{ old('address') }}</textarea>

                </section>



                <!-- DOCUMENTS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="file-check-2" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Verification Documents

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Upload the required documents for verification.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="space-y-5">


                        <!-- GOVERNMENT ID -->
                        <div class="rounded-xl border border-dashed border-gray-300 p-5">

                            <div class="flex items-start gap-4">

                                <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                                    <i data-lucide="id-card" class="h-5 w-5"></i>

                                </div>


                                <div class="flex-1">

                                    <p class="text-sm font-semibold text-gray-900">

                                        Valid Government ID

                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">

                                        Upload a clear photo or scan of a valid government-issued ID.

                                    </p>


                                    <input
                                        type="file"
                                        name="government_id"
                                        accept="image/*,.pdf"
                                        required
                                        class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                    >

                                </div>

                            </div>

                        </div>



                        <!-- BUSINESS PERMIT -->
                        <div class="rounded-xl border border-dashed border-gray-300 p-5">

                            <div class="flex items-start gap-4">

                                <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                                    <i data-lucide="briefcase-business" class="h-5 w-5"></i>

                                </div>


                                <div class="flex-1">

                                    <p class="text-sm font-semibold text-gray-900">

                                        Business / DTI Permit

                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">

                                        Upload your business registration, DTI permit, or supporting business document.

                                    </p>


                                    <input
                                        type="file"
                                        name="business_permit"
                                        accept="image/*,.pdf"
                                        required
                                        class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                    >

                                </div>

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

                            I confirm that the information and documents provided
                            are accurate and I agree to the SUKI SHOP Logistics
                            verification requirements and platform policies.

                        </span>

                    </label>

                </section>


            </div>



            <!-- RIGHT SIDEBAR -->
            <aside class="lg:sticky lg:top-6 lg:self-start">

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">


                    <!-- LOGISTICS ICON -->
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F4EE] text-[#1F6F5B]">

                        <i data-lucide="warehouse" class="h-6 w-6"></i>

                    </div>


                    <h2 class="font-semibold text-gray-900">

                        Join SUKI SHOP Logistics

                    </h2>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Complete your registration to apply as an official
                        SUKI SHOP Logistics or Sorting Center partner.

                    </p>



                    <!-- STEPS -->
                    <div class="my-6 space-y-5">


                        <!-- STEP 1 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#1F6F5B] text-xs font-semibold text-white">

                                1

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Submit Registration

                                </p>

                                <p class="text-xs text-gray-500">

                                    Complete your business information.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 2 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">

                                2

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Admin Verification

                                </p>

                                <p class="text-xs text-gray-500">

                                    SUKI SHOP verifies your documents and registration.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 3 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">

                                3

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Logistics Access

                                </p>

                                <p class="text-xs text-gray-500">

                                    Approved partners can access the logistics system.

                                </p>

                            </div>

                        </div>


                    </div>



                    <!-- INFO BOX -->
                    <div class="mb-5 rounded-xl bg-[#EEF8F3] p-4">

                        <div class="flex items-start gap-3">

                            <i
                                data-lucide="shield-check"
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#1F6F5B]"
                            ></i>


                            <p class="text-xs leading-5 text-[#155244]">

                                Your registration will be reviewed by the SUKI SHOP
                                administrator before access to the Logistics
                                Management System is granted.

                            </p>

                        </div>

                    </div>



                    <!-- SUBMIT -->
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244] active:scale-[0.99]"
                    >

                        <i data-lucide="send" class="h-4 w-4"></i>

                        Submit Registration

                    </button>



                    <!-- BACK -->
                    <a
                        href="{{ route('buyer.home') }}"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >

                        <i data-lucide="arrow-left" class="h-4 w-4"></i>

                        Back to SUKI SHOP

                    </a>


                </div>

            </aside>


        </form>

    </main>

</div>


<!-- AUTO CALCULATE AGE -->
<script>

    const birthdayInput = document.querySelector('[name="birthday"]');
    const ageInput = document.querySelector('[name="age"]');

    birthdayInput?.addEventListener('change', function () {

        if (!this.value) return;

        const birthday = new Date(this.value);
        const today = new Date();

        let age = today.getFullYear() - birthday.getFullYear();

        const monthDifference =
            today.getMonth() - birthday.getMonth();

        if (
            monthDifference < 0 ||
            (
                monthDifference === 0 &&
                today.getDate() < birthday.getDate()
            )
        ) {

            age--;

        }

        ageInput.value = age;

    });

</script>

@endsection