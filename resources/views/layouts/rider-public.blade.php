<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SUKI SHOP Rider</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>

<body class="bg-[#F8FAF8] text-gray-900">

    @yield('content')


    <script>

        document.addEventListener('DOMContentLoaded', function () {

            if (window.lucide) {

                lucide.createIcons();

            }

        });

    </script>

</body>

</html>