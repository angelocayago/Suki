@extends('layouts.auth')

@section('title', 'Create Seller Account - SUKI SHOP')

@section('content')


<form
    method="POST"
    action="{{ route('seller.register.submit') }}"
    enctype="multipart/form-data"
>

@csrf


<div class="min-h-screen bg-[#F8FAF8]">

    <div class="grid min-h-screen grid-cols-1 lg:grid-cols-[45%_55%]">


        {{-- ==========================================
             LEFT BRANDING
        =========================================== --}}
        <section class="bg-[#EEF8F3] flex items-center justify-center px-6 py-10 sm:px-10 sm:py-12 lg:px-12 lg:py-16">

            <div class="w-full max-w-md">

                {{-- LOGO --}}
                <div class="mb-7 sm:mb-8 flex justify-center">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="h-36 sm:h-40 lg:h-44 w-auto object-contain"
                    >

                </div>


                {{-- HEADING --}}
                <h1 class="text-3xl sm:text-4xl lg:text-4xl xl:text-5xl font-bold text-[#1F2937] leading-tight text-center">

                    Start selling<br>

                    with
                    <span class="text-[#1F6F5B]">
                        SUKI SHOP.
                    </span>

                </h1>


                {{-- DESCRIPTION --}}
                <p class="mt-4 sm:mt-5 text-sm sm:text-base text-gray-600 leading-7 text-center">

                    Create your seller account and start managing your products,
                    orders, inventory, and sales in one place.

                </p>


                {{-- ==========================================
                     BENEFITS
                =========================================== --}}
                <div class="mt-7 sm:mt-8 space-y-3 sm:space-y-4">


                    {{-- BENEFIT 1 --}}
                    <div class="flex items-center gap-3">

                        <div class="w-9 h-9 shrink-0 rounded-lg bg-white flex items-center justify-center">

                            <i
                                data-lucide="store"
                                class="w-5 h-5 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <span class="text-sm font-medium text-gray-700">
                            Manage your own SUKI SHOP shop
                        </span>

                    </div>


                    {{-- BENEFIT 2 --}}
                    <div class="flex items-center gap-3">

                        <div class="w-9 h-9 shrink-0 rounded-lg bg-white flex items-center justify-center">

                            <i
                                data-lucide="package"
                                class="w-5 h-5 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <span class="text-sm font-medium text-gray-700">
                            Manage products and inventory
                        </span>

                    </div>


                    {{-- BENEFIT 3 --}}
                    <div class="flex items-center gap-3">

                        <div class="w-9 h-9 shrink-0 rounded-lg bg-white flex items-center justify-center">

                            <i
                                data-lucide="chart-no-axes-combined"
                                class="w-5 h-5 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <span class="text-sm font-medium text-gray-700">
                            Monitor orders and sales
                        </span>

                    </div>

                </div>


                {{-- SECURITY --}}
                <div class="mt-8 sm:mt-10 flex items-center justify-center gap-2 text-sm text-gray-500">

                    <i
                        data-lucide="shield-check"
                        class="w-4 h-4 text-[#1F6F5B] shrink-0"
                    ></i>

                    <span>
                        Secure seller platform by SUKI SHOP
                    </span>

                </div>

            </div>

        </section>



        {{-- ==========================================
             RIGHT FORM
        =========================================== --}}
        <section class="bg-[#F8FAF8] flex items-center justify-center px-5 py-10 sm:px-8 sm:py-12 lg:px-12 xl:px-16">

            <div class="w-full max-w-xl">


                {{-- ==========================================
                     MOBILE LOGO
                =========================================== --}}
                <div class="flex justify-center mb-7 sm:mb-8 lg:hidden">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="h-20 sm:h-24 w-auto object-contain"
                    >

                </div>



                {{-- ==========================================
                     HEADER
                =========================================== --}}
                <div class="mb-7 sm:mb-8">

                    <p class="text-xs sm:text-sm font-semibold text-[#1F6F5B]">
                        SELLER CENTRE
                    </p>

                    <h2 class="mt-2 text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">
                        Create your seller account
                    </h2>

                    <p class="mt-2 text-sm text-gray-500 leading-relaxed">
                        Fill in your details to start selling on SUKI SHOP.
                    </p>

                </div>



                {{-- ==========================================
                     VALIDATION ERRORS
                =========================================== --}}
                @if ($errors->any())

                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3">

                        <div class="flex gap-3">

                            <i
                                data-lucide="circle-alert"
                                class="w-5 h-5 text-red-500 shrink-0 mt-0.5"
                            ></i>

                            <div class="min-w-0">

                                <p class="text-sm font-semibold text-red-700">
                                    Please check the following:
                                </p>

                                <ul class="mt-1 text-sm text-red-600 list-disc list-inside space-y-1">

                                    @foreach ($errors->all() as $error)

                                        <li>
                                            {{ $error }}
                                        </li>

                                    @endforeach

                                </ul>

                            </div>

                        </div>

                    </div>

                @endif



               
                @csrf


