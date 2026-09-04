<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'SUKI' }}</title>

    {{-- Vite / Laravel Assets --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Poppins Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    {{-- Lucide Icons --}}
    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-[#F8FAF8] text-[#1F2937] antialiased">

    {{-- ==========================================
         BUYER NAVIGATION
    =========================================== --}}
    @include('components.buyer-navbar')


    {{-- ==========================================
         MAIN CONTENT
    =========================================== --}}
    <main class="min-h-screen">
        @yield('content')
    </main>


    {{-- ==========================================
         FOOTER
    =========================================== --}}
    @include('components.footer')


    {{-- ==========================================
         PAGE-SPECIFIC SCRIPTS
    =========================================== --}}
    @stack('scripts')


    {{-- ==========================================
         LUCIDE ICON INITIALIZATION
    =========================================== --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>

</body>

</html>