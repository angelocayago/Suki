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

                    <div class="mb-5 rounded-xl bg-red-50 border border-red-100 px-4 py-3">

                        <ul class="text-sm text-red-600 space-y-1">

                            @foreach ($errors->all() as $error)

                                <li>
                                    {{ $error }}
                                </li>

                            @endforeach

                        </ul>

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
                >

                    @csrf


                    <!-- Personal Information -->
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
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
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
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
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
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
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
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
                                <option value="">Select sex</option>
                                <option value="Male" @selected(old('sex') === 'Male')>Male</option>
                                <option value="Female" @selected(old('sex') === 'Female')>Female</option>
                            </select>
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
                                max="{{ now()->subDay()->format('Y-m-d') }}"
                                class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                required
                            >
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


                    <!-- Contact Information -->
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
                                    type="text"
                                    id="phone"
                                    name="phone"
                                    value="{{ old('phone') }}"
                                    placeholder="Enter contact number"
                                    class="w-full pl-11 pr-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                            </div>
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
                                    class="w-full pl-11 pr-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                            </div>
                        </div>

                    </div>


                    <!-- Address -->
                    <div class="pt-2">
                        <div class="mb-4">
                            <p class="text-sm font-semibold text-[#173F35]">
                                Address
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Select your location and enter your complete street address.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                            <!-- Province -->
                            <div>
                                <label
                                    for="province"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Province <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="province"
                                    name="province"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                    required
                                >
                                    <option value="">Loading provinces...</option>
                                </select>
                            </div>


                            <!-- Municipality / City -->
                            <div>
                                <label
                                    for="municipality"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Municipality / City <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="municipality"
                                    name="municipality"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                    required
                                    disabled
                                >
                                    <option value="">Select municipality / city</option>
                                </select>
                            </div>


                            <!-- Barangay -->
                            <div>
                                <label
                                    for="barangay"
                                    class="block text-sm font-medium text-gray-700 mb-2"
                                >
                                    Barangay <span class="text-red-500">*</span>
                                </label>

                                <select
                                    id="barangay"
                                    name="barangay"
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-50 disabled:text-gray-400"
                                    required
                                    disabled
                                >
                                    <option value="">Select barangay</option>
                                </select>
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
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                    required
                                >
                            </div>

                        </div>
                    </div>


                    <!-- Government ID -->
                    <div>
                        <label
                            for="government_id"
                            class="block text-sm font-medium text-gray-700 mb-2"
                        >
                            Upload Valid ID <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="file"
                            id="government_id"
                            name="government_id"
                            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                            class="block w-full rounded-lg border border-gray-300 bg-white text-sm text-gray-600 file:mr-4 file:border-0 file:bg-[#EEF8F3] file:px-4 file:py-3 file:text-sm file:font-semibold file:text-[#1F6F5B] hover:file:bg-[#DFF2E9]"
                            required
                        >

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
                                class="w-full pl-11 pr-12 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
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
                                class="w-full pl-11 pr-12 py-3 rounded-lg border border-gray-300 bg-white text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
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

                    </div>


                    <!-- Terms -->
                    <div class="flex items-start gap-2 pt-1">

                        <input
                            type="checkbox"
                            id="terms"
                            name="terms"
                            value="1"
                            class="mt-1 w-4 h-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                            required
                        >

                        <label
                            for="terms"
                            class="text-xs text-gray-500 leading-relaxed"
                        >

                            I agree to SUKI SHOP's

                            <a
                                href="#"
                                class="text-[#1F6F5B] font-medium hover:underline"
                            >
                                Terms & Conditions
                            </a>

                            and

                            <a
                                href="#"
                                class="text-[#1F6F5B] font-medium hover:underline"
                            >
                                Privacy Policy
                            </a>.

                        </label>

                    </div>


                    <!-- Create Account -->
                    <button
                        type="submit"
                        class="w-full py-3.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-semibold hover:bg-[#155244] transition"
                    >
                        CREATE ACCOUNT
                    </button>

                </form>


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
                'https://psgc.cloud/api/v2';

            const provinceSelect =
                document.getElementById('province');

            const municipalitySelect =
                document.getElementById('municipality');

            const barangaySelect =
                document.getElementById('barangay');

            const oldProvince =
                @json(old('province'));

            const oldMunicipality =
                @json(old('municipality'));

            const oldBarangay =
                @json(old('barangay'));


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


            function resetMunicipalities() {
                municipalitySelect.innerHTML =
                    '<option value="">Select municipality / city</option>';

                municipalitySelect.disabled = true;
            }


            function resetBarangays() {
                barangaySelect.innerHTML =
                    '<option value="">Select barangay</option>';

                barangaySelect.disabled = true;
            }


            async function fetchItems(url) {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(
                        `Request failed: ${response.status}`
                    );
                }

                return getItems(
                    await response.json()
                );
            }


            async function loadProvinces() {
                provinceSelect.disabled = true;

                provinceSelect.innerHTML =
                    '<option value="">Loading provinces...</option>';

                resetMunicipalities();
                resetBarangays();

                try {
                    const provinces =
                        await fetchItems(
                            `${API_BASE}/provinces`
                        );

                    provinces.sort(
                        (a, b) =>
                            a.name.localeCompare(b.name)
                    );

                    provinceSelect.innerHTML =
                        '<option value="">Select province</option>';

                    provinces.forEach(function (province) {
                        const option =
                            document.createElement('option');

                        option.value =
                            province.name;

                        option.textContent =
                            province.name;

                        option.dataset.code =
                            province.code;

                        provinceSelect.appendChild(option);
                    });

                    provinceSelect.disabled = false;

                    if (oldProvince) {
                        const matchingProvince =
                            provinces.find(
                                province =>
                                    province.name === oldProvince
                            );

                        if (matchingProvince) {
                            provinceSelect.value =
                                matchingProvince.name;

                            await loadMunicipalities(
                                matchingProvince.code
                            );
                        }
                    }

                } catch (error) {
                    console.error(
                        'Unable to load provinces:',
                        error
                    );

                    provinceSelect.innerHTML =
                        '<option value="">Unable to load provinces</option>';

                    provinceSelect.disabled = false;
                }
            }


            async function loadMunicipalities(provinceCode) {
                resetMunicipalities();
                resetBarangays();

                municipalitySelect.innerHTML =
                    '<option value="">Loading municipalities / cities...</option>';

                try {
                    const municipalities =
                        await fetchItems(
                            `${API_BASE}/provinces/${encodeURIComponent(provinceCode)}/cities-municipalities`
                        );

                    municipalities.sort(
                        (a, b) =>
                            a.name.localeCompare(b.name)
                    );

                    municipalitySelect.innerHTML =
                        '<option value="">Select municipality / city</option>';

                    municipalities.forEach(function (municipality) {
                        const option =
                            document.createElement('option');

                        option.value =
                            municipality.name;

                        option.textContent =
                            municipality.name;

                        option.dataset.code =
                            municipality.code;

                        municipalitySelect.appendChild(option);
                    });

                    municipalitySelect.disabled = false;

                    if (oldMunicipality) {
                        const matchingMunicipality =
                            municipalities.find(
                                municipality =>
                                    municipality.name === oldMunicipality
                            );

                        if (matchingMunicipality) {
                            municipalitySelect.value =
                                matchingMunicipality.name;

                            await loadBarangays(
                                matchingMunicipality.code
                            );
                        }
                    }

                } catch (error) {
                    console.error(
                        'Unable to load municipalities:',
                        error
                    );

                    municipalitySelect.innerHTML =
                        '<option value="">Unable to load municipalities / cities</option>';

                    municipalitySelect.disabled = false;
                }
            }


            async function loadBarangays(municipalityCode) {
                resetBarangays();

                barangaySelect.innerHTML =
                    '<option value="">Loading barangays...</option>';

                try {
                    const barangays =
                        await fetchItems(
                            `${API_BASE}/cities-municipalities/${encodeURIComponent(municipalityCode)}/barangays`
                        );

                    barangays.sort(
                        (a, b) =>
                            a.name.localeCompare(b.name)
                    );

                    barangaySelect.innerHTML =
                        '<option value="">Select barangay</option>';

                    barangays.forEach(function (barangay) {
                        const option =
                            document.createElement('option');

                        option.value =
                            barangay.name;

                        option.textContent =
                            barangay.name;

                        barangaySelect.appendChild(option);
                    });

                    barangaySelect.disabled = false;

                    if (oldBarangay) {
                        barangaySelect.value =
                            oldBarangay;
                    }

                } catch (error) {
                    console.error(
                        'Unable to load barangays:',
                        error
                    );

                    barangaySelect.innerHTML =
                        '<option value="">Unable to load barangays</option>';

                    barangaySelect.disabled = false;
                }
            }


            provinceSelect?.addEventListener(
                'change',
                function () {
                    resetMunicipalities();
                    resetBarangays();

                    const selectedOption =
                        provinceSelect.options[
                            provinceSelect.selectedIndex
                        ];

                    const provinceCode =
                        selectedOption?.dataset?.code;

                    if (provinceCode) {
                        loadMunicipalities(
                            provinceCode
                        );
                    }
                }
            );


            municipalitySelect?.addEventListener(
                'change',
                function () {
                    resetBarangays();

                    const selectedOption =
                        municipalitySelect.options[
                            municipalitySelect.selectedIndex
                        ];

                    const municipalityCode =
                        selectedOption?.dataset?.code;

                    if (municipalityCode) {
                        loadBarangays(
                            municipalityCode
                        );
                    }
                }
            );


            if (
                provinceSelect &&
                municipalitySelect &&
                barangaySelect
            ) {
                loadProvinces();
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