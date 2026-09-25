<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Choose Account Type - SUKI SHOP</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">

    {{-- =====================================================
        HEADER
    ====================================================== --}}

    <header class="border-b border-[#E5ECE8] bg-white">

        <div class="mx-auto flex h-[76px] max-w-7xl items-center justify-between px-5 sm:px-8">

            {{-- BRAND --}}
            <a
                href="{{ route('landing') }}"
                class="flex items-center gap-3"
            >

                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-11 w-auto object-contain"
                >

                <div class="hidden sm:block">

                    <p class="text-sm font-semibold tracking-tight text-[#173F35]">
                        SUKI SHOP
                    </p>

                    <p class="text-[10px] uppercase tracking-[0.18em] text-[#7D948B]">
                        Connected Marketplace
                    </p>

                </div>

            </a>


            {{-- LOGIN --}}
            <div class="flex items-center gap-3">

                <span class="hidden text-xs text-gray-500 sm:inline">
                    Already have an account?
                </span>

                <a
                    href="{{ route('login') }}"
                    class="rounded-lg border border-[#DCE7E2] bg-white px-4 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-[#F2F8F5]"
                >
                    Log In
                </a>

            </div>

        </div>

    </header>


    {{-- =====================================================
        MAIN
    ====================================================== --}}

    <main class="px-5 py-12 sm:px-8 lg:py-16">

        <div class="mx-auto max-w-6xl">


            {{-- =================================================
                PAGE INTRO
            ================================================== --}}

            <div class="mx-auto max-w-2xl text-center">

                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E7F4EE] text-[#1F6F5B]"
                >
                    <i
                        data-lucide="user-plus"
                        class="h-6 w-6"
                    ></i>
                </div>


                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.16em] text-[#1F6F5B]">
                    Join SUKI SHOP
                </p>


                <h1 class="mt-2 text-3xl font-semibold tracking-[-0.03em] text-[#173F35] sm:text-4xl">
                    Choose your account type
                </h1>


                <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-gray-500">
                    Select how you want to use SUKI SHOP.
                    Each account type has its own registration process and access.
                </p>

            </div>


            {{-- =================================================
                ACCOUNT TYPE CARDS
            ================================================== --}}

            <div class="mt-12 grid gap-6 md:grid-cols-3">


                {{-- =================================================
                    BUYER
                ================================================== --}}

                <article
                    class="group flex flex-col rounded-2xl border border-[#E1E9E5] bg-white p-7 transition duration-200 hover:-translate-y-1 hover:border-[#BFD8CC] hover:shadow-lg"
                >

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EDF7F2] text-[#1F6F5B]"
                    >
                        <i
                            data-lucide="shopping-bag"
                            class="h-6 w-6"
                        ></i>
                    </div>


                    <div class="mt-6">

                        <div class="flex items-center justify-between gap-3">

                            <h2 class="text-xl font-semibold text-[#202421]">
                                Buyer
                            </h2>

                            <span
                                class="rounded-full bg-[#EDF7F2] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#1F6F5B]"
                            >
                                Shop
                            </span>

                        </div>


                        <p class="mt-3 text-sm leading-6 text-gray-500">
                            Shop products from SUKI sellers, manage your cart,
                            place orders, and track your purchases.
                        </p>

                    </div>


                    <ul class="mt-6 space-y-3">

                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Browse and purchase products

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Cart and wishlist access

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Track orders and deliveries

                        </li>

                    </ul>


                    <div class="mt-auto pt-8">

                        <a
                            href="{{ route('register.buyer') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#175747]"
                        >
                            Register as Buyer

                            <i
                                data-lucide="arrow-right"
                                class="h-4 w-4"
                            ></i>
                        </a>

                    </div>

                </article>


                {{-- =================================================
                    SELLER
                ================================================== --}}

                <article
                    class="group flex flex-col rounded-2xl border border-[#E1E9E5] bg-white p-7 transition duration-200 hover:-translate-y-1 hover:border-[#BFD8CC] hover:shadow-lg"
                >

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EDF7F2] text-[#1F6F5B]"
                    >
                        <i
                            data-lucide="store"
                            class="h-6 w-6"
                        ></i>
                    </div>


                    <div class="mt-6">

                        <div class="flex items-center justify-between gap-3">

                            <h2 class="text-xl font-semibold text-[#202421]">
                                Seller
                            </h2>

                            <span
                                class="rounded-full bg-[#EDF7F2] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#1F6F5B]"
                            >
                                Sell
                            </span>

                        </div>


                        <p class="mt-3 text-sm leading-6 text-gray-500">
                            Build your SUKI store, manage products and inventory,
                            and process customer orders.
                        </p>

                    </div>


                    <ul class="mt-6 space-y-3">

                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Manage products

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Monitor inventory

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Process customer orders

                        </li>

                    </ul>


                    <div class="mt-auto pt-8">

                        <a
                            href="{{ route('seller.register') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-5 py-3 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#1F6F5B] hover:text-white"
                        >
                            Register as Seller

                            <i
                                data-lucide="arrow-right"
                                class="h-4 w-4"
                            ></i>
                        </a>

                    </div>

                </article>


                {{-- =================================================
                    RIDER
                ================================================== --}}

                <article
                    class="group flex flex-col rounded-2xl border border-[#E1E9E5] bg-white p-7 transition duration-200 hover:-translate-y-1 hover:border-[#BFD8CC] hover:shadow-lg"
                >

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EDF7F2] text-[#1F6F5B]"
                    >
                        <i
                            data-lucide="bike"
                            class="h-6 w-6"
                        ></i>
                    </div>


                    <div class="mt-6">

                        <div class="flex items-center justify-between gap-3">

                            <h2 class="text-xl font-semibold text-[#202421]">
                                Rider
                            </h2>

                            <span
                                class="rounded-full bg-[#EDF7F2] px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-[#1F6F5B]"
                            >
                                Deliver
                            </span>

                        </div>


                        <p class="mt-3 text-sm leading-6 text-gray-500">
                            Apply as a SUKI SHOP rider and help deliver customer
                            orders within assigned delivery areas.
                        </p>

                    </div>


                    <ul class="mt-6 space-y-3">

                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Receive delivery assignments

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Update delivery status

                        </li>


                        <li class="flex items-center gap-3 text-sm text-gray-600">

                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-[#EDF7F2] text-[#1F6F5B]"
                            >
                                <i
                                    data-lucide="check"
                                    class="h-3 w-3"
                                ></i>
                            </span>

                            Track rider activity

                        </li>

                    </ul>


                    <div class="mt-auto pt-8">

                        <a
                            href="{{ route('rider.apply') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-5 py-3 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#1F6F5B] hover:text-white"
                        >
                            Apply as Rider

                            <i
                                data-lucide="arrow-right"
                                class="h-4 w-4"
                            ></i>
                        </a>

                    </div>

                </article>

            </div>


            {{-- =================================================
                APPROVAL NOTICE
            ================================================== --}}

            <div
                class="mx-auto mt-8 flex max-w-3xl items-start gap-4 rounded-2xl border border-[#DCE9E3] bg-white p-5"
            >

                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EDF7F2] text-[#1F6F5B]"
                >
                    <i
                        data-lucide="shield-check"
                        class="h-5 w-5"
                    ></i>
                </div>


                <div>

                    <h3 class="text-sm font-semibold text-[#29302C]">
                        Account approval
                    </h3>

                    <p class="mt-1 text-xs leading-5 text-gray-500 sm:text-sm">
                        Buyer and rider applications may require approval before
                        full account access becomes available.
                    </p>

                </div>

            </div>


            {{-- =================================================
                LOGIN
            ================================================== --}}

            <div class="mt-8 text-center">

                <p class="text-sm text-gray-500">

                    Already registered?

                    <a
                        href="{{ route('login') }}"
                        class="ml-1 font-semibold text-[#1F6F5B] transition hover:underline"
                    >
                        Sign in to your account
                    </a>

                </p>

            </div>

        </div>

    </main>


    <script>
        document.addEventListener('DOMContentLoaded', function () {

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        });
    </script>

</body>

</html>