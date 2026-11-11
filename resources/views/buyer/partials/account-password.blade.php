<section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="account-password-heading">
    <h1 id="account-password-heading" class="text-xl font-semibold text-[#173F35]">Change Password</h1>
    <p class="mt-2 text-sm text-gray-500">Keep your SUKI SHOP account secure. Use at least 8 characters and choose a password different from your current password.</p>
    @if(session('success'))
        <p role="status" class="mt-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p role="alert" class="mt-4 text-sm text-red-700">{{ session('error') }}</p>
    @endif
    @if($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('buyer.account.password') }}" class="mt-6 space-y-5" data-account-password-form>
        @csrf
        <div>
            <label for="account-current-password" class="block text-sm font-medium text-gray-700">Current Password</label>
            <input id="account-current-password" name="current_password" type="password" autocomplete="current-password" required
                class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm" aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}" aria-describedby="account-current-password-error">
            <p id="account-current-password-error" class="mt-1 text-sm text-red-700">@error('current_password'){{ $message }}@enderror</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="account-new-password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input id="account-new-password" name="password" type="password" autocomplete="new-password" required minlength="8"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="account-new-password-error">
                <p id="account-new-password-error" class="mt-1 text-sm text-red-700">@error('password'){{ $message }}@enderror</p>
            </div>
            <div>
                <label for="account-confirm-password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input id="account-confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8"
                    class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" aria-describedby="account-confirm-password-error">
                <p id="account-confirm-password-error" class="mt-1 text-sm text-red-700">@error('password_confirmation'){{ $message }}@enderror</p>
            </div>
        </div>
        <button type="submit" class="min-h-11 rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white hover:bg-[#155244]">Update Password</button>
    </form>
</section>
