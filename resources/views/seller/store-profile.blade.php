@extends('layouts.seller')

@section('title', 'Account Management')
@section('page-title', 'Account Management')
@section('page-subtitle', 'Your shop profile, pickup address and security')

@section('content')

@php
    $profile = session('seller_profile', []);

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


            {{-- PROFILE PHOTO --}}
            <div
                class="flex flex-col gap-5
                       sm:flex-row sm:items-start"
            >


                <div class="shrink-0">

                    @if($profilePhoto)

                        <img
                            id="shopPhotoPreview"
                            src="{{ asset('storage/' . $profilePhoto) }}"
                            alt="{{ $shopName ?: 'Shop photo' }}"
                            class="h-24 w-24
                                   rounded-full
                                   border border-gray-200
                                   object-cover shadow-sm"
                        >

                    @else

                        <div
                            id="shopPhotoPlaceholder"
                            class="flex h-24 w-24
                                   items-center justify-center
                                   rounded-full
                                   border border-gray-200
                                   bg-[#EEF7F3]
                                   text-[#1F6F5B]"
                        >

                            <i
                                data-lucide="store"
                                class="h-9 w-9"
                            ></i>

                        </div>


                        <img
                            id="shopPhotoPreview"
                            src=""
                            alt="Shop photo preview"
                            class="hidden h-24 w-24
                                   rounded-full
                                   border border-gray-200
                                   object-cover shadow-sm"
                        >

                    @endif

                </div>



                <div class="min-w-0 flex-1">

                    <p
                        class="text-sm font-semibold
                               text-gray-800"
                    >
                        {{ $shopName ?: 'Business name not available' }}
                    </p>


                    <p
                        class="mt-1 text-xs
                               leading-5 text-gray-400"
                    >
                        Add a photo that represents your shop.
                    </p>


                    <div
                        class="mt-3 flex flex-wrap
                               items-center gap-3"
                    >

                        <label
                            for="profile_photo"
                            class="cursor-pointer
                                   rounded-xl
                                   border border-gray-200
                                   bg-white
                                   px-4 py-2
                                   text-xs font-semibold
                                   text-[#1F6F5B]
                                   transition
                                   hover:bg-gray-50"
                        >
                            Change photo
                        </label>


                        <input
                            id="profile_photo"
                            name="profile_photo"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="hidden"
                        >


                        @if($profilePhoto)

                            <button
                                type="submit"
                                formaction="{{ route('seller.store.profile.photo.remove') }}"
                                class="text-xs font-medium
                                       text-red-500
                                       hover:text-red-600"
                            >
                                Remove photo
                            </button>

                        @endif

                    </div>


                    <p
                        id="photoFileName"
                        class="mt-2 text-[11px]
                               text-gray-400"
                    >
                        JPG, PNG or WEBP · max 2 MB.
                    </p>

                </div>

            </div>



            {{-- CONTACT NUMBER --}}
            <div>

                <label
                    for="phone"
                    class="text-sm font-medium
                           text-gray-700"
                >
                    Contact number
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
                Save profile
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


            <div
                class="grid grid-cols-1
                       gap-5 md:grid-cols-3"
            >


                {{-- PROVINCE --}}
                <div>

                    <label
                        for="profileProvince"
                        class="text-sm font-medium
                               text-gray-700"
                    >
                        Province
                        <span class="text-red-500">*</span>
                    </label>


                    <select
                        id="profileProvince"
                        name="province"
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

                        @if($province)

                            <option
                                value="{{ $province }}"
                                selected
                            >
                                {{ $province }}
                            </option>

                        @else

                            <option value="">
                                Loading provinces...
                            </option>

                        @endif

                    </select>

                </div>



                {{-- MUNICIPALITY --}}
                <div>

                    <label
                        for="profileMunicipality"
                        class="text-sm font-medium
                               text-gray-700"
                    >
                        Municipality / City
                        <span class="text-red-500">*</span>
                    </label>


                    <select
                        id="profileMunicipality"
                        name="municipality"
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
                               focus:ring-[#1F6F5B]/10
                               disabled:bg-gray-50"
                    >

                        @if($municipality)

                            <option
                                value="{{ $municipality }}"
                                selected
                            >
                                {{ $municipality }}
                            </option>

                        @else

                            <option value="">
                                Select province first
                            </option>

                        @endif

                    </select>

                </div>



                {{-- BARANGAY --}}
                <div>

                    <label
                        for="profileBarangay"
                        class="text-sm font-medium
                               text-gray-700"
                    >
                        Barangay
                        <span class="text-red-500">*</span>
                    </label>


                    <select
                        id="profileBarangay"
                        name="barangay"
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
                               focus:ring-[#1F6F5B]/10
                               disabled:bg-gray-50"
                    >

                        @if($barangay)

                            <option
                                value="{{ $barangay }}"
                                selected
                            >
                                {{ $barangay }}
                            </option>

                        @else

                            <option value="">
                                Select municipality/city first
                            </option>

                        @endif

                    </select>

                </div>

            </div>



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
            Business name, line of business, account holder,
            e-mail and verification information are based on
            the details already submitted during registration.
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

    const photoInput =
        document.getElementById('profile_photo');

    const photoPreview =
        document.getElementById('shopPhotoPreview');

    const photoPlaceholder =
        document.getElementById('shopPhotoPlaceholder');

    const photoFileName =
        document.getElementById('photoFileName');


    if (photoInput) {

        photoInput.addEventListener(
            'change',
            function () {

                const file =
                    this.files
                        ? this.files[0]
                        : null;


                if (!file) {
                    return;
                }


                if (photoFileName) {

                    photoFileName.textContent =
                        file.name;

                }


                if (photoPreview) {

                    photoPreview.src =
                        URL.createObjectURL(file);

                    photoPreview.classList.remove(
                        'hidden'
                    );

                }


                if (photoPlaceholder) {

                    photoPlaceholder.classList.add(
                        'hidden'
                    );

                }

            }
        );

    }



    /*
    |--------------------------------------------------------------------------
    | PICKUP ADDRESS
    |--------------------------------------------------------------------------
    |
    | Uses the PSGC proxy routes already existing in your routes/web.php.
    |
    */

    const provinceSelect =
        document.getElementById('profileProvince');

    const municipalitySelect =
        document.getElementById(
            'profileMunicipality'
        );

    const barangaySelect =
        document.getElementById(
            'profileBarangay'
        );


    const currentProvince =
        @json(old('province', $province));

    const currentMunicipality =
        @json(old('municipality', $municipality));

    const currentBarangay =
        @json(old('barangay', $barangay));


    const PSGC_API_BASE =
        '/api/psgc';



    function populateSelect(
        select,
        placeholder,
        items,
        selectedValue = ''
    ) {

        if (!select) {
            return;
        }


        select.innerHTML = '';


        const placeholderOption =
            document.createElement('option');

        placeholderOption.value = '';

        placeholderOption.textContent =
            placeholder;

        select.appendChild(
            placeholderOption
        );


        items.forEach(function (item) {

            const option =
                document.createElement('option');

            option.value =
                item.name;

            option.textContent =
                item.name;

            option.dataset.code =
                item.code;


            if (
                selectedValue
                && item.name === selectedValue
            ) {

                option.selected =
                    true;

            }


            select.appendChild(
                option
            );

        });

    }



    function setSingleOption(
        select,
        value,
        fallbackText
    ) {

        if (!select) {
            return;
        }


        select.innerHTML = '';


        const option =
            document.createElement('option');


        option.value =
            value || '';

        option.textContent =
            value || fallbackText;


        select.appendChild(
            option
        );

    }



    async function fetchJson(url) {

        const response =
            await fetch(url);


        if (!response.ok) {

            throw new Error(
                'Unable to load location data.'
            );

        }


        return response.json();

    }



    async function loadBarangays(
        municipalityCode,
        selectedBarangay = ''
    ) {

        if (
            !barangaySelect
            || !municipalityCode
        ) {
            return;
        }


        barangaySelect.disabled =
            true;

        setSingleOption(
            barangaySelect,
            '',
            'Loading barangays...'
        );


        try {

            const barangays =
                await fetchJson(
                    `${PSGC_API_BASE}/cities-municipalities/${encodeURIComponent(municipalityCode)}/barangays`
                );


            populateSelect(
                barangaySelect,
                'Select barangay',
                barangays,
                selectedBarangay
            );


            barangaySelect.disabled =
                false;

        } catch (error) {

            console.error(error);


            setSingleOption(
                barangaySelect,
                selectedBarangay,
                'Unable to load barangays'
            );


            barangaySelect.disabled =
                false;

        }

    }



    async function loadMunicipalities(
        provinceCode,
        selectedMunicipality = '',
        selectedBarangay = ''
    ) {

        if (
            !municipalitySelect
            || !provinceCode
        ) {
            return;
        }


        municipalitySelect.disabled =
            true;


        setSingleOption(
            municipalitySelect,
            '',
            'Loading municipalities/cities...'
        );


        if (barangaySelect) {

            barangaySelect.disabled =
                true;

            setSingleOption(
                barangaySelect,
                '',
                'Select municipality/city first'
            );

        }


        try {

            const municipalities =
                await fetchJson(
                    `${PSGC_API_BASE}/provinces/${encodeURIComponent(provinceCode)}/cities-municipalities`
                );


            populateSelect(
                municipalitySelect,
                'Select municipality/city',
                municipalities,
                selectedMunicipality
            );


            municipalitySelect.disabled =
                false;


            const selectedOption =
                Array.from(
                    municipalitySelect.options
                ).find(function (option) {

                    return option.value
                        === selectedMunicipality;

                });


            if (
                selectedOption
                && selectedOption.dataset.code
            ) {

                await loadBarangays(
                    selectedOption.dataset.code,
                    selectedBarangay
                );

            }

        } catch (error) {

            console.error(error);


            setSingleOption(
                municipalitySelect,
                selectedMunicipality,
                'Unable to load municipalities/cities'
            );


            municipalitySelect.disabled =
                false;


            if (barangaySelect) {

                setSingleOption(
                    barangaySelect,
                    selectedBarangay,
                    'Select municipality/city first'
                );


                barangaySelect.disabled =
                    false;

            }

        }

    }



    async function loadProvinces() {

        if (!provinceSelect) {
            return;
        }


        try {

            const provinces =
                await fetchJson(
                    `${PSGC_API_BASE}/provinces`
                );


            populateSelect(
                provinceSelect,
                'Select province',
                provinces,
                currentProvince
            );


            const selectedOption =
                Array.from(
                    provinceSelect.options
                ).find(function (option) {

                    return option.value
                        === currentProvince;

                });


            if (
                selectedOption
                && selectedOption.dataset.code
            ) {

                await loadMunicipalities(
                    selectedOption.dataset.code,
                    currentMunicipality,
                    currentBarangay
                );

            } else {

                if (municipalitySelect) {

                    municipalitySelect.disabled =
                        true;

                }


                if (barangaySelect) {

                    barangaySelect.disabled =
                        true;

                }

            }

        } catch (error) {

            console.error(error);

        }

    }



    if (
        provinceSelect
        && municipalitySelect
        && barangaySelect
    ) {


        provinceSelect.addEventListener(
            'change',
            async function () {

                const selectedOption =
                    this.options[
                        this.selectedIndex
                    ];


                const provinceCode =
                    selectedOption
                        ? selectedOption.dataset.code
                        : null;


                if (!provinceCode) {

                    setSingleOption(
                        municipalitySelect,
                        '',
                        'Select province first'
                    );

                    municipalitySelect.disabled =
                        true;


                    setSingleOption(
                        barangaySelect,
                        '',
                        'Select municipality/city first'
                    );

                    barangaySelect.disabled =
                        true;

                    return;
                }


                await loadMunicipalities(
                    provinceCode
                );

            }
        );



        municipalitySelect.addEventListener(
            'change',
            async function () {

                const selectedOption =
                    this.options[
                        this.selectedIndex
                    ];


                const municipalityCode =
                    selectedOption
                        ? selectedOption.dataset.code
                        : null;


                if (!municipalityCode) {

                    setSingleOption(
                        barangaySelect,
                        '',
                        'Select municipality/city first'
                    );

                    barangaySelect.disabled =
                        true;

                    return;
                }


                await loadBarangays(
                    municipalityCode
                );

            }
        );


        loadProvinces();

    }

});

</script>

@endpush