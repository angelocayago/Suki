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


    {{-- Sidebar styles stay local to this layout; no additional Vite entry is required. --}}
    <style>
        .seller-sidebar-shell #sellerSidebar {
            position: fixed; inset: 0 auto 0 0; z-index: 50; display: flex;
            flex-direction: column; width: 270px; height: 100dvh;
            background: #173F35; color: #fff; border-right: 1px solid #DDE6E1;
            transform: translateX(-100%); transition: width .3s ease, transform .3s ease;
        }
        .seller-sidebar-shell #sellerSidebar.is-mobile-open { transform: translateX(0); }
        .seller-sidebar-shell .seller-sidebar-overlay {
            position: fixed; inset: 0; z-index: 40; background: #102C2566;
            backdrop-filter: blur(2px);
        }
        .seller-sidebar-shell .seller-sidebar-overlay[hidden] { display: none; }
        .seller-sidebar-shell .seller-sidebar-brand {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            height: 82px; flex-shrink: 0; padding: 0 20px; border-bottom: 1px solid #ffffff1a;
        }
        .seller-sidebar-shell .seller-sidebar-brand-link { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .seller-sidebar-shell .seller-sidebar-logo { display: grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 12px; background: #ffffff1a; }
        .seller-sidebar-shell .seller-sidebar-logo img { width: 36px; height: 36px; object-fit: contain; }
        .seller-sidebar-shell .seller-sidebar-brand-copy strong { display: block; font-size: 16px; letter-spacing: -.04em; }
        .seller-sidebar-shell .seller-sidebar-brand-copy small { display: block; margin-top: 2px; color: #ffffff73; font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; }
        .seller-sidebar-shell .seller-sidebar-collapse { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 12px; color: #ffffffa6; }
        .seller-sidebar-shell .seller-sidebar-collapse:hover { background: #ffffff1a; color: #fff; }
        .seller-sidebar-shell .seller-sidebar-collapse :is(i,svg) { width: 18px; height: 18px; }
        .seller-sidebar-shell .seller-sidebar-nav { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 20px 16px; }
        .seller-sidebar-shell .seller-sidebar-section-label { margin: 28px 12px 8px; color: #ffffff59; font-size: 9px; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; }
        .seller-sidebar-shell .seller-sidebar-section-label:first-child { margin-top: 0; }
        .seller-sidebar-shell .seller-sidebar-group { display: flex; flex-direction: column; gap: 4px; }
        .seller-sidebar-shell .seller-sidebar-link {
            display: flex; align-items: center; gap: 12px; min-height: 40px; padding: 10px 12px;
            border-radius: 12px; color: #ffffffa6; font-size: 13px; font-weight: 500;
            text-decoration: none; transition: color .2s ease, background .2s ease;
        }
        .seller-sidebar-shell .seller-sidebar-link:hover { background: #ffffff12; color: #fff; }
        .seller-sidebar-shell .seller-sidebar-link[aria-current="page"] { background: #fff; color: #173F35; box-shadow: 0 1px 3px #0000000d; }
        .seller-sidebar-shell .seller-sidebar-link > :is(i,svg) { width: 18px; height: 18px; flex-shrink: 0; }
        .seller-sidebar-shell .seller-sidebar-link-label { min-width: 0; overflow-wrap: anywhere; }
        .seller-sidebar-shell .seller-sidebar-notification { position: relative; }
        .seller-sidebar-shell .seller-sidebar-notification > :is(i,svg) { position: absolute; top: 12px; left: 12px; width: 18px; height: 18px; pointer-events: none; color: #ffffffa6; }
        .seller-sidebar-shell .seller-sidebar-notification:has([aria-current="page"]) > :is(i,svg) { color: #173F35; }
        .seller-sidebar-shell .seller-sidebar-notification-link { padding-left: 42px; }
        .seller-sidebar-shell .seller-sidebar-footer { flex-shrink: 0; padding: 16px; border-top: 1px solid #ffffff1a; }
        .seller-sidebar-shell .seller-sidebar-account { display: flex; align-items: center; gap: 12px; padding: 8px; margin-bottom: 12px; }
        .seller-sidebar-shell .seller-sidebar-account-icon { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 12px; background: #ffffff1a; }
        .seller-sidebar-shell .seller-sidebar-account-icon :is(i,svg) { width: 16px; height: 16px; color: #ffffffbf; }
        .seller-sidebar-shell .seller-sidebar-account-copy { min-width: 0; }
        .seller-sidebar-shell .seller-sidebar-account-copy strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
        .seller-sidebar-shell .seller-sidebar-account-copy small { display: block; margin-top: 2px; color: #ffffff59; font-size: 9px; text-transform: uppercase; letter-spacing: .1em; }
        .seller-sidebar-shell .seller-sidebar-signout { width: 100%; font-size: 12px; color: #ffffff8c; text-align: left; }
        .seller-sidebar-shell #sellerSidebar :is(a,button):focus-visible { outline: 2px solid #ffffff99; outline-offset: 2px; }
        .seller-sidebar-shell #sellerMainContent { min-width: 0; transition: padding-left .3s ease; }
        @media (min-width: 1024px) {
            .seller-sidebar-shell #sellerSidebar { transform: none; }
            .seller-sidebar-shell #sellerMainContent { padding-left: 270px; }
            .seller-sidebar-shell .seller-sidebar-overlay { display: none; }
            .seller-sidebar-shell.seller-sidebar-collapsed #sellerSidebar { width: 76px; }
            .seller-sidebar-shell.seller-sidebar-collapsed #sellerMainContent { padding-left: 76px; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-brand { justify-content: center; padding: 6px 0; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-brand-link,
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-link-label,
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-section-label,
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-account { display: none; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-collapse { width: 30px; height: 30px; border-radius: 9px; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-nav { padding-inline: 8px; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-group + .seller-sidebar-section-label + .seller-sidebar-group { margin-top: 4px; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-link { justify-content: center; height: 44px; padding: 0; gap: 0; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-notification-link { font-size: 0; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-notification-link span { display: none; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-notification > :is(i,svg) { left: 21px; top: 13px; }
            .seller-sidebar-shell.seller-sidebar-collapsed .seller-sidebar-footer { padding-inline: 8px; }
        }
        @media (max-width: 1023px) {
            .seller-sidebar-shell.seller-mobile-nav-open { overflow: hidden; }
            .seller-sidebar-shell #sellerMainContent { padding-left: 0; }
        }
        @media (prefers-reduced-motion: reduce) {
            .seller-sidebar-shell #sellerSidebar, .seller-sidebar-shell #sellerMainContent,
            .seller-sidebar-shell .seller-sidebar-link { transition: none; }
        }
    </style>

    @include('seller.header.style')
</head>


@php

    $sellerProfile = session('seller_profile', []);

    $shopName = $sellerProfile['shop_name']
        ?? 'Everyday Finds PH';

    $sellerName = $sellerProfile['seller_name']
        ?? 'Seller';

    $headerShop = auth()->user()?->sellers()
        ->with('category:id,name')
        ->where('status', 'approved')
        ->first();
    $assignedRootCategory = $headerShop?->category?->name;
    $sellerHeader = app(\App\Support\SellerNotifications::class)->header(auth()->user(), $headerShop);

@endphp



<body
    class="seller-sidebar-shell suki-dashboard-shell
           bg-[#F6F8F6]
           font-sans
           text-[#24312C]
           antialiased"
>


    {{-- =========================================================
        MOBILE OVERLAY
    ========================================================== --}}

    <div id="sellerSidebarOverlay" class="seller-sidebar-overlay" hidden aria-hidden="true"></div>


    {{-- =========================================================
        SIDEBAR
    ========================================================== --}}

    <aside id="sellerSidebar" aria-label="Seller sidebar">
        <div class="seller-sidebar-brand">
            <a href="{{ route('seller.dashboard') }}" class="seller-sidebar-brand-link" aria-label="SUKI SHOP Seller Centre">
                <span class="seller-sidebar-logo"><img src="{{ asset('images/suki-logo.png') }}" alt="SUKI SHOP"></span>
                <span class="seller-sidebar-brand-copy"><strong>SUKI SHOP</strong><small>Seller Centre</small></span>
            </a>
            <button id="sellerSidebarCollapse" type="button" class="seller-sidebar-collapse" aria-label="Collapse sidebar" title="Collapse sidebar" aria-expanded="true" aria-controls="sellerSidebar">
                <i data-lucide="menu" aria-hidden="true"></i>
            </button>
        </div>
        <nav class="seller-sidebar-nav" aria-label="Seller navigation">
            @foreach([
                'Workspace' => [
                    ['seller.dashboard', 'Dashboard', 'layout-dashboard', 'seller.dashboard*'],
                    ['seller.orders', 'Orders', 'shopping-bag', 'seller.orders*'],
                    ['seller.products', 'Products', 'package', 'seller.products*'],
                    ['seller.inventory', 'Inventory', 'boxes', 'seller.inventory*'],
                ],
                'Business' => [
                    ['seller.sales', 'Sales', 'banknote', 'seller.sales'],
                    ['seller.reports', 'Reports', 'chart-no-axes-combined', 'seller.reports'],
                    ['seller.vouchers.index', 'Vouchers', 'badge-percent', 'seller.vouchers.*'],
                    ['seller.wallet', 'Wallet & Payouts', 'wallet', 'seller.wallet'],
                ],
                'Customer Service' => [
                    ['seller.messages.index', 'Messages', 'message-circle', 'seller.messages.*'],
                    ['seller.notifications.index', 'Notifications', 'bell', 'seller.notifications.*'],
                    ['reviews.seller-page', 'Reviews', 'star', 'reviews.seller-page'],
                    ['seller.return-refunds.index', 'Returns / Refunds', 'rotate-ccw', 'seller.return-refunds.*'],
                    ['seller.complaints.index', 'Complaints', 'message-square-warning', 'seller.complaints.*'],
                ],
                'Account' => [
                    ['seller.store.profile', 'Store Profile', 'store', 'seller.store.*'],
                    ['seller.profile', 'My Profile', 'user-round', 'seller.profile*'],
                    ['buyer.shop', 'View Marketplace', 'external-link', 'buyer.shop'],
                ],
            ] as $section => $links)
                <p class="seller-sidebar-section-label">{{ $section }}</p>
                <div class="seller-sidebar-group">
                    @foreach($links as [$destination, $label, $icon, $activePattern])
                        @if($destination === 'seller.notifications.index')
                            <div class="seller-sidebar-notification">
                                <i data-lucide="bell" aria-hidden="true"></i>
                                <a href="{{ route($destination) }}" class="seller-sidebar-link seller-sidebar-notification-link" title="Notifications" aria-label="Notifications" @if(request()->routeIs($activePattern)) aria-current="page" @endif>Notifications <span data-seller-unread-badge @if(!$sellerHeader['unread_count']) hidden @endif aria-hidden="true">{{ $sellerHeader['unread_count'] ? ($sellerHeader['unread_count'] > 99 ? '99+' : $sellerHeader['unread_count']) : '' }}</span></a>
                            </div>
                        @else
                            <a href="{{ route($destination) }}" class="seller-sidebar-link" title="{{ $label }}" aria-label="{{ $label }}" @if(request()->routeIs($activePattern)) aria-current="page" @endif>
                                <i data-lucide="{{ $icon }}" aria-hidden="true"></i>
                                <span class="seller-sidebar-link-label">{{ $label }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="seller-sidebar-footer">
            <form action="{{ route('logout') }}" method="POST" data-seller-logout>
                @csrf
                <button type="submit" class="seller-sidebar-link seller-sidebar-signout" title="Sign Out" aria-label="Sign Out">
                    <i data-lucide="log-out" aria-hidden="true"></i>
                    <span class="seller-sidebar-link-label">Sign Out</span>
                </button>
            </form>
        </div>
    </aside>


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
                class="seller-topbar-row flex min-h-[74px]
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
                <div class="seller-header-actions flex items-center gap-2">

                    <a
                        href="{{ $sellerHeader['store_url'] ?? route('buyer.home') }}"
                        target="_blank"
                        rel="noopener noreferrer"
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
                        aria-label="Add Product"
                        title="Add Product"
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

                    @include('seller.header.controls')
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


    @include('seller.logout-modal')
    @include('seller.header.script')
    @stack('scripts')


    {{-- =========================================================
        SIDEBAR SCRIPT
    ========================================================== --}}

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sellerSidebar');
            const overlay = document.getElementById('sellerSidebarOverlay');
            const openButton = document.getElementById('sellerSidebarOpen');
            const collapseButton = document.getElementById('sellerSidebarCollapse');
            const main = document.getElementById('sellerMainContent');
            if (!sidebar || !overlay || !openButton || !collapseButton || !main || sidebar.dataset.initialized) return;
            sidebar.dataset.initialized = 'true';

            const mobile = () => window.innerWidth < 1024;
            const mobileOpen = () => mobile() && sidebar.classList.contains('is-mobile-open');
            function syncSidebar() {
                const visible = mobileOpen();
                const expanded = mobile() ? visible : !document.body.classList.contains('seller-sidebar-collapsed');
                sidebar.inert = mobile() && !visible;
                main.inert = visible;
                overlay.hidden = !visible;
                document.body.classList.toggle('seller-mobile-nav-open', visible);
                openButton.setAttribute('aria-expanded', String(visible));
                collapseButton.setAttribute('aria-expanded', String(expanded));
                const label = mobile() ? 'Close navigation' : (expanded ? 'Collapse sidebar' : 'Expand sidebar');
                collapseButton.setAttribute('aria-label', label);
                collapseButton.setAttribute('title', label);
            }
            function closeSidebar(restoreFocus = true) {
                sidebar.classList.remove('is-mobile-open');
                syncSidebar();
                if (restoreFocus && mobile()) openButton.focus();
            }
            openButton.addEventListener('click', function () {
                sidebar.classList.add('is-mobile-open');
                syncSidebar();
                collapseButton.focus();
            });
            collapseButton.addEventListener('click', function () {
                if (mobile()) closeSidebar();
                else {
                    document.body.classList.toggle('seller-sidebar-collapsed');
                    syncSidebar();
                }
            });
            overlay.addEventListener('click', () => closeSidebar());
            document.addEventListener('keydown', function (event) {
                if (!mobileOpen()) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeSidebar();
                } else if (event.key === 'Tab') {
                    const controls = [...sidebar.querySelectorAll('a[href], button:not([disabled])')]
                        .filter(control => control.getClientRects().length);
                    const first = controls[0], last = controls[controls.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault(); last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault(); first.focus();
                    }
                }
            });
            window.addEventListener('resize', function () {
                const focusWasInSidebar = sidebar.contains(document.activeElement);
                closeSidebar(false);
                if (mobile() && focusWasInSidebar) openButton.focus();
            });
            syncSidebar();
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }, { once: true });
    </script>

</body>

</html>
