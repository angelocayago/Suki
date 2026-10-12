@extends('layouts.auth')

@section('title', 'Logistics Registration')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    <!-- HEADER -->
    <header class="border-b border-gray-200 bg-white">

        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">

            <!-- LOGO -->
            <a href="{{ route('buyer.home') }}" class="flex items-center gap-3">

                <img
                    src="{{ asset('images/suki-logistics.jpg') }}"
                    alt="SUKI SHOP"
                    class="h-10 w-auto object-contain sm:h-11"
                >

                <div class="hidden border-l border-gray-200 pl-3 sm:block">

                    <p class="text-sm font-semibold text-gray-900">
                        SUKI SHOP Logistics
                    </p>

                    <p class="text-xs text-gray-500">
                        Sorting Center Portal
                    </p>

                </div>

            </a>


            <!-- LOGIN -->
            <a
                href="{{ route('login') }}"
                class="text-sm font-medium text-gray-600 transition hover:text-[#1F6F5B]"
            >
                Log In
            </a>

        </div>

    </header>



    <!-- MAIN -->
    <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12 lg:py-16">


        <!-- PAGE INTRO -->
        <div class="mb-8 max-w-3xl">

            <!-- BADGE -->
            <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-[#E6F4EE] px-3 py-1.5 text-xs font-semibold text-[#1F6F5B]">

                <i data-lucide="building-2" class="h-4 w-4"></i>

                SUKI SHOP Logistics Partner

            </div>


            <!-- TITLE -->
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl lg:text-4xl">

                Register Your Logistics Center

            </h1>


            <!-- DESCRIPTION -->
            <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base">

                Join the SUKI SHOP Logistics network and manage parcel receiving,
                sorting, rider assignments, and delivery operations through
                the SUKI SHOP Logistics Management System.

            </p>

        </div>



        <!-- FORM -->
        <form
            method="POST"
            action="{{ route('logistics.register.submit') }}"
            enctype="multipart/form-data"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]"
        >

            @csrf

            @if (session('status'))
                <p class="lg:col-span-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
                    {{ session('status') }}
                </p>
            @endif

            @if ($errors->any())
                <div class="lg:col-span-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    Please correct the highlighted fields below.
                </div>
            @endif



            <!-- LEFT SIDE -->
            <div class="space-y-6">


                <!-- PERSONAL INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="user" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Account Owner Information

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Enter the information of the logistics center representative.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">


                        <!-- FIRST NAME -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                First Name

                            </label>

                            <input
                                type="text"
                                name="first_name"
                                value="{{ old('first_name') }}"
                                required
                                minlength="2"
                                maxlength="50"
                                pattern="[\p{L}]+(?:[\x20\x27\x2D][\p{L}]+)*"
                                aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}"
                                placeholder="Enter first name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('first_name')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                        </div>



                        <!-- LAST NAME -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Last Name

                            </label>

                            <input
                                type="text"
                                name="last_name"
                                value="{{ old('last_name') }}"
                                required
                                minlength="2"
                                maxlength="50"
                                pattern="[\p{L}]+(?:[\x20\x27\x2D][\p{L}]+)*"
                                aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}"
                                placeholder="Enter last name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('last_name')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                        </div>



                        <!-- POSITION -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Position

                            </label>

                            <input
                                type="text"
                                name="position"
                                value="{{ old('position') }}"
                                required
                                maxlength="100"
                                aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}"
                                placeholder="Enter your position"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('position')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                        </div>



                        <!-- EMAIL -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Email Address

                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="mail"
                                    class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>

                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    maxlength="255"
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >

                            </div>
                            @error('email')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror

                        </div>



                        <!-- CONTACT NUMBER -->
                        <div>

                            <label class="mb-2 block text-sm font-medium text-gray-700">

                                Mobile Number

                            </label>

                            <div class="relative">

                                <i
                                    data-lucide="phone"
                                    class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                ></i>

                                <input
                                    type="tel"
                                    name="mobile_number"
                                    value="{{ old('mobile_number') }}"
                                    required
                                    inputmode="tel"
                                    maxlength="13"
                                    pattern="(?:09[0-9]{9}|\+639[0-9]{9})"
                                    aria-invalid="{{ $errors->has('mobile_number') ? 'true' : 'false' }}"
                                    placeholder="09XXXXXXXXX or +639XXXXXXXXX"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-3 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >

                                </div>
                                @error('mobile_number')
                                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                                @enderror

                        </div>



                        <!-- PASSWORD -->
                        <div>

                                <label class="mb-2 block text-sm font-medium text-gray-700">

                                    Password

                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    required
                                    minlength="8"
                                    maxlength="255"
                                    autocomplete="new-password"
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    placeholder="At least 8 characters"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >
                                @error('password')
                                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                                @enderror

                        </div>



                        <!-- CONFIRM PASSWORD -->
                        <div>

                                <label class="mb-2 block text-sm font-medium text-gray-700">

                                    Confirm Password

                                </label>

                                <input
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    minlength="8"
                                    maxlength="255"
                                    autocomplete="new-password"
                                    aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                                    placeholder="Re-enter your password"
                                    class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                                >
                                @error('password_confirmation')
                                    <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                                @enderror

                        </div>


                    </div>

                </section>



                <!-- LOGISTICS BUSINESS INFORMATION -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="building-2" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Company Details

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Enter the registered company details.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                        <div class="sm:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Company Name</label>
                            <input
                                type="text"
                                name="company_name"
                                value="{{ old('company_name') }}"
                                required
                                maxlength="255"
                                pattern=".*\S.*"
                                aria-invalid="{{ $errors->has('company_name') ? 'true' : 'false' }}"
                                placeholder="Enter registered company name"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('company_name')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">SEC Registration No.</label>
                            <input
                                type="text"
                                name="sec_registration_number"
                                value="{{ old('sec_registration_number') }}"
                                required
                                maxlength="11"
                                pattern="[A-Za-z]{2}[0-9]{9}"
                                title="Enter exactly 2 letters followed by 9 digits, for example CS202012345."
                                aria-invalid="{{ $errors->has('sec_registration_number') ? 'true' : 'false' }}"
                                placeholder="Enter SEC registration number"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('sec_registration_number')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">TIN</label>
                            <input
                                type="text"
                                name="tin"
                                value="{{ old('tin') }}"
                                required
                                inputmode="numeric"
                                maxlength="16"
                                pattern="[0-9]{12}|[0-9]{3}(-[0-9]{3}){3}"
                                aria-invalid="{{ $errors->has('tin') ? 'true' : 'false' }}"
                                placeholder="12 digits or 000-000-000-000"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('tin')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>

                </section>



                <!-- ADDRESS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="map-pin" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Logistics Center Address

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Enter the service location address.

                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label for="region" class="mb-2 block text-sm font-medium text-gray-700">Region</label>
                            <select
                                id="region"
                                name="region"
                                required
                                aria-invalid="{{ $errors->has('region') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                                <option value="">Loading regions...</option>
                            </select>
                            @error('region')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="province" class="mb-2 block text-sm font-medium text-gray-700">Province</label>
                            <select
                                id="province"
                                name="province"
                                required
                                disabled
                                aria-invalid="{{ $errors->has('province') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">Select region first</option>
                            </select>
                            @error('province')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="city_municipality" class="mb-2 block text-sm font-medium text-gray-700">City/Municipality</label>
                            <select
                                id="city_municipality"
                                name="city_municipality"
                                required
                                disabled
                                aria-invalid="{{ $errors->has('city_municipality') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">Select province first</option>
                            </select>
                            @error('city_municipality')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="barangay" class="mb-2 block text-sm font-medium text-gray-700">Barangay</label>
                            <select
                                id="barangay"
                                name="barangay"
                                required
                                disabled
                                aria-invalid="{{ $errors->has('barangay') ? 'true' : 'false' }}"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10 disabled:bg-gray-100 disabled:text-gray-400"
                            >
                                <option value="">Select city/municipality first</option>
                            </select>
                            @error('barangay')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Street Address</label>
                            <input
                                type="text"
                                name="street_address"
                                value="{{ old('street_address') }}"
                                required
                                maxlength="255"
                                aria-invalid="{{ $errors->has('street_address') ? 'true' : 'false' }}"
                                placeholder="House/building number and street"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('street_address')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        @push('scripts')
                        <script>
                            const psgcApiBase = '/api/psgc';
                            const regionSelect = document.getElementById('region');
                            const provinceSelect = document.getElementById('province');
                            const citySelect = document.getElementById('city_municipality');
                            const barangaySelect = document.getElementById('barangay');
                            const previousAddress = {
                                region: @json(old('region')),
                                province: @json(old('province')),
                                city: @json(old('city_municipality')),
                                barangay: @json(old('barangay')),
                            };

                            let regionRequestId = 0;
                            let cityRequestId = 0;
                            let barangayRequestId = 0;

                            function setAddressOptions(select, placeholder, items = []) {
                                select.replaceChildren(new Option(placeholder, ''));

                                items.forEach((item) => {
                                    select.add(new Option(item.name, item.code));
                                });
                            }

                            async function fetchAddressOptions(path) {
                                const response = await fetch(`${psgcApiBase}/${path}`);

                                if (!response.ok) {
                                    throw new Error(`Unable to load address data (${response.status}).`);
                                }

                                const items = await response.json();

                                if (!Array.isArray(items)) {
                                    throw new Error('The address service returned invalid data.');
                                }

                                return items;
                            }

                            function clearCityAndBarangay() {
                                cityRequestId++;
                                barangayRequestId++;
                                setAddressOptions(citySelect, 'Select province first');
                                citySelect.disabled = true;
                                setAddressOptions(barangaySelect, 'Select city/municipality first');
                                barangaySelect.disabled = true;
                            }

                            async function loadBarangays(cityCode, selectedBarangay = '') {
                                const requestId = ++barangayRequestId;
                                setAddressOptions(barangaySelect, 'Loading barangays...');
                                barangaySelect.disabled = true;

                                try {
                                    const barangays = await fetchAddressOptions(
                                        `cities-municipalities/${encodeURIComponent(cityCode)}/barangays`
                                    );

                                    if (requestId !== barangayRequestId) {
                                        return;
                                    }

                                    setAddressOptions(barangaySelect, 'Select barangay', barangays);
                                    barangaySelect.disabled = false;

                                    if (selectedBarangay && barangays.some((item) => item.code === selectedBarangay)) {
                                        barangaySelect.value = selectedBarangay;
                                    }
                                } catch (error) {
                                    if (requestId === barangayRequestId) {
                                        setAddressOptions(barangaySelect, 'Unable to load barangays');
                                        barangaySelect.disabled = true;
                                        console.error(error);
                                    }
                                }
                            }

                            async function loadCities(parentCode, regionCode = '', selectedCity = '', selectedBarangay = '') {
                                const requestId = ++cityRequestId;
                                barangayRequestId++;
                                setAddressOptions(citySelect, 'Loading cities/municipalities...');
                                citySelect.disabled = true;
                                setAddressOptions(barangaySelect, 'Select city/municipality first');
                                barangaySelect.disabled = true;

                                const path = parentCode
                                    ? `provinces/${encodeURIComponent(parentCode)}/cities-municipalities`
                                    : `regions/${encodeURIComponent(regionCode)}/cities-municipalities`;

                                try {
                                    const cities = await fetchAddressOptions(path);

                                    if (requestId !== cityRequestId) {
                                        return;
                                    }

                                    setAddressOptions(citySelect, 'Select city/municipality', cities);
                                    citySelect.disabled = false;

                                    if (selectedCity && cities.some((item) => item.code === selectedCity)) {
                                        citySelect.value = selectedCity;
                                        await loadBarangays(selectedCity, selectedBarangay);
                                    }
                                } catch (error) {
                                    if (requestId === cityRequestId) {
                                        setAddressOptions(citySelect, 'Unable to load cities/municipalities');
                                        citySelect.disabled = true;
                                        console.error(error);
                                    }
                                }
                            }

                            async function loadProvinces(regionCode, selectedProvince = '', selectedCity = '', selectedBarangay = '') {
                                const requestId = ++regionRequestId;
                                clearCityAndBarangay();
                                setAddressOptions(provinceSelect, 'Loading provinces...');
                                provinceSelect.disabled = true;
                                provinceSelect.required = true;

                                if (!regionCode) {
                                    setAddressOptions(provinceSelect, 'Select region first');
                                    return;
                                }

                                try {
                                    const provinces = await fetchAddressOptions(
                                        `regions/${encodeURIComponent(regionCode)}/provinces`
                                    );

                                    if (requestId !== regionRequestId) {
                                        return;
                                    }

                                    if (provinces.length === 0) {
                                        setAddressOptions(provinceSelect, 'Not applicable for this region');
                                        provinceSelect.required = false;
                                        await loadCities('', regionCode, selectedCity, selectedBarangay);
                                        return;
                                    }

                                    setAddressOptions(provinceSelect, 'Select province', provinces);
                                    provinceSelect.disabled = false;

                                    if (selectedProvince && provinces.some((item) => item.code === selectedProvince)) {
                                        provinceSelect.value = selectedProvince;
                                        await loadCities(selectedProvince, '', selectedCity, selectedBarangay);
                                    }
                                } catch (error) {
                                    if (requestId === regionRequestId) {
                                        setAddressOptions(provinceSelect, 'Unable to load provinces');
                                        provinceSelect.disabled = true;
                                        console.error(error);
                                    }
                                }
                            }

                            async function loadRegions(selectedRegion = '') {
                                const requestId = ++regionRequestId;
                                clearCityAndBarangay();
                                setAddressOptions(provinceSelect, 'Select region first');
                                provinceSelect.disabled = true;
                                setAddressOptions(regionSelect, 'Loading regions...');
                                regionSelect.disabled = true;

                                try {
                                    const regions = await fetchAddressOptions('regions');

                                    if (requestId !== regionRequestId) {
                                        return;
                                    }

                                    setAddressOptions(regionSelect, 'Select region', regions);
                                    regionSelect.disabled = false;

                                    if (selectedRegion && regions.some((item) => item.code === selectedRegion)) {
                                        regionSelect.value = selectedRegion;
                                        await loadProvinces(
                                            selectedRegion,
                                            previousAddress.province,
                                            previousAddress.city,
                                            previousAddress.barangay
                                        );
                                    }
                                } catch (error) {
                                    if (requestId === regionRequestId) {
                                        setAddressOptions(regionSelect, 'Unable to load regions');
                                        regionSelect.disabled = true;
                                        console.error(error);
                                    }
                                }
                            }

                            regionSelect.addEventListener('change', () => {
                                loadProvinces(regionSelect.value);
                            });

                            provinceSelect.addEventListener('change', () => {
                                if (provinceSelect.value) {
                                    loadCities(provinceSelect.value);
                                } else {
                                    clearCityAndBarangay();
                                }
                            });

                            citySelect.addEventListener('change', () => {
                                if (citySelect.value) {
                                    loadBarangays(citySelect.value);
                                } else {
                                    barangayRequestId++;
                                    setAddressOptions(barangaySelect, 'Select city/municipality first');
                                    barangaySelect.disabled = true;
                                }
                            });

                            loadRegions(previousAddress.region);
                        </script>
                        @endpush

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Postal Code</label>
                            <input
                                type="text"
                                name="postal_code"
                                value="{{ old('postal_code') }}"
                                required
                                inputmode="numeric"
                                maxlength="4"
                                pattern="[0-9]{4}"
                                aria-invalid="{{ $errors->has('postal_code') ? 'true' : 'false' }}"
                                placeholder="4-digit postal code"
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                            >
                            @error('postal_code')
                                <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                </section>

                <!-- SERVICE AREAS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
                    <div class="mb-6">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">
                                <i data-lucide="map" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Logistics Details</h2>
                                <p class="mt-1 text-sm text-gray-500">List the locations your company serves.</p>
                            </div>
                        </div>
                    </div>
                    <label class="mb-2 block text-sm font-medium text-gray-700">Service Areas</label>
                    <textarea
                        name="service_areas"
                        rows="3"
                        required
                        maxlength="1000"
                        aria-invalid="{{ $errors->has('service_areas') ? 'true' : 'false' }}"
                        placeholder="Enter cities, municipalities, or regions served"
                        class="w-full resize-none rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[#1F6F5B] focus:ring-2 focus:ring-[#1F6F5B]/10"
                    >{{ old('service_areas') }}</textarea>
                    @error('service_areas')
                        <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </section>



                <!-- DOCUMENTS -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">


                    <div class="mb-6">

                        <div class="flex items-center gap-3">

                            <div class="rounded-xl bg-[#E6F4EE] p-2.5 text-[#1F6F5B]">

                                <i data-lucide="file-check-2" class="h-5 w-5"></i>

                            </div>


                            <div>

                                <h2 class="text-lg font-semibold text-gray-900">

                                    Verification Documents

                                </h2>

                                <p class="mt-1 text-sm text-gray-500">

                                    Upload the required documents for verification.

                                </p>

                            </div>

                        </div>

                    </div>



                    <div class="space-y-5">
                        @foreach ([
                            'sec_certificate' => 'SEC Certificate',
                            'bir_form_2303' => 'BIR Form 2303',
                            'business_permit' => 'Mayor’s / Business Permit',
                            'authorized_representative_id' => 'Valid ID of Authorized Representative',
                        ] as $field => $label)
                            <div class="rounded-xl border border-dashed border-gray-300 p-5">
                                <div class="flex items-start gap-4">
                                    <div class="rounded-xl bg-[#E6F4EE] p-3 text-[#1F6F5B]">
                                        <i data-lucide="file-check-2" class="h-5 w-5"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-gray-900">{{ $label }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Upload a JPG, PNG, or PDF file no larger than 5 MB.</p>
                                        <input
                                            type="file"
                                            name="{{ $field }}"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            required
                                            aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}"
                                            class="mt-4 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-[#E6F4EE] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#1F6F5B] hover:file:bg-[#DDF3EC]"
                                        >
                                        @error($field)
                                            <p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </section>



                <!-- AGREEMENT -->
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">

                    <label class="flex cursor-pointer items-start gap-3">

                        <input
                            type="checkbox"
                            name="terms"
                            value="1"
                            required
                            aria-invalid="{{ $errors->has('terms') ? 'true' : 'false' }}"
                            class="mt-1 h-4 w-4 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]"
                        >


                        <span class="text-sm leading-6 text-gray-600">

                            I confirm that the information and documents provided are accurate,
                            and I agree to the SUKI SHOP Terms and Privacy Policy.

                        </span>

                    </label>
                    @error('terms')
                        <p class="mt-2 text-xs text-red-600" role="alert">{{ $message }}</p>
                    @enderror

                </section>


            </div>



            <!-- RIGHT SIDEBAR -->
            <aside class="lg:sticky lg:top-6 lg:self-start">

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">


                    <!-- LOGISTICS ICON -->
                    <div class="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E6F4EE] text-[#1F6F5B]">

                        <i data-lucide="warehouse" class="h-6 w-6"></i>

                    </div>


                    <h2 class="font-semibold text-gray-900">

                        Join SUKI SHOP Logistics

                    </h2>


                    <p class="mt-1 text-sm leading-5 text-gray-500">

                        Complete your registration to apply as an official
                        SUKI SHOP Logistics or Sorting Center partner.

                    </p>



                    <!-- STEPS -->
                    <div class="my-6 space-y-5">


                        <!-- STEP 1 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#1F6F5B] text-xs font-semibold text-white">

                                1

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Submit Registration

                                </p>

                                <p class="text-xs text-gray-500">

                                    Complete your business information.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 2 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">

                                2

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Admin Verification

                                </p>

                                <p class="text-xs text-gray-500">

                                    SUKI SHOP verifies your documents and registration.

                                </p>

                            </div>

                        </div>



                        <!-- STEP 3 -->
                        <div class="flex items-start gap-3">

                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">

                                3

                            </div>


                            <div>

                                <p class="text-sm font-medium text-gray-900">

                                    Logistics Access

                                </p>

                                <p class="text-xs text-gray-500">

                                    Approved partners can access the logistics system.

                                </p>

                            </div>

                        </div>


                    </div>



                    <!-- INFO BOX -->
                    <div class="mb-5 rounded-xl bg-[#EEF8F3] p-4">

                        <div class="flex items-start gap-3">

                            <i
                                data-lucide="shield-check"
                                class="mt-0.5 h-5 w-5 shrink-0 text-[#1F6F5B]"
                            ></i>


                            <p class="text-xs leading-5 text-[#155244]">

                                Your registration will be reviewed by the SUKI SHOP
                                administrator before access to the Logistics
                                Management System is granted.

                            </p>

                        </div>

                    </div>



                    <!-- SUBMIT -->
                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#1F6F5B] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#155244] active:scale-[0.99]"
                    >

                        <i data-lucide="send" class="h-4 w-4"></i>

                        Submit Registration

                    </button>



                    <!-- BACK -->
                    <a
                        href="{{ route('buyer.home') }}"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50"
                    >

                        <i data-lucide="arrow-left" class="h-4 w-4"></i>

                        Back to SUKI SHOP

                    </a>


                </div>

            </aside>


        </form>

    </main>

</div>



@endsection