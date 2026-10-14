<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - SUKI SHOP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >
</head>
<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-2xl border border-[#DDE6E1] bg-white p-6 shadow-sm sm:p-8">
            <a href="{{ route('landing') }}" class="mb-8 flex justify-center">
                <img
                    src="{{ asset('images/suki-logo.png') }}"
                    alt="SUKI SHOP"
                    class="h-auto w-40 object-contain"
                >
            </a>

            <div class="text-center">
                <h1 class="text-2xl font-semibold text-[#173F35]">Verify your email address</h1>
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    We sent a 6-digit verification code to:
                </p>
                <p class="font-medium text-[#1F6F5B]">{{ $email }}</p>
            </div>

            @if (session('success'))
                <p role="status" class="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </p>
            @endif

            @if (session('error'))
                <p role="alert" class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">
                    {{ session('error') }}
                </p>
            @endif

            @if (session('status'))
                <p role="status" class="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('status') }}
                </p>
            @endif

            @error('email')
                <p class="mt-4 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <form
                action="{{ route('buyer.verification.verify') }}"
                method="POST"
                class="mt-7"
                novalidate
            >
                @csrf
                <label for="otp" class="block text-sm font-medium text-gray-700">
                    6-digit verification code
                </label>
                <input
                    id="otp"
                    name="otp"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    value="{{ old('otp') }}"
                    aria-invalid="{{ $errors->has('otp') ? 'true' : 'false' }}"
                    aria-describedby="otp-error"
                    class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-3 text-center text-xl tracking-[0.5em] outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/20"
                >
                <p id="otp-error" class="mt-2 min-h-5 text-sm text-red-600">
                    @error('otp'){{ $message }}@enderror
                </p>
                <button
                    type="submit"
                    class="mt-4 w-full rounded-lg bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
                >
                    VERIFY EMAIL
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-600">
                <p>Didn't receive the code?</p>
                <form action="{{ route('buyer.verification.resend') }}" method="POST" class="mt-2">
                    @csrf
                    <button type="submit" class="font-semibold text-[#1F6F5B] hover:underline">
                        RESEND OTP
                    </button>
                </form>
            </div>

            <a href="{{ route('login') }}" class="mt-7 block text-center text-sm text-gray-500 hover:text-[#1F6F5B]">
                Return to login
            </a>
        </section>
    </main>

    <script>
        document.getElementById('otp')?.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>
