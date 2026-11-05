<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'SUKI Admin Portal')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
    @include('components.theme-preload')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

</head>


<body class="suki-dashboard-shell">


<div
    x-data="{
        date: '',
        time: '',

        init() {
            this.updateClock();

            setInterval(() => {
                this.updateClock();
            }, 1000);
        },

        updateClock() {
            const now = new Date();

            this.date = now.toLocaleDateString(
                'en-US',
                {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                }
            );

            this.time = now.toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }
            );
        }
    }"
    class="min-h-screen"
>



    <!-- SIDEBAR -->

    <aside
        id="admin-sidebar"
        class="
            fixed
            left-0
            top-0
            bottom-0
            z-40
            w-[220px]
            bg-[#064e3b]
            text-white
            flex
            flex-col
        "
    >


        <!-- BRAND -->

        <div
            class="
                shrink-0
                px-4
                py-4
                border-b
                border-white/10
            "
        >

            <button type="button" class="admin-sidebar-collapse" id="admin-sidebar-collapse" aria-label="Collapse sidebar" aria-expanded="true" aria-controls="admin-sidebar"><i data-lucide="panel-left-close" aria-hidden="true"></i></button>

            <div class="flex items-center gap-3"><x-profile-avatar :user="auth()->user()" size="h-9 w-9" /><div><h1
                class="
                    font-bold
                    text-lg
                "
            >
                Admin Portal
            </h1>

            <p
                class="
                    text-xs
                    text-green-200
                    mt-1
                "
            >
                Platform Control Center
            </p></div></div>

        </div>



        <!-- MENU -->

<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Admin navigation">
    @php
        $adminNavGroups = [
            'accounts' => [
                'label' => 'Accounts & Access', 'icon' => 'users',
                'active' => request()->routeIs('admin.users*', 'admin.buyers*', 'admin.sellers*', 'admin.applications*'),
            ],
            'marketplace' => [
                'label' => 'Marketplace', 'icon' => 'store',
                'active' => request()->routeIs('admin.products.*', 'reviews.*', 'admin.compliance.*'),
            ],
            'support' => [
                'label' => 'Orders & Support', 'icon' => 'messages-square',
                'active' => request()->routeIs('admin.orders*', 'admin.return-refunds.*', 'admin.complaints.*', 'admin.messages.*', 'admin.notifications*'),
            ],
            'finance' => [
                'label' => 'Finance & Analytics', 'icon' => 'chart-no-axes-combined',
                'active' => request()->routeIs('admin.commission*', 'admin.reports*'),
            ],
            'system' => [
                'label' => 'System', 'icon' => 'settings-2',
                'active' => request()->routeIs('admin.settings*'),
            ],
        ];
    @endphp

    <a href="{{ route('admin.dashboard') }}"
       @class(['admin-nav-link mb-2', 'is-active' => request()->routeIs('admin.dashboard')])
       @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
        <i data-lucide="layout-dashboard" aria-hidden="true"></i><span>Dashboard</span>
    </a>

    @foreach($adminNavGroups as $groupKey => $group)
        <section class="admin-nav-group" data-admin-nav-group>
            <button type="button" class="admin-nav-toggle" data-active="{{ $group['active'] ? 'true' : 'false' }}" aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                    aria-controls="admin-nav-{{ $groupKey }}" data-admin-nav-toggle>
                <i data-lucide="{{ $group['icon'] }}" aria-hidden="true"></i>
                <span>{{ $group['label'] }}</span>
                <i data-lucide="chevron-down" class="admin-nav-chevron" aria-hidden="true"></i>
            </button>
            <div id="admin-nav-{{ $groupKey }}" class="admin-nav-children" @if(!$group['active']) hidden @endif>
                @if($groupKey === 'accounts')
                    <a href="{{ route('admin.users') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.users*')]) @if(request()->routeIs('admin.users*')) aria-current="page" @endif>User Management</a>
                    <a href="{{ route('admin.buyers') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.buyers*')]) @if(request()->routeIs('admin.buyers*')) aria-current="page" @endif>Buyer Accounts</a>
                    <a href="{{ route('admin.sellers') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.sellers*')]) @if(request()->routeIs('admin.sellers*')) aria-current="page" @endif>Seller Accounts</a>
                    <a href="{{ route('admin.applications') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.applications*')]) @if(request()->routeIs('admin.applications*')) aria-current="page" @endif>Registration Approvals</a>
                @elseif($groupKey === 'marketplace')
                    <a href="{{ route('admin.products.index') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.products.*')]) @if(request()->routeIs('admin.products.*')) aria-current="page" @endif>Product Moderation</a>
                    <a href="{{ route('reviews.admin-page') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('reviews.*')]) @if(request()->routeIs('reviews.*')) aria-current="page" @endif>Review Moderation</a>
                    <a href="{{ route('admin.compliance.index') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.compliance.*')]) @if(request()->routeIs('admin.compliance.*')) aria-current="page" @endif>Seller Compliance</a>
                @elseif($groupKey === 'support')
                    <a href="{{ route('admin.orders') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.orders*')]) @if(request()->routeIs('admin.orders*')) aria-current="page" @endif>Order Management</a>
                    <a href="{{ route('admin.return-refunds.index') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.return-refunds.*')]) @if(request()->routeIs('admin.return-refunds.*')) aria-current="page" @endif>Returns / Refunds</a>
                    <a href="{{ route('admin.complaints.index') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.complaints.*')]) @if(request()->routeIs('admin.complaints.*')) aria-current="page" @endif>Complaints &amp; Disputes</a>
                    <a href="{{ route('admin.messages.index') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.messages.*')]) @if(request()->routeIs('admin.messages.*')) aria-current="page" @endif>Messages</a>
                @elseif($groupKey === 'finance')
                    <a href="{{ route('admin.commission') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.commission*')]) @if(request()->routeIs('admin.commission*')) aria-current="page" @endif>Commission</a>
                    <a href="{{ route('admin.reports') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.reports*')]) @if(request()->routeIs('admin.reports*')) aria-current="page" @endif>Reports</a>
                @elseif($groupKey === 'system')
                    <a href="{{ route('admin.settings') }}" @class(['admin-nav-child', 'is-active' => request()->routeIs('admin.settings*')]) @if(request()->routeIs('admin.settings*')) aria-current="page" @endif>Platform Settings</a>
                @endif
            </div>
        </section>
    @endforeach
</nav>


        <!-- SIGN OUT -->

        <div
            class="
                shrink-0
                border-t
                border-white/10
                px-5
                py-5
                bg-[#064e3b]
            "
        >

            <form
                method="POST"
                action="{{ route('admin.logout') }}"
                data-confirm-logout
            >

                @csrf

                <button
                    type="submit"
                    class="
                        w-full
                        text-left
                        px-3
                        py-2
                        rounded-lg
                        text-sm
                        font-medium
                        text-green-100
                        hover:bg-[#047857]
                        hover:text-white
                        transition
                    "
                >
                    Sign Out
                </button>

            </form>

        </div>


    </aside>

    <button type="button" id="admin-sidebar-overlay" class="admin-sidebar-overlay" aria-label="Close navigation" hidden></button>




    <!-- MAIN AREA -->

    <div
        class="
            min-h-screen
            lg:ml-[220px]
            min-w-0
        "
    >



        <!-- TOPBAR -->

        <header
            class="
                h-16
                bg-white
                border-b
                border-[#E3EAE6]
                flex
                items-center
                justify-between
                px-6
                lg:px-8
            "
        >

            <button type="button" id="admin-sidebar-open" class="admin-mobile-menu" aria-label="Open navigation" aria-controls="admin-sidebar" aria-expanded="false">
                <i data-lucide="menu" aria-hidden="true"></i>
            </button>


            <!-- SEARCH -->

            <div
                class="
                    hidden
                    sm:flex
                    bg-[#F4F7F5]
                    rounded-xl
                    px-4
                    py-3
                    w-[330px]
                    items-center
                    gap-3
                    text-sm
                    text-gray-400
                "
            >

                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.8"
                    stroke="currentColor"
                    class="w-5 h-5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.197 5.197a7.5 7.5 0 0 0 10.606 10.606Z"
                    />
                </svg>

                <span>
                    Search users, orders, sellers...
                </span>

            </div>



            <!-- RIGHT SIDE -->

            <div
                class="
                    ml-auto
                    flex
                    items-center
                    gap-4
                    lg:gap-5
                "
            >


                <!-- DATE / TIME -->

                <div class="text-right">

                    <p
                        class="
                            font-semibold
                            text-sm
                            text-[#173F35]
                        "
                        x-text="date"
                    ></p>

                    <p
                        class="
                            text-xs
                            text-gray-500
                        "
                        x-text="time"
                    ></p>

                </div>



                <x-theme-toggle />
                <x-notification-bell :href="route('notifications.page')" label="Admin notifications" />


            </div>


        </header>




        <!-- CONTENT -->

        <main
            class="
                p-4
                md:p-5
                lg:p-6
            "
        >


            <!-- SUCCESS TOAST -->

            @if(session('success'))

                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-cloak
                    x-transition
                    x-init="setTimeout(() => show = false, 5000)"
                    class="
                        fixed
                        top-6
                        right-6
                        z-50
                        bg-white
                        border
                        border-green-200
                        shadow-xl
                        rounded-2xl
                        px-6
                        py-4
                        flex
                        items-center
                        gap-4
                        max-w-sm
                    "
                >

                    <div
                        class="
                            w-10
                            h-10
                            rounded-full
                            bg-green-100
                            text-green-600
                            flex
                            items-center
                            justify-center
                            font-bold
                        "
                    >
                        ✓
                    </div>


                    <div class="flex-1">

                        <p
                            class="
                                font-semibold
                                text-[#173F35]
                            "
                        >
                            Success
                        </p>

                        <p
                            class="
                                text-sm
                                text-gray-500
                            "
                        >
                            {{ session('success') }}
                        </p>

                    </div>


                    <button
                        type="button"
                        @click="show = false"
                        class="
                            text-gray-400
                            hover:text-gray-700
                            transition
                        "
                    >
                        ✕
                    </button>

                </div>

            @endif

            @if(session('error') || $errors->any())

                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700" role="alert">
                    <p class="font-semibold">The requested action could not be completed.</p>
                    <p class="mt-1">{{ session('error') ?? $errors->first() }}</p>
                </div>

            @endif



            @yield('content')


        </main>


    </div>


</div>



<style>

    [x-cloak] {
        display: none !important;
    }

    body.suki-dashboard-shell { background: #f8faf8; color: #173f35; font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif; }
    .suki-dashboard-shell aside { background: #173f35; border-right: 0; }
    .suki-dashboard-shell aside > div:first-child { padding: 20px 18px; border-color: rgba(255,255,255,.12); }
    .admin-nav-link, .admin-nav-toggle, .admin-nav-child { display:flex; align-items:center; gap:10px; width:100%; min-height:36px; padding:8px 10px; border:0; border-radius:6px; background:transparent; color:#e1eee8; font:inherit; font-size:12px; text-align:left; text-decoration:none; transition:background-color .15s,color .15s; }
    .admin-nav-link svg, .admin-nav-toggle svg { width:16px; height:16px; flex:none; }
    .admin-nav-link:hover, .admin-nav-toggle:hover, .admin-nav-child:hover { background:#245c4c; color:white; }
    .admin-nav-link.is-active, .admin-nav-child.is-active { background:#dcece4; color:#173f35; font-weight:600; }
    .admin-nav-toggle { justify-content:flex-start; cursor:pointer; color:#c4d7cd; font-weight:600; }
    .admin-nav-toggle[aria-expanded="true"] { color:white; }
    .admin-nav-toggle[data-active="true"] { color:#fff; }
    .admin-nav-toggle .admin-nav-chevron { width:14px; height:14px; margin-left:auto; transition:transform .15s; }
    .admin-nav-toggle[aria-expanded="true"] .admin-nav-chevron { transform:rotate(180deg); }
    .admin-nav-children { margin:2px 0 8px 25px; padding-left:8px; border-left:1px solid rgba(223,239,231,.22); }
    .admin-nav-child { min-height:32px; padding:7px 9px; color:#d6e5de; font-size:11px; }
    .admin-nav-notification a { display:flex; align-items:center; min-height:32px; padding:7px 9px; color:#d6e5de; border-radius:6px; font-size:11px; text-decoration:none; }
    .admin-nav-notification a:hover { background:#245c4c; color:white; }
    .admin-nav-toggle:focus-visible, .admin-nav-link:focus-visible, .admin-nav-child:focus-visible, .admin-nav-notification a:focus-visible { outline:2px solid #b9e4d2; outline-offset:2px; }
    .suki-dashboard-shell header { background:#fff; border-color:#e3eae6; }
    .suki-dashboard-shell main { padding-top:24px; }
    main table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    main thead th {
        white-space: nowrap;
        letter-spacing: 0.02em;
    }

    main tbody tr {
        transition: background-color 150ms ease;
    }

    main tbody tr:hover {
        background-color: #f8faf9;
    }


    .admin-mobile-menu { display:none; width:36px; height:36px; align-items:center; justify-content:center; border:1px solid #e3eae6; border-radius:6px; color:#31594a; }
    .admin-mobile-menu svg { width:18px; height:18px; }
    .admin-sidebar-overlay { display:none; }
    @media (max-width: 1023px) {
        .suki-dashboard-shell aside { transform:translateX(-100%); transition:transform .2s ease; }
        .suki-dashboard-shell aside.is-open { transform:translateX(0); }
        .admin-mobile-menu { display:flex; }
    .admin-sidebar-overlay.is-visible { display:block; position:fixed; inset:0; z-index:35; border:0; background:rgba(20,42,34,.38); }
    .admin-sidebar-collapse { display:flex; width:30px; height:30px; align-items:center; justify-content:center; margin-left:auto; color:#e1eee8; border-radius:6px; }
    .admin-sidebar-collapse svg { width:17px; height:17px; }
    .admin-sidebar-collapse:focus-visible { outline:2px solid #b9e4d2; outline-offset:2px; }
    @media (min-width:1024px) {
        body.admin-sidebar-collapsed #admin-sidebar { width:68px; }
        body.admin-sidebar-collapsed #admin-sidebar + .admin-sidebar-overlay + div { margin-left:68px; }
        body.admin-sidebar-collapsed #admin-sidebar > div:first-child { padding:12px 8px; }
        body.admin-sidebar-collapsed #admin-sidebar > div:first-child > div { display:none; }
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-link span,
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-toggle span,
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-chevron,
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-children,
        body.admin-sidebar-collapsed #admin-sidebar > div:last-child button { display:none; }
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-link,
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-toggle { justify-content:center; padding:9px 0; }
        body.admin-sidebar-collapsed #admin-sidebar .admin-nav-group { margin-bottom:4px; }
    }
    }

    @media (prefers-reduced-motion: reduce) { .admin-nav-link, .admin-nav-toggle, .admin-nav-child, .admin-nav-toggle .admin-nav-chevron { transition:none; } }

</style>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) window.lucide.createIcons();
    const groups = [...document.querySelectorAll('[data-admin-nav-group]')];
    groups.forEach(group => {
        const toggle = group.querySelector('[data-admin-nav-toggle]');
        const panel = document.getElementById(toggle?.getAttribute('aria-controls'));
        toggle?.addEventListener('click', () => {
            const willOpen = toggle.getAttribute('aria-expanded') !== 'true';
            groups.forEach(other => {
                const otherToggle = other.querySelector('[data-admin-nav-toggle]');
                const otherPanel = document.getElementById(otherToggle?.getAttribute('aria-controls'));
                otherToggle?.setAttribute('aria-expanded', 'false');
                if (otherPanel) otherPanel.hidden = true;
            });
            toggle.setAttribute('aria-expanded', String(willOpen));
            if (panel) panel.hidden = !willOpen;
        });
    });

    const sidebar = document.querySelector('.suki-dashboard-shell aside');
    const overlay = document.getElementById('admin-sidebar-overlay');
    const openButton = document.getElementById('admin-sidebar-open');
    const collapseButton = document.getElementById('admin-sidebar-collapse');
    const mobile = window.matchMedia('(max-width: 1023px)');
    const mainArea = sidebar?.nextElementSibling?.nextElementSibling;
    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        overlay?.classList.remove('is-visible');
        if (overlay) overlay.hidden = true;
        if (sidebar) sidebar.inert = true;
        if (mainArea) mainArea.inert = false;
        openButton?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
        openButton?.focus();
    };
    openButton?.addEventListener('click', () => {
        if (!mobile.matches) return;
        sidebar?.classList.add('is-open');
        overlay?.classList.add('is-visible');
        if (overlay) overlay.hidden = false;
        if (sidebar) sidebar.inert = false;
        if (mainArea) mainArea.inert = true;
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        sidebar?.querySelector('button, a')?.focus();
    });
    collapseButton?.addEventListener('click', () => {
        if (mobile.matches) return closeSidebar();
        const collapsed = document.body.classList.toggle('admin-sidebar-collapsed');
        collapseButton.setAttribute('aria-expanded', String(!collapsed));
        collapseButton.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        collapseButton.innerHTML = `<i data-lucide="${collapsed ? 'panel-left-open' : 'panel-left-close'}" aria-hidden="true"></i>`;
        window.lucide?.createIcons();
    });
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && mobile.matches && sidebar?.classList.contains('is-open')) closeSidebar();
        if (event.key === 'Tab' && mobile.matches && sidebar?.classList.contains('is-open')) {
            const items = [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter(item => item.offsetParent !== null);
            if (!items.length) return;
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
        if (window.matchMedia('(max-width: 1023px)').matches) closeSidebar();
    }));
});
</script>

@include('components.logout-confirmation')


</body>

</html>
