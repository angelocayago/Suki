<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'SUKI Rider' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-[#F7F9F8] text-[#111827] antialiased">

<div class="min-h-screen">


    {{-- =====================================================
         TOP HEADER
    ====================================================== --}}

    <header class="fixed top-0 left-0 right-0 h-[110px] bg-white border-b border-gray-200 z-50">

        <div class="h-full flex items-center justify-between">


            {{-- =================================================
                 LEFT BRANDING
                 SAME WIDTH AS SIDEBAR
            ================================================== --}}

            <div class="w-[220px] h-full shrink-0 flex items-center px-4">

    <a
        href="{{ route('rider.dashboard') }}"
        class="flex items-center shrink-0"
    >

        <img
            src="{{ asset('images/suki-rider.jpg') }}"
            alt="SUKI Rider"
            class="w-[82px] h-[58px] object-contain"
        >

    </a>

    <div class="ml-2 pl-2 border-l border-gray-200">

        <p class="text-[16px] font-bold text-[#075C4A] whitespace-nowrap leading-tight">
            SUKI Logistics
        </p>

        <p class="text-[9px] text-gray-400 mt-0.5 whitespace-nowrap">
            Rider Portal
        </p>

    </div>

</div>
            {{-- =================================================
                 RIGHT SIDE
            ================================================== --}}

            <div class="hidden sm:flex items-center gap-6 pr-8">


                {{-- MARKETPLACE --}}

                <a
                    href="{{ route('buyer.home') }}"
                    class="flex items-center gap-2 text-sm text-gray-600 hover:text-[#075C4A] transition"
                >

                    <i
                        data-lucide="store"
                        class="w-4 h-4"
                    ></i>

                    <span>
                        Marketplace
                    </span>

                </a>


                <div class="h-7 w-px bg-gray-200"></div>


                {{-- RIDER --}}

                <div class="flex items-center gap-3">

                    <div class="w-10 h-10 rounded-full bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="bike"
                            class="w-5 h-5 text-[#075C4A]"
                        ></i>

                    </div>

                    <span class="text-sm font-medium text-gray-800">
                        Rider
                    </span>

                </div>

            </div>

        </div>

    </header>



    {{-- =====================================================
         DESKTOP SIDEBAR
    ====================================================== --}}

    <aside class="hidden lg:flex fixed left-0 top-[110px] bottom-0 w-[280px] bg-[#075C4A] text-white flex-col z-40">


        {{-- =================================================
             RIDER PROFILE
        ================================================== --}}

        <div class="px-6 py-6 border-b border-white/15">

            <div class="flex items-center gap-4">


                {{-- PROFILE ICON --}}

                <div class="w-14 h-14 shrink-0 rounded-full bg-white/10 border border-white/20 flex items-center justify-center">

                    <i
                        data-lucide="user-round"
                        class="w-7 h-7 text-white"
                    ></i>

                </div>


                {{-- PROFILE DETAILS --}}

                <div class="min-w-0">

                    @php
                        $sidebarRider = session('rider_application', []);

                        $sidebarName = trim(
                            ($sidebarRider['first_name'] ?? '') . ' ' .
                            ($sidebarRider['last_name'] ?? '')
                        );

                        if ($sidebarName === '') {
                            $sidebarName = 'Rider';
                        }
                    @endphp

                    <p class="font-semibold text-sm truncate">
                        {{ $sidebarName }}
                    </p>

                    <p class="text-xs text-white/70 mt-0.5">
                        SUKI Rider
                    </p>


                    <div class="flex items-center gap-1.5 mt-2">

                        <span class="text-xs px-2 py-0.5 rounded-md bg-white/10">
                            4.9
                        </span>

                        <i
                            data-lucide="star"
                            class="w-3.5 h-3.5 text-amber-300"
                        ></i>

                    </div>

                </div>

            </div>

        </div>



        {{-- =================================================
             SIDEBAR MENU
        ================================================== --}}

        <div class="px-4 py-6 flex-1 overflow-y-auto">


            {{-- MAIN MENU --}}

            <p class="px-4 mb-3 text-[11px] font-semibold tracking-wider text-white/55 uppercase">
                Main Menu
            </p>


            <nav class="space-y-1">


                {{-- DASHBOARD --}}

                <a
                    href="{{ route('rider.dashboard') }}"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium
                    {{ request()->routeIs('rider.dashboard')
                        ? 'bg-white/10 text-white'
                        : 'text-white/80 hover:bg-white/10 hover:text-white' }}
                    transition"
                >

                    <i
                        data-lucide="layout-dashboard"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Dashboard
                    </span>

                </a>


                {{-- DELIVERIES --}}

                <a
                    href="{{ route('rider.deliveries') }}"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium
                    {{ request()->routeIs('rider.deliveries')
                        ? 'bg-white/10 text-white'
                        : 'text-white/80 hover:bg-white/10 hover:text-white' }}
                    transition"
                >

                    <i
                        data-lucide="package"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Deliveries
                    </span>

                </a>


                {{-- EARNINGS --}}

                <a
                    href="{{ route('rider.earnings') }}"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium
                    {{ request()->routeIs('rider.earnings')
                        ? 'bg-white/10 text-white'
                        : 'text-white/80 hover:bg-white/10 hover:text-white' }}
                    transition"
                >

                    <i
                        data-lucide="wallet"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Earnings
                    </span>

                </a>

            </nav>



            {{-- DIVIDER --}}

            <div class="border-t border-white/15 my-6"></div>



            {{-- OTHER --}}

            <p class="px-4 mb-3 text-[11px] font-semibold tracking-wider text-white/55 uppercase">
                Other
            </p>


            <nav class="space-y-1">


                {{-- PROFILE --}}

                <a
                    href="#"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white transition"
                >

                    <i
                        data-lucide="user-round"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Profile
                    </span>

                </a>


                {{-- SETTINGS --}}

                <a
                    href="#"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white transition"
                >

                    <i
                        data-lucide="settings"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Settings
                    </span>

                </a>


                {{-- HELP CENTER --}}

                <a
                    href="#"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white transition"
                >

                    <i
                        data-lucide="circle-help"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Help Center
                    </span>

                </a>

            </nav>

        </div>



        {{-- =================================================
             LOGOUT
        ================================================== --}}

        <div class="px-4 py-5 border-t border-white/15">

            <form
                method="POST"
                action="{{ route('logout') }}"
            >

                @csrf

                <button
                    type="submit"
                    class="w-full flex items-center gap-4 px-4 py-3 rounded-xl text-sm font-medium text-white/80 hover:bg-white/10 hover:text-white transition"
                >

                    <i
                        data-lucide="log-out"
                        class="w-5 h-5"
                    ></i>

                    <span>
                        Logout
                    </span>

                </button>

            </form>

        </div>

    </aside>



    {{-- =====================================================
         MOBILE HEADER
    ====================================================== --}}

    <header class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-white border-b border-gray-200 z-50">

        <div class="h-full px-4 flex items-center justify-between">

            <a href="{{ route('rider.dashboard') }}">

                <img
                    src="{{ asset('images/suki-rider.jpg') }}"
                    alt="SUKI Rider"
                    class="h-11 w-auto object-contain"
                >

            </a>


            <a
                href="{{ route('buyer.home') }}"
                class="flex items-center gap-2 text-sm text-gray-600"
            >

                <i
                    data-lucide="store"
                    class="w-4 h-4"
                ></i>

                <span>
                    Marketplace
                </span>

            </a>

        </div>

    </header>



    {{-- =====================================================
         MAIN CONTENT
    ====================================================== --}}

    <div class="lg:ml-[280px] pt-[110px] min-h-screen">


        <main class="min-h-screen">

            @yield('content')

        </main>



        {{-- =================================================
             FOOTER
        ================================================== --}}

        <footer class="border-t border-gray-200 bg-white">

            <div class="px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">

                <p class="text-xs text-gray-500">
                    © {{ date('Y') }} SUKI Logistics. All rights reserved.
                </p>

                <p class="text-xs text-gray-400">
                    Rider Portal v1.0.0
                </p>

            </div>

        </footer>

    </div>

</div>



@stack('scripts')


<script>
    document.addEventListener('DOMContentLoaded', function () {

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

    });
</script>

</body>
</html>