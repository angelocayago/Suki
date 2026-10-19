@extends('layouts.app')

@section('content')

@php
    $profilePhotoUrl = $user->profile_photo_url;
  $maxBirthday = \Illuminate\Support\Carbon::today('Asia/Manila')
        ->subYears(18)
        ->toDateString();
@endphp



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

                        <div class="flex items-center justify-between gap-4">

    <div class="flex items-center gap-4">

        {{-- AVATAR --}}
        <div
            class="flex h-20 w-20 shrink-0
                   items-center justify-center
                   overflow-hidden rounded-full
                   border-2 border-white
                   bg-[#DDF3EC]
                   shadow-sm ring-1 ring-gray-200"
        >

            @if($profilePhotoUrl)

                <img
                    src="{{ $profilePhotoUrl }}"
                    alt="Profile picture"
                    class="h-full w-full object-cover"
                >

            @else

                <i
                    data-lucide="user-round"
                    class="h-8 w-8 text-[#1F6F5B]"
                ></i>

            @endif

        </div>


        {{-- NAME + EMAIL --}}
        <div>

            <h2 class="text-lg font-semibold text-gray-900">

                {{ $user->first_name ?? 'SUKI SHOP' }}

                @if($user->middle_initial)
                    {{ $user->middle_initial }}.
                @endif

                {{ $user->last_name ?? 'Buyer' }}

            </h2>


            <p class="mt-1 text-sm text-gray-500">

                {{ $user->email }}

            </p>

        </div>

    </div>


    {{-- ACTIONS --}}
    <div class="flex flex-wrap items-center gap-2">

        @if($profilePhotoUrl)

            <form
                action="{{ route('buyer.account.photo.remove') }}"
                method="POST"
            >

                @csrf

                <button
                    type="submit"
                    onclick="return confirm('Remove your profile picture?')"
                    class="inline-flex items-center
                           justify-center gap-2
                           rounded-xl
                           border border-red-200
                           bg-white px-4 py-2.5
                           text-sm font-semibold
                           text-red-500 transition
                           hover:bg-red-50"
                >

                    <i
                        data-lucide="trash-2"
                        class="h-4 w-4"
                    ></i>

                    Remove Photo

                </button>

            </form>

        @endif


        <button
            type="button"
            onclick="openEditProfile()"
            class="inline-flex items-center
                   justify-center gap-2
                   rounded-xl
                   border border-[#1F6F5B]
                   bg-white px-4 py-2.5
                   text-sm font-semibold
                   text-[#1F6F5B]
                   transition
                   hover:bg-[#EEF8F3]"
        >

            <i
                data-lucide="pencil"
                class="h-4 w-4"
            ></i>

            Edit Profile

        </button>

    </div>

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
                    {{ $user->first_name ?: 'Not provided' }}
                </span>

            </div>

        </div>


        {{-- MIDDLE INITIAL --}}
        <div>

            <p class="text-xs font-medium text-gray-400">
                Middle Initial
            </p>

            <div class="mt-2 flex items-center gap-2">

                <i
                    data-lucide="user"
                    class="h-4 w-4 text-gray-400"
                ></i>

                <span class="text-sm font-medium text-gray-700">
                    {{ $user->middle_initial ?: 'Not provided' }}
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
                    {{ $user->last_name ?: 'Not provided' }}
                </span>

            </div>

        </div>


        {{-- SEX --}}
        <div>

            <p class="text-xs font-medium text-gray-400">
                Sex
            </p>

            <div class="mt-2 flex items-center gap-2">

                <i
                    data-lucide="users"
                    class="h-4 w-4 text-gray-400"
                ></i>

                <span class="text-sm font-medium text-gray-700">
                    {{ $user->sex ?: 'Not provided' }}
                </span>

            </div>

        </div>


        {{-- BIRTHDAY --}}
        <div>

            <p class="text-xs font-medium text-gray-400">
                Birthday
            </p>

            <div class="mt-2 flex items-center gap-2">

                <i
                    data-lucide="calendar"
                    class="h-4 w-4 text-gray-400"
                ></i>

                <span class="text-sm font-medium text-gray-700">
                    {{
                        $user->birthday
                            ? $user->birthday->format('F d, Y')
                            : 'Not provided'
                    }}
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
                    {{ $user->phone ?: 'Not provided' }}
                </span>

            </div>

        </div>


        {{-- EMAIL --}}
        <div class="sm:col-span-2">

            <p class="text-xs font-medium text-gray-400">
                Email Address
            </p>

            <div class="mt-2 flex items-center gap-2">

                <i
                    data-lucide="mail"
                    class="h-4 w-4 text-gray-400"
                ></i>

                <span class="break-all text-sm font-medium text-gray-700">
                    {{ $user->email }}
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
                                Want to earn with SUKI SHOP?
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-gray-600">
                                Become a seller or apply as a SUKI SHOP Rider.
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
    enctype="multipart/form-data"
>

            @csrf

            <div class="space-y-5 px-5 py-5">

    {{-- PROFILE PICTURE --}}
    <div
        class="flex flex-col items-center
               rounded-2xl border
               border-gray-100
               bg-[#F8FAF8]
               p-5"
    >

        <div
            class="h-24 w-24
                   overflow-hidden
                   rounded-full
                   bg-[#DDF3EC]
                   ring-1 ring-gray-200"
        >

            <img
                id="profilePhotoPreview"
                src="{{ $profilePhotoUrl ?: '' }}"
                alt="Profile preview"
                class="{{ $profilePhotoUrl ? '' : 'hidden' }}
                       h-full w-full object-cover"
            >

            <div
                id="profilePhotoPlaceholder"
                class="{{ $profilePhotoUrl ? 'hidden' : 'flex' }}
                       h-full w-full
                       items-center
                       justify-center"
            >

                <i
                    data-lucide="user-round"
                    class="h-10 w-10 text-[#1F6F5B]"
                ></i>

            </div>

        </div>

        <label
            for="profile_photo"
            class="mt-4 cursor-pointer
                   rounded-xl
                   border border-[#1F6F5B]
                   bg-white
                   px-4 py-2
                   text-xs font-semibold
                   text-[#1F6F5B]
                   transition
                   hover:bg-[#EEF8F3]"
        >
            Choose Profile Picture
        </label>

        <input
            id="profile_photo"
            name="profile_photo"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
            onchange="previewProfilePhoto(this)"
        >

        <p
            id="selectedProfilePhotoName"
            class="mt-2 max-w-full truncate
                   text-xs text-gray-500"
        ></p>

        <p class="mt-2 text-center text-[11px] text-gray-400">
            JPG, PNG or WEBP · Maximum 2 MB
        </p>

        @error('profile_photo')
            <p class="mt-2 text-xs text-red-600">
                {{ $message }}
            </p>
        @enderror

    </div>


    {{-- NAME FIELDS --}}
    <div class="grid gap-4 sm:grid-cols-2">

        <div>

            <label class="text-xs font-medium text-gray-600">
                First Name
            </label>

            <input
                type="text"
                name="first_name"
                value="{{ old('first_name', $user->first_name) }}"
                required
                class="mt-2 w-full
                       rounded-xl border
                       border-gray-200
                       px-4 py-3 text-sm
                       outline-none transition
                       focus:border-[#1F6F5B]
                       focus:ring-2
                       focus:ring-[#DDF3EC]"
            >

            @error('first_name')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror

        </div>


        <div>

            <label class="text-xs font-medium text-gray-600">
                Middle Initial
            </label>

            <input
                type="text"
                name="middle_initial"
                value="{{ old('middle_initial', $user->middle_initial) }}"
                maxlength="10"
                class="mt-2 w-full
                       rounded-xl border
                       border-gray-200
                       px-4 py-3 text-sm
                       outline-none transition
                       focus:border-[#1F6F5B]
                       focus:ring-2
                       focus:ring-[#DDF3EC]"
            >

            @error('middle_initial')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror

        </div>


        <div class="sm:col-span-2">

            <label class="text-xs font-medium text-gray-600">
                Last Name
            </label>

            <input
                type="text"
                name="last_name"
                value="{{ old('last_name', $user->last_name) }}"
                required
                class="mt-2 w-full
                       rounded-xl border
                       border-gray-200
                       px-4 py-3 text-sm
                       outline-none transition
                       focus:border-[#1F6F5B]
                       focus:ring-2
                       focus:ring-[#DDF3EC]"
            >

            @error('last_name')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror

        </div>

    </div>


    {{-- SEX + BIRTHDAY --}}
    <div class="grid gap-4 sm:grid-cols-2">

        <div>

            <label class="text-xs font-medium text-gray-600">
                Sex
            </label>

            <select
                name="sex"
                required
                class="mt-2 w-full
                       rounded-xl border
                       border-gray-200
                       bg-white
                       px-4 py-3 text-sm
                       outline-none
                       focus:border-[#1F6F5B]
                       focus:ring-2
                       focus:ring-[#DDF3EC]"
            >

                <option value="">
                    Select
                </option>

                <option
                    value="Male"
                    @selected(old('sex', $user->sex) === 'Male')
                >
                    Male
                </option>

                <option
                    value="Female"
                    @selected(old('sex', $user->sex) === 'Female')
                >
                    Female
                </option>

            </select>

            @error('sex')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror

        </div>


        <div>

            <label class="text-xs font-medium text-gray-600">
                Birthday
            </label>

            <input
    type="date"
    name="birthday"
    value="{{ old('birthday', $user->birthday?->format('Y-m-d')) }}"
    max="{{ $maxBirthday }}"
    required
    class="mt-2 w-full
           rounded-xl border
           border-gray-200
           px-4 py-3 text-sm
           outline-none
           focus:border-[#1F6F5B]
           focus:ring-2
           focus:ring-[#DDF3EC]"
>

            @error('birthday')
                <p class="mt-1 text-xs text-red-600">
                    {{ $message }}
                </p>
            @enderror

        </div>

    </div>


    {{-- PHONE --}}
    <div>

        <label class="text-xs font-medium text-gray-600">
            Phone Number
        </label>

        <input
            type="tel"
            name="phone"
            value="{{ old('phone', $user->phone) }}"
            placeholder="09XXXXXXXXX"
            required
            class="mt-2 w-full
                   rounded-xl border
                   border-gray-200
                   px-4 py-3 text-sm
                   outline-none
                   focus:border-[#1F6F5B]
                   focus:ring-2
                   focus:ring-[#DDF3EC]"
        >

        @error('phone')
            <p class="mt-1 text-xs text-red-600">
                {{ $message }}
            </p>
        @enderror

    </div>


    {{-- EMAIL --}}
    <div>

        <label class="text-xs font-medium text-gray-600">
            Email Address
        </label>

        <input
            type="email"
            name="email"
            value="{{ old('email', $user->email) }}"
            required
            class="mt-2 w-full
                   rounded-xl border
                   border-gray-200
                   px-4 py-3 text-sm
                   outline-none
                   focus:border-[#1F6F5B]
                   focus:ring-2
                   focus:ring-[#DDF3EC]"
        >

        @error('email')
            <p class="mt-1 text-xs text-red-600">
                {{ $message }}
            </p>
        @enderror

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
                    Keep your SUKI SHOP account secure.
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

    function previewProfilePhoto(input) {

        const file = input.files?.[0];

        const preview =
            document.getElementById('profilePhotoPreview');

        const placeholder =
            document.getElementById('profilePhotoPlaceholder');

        const fileName =
            document.getElementById('selectedProfilePhotoName');


        if (!file) {
            return;
        }


        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];


        if (!allowedTypes.includes(file.type)) {

            alert(
                'Please choose a JPG, PNG, or WEBP image.'
            );

            input.value = '';

            return;
        }


        if (file.size > 2 * 1024 * 1024) {

            alert(
                'Profile picture must be 2 MB or smaller.'
            );

            input.value = '';

            return;
        }


        preview.src =
            URL.createObjectURL(file);

        preview.classList.remove(
            'hidden'
        );

        placeholder.classList.add(
            'hidden'
        );

        fileName.textContent =
            file.name;
    }


    function openEditProfile() {

        const modal =
            document.getElementById(
                'editProfileModal'
            );

        modal.classList.remove(
            'hidden'
        );

        modal.classList.add(
            'flex'
        );

        document.body.classList.add(
            'overflow-hidden'
        );
    }


    function closeEditProfile() {

        const modal =
            document.getElementById(
                'editProfileModal'
            );

        modal.classList.add(
            'hidden'
        );

        modal.classList.remove(
            'flex'
        );

        document.body.classList.remove(
            'overflow-hidden'
        );
    }


    function openPasswordModal() {

        const modal =
            document.getElementById(
                'passwordModal'
            );

        modal.classList.remove(
            'hidden'
        );

        modal.classList.add(
            'flex'
        );

        document.body.classList.add(
            'overflow-hidden'
        );
    }


    function closePasswordModal() {

        const modal =
            document.getElementById(
                'passwordModal'
            );

        modal.classList.add(
            'hidden'
        );

        modal.classList.remove(
            'flex'
        );

        document.body.classList.remove(
            'overflow-hidden'
        );
    }


    document
        .getElementById(
            'editProfileModal'
        )
        ?.addEventListener(
            'click',
            function (event) {

                if (event.target === this) {
                    closeEditProfile();
                }

            }
        );


    document
        .getElementById(
            'passwordModal'
        )
        ?.addEventListener(
            'click',
            function (event) {

                if (event.target === this) {
                    closePasswordModal();
                }

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Re-open Edit Profile modal
    | kapag may validation error
    |--------------------------------------------------------------------------
    */

    @if(
        $errors->hasAny([
            'first_name',
            'middle_initial',
            'last_name',
            'sex',
            'birthday',
            'phone',
            'email',
            'profile_photo'
        ])
    )

        document.addEventListener(
            'DOMContentLoaded',
            function () {
                openEditProfile();
            }
        );

    @endif

</script>

@endsection