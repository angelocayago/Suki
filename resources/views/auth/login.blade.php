<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log In - SUKI SHOP</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">

    <div class="grid min-h-screen lg:grid-cols-[45%_55%]">

        <!-- ================================================= -->
        <!-- LEFT SIDE - SUKI SHOP BRANDING -->
        <!-- ================================================= -->

        <section class="hidden items-center justify-center bg-[#EEF8F3] px-10 lg:flex">

            <div class="max-w-lg text-center">

                <!-- Logo -->
                <div class="flex justify-center">

                    <div class="rounded-[28px] border border-[#DCEDE6] bg-white px-8 py-6 shadow-sm">

                        <img
                            src="{{ asset('images/suki-logo.png') }}"
                            alt="SUKI SHOP"
                            class="h-auto w-[210px] object-contain xl:w-[240px]"
                        >

                    </div>

                </div>

                <!-- Tagline -->
                <h1 class="mt-8 text-3xl font-semibold leading-tight text-[#173F35] xl:text-4xl">

                    Your everyday

                    <span class="text-[#1F6F5B]">
                        marketplace.
                    </span>

                </h1>

                <!-- Description -->
                <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-gray-500 xl:text-base">

                    Discover products, find great deals, and shop
                    from sellers you can trust — all in one place.

                </p>

                <!-- Features -->
                <div class="mt-8 flex justify-center gap-3">

                    <!-- Great Deals -->
                    <div class="flex items-center gap-2 rounded-full border border-[#DCEDE6] bg-white px-4 py-2">

                        <i
                            data-lucide="tag"
                            class="h-4 w-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Great Deals
                        </span>

                    </div>

                    <!-- Easy Delivery -->
                    <div class="flex items-center gap-2 rounded-full border border-[#DCEDE6] bg-white px-4 py-2">

                        <i
                            data-lucide="truck"
                            class="h-4 w-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Easy Delivery
                        </span>

                    </div>

                </div>

                <!-- Trust Message -->
                <div class="mt-10 flex items-center justify-center gap-2 text-xs text-gray-400">

                    <i
                        data-lucide="shield-check"
                        class="h-4 w-4 text-[#1F6F5B]"
                    ></i>

                    <span>
                        A marketplace made for everyday shopping
                    </span>

                </div>

            </div>

        </section>

        <!-- ================================================= -->
        <!-- RIGHT SIDE - LOGIN -->
        <!-- ================================================= -->

        <section class="flex items-center justify-center bg-[#F8FAF8] px-6 py-10 sm:px-10 lg:px-16">

            <div class="w-full max-w-[440px]">

                <!-- Mobile Logo -->
                <div class="mb-8 text-center lg:hidden">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="mx-auto h-auto w-[155px]"
                    >

                </div>

                <!-- Header -->
                <div class="mb-8">

                    <p class="mb-2 text-sm font-semibold tracking-wide text-[#1F6F5B]">
                        WELCOME BACK
                    </p>

                    <h2 class="text-3xl font-semibold text-gray-900">
                        Log in to SUKI SHOP
                    </h2>

                    <p class="mt-2 text-sm text-gray-500">
                        Sign in to continue shopping with us.
                    </p>

                </div>

                <!-- Error Messages -->
                @if ($errors->any())

                    <div class="mb-5 rounded-xl border border-red-100 bg-red-50 px-4 py-3">

                        <ul class="space-y-1 text-sm text-red-600">

                            @foreach ($errors->all() as $error)

                                <li>
                                    {{ $error }}
                                </li>

                            @endforeach

                        </ul>

                    </div>

                @endif

                <!-- ================================================= -->
                <!-- LOGIN FORM -->
                <!-- ================================================= -->

                <form
                    action="{{ route('login.submit') }}"
                    method="POST"
                    class="space-y-5"
                >

                    @csrf

                    <!-- Phone / Gmail -->
                    <div>

                        <label
                            for="login"
                            class="mb-2 block text-sm font-medium text-gray-700"
                        >
                            Phone Number or Gmail
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="user"
                                class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            ></i>

                            <input
                                type="text"
                                id="login"
                                name="login"
                                value="{{ old('login') }}"
                                placeholder="Enter phone number or Gmail"
                                autocomplete="username"
                                class="w-full rounded-lg border border-gray-300 bg-white py-3.5 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                        </div>

                    </div>

                    <!-- Password -->
                    <div>

                        <div class="mb-2 flex items-center justify-between">

                            <label
                                for="password"
                                class="text-sm font-medium text-gray-700"
                            >
                                Password
                            </label>

                            <a
                                href="#"
                                class="text-xs font-medium text-[#1F6F5B] hover:underline"
                            >
                                Forgot Password?
                            </a>

                        </div>

                        <div class="relative">

                            <i
                                data-lucide="lock"
                                class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                            ></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                class="w-full rounded-lg border border-gray-300 bg-white py-3.5 pl-11 pr-12 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                            <!-- Show Password -->
                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 transition hover:text-[#1F6F5B]"
                                aria-label="Show password"
                            >

                                <i
                                    id="passwordIcon"
                                    data-lucide="eye"
                                    class="h-5 w-5"
                                ></i>

                            </button>

                        </div>

                    </div>

                    <!-- ================================================= -->
                    <!-- REMEMBER ME -->
                    <!-- ================================================= -->

                    <div class="flex items-center justify-between">

                        <label
                            for="remember"
                            class="flex cursor-pointer items-center gap-2.5"
                        >

                            <input
                                type="checkbox"
                                id="remember"
                                name="remember"
                                value="1"
                                {{ old('remember') ? 'checked' : '' }}
                                class="h-4 w-4 cursor-pointer rounded border-gray-300 text-[#1F6F5B] accent-[#1F6F5B] focus:ring-[#1F6F5B]"
                            >

                            <span class="text-sm text-gray-600">
                                Remember me
                            </span>

                        </label>

                        <div class="flex items-center gap-1.5 text-xs text-gray-400">

                            <i
                                data-lucide="shield-check"
                                class="h-3.5 w-3.5"
                            ></i>

                            <span>
                                Stay signed in
                            </span>

                        </div>

                    </div>

                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="w-full rounded-lg bg-[#1F6F5B] py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244]"
                    >
                        LOG IN
                    </button>

                </form>

                <!-- ================================================= -->
                <!-- DIVIDER -->
                <!-- ================================================= -->

                <div class="my-7 flex items-center gap-4">

                    <div class="h-px flex-1 bg-gray-200"></div>

                    <span class="text-xs font-medium text-gray-400">
                        OR
                    </span>

                    <div class="h-px flex-1 bg-gray-200"></div>

                </div>

                <!-- ================================================= -->
                <!-- SIGN UP -->
                <!-- ================================================= -->

                <div class="text-center text-sm">

                    <span class="text-gray-500">
                        Don't have a SUKI SHOP account?
                    </span>

                    <a
                        href="{{ route('register') }}"
                        class="ml-1 font-semibold text-[#1F6F5B] hover:underline"
                    >
                        Sign Up
                    </a>

                </div>

                <!-- ================================================= -->
                <!-- RIDER APPLICATION -->
                <!-- ================================================= -->

                <div class="mt-8">

                    <div class="rounded-2xl border border-[#DDF3EC] bg-[#F7FCFA] p-4">

                        <div class="flex items-center gap-4">

                            <!-- Rider Icon -->
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#DDF3EC] bg-white">

                                <i
                                    data-lucide="bike"
                                    class="h-5 w-5 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <!-- Rider Information -->
                            <div class="flex-1">

                                <h3 class="text-sm font-semibold text-[#173F35]">
                                    Become a SUKI SHOP Rider
                                </h3>

                                <p class="mt-1 text-xs text-gray-500">
                                    Earn while delivering orders in your area.
                                </p>

                                <!-- FIXED LINK -->
                                <a
                                    href="{{ route('rider.apply') }}"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-[#1F6F5B] transition hover:text-[#155244]"
                                >

                                    Apply as a SUKI SHOP Rider

                                    <i
                                        data-lucide="arrow-right"
                                        class="h-3.5 w-3.5"
                                    ></i>

                                </a>

                            </div>

                        </div>

                    </div>

                </div>

                <!-- ================================================= -->
                <!-- CONTINUE AS GUEST -->
                <!-- ================================================= -->

                <div class="mt-6 text-center">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="inline-flex items-center gap-1.5 text-xs text-gray-400 transition hover:text-[#1F6F5B]"
                    >

                        <i
                            data-lucide="arrow-left"
                            class="h-3.5 w-3.5"
                        ></i>

                        Continue as Guest

                    </a>

                </div>

            </div>

        </section>

    </div>

    <!-- ================================================= -->
    <!-- JAVASCRIPT -->
    <!-- ================================================= -->

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        });

        function togglePassword() {

            const password = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');

            if (password.type === 'password') {

                password.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');

            } else {

                password.type = 'password';
                icon.setAttribute('data-lucide', 'eye');

            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

        }

    </script>

</body>
</html>