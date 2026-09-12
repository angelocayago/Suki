@extends('layouts.app')

@section('content')

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



                {{-- ==========================================
                     REGISTRATION FORM
                =========================================== --}}
                <form
                    method="POST"
                    action="{{ route('seller.register.submit') }}"
                    class="space-y-5"
                >

                    @csrf



                    {{-- ==========================================
                         SHOP INFORMATION
                    =========================================== --}}
                    <div>

                        <h3 class="text-sm font-semibold text-gray-900 mb-4">
                            Shop Information
                        </h3>


                        {{-- SHOP NAME --}}
                        <div>

                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Shop Name
                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="store"
                                    class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                ></i>

                                <input
                                    type="text"
                                    name="shop_name"
                                    value="{{ old('shop_name') }}"
                                    required
                                    placeholder="Enter your shop name"
                                    class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >

                            </div>

                        </div>

                    </div>



                    {{-- ==========================================
                         SELLER INFORMATION
                    =========================================== --}}
                    <div>

                        <h3 class="text-sm font-semibold text-gray-900 mb-4">
                            Seller Information
                        </h3>


                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">


                            {{-- SELLER NAME --}}
                            <div>

                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Seller Name
                                </label>

                                <div class="relative">

                                    <i
                                        data-lucide="user"
                                        class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                    ></i>

                                    <input
                                        type="text"
                                        name="seller_name"
                                        value="{{ old('seller_name') }}"
                                        required
                                        placeholder="Full name"
                                        class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    >

                                </div>

                            </div>



                            {{-- PHONE --}}
                            <div>

                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number
                                </label>

                                <div class="relative">

                                    <i
                                        data-lucide="phone"
                                        class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                    ></i>

                                    <input
                                        type="text"
                                        name="phone"
                                        value="{{ old('phone') }}"
                                        required
                                        placeholder="09XXXXXXXXX"
                                        class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    >

                                </div>

                            </div>

                        </div>

                    </div>



                    {{-- ==========================================
                         EMAIL
                    =========================================== --}}
                    <div>

                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="mail"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                            ></i>

                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                placeholder="seller@example.com"
                                class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>

                    </div>



                    {{-- ==========================================
                         ADDRESS
                    =========================================== --}}
                    <div>

                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Business / Pickup Address
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="map-pin"
                                class="absolute left-4 top-4 w-5 h-5 text-gray-400"
                            ></i>

                            <textarea
                                name="address"
                                rows="3"
                                required
                                placeholder="Enter your complete business or pickup address"
                                class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm outline-none transition resize-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >{{ old('address') }}</textarea>

                        </div>

                    </div>



                    {{-- ==========================================
                         PASSWORDS
                    =========================================== --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">


                        {{-- PASSWORD --}}
                        <div>

                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="lock-keyhole"
                                    class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                ></i>

                                <input
                                    type="password"
                                    name="password"
                                    id="sellerPassword"
                                    required
                                    placeholder="Minimum 8 characters"
                                    class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-12 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >

                                <button
                                    type="button"
                                    onclick="togglePassword('sellerPassword', 'sellerPasswordIcon')"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B] transition"
                                >

                                    <i
                                        data-lucide="eye"
                                        id="sellerPasswordIcon"
                                        class="w-5 h-5"
                                    ></i>

                                </button>

                            </div>

                        </div>



                        {{-- CONFIRM PASSWORD --}}
                        <div>

                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password
                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="lock-keyhole"
                                    class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                ></i>

                                <input
                                    type="password"
                                    name="password_confirmation"
                                    id="sellerPasswordConfirm"
                                    required
                                    placeholder="Re-enter password"
                                    class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-12 py-3.5 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >

                                <button
                                    type="button"
                                    onclick="togglePassword('sellerPasswordConfirm', 'sellerPasswordConfirmIcon')"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B] transition"
                                >

                                    <i
                                        data-lucide="eye"
                                        id="sellerPasswordConfirmIcon"
                                        class="w-5 h-5"
                                    ></i>

                                </button>

                            </div>

                        </div>

                    </div>



                    {{-- ==========================================
                         TERMS
                    =========================================== --}}
                    <div class="flex items-start gap-3 pt-1">

                        <input
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                            class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                        >

                        <p class="text-xs leading-relaxed text-gray-500">

                            I agree to SUKI SHOP's

                            <span class="font-medium text-gray-700">
                                Terms of Service
                            </span>

                            and

                            <span class="font-medium text-gray-700">
                                Seller Policies
                            </span>.

                        </p>

                    </div>



                    {{-- ==========================================
                         SUBMIT
                    =========================================== --}}
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-[#1F6F5B] py-3.5 text-sm font-semibold text-white hover:bg-[#155244] active:scale-[0.99] transition"
                    >
                        CREATE SELLER ACCOUNT
                    </button>

                </form>



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

@endsection