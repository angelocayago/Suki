<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'SUKI Super Admin')
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

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
        class="
            fixed
            left-0
            top-0
            bottom-0
            z-40
            w-[245px]
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
                px-6
                py-7
                border-b
                border-white/10
            "
        >

            <h1
                class="
                    font-bold
                    text-lg
                "
            >
                Super Admin Portal
            </h1>

            <p
                class="
                    text-xs
                    text-green-200
                    mt-1
                "
            >
                Platform Control Center
            </p>

        </div>



        <!-- MENU -->

<nav
    class="
        flex-1
        overflow-y-auto
        px-4
        py-6
        space-y-2
    "
>


    <!-- DASHBOARD -->

    <a
        href="{{ route('superadmin.dashboard') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.dashboard')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Dashboard
    </a>




    <!-- USERS -->

    <a
        href="{{ route('superadmin.users') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.users*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Users
    </a>





    <!-- BUYER MANAGEMENT -->

    <a
        href="{{ route('superadmin.buyers') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.buyers*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Buyer Management
    </a>





    <!-- SELLER MANAGEMENT -->

    <a
        href="{{ route('superadmin.sellers') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.sellers*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Seller Management
    </a>





    <!-- APPLICATIONS -->

    <a
        href="{{ route('superadmin.applications') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.applications*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Applications
    </a>





    <!-- ORDERS -->

    <a
        href="{{ route('superadmin.orders') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.orders*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Orders
    </a>





    <!-- COMMISSION -->

    <a
        href="{{ route('superadmin.commission') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.commission*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Commission
    </a>





    <!-- REPORTS -->

    <a
        href="{{ route('superadmin.reports') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.reports*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Reports
    </a>





    <!-- SETTINGS -->

    <a
        href="{{ route('superadmin.settings') }}"
        class="
            block
            px-4
            py-3
            rounded-xl
            text-sm
            font-medium
            transition

            {{ request()->routeIs('superadmin.settings*')
                ? 'bg-[#10b981] text-white'
                : 'text-green-100 hover:bg-[#047857] hover:text-white'
            }}
        "
    >
        Settings
    </a>


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
                action="{{ route('logout') }}"
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




    <!-- MAIN AREA -->

    <div
        class="
            min-h-screen
            lg:ml-[245px]
            min-w-0
        "
    >



        <!-- TOPBAR -->

        <header
            class="
                h-20
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



                <!-- NOTIFICATION -->

                <button
                    type="button"
                    class="
                        w-10
                        h-10
                        rounded-xl
                        hover:bg-gray-100
                        flex
                        items-center
                        justify-center
                        text-gray-600
                        transition
                    "
                    aria-label="Notifications"
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
                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9a6 6 0 00-12 0v.75c0 2.312-.877 4.51-2.311 6.022a23.848 23.848 0 005.454 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                        />
                    </svg>

                </button>


            </div>


        </header>




        <!-- CONTENT -->

        <main
            class="
                p-5
                md:p-6
                lg:p-8
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



            @yield('content')


        </main>


    </div>


</div>



<style>

    [x-cloak] {
        display: none !important;
    }


    @media (max-width: 1023px) {

        aside {
            transform: translateX(-100%);
        }

    }

</style>


</body>

</html>