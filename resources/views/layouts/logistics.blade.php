<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >


    <title>
        @yield('title', 'Logistics') | SUKI SHOP
    </title>


    {{-- =====================================================
        APP ASSETS
    ====================================================== --}}

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])


    {{-- =====================================================
        POPPINS
    ====================================================== --}}

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


    {{-- =====================================================
        LUCIDE
    ====================================================== --}}

    <script
        src="https://unpkg.com/lucide@latest"
    ></script>


    @stack('styles')

</head>


<body
    class="
        min-h-screen
        bg-[#F7F9F8]
        font-[Poppins]
        text-[#24312C]
        antialiased
    "
>


{{-- =========================================================
    MOBILE OVERLAY
========================================================= --}}

<div
    id="logisticsSidebarOverlay"
    class="
        fixed inset-0 z-40
        hidden
        bg-[#102C25]/45
        backdrop-blur-[1px]
        lg:hidden
    "
></div>


{{-- =========================================================
    SIDEBAR
========================================================= --}}

<aside
    id="logisticsSidebar"
    class="
        fixed inset-y-0 left-0 z-50
        flex w-[270px]
        -translate-x-full
        flex-col
        border-r
        border-white/[0.08]
        bg-[#173F35]
        transition-transform
        duration-300
        lg:translate-x-0
    "
>

    {{-- BRAND --}}
    <div
        class="
            flex h-[78px]
            items-center
            border-b
            border-white/[0.08]
            px-5
        "
    >

        <a
            href="{{ route('logistics.dashboard') }}"
            class="flex min-w-0 items-center gap-3"
        >

            <div
                class="
                    flex h-10 w-10
                    shrink-0
                    items-center justify-center
                    overflow-hidden
                    rounded-xl
                    bg-white
                "
            >

                <img
                    src="{{ asset('images/logistics-logo.png') }}"
                    alt="SUKI SHOP Logistics"
                    class="h-8 w-8 object-contain"
                >

            </div>


            <div class="min-w-0">

                <p
                    class="
                        truncate
                        text-[13px]
                        font-semibold
                        tracking-[-0.02em]
                        text-white
                    "
                >
                    SUKI Logistics
                </p>


                <p
                    class="
                        mt-0.5
                        truncate
                        text-[9px]
                        font-medium
                        uppercase
                        tracking-[0.12em]
                        text-white/40
                    "
                >
                    Sorting Center Portal
                </p>

            </div>

        </a>


        <button
            type="button"
            id="closeLogisticsSidebar"
            class="
                ml-auto
                flex h-9 w-9
                items-center justify-center
                rounded-lg
                text-white/55
                transition
                hover:bg-white/[0.08]
                hover:text-white
                lg:hidden
            "
            aria-label="Close navigation"
        >

            <i
                data-lucide="x"
                class="h-[18px] w-[18px]"
            ></i>

        </button>

    </div>


    {{-- =====================================================
        PORTAL IDENTIFIER
    ====================================================== --}}

    <div class="px-4 pt-5">

        <div
            class="
                rounded-2xl
                border
                border-white/[0.08]
                bg-white/[0.05]
                p-3.5
            "
        >

            <div class="flex items-center gap-3">

                <div
                    class="
                        flex h-9 w-9
                        shrink-0
                        items-center justify-center
                        rounded-xl
                        bg-[#DDF3EC]
                        text-[#173F35]
                    "
                >

                    <i
                        data-lucide="warehouse"
                        class="h-[17px] w-[17px]"
                    ></i>

                </div>


                <div class="min-w-0">

                    <p
                        class="
                            text-[11px]
                            font-semibold
                            text-white
                        "
                    >
                        Operations Center
                    </p>


                    <div
                        class="
                            mt-1
                            flex items-center gap-1.5
                            text-[9px]
                            text-white/45
                        "
                    >

                        <span
                            class="
                                h-1.5 w-1.5
                                rounded-full
                                bg-emerald-400
                            "
                        ></span>

                        Logistics active

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
        NAVIGATION
    ====================================================== --}}

    <div
        class="
            mt-5
            flex-1
            overflow-y-auto
            px-3
            pb-5
        "
    >

        <p
            class="
                mb-2
                px-3
                text-[8px]
                font-semibold
                uppercase
                tracking-[0.16em]
                text-white/30
            "
        >
            Overview
        </p>


        {{-- DASHBOARD --}}
        <a
            href="{{ route('logistics.dashboard') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.dashboard')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="layout-dashboard"
                class="h-[17px] w-[17px]"
            ></i>

            Dashboard

        </a>


        {{-- =================================================
            PARCEL OPERATIONS
        ================================================== --}}

        <p
            class="
                mb-2 mt-6
                px-3
                text-[8px]
                font-semibold
                uppercase
                tracking-[0.16em]
                text-white/30
            "
        >
            Parcel Operations
        </p>


        {{-- INCOMING PARCELS --}}
        <a
            href="{{ route('logistics.parcels') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.parcels')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="package-check"
                class="h-[17px] w-[17px]"
            ></i>

            Incoming Parcels

        </a>


        {{-- SORTING --}}
        <a
            href="{{ route('logistics.sorting') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.sorting')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="scan-line"
                class="h-[17px] w-[17px]"
            ></i>

            Parcel Sorting

        </a>


        {{-- ASSIGNMENTS --}}
        <a
            href="{{ route('logistics.assignments') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.assignments')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="map-pinned"
                class="h-[17px] w-[17px]"
            ></i>

            Delivery Assignment

        </a>


        {{-- SHIPMENTS --}}
        <a
            href="{{ route('logistics.shipments') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.shipments')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="boxes"
                class="h-[17px] w-[17px]"
            ></i>

            Shipments

        </a>


        {{-- MONITORING --}}
        <a
            href="{{ route('logistics.monitoring') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.monitoring')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="route"
                class="h-[17px] w-[17px]"
            ></i>

            Delivery Monitoring

        </a>


        {{-- =================================================
            RIDERS
        ================================================== --}}

        <p
            class="
                mb-2 mt-6
                px-3
                text-[8px]
                font-semibold
                uppercase
                tracking-[0.16em]
                text-white/30
            "
        >
            Riders
        </p>


        <a
            href="{{ route('logistics.riders') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs(
                    'logistics.riders',
                    'logistics.riders.review'
                )
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="bike"
                class="h-[17px] w-[17px]"
            ></i>

            Rider Management

        </a>


        {{-- =================================================
            INSIGHTS
        ================================================== --}}

        <p
            class="
                mb-2 mt-6
                px-3
                text-[8px]
                font-semibold
                uppercase
                tracking-[0.16em]
                text-white/30
            "
        >
            Insights
        </p>


        <a
            href="{{ route('logistics.reports') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[12px]
                font-medium
                transition

                {{ request()->routeIs('logistics.reports')
                    ? 'bg-white text-[#173F35]'
                    : 'text-white/62 hover:bg-white/[0.07] hover:text-white'
                }}
            "
        >

            <i
                data-lucide="chart-no-axes-combined"
                class="h-[17px] w-[17px]"
            ></i>

            Reports

        </a>

    </div>


    {{-- =====================================================
        SIDEBAR FOOTER
    ====================================================== --}}

    <div
        class="
            border-t
            border-white/[0.08]
            p-3
        "
    >

        <a
            href="{{ route('buyer.home') }}"
            class="
                mb-1
                flex items-center gap-3
                rounded-xl
                px-3 py-2.5
                text-[11px]
                font-medium
                text-white/55
                transition
                hover:bg-white/[0.07]
                hover:text-white
            "
        >

            <i
                data-lucide="store"
                class="h-[16px] w-[16px]"
            ></i>

            View Marketplace

        </a>


        <form
            method="POST"
            action="{{ route('logout') }}"
        >

            @csrf


            <button
                type="submit"
                class="
                    flex w-full
                    items-center gap-3
                    rounded-xl
                    px-3 py-2.5
                    text-left
                    text-[11px]
                    font-medium
                    text-white/55
                    transition
                    hover:bg-red-500/10
                    hover:text-red-200
                "
            >

                <i
                    data-lucide="log-out"
                    class="h-[16px] w-[16px]"
                ></i>

                Sign Out

            </button>

        </form>

    </div>

</aside>


{{-- =========================================================
    APP SHELL
========================================================= --}}

<div
    class="
        min-h-screen
        lg:pl-[270px]
    "
>


    {{-- =====================================================
        TOPBAR
    ====================================================== --}}

    <header
        class="
            sticky top-0 z-30
            h-[72px]
            border-b
            border-[#E2E9E5]
            bg-white/95
            backdrop-blur
        "
    >

        <div
            class="
                flex h-full
                items-center
                justify-between
                gap-4
                px-4
                sm:px-6
                xl:px-8
            "
        >

            {{-- LEFT --}}
            <div class="flex min-w-0 items-center gap-3">

                <button
                    type="button"
                    id="openLogisticsSidebar"
                    class="
                        flex h-9 w-9
                        shrink-0
                        items-center justify-center
                        rounded-xl
                        border
                        border-[#DFE7E3]
                        bg-white
                        text-[#52635B]
                        transition
                        hover:bg-[#F3F7F5]
                        lg:hidden
                    "
                    aria-label="Open navigation"
                >

                    <i
                        data-lucide="menu"
                        class="h-[17px] w-[17px]"
                    ></i>

                </button>


                <div class="min-w-0">

                    <p
                        class="
                            text-[9px]
                            font-semibold
                            uppercase
                            tracking-[0.12em]
                            text-[#92A098]
                        "
                    >
                        SUKI Logistics
                    </p>


                    <h1
                        class="
                            truncate
                            text-[15px]
                            font-semibold
                            tracking-[-0.02em]
                            text-[#24312C]
                            sm:text-[17px]
                        "
                    >
                        @yield(
                            'page-heading',
                            'Sorting Center'
                        )
                    </h1>

                </div>

            </div>


            {{-- RIGHT --}}
            <div
                class="
                    flex shrink-0
                    items-center gap-2
                "
            >

                {{-- ACTIVE STATUS --}}
                <div
                    class="
                        hidden
                        items-center gap-2
                        rounded-xl
                        border
                        border-emerald-200
                        bg-emerald-50
                        px-3 py-2
                        text-[9px]
                        font-semibold
                        text-emerald-700
                        sm:flex
                    "
                >

                    <span
                        class="
                            h-1.5 w-1.5
                            rounded-full
                            bg-emerald-500
                        "
                    ></span>

                    Operations Active

                </div>


                {{-- MARKETPLACE --}}
                <a
                    href="{{ route('buyer.home') }}"
                    class="
                        hidden h-9
                        items-center
                        justify-center gap-2
                        rounded-xl
                        border
                        border-[#DFE7E3]
                        bg-white
                        px-3
                        text-[10px]
                        font-semibold
                        text-[#52635B]
                        transition
                        hover:border-[#BFD5CA]
                        hover:bg-[#F5F8F6]
                        sm:inline-flex
                    "
                >

                    <i
                        data-lucide="external-link"
                        class="h-3.5 w-3.5"
                    ></i>

                    Marketplace

                </a>


                {{-- PROFILE ICON --}}
                <div
                    class="
                        flex h-9 w-9
                        items-center justify-center
                        rounded-xl
                        bg-[#173F35]
                        text-white
                    "
                    title="SUKI Logistics"
                >

                    <i
                        data-lucide="warehouse"
                        class="h-4 w-4"
                    ></i>

                </div>

            </div>

        </div>

    </header>


    {{-- =====================================================
        MAIN CONTENT
    ====================================================== --}}

    <main
        class="
            min-h-[calc(100vh-72px)]
            p-4
            sm:p-6
            xl:p-8
        "
    >

        @yield('content')

    </main>

</div>


{{-- =========================================================
    SCRIPTS
========================================================= --}}

@stack('scripts')


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById(
                'logisticsSidebar'
            );


        const overlay =
            document.getElementById(
                'logisticsSidebarOverlay'
            );


        const openButton =
            document.getElementById(
                'openLogisticsSidebar'
            );


        const closeButton =
            document.getElementById(
                'closeLogisticsSidebar'
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


        closeButton?.addEventListener(
            'click',
            closeSidebar
        );


        overlay?.addEventListener(
            'click',
            closeSidebar
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'Escape'
                ) {

                    closeSidebar();

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