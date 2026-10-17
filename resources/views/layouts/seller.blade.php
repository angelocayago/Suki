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
        @yield('title', 'Seller Centre') | SUKI SHOP
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

    $sellerProfile = session('seller_profile', []);

    $sellerName = $sellerProfile['seller_name']
        ?? 'Seller';

@endphp


<body
    class="suki-dashboard-shell
           bg-[#F6F8F6]
           font-sans
           text-[#24312C]
           antialiased"
>


    {{-- =========================================================
        MOBILE OVERLAY
    ========================================================== --}}

    <div
        id="sellerSidebarOverlay"
        class="fixed inset-0 z-40
               hidden bg-[#102C25]/40
               backdrop-blur-[2px]
               lg:hidden"
    ></div>


    {{-- =========================================================
        SIDEBAR
    ========================================================== --}}

    <aside
        id="sellerSidebar"
        class="fixed inset-y-0 left-0 z-50
               flex w-[270px]
               -translate-x-full
               flex-col
               border-r border-[#DDE6E1]
               bg-[#173F35]
               text-white
               transition-transform duration-300
               lg:translate-x-0"
    >


        {{-- BRAND --}}
        <div
            class="seller-sidebar-brand flex h-[82px] shrink-0
                   items-center justify-between
                   border-b border-white/10
                   px-5"
        >

            <a
                href="{{ route('seller.dashboard') }}"
                class="seller-sidebar-brand-link flex min-w-0 items-center gap-3"
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


                <div class="seller-sidebar-brand-copy">

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
                        Seller Centre
                    </p>

                </div>

            </a>

            <button
                id="sellerSidebarCollapse"
                type="button"
                class="seller-sidebar-collapse flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white/65 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40"
                aria-label="Collapse sidebar"
                aria-expanded="true"
                aria-controls="sellerSidebar"
                title="Collapse sidebar"
            >
                <i data-lucide="menu" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
            </button>

        </div>


        {{-- NAVIGATION --}}
        <nav aria-label="Seller navigation" class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-4 py-5">

            <p
                class="seller-sidebar-section-label
                       mb-2 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                WORKSPACE
            </p>


            <div class="space-y-1">


                {{-- DASHBOARD --}}
                <a
                    href="{{ route('seller.dashboard') }}"
                    aria-label="Dashboard"
                    title="Dashboard"
                    @if(request()->routeIs('seller.dashboard')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.dashboard')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="layout-dashboard"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Dashboard</span>

                </a>


            </div>

            <p class="seller-sidebar-section-label mb-2 mt-7 px-3 text-[9px] font-semibold uppercase tracking-[0.18em] text-white/35">
                STORE MANAGEMENT
            </p>

            <div class="space-y-1">

                {{-- ORDERS --}}
                <a
                    href="{{ route('seller.orders') }}"
                    aria-label="Orders"
                    title="Orders"
                    @if(request()->routeIs('seller.orders', 'seller.orders.*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.orders', 'seller.orders.*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="shopping-bag"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Orders</span>

                </a>


                {{-- PRODUCTS --}}
                <a
                    href="{{ route('seller.products') }}"
                    aria-label="Products"
                    title="Products"
                    @if(request()->routeIs('seller.products*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.products*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="package"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Products</span>

                </a>


                {{-- INVENTORY --}}
                <a
                    href="{{ route('seller.inventory') }}"
                    aria-label="Inventory"
                    title="Inventory"
                    @if(request()->routeIs('seller.inventory*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.inventory*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="boxes"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Inventory</span>

                </a>


            </div>

            <p class="seller-sidebar-section-label mb-2 mt-7 px-3 text-[9px] font-semibold uppercase tracking-[0.18em] text-white/35">
                FINANCE &amp; ANALYTICS
            </p>

            <div class="space-y-1">

                {{-- SALES --}}
                <a
                    href="{{ route('seller.sales') }}"
                    aria-label="Earnings &amp; Payouts"
                    title="Earnings &amp; Payouts"
                    @if(request()->routeIs('seller.sales', 'seller.sales.*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.sales', 'seller.sales.*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >
                    <i
                        data-lucide="banknote"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Earnings &amp; Payouts</span>
                </a>


                {{-- REPORTS --}}
                <a
                    href="{{ route('seller.reports') }}"
                    aria-label="Reports"
                    title="Reports"
                    @if(request()->routeIs('seller.reports', 'seller.reports.*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.reports', 'seller.reports.*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="chart-no-axes-combined"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Reports</span>

                </a>

                {{-- PROMOTIONS --}}
                <a
                    href="{{ route('seller.promotions') }}"
                    aria-label="Promotions"
                    title="Promotions"
                    @if(request()->routeIs('seller.promotions*')) aria-current="page" @endif
                    class="
                        seller-sidebar-link
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.promotions*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >
                    <i
                        data-lucide="badge-percent"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">Promotions</span>
                </a>



            </div>


            <p class="seller-sidebar-section-label mb-2 mt-7 px-3 text-[9px] font-semibold uppercase tracking-[0.18em] text-white/35">
                CUSTOMER SERVICE
            </p>

            <div class="space-y-1">

                <span
                    role="link"
                    aria-disabled="true"
                    aria-label="Messages (Coming soon)"
                    title="Messages - Coming soon"
                    class="seller-sidebar-link flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-white/45"
                >
                    <i data-lucide="message-circle" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label min-w-0">
                        Messages
                        <span class="block text-[9px] font-normal text-white/35">Coming soon</span>
                    </span>
                </span>

                <span
                    role="link"
                    aria-disabled="true"
                    aria-label="Notifications (Coming soon)"
                    title="Notifications - Coming soon"
                    class="seller-sidebar-link flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-white/45"
                >
                    <i data-lucide="bell" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label min-w-0">
                        Notifications
                        <span class="block text-[9px] font-normal text-white/35">Coming soon</span>
                    </span>
                </span>

                <span
                    role="link"
                    aria-disabled="true"
                    aria-label="Reviews (Coming soon)"
                    title="Reviews - Coming soon"
                    class="seller-sidebar-link flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-white/45"
                >
                    <i data-lucide="star" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label min-w-0">
                        Reviews
                        <span class="block text-[9px] font-normal text-white/35">Coming soon</span>
                    </span>
                </span>
            </div>

            {{-- STORE SECTION --}}
            <p
                class="seller-sidebar-section-label mb-2 mt-7 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                STORE
            </p>


            <div class="space-y-1">

                <a
                    href="{{ route('buyer.home') }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="View Marketplace"
                    title="View Marketplace"
                    class="flex items-center gap-3
                           seller-sidebar-link
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
                        data-lucide="external-link"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="seller-sidebar-link-label">View Marketplace</span>

                </a>

            </div>

            <p class="seller-sidebar-section-label mb-2 mt-7 px-3 text-[9px] font-semibold uppercase tracking-[0.18em] text-white/35">
                ACCOUNT
            </p>

            <div class="space-y-1">

                <a
                    href="{{ route('seller.store.profile') }}"
                    aria-label="Store Profile"
                    title="Store Profile"
                    @if(request()->routeIs('seller.store.*')) aria-current="page" @endif
                    class="seller-sidebar-link flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium transition
                        {{ request()->routeIs('seller.store.*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}"
                >
                    <i data-lucide="store" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label">Store Profile</span>
                </a>

                <span
                    role="link"
                    aria-disabled="true"
                    aria-label="My Profile (Coming soon)"
                    title="My Profile - Coming soon"
                    class="seller-sidebar-link flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-medium text-white/45"
                >
                    <i data-lucide="user-round" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label min-w-0">
                        My Profile
                        <span class="block text-[9px] font-normal text-white/35">Coming soon</span>
                    </span>
                </span>
            </div>

        </nav>


        {{-- SIDEBAR FOOTER --}}
        <div
            class="seller-sidebar-footer shrink-0 border-t border-white/10
                   p-4"
        >

            <form
                id="sellerLogoutForm"
                action="{{ route('logout') }}"
                method="POST"
            >

                @csrf

                <button
                    id="sellerLogoutTrigger"
                    type="button"
                    aria-haspopup="dialog"
                    aria-controls="sellerLogoutDialog"
                    aria-label="Sign Out"
                    title="Sign Out"
                    class="seller-sidebar-signout flex w-full
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

                    <span class="seller-sidebar-signout-label">Sign Out</span>

                </button>

            </form>

        </div>

    </aside>

    <dialog
        id="sellerLogoutDialog"
        aria-labelledby="sellerLogoutTitle"
        aria-describedby="sellerLogoutDescription"
        class="m-auto max-h-[90dvh] w-[min(440px,calc(100%-2rem))] overflow-y-auto rounded-2xl border border-[#DDE6E1] bg-[#F8FAF8] p-6 text-[#24312C] shadow-2xl backdrop:bg-[#102C25]/55 backdrop:backdrop-blur-sm"
    >
        <h2 id="sellerLogoutTitle" class="text-lg font-semibold text-[#173F35]">
            Are you sure you want to log out?
        </h2>
        <p id="sellerLogoutDescription" class="mt-2 text-sm text-[#52635B]">
            You will be signed out of your seller account.
        </p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <button
                id="sellerLogoutCancel"
                type="button"
                autofocus
                class="rounded-xl border border-[#DDE6E1] bg-white px-4 py-2.5 text-sm font-medium text-[#173F35] transition hover:bg-[#EEF4F0] focus:outline-none focus:ring-2 focus:ring-[#173F35]/40"
            >No, Cancel</button>
            <button
                id="sellerLogoutConfirm"
                type="submit"
                form="sellerLogoutForm"
                class="rounded-xl bg-[#173F35] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#245546] focus:outline-none focus:ring-2 focus:ring-[#173F35]/40 disabled:cursor-wait disabled:opacity-60"
            >Yes, Log Out</button>
        </div>
    </dialog>


    {{-- =========================================================
        MAIN
    ========================================================== --}}

    <div id="sellerMainContent" class="min-h-screen lg:pl-[270px]">


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
                    id="sellerSidebarOpen"
                    type="button"
                    class="flex h-10 w-10
                           shrink-0
                           items-center justify-center
                           rounded-xl
                           border border-[#DDE6E1]
                           bg-white
                           text-[#52635B]
                           focus:outline-none focus:ring-2 focus:ring-[#173F35]/40
                           lg:hidden"
                    aria-label="Open navigation"
                    aria-expanded="false"
                    aria-controls="sellerSidebar"
                >

                    <i
                        data-lucide="menu"
                        class="h-5 w-5"
                    ></i>

                </button>


               {{-- PAGE TITLE --}}
<div class="min-w-0 flex-1">

    @hasSection('page-subtitle')

        <h1
            class="truncate
                   text-[17px]
                   font-semibold
                   tracking-[-0.025em]
                   text-[#24312C]
                   sm:text-lg"
        >
            @yield('page-title', 'Dashboard')
        </h1>

        <p
            class="mt-0.5
                   truncate
                   text-[11px]
                   text-[#89958F]
                   sm:text-xs"
        >
            @yield('page-subtitle')
        </p>

    @else

        <p
            class="text-[10px]
                   font-semibold
                   uppercase
                   tracking-[0.14em]
                   text-[#1F6F5B]"
        >
            Seller Centre
        </p>

        <h1
            class="truncate
                   text-[17px]
                   font-semibold
                   tracking-[-0.025em]
                   text-[#24312C]
                   sm:text-lg"
        >
            @yield('page-title', 'Dashboard')
        </h1>

    @endif

</div>


                {{-- ACTIONS --}}
                <div class="relative flex shrink-0 items-center gap-2">

                    <a
                        href="{{ route('buyer.home') }}"
                        target="_blank"
                        class="hidden h-10
                               items-center gap-2
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white
                               px-3.5
                               text-xs font-semibold
                               text-[#52635B]
                               transition
                               hover:border-[#BFD2C9]
                               hover:bg-[#F3F7F5]
                               hover:text-[#173F35]
                               md:flex"
                    >

                        <i
                            data-lucide="external-link"
                            class="h-4 w-4"
                        ></i>

                        View Store

                    </a>


                    <a
                        href="{{ route('seller.products.create') }}"
                        class="flex h-10
                               items-center gap-2
                               rounded-xl
                               bg-[#173F35]
                               px-3.5
                               text-xs
                               font-semibold
                               text-white
                               transition
                               hover:bg-[#1F6F5B]
                               sm:px-4"
                    >

                        <i
                            data-lucide="plus"
                            class="h-4 w-4"
                        ></i>

                        <span class="hidden sm:inline">
                            Add Product
                        </span>

                    </a>


                    <div id="sellerNotificationsControl">
                        <button
                            id="sellerNotificationsToggle"
                            type="button"
                            aria-label="Notifications"
                            aria-expanded="false"
                            aria-controls="sellerNotificationsPanel"
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#DDE6E1] bg-white text-[#52635B] transition hover:bg-[#F3F7F5] hover:text-[#173F35] focus:outline-none focus:ring-2 focus:ring-[#173F35]/40"
                        >
                            <i data-lucide="bell" class="h-[18px] w-[18px]" aria-hidden="true"></i>
                        </button>
                        <div
                            id="sellerNotificationsPanel"
                            hidden
                            role="region"
                            aria-labelledby="sellerNotificationsHeading"
                            class="absolute right-0 top-full z-40 mt-3 max-h-[70dvh] w-[min(320px,calc(100vw-2rem))] overflow-y-auto rounded-2xl border border-[#DDE6E1] bg-white p-4 text-sm text-[#24312C] shadow-lg"
                        >
                            <h2 id="sellerNotificationsHeading" class="font-semibold text-[#173F35]">Notifications</h2>
                            <p class="mt-2 text-xs leading-relaxed text-[#52635B]">Seller notifications are not available yet.</p>
                        </div>
                    </div>

                    <div id="sellerAccountControl">
                        <button
                            id="sellerAccountToggle"
                            type="button"
                            aria-label="Seller account"
                            aria-expanded="false"
                            aria-controls="sellerAccountPanel"
                            class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#DDE6E1] bg-white text-[#52635B] transition hover:bg-[#F3F7F5] hover:text-[#173F35] focus:outline-none focus:ring-2 focus:ring-[#173F35]/40"
                        >
                            <i data-lucide="user-round" class="h-[18px] w-[18px]" aria-hidden="true"></i>
                        </button>
                        <div
                            id="sellerAccountPanel"
                            hidden
                            role="region"
                            aria-labelledby="sellerAccountHeading"
                            class="absolute right-0 top-full z-40 mt-3 max-h-[70dvh] w-[min(240px,calc(100vw-2rem))] overflow-y-auto rounded-2xl border border-[#DDE6E1] bg-white p-2 text-sm text-[#24312C] shadow-lg"
                        >
                            <h2 id="sellerAccountHeading" class="truncate px-3 py-2 font-semibold text-[#173F35]">{{ $sellerName }}</h2>
                            <span role="link" aria-disabled="true" class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[#89958F]">
                                <i data-lucide="user-round" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                                <span>My Profile <span class="block text-[10px]">Coming soon</span></span>
                            </span>
                            <button
                                id="sellerAccountLogout"
                                type="button"
                                aria-haspopup="dialog"
                                aria-controls="sellerLogoutDialog"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left font-medium text-[#173F35] transition hover:bg-[#F3F7F5] focus:outline-none focus:ring-2 focus:ring-[#173F35]/40"
                            >
                                <i data-lucide="log-out" class="h-[18px] w-[18px] shrink-0" aria-hidden="true"></i>
                                Sign Out
                            </button>
                        </div>
                    </div>

                </div>

            </div>

        </header>


        {{-- PAGE --}}
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

        document.addEventListener('DOMContentLoaded', function () {

            const sidebar =
                document.getElementById('sellerSidebar');

            const overlay =
                document.getElementById('sellerSidebarOverlay');

            const openButton =
                document.getElementById('sellerSidebarOpen');

            const collapseButton =
                document.getElementById('sellerSidebarCollapse');

            const root =
                document.documentElement;


            const logoutForm = document.getElementById('sellerLogoutForm');
            const logoutTrigger = document.getElementById('sellerLogoutTrigger');
            const logoutDialog = document.getElementById('sellerLogoutDialog');
            const cancelLogout = document.getElementById('sellerLogoutCancel');
            const confirmLogout = document.getElementById('sellerLogoutConfirm');
            let logoutSubmitting = false;
            let logoutReturnFocus = logoutTrigger;
            const accountToggle = document.getElementById('sellerAccountToggle');
            const accountLogout = document.getElementById('sellerAccountLogout');
            const dropdowns = [
                {
                    control: document.getElementById('sellerNotificationsControl'),
                    toggle: document.getElementById('sellerNotificationsToggle'),
                    panel: document.getElementById('sellerNotificationsPanel'),
                },
                {
                    control: document.getElementById('sellerAccountControl'),
                    toggle: accountToggle,
                    panel: document.getElementById('sellerAccountPanel'),
                },
            ];
            let activeDropdown = null;

            function closeDropdown(restoreFocus = false) {
                if (!activeDropdown) return;
                const previous = activeDropdown;
                activeDropdown = null;
                previous.panel.hidden = true;
                previous.toggle.setAttribute('aria-expanded', 'false');
                if (restoreFocus) previous.toggle.focus();
            }

            dropdowns.forEach(function (dropdown) {
                dropdown.toggle.addEventListener('click', function () {
                    const wasOpen = activeDropdown === dropdown;
                    closeDropdown();
                    if (!wasOpen) {
                        dropdown.panel.hidden = false;
                        dropdown.toggle.setAttribute('aria-expanded', 'true');
                        activeDropdown = dropdown;
                    }
                });
            });
            document.addEventListener('click', function (event) {
                if (activeDropdown && !activeDropdown.control.contains(event.target)) {
                    closeDropdown();
                }
            });
            document.addEventListener('focusin', function (event) {
                if (activeDropdown && !activeDropdown.control.contains(event.target)) {
                    closeDropdown();
                }
            });

            function isMobileSidebarOpen() {
                return window.innerWidth < 1024 && !sidebar.classList.contains('-translate-x-full');
            }

            function syncSidebarState() {
                const mobileOpen = isMobileSidebarOpen();
                const expanded = window.innerWidth < 1024
                    ? mobileOpen
                    : !root.classList.contains('seller-sidebar-collapsed');
                const label = expanded ? 'Collapse sidebar' : 'Expand sidebar';

                collapseButton.setAttribute('aria-expanded', String(expanded));
                collapseButton.setAttribute('aria-label', label);
                collapseButton.setAttribute('title', label);
                openButton.setAttribute('aria-expanded', String(mobileOpen));
                openButton.setAttribute('aria-label', mobileOpen ? 'Close navigation' : 'Open navigation');
                overlay.classList.toggle('hidden', !mobileOpen);
                document.body.classList.toggle('overflow-hidden', mobileOpen || logoutDialog.open);
            }

            function closeSidebar() {
                sidebar.classList.add('-translate-x-full');
                syncSidebarState();
            }

            function toggleSidebar() {
                if (window.innerWidth < 1024) {
                    sidebar.classList.toggle('-translate-x-full');
                } else {
                    root.classList.toggle('seller-sidebar-collapsed');
                }
                syncSidebarState();
            }

            openButton.addEventListener('click', toggleSidebar);
            collapseButton.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', function () {
                closeSidebar();
                openButton.focus();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && activeDropdown) {
                    closeDropdown(true);
                    event.preventDefault();
                    return;
                }
                if (event.key === 'Escape' && !logoutDialog.open && isMobileSidebarOpen()) {
                    closeSidebar();
                    openButton.focus();
                }
            });
            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024) {
                    sidebar.classList.add('-translate-x-full');
                }
                syncSidebarState();
            });

            function openLogoutDialog(returnFocus = logoutTrigger) {
                if (!logoutDialog.open && !logoutSubmitting) {
                    logoutReturnFocus = returnFocus;
                    closeDropdown();
                    logoutDialog.showModal();
                    cancelLogout.focus();
                    syncSidebarState();
                }
            }

            logoutTrigger.addEventListener('click', function () {
                openLogoutDialog();
            });
            accountLogout.addEventListener('click', function () {
                openLogoutDialog(accountToggle);
            });
            cancelLogout.addEventListener('click', function () {
                logoutDialog.close();
            });
            logoutDialog.addEventListener('close', function () {
                syncSidebarState();
                logoutReturnFocus.focus();
            });
            logoutForm.addEventListener('submit', function (event) {
                if (logoutSubmitting) {
                    event.preventDefault();
                    return;
                }
                if (!logoutDialog.open || event.submitter !== confirmLogout) {
                    event.preventDefault();
                    openLogoutDialog();
                    return;
                }

                // Allow the original CSRF-protected POST only after confirmation.
                logoutSubmitting = true;
                confirmLogout.disabled = true;
                cancelLogout.disabled = true;
            });

            syncSidebarState();

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        });

    </script>

</body>

</html>