{{-- ==========================================
     ACCOUNT INFORMATION
========================================== --}}
<div>

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Account Information
    </h3>


    <div class="space-y-4">


        {{-- EMAIL --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                E-mail *
            </label>

            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                placeholder="Enter your e-mail address"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">


            {{-- PASSWORD --}}
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Password *
                </label>

                <div class="relative">

    <input
        type="password"
        id="password"
        name="password"
        required
        placeholder="Create a password"
        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
    >


    <button
        type="button"
        onclick="togglePassword('password','passwordIcon')"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B]"
    >

        <i
            id="passwordIcon"
            data-lucide="eye"
            class="w-5 h-5"
        ></i>

    </button>

</div>

            </div>



            {{-- CONFIRM PASSWORD --}}
            <div>

                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Confirm Password *
                </label>

                <div class="relative">

    <input
        type="password"
        id="password_confirmation"
        name="password_confirmation"
        required
        placeholder="Confirm your password"
        class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 pr-12 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
    >


    <button
        type="button"
        onclick="togglePassword('password_confirmation','confirmPasswordIcon')"
        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B]"
    >

        <i
            id="confirmPasswordIcon"
            data-lucide="eye"
            class="w-5 h-5"
        ></i>

    </button>


</div>

            </div>


        </div>


    </div>

</div>





