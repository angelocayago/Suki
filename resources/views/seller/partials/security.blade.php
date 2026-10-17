    <section
        id="security"
        class="scroll-mt-28
               rounded-2xl
               border border-gray-200
               bg-white
               p-6 shadow-sm
               sm:p-7"
    >

        <div class="mb-6">

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.08em]
                       text-[#1F6F5B]"
            >
                Security
            </p>


            <p class="mt-1 text-xs text-gray-400">
                Change your password.
            </p>

        </div>



        <form
            action="{{ route('seller.store.password.update') }}"
            method="POST"
            class="max-w-xl space-y-5"
        >

            @csrf



            {{-- CURRENT PASSWORD --}}
            <div>

                <label
                    for="currentPassword"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    Current password
                    <span class="text-red-500">*</span>
                </label>


                <input
                    id="currentPassword"
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                    class="mt-2 w-full
                           rounded-xl
                           border border-gray-200
                           bg-white
                           px-4 py-3
                           text-sm text-gray-700
                           outline-none
                           focus:border-[#1F6F5B]
                           focus:ring-2
                           focus:ring-[#1F6F5B]/10"
                >
                @error('current_password')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror

            </div>



            {{-- NEW PASSWORD --}}
            <div>

                <label
                    for="newPassword"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    New password
                    <span class="text-red-500">*</span>
                </label>


                <input
                    id="newPassword"
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="mt-2 w-full
                           rounded-xl
                           border border-gray-200
                           bg-white
                           px-4 py-3
                           text-sm text-gray-700
                           outline-none
                           focus:border-[#1F6F5B]
                           focus:ring-2
                           focus:ring-[#1F6F5B]/10"
                >
                @error('password')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror


                <p
                    class="mt-2 text-[11px]
                           text-gray-400"
                >
                    At least 8 characters and different
                    from your current password.
                </p>

            </div>



            {{-- CONFIRM PASSWORD --}}
            <div>

                <label
                    for="confirmPassword"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    Confirm new password
                    <span class="text-red-500">*</span>
                </label>


                <input
                    id="confirmPassword"
                    type="password"
                    name="password_confirmation"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="mt-2 w-full
                           rounded-xl
                           border border-gray-200
                           bg-white
                           px-4 py-3
                           text-sm text-gray-700
                           outline-none
                           focus:border-[#1F6F5B]
                           focus:ring-2
                           focus:ring-[#1F6F5B]/10"
                >
                @error('password_confirmation')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror

            </div>



            <button
                type="submit"
                class="rounded-xl
                       bg-[#1F6F5B]
                       px-5 py-3
                       text-sm font-semibold
                       text-white
                       transition
                       hover:bg-[#155244]"
            >
                Update password
            </button>

        </form>

    </section>
