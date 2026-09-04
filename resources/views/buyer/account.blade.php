@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

        {{-- PAGE HEADER --}}
        <div class="mb-8">

            <h1 class="text-2xl font-bold text-gray-900">
                My Account
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Manage your profile, addresses, and account settings.
            </p>

        </div>


        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))

            <div class="mb-6 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">

                <i
                    data-lucide="check-circle"
                    class="h-5 w-5 shrink-0"
                ></i>

                <span>
                    {{ session('success') }}
                </span>

            </div>

        @endif


        {{-- ERROR MESSAGE --}}
        @if(session('error'))

            <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">

                <i
                    data-lucide="alert-circle"
                    class="h-5 w-5 shrink-0"
                ></i>

                <span>
                    {{ session('error') }}
                </span>

            </div>

        @endif


        <div class="grid gap-6 lg:grid-cols-3">


            {{-- ===================================================== --}}
            {{-- PROFILE CARD --}}
            {{-- ===================================================== --}}

            <section class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white">

                {{-- PROFILE HEADER --}}
                <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">

                        <div class="flex items-center gap-4">

                            {{-- AVATAR --}}
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-[#DDF3EC]">

                                <i
                                    data-lucide="user"
                                    class="h-7 w-7 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">
                                    {{ session('buyer_profile.first_name', 'SUKI Buyer') }}
                                    {{ session('buyer_profile.last_name', '') }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-500">
                                    {{ session('buyer_profile.email', 'buyer@suki.com') }}
                                </p>

                            </div>

                        </div>


                        <button
                            type="button"
                            onclick="openEditProfile()"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-4 py-2.5 text-sm font-semibold text-[#1F6F5B] transition hover:bg-[#EEF8F3]"
                        >

                            <i
                                data-lucide="pencil"
                                class="h-4 w-4"
                            ></i>

                            Edit Profile

                        </button>

                    </div>

                </div>


                {{-- PROFILE INFORMATION --}}
                <div class="p-5 sm:p-6">

                    <div class="mb-5">

                        <h3 class="text-sm font-semibold text-gray-900">
                            Personal Information
                        </h3>

                        <p class="mt-1 text-xs text-gray-500">
                            Your basic account information.
                        </p>

                    </div>


                    <div class="grid gap-5 sm:grid-cols-2">

                        {{-- FIRST NAME --}}
                        <div>

                            <p class="text-xs font-medium text-gray-400">
                                First Name
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="user"
                                    class="h-4 w-4 text-gray-400"
                                ></i>

                                <span class="text-sm font-medium text-gray-700">
                                    {{ session('buyer_profile.first_name', 'Not provided') }}
                                </span>

                            </div>

                        </div>


                        {{-- LAST NAME --}}
                        <div>

                            <p class="text-xs font-medium text-gray-400">
                                Last Name
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="user"
                                    class="h-4 w-4 text-gray-400"
                                ></i>

                                <span class="text-sm font-medium text-gray-700">
                                    {{ session('buyer_profile.last_name', 'Not provided') }}
                                </span>

                            </div>

                        </div>


                        {{-- PHONE --}}
                        <div>

                            <p class="text-xs font-medium text-gray-400">
                                Phone Number
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="phone"
                                    class="h-4 w-4 text-gray-400"
                                ></i>

                                <span class="text-sm font-medium text-gray-700">
                                    {{ session('buyer_profile.phone', 'Not provided') }}
                                </span>

                            </div>

                        </div>


                        {{-- EMAIL --}}
                        <div>

                            <p class="text-xs font-medium text-gray-400">
                                Email Address
                            </p>

                            <div class="mt-2 flex items-center gap-2">

                                <i
                                    data-lucide="mail"
                                    class="h-4 w-4 text-gray-400"
                                ></i>

                                <span class="break-all text-sm font-medium text-gray-700">
                                    {{ session('buyer_profile.email', 'Not provided') }}
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </section>


            {{-- ===================================================== --}}
            {{-- QUICK ACTIONS --}}
            {{-- ===================================================== --}}

            <aside class="space-y-6">


                {{-- ACCOUNT MENU --}}
                <section class="rounded-2xl border border-gray-200 bg-white p-5">

                    <h2 class="font-semibold text-gray-900">
                        Account
                    </h2>

                    <div class="mt-4 space-y-1">


                        {{-- MY ORDERS --}}
                        <a
                            href="{{ route('buyer.my-orders') }}"
                            class="group flex items-center justify-between rounded-xl px-3 py-3 transition hover:bg-[#F8FAF8]"
                        >

                            <div class="flex items-center gap-3">

                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#EEF8F3]">

                                    <i
                                        data-lucide="package"
                                        class="h-4 w-4 text-[#1F6F5B]"
                                    ></i>

                                </div>

                                <span class="text-sm font-medium text-gray-700">
                                    My Orders
                                </span>

                            </div>

                            <i
                                data-lucide="chevron-right"
                                class="h-4 w-4 text-gray-400 transition group-hover:translate-x-0.5"
                            ></i>

                        </a>


                        {{-- ADDRESSES --}}
                        <a
                            href="{{ route('buyer.addresses') }}"
                            class="group flex items-center justify-between rounded-xl px-3 py-3 transition hover:bg-[#F8FAF8]"
                        >

                            <div class="flex items-center gap-3">

                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#EEF8F3]">

                                    <i
                                        data-lucide="map-pin"
                                        class="h-4 w-4 text-[#1F6F5B]"
                                    ></i>

                                </div>

                                <span class="text-sm font-medium text-gray-700">
                                    My Addresses
                                </span>

                            </div>

                            <i
                                data-lucide="chevron-right"
                                class="h-4 w-4 text-gray-400 transition group-hover:translate-x-0.5"
                            ></i>

                        </a>


                        {{-- WISHLIST --}}
                        <a
                            href="{{ route('buyer.wishlist') }}"
                            class="group flex items-center justify-between rounded-xl px-3 py-3 transition hover:bg-[#F8FAF8]"
                        >

                            <div class="flex items-center gap-3">

                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#EEF8F3]">

                                    <i
                                        data-lucide="heart"
                                        class="h-4 w-4 text-[#1F6F5B]"
                                    ></i>

                                </div>

                                <span class="text-sm font-medium text-gray-700">
                                    Wishlist
                                </span>

                            </div>

                            <i
                                data-lucide="chevron-right"
                                class="h-4 w-4 text-gray-400 transition group-hover:translate-x-0.5"
                            ></i>

                        </a>


                        {{-- SECURITY --}}
                        <button
                            type="button"
                            onclick="openPasswordModal()"
                            class="group flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition hover:bg-[#F8FAF8]"
                        >

                            <div class="flex items-center gap-3">

                                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#EEF8F3]">

                                    <i
                                        data-lucide="lock"
                                        class="h-4 w-4 text-[#1F6F5B]"
                                    ></i>

                                </div>

                                <span class="text-sm font-medium text-gray-700">
                                    Change Password
                                </span>

                            </div>

                            <i
                                data-lucide="chevron-right"
                                class="h-4 w-4 text-gray-400 transition group-hover:translate-x-0.5"
                            ></i>

                        </button>

                    </div>

                </section>


                {{-- SELLER / RIDER --}}
                <section class="rounded-2xl border border-[#DDF3EC] bg-[#EEF8F3] p-5">

                    <div class="flex items-start gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white">

                            <i
                                data-lucide="store"
                                class="h-5 w-5 text-[#1F6F5B]"
                            ></i>

                        </div>

                        <div>

                            <h3 class="text-sm font-semibold text-gray-900">
                                Want to earn with SUKI?
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-gray-600">
                                Become a seller or apply as a SUKI Rider.
                            </p>

                        </div>

                    </div>


                    <div class="mt-4 grid gap-2">

                        <a
                            href="{{ route('register') }}"
                            class="flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-[#155244]"
                        >

                            <i
                                data-lucide="store"
                                class="h-4 w-4"
                            ></i>

                            Become a Seller

                        </a>


                        <a
                            href="{{ route('register') }}"
                            class="flex items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-4 py-2.5 text-xs font-semibold text-[#1F6F5B] transition hover:bg-[#F8FAF8]"
                        >

                            <i
                                data-lucide="bike"
                                class="h-4 w-4"
                            ></i>

                            Apply as Rider

                        </a>

                    </div>

                </section>

            </aside>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- EDIT PROFILE MODAL --}}
{{-- ========================================================= --}}

<div
    id="editProfileModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4"
>

    <div
        class="w-full max-w-lg rounded-2xl bg-white shadow-xl"
        onclick="event.stopPropagation()"
    >

        {{-- HEADER --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">

            <div>

                <h2 class="text-lg font-semibold text-gray-900">
                    Edit Profile
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Update your personal information.
                </p>

            </div>


            <button
                type="button"
                onclick="closeEditProfile()"
                class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
            >

                <i
                    data-lucide="x"
                    class="h-5 w-5"
                ></i>

            </button>

        </div>


        {{-- FORM --}}
        <form
            action="{{ route('buyer.account.update') }}"
            method="POST"
        >

            @csrf

            <div class="space-y-4 px-5 py-5">

                <div class="grid gap-4 sm:grid-cols-2">

                    <div>

                        <label class="text-xs font-medium text-gray-600">
                            First Name
                        </label>

                        <input
                            type="text"
                            name="first_name"
                            value="{{ session('buyer_profile.first_name', '') }}"
                            required
                            class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>


                    <div>

                        <label class="text-xs font-medium text-gray-600">
                            Last Name
                        </label>

                        <input
                            type="text"
                            name="last_name"
                            value="{{ session('buyer_profile.last_name', '') }}"
                            required
                            class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>

                </div>


                <div>

                    <label class="text-xs font-medium text-gray-600">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="{{ session('buyer_profile.phone', '') }}"
                        required
                        class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >

                </div>


                <div>

                    <label class="text-xs font-medium text-gray-600">
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="{{ session('buyer_profile.email', '') }}"
                        required
                        class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >

                </div>

            </div>


            {{-- BUTTONS --}}
            <div class="flex gap-3 border-t border-gray-100 px-5 py-4">

                <button
                    type="button"
                    onclick="closeEditProfile()"
                    class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


{{-- ========================================================= --}}
{{-- CHANGE PASSWORD MODAL --}}
{{-- ========================================================= --}}

<div
    id="passwordModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4"
>

    <div
        class="w-full max-w-md rounded-2xl bg-white shadow-xl"
        onclick="event.stopPropagation()"
    >

        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">

            <div>

                <h2 class="text-lg font-semibold text-gray-900">
                    Change Password
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Keep your SUKI account secure.
                </p>

            </div>


            <button
                type="button"
                onclick="closePasswordModal()"
                class="flex h-8 w-8 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100"
            >

                <i
                    data-lucide="x"
                    class="h-5 w-5"
                ></i>

            </button>

        </div>


        <form
            action="{{ route('buyer.account.password') }}"
            method="POST"
        >

            @csrf

            <div class="space-y-4 px-5 py-5">

                <div>

                    <label class="text-xs font-medium text-gray-600">
                        Current Password
                    </label>

                    <input
                        type="password"
                        name="current_password"
                        required
                        class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >

                </div>


                <div>

                    <label class="text-xs font-medium text-gray-600">
                        New Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        required
                        minlength="8"
                        class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >

                </div>


                <div>

                    <label class="text-xs font-medium text-gray-600">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        minlength="8"
                        class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                    >

                </div>

            </div>


            <div class="flex gap-3 border-t border-gray-100 px-5 py-4">

                <button
                    type="button"
                    onclick="closePasswordModal()"
                    class="flex-1 rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white hover:bg-[#155244]"
                >
                    Update Password
                </button>

            </div>

        </form>

    </div>

</div>


{{-- ========================================================= --}}
{{-- SCRIPT --}}
{{-- ========================================================= --}}

<script>

    function openEditProfile() {
        const modal = document.getElementById('editProfileModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');
    }


    function closeEditProfile() {
        const modal = document.getElementById('editProfileModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');
    }


    function openPasswordModal() {
        const modal = document.getElementById('passwordModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');
    }


    function closePasswordModal() {
        const modal = document.getElementById('passwordModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');
    }


    document.getElementById('editProfileModal')?.addEventListener('click', function(event) {

        if (event.target === this) {
            closeEditProfile();
        }

    });


    document.getElementById('passwordModal')?.addEventListener('click', function(event) {

        if (event.target === this) {
            closePasswordModal();
        }

    });

</script>

@endsection