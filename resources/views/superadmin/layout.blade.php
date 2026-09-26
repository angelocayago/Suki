<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        @yield('title', 'SUKI Super Admin')
    </title>


    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])


</head>


<body class="bg-slate-50 text-slate-800">


<div class="flex min-h-screen">



    <!-- SIDEBAR -->

    <aside
        class="
        w-72
        bg-white
        border-r
        hidden
        lg:flex
        flex-col
        fixed
        inset-y-0
        ">


        <!-- BRAND -->

        <div class="px-6 py-6 border-b">


            <img
                src="{{ asset('images/suki-logo.png') }}"
                class="h-14 object-contain"
                alt="SUKI Logo"
            >


            <div class="mt-4">


                <h2 class="text-lg font-bold text-slate-800">

                    Super Admin Portal

                </h2>


                <p class="text-sm text-slate-500">

                    SUKI Platform Control

                </p>


            </div>


        </div>





        <!-- NAVIGATION -->

        <nav class="flex-1 px-4 py-6 space-y-2">



            <a href="{{ route('superadmin.dashboard') }}"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                <svg class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">

                    <path stroke-width="2"
                    d="M3 12l9-9 9 9v9a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>

                </svg>


                Dashboard


            </a>






            <a href="{{ route('superadmin.users') }}"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                <svg class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">


                    <path stroke-width="2"
                    d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/>


                </svg>


                Users


            </a>







            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                📝

                Applications


            </a>





            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                🏪

                Seller Management


            </a>





            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                📦

                Orders


            </a>





            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                💰

                Commission


            </a>





            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                📊

                Reports


            </a>






            <a href="#"
            class="
            flex items-center gap-3
            px-4 py-3
            rounded-xl
            text-sm font-medium
            hover:bg-green-50
            hover:text-green-700
            transition">


                ⚙

                Settings


            </a>




        </nav>








        <!-- USER CARD -->


        <div class="p-5 border-t">


            <div class="flex items-center gap-3">


                <div
                class="
                w-12
                h-12
                rounded-full
                bg-green-600
                text-white
                flex
                items-center
                justify-center
                font-bold">


                    {{ strtoupper(substr(auth()->user()->name,0,1)) }}


                </div>




                <div>


                    <p class="font-semibold text-sm">

                        {{ auth()->user()->name }}

                    </p>


                    <p class="text-xs text-slate-500">

                        Super Administrator

                    </p>


                </div>


            </div>


        </div>



    </aside>








    <!-- MAIN AREA -->


    <div class="flex-1 lg:ml-72">





        <!-- TOPBAR -->


        <header
        class="
        h-20
        bg-white
        border-b
        px-8
        flex
        items-center
        justify-between">


            <div>


                <h1 class="text-2xl font-bold">

                    @yield('title')

                </h1>


                <p class="text-sm text-slate-500">

                    Manage SUKI operations and platform data

                </p>


            </div>






            <div class="flex items-center gap-4">



                <button
                class="
                w-10
                h-10
                rounded-xl
                hover:bg-slate-100">

                    🔔

                </button>




                <div
                class="
                w-11
                h-11
                rounded-full
                bg-green-600
                text-white
                flex
                items-center
                justify-center
                font-bold">


                    {{ strtoupper(substr(auth()->user()->name,0,1)) }}


                </div>



            </div>



        </header>







        <!-- CONTENT -->


        <main class="p-8">


            @if(session('success'))

                <div class="
                mb-6
                bg-green-100
                text-green-700
                px-5
                py-4
                rounded-xl">


                    {{ session('success') }}


                </div>

            @endif




            @yield('content')



        </main>



    </div>



</div>


</body>


</html>