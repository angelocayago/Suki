<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Contact Details - SUKI SHOP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F8FAF8] px-5 py-12 text-gray-800">
<main class="mx-auto max-w-md rounded-2xl border border-gray-200 bg-white p-7 shadow-sm">
    <a href="{{ route('buyer.home') }}"><img src="{{ asset('images/suki-logo.png') }}" alt="SUKI SHOP" class="mx-auto h-16 w-auto"></a>
    <div class="mt-7 text-center">
        <p class="text-xs font-semibold uppercase tracking-wider text-[#1F6F5B]">Contact verification</p>
        <h1 class="mt-2 text-2xl font-bold">{{ $riderOnly ? 'Verify your mobile number' : 'Verify your email address' }}</h1>
        @if($riderOnly)
            <p class="mt-2 text-sm text-gray-600">Enter the 6-digit code sent to <strong>{{ $phone }}</strong>. Codes expire after 5 minutes.</p>
        @else
            <p class="mt-2 text-sm text-gray-600">Enter the 6-digit code sent to <strong>{{ $email }}</strong>. Codes expire after 5 minutes.</p>
        @endif
    </div>

    @if(session('success')) <div class="mt-5 rounded-xl bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="mt-5 rounded-xl bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('verification.contact.verify') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-semibold" for="code">Verification code</label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus
               class="w-full rounded-xl border border-gray-300 px-4 py-3 text-center text-2xl tracking-[.45em] focus:border-[#1F6F5B] focus:outline-none">
        <button data-submit-button class="w-full rounded-xl bg-[#1F6F5B] px-4 py-3 font-semibold text-white hover:bg-[#185a4a]">Verify Code</button>
    </form>
    <form method="POST" action="{{ route('verification.contact.resend') }}" class="mt-3 text-center">
        @csrf
        <button data-submit-button class="text-sm font-semibold text-[#1F6F5B] hover:underline">Resend code</button>
        <p class="mt-1 text-xs text-gray-500">A 60-second resend cooldown applies. {{ $riderOnly ? '' : 'A new code is sent to your email address.' }}</p>
    </form>
</main>
<script>
document.querySelectorAll('form').forEach((form) => form.addEventListener('submit', () => {
    const button = form.querySelector('[data-submit-button]');
    if (button) { button.disabled = true; button.classList.add('opacity-60', 'cursor-not-allowed'); }
}));
</script>
</body>
</html>
