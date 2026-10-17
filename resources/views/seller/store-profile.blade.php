@extends('layouts.seller')

@section('title', 'Store Profile')
@section('page-title', 'Store Profile')
@section('page-subtitle', 'Your shop profile, pickup address and security')

@section('content')

@php
    // The controller provides values from the authenticated owner's persisted store.

    $shopName = $profile['shop_name'] ?? '';
    $businessCategory = $profile['business_category'] ?? '';
    $sellerName = $profile['seller_name'] ?? '';
    $phone = $profile['phone'] ?? '';
    $email = $profile['email'] ?? '';

    $description = $profile['description'] ?? '';

    $province = $profile['province'] ?? '';
    $municipality = $profile['municipality'] ?? '';
    $barangay = $profile['barangay'] ?? '';
    $streetAddress = $profile['address'] ?? '';

    $initialAddressValues = [
        'region' => old('region', $profile['region']),
        'province' => old('province', $province),
        'municipality' => old('municipality', $municipality),
        'barangay' => old('barangay', $barangay),
    ];

    $profilePhoto = $profile['profile_photo'] ?? null;

    $status = $profile['status'] ?? '';

    $requiredSellerDocuments = config(
        'seller_document_requirements.requirements.' . ($profile['seller_type'] ?? ''),
        []
    );
    $storedSellerDocuments = $profile['documents'] ?? [];
    $isVerified = $status === 'approved';

    foreach ($requiredSellerDocuments as $requiredSellerDocument) {
        $isVerified = $isVerified
            && ! empty($storedSellerDocuments[$requiredSellerDocument]);
    }

    $accountStatus = match ($status) {
        'approved' => 'Active',
        'pending' => 'Pending',
        'suspended' => 'Suspended',
        'rejected' => 'Rejected',
        default => 'Not available',
    };

    $memberSince = null;

    if (!empty($profile['created_at'])) {
        try {
            $memberSince = \Illuminate\Support\Carbon::parse(
                $profile['created_at']
            )->format('F j, Y');
        } catch (\Throwable $e) {
            $memberSince = null;
        }
    }
@endphp


