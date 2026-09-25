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
        @yield('title', 'Admin') | SUKI SHOP
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
        id="adminSidebarOverlay"
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
        id="adminSidebar"
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
                href="{{ route('admin.dashboard') }}"
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
                        Administration
                    </p>

                </div>

            </a>

        </div>


        {{-- ADMIN IDENTITY --}}
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
                               text-sm
                               font-bold
                               text-[#173F35]"
                    >
                        {{ strtoupper(
                            substr(
                                auth()->user()->first_name
                                    ?? auth()->user()->name
                                    ?? 'A',
                                0,
                                1
                            )
                        ) }}
                    </div>


                    <div class="min-w-0">

                        <p
                            class="truncate
                                   text-[13px]
                                   font-semibold
                                   text-white"
                        >
                            {{ auth()->user()->full_name
                                ?? auth()->user()->name
                                ?? 'Administrator' }}
                        </p>

                        <p
                            class="mt-0.5
                                   truncate
                                   text-[10px]
                                   text-white/40"
                        >
                            Administrator account
                        </p>

                    </div>

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
                Management
            </p>


            <div class="space-y-1">


                {{-- DASHBOARD --}}
                <a
                    href="{{ route('admin.dashboard') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition

                        {{ request()->routeIs('admin.dashboard')
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


                {{-- BUYER APPLICATIONS --}}
                <a
                    href="{{ route('admin.buyers') }}"
                    class="
                        flex items-center gap-3
                        rounded-xl
                        px-3 py-2.5
                        text-[13px]
                        font-medium
                        transition

                        {{ request()->routeIs('admin.buyers*')
                            ? 'bg-white text-[#173F35] shadow-sm'
                            : 'text-white/65 hover:bg-white/[0.07] hover:text-white' }}
                    "
                >

                    <i
                        data-lucide="users-round"
                        class="h-[18px] w-[18px]"
                    ></i>

                    <span class="flex-1">
                        Buyer Applications
                    </span>

                </a>

            </div>


            {{-- SYSTEM --}}
            <p
                class="mb-2 mt-7 px-3
                       text-[9px]
                       font-semibold
                       uppercase
                       tracking-[0.18em]
                       text-white/35"
            >
                System
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

                    <span>
                        View Marketplace
                    </span>

                </a>

            </div>

        </nav>


        {{-- =====================================================
            SIDEBAR FOOTER
        ====================================================== --}}

        <div
            class="border-t border-white/10
                   p-4"
        >

            <div
                class="mb-3
                       flex items-center gap-3
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
                        data-lucide="shield-check"
                        class="h-4 w-4
                               text-white/70"
                    ></i>

                </div>


                <div class="min-w-0">

                    <p
                        class="text-[10px]
                               font-semibold
                               text-white/75"
                    >
                        Admin Portal
                    </p>

                    <p
                        class="mt-0.5
                               text-[9px]
                               text-white/35"
                    >
                        Secure management access
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="{{ route('admin.logout') }}"
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


        {{-- =====================================================
            TOPBAR
        ====================================================== --}}

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
                    id="adminSidebarOpen"
                    type="button"
                    aria-label="Open admin navigation"
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


                {{-- PAGE HEADING --}}
                <div class="min-w-0 flex-1">

                    <p
                        class="text-[10px]
                               font-semibold
                               uppercase
                               tracking-[0.14em]
                               text-[#1F6F5B]"
                    >
                        SUKI Administration
                    </p>


                    <h1
                        class="truncate
                               text-[17px]
                               font-semibold
                               tracking-[-0.025em]
                               text-[#24312C]
                               sm:text-lg"
                    >
                        @yield('page-heading', 'Admin')
                    </h1>

                </div>


                {{-- RIGHT --}}
                <div
                    class="flex items-center gap-2"
                >

                    <div
                        class="hidden
                               items-center gap-2
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white
                               px-3.5 py-2.5
                               text-[10px]
                               font-medium
                               text-[#728078]
                               sm:flex"
                    >

                        <i
                            data-lucide="calendar-days"
                            class="h-3.5 w-3.5
                                   text-[#1F6F5B]"
                        ></i>

                        {{ now()->format('M d, Y') }}

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


        {{-- =====================================================
            PAGE CONTENT
        ====================================================== --}}

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
        SIDEBAR + ICON SCRIPT
    ========================================================== --}}

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                const sidebar =
                    document.getElementById(
                        'adminSidebar'
                    );

                const overlay =
                    document.getElementById(
                        'adminSidebarOverlay'
                    );

                const openButton =
                    document.getElementById(
                        'adminSidebarOpen'
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