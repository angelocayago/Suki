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


<body class="bg-gray-100">


<div x-data="{ sidebar:false }"
     class="min-h-screen">


    <!-- MOBILE OVERLAY -->

    <div
        x-show="sidebar"
        @click="sidebar=false"
        class="fixed inset-0 bg-black/40 z-30 lg:hidden">
    </div>



    <!-- SIDEBAR -->

    <aside
        :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        class="
            fixed
            lg:translate-x-0
            inset-y-0
            left-0
            w-72
            bg-white
            shadow-xl
            z-40
            transition-transform
            duration-300
        ">


        <!-- BRAND -->

        <div class="p-6 border-b">


            <div class="flex items-center gap-3">


                <div
                    class="
                    w-12
                    h-12
                    rounded-xl
                    bg-green-600
                    text-white
                    flex
                    items-center
                    justify-center
                    font-bold
                    text-xl
                    ">
                    S
                </div>



                <div>

                    <h1 class="font-bold text-xl text-gray-800">
                        SUKI
                    </h1>


                    <p class="text-sm text-gray-500">
                        Super Admin
                    </p>

                </div>


            </div>


        </div>





        <!-- NAVIGATION -->


        <nav class="p-4 space-y-2">


            <a href="{{ route('superadmin.dashboard') }}"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                📊
                Dashboard

            </a>





            <a href="{{ route('superadmin.users') }}"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                👥
                User Management

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                📝
                Applications

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                🏪
                Seller Compliance

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                📦
                Orders

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                💰
                Commission

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                📈
                Reports

            </a>





            <a href="#"
               class="
               flex
               items-center
               gap-3
               px-4
               py-3
               rounded-xl
               hover:bg-green-50
               hover:text-green-600
               text-gray-700
               ">

                ⚙️
                Settings

            </a>



        </nav>



    </aside>






    <!-- MAIN -->

    <div class="lg:ml-72">





        <!-- TOP NAVBAR -->

        <header
            class="
            bg-white
            shadow-sm
            h-20
            flex
            items-center
            justify-between
            px-6
            ">


            <!-- hamburger -->

            <button
                @click="sidebar=true"
                class="
                lg:hidden
                text-2xl
                ">

                ☰

            </button>





            <div>

                <h2 class="text-xl font-semibold text-gray-800">

                    @yield('title')

                </h2>

                <p class="text-sm text-gray-500">

                    Manage SUKI platform operations

                </p>


            </div>





            <!-- PROFILE -->


            <div class="flex items-center gap-4">


                <div class="text-right hidden md:block">


                    <p class="font-semibold">

                        {{ auth()->user()->name }}

                    </p>


                    <p class="text-xs text-gray-500">

                        Super Administrator

                    </p>


                </div>




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
                    font-bold
                    ">

                    {{ strtoupper(substr(auth()->user()->name,0,1)) }}

                </div>


            </div>



        </header>





        <!-- CONTENT -->


        <main class="p-6">


            @if(session('success'))

                <div class="mb-5 bg-green-100 text-green-700 p-4 rounded-xl">

                    {{ session('success') }}

                </div>

            @endif



            @yield('content')


        </main>



    </div>


</div>


</body>


</html>