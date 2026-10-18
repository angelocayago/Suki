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

    <div class="grid min-h-screen grid-cols-1">


        {{-- ==========================================
             LEFT BRANDING
        =========================================== --}}
        <section class="hidden" aria-hidden="true">

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
                <div class="flex justify-center mb-7 sm:mb-8">

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
                value="{{ old('email', data_get(session('google_registration'), 'email')) }}"
                @if(session('google_registration')) readonly @endif
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
        minlength="8"
        autocomplete="new-password"
        aria-describedby="password-requirements"
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

                <p id="password-requirements" class="mt-1 text-xs text-gray-500">Use at least 8 characters with uppercase and lowercase letters, a number, and a special character.</p>
                @error('password')
                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                @enderror

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
        minlength="8"
        autocomplete="new-password"
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

                @error('password_confirmation')
                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                @enderror

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
                value="{{ old('first_name', data_get(session('google_registration'), 'given_name')) }}"
                autocomplete="given-name"
                pattern="[A-Za-zÀ-ÿÑñ' -]+"
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
                value="{{ old('last_name', data_get(session('google_registration'), 'family_name')) }}"
                autocomplete="family-name"
                pattern="[A-Za-zÀ-ÿÑñ' -]+"
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
                value="{{ old('middle_initial') }}"
                maxlength="1"
                pattern="[A-Za-zÀ-ÿÑñ]"
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

                <option value="Male" @selected(old('sex') === 'Male')>
                    Male
                </option>

                <option value="Female" @selected(old('sex') === 'Female')>
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
                value="{{ old('birthday') }}"
                max="{{ now()->subYears(18)->toDateString() }}"
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
            value="{{ old('phone') }}"
            inputmode="numeric"
            maxlength="11"
            pattern="09[0-9]{9}"
            required
            placeholder="09XXXXXXXXX"
            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
        >
        <p class="mt-1 text-xs text-gray-500">Numbers only. Use 11 digits starting with 09.</p>

    </div>


</div>

