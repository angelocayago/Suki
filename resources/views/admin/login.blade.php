<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SUKI SHOP Admin Login</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

</head>

<body class="min-h-screen bg-[#F8FAF8]">

    <main class="flex min-h-screen items-center justify-center px-4 py-12">

        <div class="w-full max-w-md">

            {{-- BRAND --}}
            <div class="mb-8 text-center">

                <h1 class="text-3xl font-bold tracking-tight text-[#173F35]">
                    SUKI SHOP
                </h1>

                <p class="mt-2 text-sm text-gray-500">
                    Administrator Portal
                </p>

            </div>


            {{-- LOGIN CARD --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">

                <div>

                    <h2 class="text-xl font-semibold text-gray-900">
                        Admin Login
                    </h2>

                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        Sign in using your administrator account.
                    </p>

                </div>


                {{-- SUCCESS MESSAGE --}}
                @if(session('success'))

                    <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">

                        {{ session('success') }}

                    </div>

                @endif


                {{-- ERROR MESSAGE --}}
                @if(session('error'))

                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                        {{ session('error') }}

                    </div>

                @endif


                {{-- VALIDATION ERRORS --}}
                @if($errors->any())

                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                        {{ $errors->first() }}

                    </div>

                @endif


                {{-- LOGIN FORM --}}
                <form
                    action="{{ route('admin.login.submit') }}"
                    method="POST"
                    class="mt-7 space-y-5"
                >

                    @csrf


                    {{-- EMAIL / PHONE --}}
                    <div>

                        <label
                            for="login"
                            class="mb-2 block text-sm font-medium text-gray-700"
                        >
                            Email or Phone
                        </label>

                        <input
                            id="login"
                            name="login"
                            type="text"
                            value="{{ old('login') }}"
                            autocomplete="username"
                            required
                            autofocus
                            placeholder="Enter admin email or phone"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>


                    {{-- PASSWORD --}}
                    <div>

                        <label
                            for="password"
                            class="mb-2 block text-sm font-medium text-gray-700"
                        >
                            Password
                        </label>

                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            placeholder="Enter password"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>


                    {{-- SUBMIT --}}
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#155244] focus:outline-none focus:ring-2 focus:ring-[#1F6F5B] focus:ring-offset-2"
                    >
                        Sign In
                    </button>

                </form>


                <div class="mt-6 border-t border-gray-100 pt-5">

                    <p class="text-center text-xs leading-5 text-gray-400">
                        Authorized SUKI SHOP administrators only.
                    </p>

                </div>

            </div>

        </div>

    </main>

</body>

</html>