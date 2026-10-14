<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sign Up - SUKI SHOP</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .buyer-policy-dialog[open] {
            display: flex;
        }
    </style>
</head>

<body class="min-h-screen bg-[#F8FAF8] font-[Poppins] text-[#1F2937]">

    <div class="min-h-screen grid lg:grid-cols-[45%_55%]">

        <!-- ================================================= -->
        <!-- LEFT SIDE - SUKI SHOP BRANDING -->
        <!-- ================================================= -->

        <section class="hidden lg:flex items-center justify-center bg-[#EEF8F3] px-10">

            <div class="text-center max-w-lg">

                <!-- Logo -->
                <div class="flex justify-center">

                    <div class="bg-white rounded-[28px] px-8 py-6 shadow-sm border border-[#DCEDE6]">

                        <img
                            src="{{ asset('images/suki-logo.png') }}"
                            alt="SUKI SHOP"
                            class="w-[210px] xl:w-[240px] h-auto object-contain"
                        >

                    </div>

                </div>


                <!-- Tagline -->
                <h1 class="mt-8 text-3xl xl:text-4xl font-semibold text-[#173F35] leading-tight">

                    Your everyday

                    <span class="text-[#1F6F5B]">
                        marketplace.
                    </span>

                </h1>


                <!-- Description -->
                <p class="mt-4 text-sm xl:text-base text-gray-500 leading-relaxed max-w-md mx-auto">

                    Discover products, find great deals, and shop
                    from sellers you can trust — all in one place.

                </p>


                <!-- Features -->
                <div class="flex justify-center gap-3 mt-8">

                    <!-- Great Deals -->
                    <div class="flex items-center gap-2 bg-white border border-[#DCEDE6] rounded-full px-4 py-2">

                        <i
                            data-lucide="tag"
                            class="w-4 h-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Great Deals
                        </span>

                    </div>


                    <!-- Easy Delivery -->
                    <div class="flex items-center gap-2 bg-white border border-[#DCEDE6] rounded-full px-4 py-2">

                        <i
                            data-lucide="truck"
                            class="w-4 h-4 text-[#1F6F5B]"
                        ></i>

                        <span class="text-xs font-medium text-gray-600">
                            Easy Delivery
                        </span>

                    </div>

                </div>


                <!-- Trust Message -->
                <div class="flex justify-center items-center gap-2 mt-10 text-xs text-gray-400">

                    <i
                        data-lucide="shield-check"
                        class="w-4 h-4 text-[#1F6F5B]"
                    ></i>

                    <span>
                        A marketplace made for everyday shopping
                    </span>

                </div>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- RIGHT SIDE - SIGN UP -->
        <!-- ================================================= -->

        <section class="flex items-center justify-center bg-[#F8FAF8] px-6 sm:px-10 lg:px-16 py-10">

            <div class="w-full max-w-[440px]">

                <!-- Mobile Logo -->
                <div class="lg:hidden text-center mb-7">

                    <img
                        src="{{ asset('images/suki-logo.png') }}"
                        alt="SUKI SHOP"
                        class="w-[150px] mx-auto h-auto"
                    >

                </div>


                <!-- Header -->
                <div class="mb-7">

                    <p class="text-sm font-semibold tracking-wide text-[#1F6F5B] mb-2">
                        JOIN SUKI SHOP
                    </p>

                    <h2 class="text-2xl sm:text-3xl font-semibold text-gray-900">
                        Create your account
                    </h2>

                    <p class="text-sm text-gray-500 mt-2">
                        Sign up and start shopping with SUKI SHOP.
                    </p>

                </div>


                <!-- Error Messages -->
                @if ($errors->any())

                    <div id="registrationErrorSummary" class="mb-5 rounded-xl bg-red-50 border border-red-100 px-4 py-3">
                        <p class="text-sm text-red-600" role="alert">
                            Please correct the highlighted fields below.
                        </p>
                    </div>

                @endif


                <!-- ================================================= -->
                <!-- REGISTER FORM -->
                <!-- ================================================= -->

                <form
                    action="{{ route('register.submit') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    class="space-y-4"
                    novalidate
                >

                    @csrf


                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF8F3] text-xs font-bold text-[#1F6F5B]">
                            01
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#173F35]">Personal Information</h3>
                            <p class="mt-1 text-xs text-gray-500">Tell us about yourself.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <!-- Last Name -->
                        <div>
                            <label
                                for="last_name"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Last Name <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                placeholder="Last name"
                                minlength="2"
                                pattern="[\p{L}]+(?: [\p{L}]+)*"
                                title="Use 2 to 50 letters, with spaces between name parts."
                                aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}"
                                class="w-full px-4 py-3 rounded-lg border {{ $errors->has('last_name') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
                            <p data-field-error="last_name" class="mt-1 text-xs text-red-600 {{ $errors->has('last_name') ? '' : 'hidden' }}" role="alert">{{ $errors->first('last_name') }}</p>
                        </div>


                        <!-- First Name -->
                        <div>
                            <label
                                for="first_name"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                First Name <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                value="{{ old('first_name') }}"
                                placeholder="First name"
                                minlength="2"
                                pattern="[\p{L}]+(?: [\p{L}]+)*"
                                title="Use 2 to 50 letters, with spaces between name parts."
                                aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}"
                                class="w-full px-4 py-3 rounded-lg border {{ $errors->has('first_name') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
                            <p data-field-error="first_name" class="mt-1 text-xs text-red-600 {{ $errors->has('first_name') ? '' : 'hidden' }}" role="alert">{{ $errors->first('first_name') }}</p>
                        </div>


                        <!-- Middle Initial -->
                        <div>
                            <label
                                for="middle_initial"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Middle Initial
                            </label>

                            <input
                                type="text"
                                id="middle_initial"
                                name="middle_initial"
                                value="{{ old('middle_initial') }}"
                                maxlength="10"
                                placeholder="e.g. M."
                                aria-invalid="{{ $errors->has('middle_initial') ? 'true' : 'false' }}"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            <p data-field-error="middle_initial" class="mt-1 text-xs text-red-600 {{ $errors->has('middle_initial') ? '' : 'hidden' }}" role="alert">{{ $errors->first('middle_initial') }}</p>
                        </div>


                        <!-- Sex -->
                        <div>
                            <label
                                for="sex"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Sex <span class="text-red-500">*</span>
                            </label>

                            <select
                                id="sex"
                                name="sex"
                                aria-invalid="{{ $errors->has('sex') ? 'true' : 'false' }}"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
                                <option value="">Select sex</option>
                                <option value="Male" @selected(old('sex') === 'Male')>Male</option>
                                <option value="Female" @selected(old('sex') === 'Female')>Female</option>
                            </select>
                            <p data-field-error="sex" class="mt-1 text-xs text-red-600 {{ $errors->has('sex') ? '' : 'hidden' }}" role="alert">{{ $errors->first('sex') }}</p>
                        </div>


                        <!-- Birthday -->
                        <div>
                            <label
                                for="birthday"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Birthday <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="date"
                                id="birthday"
                                name="birthday"
                                value="{{ old('birthday') }}"
                                max="{{ now('Asia/Manila')->subYears(18)->format('Y-m-d') }}"
                                aria-invalid="{{ $errors->has('birthday') ? 'true' : 'false' }}"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
                            <p data-field-error="birthday" class="mt-1 text-xs text-red-600 {{ $errors->has('birthday') ? '' : 'hidden' }}" role="alert">{{ $errors->first('birthday') }}</p>
                        </div>


                        <!-- Age -->
                        <div>
                            <label
                                for="age"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Age
                            </label>

                            <input
                                type="number"
                                id="age"
                                name="age"
                                placeholder="Auto calculated"
                                readonly
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-500 outline-none cursor-not-allowed"
                            >
                        </div>

                    </div>


                    <div class="flex items-start gap-3 border-t border-gray-100 pt-5">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF8F3] text-xs font-bold text-[#1F6F5B]">
                            02
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#173F35]">Contact Information</h3>
                            <p class="mt-1 text-xs text-gray-500">We’ll use these details for important account updates.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <!-- Phone Number -->
                        <div>
                            <label
                                for="phone"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Contact No. <span class="text-red-500">*</span>
                            </label>

                            <div class="relative">
                                <i
                                    data-lucide="phone"
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                ></i>

                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    placeholder="Enter contact number"
                                    inputmode="tel"
                                    pattern="(?:09[0-9]{9}|\+639[0-9]{9})"
                                    maxlength="13"
                                    aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
                                    class="w-full pl-11 pr-4 py-3 rounded-lg border {{ $errors->has('phone') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                            </div>
                            <p data-field-error="phone" class="mt-1 text-xs text-red-600 {{ $errors->has('phone') ? '' : 'hidden' }}" role="alert">{{ $errors->first('phone') }}</p>
                        </div>


                        <!-- Email -->
                        <div>
                            <label
                                for="email"
                                class="block text-sm font-medium text-gray-700 mb-2"
                            >
                                Email <span class="text-red-500">*</span>
                            </label>

                            <div class="relative">
                                <i
                                    data-lucide="mail"
                                    class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                                ></i>

                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="Enter email address"
                                    maxlength="255"
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                    class="w-full pl-11 pr-4 py-3 rounded-lg border {{ $errors->has('email') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                            </div>
                            <p data-field-error="email" class="mt-1 text-xs text-red-600 {{ $errors->has('email') ? '' : 'hidden' }}" role="alert">{{ $errors->first('email') }}</p>
                        </div>

                    </div>


                    <!-- Address -->
                    <div class="pt-2">
                        <div class="mb-4 flex items-start gap-3 border-t border-gray-100 pt-5">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF8F3] text-xs font-bold text-[#1F6F5B]">
                                03
                            </span>
                            <div>
                                <h3 class="text-sm font-semibold text-[#173F35]">Address Information</h3>
                                <p class="mt-1 text-xs text-gray-500">Select your location and enter your complete street address.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">

                            <!-- Region -->
                            <div>
                                <label
                                    for="regionDropdown"
                                    id="regionDropdownLabel"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Region <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">
                                    <button
                                        type="button"
                                        id="regionDropdown"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                        aria-controls="regionOptions"
                                        aria-labelledby="regionDropdownLabel"
                                        aria-required="true"
                                        aria-invalid="{{ $errors->has('region') ? 'true' : 'false' }}"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                        disabled
                                    >
                                        <span id="regionDropdownValue" class="text-gray-500">Loading regions...</span>
                                        <i data-lucide="chevron-down" class="ml-3 h-4 w-4 shrink-0 text-gray-400"></i>
                                    </button>

                                    <div
                                        id="regionOptions"
                                        role="listbox"
                                        aria-labelledby="regionDropdownLabel"
                                        class="absolute left-0 top-full z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    ></div>

                                    <p id="regionError" data-field-error="region" class="mt-1 text-xs text-red-600 {{ $errors->has('region') ? '' : 'hidden' }}" role="alert">{{ $errors->first('region') }}</p>

                                    <select
                                        id="region"
                                        name="region"
                                        class="sr-only"
                                        tabindex="-1"
                                        aria-hidden="true"
                                        required
                                    >
                                        <option value="">Loading regions...</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Province -->
                            <div>
                                <label
                                    for="provinceDropdown"
                                    id="provinceDropdownLabel"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Province <span id="provinceRequiredMark" class="text-red-500">*</span>
                                </label>

                                <div class="relative">
                                    <button
                                        type="button"
                                        id="provinceDropdown"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                        aria-controls="provinceOptions"
                                        aria-labelledby="provinceDropdownLabel"
                                        aria-required="true"
                                        aria-invalid="{{ $errors->has('province') ? 'true' : 'false' }}"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                        disabled
                                    >
                                        <span id="provinceDropdownValue" class="text-gray-500">Select region first</span>
                                        <i data-lucide="chevron-down" class="ml-3 h-4 w-4 shrink-0 text-gray-400"></i>
                                    </button>

                                    <div
                                        id="provinceOptions"
                                        role="listbox"
                                        aria-labelledby="provinceDropdownLabel"
                                        class="absolute left-0 top-full z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    ></div>

                                    <p id="provinceError" data-field-error="province" class="mt-1 text-xs text-red-600 {{ $errors->has('province') ? '' : 'hidden' }}" role="alert">{{ $errors->first('province') }}</p>

                                    <select
                                        id="province"
                                        name="province"
                                        class="sr-only"
                                        tabindex="-1"
                                        aria-hidden="true"
                                        required
                                    >
                                        <option value="">Loading provinces...</option>
                                    </select>
                                </div>
                            </div>


                            <!-- Municipality / City -->
                            <div>
                                <label
                                    for="municipalityDropdown"
                                    id="municipalityDropdownLabel"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Municipality / City <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">
                                    <button
                                        type="button"
                                        id="municipalityDropdown"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                        aria-controls="municipalityOptions"
                                        aria-labelledby="municipalityDropdownLabel"
                                        aria-required="true"
                                        aria-invalid="{{ $errors->has('municipality') ? 'true' : 'false' }}"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                        disabled
                                    >
                                        <span id="municipalityDropdownValue" class="text-gray-500">Select province first</span>
                                        <i data-lucide="chevron-down" class="ml-3 h-4 w-4 shrink-0 text-gray-400"></i>
                                    </button>

                                    <div
                                        id="municipalityOptions"
                                        role="listbox"
                                        aria-labelledby="municipalityDropdownLabel"
                                        class="absolute left-0 top-full z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    ></div>

                                    <p id="municipalityError" data-field-error="municipality" class="mt-1 text-xs text-red-600 {{ $errors->has('municipality') ? '' : 'hidden' }}" role="alert">{{ $errors->first('municipality') }}</p>

                                    <select
                                        id="municipality"
                                        name="municipality"
                                        class="sr-only"
                                        tabindex="-1"
                                        aria-hidden="true"
                                        required
                                        disabled
                                    >
                                        <option value="">Select province first</option>
                                    </select>
                                </div>
                            </div>


                            <!-- Barangay -->
                            <div>
                                <label
                                    for="barangayDropdown"
                                    id="barangayDropdownLabel"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Barangay <span class="text-red-500">*</span>
                                </label>

                                <div class="relative">
                                    <button
                                        type="button"
                                        id="barangayDropdown"
                                        role="combobox"
                                        aria-haspopup="listbox"
                                        aria-expanded="false"
                                        aria-controls="barangayOptions"
                                        aria-labelledby="barangayDropdownLabel"
                                        aria-required="true"
                                        aria-invalid="{{ $errors->has('barangay') ? 'true' : 'false' }}"
                                        class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-3 text-left text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                        disabled
                                    >
                                        <span id="barangayDropdownValue" class="text-gray-500">Select municipality / city first</span>
                                        <i data-lucide="chevron-down" class="ml-3 h-4 w-4 shrink-0 text-gray-400"></i>
                                    </button>

                                    <div
                                        id="barangayOptions"
                                        role="listbox"
                                        aria-labelledby="barangayDropdownLabel"
                                        class="absolute left-0 top-full z-50 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
                                    ></div>

                                    <p id="barangayError" data-field-error="barangay" class="mt-1 text-xs text-red-600 {{ $errors->has('barangay') ? '' : 'hidden' }}" role="alert">{{ $errors->first('barangay') }}</p>

                                    <select
                                        id="barangay"
                                        name="barangay"
                                        class="sr-only"
                                        tabindex="-1"
                                        aria-hidden="true"
                                        required
                                        disabled
                                    >
                                        <option value="">Select municipality / city first</option>
                                    </select>
                                </div>
                            </div>


                            <!-- Street Address -->
                            <div>
                                <label
                                    for="street_address"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Street / House No. <span class="text-red-500">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="street_address"
                                    name="street_address"
                                    value="{{ old('street_address') }}"
                                    placeholder="House no., street, subdivision"
                                    maxlength="255"
                                    aria-invalid="{{ $errors->has('street_address') ? 'true' : 'false' }}"
                                    class="w-full px-4 py-3 rounded-lg border {{ $errors->has('street_address') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                                <p data-field-error="street_address" class="mt-1 text-xs text-red-600 {{ $errors->has('street_address') ? '' : 'hidden' }}" role="alert">{{ $errors->first('street_address') }}</p>
                            </div>

                        </div>
                    </div>


                    <div class="flex items-start gap-3 border-t border-gray-100 pt-5">
                        <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EEF8F3] text-xs font-bold text-[#1F6F5B]">
                            04
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#173F35]">Account Information</h3>
                            <p class="mt-1 text-xs text-gray-500">Upload a valid ID and set up your sign-in details.</p>
                        </div>
                    </div>

                    <div>
                        <label
                            for="government_id"
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Upload Valid ID <span class="text-red-500">*</span>
                        </label>

                        <div class="rounded-lg border {{ $errors->has('government_id') ? 'border-red-500' : 'border-gray-300' }} bg-white p-3">
                            <input
                                type="file"
                                id="government_id"
                                name="government_id"
                                accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                aria-invalid="{{ $errors->has('government_id') ? 'true' : 'false' }}"
                                aria-describedby="governmentIdPreviewName"
                                class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#EEF8F3] file:px-4 file:py-3 file:text-sm file:font-semibold file:text-[#1F6F5B] hover:file:bg-[#DFF2E9]"
                                required
                            >

                            <div
                                id="governmentIdPreviewContainer"
                                class="mt-3 hidden items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 p-2"
                            >
                                <img
                                    id="governmentIdPreview"
                                    alt="Selected government ID preview"
                                    class="hidden h-20 w-20 rounded-md border border-gray-200 bg-white object-contain"
                                >
                                <span
                                    id="governmentIdPreviewName"
                                    class="min-w-0 flex-1 break-all text-xs font-medium text-gray-600"
                                ></span>
                                <button
                                    type="button"
                                    id="removeGovernmentId"
                                    class="shrink-0 rounded-full bg-red-600 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    aria-label="Remove selected government ID"
                                >
                                    X
                                </button>
                            </div>
                        </div>

                        <p data-field-error="government_id" class="mt-1 text-xs text-red-600 {{ $errors->has('government_id') ? '' : 'hidden' }}" role="alert">{{ $errors->first('government_id') }}</p>
                        <p class="mt-1.5 text-xs text-gray-400">
                            JPG, JPEG, PNG, or PDF. Maximum file size: 2 MB.
                        </p>
                    </div>


                    <!-- Password -->
                    <div>

                        <label
                            for="password"
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Password
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="lock"
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                            ></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                minlength="8"
                                maxlength="255"
                                pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\p{P}\p{S}]).{8,255}"
                                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                class="w-full pl-11 pr-12 py-3 rounded-lg border {{ $errors->has('password') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                            <button
                                type="button"
                                onclick="togglePassword('password', 'passwordIcon')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B] transition"
                                aria-label="Show password"
                            >

                                <i
                                    id="passwordIcon"
                                    data-lucide="eye"
                                    class="w-5 h-5"
                                ></i>

                            </button>

                        </div>
                        <p data-field-error="password" class="mt-1 text-xs text-red-600 {{ $errors->has('password') ? '' : 'hidden' }}" role="alert">{{ $errors->first('password') }}</p>

                    </div>


                    <!-- Confirm Password -->
                    <div>

                        <label
                            for="password_confirmation"
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Confirm Password
                        </label>

                        <div class="relative">

                            <i
                                data-lucide="lock-keyhole"
                                class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                            ></i>

                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                placeholder="Confirm your password"
                                aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                                class="w-full pl-11 pr-12 py-3 rounded-lg border {{ $errors->has('password_confirmation') ? 'border-red-500' : 'border-gray-300' }} bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >

                            <button
                                type="button"
                                onclick="togglePassword('password_confirmation', 'confirmPasswordIcon')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#1F6F5B] transition"
                                aria-label="Show password"
                            >

                                <i
                                    id="confirmPasswordIcon"
                                    data-lucide="eye"
                                    class="w-5 h-5"
                                ></i>

                            </button>

                        </div>
                        <p data-field-error="password_confirmation" class="mt-1 text-xs text-red-600 {{ $errors->has('password_confirmation') ? '' : 'hidden' }}" role="alert">{{ $errors->first('password_confirmation') }}</p>

                    </div>


                    <!-- Terms -->
                    <div class="flex items-start gap-2 pt-1">

                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            value="1"
                            aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"
                            class="mt-1 w-4 h-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                            required
                        >

                        <div class="text-xs text-gray-500 leading-relaxed">
                            <label for="terms">I agree to SUKI SHOP's</label>
                            <button
                                type="button"
                                data-open-policy="termsDialog"
                                class="font-medium text-[#1F6F5B] hover:underline"
                            >Terms &amp; Conditions</button>
                            and
                            <button
                                type="button"
                                data-open-policy="privacyDialog"
                                class="font-medium text-[#1F6F5B] hover:underline"
                            >Privacy Policy</button>.
                        </div>

                    </div>
                    <p data-field-error="terms" class="mt-1 text-xs text-red-600 {{ $errors->has('terms') ? '' : 'hidden' }}" role="alert">{{ $errors->first('terms') }}</p>


                    <!-- Create Account -->
                    <button
                        type="submit"
                        class="w-full py-3.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                    >
                        CREATE ACCOUNT
                    </button>

                </form>

                <dialog
                    id="termsDialog"
                    aria-labelledby="termsDialogTitle"
                    class="buyer-policy-dialog m-auto h-[90dvh] max-h-[calc(100dvh-2rem)] w-[min(720px,calc(100%-2rem))] flex-col overflow-hidden rounded-2xl border border-[#DDE6E1] bg-white p-0 text-gray-700 shadow-2xl backdrop:bg-[#102C25]/55 backdrop:backdrop-blur-sm"
                >
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 sm:px-7">
                        <div>
                            <h2 id="termsDialogTitle" class="text-lg font-semibold text-[#173F35]">SUKI SHOP Terms and Conditions</h2>
                            <p class="mt-1 text-xs text-gray-500">Effective Date: October 2026</p>
                        </div>
                        <button
                            type="button"
                            data-close-policy
                            class="rounded-full px-3 py-1.5 text-sm font-semibold text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                            aria-label="Close Terms and Conditions"
                        >X</button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-5 text-sm leading-6 sm:px-7">
                        <p>Welcome to SUKI SHOP. These Terms and Conditions govern your use of the SUKI SHOP platform, including browsing, account registration, purchasing products, and interacting with sellers.</p>
                        <p>By creating an account or using SUKI SHOP, you agree to comply with these Terms and Conditions and our Privacy Policy.</p>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">1. Account Registration</h3>
                            <p>Users must provide accurate, complete, and updated information when creating an account.</p>
                            <p>Users are responsible for maintaining the confidentiality of their account credentials and are responsible for all activities performed through their account.</p>
                            <p>Users must not:</p>
                            <ul class="list-disc space-y-1 pl-5">
                                <li>Create false accounts.</li>
                                <li>Use another person's identity.</li>
                                <li>Provide misleading information.</li>
                                <li>Use the platform for unauthorized activities.</li>
                            </ul>
                            <p>SUKI SHOP reserves the right to suspend or terminate accounts that violate these Terms.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">2. User Eligibility</h3>
                            <p>Users must provide accurate personal information and meet the required eligibility requirements to use SUKI SHOP services.</p>
                            <p>Users are responsible for ensuring that their account information remains correct and updated.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">3. Buying Products</h3>
                            <p>Buyers may browse products, place orders, and communicate with sellers through the platform.</p>
                            <p>Before placing an order, buyers should review:</p>
                            <ul class="list-disc space-y-1 pl-5">
                                <li>Product descriptions.</li>
                                <li>Product prices.</li>
                                <li>Available quantities.</li>
                                <li>Seller information.</li>
                            </ul>
                            <p>Orders submitted through SUKI SHOP are considered a request to purchase and may be subject to seller confirmation and platform processing.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">4. Payments</h3>
                            <p>Users must complete payments using available payment methods provided by SUKI SHOP.</p>
                            <p>Users must not:</p>
                            <ul class="list-disc space-y-1 pl-5">
                                <li>Use fraudulent payment methods.</li>
                                <li>Perform unauthorized transactions.</li>
                                <li>Attempt to bypass payment procedures.</li>
                            </ul>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">5. Seller Responsibilities</h3>
                            <p>Sellers are responsible for providing accurate product information, uploading correct product images, maintaining product quality, processing orders properly, and providing truthful business information.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">6. Product Information</h3>
                            <p>SUKI SHOP requires sellers to provide accurate product information. However, buyers understand that product appearance, availability, and descriptions may vary depending on seller-provided information.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">7. Orders and Delivery</h3>
                            <p>Users are responsible for providing correct delivery information. SUKI SHOP is not responsible for delays caused by incorrect information provided by users or circumstances beyond reasonable control.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">8. Returns and Disputes</h3>
                            <p>Users may request assistance for order-related concerns according to SUKI SHOP policies.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">9. Prohibited Activities</h3>
                            <p>Users must not use SUKI SHOP for illegal activities, upload harmful or unauthorized content, attempt to access another user's account, abuse platform features, submit fraudulent transactions, or manipulate reviews, ratings, or platform systems.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">10. Privacy and Data Protection</h3>
                            <p>SUKI SHOP collects and processes user information to provide platform services. User information may be used for account management, order processing, customer support, and security and fraud prevention.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">11. Account Suspension or Termination</h3>
                            <p>SUKI SHOP may suspend or terminate accounts that violate these Terms.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">12. Changes to Terms</h3>
                            <p>SUKI SHOP may update these Terms and Conditions when necessary.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">13. Contact</h3>
                            <p>For questions or concerns regarding these Terms and Conditions, users may contact SUKI SHOP customer support.</p>
                        </section>
                    </div>
                    <div class="flex shrink-0 justify-end border-t border-gray-100 px-5 py-4 sm:px-7">
                        <button type="button" data-close-policy class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#155244]">Close</button>
                    </div>
                </dialog>

                <dialog
                    id="privacyDialog"
                    aria-labelledby="privacyDialogTitle"
                    class="buyer-policy-dialog m-auto h-[90dvh] max-h-[calc(100dvh-2rem)] w-[min(720px,calc(100%-2rem))] flex-col overflow-hidden rounded-2xl border border-[#DDE6E1] bg-white p-0 text-gray-700 shadow-2xl backdrop:bg-[#102C25]/55 backdrop:backdrop-blur-sm"
                >
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-5 py-4 sm:px-7">
                        <div>
                            <h2 id="privacyDialogTitle" class="text-lg font-semibold text-[#173F35]">SUKI SHOP Privacy Policy</h2>
                            <p class="mt-1 text-xs text-gray-500">Effective Date: October 2026</p>
                        </div>
                        <button
                            type="button"
                            data-close-policy
                            class="rounded-full px-3 py-1.5 text-sm font-semibold text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                            aria-label="Close Privacy Policy"
                        >X</button>
                    </div>
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-5 text-sm leading-6 sm:px-7">
                        <p>This Privacy Policy describes information collected through SUKI SHOP buyer registration and how that information is used to operate the platform.</p>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">1. Information Collected</h3>
                            <p>When you register, SUKI SHOP collects the information you submit, including your name, sex, date of birth, email address, mobile number, government ID, and delivery address.</p>
                            <p>When you use the platform, information related to orders and transactions may also be processed.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">2. Account Information Usage</h3>
                            <p>Account information is used to create and manage your account, verify registration information, communicate with you about your account, provide customer support, and protect the platform against unauthorized or fraudulent activity.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">3. Order and Transaction Information</h3>
                            <p>Order and transaction information is used to process purchases, coordinate fulfillment and delivery, provide order support, and maintain records of platform activity. Relevant delivery details may be shared with the parties involved in fulfilling an order.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">4. Data Security</h3>
                            <p>SUKI SHOP uses reasonable measures intended to protect information handled through the platform. No method of storage or transmission can be guaranteed to be completely secure.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">5. User Privacy Rights</h3>
                            <p>You may contact SUKI SHOP to request access to or correction of your account information, or to ask questions about how your information is handled. Requests may be subject to identity verification and applicable recordkeeping requirements.</p>
                        </section>
                        <section>
                            <h3 class="font-semibold text-[#173F35]">6. Contact Information</h3>
                            <p>For questions or privacy requests, contact SUKI SHOP customer support through the support channels made available on the platform.</p>
                        </section>
                    </div>
                    <div class="flex shrink-0 justify-end border-t border-gray-100 px-5 py-4 sm:px-7">
                        <button type="button" data-close-policy class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#155244]">Close</button>
                    </div>
                </dialog>


                <!-- ================================================= -->
                <!-- DIVIDER -->
                <!-- ================================================= -->

                <div class="flex items-center gap-4 my-6">

                    <div class="flex-1 h-px bg-gray-200"></div>

                    <span class="text-xs text-gray-400 font-medium">
                        OR
                    </span>

                    <div class="flex-1 h-px bg-gray-200"></div>

                </div>


                <!-- ================================================= -->
                <!-- LOGIN -->
                <!-- ================================================= -->

                <div class="text-center text-sm">

                    <span class="text-gray-500">
                        Already have a SUKI SHOP account?
                    </span>

                    <a
                        href="{{ route('login') }}"
                        class="font-semibold text-[#1F6F5B] hover:underline ml-1"
                    >
                        Log In
                    </a>

                </div>


                <!-- ================================================= -->
                <!-- RIDER APPLICATION -->
                <!-- ================================================= -->

                <div class="mt-7">

                    <div class="rounded-2xl border border-[#DDF3EC] bg-[#F7FCFA] p-4">

                        <div class="flex items-center gap-4">

                            <!-- Rider Icon -->
                            <div class="shrink-0 w-11 h-11 rounded-xl bg-white border border-[#DDF3EC] flex items-center justify-center">

                                <i
                                    data-lucide="bike"
                                    class="w-5 h-5 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <!-- Rider Information -->
                            <div class="flex-1">

                                <h3 class="text-sm font-semibold text-[#173F35]">
                                    Become a SUKI SHOP Rider
                                </h3>

                                <p class="text-xs text-gray-500 mt-1">
                                    Earn while delivering orders in your area.
                                </p>

                                <a
                                    href="/rider/register"
                                    class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-[#1F6F5B] hover:text-[#155244] transition"
                                >

                                    Apply as a SUKI SHOP Rider

                                    <i
                                        data-lucide="arrow-right"
                                        class="w-3.5 h-3.5"
                                    ></i>

                                </a>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- CONTINUE AS GUEST -->
                <!-- ================================================= -->

                <div class="text-center mt-5">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-[#1F6F5B] transition"
                    >

                        <i
                            data-lucide="arrow-left"
                            class="w-3.5 h-3.5"
                        ></i>

                        Continue as Guest

                    </a>

                </div>

            </div>

        </section>

    </div>


    <!-- ================================================= -->
    <!-- JAVASCRIPT -->
    <!-- ================================================= -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // =====================================================
            // AGE AUTO CALCULATION
            // =====================================================

            const birthdayInput = document.getElementById('birthday');
            const ageInput = document.getElementById('age');

            function calculateAge() {
                if (!birthdayInput || !ageInput || !birthdayInput.value) {
                    if (ageInput) {
                        ageInput.value = '';
                    }
                    return;
                }

                const birthDate = new Date(
                    birthdayInput.value + 'T00:00:00'
                );

                const today = new Date();

                let age =
                    today.getFullYear() -
                    birthDate.getFullYear();

                const monthDifference =
                    today.getMonth() -
                    birthDate.getMonth();

                if (
                    monthDifference < 0 ||
                    (
                        monthDifference === 0 &&
                        today.getDate() < birthDate.getDate()
                    )
                ) {
                    age--;
                }

                ageInput.value =
                    age >= 0
                        ? age
                        : '';
            }

            birthdayInput?.addEventListener(
                'change',
                calculateAge
            );

            if (birthdayInput?.value) {
                calculateAge();
            }


            // =====================================================
            // PHILIPPINE ADDRESS - PSGC CLOUD
            // =====================================================

            const API_BASE =
                '/api/psgc';

            const regionSelect =
                document.getElementById('region');

            const provinceSelect =
                document.getElementById('province');

            const municipalitySelect =
                document.getElementById('municipality');

            const barangaySelect =
                document.getElementById('barangay');

            const oldRegion =
                @json(old('region'));

            const oldProvince =
                @json(old('province'));

            const oldMunicipality =
                @json(old('municipality'));

            const oldBarangay =
                @json(old('barangay'));

            function createAddressDropdown(select) {
                const trigger =
                    document.getElementById(`${select.id}Dropdown`);

                const value =
                    document.getElementById(`${select.id}DropdownValue`);

                const listbox =
                    document.getElementById(`${select.id}Options`);

                const error =
                    document.getElementById(`${select.id}Error`);

                const wrapper = trigger.parentElement;
                let typeahead = '';
                let typeaheadTimer;

                function options() {
                    return Array.from(select.options)
                        .filter(option => option.value);
                }

                function close(returnFocus = false) {
                    listbox.classList.add('hidden');
                    trigger.setAttribute('aria-expanded', 'false');

                    if (returnFocus) {
                        trigger.focus();
                    }
                }

                function focusOption(index) {
                    const items =
                        listbox.querySelectorAll('[role="option"]');

                    if (!items.length) {
                        return;
                    }

                    const boundedIndex =
                        Math.max(0, Math.min(index, items.length - 1));

                    items[boundedIndex].focus();
                }

                function refresh() {
                    listbox.replaceChildren();

                    const items = options();

                    items.forEach(function (option, index) {
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.id = `${select.id}-option-${index}`;
                        item.setAttribute('role', 'option');
                        item.setAttribute(
                            'aria-selected',
                            option.value === select.value ? 'true' : 'false'
                        );
                        item.className =
                            'block w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-[#EEF8F3] focus:bg-[#EEF8F3] focus:outline-none';
                        item.textContent = option.textContent;
                        item.addEventListener('click', function () {
                            select.value = option.value;
                            select.dispatchEvent(
                                new Event('change', { bubbles: true })
                            );
                            error.classList.add('hidden');
                            trigger.removeAttribute('aria-invalid');
                            close();
                            trigger.focus();
                        });

                        listbox.appendChild(item);
                    });

                    const selectedOption =
                        select.options[select.selectedIndex];

                    value.textContent = select.value
                        ? selectedOption.textContent
                        : select.options[0]?.textContent || 'Select an option';

                    value.classList.toggle(
                        'text-gray-500',
                        !select.value
                    );

                    trigger.disabled =
                        select.disabled || items.length === 0;

                    if (trigger.disabled) {
                        close();
                    }
                }

                function open(index) {
                    if (trigger.disabled) {
                        return;
                    }

                    listbox.classList.remove('hidden');
                    trigger.setAttribute('aria-expanded', 'true');
                    focusOption(index);
                }

                trigger.addEventListener('click', function () {
                    if (trigger.getAttribute('aria-expanded') === 'true') {
                        close();
                        return;
                    }

                    const selectedIndex = options()
                        .findIndex(option => option.value === select.value);

                    open(Math.max(selectedIndex, 0));
                });

                trigger.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        open(
                            event.key === 'ArrowUp'
                                ? options().length - 1
                                : 0
                        );
                    }
                });

                listbox.addEventListener('keydown', function (event) {
                    const items = Array.from(
                        listbox.querySelectorAll('[role="option"]')
                    );

                    const activeIndex =
                        items.indexOf(document.activeElement);

                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        focusOption(
                            activeIndex + (event.key === 'ArrowDown' ? 1 : -1)
                        );
                    } else if (event.key === 'Home' || event.key === 'End') {
                        event.preventDefault();
                        focusOption(
                            event.key === 'Home' ? 0 : items.length - 1
                        );
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        close(true);
                    } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        document.activeElement.click();
                    } else if (
                        event.key.length === 1 &&
                        !event.altKey &&
                        !event.ctrlKey &&
                        !event.metaKey
                    ) {
                        typeahead += event.key.toLocaleLowerCase();
                        clearTimeout(typeaheadTimer);
                        typeaheadTimer = setTimeout(function () {
                            typeahead = '';
                        }, 500);

                        for (let offset = 1; offset <= items.length; offset++) {
                            const index =
                                (activeIndex + offset + items.length) % items.length;

                            if (items[index].textContent.toLocaleLowerCase().startsWith(typeahead)) {
                                focusOption(index);
                                break;
                            }
                        }
                    }
                });

                select.addEventListener('change', function () {
                    refresh();

                    if (select.value) {
                        error.classList.add('hidden');
                        trigger.removeAttribute('aria-invalid');
                    }
                });

                select.addEventListener('invalid', function (event) {
                    event.preventDefault();
                    error.classList.remove('hidden');
                    trigger.setAttribute('aria-invalid', 'true');
                    trigger.focus();
                });

                return { refresh, close, wrapper };
            }

            const addressDropdowns = new Map([
                ['region', createAddressDropdown(regionSelect)],
                ['province', createAddressDropdown(provinceSelect)],
                ['municipality', createAddressDropdown(municipalitySelect)],
                ['barangay', createAddressDropdown(barangaySelect)],
            ]);

            const registrationForm = document.querySelector('form');
            const registrationFields = [
                'last_name',
                'first_name',
                'middle_initial',
                'sex',
                'birthday',
                'phone',
                'email',
                'region',
                'province',
                'municipality',
                'barangay',
                'street_address',
                'government_id',
                'password',
                'password_confirmation',
                'terms',
            ];
            const namePattern = /^\p{L}+(?: \p{L}+)*$/u;
            const mobilePattern = /^(?:09[0-9]{9}|\+639[0-9]{9})$/;
            const emailDomainPattern =
                /^[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/u;
            const passwordPattern =
                /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\p{P}\p{S}]).{8,255}$/u;
            const interactedFields = new Set();
            let submitted = false;
            let provinceRequired = true;
            let regionLoadId = 0;
            let municipalityLoadId = 0;
            let barangayLoadId = 0;

            function registrationControl(fieldName) {
                return registrationForm?.elements.namedItem(fieldName);
            }

            function setFieldError(fieldName, message) {
                const error = registrationForm?.querySelector(
                    `[data-field-error="${fieldName}"]`
                );
                const control = registrationControl(fieldName);
                const ariaControl = addressDropdowns.has(fieldName)
                    ? document.getElementById(`${fieldName}Dropdown`)
                    : control;

                if (error) {
                    error.textContent = message;
                    error.classList.toggle('hidden', !message);
                }

                if (ariaControl) {
                    ariaControl.setAttribute(
                        'aria-invalid',
                        message ? 'true' : 'false'
                    );
                    ariaControl.classList.toggle('border-red-500', Boolean(message));
                    ariaControl.classList.toggle('border-gray-300', !message);
                }

                const summary = document.getElementById('registrationErrorSummary');
                if (summary) {
                    const hasVisibleFieldErrors = Array.from(
                        registrationForm.querySelectorAll('[data-field-error]')
                    ).some(fieldError => !fieldError.classList.contains('hidden'));
                    summary.classList.toggle('hidden', !hasVisibleFieldErrors);
                }
            }

            function fieldMessage(fieldName, showRequired) {
                const control = registrationControl(fieldName);
                const value = String(control?.value || '');

                switch (fieldName) {
                    case 'first_name':
                    case 'last_name': {
                        const name = fieldName === 'first_name'
                            ? 'First name'
                            : 'Last name';
                        const length = Array.from(value).length;

                        if (!value.trim() && showRequired) {
                            return `${name} is required.`;
                        }
                        if (!value.trim()) {
                            return '';
                        }
                        if (length < 2) {
                            return `${name} must be at least 2 characters.`;
                        }
                        if (length > 50) {
                            return `${name} may not exceed 50 characters.`;
                        }
                        if (!namePattern.test(value)) {
                            return `${name} may contain letters only, with spaces allowed between names.`;
                        }
                        return '';
                    }
                    case 'middle_initial':
                        return Array.from(value).length > 10
                            ? 'Middle initial may not exceed 10 characters.'
                            : '';
                    case 'sex':
                        if (!value && showRequired) return 'Select your sex.';
                        return value && !['Male', 'Female'].includes(value)
                            ? 'Select a valid sex.'
                            : '';
                    case 'birthday':
                        if (!value && showRequired) return 'Birthday is required.';
                        if (value && control.validity.badInput) {
                            return 'Enter a valid birthday.';
                        }
                        if (value && control.max && value > control.max) {
                            return 'You must be at least 18 years old to register.';
                        }
                        return '';
                    case 'phone':
                        if (!value && showRequired) return 'Contact number is required.';
                        return value && !mobilePattern.test(value)
                            ? 'Enter a valid Philippine mobile number in local or +63 format.'
                            : '';
                    case 'email':
                        if (!value && showRequired) return 'Email is required.';
                        if (Array.from(value).length > 255) {
                            return 'Email may not exceed 255 characters.';
                        }
                        return value && (
                            control.validity.typeMismatch ||
                            !emailDomainPattern.test(value)
                        )
                            ? 'Enter a valid email address with a domain extension.'
                            : '';
                    case 'region':
                        return !value && showRequired
                            ? 'Please select a region.'
                            : '';
                    case 'province':
                    case 'municipality':
                    case 'barangay':
                        if (fieldName === 'province' && !provinceRequired) {
                            return '';
                        }
                        if (!value && showRequired) {
                            return fieldName === 'province'
                                ? 'Please select a province.'
                                : fieldName === 'municipality'
                                    ? 'Please select a municipality or city.'
                                    : 'Please select a barangay.';
                        }
                        return '';
                    case 'street_address':
                        if (!value.trim() && showRequired) {
                            return 'Street address is required.';
                        }
                        return Array.from(value).length > 255
                            ? 'Street address may not exceed 255 characters.'
                            : '';
                    case 'government_id': {
                        if (control.dataset.invalidUploadMessage) {
                            return control.dataset.invalidUploadMessage;
                        }

                        const file = control.files?.[0];
                        if (!file && showRequired) return 'A valid ID file is required.';
                        if (!file) return '';
                        if (
                            !['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) ||
                            !/\.(jpe?g|png|pdf)$/i.test(file.name)
                        ) {
                            return 'Upload a JPG, JPEG, PNG, or PDF file.';
                        }
                        return file.size > 2 * 1024 * 1024
                            ? 'Government ID may not exceed 2 MB.'
                            : '';
                    }
                    case 'password':
                        if (!value && showRequired) return 'Password is required.';
                        return value && !passwordPattern.test(value)
                            ? 'Password must be at least 8 characters and include uppercase and lowercase letters, a number, and a special character.'
                            : '';
                    case 'password_confirmation':
                        if (!value && showRequired) return 'Confirm password is required.';
                        return value && value !== registrationControl('password').value
                            ? 'Password confirmation must match the password.'
                            : '';
                    case 'terms':
                        return !control.checked && showRequired
                            ? "You must agree to SUKI SHOP's Terms & Conditions and Privacy Policy before continuing."
                            : '';
                    default:
                        return '';
                }
            }

            function validateRegistrationField(
                fieldName,
                showRequired = submitted || interactedFields.has(fieldName)
            ) {
                const message = fieldMessage(fieldName, showRequired);
                setFieldError(fieldName, message);
                return !message;
            }

            function filterNameInput(input) {
                const originalValue = input.value;
                const cursor = input.selectionStart ?? originalValue.length;
                const allowedCharacters = /[^\p{L} ]/gu;
                const filteredValue = originalValue.replace(allowedCharacters, '');
                const filteredPrefix = originalValue
                    .slice(0, cursor)
                    .replace(allowedCharacters, '');
                const filteredCharacters = Array.from(filteredValue).slice(0, 50);
                const nextValue = filteredCharacters.join('');
                const nextCursor = filteredCharacters
                    .slice(0, Array.from(filteredPrefix).length)
                    .join('')
                    .length;

                if (nextValue !== originalValue) {
                    input.value = nextValue;
                    input.setSelectionRange(nextCursor, nextCursor);
                }
            }

            function filterNameInsertion(input, event) {
                if (
                    !event.data ||
                    !event.inputType.startsWith('insert') ||
                    event.isComposing
                ) {
                    return;
                }

                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? start;
                const currentLength =
                    Array.from(input.value.slice(0, start)).length +
                    Array.from(input.value.slice(end)).length;
                const availableLength = Math.max(0, 50 - currentLength);
                const insertedText = Array.from(event.data)
                    .filter(character => /\p{L}| /u.test(character))
                    .slice(0, availableLength)
                    .join('');
                const resultingLength = currentLength +
                    Array.from(insertedText).length;

                if (
                    insertedText === event.data &&
                    resultingLength <= 50
                ) {
                    return;
                }

                event.preventDefault();

                if (insertedText) {
                    input.setRangeText(insertedText, start, end, 'end');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            function filterPhoneInput(input) {
                const originalValue = input.value;
                const cursor = input.selectionStart ?? originalValue.length;
                let filteredValue = '';
                let filteredPrefix = '';

                Array.from(originalValue).forEach((character, index) => {
                    const allowed = /[0-9]/.test(character) ||
                        (character === '+' && index === 0);

                    if (allowed) {
                        filteredValue += character;
                        if (index < cursor) {
                            filteredPrefix += character;
                        }
                    }
                });

                const maxLength = filteredValue.startsWith('+') ? 13 : 11;
                filteredValue = Array.from(filteredValue)
                    .slice(0, maxLength)
                    .join('');
                const nextCursor = Math.min(
                    Array.from(filteredPrefix).length,
                    Array.from(filteredValue).length
                );

                if (filteredValue !== originalValue) {
                    input.value = filteredValue;
                    input.setSelectionRange(nextCursor, nextCursor);
                }
            }

            function allowPhoneInsertion(input, event) {
                if (
                    !event.data ||
                    !event.inputType.startsWith('insert') ||
                    event.isComposing
                ) {
                    return;
                }

                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? start;
                const nextValue =
                    input.value.slice(0, start) +
                    event.data +
                    input.value.slice(end);
                const maxLength = nextValue.startsWith('+') ? 13 : 11;
                const insertedCharactersAreValid = Array.from(event.data)
                    .every((character, index) => (
                        /[0-9]/.test(character) ||
                        (
                            character === '+' &&
                            start === 0 &&
                            index === 0 &&
                            !input.value.includes('+')
                        )
                    ));

                if (
                    insertedCharactersAreValid &&
                    Array.from(nextValue).length <= maxLength
                ) {
                    return;
                }

                event.preventDefault();
            }

            function resetBuyerGovernmentIdPreview() {
                const input = registrationControl('government_id');
                const container = document.getElementById('governmentIdPreviewContainer');
                const preview = document.getElementById('governmentIdPreview');
                const filename = document.getElementById('governmentIdPreviewName');

                if (preview.src.startsWith('blob:')) {
                    URL.revokeObjectURL(preview.src);
                }

                preview.removeAttribute('src');
                preview.classList.add('hidden');
                filename.textContent = '';
                container.classList.add('hidden');
                container.classList.remove('flex');
                input.value = '';
                delete input.dataset.invalidUploadMessage;
            }

            function updateBuyerGovernmentIdPreview(input) {
                const file = input.files?.[0];
                const container = document.getElementById('governmentIdPreviewContainer');
                const preview = document.getElementById('governmentIdPreview');
                const filename = document.getElementById('governmentIdPreviewName');

                if (!file) {
                    resetBuyerGovernmentIdPreview();
                    return;
                }

                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'application/pdf',
                ];
                let message = '';

                if (
                    !allowedTypes.includes(file.type) ||
                    !/\.(jpe?g|png|pdf)$/i.test(file.name)
                ) {
                    message = 'Upload a JPG, JPEG, PNG, or PDF file.';
                } else if (file.size > 2 * 1024 * 1024) {
                    message = 'Government ID may not exceed 2 MB.';
                }

                if (message) {
                    resetBuyerGovernmentIdPreview();
                    input.dataset.invalidUploadMessage = message;
                    interactedFields.add('government_id');
                    validateRegistrationField('government_id');
                    return;
                }

                delete input.dataset.invalidUploadMessage;
                filename.textContent = file.name;
                container.classList.remove('hidden');
                container.classList.add('flex');

                if (preview.src.startsWith('blob:')) {
                    URL.revokeObjectURL(preview.src);
                }

                if (file.type.startsWith('image/')) {
                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('hidden');
                } else {
                    preview.removeAttribute('src');
                    preview.classList.add('hidden');
                }

                interactedFields.add('government_id');
                validateRegistrationField('government_id');
            }

            if (registrationForm) {
                registrationForm.noValidate = true;

                document.querySelectorAll('[data-open-policy]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        document.getElementById(button.dataset.openPolicy).showModal();
                    });
                });

                document.querySelectorAll('dialog[id$="Dialog"]').forEach(function (dialog) {
                    dialog.querySelectorAll('[data-close-policy]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            dialog.close();
                        });
                    });

                    dialog.addEventListener('click', function (event) {
                        if (event.target === dialog) {
                            dialog.close();
                        }
                    });
                });

                ['first_name', 'last_name'].forEach(function (fieldName) {
                    const input = registrationControl(fieldName);

                    input.addEventListener('beforeinput', function (event) {
                        filterNameInsertion(input, event);
                    });

                    input.addEventListener('input', function (event) {
                        if (event.isComposing) {
                            return;
                        }

                        filterNameInput(input);
                        interactedFields.add(fieldName);
                        validateRegistrationField(fieldName);
                    });

                    input.addEventListener('compositionend', function () {
                        filterNameInput(input);
                        interactedFields.add(fieldName);
                        validateRegistrationField(fieldName);
                    });

                    input.addEventListener('blur', function () {
                        interactedFields.add(fieldName);
                        validateRegistrationField(fieldName);
                    });
                });

                const phoneInput = registrationControl('phone');
                phoneInput.addEventListener('beforeinput', function (event) {
                    allowPhoneInsertion(phoneInput, event);
                });
                phoneInput.addEventListener('input', function () {
                    filterPhoneInput(phoneInput);
                    interactedFields.add('phone');
                    validateRegistrationField('phone');
                });

                registrationControl('government_id')
                    .addEventListener('change', function () {
                        updateBuyerGovernmentIdPreview(this);
                    });

                document.getElementById('removeGovernmentId')
                    .addEventListener('click', function () {
                        resetBuyerGovernmentIdPreview();
                        validateRegistrationField('government_id');
                    });

                registrationFields.forEach(function (fieldName) {
                    const control = registrationControl(fieldName);

                    if (['first_name', 'last_name'].includes(fieldName)) {
                        const serverError = registrationForm.querySelector(
                            `[data-field-error="${fieldName}"]`
                        );
                        if (serverError && !serverError.classList.contains('hidden')) {
                            setFieldError(fieldName, serverError.textContent.trim());
                        }
                        return;
                    }

                    const eventName = fieldName === 'terms' ||
                        ['sex', 'birthday', 'region', 'province', 'municipality', 'barangay', 'government_id'].includes(fieldName)
                        ? 'change'
                        : 'input';

                    control?.addEventListener(eventName, function () {
                        interactedFields.add(fieldName);
                        validateRegistrationField(fieldName);

                        if (fieldName === 'password') {
                            validateRegistrationField('password_confirmation');
                        }
                        if (fieldName === 'region') {
                            validateRegistrationField('province');
                            validateRegistrationField('municipality');
                            validateRegistrationField('barangay');
                        } else if (fieldName === 'province') {
                            validateRegistrationField('municipality');
                            validateRegistrationField('barangay');
                        }
                        if (fieldName === 'municipality') {
                            validateRegistrationField('barangay');
                        }
                    });

                    control?.addEventListener('blur', function () {
                        interactedFields.add(fieldName);
                        validateRegistrationField(fieldName);
                    });

                    const serverError = registrationForm.querySelector(
                        `[data-field-error="${fieldName}"]`
                    );
                    if (serverError && !serverError.classList.contains('hidden')) {
                        setFieldError(fieldName, serverError.textContent.trim());
                    }
                });

                registrationForm.addEventListener('submit', function (event) {
                    submitted = true;
                    const isValid = registrationFields
                        .map(fieldName => validateRegistrationField(fieldName, true))
                        .every(Boolean);

                    if (!isValid) {
                        event.preventDefault();

                        const firstInvalidField = registrationFields.find(fieldName => (
                            registrationForm.querySelector(
                                `[data-field-error="${fieldName}"]:not(.hidden)`
                            )
                        ));
                        const focusTarget = addressDropdowns.has(firstInvalidField)
                            ? document.getElementById(`${firstInvalidField}Dropdown`)
                            : registrationControl(firstInvalidField);

                        focusTarget?.focus();
                        focusTarget?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                    }
                });
            }

            function refreshDropdown(select) {
                addressDropdowns.get(select.id).refresh();
            }

            document.addEventListener('click', function (event) {
                addressDropdowns.forEach(function (dropdown) {
                    if (!dropdown.wrapper.contains(event.target)) {
                        dropdown.close();
                    }
                });
            });

            function getItems(payload) {
                if (Array.isArray(payload)) {
                    return payload;
                }

                if (
                    payload &&
                    Array.isArray(payload.data)
                ) {
                    return payload.data;
                }

                return [];
            }


            function resetProvince() {
                provinceRequired = true;
                provinceSelect.required = true;
                document.getElementById('provinceDropdown')
                    .setAttribute('aria-required', 'true');
                document.getElementById('provinceRequiredMark').classList.remove('hidden');
                provinceSelect.innerHTML =
                    '<option value="">Select region first</option>';
                provinceSelect.disabled = true;
                refreshDropdown(provinceSelect);
            }


            function resetMunicipalities(placeholder = 'Select province first') {
                municipalitySelect.innerHTML =
                    `<option value="">${placeholder}</option>`;
                municipalitySelect.disabled = true;
                refreshDropdown(municipalitySelect);
            }


            function resetBarangays() {
                barangaySelect.innerHTML =
                    '<option value="">Select municipality / city first</option>';
                barangaySelect.disabled = true;
                refreshDropdown(barangaySelect);
            }


            async function fetchItems(path) {
                const response = await fetch(
                    `${API_BASE}/${path}`,
                    {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(`Request failed: ${response.status}`);
                }

                return getItems(await response.json());
            }


            function setSelectOptions(select, placeholder, items) {
                select.innerHTML = '';

                const placeholderOption = document.createElement('option');
                placeholderOption.value = '';
                placeholderOption.textContent = placeholder;
                select.appendChild(placeholderOption);

                items
                    .sort((a, b) => a.name.localeCompare(b.name))
                    .forEach(function (item) {
                        const option = document.createElement('option');
                        option.value = item.code;
                        option.textContent = item.name;
                        select.appendChild(option);
                    });

                select.disabled = false;
                refreshDropdown(select);
            }


            async function loadRegions(selectedRegion = '') {
                const loadId = ++regionLoadId;
                resetProvince();
                resetMunicipalities();
                resetBarangays();
                regionSelect.innerHTML =
                    '<option value="">Loading regions...</option>';
                regionSelect.disabled = true;
                refreshDropdown(regionSelect);

                try {
                    const regions = await fetchItems('regions');

                    if (loadId !== regionLoadId) {
                        return;
                    }

                    setSelectOptions(regionSelect, 'Select region', regions);

                    if (selectedRegion && regions.some(region => region.code === selectedRegion)) {
                        regionSelect.value = selectedRegion;
                        refreshDropdown(regionSelect);
                        await loadProvinces(
                            selectedRegion,
                            oldProvince,
                            oldMunicipality,
                            oldBarangay
                        );
                    }
                } catch (error) {
                    if (loadId === regionLoadId) {
                        console.error('Unable to load regions:', error);
                        regionSelect.innerHTML =
                            '<option value="">Unable to load regions</option>';
                        regionSelect.disabled = true;
                        refreshDropdown(regionSelect);
                    }
                }
            }


            async function loadProvinces(
                regionCode,
                selectedProvince = '',
                selectedMunicipality = '',
                selectedBarangay = ''
            ) {
                const loadId = ++regionLoadId;
                municipalityLoadId++;
                barangayLoadId++;
                resetProvince();
                resetMunicipalities();
                resetBarangays();

                if (!regionCode) {
                    return;
                }

                provinceSelect.innerHTML =
                    '<option value="">Loading provinces...</option>';
                refreshDropdown(provinceSelect);

                try {
                    const provinces = await fetchItems(
                        `regions/${encodeURIComponent(regionCode)}/provinces`
                    );

                    if (loadId !== regionLoadId) {
                        return;
                    }

                    if (provinces.length === 0) {
                        provinceRequired = false;
                        provinceSelect.required = false;
                        document.getElementById('provinceDropdown')
                            .setAttribute('aria-required', 'false');
                        provinceSelect.disabled = true;
                        provinceSelect.innerHTML =
                            '<option value="">Not applicable for this region</option>';
                        document.getElementById('provinceRequiredMark').classList.add('hidden');
                        refreshDropdown(provinceSelect);
                        validateRegistrationField('province');
                        await loadMunicipalities(
                            '',
                            regionCode,
                            selectedMunicipality,
                            selectedBarangay
                        );
                        return;
                    }

                    provinceRequired = true;
                    provinceSelect.required = true;
                    document.getElementById('provinceDropdown')
                        .setAttribute('aria-required', 'true');
                    document.getElementById('provinceRequiredMark').classList.remove('hidden');
                    setSelectOptions(provinceSelect, 'Select province', provinces);

                    if (selectedProvince && provinces.some(province => province.code === selectedProvince)) {
                        provinceSelect.value = selectedProvince;
                        refreshDropdown(provinceSelect);
                        await loadMunicipalities(
                            selectedProvince,
                            '',
                            selectedMunicipality,
                            selectedBarangay
                        );
                    }
                } catch (error) {
                    if (loadId === regionLoadId) {
                        console.error('Unable to load provinces:', error);
                        provinceSelect.innerHTML =
                            '<option value="">Unable to load provinces</option>';
                        provinceSelect.disabled = true;
                        refreshDropdown(provinceSelect);
                    }
                }
            }


            async function loadMunicipalities(
                provinceCode,
                regionCode = '',
                selectedMunicipality = '',
                selectedBarangay = ''
            ) {
                const loadId = ++municipalityLoadId;
                resetMunicipalities('Loading municipalities / cities...');
                resetBarangays();
                barangayLoadId++;

                const path = provinceCode
                    ? `provinces/${encodeURIComponent(provinceCode)}/cities-municipalities`
                    : `regions/${encodeURIComponent(regionCode)}/cities-municipalities`;

                try {
                    const municipalities = await fetchItems(path);

                    if (loadId !== municipalityLoadId) {
                        return;
                    }

                    setSelectOptions(
                        municipalitySelect,
                        'Select municipality / city',
                        municipalities
                    );

                    if (selectedMunicipality && municipalities.some(item => item.code === selectedMunicipality)) {
                        municipalitySelect.value = selectedMunicipality;
                        refreshDropdown(municipalitySelect);
                        await loadBarangays(selectedMunicipality, selectedBarangay);
                    }
                } catch (error) {
                    if (loadId === municipalityLoadId) {
                        console.error('Unable to load municipalities:', error);
                        municipalitySelect.innerHTML =
                            '<option value="">Unable to load municipalities / cities</option>';
                        municipalitySelect.disabled = true;
                        refreshDropdown(municipalitySelect);
                    }
                }
            }


            async function loadBarangays(municipalityCode, selectedBarangay = '') {
                const loadId = ++barangayLoadId;
                barangaySelect.innerHTML =
                    '<option value="">Loading barangays...</option>';
                barangaySelect.disabled = true;
                refreshDropdown(barangaySelect);

                try {
                    const barangays = await fetchItems(
                        `cities-municipalities/${encodeURIComponent(municipalityCode)}/barangays`
                    );

                    if (loadId !== barangayLoadId) {
                        return;
                    }

                    setSelectOptions(barangaySelect, 'Select barangay', barangays);

                    if (selectedBarangay && barangays.some(item => item.code === selectedBarangay)) {
                        barangaySelect.value = selectedBarangay;
                        refreshDropdown(barangaySelect);
                    }
                } catch (error) {
                    if (loadId === barangayLoadId) {
                        console.error('Unable to load barangays:', error);
                        barangaySelect.innerHTML =
                            '<option value="">Unable to load barangays</option>';
                        barangaySelect.disabled = true;
                        refreshDropdown(barangaySelect);
                    }
                }
            }


            regionSelect?.addEventListener('change', function () {
                loadProvinces(this.value);
            });

            provinceSelect?.addEventListener('change', function () {
                if (this.value) {
                    loadMunicipalities(this.value);
                } else {
                    municipalityLoadId++;
                    barangayLoadId++;
                    resetMunicipalities();
                    resetBarangays();
                }
            });

            municipalitySelect?.addEventListener('change', function () {
                if (this.value) {
                    loadBarangays(this.value);
                } else {
                    barangayLoadId++;
                    resetBarangays();
                }
            });

            if (regionSelect && provinceSelect && municipalitySelect && barangaySelect) {
                loadRegions(oldRegion);
            }

        });


        function togglePassword(inputId, iconId) {
            const password =
                document.getElementById(inputId);

            const icon =
                document.getElementById(iconId);

            if (!password || !icon) {
                return;
            }

            if (password.type === 'password') {
                password.type = 'text';
                icon.setAttribute(
                    'data-lucide',
                    'eye-off'
                );
            } else {
                password.type = 'password';
                icon.setAttribute(
                    'data-lucide',
                    'eye'
                );
            }

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    </script>

</body>
</html>