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

    $shopName = $sellerProfile['shop_name']
        ?? 'Everyday Finds PH';

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
            class="flex h-[82px]
                   items-center
                   border-b border-white/10
                   px-6"
        >

            <a
                href="{{ route('seller.dashboard') }}"
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
                        Seller Centre
                    </p>

                </div>

            </a>

        </div>


        {{-- STORE --}}
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
                            data-lucide="store"
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
                            {{ $shopName }}
                        </p>

                        <p
                            class="mt-0.5
                                   text-[10px]
                                   text-white/45"
                        >
                            Active seller account
                        </p>

                    </div>

                </div>

            </div>

        </div>


        {{-- NAVIGATION --}}
        <nav class="flex-1 overflow-y-auto px-4 py-5">

            <p
                class="mb-2 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                Workspace
            </p>


            <div class="space-y-1">


                {{-- DASHBOARD --}}
                <a
                    href="{{ route('seller.dashboard') }}"
                    class="
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

                    <span>Dashboard</span>

                </a>


                {{-- ORDERS --}}
                <a
                    href="{{ route('seller.orders') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.orders')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="shopping-bag"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>Orders</span>

                </a>


                {{-- PRODUCTS --}}
                <a
                    href="{{ route('seller.products') }}"
                    class="
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

                    <span>Products</span>

                </a>


                {{-- INVENTORY --}}
                <a
                    href="{{ route('seller.inventory') }}"
                    class="
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

                    <span>Inventory</span>

                </a>


                {{-- REPORTS --}}
                <a
                    href="{{ route('seller.reports') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition
                        {{ request()->routeIs('seller.reports')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="chart-no-axes-combined"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>Reports</span>

                </a>

            </div>


            {{-- STORE SECTION --}}
            <p
                class="mb-2 mt-7 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                Store
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
                        data-lucide="external-link"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span>View Marketplace</span>

                </a>

            </div>

        </nav>


        {{-- SIDEBAR FOOTER --}}
        <div
            class="border-t border-white/10
                   p-4"
        >

            <div
                class="mb-3
                       flex items-center gap-3
                       rounded-xl
                       px-2 py-2"
            >

                <div
                    class="flex h-9 w-9
                           shrink-0
                           items-center justify-center
                           rounded-xl
                           bg-white/10"
                >

                    <i
                        data-lucide="user-round"
                        class="h-4 w-4 text-white/75"
                    ></i>

                </div>


                <div class="min-w-0 flex-1">

                    <p
                        class="truncate
                               text-xs
                               font-semibold
                               text-white"
                    >
                        {{ $sellerName }}
                    </p>

                    <p
                        class="truncate
                               text-[9px]
                               uppercase
                               tracking-[0.1em]
                               text-white/35"
                    >
                        Seller
                    </p>

                </div>

            </div>


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
        MAIN
    ========================================================== --}}

    <div class="min-h-screen lg:pl-[270px]">


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
                           lg:hidden"
                    aria-label="Open navigation"
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

                </div>


                {{-- ACTIONS --}}
                <div class="flex items-center gap-2">

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


            function openSidebar() {

                if (!sidebar || !overlay) {
                    return;
                }

                sidebar.classList.remove('-translate-x-full');

                overlay.classList.remove('hidden');

                document.body.classList.add('overflow-hidden');

            }


            function closeSidebar() {

                if (!sidebar || !overlay) {
                    return;
                }

                sidebar.classList.add('-translate-x-full');

                overlay.classList.add('hidden');

                document.body.classList.remove('overflow-hidden');

            }


            if (openButton) {

                openButton.addEventListener(
                    'click',
                    openSidebar
                );

            }


            if (overlay) {

                overlay.addEventListener(
                    'click',
                    closeSidebar
                );

            }


            window.addEventListener(
                'resize',
                function () {

                    if (window.innerWidth >= 1024) {

                        overlay?.classList.add('hidden');

                        document.body.classList.remove(
                            'overflow-hidden'
                        );

                    }

                }
            );


            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        });

    </script>

</body>

</html>