{{-- ==========================================
     PERSONAL INFORMATION
========================================== --}}
<div class="mt-8">

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Personal Information
    </h3>


    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">


        {{-- FIRST NAME --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                First Name *
            </label>

            <input
                type="text"
                name="first_name"
                required
                placeholder="First name"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        {{-- LAST NAME --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Last Name *
            </label>

            <input
                type="text"
                name="last_name"
                required
                placeholder="Last name"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        {{-- MIDDLE INITIAL --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Middle Initial
            </label>

            <input
                type="text"
                name="middle_initial"
                placeholder="M"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        {{-- SEX --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Sex *
            </label>

            <select
                name="sex"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

                <option value="">
                    Select sex
                </option>

                <option value="Male">
                    Male
                </option>

                <option value="Female">
                    Female
                </option>

            </select>

        </div>



        {{-- BIRTHDAY --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Birthday *
            </label>

            <input
                type="date"
                name="birthday"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        {{-- AGE --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Age *
            </label>

            <input
    type="text"
    id="age"
    name="age"
    readonly
    placeholder="Auto-generated"
    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3.5 text-sm"
>

        </div>



    </div>



    {{-- CONTACT --}}
    <div class="mt-4">

        <label class="block text-sm font-medium text-gray-700 mb-2">
            Contact No. *
        </label>

        <input
            type="tel"
            name="phone"
            value="{{ old('phone') }}"
            required
            inputmode="tel"
            autocomplete="tel"
            maxlength="13"
            pattern="(?:09[0-9]{9}|\+639[0-9]{9})"
            title="Enter an 11-digit Philippine mobile number starting with 09 or +639."
            placeholder="09XXXXXXXXX or +639XXXXXXXXX"
            aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
        >

        @error('phone')
            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
        @enderror

    </div>


</div>

{{-- ==========================================
     ADDRESS
========================================== --}}
<div class="mt-8">

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Address
    </h3>


    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- REGION --}}
        <div>

            <label for="region-trigger" class="block text-sm font-medium text-gray-700 mb-2">
                Region *
            </label>

            <div class="relative" data-address-combobox="region">
                <button
                    id="region-trigger"
                    type="button"
                    role="combobox"
                    aria-expanded="false"
                    aria-controls="region-listbox"
                    aria-haspopup="listbox"
                    aria-describedby="region-validation"
                    disabled
                    class="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
                >
                    <span id="region-value" class="truncate text-gray-500">Loading regions...</span>
                    <span class="ml-3 shrink-0 text-gray-500" aria-hidden="true">⌄</span>
                </button>
                <select id="region" name="region" class="hidden" tabindex="-1" aria-hidden="true"></select>
                <div
                    id="region-options"
                    class="absolute left-0 right-0 top-full z-30 mt-1 hidden rounded-xl border border-gray-200 bg-white p-2 shadow-lg"
                >
                    <input
                        id="region-search"
                        type="search"
                        role="searchbox"
                        aria-label="Search regions"
                        autocomplete="off"
                        placeholder="Search region"
                        class="mb-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                    >
                    <div id="region-listbox" role="listbox" aria-label="Regions" class="overflow-y-auto" style="max-height: 224px; overscroll-behavior: contain;"></div>
                </div>
                <p id="region-validation" class="mt-1 hidden text-xs text-red-600" aria-live="polite"></p>
            </div>

        </div>


        {{-- PROVINCE --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Province *
            </label>

            <select
                id="province"
                name="province"
                required
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >
                <option value="">
                    Select region first
                </option>
            </select>

        </div>



        {{-- MUNICIPALITY --}}
        <div>

            <label for="municipality-trigger" class="block text-sm font-medium text-gray-700 mb-2">
                City / Municipality *
            </label>

            <div class="relative" data-address-combobox="municipality">
                <button
                    id="municipality-trigger"
                    type="button"
                    role="combobox"
                    aria-expanded="false"
                    aria-controls="municipality-listbox"
                    aria-haspopup="listbox"
                    aria-describedby="municipality-validation"
                    disabled
                    class="flex w-full items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
                >
                    <span id="municipality-value" class="truncate text-gray-500">Select city/municipality</span>
                    <span class="ml-3 shrink-0 text-gray-500" aria-hidden="true">⌄</span>
                </button>
                <select id="municipality" name="municipality" class="hidden" tabindex="-1" aria-hidden="true"></select>
                <div
                    id="municipality-options"
                    class="absolute left-0 right-0 top-full z-30 mt-1 hidden rounded-xl border border-gray-200 bg-white p-2 shadow-lg"
                >
                    <input
                        id="municipality-search"
                        type="search"
                        role="searchbox"
                        aria-label="Search cities and municipalities"
                        autocomplete="off"
                        placeholder="Search city/municipality"
                        class="mb-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                    >
                    <div id="municipality-listbox" role="listbox" aria-label="Cities and municipalities" class="overflow-y-auto" style="max-height: 224px; overscroll-behavior: contain;"></div>
                </div>
                <p id="municipality-validation" class="mt-1 hidden text-xs text-red-600" aria-live="polite"></p>
            </div>

        </div>



        {{-- BARANGAY --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Barangay *
            </label>

            <select
                id="barangay"
                name="barangay"
                required
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
            >

                <option value="">Select city/municipality first</option>

            </select>

        </div>


        {{-- STREET ADDRESS --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Street Address *
            </label>

            <input
                type="text"
                name="address"
                value="{{ old('address') }}"
                required
                placeholder="Enter complete address"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>

        {{-- POSTAL CODE --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Postal Code *
            </label>

            <input
                type="text"
                name="postal_code"
                value="{{ old('postal_code') }}"
                required
                inputmode="numeric"
                pattern="[0-9]{4}"
                title="Postal code must contain exactly four digits."
                placeholder="0000"
                oninput="this.setCustomValidity(/[^0-9]/.test(this.value) ? 'Postal code must contain numbers only.' : '')"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>

    </div>


</div>





{{-- ==========================================
     BUSINESS INFORMATION
========================================== --}}
<div class="mt-8">

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Business Information
    </h3>


    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">


        {{-- BUSINESS NAME --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Business Name *
            </label>

            <input
                type="text"
                name="business_name"
                value="{{ old('business_name') }}"
                required
                placeholder="Business name"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>



        {{-- CATEGORY --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Line of Business *
            </label>

            <select
                name="business_category"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

                <option value="">
                    Select category
                </option>

                @foreach(array_keys(config('seller_categories')) as $businessCategory)

                    <option
                        value="{{ $businessCategory }}"
                        {{ old('business_category') === $businessCategory ? 'selected' : '' }}
                    >
                        {{ $businessCategory }}
                    </option>

                @endforeach

            </select>

        </div>

        {{-- SELLER TYPE --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Seller Type *
            </label>

            <select
                id="seller_type"
                name="seller_type"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

                <option value="">
                    Select seller type
                </option>

                @foreach ($sellerTypes as $sellerType)
                    <option
                        value="{{ $sellerType }}"
                        {{ old('seller_type') === $sellerType ? 'selected' : '' }}
                    >
                        {{ $sellerType }}
                    </option>
                @endforeach

            </select>

        </div>

        {{-- TIN --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                TIN *
            </label>

            <input
                type="text"
                name="tin"
                value="{{ old('tin') }}"
                required
                inputmode="numeric"
                maxlength="15"
                pattern="[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}"
                title="Enter exactly 12 digits in the format 000-000-000-000."
                placeholder="000-000-000-000"
                oninput="formatTinInput(this)"
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

        </div>


    </div>


</div>



{{-- ==========================================
     DOCUMENT UPLOAD
========================================== --}}
<div class="mt-8">

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Document Upload
    </h3>


    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

        @php
            $initialRequiredDocuments = $sellerDocumentRequirements[old('seller_type')]
                ?? ['government_id'];
        @endphp

        @foreach ($sellerDocumentLabels as $documentType => $documentLabel)
            @php
                $documentId = 'seller-document-' . $documentType;
                $requiredInitially = in_array(
                    $documentType,
                    $initialRequiredDocuments,
                    true
                );
                $alwaysVisible = $requiredInitially || $documentType === 'business_permit';
            @endphp

            <div
                data-seller-document-field="{{ $documentType }}"
                @class(['hidden' => ! $alwaysVisible])
                @if ($errors->has($documentType))
                    aria-invalid="true"
                @endif
            >
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    {{ $documentLabel }}
                    <span data-required-marker @class(['hidden' => ! $requiredInitially])>*</span>
                </label>

                <label class="flex min-h-44 w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-200 bg-white px-4 py-3 text-center cursor-pointer hover:border-[#1F6F5B] transition">
                    <i
                        id="{{ $documentId }}-icon"
                        data-lucide="upload"
                        class="w-6 h-6 text-gray-400 mb-2"
                    ></i>

                    <img
                        id="{{ $documentId }}-preview"
                        alt="Selected {{ $documentLabel }} preview"
                        class="hidden max-h-24 max-w-full rounded-lg border border-gray-100 object-contain"
                    >

                    <span class="text-sm font-medium text-gray-700">
                        Choose {{ strtolower($documentLabel) }} file
                    </span>

                    <span class="text-xs text-gray-400 mt-1">
                        JPG, PNG or PDF (maximum 5 MB)
                    </span>

                    <span
                        id="{{ $documentId }}-name"
                        class="mt-2 text-xs font-semibold text-[#1F6F5B]"
                    ></span>

                    <input
                        type="file"
                        id="{{ $documentType }}"
                        name="{{ $documentType }}"
                        accept="image/jpeg,image/png,application/pdf"
                        @disabled(! $alwaysVisible)
                        class="hidden"
                        onchange="validateSellerDocument(this)"
                    >
                </label>

                <p
                    data-document-error
                    class="mt-1 hidden text-xs text-red-600"
                    aria-live="polite"
                ></p>

                @error($documentType)
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

    </div>


</div>





{{-- ==========================================
     ADMIN APPROVAL NOTICE
========================================== --}}
<div class="rounded-xl border border-gray-200 bg-white px-4 py-4">


    <div class="flex gap-3">


        <i
            data-lucide="info"
            class="w-5 h-5 text-[#1F6F5B] shrink-0"
        ></i>


        <p class="text-xs text-gray-500 leading-relaxed">

            <span class="font-semibold text-gray-700">
                Administrator approval required.
            </span>

            After submitting your registration, your application will be reviewed by SUKI SHOP administrator.

        </p>


    </div>


</div>





{{-- ==========================================
     TERMS
========================================== --}}
<div class="flex items-start gap-3">


    <input
        type="checkbox"
        name="terms"
        value="1"
        required
        class="mt-1 h-4 w-4 rounded border-gray-300 text-[#1F6F5B]"
    >


    <p class="text-xs text-gray-500 leading-relaxed">

        I confirm that the information provided is accurate and complete,
        and I agree to SUKI SHOP Terms and Policies.

    </p>


</div>





{{-- ==========================================
     SUBMIT
========================================== --}}
<button
    type="submit"
    class="w-full rounded-xl bg-[#1F6F5B] py-3.5 text-sm font-semibold text-white hover:bg-[#155244] transition"
>
    Submit Registration →
</button>


                {{-- ==========================================
                     LOGIN DIVIDER
                =========================================== --}}
                <div class="flex items-center gap-4 my-7">

                    <div class="h-px flex-1 bg-gray-200"></div>

                    <span class="text-xs text-gray-400">
                        OR
                    </span>

                    <div class="h-px flex-1 bg-gray-200"></div>

                </div>



                {{-- ==========================================
                     LOGIN
                =========================================== --}}
                <p class="text-center text-sm text-gray-500">

                    Already have a seller account?

                    <a
                        href="{{ route('login') }}"
                        class="font-semibold text-[#1F6F5B] hover:underline"
                    >
                        Log In
                    </a>

                </p>



                {{-- ==========================================
                     BACK TO BUYER
                =========================================== --}}
                <div class="mt-5 text-center">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#1F6F5B] transition"
                    >

                        <i
                            data-lucide="arrow-left"
                            class="w-4 h-4"
                        ></i>

                        Back to SUKI SHOP Marketplace

                    </a>

                </div>

            </div>

        </section>

    </div>

</div>



{{-- ==========================================
     PASSWORD TOGGLE
=========================================== --}}
@push('scripts')

<script>

    const birthdayInput = document.querySelector('[name="birthday"]');
const ageInput = document.getElementById('age');


if (birthdayInput && ageInput) {

    birthdayInput.addEventListener('change', function () {

        const birthDate = new Date(this.value);
        const today = new Date();


        let age =
            today.getFullYear() - birthDate.getFullYear();


        const month =
            today.getMonth() - birthDate.getMonth();


        if (
            month < 0 ||
            (month === 0 && today.getDate() < birthDate.getDate())
        ) {
            age--;
        }


        ageInput.value = age;

    });

}

function showFileName(input, targetId, previewId, iconId) {

    const target = document.getElementById(targetId);
    const preview = document.getElementById(previewId);
    const icon = document.getElementById(iconId);

    if (!target || !preview || !icon) {
        return;
    }


    if (input.files.length > 0) {

        const file = input.files[0];
        target.textContent = file.name;

        if (preview.src.startsWith('blob:')) {
            URL.revokeObjectURL(preview.src);
        }

        if (file.type.startsWith('image/')) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
            icon.classList.add('hidden');
        } else {
            preview.removeAttribute('src');
            preview.classList.add('hidden');
            icon.classList.remove('hidden');
        }

    } else {

        target.textContent = '';

        if (preview.src.startsWith('blob:')) {
            URL.revokeObjectURL(preview.src);
        }

        preview.removeAttribute('src');
        preview.classList.add('hidden');
        icon.classList.remove('hidden');
    }

}

function validateSellerDocument(input) {
    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];
    const file = input.files[0];
    const error = input.closest('[data-seller-document-field]')
        ?.querySelector('[data-document-error]');

    if (!file || !error) {
        return;
    }

    let message = '';

    if (!allowedTypes.includes(file.type)) {
        message = 'Choose a JPG, PNG, or PDF file.';
    } else if (file.size > 5 * 1024 * 1024) {
        message = 'The file must not exceed 5 MB.';
    }

    if (message) {
        input.value = '';
        input.dataset.invalidUpload = 'true';
        error.textContent = message;
        error.classList.remove('hidden');
    } else {
        delete input.dataset.invalidUpload;
        error.textContent = '';
        error.classList.add('hidden');
    }

    const documentType = input.name;
    const documentId = `seller-document-${documentType}`;
    showFileName(
        input,
        `${documentId}-name`,
        `${documentId}-preview`,
        `${documentId}-icon`
    );
}

const sellerDocumentRequirements = @json($sellerDocumentRequirements);
const sellerTypeSelect = document.getElementById('seller_type');

function updateSellerDocumentFields() {
    const requiredDocuments = sellerDocumentRequirements[sellerTypeSelect.value] || [];

    document.querySelectorAll('[data-seller-document-field]').forEach((field) => {
        const documentType = field.dataset.sellerDocumentField;
        const fileInput = field.querySelector('input[type="file"]');
        const requiredMarker = field.querySelector('[data-required-marker]');
        const governmentId = documentType === 'government_id';
        const optionalPermit = documentType === 'business_permit';
        const visible = governmentId
            || optionalPermit
            || requiredDocuments.includes(documentType);
        const required = governmentId || requiredDocuments.includes(documentType);

        field.classList.toggle('hidden', !visible);
        fileInput.disabled = !visible;
        fileInput.required = false;
        fileInput.dataset.required = required ? 'true' : 'false';
        requiredMarker?.classList.toggle('hidden', !required);

        if (!visible) {
            if (fileInput.files.length > 0) {
                fileInput.value = '';
            }
            delete fileInput.dataset.invalidUpload;
            const error = field.querySelector('[data-document-error]');
            error.textContent = '';
            error.classList.add('hidden');

            const documentId = `seller-document-${documentType}`;
            showFileName(
                fileInput,
                `${documentId}-name`,
                `${documentId}-preview`,
                `${documentId}-icon`
            );
        }
    });
}

if (sellerTypeSelect) {
    sellerTypeSelect.addEventListener('change', updateSellerDocumentFields);
    updateSellerDocumentFields();

    sellerTypeSelect.form?.addEventListener('submit', (event) => {
        const form = sellerTypeSelect.form;
        const invalidUpload = form.querySelector(
            '[data-invalid-upload="true"]'
        );

        const missingRequiredUploads = [...form.querySelectorAll(
            '[data-seller-document-field] input[type="file"]'
        )].filter((input) => (
            !input.disabled
            && input.dataset.required === 'true'
            && input.files.length === 0
        ));

        missingRequiredUploads.forEach((input) => {
            const field = input.closest('[data-seller-document-field]');
            const label = field.querySelector('[data-required-marker]')
                ?.parentElement.textContent.replace(/\s*\*\s*$/, '').trim();
            const error = field.querySelector('[data-document-error]');

            error.textContent = `${label || 'This document'} is required for the selected seller type.`;
            error.classList.remove('hidden');
        });

        if (invalidUpload || missingRequiredUploads.length > 0) {
            event.preventDefault();
            const firstInvalidField = (
                invalidUpload
                    || missingRequiredUploads[0]
            ).closest('[data-seller-document-field]');
            firstInvalidField.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        }
    });
}

const PSGC_API_BASE = '/api/psgc';

const regionSelect = document.getElementById('region');
const provinceSelect = document.getElementById('province');
const municipalitySelect = document.getElementById('municipality');
const barangaySelect = document.getElementById('barangay');

const previousLocation = {
    region: @json(old('region')),
    province: @json(old('province')),
    municipality: @json(old('municipality')),
    barangay: @json(old('barangay')),
};

let regionLoadId = 0;
let municipalityLoadId = 0;
let barangayLoadId = 0;

function createAddressCombobox(select, inputId, listId, placeholder) {
    const trigger = document.getElementById(inputId.replace('-search', '-trigger'));
    const valueDisplay = document.getElementById(inputId.replace('-search', '-value'));
    const search = document.getElementById(inputId);
    const panel = document.getElementById(listId);
    const list = document.getElementById(listId.replace('-options', '-listbox'));
    const validation = document.getElementById(inputId.replace('-search', '-validation'));
    const wrapper = trigger.closest('[data-address-combobox]');
    let options = [];
    let activeIndex = -1;

    function close() {
        panel.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function updateActiveOption() {
        const visibleOptions = [...list.querySelectorAll('[role="option"]')];

        visibleOptions.forEach((option, index) => {
            const active = index === activeIndex;
            option.classList.toggle('bg-[#EEF8F3]', active);
            option.classList.toggle('text-[#1F6F5B]', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (visibleOptions[activeIndex]) {
            search.setAttribute('aria-activedescendant', visibleOptions[activeIndex].id);
            visibleOptions[activeIndex].scrollIntoView({ block: 'nearest' });
        } else {
            search.removeAttribute('aria-activedescendant');
        }
    }

    function renderOptions() {
        const query = search.value.trim().toLocaleLowerCase();
        const filteredOptions = options.filter((option) =>
            option.name.toLocaleLowerCase().includes(query)
        );

        list.innerHTML = '';

        if (filteredOptions.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.className = 'px-4 py-3 text-sm text-gray-500';
            emptyMessage.textContent = options.length === 0
                ? 'No options available.'
                : 'No matching results found.';
            list.appendChild(emptyMessage);
            activeIndex = -1;
            search.removeAttribute('aria-activedescendant');
            return;
        }

        filteredOptions.forEach((option, index) => {
            const optionElement = document.createElement('div');
            optionElement.id = `${list.id}-option-${index}`;
            optionElement.setAttribute('role', 'option');
            optionElement.setAttribute(
                'aria-selected',
                select.value === option.code ? 'true' : 'false'
            );
            optionElement.className = 'cursor-pointer px-4 py-2.5 text-sm text-gray-700 hover:bg-[#EEF8F3] hover:text-[#1F6F5B]';
            optionElement.textContent = option.name;
            optionElement.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                selectOption(option);
            });
            list.appendChild(optionElement);
        });

        if (activeIndex >= filteredOptions.length) {
            activeIndex = filteredOptions.length - 1;
        }
        updateActiveOption();
    }

    function open() {
        if (trigger.disabled) {
            return;
        }

        search.value = '';
        renderOptions();
        panel.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        search.focus();
    }

    function selectOption(option) {
        select.value = option.code;
        valueDisplay.textContent = option.name;
        valueDisplay.classList.remove('text-gray-500');
        validation.textContent = '';
        validation.classList.add('hidden');
        close();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        trigger.focus();
    }

    trigger.addEventListener('click', open);
    trigger.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
            event.preventDefault();
            close();
        }
    });
    search.addEventListener('input', renderOptions);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const visibleOptions = list.querySelectorAll('[role="option"]');
            if (visibleOptions.length > 0) {
                const direction = event.key === 'ArrowDown' ? 1 : -1;
                activeIndex = activeIndex < 0
                    ? (direction > 0 ? 0 : visibleOptions.length - 1)
                    : (activeIndex + direction + visibleOptions.length) % visibleOptions.length;
                updateActiveOption();
            }
        } else if (event.key === 'Enter' && !panel.classList.contains('hidden')) {
            event.preventDefault();
            const filteredOptions = options.filter((option) =>
                option.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())
            );
            if (filteredOptions[activeIndex]) {
                selectOption(filteredOptions[activeIndex]);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
            trigger.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!wrapper.contains(event.target)) {
            close();
            if (document.activeElement === search) {
                search.blur();
            }
        }
    });

    return {
        setOptions(items, optionPlaceholder = placeholder) {
            options = items.map((item) => ({
                code: item.code,
                name: item.name.trim(),
            }));
            select.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = optionPlaceholder;
            select.appendChild(placeholderOption);

            options.forEach((option) => {
                const nativeOption = document.createElement('option');
                nativeOption.value = option.code;
                nativeOption.textContent = option.name;
                select.appendChild(nativeOption);
            });

            select.value = '';
            valueDisplay.textContent = optionPlaceholder;
            valueDisplay.classList.add('text-gray-500');
            search.placeholder = `Search ${optionPlaceholder.toLocaleLowerCase().replace(/^select /, '')}`;
            validation.textContent = '';
            validation.classList.add('hidden');
            renderOptions();
        },
        setState(message, disabled) {
            options = [];
            select.innerHTML = '';
            valueDisplay.textContent = message;
            valueDisplay.classList.add('text-gray-500');
            trigger.disabled = disabled;
            select.disabled = disabled;
            close();
        },
        setValue(code) {
            select.value = code;
            valueDisplay.textContent = select.selectedOptions[0]?.textContent || placeholder;
            valueDisplay.classList.toggle('text-gray-500', !select.value);
            validation.textContent = '';
            validation.classList.add('hidden');
        },
        setDisabled(disabled) {
            trigger.disabled = disabled;
            select.disabled = disabled;
            if (disabled) {
                close();
            }
        },
        validate() {
            const valid = Boolean(select.value);
            validation.textContent = valid ? '' : `${placeholder} is required.`;
            validation.classList.toggle('hidden', valid);
            trigger.setAttribute('aria-invalid', valid ? 'false' : 'true');
            if (!valid && !trigger.disabled) {
                trigger.focus();
            }
            return valid;
        },
    };
}

const regionCombobox = createAddressCombobox(
    regionSelect,
    'region-search',
    'region-options',
    'Select region'
);
const municipalityCombobox = createAddressCombobox(
    municipalitySelect,
    'municipality-search',
    'municipality-options',
    'Select city/municipality'
);

function setSelectOptions(select, placeholder, items) {
    if (select === regionSelect) {
        regionCombobox.setOptions(items, placeholder);
        return;
    }

    if (select === municipalitySelect) {
        municipalityCombobox.setOptions(items, placeholder);
        return;
    }

    select.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.code;
        option.textContent = item.name;
        select.appendChild(option);
    });
}

async function fetchPsgcOptions(path) {
    const response = await fetch(`${PSGC_API_BASE}/${path}`);

    if (!response.ok) {
        throw new Error(`Unable to load address data (${response.status}).`);
    }

    const items = await response.json();

    if (!Array.isArray(items)) {
        throw new Error('The address service returned invalid data.');
    }

    return items;
}

function resetDependentAddressSelects() {
    municipalityLoadId++;
    barangayLoadId++;

    provinceSelect.innerHTML = '<option value="">Select region first</option>';
    provinceSelect.disabled = true;
    provinceSelect.required = false;

    municipalityCombobox.setState('Select province first', true);

    barangaySelect.innerHTML = '<option value="">Select city/municipality first</option>';
    barangaySelect.disabled = true;
}

async function loadRegions(selectedRegion = '') {
    const loadId = ++regionLoadId;

    resetDependentAddressSelects();
    regionCombobox.setState('Loading regions...', true);

    try {
        const regions = await fetchPsgcOptions('regions');

        if (loadId !== regionLoadId) {
            return;
        }

        setSelectOptions(regionSelect, 'Select region', regions);
        regionCombobox.setDisabled(false);

        if (selectedRegion && regions.some((region) => region.code === selectedRegion)) {
            regionCombobox.setValue(selectedRegion);
            await loadProvinces(
                selectedRegion,
                previousLocation.province,
                previousLocation.municipality,
                previousLocation.barangay
            );
        }
    } catch (error) {
        if (loadId === regionLoadId) {
            regionCombobox.setState('Unable to load regions', true);
            console.error(error);
        }
    }
}

async function loadProvinces(
    regionCode,
    selectedProvince = '',
    selectedMunicipality = '',
    selectedBarangay = ''
) {
    const loadId = ++regionLoadId;
    resetDependentAddressSelects();

    if (!regionCode) {
        provinceSelect.innerHTML = '<option value="">Select region first</option>';
        return;
    }

    provinceSelect.innerHTML = '<option value="">Loading provinces...</option>';

    try {
        const provinces = await fetchPsgcOptions(
            `regions/${encodeURIComponent(regionCode)}/provinces`
        );

        if (loadId !== regionLoadId) {
            return;
        }

        if (provinces.length === 0) {
            provinceSelect.innerHTML = '<option value="">No provinces in this region</option>';
            provinceSelect.disabled = true;
            provinceSelect.required = false;
            await loadMunicipalities(
                '',
                regionCode,
                selectedMunicipality,
                selectedBarangay
            );
            return;
        }

        setSelectOptions(provinceSelect, 'Select province', provinces);
        provinceSelect.required = true;
        provinceSelect.disabled = false;

        if (selectedProvince && provinces.some((province) => province.code === selectedProvince)) {
            provinceSelect.value = selectedProvince;
            await loadMunicipalities(
                selectedProvince,
                '',
                selectedMunicipality,
                selectedBarangay
            );
        }
    } catch (error) {
        if (loadId === regionLoadId) {
            provinceSelect.innerHTML = '<option value="">Unable to load provinces</option>';
            provinceSelect.disabled = true;
            console.error(error);
        }
    }
}

async function loadMunicipalities(
    parentCode,
    regionCode = '',
    selectedMunicipality = '',
    selectedBarangay = ''
) {
    const loadId = ++municipalityLoadId;

    municipalityCombobox.setState('Loading cities/municipalities...', true);
    barangaySelect.innerHTML = '<option value="">Select city/municipality first</option>';
    barangaySelect.disabled = true;
    barangayLoadId++;

    const path = parentCode
        ? `provinces/${encodeURIComponent(parentCode)}/cities-municipalities`
        : `regions/${encodeURIComponent(regionCode)}/cities-municipalities`;

    try {
        const municipalities = await fetchPsgcOptions(path);

        if (loadId !== municipalityLoadId) {
            return;
        }

        setSelectOptions(municipalitySelect, 'Select city/municipality', municipalities);
        municipalityCombobox.setDisabled(false);

        if (
            selectedMunicipality &&
            municipalities.some((municipality) => municipality.code === selectedMunicipality)
        ) {
            municipalityCombobox.setValue(selectedMunicipality);
            await loadBarangays(selectedMunicipality, selectedBarangay);
        }
    } catch (error) {
        if (loadId === municipalityLoadId) {
            municipalityCombobox.setState(
                'Unable to load cities/municipalities',
                false
            );
            console.error(error);
        }
    }
}

async function loadBarangays(municipalityCode, selectedBarangay = '') {
    const loadId = ++barangayLoadId;

    barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
    barangaySelect.disabled = true;

    try {
        const barangays = await fetchPsgcOptions(
            `cities-municipalities/${encodeURIComponent(municipalityCode)}/barangays`
        );

        if (loadId !== barangayLoadId) {
            return;
        }

        setSelectOptions(barangaySelect, 'Select barangay', barangays);
        barangaySelect.disabled = false;

        if (selectedBarangay && barangays.some((barangay) => barangay.code === selectedBarangay)) {
            barangaySelect.value = selectedBarangay;
        }
    } catch (error) {
        if (loadId === barangayLoadId) {
            barangaySelect.innerHTML = '<option value="">Unable to load barangays</option>';
            console.error(error);
        }
    }
}

if (regionSelect && provinceSelect && municipalitySelect && barangaySelect) {
    regionSelect.addEventListener('change', function () {
        loadProvinces(this.value);
    });

    provinceSelect.addEventListener('change', function () {
        if (this.value) {
            loadMunicipalities(this.value);
        } else {
            municipalityLoadId++;
            barangayLoadId++;
            municipalityCombobox.setState('Select province first', true);
            barangaySelect.innerHTML = '<option value="">Select city/municipality first</option>';
            barangaySelect.disabled = true;
        }
    });

    municipalitySelect.addEventListener('change', function () {
        if (this.value) {
            loadBarangays(this.value);
        } else {
            barangayLoadId++;
            barangaySelect.innerHTML = '<option value="">Select city/municipality first</option>';
            barangaySelect.disabled = true;
        }
    });

    regionSelect.closest('form').addEventListener('submit', (event) => {
        const regionValid = regionCombobox.validate();
        const municipalityValid = municipalityCombobox.validate();

        if (!regionValid || !municipalityValid) {
            event.preventDefault();
        }
    });

    loadRegions(previousLocation.region || '');
}

function togglePassword(inputId, iconId) {

    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);

    if (!input || !icon) {
        return;
    }

    if (input.type === 'password') {

        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');

    } else {

        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');

    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

}

function formatTinInput(input) {

    const containsInvalidCharacters = /[^0-9-]/.test(input.value);
    const digits = input.value.replace(/\D/g, '').slice(0, 12);
    const groups = digits.match(/.{1,3}/g) || [];

    input.value = groups.join('-');
    input.setCustomValidity(
        containsInvalidCharacters
            ? 'TIN can contain numbers only.'
            : ''
    );

}

</script>

@endpush

</form>

@endsection