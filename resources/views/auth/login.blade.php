<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log In - SUKI</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">

    <div class="min-h-screen grid lg:grid-cols-[45%_55%]">

        <!-- ================================================= -->
        <!-- LEFT SIDE - SUKI BRANDING -->
        <!-- ================================================= -->

        <section class="hidden lg:flex items-center justify-center bg-[#EEF8F3] px-10">

            <div class="text-center max-w-lg">

                <!-- Logo -->
                <div class="flex justify-center">

                    <div class="bg-white rounded-[28px] px-8 py-6 shadow-sm border border-[#DCEDE6]">

                        <img
                            src="{{ asset('images/suki-logo.png') }}"
                            alt="SUKI"
                            class="w-[210px] xl:w-[240px] h-auto object-contain"
                        >

                    </div>

                </div>


                <!-- Tagline -->
                <h1 class="mt-8 text-3xl xl:text-4xl font-semibold text-[#173F35] leading-tight">

                    Your everyday

                    <span class="text-[#1F6F5B]">
                        marketplace.
                    </span>

                </h1>


                <!-- Description -->
                <p class="mt-4 text-sm xl:text-base text-gray-500 leading-relaxed max-w-md mx-auto">

                    Discover products, find great deals, and shop
                    from sellers you can trust — all in one place.

                </p>


                <!-- Features -->
                <div class="flex justify-center gap-3 mt-8">

                    <!-- Great Deals -->
                    <div class="flex items-center gap-2 bg-white border border-[#DCEDE6] rounded-full px-4 py-2">

                        <i
                            data-lucide="tag"
                            class="w-4 h-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Great Deals
                        </span>

                    </div>


                    <!-- Easy Delivery -->
                    <div class="flex items-center gap-2 bg-white border border-[#DCEDE6] rounded-full px-4 py-2">

                        <i
                            data-lucide="truck"
                            class="w-4 h-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Easy Delivery
                        </span>

                    </div>

                </div>


                <!-- Trust Message -->
                <div class="flex justify-center items-center gap-2 mt-10 text-xs text-gray-400">

                    <i
                        data-lucide="shield-check"
                        class="w-4 h-4 text-[#1F6F5B]"
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

        <section class="flex items-center justify-center bg-[#F8FAF8] px-6 sm:px-10 lg:px-16 py-10">

            <div class="w-full max-w-[440px]">

                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-8">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI"
                        class="w-[155px] mx-auto h-auto"
                    >

                </div>


                <!-- Header -->
                <div class="mb-8">

                    <p class="text-sm font-semibold tracking-wide text-[#1F6F5B] mb-2">
                        WELCOME BACK
                    </p>

                    <h2 class="text-3xl font-semibold text-gray-900">
                        Log in to SUKI
                    </h2>

                    <p class="text-sm text-gray-500 mt-2">
                        Sign in to continue shopping with us.
                    </p>

                </div>


                <!-- Error Messages -->
                @if ($errors->any())

                    <div class="mb-5 rounded-xl bg-red-50 border border-red-100 px-4 py-3">

                        <ul class="text-sm text-red-600 space-y-1">

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
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Phone Number or Gmail
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="user"
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                            ></i>

                            <input
                                type="text"
                                id="login"
                                name="login"
                                value="{{ old('login') }}"
                                placeholder="Enter phone number or Gmail"
                                class="w-full pl-11 pr-4 py-3.5 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                        </div>

                    </div>


                    <!-- Password -->
                    <div>

                        <div class="flex items-center justify-between mb-2">

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
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                            ></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                class="w-full pl-11 pr-12 py-3.5 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                            <!-- Show Password -->
                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B] transition"
                                aria-label="Show password"
                            >

                                <i
                                    id="passwordIcon"
                                    data-lucide="eye"
                                    class="w-5 h-5"
                                ></i>

                            </button>

                        </div>

                    </div>


                    <!-- Login Button -->
                    <button
                        type="submit"
                        class="w-full py-3.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                    >
                        LOG IN
                    </button>

                </form>


                <!-- ================================================= -->
                <!-- DIVIDER -->
                <!-- ================================================= -->

                <div class="flex items-center gap-4 my-7">

                    <div class="flex-1 h-px bg-gray-200"></div>

                    <span class="text-xs text-gray-400 font-medium">
                        OR
                    </span>

                    <div class="flex-1 h-px bg-gray-200"></div>

                </div>


                <!-- ================================================= -->
                <!-- SIGN UP -->
                <!-- ================================================= -->

                <div class="text-center text-sm">

                    <span class="text-gray-500">
                        Don't have a SUKI account?
                    </span>

                    <a
                        href="{{ route('register') }}"
                        class="font-semibold text-[#1F6F5B] hover:underline ml-1"
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
                            <div class="shrink-0 w-11 h-11 rounded-xl bg-white border border-[#DDF3EC] flex items-center justify-center">

                                <i
                                    data-lucide="bike"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <!-- Rider Information -->
                            <div class="flex-1">

                                <h3 class="text-sm font-semibold text-[#173F35]">
                                    Become a SUKI Rider
                                </h3>

                                <p class="text-xs text-gray-500 mt-1">
                                    Earn while delivering orders in your area.
                                </p>

                                <a
                                    href="/rider/register"
                                    class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-[#1F6F5B] hover:text-[#155244] transition"
                                >

                                    Apply as a SUKI Rider

                                    <i
                                        data-lucide="arrow-right"
                                        class="w-3.5 h-3.5"
                                    ></i>

                                </a>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- CONTINUE AS GUEST -->
                <!-- ================================================= -->

                <div class="text-center mt-6">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-[#1F6F5B] transition"
                    >

                        <i
                            data-lucide="arrow-left"
                            class="w-3.5 h-3.5"
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

            lucide.createIcons();

        }

    </script>

</body>
</html>