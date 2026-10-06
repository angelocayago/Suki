<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'Admin') | SUKI SHOP
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen bg-[#F6F8F6] text-[#202421] antialiased">

    <div class="min-h-screen lg:flex">

        {{-- =====================================================
            MOBILE OVERLAY
        ====================================================== --}}
        <div
            id="admin-sidebar-overlay"
            class="fixed inset-0 z-40 hidden bg-black/30 lg:hidden"
        ></div>


        {{-- =====================================================
            SIDEBAR
        ====================================================== --}}
        <aside
            id="admin-sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-[260px] -translate-x-full flex-col border-r border-[#E4E9E6] bg-white transition-transform duration-200 lg:static lg:translate-x-0"
        >

            {{-- BRAND --}}
            <div class="flex h-[72px] items-center justify-between border-b border-[#EDF0EE] px-6">

                <a
                    href="{{ route('admin.dashboard') }}"
                    class="flex items-center gap-3"
                >

                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#173F35] text-sm font-bold text-white">
                        S
                    </div>

                    <div>
                        <p class="text-sm font-bold tracking-[-0.02em] text-[#202421]">
                            SUKI SHOP
                        </p>

                        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#8A918C]">
                            Admin Portal
                        </p>
                    </div>

                </a>


                <button
                    id="admin-sidebar-close"
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-[#747C76] hover:bg-[#F3F5F4] lg:hidden"
                    aria-label="Close navigation"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>

            </div>


            {{-- NAVIGATION --}}
            <nav class="flex-1 overflow-y-auto px-4 py-6">

                <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A0A6A2]">
                    Management
                </p>


                {{-- DASHBOARD --}}
                <a
                    href="{{ route('admin.dashboard') }}"
                    class="
                        mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                        {{ request()->routeIs('admin.dashboard')
                            ? 'bg-[#EDF5F1] text-[#173F35]'
                            : 'text-[#69716C] hover:bg-[#F5F7F6] hover:text-[#29302C]'
                        }}
                    "
                >

                    <svg
                        class="h-[18px] w-[18px] shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>

                    <span>
                        Dashboard
                    </span>

                </a>


                {{-- BUYERS --}}
                <a
                    href="{{ route('admin.buyers') }}"
                    class="
                        mb-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition
                        {{ request()->routeIs('admin.buyers*')
                            ? 'bg-[#EDF5F1] text-[#173F35]'
                            : 'text-[#69716C] hover:bg-[#F5F7F6] hover:text-[#29302C]'
                        }}
                    "
                >

                    <svg
                        class="h-[18px] w-[18px] shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    >
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/>
                        <path d="M16 11h6"/>
                        <path d="M19 8v6"/>
                    </svg>

                    <span>
                        Buyer Applications
                    </span>

                </a>

            </nav>


            {{-- ADMIN ACCOUNT --}}
            <div class="border-t border-[#EDF0EE] p-4">

                <div class="mb-3 flex items-center gap-3 rounded-xl bg-[#F8FAF8] p-3">

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#DDEBE4] text-xs font-bold text-[#173F35]">
                        {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>

                    <div class="min-w-0 flex-1">

                        <p class="truncate text-xs font-semibold text-[#29302C]">
                            {{ auth()->user()->full_name ?? auth()->user()->name ?? 'Administrator' }}
                        </p>

                        <p class="truncate text-[11px] text-[#8A918C]">
                            {{ auth()->user()->email ?? '' }}
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
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#DEE3E0] bg-white px-4 py-2.5 text-xs font-semibold text-[#59615C] transition hover:bg-[#F5F7F6] hover:text-[#202421]"
                    >

                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path d="M10 17l5-5-5-5"/>
                            <path d="M15 12H3"/>
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        </svg>

                        Sign out
                    </button>
                </form>

            </div>

        </aside>


        {{-- =====================================================
            MAIN AREA
        ====================================================== --}}
        <div class="min-w-0 flex-1">

            {{-- TOPBAR --}}
            <header class="sticky top-0 z-30 border-b border-[#E5E9E6] bg-white/95 backdrop-blur">

                <div class="flex h-[72px] items-center justify-between px-4 sm:px-6 lg:px-8">

                    <div class="flex min-w-0 items-center gap-3">

                        <button
                            id="admin-sidebar-open"
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#E2E7E4] text-[#5F6762] hover:bg-[#F5F7F6] lg:hidden"
                            aria-label="Open navigation"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M4 7h16"/>
                                <path d="M4 12h16"/>
                                <path d="M4 17h16"/>
                            </svg>
                        </button>


                        <div class="min-w-0">

                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#98A09A]">
                                SUKI Administration
                            </p>

                            <h1 class="truncate text-sm font-semibold text-[#29302C]">
                                @yield('page-heading', 'Admin')
                            </h1>

                        </div>

                    </div>


                    <div class="flex items-center gap-3">

                        <div class="hidden text-right sm:block">

                            <p class="text-xs font-semibold text-[#343B36]">
                                {{ auth()->user()->first_name ?? 'Admin' }}
                            </p>

                            <p class="text-[10px] uppercase tracking-wide text-[#929994]">
                                Administrator
                            </p>

                        </div>


                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#173F35] text-xs font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->first_name ?? auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>

                    </div>

                </div>

            </header>


            {{-- =====================================================
                FLASH MESSAGES
            ====================================================== --}}
            <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">

                @if(session('success'))

                    <div class="mb-5 rounded-xl border border-[#CCE3D8] bg-[#F0F8F4] px-4 py-3 text-sm text-[#23634F]">
                        {{ session('success') }}
                    </div>

                @endif


                @if(session('error'))

                    <div class="mb-5 rounded-xl border border-[#F0D4D4] bg-[#FFF5F5] px-4 py-3 text-sm text-[#9D3F3F]">
                        {{ session('error') }}
                    </div>

                @endif


                @if($errors->any())

                    <div class="mb-5 rounded-xl border border-[#F0D4D4] bg-[#FFF5F5] px-4 py-3">

                        @foreach($errors->all() as $error)

                            <p class="text-sm text-[#9D3F3F]">
                                {{ $error }}
                            </p>

                        @endforeach

                    </div>

                @endif


                {{-- PAGE CONTENT --}}
                @yield('content')

            </main>

        </div>

    </div>


    {{-- =====================================================
        MOBILE SIDEBAR SCRIPT
    ====================================================== --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const sidebar =
                document.getElementById('admin-sidebar');

            const overlay =
                document.getElementById('admin-sidebar-overlay');

            const openButton =
                document.getElementById('admin-sidebar-open');

            const closeButton =
                document.getElementById('admin-sidebar-close');


            function openSidebar() {

                sidebar?.classList.remove('-translate-x-full');

                overlay?.classList.remove('hidden');

                document.body.classList.add('overflow-hidden');
            }


            function closeSidebar() {

                sidebar?.classList.add('-translate-x-full');

                overlay?.classList.add('hidden');

                document.body.classList.remove('overflow-hidden');
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

        });
    </script>

</body>
</html>