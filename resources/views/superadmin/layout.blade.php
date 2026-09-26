<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        SUKI Super Admin
    </title>

    @vite(['resources/css/app.css','resources/js/app.js'])

</head>


<body class="bg-gray-100">


<div class="flex min-h-screen">


    <!-- Sidebar -->

    <aside class="w-64 bg-white shadow-lg">

        <div class="p-6 border-b">

            <h1 class="text-2xl font-bold text-green-600">
                SUKI
            </h1>

            <p class="text-sm text-gray-500">
                Super Admin Panel
            </p>

        </div>



        <nav class="p-4 space-y-2">


            <a href="{{ route('superadmin.dashboard') }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                <span>
                    📊
                </span>

                Dashboard

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                👥 Users

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                📝 Applications

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                🏪 Sellers

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                📦 Orders

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                📈 Reports

            </a>



            <a href="#"
               class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-green-50 text-gray-700">

                ⚙ Settings

            </a>


        </nav>


    </aside>





    <!-- Main Content -->


    <main class="flex-1">


        <!-- Header -->

        <header class="bg-white shadow-sm p-5 flex justify-between">


            <div>

                <h2 class="text-xl font-semibold">

                    @yield('title')

                </h2>

            </div>



            <div class="text-right">


                <p class="font-medium">

                    {{ auth()->user()->name }}

                </p>


                <span class="text-sm text-gray-500">

                    Super Administrator

                </span>


            </div>



        </header>





        <section class="p-6">


            @yield('content')


        </section>


    </main>


</div>


</body>

</html>