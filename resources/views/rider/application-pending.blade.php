@extends('layouts.rider')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <a href="{{ route('buyer.home') }}" class="flex items-center">

                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-10 w-auto object-contain sm:h-12"
                >

            </a>

            <a
                href="{{ route('login') }}"
                class="text-sm font-medium text-gray-600 transition hover:text-[#1F6F5B]"
            >
                Log In
            </a>

        </div>

    </header>


    <!-- MAIN -->
    <main class="flex min-h-[calc(100vh-81px)] items-center justify-center px-5 py-10">

        <div class="w-full max-w-xl">

            <!-- SUCCESS / PENDING CARD -->
            <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">

                <!-- TOP SECTION -->
                <div class="bg-[#EEF8F3] px-6 py-10 text-center sm:px-10">

                    <!-- ICON -->
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-[#1F6F5B] text-white shadow-lg">

                        <i
                            data-lucide="clock-3"
                            class="h-10 w-10"
                        ></i>

                    </div>


                    <!-- TITLE -->
                    <h1 class="mt-6 text-2xl font-bold text-gray-900 sm:text-3xl">

                        Application Submitted!

                    </h1>


                    <!-- DESCRIPTION -->
                    <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-gray-600">

                        Thank you for applying to become a SUKI SHOP Rider.
                        Your application has been successfully submitted
                        and is now waiting for verification.

                    </p>

                </div>


                <!-- CONTENT -->
                <div class="px-6 py-7 sm:px-10">

                    <!-- STATUS -->
                    <div class="rounded-2xl border border-[#BFE3D2] bg-[#F4FBF7] p-5">

                        <div class="flex items-start gap-4">

                            <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">

                                <i
                                    data-lucide="shield-check"
                                    class="h-6 w-6"
                                ></i>

                            </div>


                            <div>

                                <p class="text-sm font-semibold text-gray-900">

                                    Application Under Review

                                </p>

                                <p class="mt-1 text-sm leading-6 text-gray-600">

                                    Our SUKI SHOP team will review your personal
                                    information and verification documents.

                                </p>

                            </div>

                        </div>

                    </div>


                    <!-- WHAT HAPPENS NEXT -->
                    <div class="mt-7">

                        <h2 class="text-base font-semibold text-gray-900">

                            What happens next?

                        </h2>


                        <div class="mt-5 space-y-5">


                            <!-- STEP 1 -->
                            <div class="flex gap-4">

                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#1F6F5B] text-sm font-semibold text-white">

                                    1

                                </div>


                                <div>

                                    <p class="text-sm font-medium text-gray-900">

                                        Application Review

                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        SUKI SHOP will verify your submitted information
                                        and required documents.

                                    </p>

                                </div>

                            </div>


                            <!-- STEP 2 -->
                            <div class="flex gap-4">

                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500">

                                    2

                                </div>


                                <div>

                                    <p class="text-sm font-medium text-gray-900">

                                        Approval Decision

                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        Your application will either be approved
                                        or returned for additional information.

                                    </p>

                                </div>

                            </div>


                            <!-- STEP 3 -->
                            <div class="flex gap-4">

                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500">

                                    3

                                </div>


                                <div>

                                    <p class="text-sm font-medium text-gray-900">

                                        Access SUKI SHOP Rider

                                    </p>

                                    <p class="mt-1 text-xs leading-5 text-gray-500">

                                        Once approved, you can log in and access
                                        the SUKI SHOP Rider dashboard.

                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- NOTICE -->
                    <div class="mt-7 flex items-start gap-3 rounded-xl bg-amber-50 p-4">

                        <i
                            data-lucide="info"
                            class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                        ></i>

                        <p class="text-xs leading-5 text-amber-800">

                            Please wait for your application to be reviewed.
                            Rider dashboard access will only be available
                            after your application has been approved.

                        </p>

                    </div>


                    <!-- BUTTONS -->
                    <div class="mt-7 space-y-3">

                        <a
                            href="{{ route('buyer.home') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                        >

                            <i
                                data-lucide="home"
                                class="h-4 w-4"
                            ></i>

                            Back to SUKI SHOP

                        </a>


                        <a
                            href="{{ route('login') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                        >

                            <i
                                data-lucide="log-in"
                                class="h-4 w-4"
                            ></i>

                            Go to Log In

                        </a>

                    </div>

                </div>

            </div>


            <!-- FOOTER TEXT -->
            <p class="mt-6 text-center text-xs text-gray-400">

                SUKI SHOP Rider Application • Your information is securely submitted for verification.

            </p>

        </div>

    </main>

</div>

@endsection