{{-- ==========================================
     ADDRESS
========================================== --}}
<div class="mt-8">

    <h3 class="text-sm font-semibold text-gray-900 mb-4">
        Address
    </h3>


    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- REGION --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2" for="region">
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

            @error('region')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

        </div>


        {{-- PROVINCE --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2" for="province">
                Province <span id="province-required">*</span>
            </label>

            <select
                id="province"
                name="province"
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

                <option value="">
                    Select region first
                </option>

            </select>

            @error('province')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

        </div>



        {{-- MUNICIPALITY --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2" for="municipality">
                Municipality/City *
            </label>

            <select
                id="municipality"
                name="municipality"
                required
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
            >

                <option value="">
                    Select province first
                </option>

            </select>

            @error('municipality')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

        </div>



        {{-- BARANGAY --}}
        <div>

            <label class="block text-sm font-medium text-gray-700 mb-2" for="barangay">
                Barangay *
            </label>

            <select
                id="barangay"
                name="barangay"
                required
                disabled
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
            >

                <option value="">
                    Select municipality/city first
                </option>

            </select>

            @error('barangay')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror

        </div>


    </div>



    {{-- STREET --}}
    <div class="mt-4">

        <label class="block text-sm font-medium text-gray-700 mb-2">
            Street / House No. / Building *
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
                Line of Business (Category) *
            </label>

            <select
                name="category_id"
                required
                class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
            >

                <option value="">
                    Select category
                </option>

                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach

            </select>

        </div>


    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">

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
                <option value="">Select seller type</option>
                <option value="individual" @selected(old('seller_type') === 'individual')>Individual</option>
                <option value="business" @selected(old('seller_type') === 'business')>Registered Business</option>
            </select>
        </div>

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
                placeholder="123-456-789-000"
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


            <label class="flex h-36 w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white cursor-pointer hover:border-[#1F6F5B] transition">


                <i
                    data-lucide="upload"
                    class="w-6 h-6 text-gray-400 mb-2"
                ></i>


                <span class="text-sm font-medium text-gray-700">
    Upload your valid ID
</span>


<span class="text-xs text-gray-400 mt-1">
    JPG, JPEG, PNG or PDF · Maximum 5 MB
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
    accept=".jpg,.jpeg,.png,.pdf"
    class="hidden"
    onchange="showFileName(this, 'validIdName')"
>


            </label>


        </div>





        {{-- BUSINESS PERMIT --}}
        <div id="businessPermitField">


            <label class="block text-sm font-medium text-gray-700 mb-2">
                Upload Business Permit <span id="businessPermitRequired">*</span>
            </label>


            <label class="flex h-36 w-full flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 bg-white cursor-pointer hover:border-[#1F6F5B] transition">


                <i
                    data-lucide="upload"
                    class="w-6 h-6 text-gray-400 mb-2"
                ></i>


                <span class="text-sm font-medium text-gray-700">
    Upload business permit
</span>


<span class="text-xs text-gray-400 mt-1">
    JPG, JPEG, PNG or PDF · Maximum 5 MB
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
    accept=".jpg,.jpeg,.png,.pdf"
    class="hidden"
    onchange="showFileName(this, 'permitName')"
>


            </label>


        </div>


    </div>


</div>





{{-- ==========================================
     ADMIN APPROVAL NOTICE
========================================== --}}
<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4">


    <div class="flex gap-3">


        <i
            data-lucide="info"
            class="w-5 h-5 text-[#1F6F5B] shrink-0"
        ></i>


        <p class="text-xs text-gray-500 leading-relaxed">

            <span class="font-semibold text-gray-700">
                Administrator approval required.
            </span>

            Your account will remain pending and seller portal access will stay disabled until an administrator reviews your information and private documents. You will see the decision when you next sign in.

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
    Submit for Admin Review
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

function showFileName(input, targetId) {

    const target = document.getElementById(targetId);

    if (!target) {
        return;
    }


    if (input.files.length > 0) {

        const file = input.files[0];
        const sizeInMb = file.size / (1024 * 1024);

        target.textContent = `${file.name} (${sizeInMb.toFixed(1)} MB)`;
        target.classList.toggle('text-red-600', sizeInMb > 5);
        target.classList.toggle('text-[#1F6F5B]', sizeInMb <= 5);

        if (sizeInMb > 5) {
            target.textContent += ' — file exceeds the 5 MB limit';
        }

    }

}

const sellerTypeSelect = document.getElementById('seller_type');
const businessPermitField = document.getElementById('businessPermitField');
const businessPermitInput = document.getElementById('business_permit');
const businessPermitRequired = document.getElementById('businessPermitRequired');

function syncSellerDocumentFields() {
    if (!sellerTypeSelect || !businessPermitField || !businessPermitInput) {
        return;
    }

    const requiresPermit = sellerTypeSelect.value === 'business';
    businessPermitField.classList.toggle('hidden', !requiresPermit);
    businessPermitInput.required = requiresPermit;

    if (businessPermitRequired) {
        businessPermitRequired.classList.toggle('hidden', !requiresPermit);
    }
}

if (sellerTypeSelect) {
    sellerTypeSelect.addEventListener('change', syncSellerDocumentFields);
    syncSellerDocumentFields();
}


// The browser uses narrow Laravel routes only. PSGC provider access and
// hierarchy validation remain centralized in PhilippineAddressService.
const ADDRESS_API_BASE = '/address';
const regionSelect = document.getElementById('region');
const provinceSelect = document.getElementById('province');
const municipalitySelect = document.getElementById('municipality');
const barangaySelect = document.getElementById('barangay');
const oldRegion = @json(old('region'));
const oldProvince = @json(old('province'));
const oldMunicipality = @json(old('municipality'));
const oldBarangay = @json(old('barangay'));
const provinceRequired = document.getElementById('province-required');

function resetSelect(select, placeholder) {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
}

function setOptions(select, placeholder, items, selected = '') {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    items.sort((a, b) => a.name.localeCompare(b.name)).forEach((item) => {
        const option = document.createElement('option');
        option.value = item.code;
        option.textContent = item.name;
        option.selected = item.code === selected;
        select.appendChild(option);
    });
    select.disabled = items.length === 0;
}

async function fetchItems(path) {
    const response = await fetch(`${ADDRESS_API_BASE}/${path}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Address request failed: ${response.status}`);
    const payload = await response.json();
    return Array.isArray(payload) ? payload : [];
}

function resetProvince() {
    resetSelect(provinceSelect, 'Select province');
    provinceSelect.required = false;
    provinceRequired.classList.add('hidden');
}

function resetMunicipalities() {
    resetSelect(municipalitySelect, 'Select municipality/city');
}

function resetBarangays() {
    resetSelect(barangaySelect, 'Select barangay');
}

async function loadBarangays(cityCode, restore = false) {
    resetBarangays();
    barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
    try {
        setOptions(barangaySelect, 'Select barangay', await fetchItems(`cities-municipalities/${encodeURIComponent(cityCode)}/barangays`), restore ? oldBarangay : '');
    } catch (_) {
        resetSelect(barangaySelect, 'Unable to load barangays. Please retry.');
    }
}

async function loadMunicipalities(parentCode, isRegion = false, restore = false) {
    resetMunicipalities();
    resetBarangays();
    municipalitySelect.innerHTML = '<option value="">Loading municipalities/cities...</option>';
    try {
        const parent = isRegion ? 'regions' : 'provinces';
        setOptions(municipalitySelect, 'Select municipality/city', await fetchItems(`${parent}/${encodeURIComponent(parentCode)}/cities-municipalities`), restore ? oldMunicipality : '');
        if (restore && oldMunicipality) await loadBarangays(oldMunicipality, true);
    } catch (_) {
        resetSelect(municipalitySelect, 'Unable to load municipalities/cities. Please retry.');
    }
}

async function loadRegionChildren(regionCode, restore = false) {
    resetProvince();
    resetMunicipalities();
    resetBarangays();
    provinceSelect.innerHTML = '<option value="">Loading provinces...</option>';
    try {
        const provinces = await fetchItems(`regions/${encodeURIComponent(regionCode)}/provinces`);
        if (provinces.length > 0) {
            setOptions(provinceSelect, 'Select province', provinces, restore ? oldProvince : '');
            provinceSelect.required = true;
            provinceRequired.classList.remove('hidden');
            if (restore && oldProvince) await loadMunicipalities(oldProvince, false, true);
            return;
        }

        resetProvince();
        await loadMunicipalities(regionCode, true, restore);
    } catch (_) {
        resetSelect(provinceSelect, 'Unable to load provinces. Please retry.');
    }
}

async function loadRegions() {
    regionSelect.disabled = true;
    try {
        setOptions(regionSelect, 'Select region', await fetchItems('regions'), oldRegion);
        if (oldRegion) await loadRegionChildren(oldRegion, true);
    } catch (_) {
        resetSelect(regionSelect, 'Unable to load regions. Please retry.');
        regionSelect.disabled = false;
    }
}

if (regionSelect && provinceSelect && municipalitySelect && barangaySelect) {
    regionSelect.addEventListener('change', () => {
        if (!regionSelect.value) {
            resetProvince(); resetMunicipalities(); resetBarangays();
            return;
        }
        loadRegionChildren(regionSelect.value);
    });
    provinceSelect.addEventListener('change', () => {
        resetMunicipalities(); resetBarangays();
        if (provinceSelect.value) loadMunicipalities(provinceSelect.value);
    });
    municipalitySelect.addEventListener('change', () => {
        resetBarangays();
        if (municipalitySelect.value) loadBarangays(municipalitySelect.value);
    });
    loadRegions();
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

</script>

@endpush

</form>

<div class="mt-6 text-center text-sm text-gray-500">or</div>
<a class="mt-3 block rounded border px-4 py-3 text-center font-semibold" href="{{ route('auth.google.redirect', ['intent' => 'seller']) }}"><span aria-hidden="true">G</span> Continue with Google</a>

@endsection
