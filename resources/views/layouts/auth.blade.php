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
        @yield('title', 'SUKI SHOP')
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
    class="min-h-screen
           bg-[#F8FAF8]
           font-[Poppins]
           text-[#24312C]
           antialiased"
>

    {{-- =========================================================
         AUTH / GUEST CONTENT ONLY

         No Buyer Navbar
         No Seller Sidebar
         No Marketplace Footer
    ========================================================== --}}

    <main class="min-h-screen">

        @yield('content')

    </main>


    @stack('scripts')


    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }

            }
        );

    </script>

</body>

</html>