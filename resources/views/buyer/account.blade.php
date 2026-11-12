@extends('layouts.app')

@section('content')

@php
    $passwordSection = request()->query('section') === 'password';
    $accountUser = auth()->user();
    $profileName = trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? ''));
    $profileHasErrors = $errors->hasAny(['first_name', 'last_name', 'email', 'phone']);
@endphp
<x-buyer.account-shell :active="$passwordSection ? 'password' : 'profile'">
    @if($passwordSection)
        @include('buyer.partials.account-password')
    @else

        {{-- PAGE HEADER --}}
        <div class="mb-8">

            <h1 class="text-2xl font-bold text-gray-900">
                Profile
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Manage your personal information and view your account details.
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


        <div class="buyer-account-panels">


            <section class="rounded-2xl border border-gray-200 bg-white" aria-labelledby="profile-name">
                <div class="flex flex-col gap-5 border-b border-gray-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <x-profile-avatar :user="$accountUser" :name="$profileName" size="h-16 w-16 shrink-0" :circular="true" />
                        <div class="min-w-0">
                            <h2 id="profile-name" data-profile-name class="text-lg font-semibold text-[#173F35]">{{ $profileName !== '' ? $profileName : 'Name not provided' }}</h2>
                            <p data-profile-email class="mt-1 break-all text-sm text-gray-500">{{ filled($profile['email'] ?? null) ? $profile['email'] : 'Not provided' }}</p>
                        </div>
                    </div>
                    <button type="button" onclick="openEditProfile()" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-[#1F6F5B] bg-white px-4 py-2.5 text-sm font-semibold text-[#1F6F5B] hover:bg-[#EEF8F3]">
                        <i data-lucide="pencil" class="h-4 w-4" aria-hidden="true"></i> Edit Profile
                    </button>
                </div>
                <div class="p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-900">Personal Information</h3>
                    <p class="mt-1 text-xs text-gray-500">Your saved name and contact details.</p>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        @foreach(['first_name' => 'First Name', 'last_name' => 'Last Name', 'phone' => 'Contact Number', 'email' => 'Email Address'] as $profileField => $label)
                            <div>
                                <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                                <dd data-profile-field="{{ $profileField }}" class="mt-2 break-words text-sm font-medium text-gray-700">{{ filled($profile[$profileField] ?? null) ? $profile[$profileField] : 'Not provided' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6" aria-labelledby="account-details-heading" data-profile-details>
                <h2 id="account-details-heading" class="text-sm font-semibold text-gray-900">Account Details</h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    @if($accountUser->birthday)
                        <div>
                            <dt class="text-xs font-medium text-gray-500">Birthday</dt>
                            <dd data-profile-birthday class="mt-2 text-sm font-medium text-gray-700">{{ $accountUser->birthday->format('F j, Y') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Member Since</dt>
                        <dd data-profile-member-since class="mt-2 text-sm font-medium text-gray-700">{{ \App\Support\ManilaTime::format($accountUser->created_at, 'F j, Y') ?? 'Not available' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">Account Status</dt>
                        <dd data-profile-status class="mt-2 text-sm font-medium text-gray-700">{{ filled($accountUser->status) ? ucfirst($accountUser->status) : 'Not available' }}</dd>
                    </div>
                </dl>
            </section>


            <aside class="buyer-account-opportunities">

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

    @endif
</x-buyer.account-shell>


{{-- ========================================================= --}}
{{-- EDIT PROFILE MODAL --}}
{{-- ========================================================= --}}

<div
    id="editProfileModal"
    role="dialog" aria-modal="true" aria-labelledby="edit-profile-title" data-profile-errors="{{ $profileHasErrors ? 'true' : 'false' }}"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4"
>

    <div
        class="max-h-[calc(100dvh-3rem)] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl"
        onclick="event.stopPropagation()"
    >

        {{-- HEADER --}}
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">

            <div>

                <h2 id="edit-profile-title" class="text-lg font-semibold text-gray-900">
                    Edit Profile
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Update your personal information.
                </p>

            </div>


            <button
                type="button"
                onclick="closeEditProfile()"
                aria-label="Close Edit Profile"
                class="flex h-11 w-11 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
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
                @if($profileHasErrors)
                    <p role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">Please check the highlighted profile fields.</p>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach(['first_name' => ['First Name', 'text', 'given-name'], 'last_name' => ['Last Name', 'text', 'family-name'], 'phone' => ['Phone Number', 'text', 'tel'], 'email' => ['Email Address', 'email', 'email']] as $profileField => [$label, $type, $autocomplete])
                        <div @class(['sm:col-span-2' => in_array($profileField, ['phone', 'email'], true)])>
                            <label for="edit-profile-{{ $profileField }}" class="text-xs font-medium text-gray-600">{{ $label }}</label>
                            <input id="edit-profile-{{ $profileField }}" type="{{ $type }}" name="{{ $profileField }}" autocomplete="{{ $autocomplete }}"
                                value="{{ old($profileField, $profile[$profileField] ?? '') }}" required
                                aria-invalid="{{ $errors->has($profileField) ? 'true' : 'false' }}" aria-describedby="edit-profile-{{ $profileField }}-error"
                                class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]">
                            <p id="edit-profile-{{ $profileField }}-error" class="mt-1 text-xs text-red-700">@error($profileField){{ $message }}@enderror</p>
                        </div>
                    @endforeach
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
{{-- SCRIPT --}}
{{-- ========================================================= --}}

<script>

    function openEditProfile() {
        const modal = document.getElementById('editProfileModal');

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.classList.add('overflow-hidden');
        modal.querySelector('[aria-invalid="true"]')?.focus();
    }


    function closeEditProfile() {
        const modal = document.getElementById('editProfileModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.classList.remove('overflow-hidden');
    }


    document.getElementById('editProfileModal')?.addEventListener('click', function(event) {

        if (event.target === this) {
            closeEditProfile();
        }

    });


    if (window.location.hash === '#editProfileModal' || document.getElementById('editProfileModal')?.dataset.profileErrors === 'true') openEditProfile();

</script>

@endsection