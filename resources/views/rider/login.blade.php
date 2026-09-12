@extends('layouts.rider-guest')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">


    <!-- =====================================================
    | PUBLIC RIDER NAVIGATION
    ====================================================== -->
    <header class="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3 sm:px-8">


            <!-- =====================================================
            | SUKI SHOP RIDER BRAND
            ====================================================== -->
            <a
                href="{{ route('buyer.home') }}"
                class="flex items-center gap-3"
            >

                <img
                    src="{{ asset('images/suki-rider.jpg') }}"
                    alt="SUKI SHOP Rider"
                    class="h-10 w-auto object-contain sm:h-11"
                >


                <div class="hidden sm:block">

                    <div class="flex items-center gap-2">

                        <p class="text-sm font-bold tracking-tight text-gray-900">
                            SUKI SHOP Rider
                        </p>


                        <span class="rounded-full bg-[#E6F4EE] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-[#1F6F5B]">
                            Portal
                        </span>

                    </div>


                    <p class="text-xs text-gray-500">
                        Delivery Partner
                    </p>

                </div>

            </a>



            <!-- =====================================================
            | PUBLIC NAVIGATION
            ====================================================== -->
            <nav class="flex items-center gap-2 sm:gap-3">


                <!-- MARKETPLACE -->
                <a
                    href="{{ route('buyer.home') }}"
                    class="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B] sm:flex"
                >

                    <i
                        data-lucide="store"
                        class="h-4 w-4"
                    ></i>

                    Marketplace

                </a>



                <!-- APPLY AS RIDER -->
                <a
                    href="{{ route('rider.apply') }}"
                    class="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-[#F4F8F6] hover:text-[#1F6F5B] md:flex"
                >

                    <i
                        data-lucide="file-plus-2"
                        class="h-4 w-4"
                    ></i>

                    Apply as Rider

                </a>



                <!-- DIVIDER -->
                <div class="hidden h-6 w-px bg-gray-200 sm:block"></div>



                <!-- BACK TO SUKI SHOP -->
                <a
                    href="{{ route('buyer.home') }}"
                    class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:px-4"
                >

                    <i
                        data-lucide="arrow-left"
                        class="h-4 w-4"
                    ></i>

                    <span class="hidden sm:inline">
                        Back to SUKI SHOP
                    </span>

                </a>

            </nav>

        </div>

    </header>



    <!-- =====================================================
    | MAIN LOGIN AREA
    ====================================================== -->
    <main class="flex min-h-[calc(100vh-73px)] items-center justify-center px-5 py-10 sm:px-8">

        <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm lg:grid-cols-2">


            <!-- =====================================================
            | LEFT SIDE - RIDER INFORMATION
            ====================================================== -->
            <div class="hidden bg-[#1F6F5B] p-10 text-white lg:flex lg:flex-col lg:justify-between">


                <!-- TOP CONTENT -->
                <div>


                    <!-- RIDER BRAND -->
                    <div class="flex items-center gap-3">

                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10">

                            <i
                                data-lucide="bike"
                                class="h-7 w-7"
                            ></i>

                        </div>


                        <div>

                            <p class="text-lg font-bold">
                                SUKI SHOP Rider
                            </p>

                            <p class="text-sm text-white/70">
                                Delivery Partner Portal
                            </p>

                        </div>

                    </div>



                    <!-- HERO TEXT -->
                    <h1 class="mt-10 text-3xl font-bold leading-tight">

                        Deliver with SUKI SHOP.

                    </h1>


                    <p class="mt-4 max-w-sm text-sm leading-6 text-white/80">

                        Access your rider dashboard, manage pickup and delivery
                        assignments, track completed deliveries, and monitor
                        your earnings.

                    </p>

                </div>



                <!-- FEATURES -->
                <div class="mt-12 space-y-4">


                    <!-- FEATURE 1 -->
                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">

                            <i
                                data-lucide="package-check"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-medium text-white">
                                Manage Assignments
                            </p>

                            <p class="text-xs text-white/65">
                                View your pickup and delivery tasks.
                            </p>

                        </div>

                    </div>



                    <!-- FEATURE 2 -->
                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">

                            <i
                                data-lucide="truck"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-medium text-white">
                                Track Deliveries
                            </p>

                            <p class="text-xs text-white/65">
                                Monitor your active delivery progress.
                            </p>

                        </div>

                    </div>



                    <!-- FEATURE 3 -->
                    <div class="flex items-center gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10">

                            <i
                                data-lucide="wallet"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <div>

                            <p class="text-sm font-medium text-white">
                                Monitor Earnings
                            </p>

                            <p class="text-xs text-white/65">
                                Keep track of your completed deliveries.
                            </p>

                        </div>

                    </div>

                </div>

            </div>



            <!-- =====================================================
            | LOGIN FORM
            ====================================================== -->
            <div class="p-6 sm:p-10 lg:p-12">


                <!-- MOBILE RIDER ICON -->
                <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F4EE] text-[#1F6F5B] lg:hidden">

                    <i
                        data-lucide="bike"
                        class="h-6 w-6"
                    ></i>

                </div>



                <!-- HEADING -->
                <div>

                    <div class="flex items-center gap-2">

                        <span class="h-2 w-2 rounded-full bg-[#1F6F5B]"></span>

                        <p class="text-sm font-semibold text-[#1F6F5B]">
                            SUKI SHOP Rider Portal
                        </p>

                    </div>


                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">

                        Welcome back!

                    </h2>


                    <p class="mt-3 text-sm leading-6 text-gray-500">

                        Log in to access your SUKI SHOP Rider account and manage
                        your delivery assignments.

                    </p>

                </div>



                <!-- =====================================================
                | SUCCESS MESSAGE
                ====================================================== -->
                @if(session('success'))

                    <div class="mt-6 flex items-start gap-3 rounded-xl border border-[#BFE3D3] bg-[#E6F4EE] p-4">

                        <i
                            data-lucide="circle-check"
                            class="h-5 w-5 shrink-0 text-[#1F6F5B]"
                        ></i>


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



                <!-- =====================================================
                | ERROR MESSAGE
                ====================================================== -->
                @if(session('error'))

                    <div class="mt-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4">

                        <i
                            data-lucide="circle-alert"
                            class="h-5 w-5 shrink-0 text-red-500"
                        ></i>


                        <div>

                            <p class="text-sm font-semibold text-red-700">
                                Login Failed
                            </p>

                            <p class="mt-1 text-sm text-red-600">
                                {{ session('error') }}
                            </p>

                        </div>

                    </div>

                @endif



                <!-- =====================================================
                | LOGIN FORM
                ====================================================== -->
                <form
                    method="POST"
                    action="{{ route('rider.login.submit') }}"
                    class="mt-8 space-y-5"
                >

                    @csrf



                    <!-- EMAIL OR PHONE -->
                    <div>

                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Email or Phone Number
                        </label>


                        <div class="relative">

                            <i
                                data-lucide="user"
                                class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            ></i>


                            <input
                                type="text"
                                name="login"
                                value="{{ old('login') }}"
                                required
                                autofocus
                                placeholder="Enter your email or phone number"
                                class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>


                        @error('login')

                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>



                    <!-- PASSWORD -->
                    <div>

                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Password
                        </label>


                        <div class="relative">

                            <i
                                data-lucide="lock"
                                class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            ></i>


                            <input
                                type="password"
                                name="password"
                                required
                                placeholder="Enter your password"
                                class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >

                        </div>


                        @error('password')

                            <p class="mt-2 text-xs text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>



                    <!-- LOGIN BUTTON -->
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244] active:scale-[0.99]"
                    >

                        <i
                            data-lucide="log-in"
                            class="h-4 w-4"
                        ></i>

                        Log In to Rider Portal

                    </button>

                </form>



                <!-- =====================================================
                | DIVIDER
                ====================================================== -->
                <div class="my-7 flex items-center gap-4">

                    <div class="h-px flex-1 bg-gray-200"></div>

                    <span class="whitespace-nowrap text-xs font-medium text-gray-400">
                        NEW TO SUKI SHOP RIDER?
                    </span>

                    <div class="h-px flex-1 bg-gray-200"></div>

                </div>



                <!-- APPLY AS RIDER -->
                <a
                    href="{{ route('rider.apply') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-semibold text-gray-700 transition hover:border-[#CFE9DD] hover:bg-[#F4F8F6] hover:text-[#1F6F5B]"
                >

                    <i
                        data-lucide="file-plus-2"
                        class="h-4 w-4"
                    ></i>

                    Apply as a SUKI SHOP Rider

                </a>



                <!-- NOTE -->
                <div class="mt-6 flex items-start justify-center gap-2 text-center">

                    <i
                        data-lucide="shield-check"
                        class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                    ></i>

                    <p class="text-xs leading-5 text-gray-400">

                        Only approved SUKI SHOP Rider applications can access
                        the Rider Portal.

                    </p>

                </div>


            </div>

        </div>

    </main>

</div>

@endsection