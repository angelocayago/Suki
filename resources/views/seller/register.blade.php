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
            type="text"
            name="phone"
            required
            placeholder="09XXXXXXXXX"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
        >

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

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Region *
            </label>

            <select
                id="region"
                name="region"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >
                <option value="">Loading regions...</option>
            </select>

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

            <label class="block text-sm font-medium text-gray-700 mb-2">
                City / Municipality *
            </label>

            <select
                id="municipality"
                name="municipality"
                required
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
            >

                <option value="">Select province first</option>

            </select>

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


        {{-- VALID ID --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2">
                Upload Valid ID
            </label>


            <label class="flex min-h-44 w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-200 bg-white px-4 py-3 text-center cursor-pointer hover:border-[#1F6F5B] transition">


                <i
                    id="validIdUploadIcon"
                    data-lucide="upload"
                    class="w-6 h-6 text-gray-400 mb-2"
                ></i>

                <img
                    id="validIdPreview"
                    alt="Selected valid ID preview"
                    class="hidden max-h-24 max-w-full rounded-lg border border-gray-100 object-contain"
                >

                <span class="text-sm font-medium text-gray-700">
    Choose a valid ID file
</span>


<span class="text-xs text-gray-400 mt-1">
    JPG, JPEG, PNG or PDF
</span>


<span
    id="validIdName"
    class="mt-2 text-xs font-semibold text-[#1F6F5B]"
>
</span>


                <input
    type="file"
    id="valid_id"
    name="valid_id"
    required
    accept="image/jpeg,image/png,application/pdf"
    class="hidden"
    onchange="showFileName(this, 'validIdName', 'validIdPreview', 'validIdUploadIcon')"
>


            </label>


        </div>





        {{-- BUSINESS PERMIT --}}
        <div>


            <label class="block text-sm font-medium text-gray-700 mb-2">
                Upload Business Permit
            </label>


            <label class="flex min-h-44 w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-gray-200 bg-white px-4 py-3 text-center cursor-pointer hover:border-[#1F6F5B] transition">


                <i
                    id="permitUploadIcon"
                    data-lucide="upload"
                    class="w-6 h-6 text-gray-400 mb-2"
                ></i>

                <img
                    id="permitPreview"
                    alt="Selected business permit preview"
                    class="hidden max-h-24 max-w-full rounded-lg border border-gray-100 object-contain"
                >

                <span class="text-sm font-medium text-gray-700">
    Choose a business permit file
</span>


<span class="text-xs text-gray-400 mt-1">
    JPG, JPEG, PNG or PDF
</span>


<span
    id="permitName"
    class="mt-2 text-xs font-semibold text-[#1F6F5B]"
>
</span>


<input
    type="file"
    id="business_permit"
    name="business_permit"
    required
    accept="image/jpeg,image/png,application/pdf"
    class="hidden"
    onchange="showFileName(this, 'permitName', 'permitPreview', 'permitUploadIcon')"
>


            </label>


        </div>


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

function setSelectOptions(select, placeholder, items) {
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

    municipalitySelect.innerHTML = '<option value="">Select province first</option>';
    municipalitySelect.disabled = true;

    barangaySelect.innerHTML = '<option value="">Select city/municipality first</option>';
    barangaySelect.disabled = true;
}

async function loadRegions(selectedRegion = '') {
    const loadId = ++regionLoadId;

    resetDependentAddressSelects();
    regionSelect.disabled = true;
    regionSelect.innerHTML = '<option value="">Loading regions...</option>';

    try {
        const regions = await fetchPsgcOptions('regions');

        if (loadId !== regionLoadId) {
            return;
        }

        setSelectOptions(regionSelect, 'Select region', regions);
        regionSelect.disabled = false;

        if (selectedRegion && regions.some((region) => region.code === selectedRegion)) {
            regionSelect.value = selectedRegion;
            await loadProvinces(
                selectedRegion,
                previousLocation.province,
                previousLocation.municipality,
                previousLocation.barangay
            );
        }
    } catch (error) {
        if (loadId === regionLoadId) {
            regionSelect.innerHTML = '<option value="">Unable to load regions</option>';
            regionSelect.disabled = true;
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

    municipalitySelect.innerHTML = '<option value="">Loading cities/municipalities...</option>';
    municipalitySelect.disabled = true;
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
        municipalitySelect.disabled = false;

        if (
            selectedMunicipality &&
            municipalities.some((municipality) => municipality.code === selectedMunicipality)
        ) {
            municipalitySelect.value = selectedMunicipality;
            await loadBarangays(selectedMunicipality, selectedBarangay);
        }
    } catch (error) {
        if (loadId === municipalityLoadId) {
            municipalitySelect.innerHTML = '<option value="">Unable to load cities/municipalities</option>';
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
            municipalitySelect.innerHTML = '<option value="">Select province first</option>';
            municipalitySelect.disabled = true;
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