<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#173F35"
    >

    <title>
        @yield('title', 'Rider Portal') | SUKI SHOP
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>

</head>


@php

    $sidebarRiders =
        session('rider_applications', []);

    $sidebarRiderIndex =
        session('logged_in_rider_index');

    $sidebarRider = [];

    if (
        $sidebarRiderIndex !== null &&
        isset($sidebarRiders[$sidebarRiderIndex])
    ) {
        $sidebarRider =
            $sidebarRiders[$sidebarRiderIndex];
    }

    $sidebarRiderName = trim(
        ($sidebarRider['first_name'] ?? '') . ' ' .
        ($sidebarRider['last_name'] ?? '')
    );

    if ($sidebarRiderName === '') {
        $sidebarRiderName = 'SUKI Rider';
    }

    $sidebarVehicle =
        $sidebarRider['vehicle_type']
        ?? 'Delivery Rider';

@endphp


<body
    class="suki-dashboard-shell
           min-h-screen
           bg-[#F6F8F6]
           font-[Poppins]
           text-[#24312C]
           antialiased"
>


    {{-- =========================================================
        MOBILE OVERLAY
    ========================================================== --}}

    <div
        id="riderSidebarOverlay"
        class="fixed inset-0 z-40
               hidden
               bg-[#102C25]/40
               backdrop-blur-[2px]
               lg:hidden"
    ></div>


    {{-- =========================================================
        SIDEBAR
    ========================================================== --}}

    <aside
        id="riderSidebar"
        class="fixed inset-y-0 left-0 z-50
               flex w-[270px]
               -translate-x-full
               flex-col
               border-r border-white/10
               bg-[#173F35]
               text-white
               transition-transform
               duration-300
               lg:translate-x-0"
    >


        {{-- BRAND --}}
        <div
            class="flex h-[82px]
                   items-center
                   border-b border-white/10
                   px-6"
        >

            <a
                href="{{ route('rider.dashboard') }}"
                class="flex items-center gap-3"
            >

                <div
                    class="flex h-11 w-11
                           items-center justify-center
                           rounded-xl
                           bg-white/10"
                >

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="h-9 w-9 object-contain"
                    >

                </div>


                <div>

                    <p
                        class="text-[16px]
                               font-bold
                               tracking-[-0.04em]"
                    >
                        SUKI SHOP
                    </p>

                    <p
                        class="mt-0.5
                               text-[9px]
                               font-medium
                               uppercase
                               tracking-[0.18em]
                               text-white/45"
                    >
                        Rider Portal
                    </p>

                </div>

            </a>

        </div>


        {{-- RIDER PROFILE --}}
        <div class="px-4 pt-5">

            <div
                class="rounded-2xl
                       border border-white/10
                       bg-white/[0.06]
                       p-4"
            >

                <div class="flex items-center gap-3">

                    <div
                        class="flex h-10 w-10
                               shrink-0
                               items-center justify-center
                               rounded-xl
                               bg-[#DDF3EC]
                               text-[#173F35]"
                    >

                        <i
                            data-lucide="bike"
                            class="h-[18px] w-[18px]"
                        ></i>

                    </div>


                    <div class="min-w-0">

                        <p
                            class="truncate
                                   text-[13px]
                                   font-semibold
                                   text-white"
                        >
                            {{ $sidebarRiderName }}
                        </p>

                        <p
                            class="mt-0.5
                                   truncate
                                   text-[10px]
                                   text-white/45"
                        >
                            {{ $sidebarVehicle }}
                        </p>

                    </div>

                </div>


                <div
                    class="mt-4
                           flex items-center gap-2
                           border-t border-white/10
                           pt-3"
                >

                    <span
                        class="h-2 w-2
                               rounded-full
                               bg-emerald-400"
                    ></span>

                    <span
                        class="text-[10px]
                               font-medium
                               text-white/55"
                    >
                        Approved rider account
                    </span>

                </div>

            </div>

        </div>


        {{-- =====================================================
            NAVIGATION
        ====================================================== --}}

        <nav
            class="flex-1
                   overflow-y-auto
                   px-4 py-5"
        >

            <p
                class="mb-2 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                Delivery Workspace
            </p>


            <div class="space-y-1">


                {{-- DASHBOARD --}}
                <a
                    href="{{ route('rider.dashboard') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition

                        {{ request()->routeIs('rider.dashboard')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="layout-dashboard"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>
                        Dashboard
                    </span>

                </a>


                {{-- DELIVERIES --}}
                <a
                    href="{{ route('rider.deliveries') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition

                        {{ request()->routeIs('rider.deliveries')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="package-check"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>
                        Deliveries
                    </span>

                </a>


                {{-- EARNINGS --}}
                <a
                    href="{{ route('rider.earnings') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition

                        {{ request()->routeIs('rider.earnings')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="wallet-cards"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>
                        Earnings
                    </span>

                </a>

            </div>


            <p
                class="mb-2 mt-7 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                Marketplace
            </p>


            <div class="space-y-1">

                <a
                    href="{{ route('buyer.home') }}"
                    target="_blank"
                    class="flex items-center gap-3
                           rounded-xl
                           px-3 py-2.5
                           text-[13px]
                           font-medium
                           text-white/65
                           transition
                           hover:bg-white/[0.07]
                           hover:text-white"
                >

                    <i
                        data-lucide="store"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>
                        View Marketplace
                    </span>

                </a>

            </div>

        </nav>


        {{-- =====================================================
            LOGOUT
        ====================================================== --}}

        <div
            class="border-t border-white/10
                   p-4"
        >

            <form
                action="{{ route('logout') }}"
                method="POST"
            >

                @csrf


                <button
                    type="submit"
                    class="flex w-full
                           items-center gap-3
                           rounded-xl
                           px-3 py-2.5
                           text-xs
                           font-medium
                           text-white/55
                           transition
                           hover:bg-white/[0.07]
                           hover:text-white"
                >

                    <i
                        data-lucide="log-out"
                        class="h-4 w-4"
                    ></i>

                    Sign Out

                </button>

            </form>

        </div>

    </aside>


    {{-- =========================================================
        MAIN AREA
    ========================================================== --}}

    <div
        class="min-h-screen
               lg:pl-[270px]"
    >


        {{-- TOPBAR --}}
        <header
            class="sticky top-0 z-30
                   border-b border-[#DDE6E1]
                   bg-[#F8FAF8]/95
                   backdrop-blur-xl"
        >

            <div
                class="flex min-h-[74px]
                       items-center gap-4
                       px-4
                       sm:px-6
                       lg:px-8"
            >


                {{-- MOBILE MENU --}}
                <button
                    id="riderSidebarOpen"
                    type="button"
                    aria-label="Open rider navigation"
                    class="flex h-10 w-10
                           shrink-0
                           items-center justify-center
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           text-[#52635B]
                           transition
                           hover:bg-[#F3F7F5]
                           lg:hidden"
                >

                    <i
                        data-lucide="menu"
                        class="h-5 w-5"
                    ></i>

                </button>


                {{-- PAGE TITLE --}}
                <div class="min-w-0 flex-1">

                    <p
                        class="text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.14em]
                               text-[#1F6F5B]"
                    >
                        Rider Portal
                    </p>


                    <h1
                        class="truncate
                               text-[17px]
                               font-semibold
                               tracking-[-0.025em]
                               text-[#24312C]
                               sm:text-lg"
                    >
                        @yield('page-heading', 'Dashboard')
                    </h1>

                </div>


                {{-- STATUS --}}
                <div class="flex items-center gap-2">

                    <div
                        class="hidden
                               items-center gap-2
                               rounded-xl
                               border border-emerald-200
                               bg-emerald-50
                               px-3.5 py-2.5
                               text-[10px]
                               font-semibold
                               text-emerald-700
                               sm:flex"
                    >

                        <span
                            class="h-2 w-2
                                   rounded-full
                                   bg-emerald-500"
                        ></span>

                        Online

                    </div>


                    <a
                        href="{{ route('buyer.home') }}"
                        target="_blank"
                        title="Open marketplace"
                        class="flex h-10 w-10
                               items-center justify-center
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white
                               text-[#52635B]
                               transition
                               hover:border-[#BFD2C9]
                               hover:bg-[#F3F7F5]
                               hover:text-[#173F35]"
                    >

                        <i
                            data-lucide="external-link"
                            class="h-4 w-4"
                        ></i>

                    </a>

                </div>

            </div>

        </header>


        {{-- PAGE CONTENT --}}
        <main
            class="mx-auto
                   w-full
                   max-w-[1500px]
                   px-4 py-6
                   sm:px-6
                   lg:px-8
                   lg:py-8"
        >

            @yield('content')

        </main>

    </div>


    @stack('scripts')


    {{-- =========================================================
        SIDEBAR SCRIPT
    ========================================================== --}}

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const sidebar =
                    document.getElementById(
                        'riderSidebar'
                    );

                const overlay =
                    document.getElementById(
                        'riderSidebarOverlay'
                    );

                const openButton =
                    document.getElementById(
                        'riderSidebarOpen'
                    );


                function openSidebar() {

                    sidebar?.classList.remove(
                        '-translate-x-full'
                    );

                    overlay?.classList.remove(
                        'hidden'
                    );

                    document.body.classList.add(
                        'overflow-hidden'
                    );

                }


                function closeSidebar() {

                    sidebar?.classList.add(
                        '-translate-x-full'
                    );

                    overlay?.classList.add(
                        'hidden'
                    );

                    document.body.classList.remove(
                        'overflow-hidden'
                    );

                }


                openButton?.addEventListener(
                    'click',
                    openSidebar
                );


                overlay?.addEventListener(
                    'click',
                    closeSidebar
                );


                window.addEventListener(
                    'resize',
                    function () {

                        if (
                            window.innerWidth >= 1024
                        ) {

                            overlay?.classList.add(
                                'hidden'
                            );

                            document.body.classList.remove(
                                'overflow-hidden'
                            );

                        }

                    }
                );


                if (
                    typeof lucide !== 'undefined' &&
                    typeof lucide.createIcons ===
                        'function'
                ) {

                    lucide.createIcons();

                }

            }
        );

    </script>

</body>

</html>