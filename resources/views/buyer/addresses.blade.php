@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

        {{-- ===================================================== --}}
        {{-- HEADER --}}
        {{-- ===================================================== --}}

        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

            <div>

                <div class="flex items-center gap-2">

                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#DDF3EC]">

                        <i
                            data-lucide="map-pin"
                            class="h-5 w-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                    <h1 class="text-2xl font-bold text-gray-900">
                        My Addresses
                    </h1>

                </div>

                <p class="mt-2 text-sm text-gray-500">
                    Manage your saved delivery addresses for faster checkout.
                </p>

            </div>


            <button
                type="button"
                onclick="openAddressModal()"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#155244]"
            >

                <i
                    data-lucide="plus"
                    class="h-4 w-4"
                ></i>

                Add New Address

            </button>

        </div>


        {{-- ===================================================== --}}
        {{-- SUCCESS --}}
        {{-- ===================================================== --}}

        @if(session('success'))

            <div class="mb-6 flex items-center gap-3 rounded-xl border border-[#BFE7D8] bg-[#DDF3EC] px-4 py-3 text-sm text-[#155244]">

                <i
                    data-lucide="check-circle"
                    class="h-5 w-5 shrink-0"
                ></i>

                <span>
                    {{ session('success') }}
                </span>

            </div>

        @endif


        {{-- ===================================================== --}}
        {{-- ERROR --}}
        {{-- ===================================================== --}}

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


        {{-- ===================================================== --}}
        {{-- ADDRESS COUNT --}}
        {{-- ===================================================== --}}

        @if(!empty($addresses))

            <div class="mb-4 flex items-center justify-between">

                <div>

                    <h2 class="text-sm font-semibold text-gray-900">
                        Saved Addresses
                    </h2>

                    <p class="mt-1 text-xs text-gray-500">
                        {{ count($addresses) }} saved address{{ count($addresses) !== 1 ? 'es' : '' }}
                    </p>

                </div>

            </div>

        @endif


        {{-- ===================================================== --}}
        {{-- EMPTY STATE --}}
        {{-- ===================================================== --}}

        @if(empty($addresses))

            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center">

                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#DDF3EC]">

                    <i
                        data-lucide="map-pin"
                        class="h-7 w-7 text-[#1F6F5B]"
                    ></i>

                </div>


                <h2 class="mt-5 text-lg font-semibold text-gray-900">
                    No saved addresses yet
                </h2>


                <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-gray-500">
                    Add your delivery address so you can quickly select it during checkout.
                </p>


                <button
                    type="button"
                    onclick="openAddressModal()"
                    class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#1F6F5B] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#155244]"
                >

                    <i
                        data-lucide="plus"
                        class="h-4 w-4"
                    ></i>

                    Add Your First Address

                </button>

            </div>

        @else


            {{-- ================================================= --}}
            {{-- ADDRESS LIST --}}
            {{-- ================================================= --}}

            <div class="grid gap-4 lg:grid-cols-2">

                @foreach($addresses as $address)

                    <article
                        class="
                            relative overflow-hidden rounded-2xl border bg-white p-5 transition
                            {{ $address['is_default']
                                ? 'border-[#BFE7D8] ring-1 ring-[#DDF3EC]'
                                : 'border-gray-200 hover:border-gray-300' }}
                        "
                    >

                        {{-- DEFAULT TOP LINE --}}
                        @if($address['is_default'])

                            <div class="absolute inset-x-0 top-0 h-1 bg-[#1F6F5B]"></div>

                        @endif


                        {{-- ADDRESS HEADER --}}
                        <div class="flex items-start justify-between gap-4">

                            <div class="flex min-w-0 items-start gap-3">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EEF8F3]">

                                    <i
                                        data-lucide="map-pin"
                                        class="h-5 w-5 text-[#1F6F5B]"
                                    ></i>

                                </div>


                                <div class="min-w-0">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h2 class="text-sm font-semibold text-gray-900">
                                            {{ $address['name'] }}
                                        </h2>

                                        @if($address['is_default'])

                                            <span class="inline-flex items-center gap-1 rounded-full bg-[#DDF3EC] px-2.5 py-1 text-[11px] font-semibold text-[#1F6F5B]">

                                                <i
                                                    data-lucide="check"
                                                    class="h-3 w-3"
                                                ></i>

                                                Default

                                            </span>

                                        @endif

                                    </div>


                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $address['phone'] }}
                                    </p>

                                </div>

                            </div>


                            {{-- LABEL --}}
                            <span class="shrink-0 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-medium text-gray-500">

                                {{ $address['label'] }}

                            </span>

                        </div>


                        {{-- DIVIDER --}}
                        <div class="my-4 border-t border-gray-100"></div>


                        {{-- FULL ADDRESS --}}
                        <div class="flex items-start gap-2">

                            <i
                                data-lucide="navigation"
                                class="mt-0.5 h-4 w-4 shrink-0 text-gray-400"
                            ></i>

                            <p class="text-sm leading-6 text-gray-600">

                                @if(!empty($address['house_number']))

                                    {{ $address['house_number'] }},

                                @endif

                                {{ $address['street'] }},

                                {{ $address['barangay'] }},

                                {{ $address['municipality'] }},

                                {{ $address['province'] }}

                                {{ $address['postal_code'] }}

                            </p>

                        </div>


                        {{-- ACTIONS --}}
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">

                            <div>

                                @if(!$address['is_default'])

                                    <form
                                        action="{{ route('buyer.addresses.default', $address['id']) }}"
                                        method="POST"
                                    >

                                        @csrf

                                        <button
                                            type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:border-[#1F6F5B] hover:bg-[#EEF8F3] hover:text-[#1F6F5B]"
                                        >

                                            <i
                                                data-lucide="check"
                                                class="h-3.5 w-3.5"
                                            ></i>

                                            Set as Default

                                        </button>

                                    </form>

                                @else

                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[#1F6F5B]">

                                        <i
                                            data-lucide="badge-check"
                                            class="h-4 w-4"
                                        ></i>

                                        Default delivery address

                                    </span>

                                @endif

                            </div>


                            <form
                                action="{{ route('buyer.addresses.delete', $address['id']) }}"
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to remove this address?')"
                            >

                                @csrf

                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-100 px-3 py-2 text-xs font-semibold text-red-500 transition hover:bg-red-50"
                                >

                                    <i
                                        data-lucide="trash-2"
                                        class="h-3.5 w-3.5"
                                    ></i>

                                    Delete

                                </button>

                            </form>

                        </div>

                    </article>

                @endforeach

            </div>

        @endif

    </div>

</div>


{{-- ========================================================= --}}
{{-- ADD ADDRESS MODAL --}}
{{-- ========================================================= --}}

<div
    id="addressModal"
    class="fixed inset-0 z-50 hidden"
>

    {{-- OVERLAY --}}
    <div
        class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"
        onclick="closeAddressModal()"
    ></div>


    {{-- MODAL WRAPPER --}}
    <div class="relative flex min-h-full items-center justify-center p-4">

        <div
            class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl"
            onclick="event.stopPropagation()"
        >

            {{-- ================================================= --}}
            {{-- MODAL HEADER --}}
            {{-- ================================================= --}}

            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-5 sm:px-6">

                <div class="flex items-center gap-3">

                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#DDF3EC]">

                        <i
                            data-lucide="map-pin"
                            class="h-5 w-5 text-[#1F6F5B]"
                        ></i>

                    </div>


                    <div>

                        <h2 class="text-lg font-semibold text-gray-900">
                            Add New Address
                        </h2>

                        <p class="mt-1 text-xs text-gray-500">
                            Enter your complete delivery information.
                        </p>

                    </div>

                </div>


                <button
                    type="button"
                    onclick="closeAddressModal()"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                >

                    <i
                        data-lucide="x"
                        class="h-5 w-5"
                    ></i>

                </button>

            </div>


            {{-- ================================================= --}}
            {{-- FORM --}}
            {{-- ================================================= --}}

            <form
                action="{{ route('buyer.addresses.store') }}"
                method="POST"
                class="max-h-[75vh] overflow-y-auto"
            >

                @csrf


                <div class="space-y-5 px-5 py-6 sm:px-6">


                    {{-- NAME + PHONE --}}
                    <div class="grid gap-5 sm:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Full Name
                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="user"
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>

                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    placeholder="Juan Dela Cruz"
                                    required
                                    class="w-full rounded-xl border border-gray-200 py-3 pl-10 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                                >

                            </div>

                            @error('name')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>


                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Phone Number
                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="phone"
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>

                                <input
                                    type="text"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    placeholder="0912 345 6789"
                                    required
                                    class="w-full rounded-xl border border-gray-200 py-3 pl-10 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                                >

                            </div>

                            @error('phone')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>

                    </div>


                    {{-- PROVINCE + MUNICIPALITY --}}
                    <div class="grid gap-5 sm:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Province
                            </label>

                            <input
                                type="text"
                                name="province"
                                value="{{ old('province') }}"
                                placeholder="Laguna"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                            >

                            @error('province')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>


                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Municipality / City
                            </label>

                            <input
                                type="text"
                                name="municipality"
                                value="{{ old('municipality') }}"
                                placeholder="Santa Rosa"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                            >

                            @error('municipality')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>

                    </div>


                    {{-- BARANGAY + POSTAL --}}
                    <div class="grid gap-5 sm:grid-cols-2">

                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Barangay
                            </label>

                            <input
                                type="text"
                                name="barangay"
                                value="{{ old('barangay') }}"
                                placeholder="San Antonio"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                            >

                            @error('barangay')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>


                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">
                                Postal Code
                            </label>

                            <input
                                type="text"
                                name="postal_code"
                                value="{{ old('postal_code') }}"
                                placeholder="4026"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                            >

                            @error('postal_code')

                                <p class="mt-1 text-xs text-red-500">
                                    {{ $message }}
                                </p>

                            @enderror

                        </div>

                    </div>


                    {{-- STREET --}}
                    <div>

                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Street / Subdivision
                        </label>

                        <input
                            type="text"
                            name="street"
                            value="{{ old('street') }}"
                            placeholder="Main Street"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                        @error('street')

                            <p class="mt-1 text-xs text-red-500">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- HOUSE NUMBER --}}
                    <div>

                        <label class="mb-2 block text-sm font-medium text-gray-700">

                            House Number / Unit

                            <span class="font-normal text-gray-400">
                                (Optional)
                            </span>

                        </label>

                        <input
                            type="text"
                            name="house_number"
                            value="{{ old('house_number') }}"
                            placeholder="House 123 / Unit 4B"
                            class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#DDF3EC]"
                        >

                    </div>


                    {{-- ADDRESS LABEL --}}
                    <div>

                        <label class="mb-2 block text-sm font-medium text-gray-700">
                            Address Label
                        </label>

                        <div class="grid grid-cols-3 gap-2">

                            {{-- HOME --}}
                            <label class="cursor-pointer">

                                <input
                                    type="radio"
                                    name="label"
                                    value="Home"
                                    class="peer sr-only"
                                    {{ old('label', 'Home') === 'Home' ? 'checked' : '' }}
                                >

                                <span class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-3 py-3 text-xs font-medium text-gray-600 transition peer-checked:border-[#1F6F5B] peer-checked:bg-[#DDF3EC] peer-checked:text-[#1F6F5B]">

                                    <i
                                        data-lucide="house"
                                        class="h-4 w-4"
                                    ></i>

                                    Home

                                </span>

                            </label>


                            {{-- WORK --}}
                            <label class="cursor-pointer">

                                <input
                                    type="radio"
                                    name="label"
                                    value="Work"
                                    class="peer sr-only"
                                    {{ old('label') === 'Work' ? 'checked' : '' }}
                                >

                                <span class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-3 py-3 text-xs font-medium text-gray-600 transition peer-checked:border-[#1F6F5B] peer-checked:bg-[#DDF3EC] peer-checked:text-[#1F6F5B]">

                                    <i
                                        data-lucide="briefcase"
                                        class="h-4 w-4"
                                    ></i>

                                    Work

                                </span>

                            </label>


                            {{-- OTHER --}}
                            <label class="cursor-pointer">

                                <input
                                    type="radio"
                                    name="label"
                                    value="Other"
                                    class="peer sr-only"
                                    {{ old('label') === 'Other' ? 'checked' : '' }}
                                >

                                <span class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-3 py-3 text-xs font-medium text-gray-600 transition peer-checked:border-[#1F6F5B] peer-checked:bg-[#DDF3EC] peer-checked:text-[#1F6F5B]">

                                    <i
                                        data-lucide="map-pin"
                                        class="h-4 w-4"
                                    ></i>

                                    Other

                                </span>

                            </label>

                        </div>

                    </div>


                    {{-- DEFAULT --}}
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 transition hover:border-[#BFE7D8]">

                        <input
                            type="checkbox"
                            name="is_default"
                            value="1"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 accent-[#1F6F5B]"
                        >

                        <span>

                            <span class="block text-sm font-medium text-gray-700">
                                Set as my default address
                            </span>

                            <span class="mt-1 block text-xs text-gray-500">
                                This address will be selected automatically during checkout.
                            </span>

                        </span>

                    </label>

                </div>


                {{-- ================================================= --}}
                {{-- MODAL FOOTER --}}
                {{-- ================================================= --}}

                <div class="flex gap-3 border-t border-gray-100 bg-gray-50 px-5 py-4 sm:justify-end sm:px-6">

                    <button
                        type="button"
                        onclick="closeAddressModal()"
                        class="flex-1 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 sm:flex-none"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="flex-1 rounded-xl bg-[#1F6F5B] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#155244] sm:flex-none"
                    >

                        <span class="inline-flex items-center gap-2">

                            <i
                                data-lucide="save"
                                class="h-4 w-4"
                            ></i>

                            Save Address

                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- SCRIPT --}}
{{-- ========================================================= --}}

<script>

    function openAddressModal() {

        const modal = document.getElementById('addressModal');

        modal.classList.remove('hidden');

        document.body.classList.add('overflow-hidden');

    }


    function closeAddressModal() {

        const modal = document.getElementById('addressModal');

        modal.classList.add('hidden');

        document.body.classList.remove('overflow-hidden');

    }


    document
        .getElementById('addressModal')
        ?.addEventListener('keydown', function(event) {

            if (event.key === 'Escape') {

                closeAddressModal();

            }

        });


    document.addEventListener('keydown', function(event) {

        if (event.key === 'Escape') {

            closeAddressModal();

        }

    });

</script>

@endsection