<div class="mx-auto max-w-5xl space-y-6">


    {{-- SUCCESS MESSAGE --}}
    @if(session('success'))

        <div
            class="rounded-2xl border border-emerald-200
                   bg-emerald-50 px-4 py-3
                   text-sm text-emerald-700"
        >
            {{ session('success') }}
        </div>

    @endif


    {{-- ERROR MESSAGE --}}
    @if($errors->any())

        <div
            class="rounded-2xl border border-red-200
                   bg-red-50 px-4 py-3
                   text-sm text-red-700"
        >

            <p class="font-semibold">
                Please check the form.
            </p>

            <ul class="mt-2 list-disc space-y-1 pl-5">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif



    {{-- =====================================================
        SECTION NAVIGATION
    ====================================================== --}}

    <div class="flex flex-wrap gap-2">

        <a
            href="#shop-profile"
            class="rounded-full border border-gray-200
                   bg-white px-4 py-2
                   text-xs font-medium text-gray-600
                   shadow-sm transition
                   hover:border-[#1F6F5B]
                   hover:text-[#1F6F5B]"
        >
            Shop Profile
        </a>


        <a
            href="#pickup-address"
            class="rounded-full border border-gray-200
                   bg-white px-4 py-2
                   text-xs font-medium text-gray-600
                   shadow-sm transition
                   hover:border-[#1F6F5B]
                   hover:text-[#1F6F5B]"
        >
            Pickup Address
        </a>


        <a
            href="#business-verification"
            class="rounded-full border border-gray-200
                   bg-white px-4 py-2
                   text-xs font-medium text-gray-600
                   shadow-sm transition
                   hover:border-[#1F6F5B]
                   hover:text-[#1F6F5B]"
        >
            Business & Verification
        </a>


        <a
            href="#security"
            class="rounded-full border border-gray-200
                   bg-white px-4 py-2
                   text-xs font-medium text-gray-600
                   shadow-sm transition
                   hover:border-[#1F6F5B]
                   hover:text-[#1F6F5B]"
        >
            Security
        </a>

    </div>




    {{-- =====================================================
        SHOP PROFILE
    ====================================================== --}}

    <section
        id="shop-profile"
        class="scroll-mt-28 rounded-2xl
               border border-gray-200
               bg-white p-6 shadow-sm
               sm:p-7"
    >

        <div class="mb-6">

            <p
                class="text-xs font-bold
                       uppercase tracking-[0.08em]
                       text-[#1F6F5B]"
            >
                Shop Profile
            </p>

            <p class="mt-1 text-xs text-gray-400">
                This is how your shop information appears.
            </p>

        </div>


        <form
            action="{{ route('seller.store.profile.update') }}"
            method="POST"
            enctype="multipart/form-data"
            class="space-y-6"
        >

            @csrf


            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="shopId" class="text-sm font-medium text-gray-700">Shop ID</label>
                    <input id="shopId" readonly value="{{ $store->shop_code }}" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 bg-gray-50">
                </div>
                <div>
                    <label for="assignedCategory" class="text-sm font-medium text-gray-700">Assigned Main Category / Line of Business</label>
                    <input id="assignedCategory" readonly value="{{ $businessCategory }}" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 bg-gray-50">
                    <p class="mt-1 text-xs text-gray-400">Assigned during registration. Contact support for category concerns.</p>
                </div>
            </div>
            <div>
                <label for="shop_name" class="text-sm font-medium text-gray-700">Store Name <span class="text-red-500">*</span></label>
                <input id="shop_name" name="shop_name" required minlength="3" maxlength="30" value="{{ old('shop_name', $shopName) }}" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                @error('shop_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- STORE LOGO --}}
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="shrink-0">
                    <img
                        id="shopPhotoPreview"
                        @if($profilePhoto) src="{{ asset('storage/' . $profilePhoto) }}" @endif
                        alt="Store logo"
                        class="{{ $profilePhoto ? '' : 'hidden' }} h-36 w-36 rounded-[28px] border border-gray-200 bg-[#F8FAF8] object-contain p-2 shadow-sm sm:h-40 sm:w-40"
                    >
                    <div
                        id="shopPhotoPlaceholder"
                        class="{{ $profilePhoto ? 'hidden' : '' }} flex h-36 w-36 items-center justify-center rounded-[28px] border border-gray-200 bg-[#EEF7F3] text-[#1F6F5B] sm:h-40 sm:w-40"
                    >
                        <i data-lucide="store" class="h-10 w-10" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-800">Store Logo</p>
                    <p class="mt-1 text-xs leading-5 text-gray-400">Upload a store logo that represents your business.</p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <label for="profile_photo" class="sr-only">Store logo image file</label>
                        <input id="profile_photo" name="profile_photo" type="file" tabindex="-1" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="logoUploadHelp logoUploadStatus" class="sr-only">
                        <button id="logoUploadButton" type="button" aria-controls="profile_photo" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">
                            {{ $profilePhoto ? 'Replace image' : 'Upload image' }}
                        </button>
                        @if($profilePhoto)
                            <button type="submit" name="image" value="logo" formnovalidate formaction="{{ route('seller.store.profile.photo.remove') }}" aria-label="Remove store logo" class="rounded-xl px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400/40">Remove image</button>
                        @endif
                    </div>
                    <p id="logoUploadHelp" class="mt-2 text-[11px] text-gray-400">JPG, PNG or WEBP - max 2 MB.</p>
                    <p id="logoUploadStatus" role="status" class="mt-1 text-xs text-[#52635B]"></p>
                    @error('profile_photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- STORE BANNER --}}
            <div>
                <p class="text-sm font-medium text-gray-700">Store Banner</p>
                <img
                    id="storeBannerPreview"
                    @if($profile['banner_path']) src="{{ asset('storage/' . $profile['banner_path']) }}" @endif
                    alt="Store banner"
                    class="{{ $profile['banner_path'] ? '' : 'hidden' }} mt-2 h-32 w-full rounded-xl border border-gray-200 object-cover object-center sm:h-40"
                >
                <p id="storeBannerPlaceholder" class="{{ $profile['banner_path'] ? 'hidden' : '' }} mt-2 flex h-32 w-full items-center justify-center rounded-xl border border-gray-200 bg-[#F8FAF8] text-xs text-gray-400 sm:h-40">No store banner uploaded.</p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label for="storeBanner" class="sr-only">Store banner image file</label>
                    <input id="storeBanner" name="banner" type="file" tabindex="-1" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="bannerUploadHelp bannerUploadStatus" class="sr-only">
                    <button id="bannerUploadButton" type="button" aria-controls="storeBanner" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-[#1F6F5B] transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#1F6F5B]/40">
                        {{ $profile['banner_path'] ? 'Replace image' : 'Upload image' }}
                    </button>
                    @if($profile['banner_path'])
                        <button type="submit" name="image" value="banner" formnovalidate formaction="{{ route('seller.store.profile.photo.remove') }}" aria-label="Remove store banner" class="rounded-xl px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400/40">Remove image</button>
                    @endif
                </div>
                <p id="bannerUploadHelp" class="mt-2 text-[11px] text-gray-400">JPG, PNG or WEBP - max 5 MB.</p>
                <p id="bannerUploadStatus" role="status" class="mt-1 text-xs text-[#52635B]"></p>
                @error('banner')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- CONTACT NUMBER --}}
            <div>

                <label
                    for="phone"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    Business contact number
                    <span class="text-red-500">*</span>
                </label>


                <input
                    id="phone"
                    type="text"
                    name="phone"
                    value="{{ old('phone', $phone) }}"
                    required
                    class="mt-2 w-full
                           rounded-xl
                           border border-gray-200
                           bg-white
                           px-4 py-3
                           text-sm text-gray-700
                           outline-none
                           transition
                           focus:border-[#1F6F5B]
                           focus:ring-2
                           focus:ring-[#1F6F5B]/10"
                >


                <p
                    class="mt-2 text-[11px]
                           text-gray-400"
                >
                    Used as your seller contact number.
                </p>

            </div>



            <p class="text-xs text-gray-400">This is the store pickup contact. Your personal phone and login email are unchanged.</p>
            @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            {{-- DESCRIPTION --}}
            <div>

                <div
                    class="flex items-center
                           justify-between gap-4"
                >

                    <label
                        for="description"
                        class="text-sm font-medium
                               text-gray-700"
                    >
                        Shop description

                        <span
                            class="font-normal
                                   text-gray-400"
                        >
                            (optional)
                        </span>

                    </label>


                    <span
                        id="descriptionCount"
                        class="text-[11px]
                               text-gray-400"
                    >
                        0/500
                    </span>

                </div>


                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="500"
                    placeholder="Tell buyers what your shop is about..."
                    class="mt-2 w-full
                           resize-y
                           rounded-xl
                           border border-gray-200
                           bg-white
                           px-4 py-3
                           text-sm text-gray-700
                           outline-none
                           transition
                           placeholder:text-gray-400
                           focus:border-[#1F6F5B]
                           focus:ring-2
                           focus:ring-[#1F6F5B]/10"
                >{{ old('description', $description) }}</textarea>


                <p
                    class="mt-2 text-[11px]
                           text-gray-400"
                >
                    Shown on your shop profile when provided.
                </p>

            </div>



            @error('description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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
                Save store profile
            </button>

        </form>

    </section>





    {{-- =====================================================
        PICKUP ADDRESS
    ====================================================== --}}

    <section
        id="pickup-address"
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
                Pickup Address
            </p>


            <p class="mt-1 text-xs text-gray-400">
                Used as the pickup location for your seller account.
            </p>

        </div>


        <form
            action="{{ route('seller.store.address.update') }}"
            method="POST"
            class="space-y-5"
        >

            @csrf


            <p class="text-xs text-gray-400">Select your address from the live PSGC locations. Saving creates a separate pickup address when the original is personal or shared.</p>
            <p class="text-xs text-gray-500">Saved pickup address: {{ collect([$streetAddress, $barangay, $municipality, $province, $profile['region'], $profile['postal_code']])->filter()->implode(', ') ?: 'Not set' }}</p>
            <p id="profileAddressStatus" class="text-xs text-amber-700" role="status"></p>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="profileRegion" class="text-sm font-medium text-gray-700">Region</label>
                    <select id="profileRegion" name="region" required class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>
                    @error('region')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="profileProvince" class="text-sm font-medium text-gray-700">Province</label>
                    <select id="profileProvince" name="province"  class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>
                    @error('province')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="profileMunicipality" class="text-sm font-medium text-gray-700">Municipality / City</label>
                    <select id="profileMunicipality" name="municipality" required class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>
                    @error('municipality')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="profileBarangay" class="text-sm font-medium text-gray-700">Barangay</label>
                    <select id="profileBarangay" name="barangay" required class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"><option value="">Loading locations...</option></select>
                    @error('barangay')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="profilePostalCode" class="text-sm font-medium text-gray-700">Postal code</label>
                    <input id="profilePostalCode" name="postal_code" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ old('postal_code', $profile['postal_code']) }}" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('postal_code')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="pickupPhone" class="text-sm font-medium text-gray-700">Pickup contact number</label>
                    <input id="pickupPhone" name="pickup_phone" required value="{{ old('pickup_phone', $phone) }}" class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 outline-none focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10">
                    @error('pickup_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @error('address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            {{-- STREET ADDRESS --}}
            <div>

                <label
                    for="profileAddress"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    Street / House No. / Building
                    <span class="text-red-500">*</span>
                </label>


                <input
                    id="profileAddress"
                    type="text"
                    name="address"
                    value="{{ old('address', $streetAddress) }}"
                    required
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
                Save address
            </button>

        </form>

    </section>





    {{-- =====================================================
        BUSINESS AND VERIFICATION
    ====================================================== --}}

    <section
        id="business-verification"
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
                Business & Verification
            </p>


            <p class="mt-1 text-xs text-gray-400">
                Based on the information submitted during seller registration.
            </p>

        </div>



        <div
            class="grid gap-x-12 gap-y-6
                   sm:grid-cols-2"
        >


            {{-- BUSINESS NAME --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Business name
                </p>

                <p
                    class="mt-1 text-sm font-medium
                           text-gray-700"
                >
                    {{ $shopName ?: 'Not provided' }}
                </p>

            </div>



            {{-- BUSINESS CATEGORY --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Line of business
                </p>

                <p
                    class="mt-1 text-sm font-medium
                           text-gray-700"
                >
                    {{ $businessCategory ?: 'Not provided' }}
                </p>

            </div>



            {{-- SELLER NAME --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Account holder
                </p>

                <p
                    class="mt-1 text-sm font-medium
                           text-gray-700"
                >
                    {{ $sellerName ?: 'Not provided' }}
                </p>

            </div>



            {{-- EMAIL --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    E-mail
                </p>

                <p
                    class="mt-1 break-words
                           text-sm font-medium
                           text-gray-700"
                >
                    {{ $email ?: 'Not provided' }}
                </p>

            </div>



            {{-- VERIFICATION --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Verification
                </p>


                <div class="mt-1">

                    @if($isVerified)

                        <span
                            class="rounded-full
                                   bg-emerald-50
                                   px-2.5 py-1
                                   text-xs font-medium
                                   text-emerald-600"
                        >
                            Verified
                        </span>

                    @else

                        <span
                            class="rounded-full
                                   bg-amber-50
                                   px-2.5 py-1
                                   text-xs font-medium
                                   text-amber-700"
                        >
                            Pending
                        </span>

                    @endif

                </div>

            </div>



            {{-- STATUS --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Account status
                </p>

                <div class="mt-1">

                    <span
                        class="rounded-full
                               bg-[#EEF7F3]
                               px-2.5 py-1
                               text-xs font-medium
                               text-[#1F6F5B]"
                    >
                        {{ $accountStatus }}
                    </span>

                </div>

            </div>



            {{-- MEMBER SINCE --}}
            <div>

                <p class="text-[11px] text-gray-400">
                    Member since
                </p>

                <p
                    class="mt-1 text-sm font-medium
                           text-gray-700"
                >
                    {{ $memberSince ?: 'Not available' }}
                </p>

            </div>

        </div>



        <div
            class="mt-6 rounded-xl
                   bg-gray-50
                   px-4 py-4
                   text-xs leading-5
                   text-gray-500"
        >
            Store details are loaded from your saved shop record. The assigned category cannot be changed here. Account-holder and login e-mail details are read-only; personal profile management is a separate future feature.
        </div>

    </section>





    {{-- =====================================================
        SECURITY
    ====================================================== --}}

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


</div>

@endsection



@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {


    /*
    |--------------------------------------------------------------------------
    | SHOP DESCRIPTION CHARACTER COUNTER
    |--------------------------------------------------------------------------
    */

    const description =
        document.getElementById('description');

    const descriptionCount =
        document.getElementById('descriptionCount');


    function updateDescriptionCount() {

        if (!description || !descriptionCount) {
            return;
        }

        descriptionCount.textContent =
            `${description.value.length}/500`;

    }


    updateDescriptionCount();


    if (description) {

        description.addEventListener(
            'input',
            updateDescriptionCount
        );

    }



    /*
    |--------------------------------------------------------------------------
    | PROFILE PHOTO PREVIEW
    |--------------------------------------------------------------------------
    */

    function bindImageUpload(inputId, previewId, placeholderId, buttonId, statusId, maxMegabytes) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const placeholder = document.getElementById(placeholderId);
        const button = document.getElementById(buttonId);
        const status = document.getElementById(statusId);
        const savedSource = preview.getAttribute('src');
        let previewUrl;

        function releasePreview() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }
        function resetPreview() {
            releasePreview();
            if (savedSource) preview.src = savedSource;
            else preview.removeAttribute('src');
            preview.classList.toggle('hidden', !savedSource);
            placeholder.classList.toggle('hidden', Boolean(savedSource));
            button.textContent = savedSource ? 'Replace image' : 'Upload image';
        }
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', function () {
            const file = input.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > maxMegabytes * 1024 * 1024) {
                input.value = '';
                resetPreview();
                status.textContent = 'Choose a JPG, PNG or WEBP image up to ' + maxMegabytes + ' MB.';
                return;
            }
            releasePreview();
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            button.textContent = 'Replace image';
            status.textContent = 'Image selected. Save store profile to upload.';
        });
        window.addEventListener('pagehide', function (event) {
            if (!event.persisted) releasePreview();
        });
    }
    bindImageUpload('profile_photo', 'shopPhotoPreview', 'shopPhotoPlaceholder', 'logoUploadButton', 'logoUploadStatus', 2);
    bindImageUpload('storeBanner', 'storeBannerPreview', 'storeBannerPlaceholder', 'bannerUploadButton', 'bannerUploadStatus', 5);

    const fields = {
        region: document.getElementById('profileRegion'),
        province: document.getElementById('profileProvince'),
        municipality: document.getElementById('profileMunicipality'),
        barangay: document.getElementById('profileBarangay'),
    };
    const saved = @json($initialAddressValues);
    const addressStatus = document.getElementById('profileAddressStatus');
    const addressForm = fields.region.closest('form');
    const addressSubmit = addressForm.querySelector('button[type="submit"]');
    let addressVersion = 0;

    function populate(name, items, selected = '') {
        const select = fields[name];
        select.replaceChildren(new Option('Select ' + name, ''));
        items.forEach(item => select.add(new Option(item.name, item.code, false, item.code === selected || item.name === selected)));
        select.disabled = !items.length;
        if (name === 'province') select.required = items.length > 0;
    }
    async function locations(path) {
        const response = await fetch(@json(url('/api/psgc')) + '/' + path, {headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Location data is unavailable. Reload the page to try again.');
        return response.json();
    }
    async function loadAddress(from, initial = false) {
        const version = ++addressVersion;
        addressSubmit.disabled = true;
        addressStatus.textContent = 'Loading locations...';
        const order = ['region', 'province', 'municipality', 'barangay'];
        const start = order.indexOf(from);
        const selected = Object.fromEntries(order.map(name => [name, initial ? saved[name] : fields[name].value]));
        order.slice(start).forEach(name => populate(name, []));
        try {
            if (from === 'region') {
                const items = await locations('regions');
                if (version !== addressVersion) return;
                populate('region', items, selected.region);
            }
            if (start <= 1 && fields.region.value) {
                const items = await locations('regions/' + fields.region.value + '/provinces');
                if (version !== addressVersion) return;
                populate('province', items, initial ? selected.province : '');
            }
            if (start <= 2 && fields.region.value && (fields.province.disabled || fields.province.value)) {
                const path = fields.province.disabled ? 'regions/' + fields.region.value : 'provinces/' + fields.province.value;
                const items = await locations(path + '/cities-municipalities');
                if (version !== addressVersion) return;
                populate('municipality', items, initial ? selected.municipality : '');
            }
            if (fields.municipality.value) {
                const items = await locations('cities-municipalities/' + fields.municipality.value + '/barangays');
                if (version !== addressVersion) return;
                populate('barangay', items, initial ? selected.barangay : '');
            }
            addressStatus.textContent = '';
        } catch (error) {
            if (version !== addressVersion) return;
            addressStatus.textContent = error.message;
            return;
        }
        if (version === addressVersion) addressSubmit.disabled = false;
    }
    fields.region.addEventListener('change', () => loadAddress('province'));
    fields.province.addEventListener('change', () => loadAddress('municipality'));
    fields.municipality.addEventListener('change', () => loadAddress('barangay'));
    loadAddress('region', true);

    document.querySelectorAll('main form').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = 'true';
            if (event.submitter) event.submitter.setAttribute('aria-disabled', 'true');
        });
    });

});

</script>

@endpush
