@extends('layouts.seller')

@section('title', 'Add Product')
@section('page-title', 'Add Product')

@section('content')

@php
    $oldVariationNames = old('variation_name', ['']);
    $oldVariationValues = old('variation_value', ['']);

    $selectedShipping = old('shipping_options', [
        'suki_rider',
        'logistics_partner'
    ]);
@endphp


{{-- =========================================================
    BREADCRUMB + INTRO
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        <div
            class="mb-2
                   flex items-center gap-2
                   text-[11px]
                   font-medium
                   text-[#8A9791]"
        >

            <a
                href="{{ route('seller.dashboard') }}"
                class="transition hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>

            <a
                href="{{ route('seller.products') }}"
                class="transition hover:text-[#1F6F5B]"
            >
                Products
            </a>

            <i
                data-lucide="chevron-right"
                class="h-3 w-3"
            ></i>

            <span class="text-[#52635B]">
                Add Product
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Add New Product
        </h2>

        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Create a complete product listing for your SUKI SHOP store.
        </p>

    </div>


    <a
        href="{{ route('seller.products') }}"
        class="inline-flex h-10
               items-center justify-center gap-2
               self-start
               rounded-xl
               border border-[#DDE6E1]
               bg-white
               px-4
               text-xs
               font-semibold
               text-[#52635B]
               transition
               hover:border-[#BFD2C9]
               hover:bg-[#F5F8F6]
               hover:text-[#173F35]
               sm:self-auto"
    >

        <i
            data-lucide="arrow-left"
            class="h-4 w-4"
        ></i>

        Back to Products

    </a>

</div>


{{-- =========================================================
    VALIDATION ERRORS
========================================================= --}}

@if($errors->any())

    <div
        class="mb-6
               rounded-2xl
               border border-red-200
               bg-red-50
               p-4"
    >

        <div class="flex items-start gap-3">

            <div
                class="flex h-9 w-9
                       shrink-0
                       items-center justify-center
                       rounded-xl
                       bg-white"
            >

                <i
                    data-lucide="triangle-alert"
                    class="h-4 w-4 text-red-600"
                ></i>

            </div>


            <div>

                <p
                    class="text-xs
                           font-semibold
                           text-red-800"
                >
                    Please review the form
                </p>


                <ul
                    class="mt-2
                           space-y-1
                           text-xs
                           leading-5
                           text-red-700"
                >

                    @foreach($errors->all() as $error)

                        <li>
                            {{ $error }}
                        </li>

                    @endforeach

                </ul>

            </div>

        </div>

    </div>

@endif


{{-- =========================================================
    FORM
========================================================= --}}

<form
    action="{{ route('seller.products.store') }}"
    method="POST"
    enctype="multipart/form-data"
    id="productForm"
>

    @csrf


    <div
        class="grid grid-cols-1
               gap-6
               xl:grid-cols-[minmax(0,1fr)_320px]"
    >


        {{-- =====================================================
            LEFT COLUMN
        ====================================================== --}}

        <div class="space-y-6">


            {{-- =================================================
                BASIC INFORMATION
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white"
            >

                <div
                    class="flex items-center gap-3
                           border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <div
                        class="flex h-9 w-9
                               items-center justify-center
                               rounded-xl
                               bg-[#EEF5F1]
                               text-[#173F35]"
                    >

                        <i
                            data-lucide="package"
                            class="h-4 w-4"
                        ></i>

                    </div>


                    <div>

                        <h3
                            class="text-sm
                                   font-semibold
                                   text-[#24312C]"
                        >
                            Basic Information
                        </h3>

                        <p
                            class="mt-0.5
                                   text-[11px]
                                   text-[#7C8983]"
                        >
                            Main information customers will see.
                        </p>

                    </div>

                </div>


                <div class="space-y-5 p-5">


                    {{-- PRODUCT NAME --}}
                    <div>

                        <label
                            for="name"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Product Name

                            <span class="text-red-500">
                                *
                            </span>
                        </label>


                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            maxlength="150"
                            required
                            placeholder="Enter a clear product name"
                            class="h-11 w-full
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4
                                   text-sm
                                   text-[#34483F]
                                   placeholder:text-[#9AA69F]
                                   focus:border-[#1F6F5B]
                                   focus:ring-4
                                   focus:ring-[#DDF3EC]/70"
                        >


                        <p
                            class="mt-1.5
                                   text-[10px]
                                   leading-5
                                   text-[#8C9992]"
                        >
                            Keep the name simple, descriptive, and easy to search.
                        </p>

                    </div>


                    {{-- CATEGORY + BRAND --}}
                    <div
                        class="grid grid-cols-1
                               gap-5
                               md:grid-cols-2"
                    >


                        {{-- CATEGORY --}}
                        <div>

                            <label
                                for="category"
                                class="mb-2
                                       block
                                       text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                Category

                                <span class="text-red-500">
                                    *
                                </span>
                            </label>


                            <div class="relative">

                                <select
                                    id="category"
                                    name="category"
                                    required
                                    class="h-11 w-full
                                           appearance-none
                                           rounded-xl
                                           border border-[#DDE6E1]
                                           bg-white
                                           px-4 pr-10
                                           text-sm
                                           text-[#52635B]
                                           focus:border-[#1F6F5B]
                                           focus:ring-4
                                           focus:ring-[#DDF3EC]/70"
                                >

                                    <option value="">
                                        Select category
                                    </option>

                                    @foreach([
                                        'Electronics',
                                        'Fashion',
                                        'Beauty',
                                        'Home & Living',
                                        'Groceries',
                                        'Health',
                                        'Sports',
                                        'Toys & Hobbies',
                                        'Automotive',
                                        'Pet Supplies',
                                        'Books & Stationery',
                                        'Others'
                                    ] as $category)

                                        <option
                                            value="{{ $category }}"
                                            {{ old('category') === $category ? 'selected' : '' }}
                                        >
                                            {{ $category }}
                                        </option>

                                    @endforeach

                                </select>


                                <i
                                    data-lucide="chevron-down"
                                    class="pointer-events-none
                                           absolute
                                           right-3.5 top-1/2
                                           h-4 w-4
                                           -translate-y-1/2
                                           text-[#8A9791]"
                                ></i>

                            </div>

                        </div>


                        {{-- BRAND --}}
                        <div>

                            <label
                                for="brand"
                                class="mb-2
                                       block
                                       text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                Brand
                            </label>


                            <input
                                type="text"
                                id="brand"
                                name="brand"
                                value="{{ old('brand') }}"
                                placeholder="Enter brand name"
                                class="h-11 w-full
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       px-4
                                       text-sm
                                       text-[#34483F]
                                       placeholder:text-[#9AA69F]
                                       focus:border-[#1F6F5B]
                                       focus:ring-4
                                       focus:ring-[#DDF3EC]/70"
                            >

                        </div>

                    </div>


                    {{-- DESCRIPTION --}}
                    <div>

                        <div
                            class="mb-2
                                   flex items-center
                                   justify-between gap-4"
                        >

                            <label
                                for="description"
                                class="text-xs
                                       font-semibold
                                       text-[#34483F]"
                            >
                                Description

                                <span class="text-red-500">
                                    *
                                </span>
                            </label>


                            <span
                                id="descriptionCount"
                                class="text-[10px]
                                       font-medium
                                       text-[#97A39D]"
                            >
                                0 / 3000
                            </span>

                        </div>


                        <textarea
                            id="description"
                            name="description"
                            rows="7"
                            maxlength="3000"
                            required
                            placeholder="Describe the product, features, materials, size, usage, and other important details..."
                            class="w-full
                                   resize-none
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4 py-3
                                   text-sm
                                   leading-6
                                   text-[#34483F]
                                   placeholder:text-[#9AA69F]
                                   focus:border-[#1F6F5B]
                                   focus:ring-4
                                   focus:ring-[#DDF3EC]/70"
                        >{{ old('description') }}</textarea>

                    </div>

                </div>

            </section>


            {{-- =================================================
                PRODUCT IMAGES
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white"
            >

                <div
                    class="flex items-center gap-3
                           border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <div
                        class="flex h-9 w-9
                               items-center justify-center
                               rounded-xl
                               bg-[#EEF5F1]
                               text-[#173F35]"
                    >

                        <i
                            data-lucide="images"
                            class="h-4 w-4"
                        ></i>

                    </div>


                    <div>

                        <h3
                            class="text-sm
                                   font-semibold
                                   text-[#24312C]"
                        >
                            Product Images
                        </h3>

                        <p
                            class="mt-0.5
                                   text-[11px]
                                   text-[#7C8983]"
                        >
                            Upload clear images of your product.
                        </p>

                    </div>

                </div>


                <div class="p-5">

                    <label
                        for="images"
                        class="group
                               flex min-h-[170px]
                               cursor-pointer
                               flex-col
                               items-center
                               justify-center
                               rounded-2xl
                               border border-dashed
                               border-[#C8D6CF]
                               bg-[#F8FAF8]
                               px-6 py-8
                               text-center
                               transition
                               hover:border-[#1F6F5B]
                               hover:bg-[#F3F8F5]"
                    >

                        <div
                            class="flex h-11 w-11
                                   items-center justify-center
                                   rounded-xl
                                   bg-white
                                   text-[#1F6F5B]
                                   shadow-sm"
                        >

                            <i
                                data-lucide="image-plus"
                                class="h-5 w-5"
                            ></i>

                        </div>


                        <p
                            class="mt-3
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Choose product images
                        </p>


                        <p
                            class="mt-1
                                   max-w-sm
                                   text-[10px]
                                   leading-5
                                   text-[#8A9791]"
                        >
                            JPEG, PNG, or WebP. You may select multiple files.
                        </p>


                        <span
                            class="mt-3
                                   inline-flex
                                   rounded-lg
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-3 py-1.5
                                   text-[10px]
                                   font-semibold
                                   text-[#52635B]"
                        >
                            Browse files
                        </span>

                    </label>


                    <input
                        type="file"
                        id="images"
                        name="images[]"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="hidden"
                    >


                    <div
                        id="imagePreview"
                        class="mt-4
                               hidden
                               grid grid-cols-2
                               gap-3
                               sm:grid-cols-3
                               lg:grid-cols-4"
                    ></div>

                </div>

            </section>


            {{-- =================================================
                PRICING AND INVENTORY
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white"
            >

                <div
                    class="flex items-center gap-3
                           border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <div
                        class="flex h-9 w-9
                               items-center justify-center
                               rounded-xl
                               bg-[#EEF5F1]
                               text-[#173F35]"
                    >

                        <i
                            data-lucide="badge-dollar-sign"
                            class="h-4 w-4"
                        ></i>

                    </div>


                    <div>

                        <h3
                            class="text-sm
                                   font-semibold
                                   text-[#24312C]"
                        >
                            Pricing & Inventory
                        </h3>

                        <p
                            class="mt-0.5
                                   text-[11px]
                                   text-[#7C8983]"
                        >
                            Set the selling price and available quantity.
                        </p>

                    </div>

                </div>


                <div
                    class="grid grid-cols-1
                           gap-5
                           p-5
                           md:grid-cols-3"
                >


                    {{-- PRICE --}}
                    <div>

                        <label
                            for="price"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Price

                            <span class="text-red-500">
                                *
                            </span>
                        </label>


                        <div class="relative">

                            <span
                                class="absolute
                                       left-4 top-1/2
                                       -translate-y-1/2
                                       text-sm
                                       font-medium
                                       text-[#728078]"
                            >
                                ₱
                            </span>


                            <input
                                type="number"
                                id="price"
                                name="price"
                                value="{{ old('price') }}"
                                min="0"
                                step="0.01"
                                required
                                placeholder="0.00"
                                class="h-11 w-full
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       pl-9 pr-4
                                       text-sm
                                       text-[#34483F]
                                       placeholder:text-[#9AA69F]
                                       focus:border-[#1F6F5B]
                                       focus:ring-4
                                       focus:ring-[#DDF3EC]/70"
                            >

                        </div>

                    </div>


                    {{-- STOCK --}}
                    <div>

                        <label
                            for="stock"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Stock

                            <span class="text-red-500">
                                *
                            </span>
                        </label>


                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            value="{{ old('stock', 0) }}"
                            min="0"
                            required
                            placeholder="0"
                            class="h-11 w-full
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4
                                   text-sm
                                   text-[#34483F]
                                   placeholder:text-[#9AA69F]
                                   focus:border-[#1F6F5B]
                                   focus:ring-4
                                   focus:ring-[#DDF3EC]/70"
                        >

                    </div>


                    {{-- SKU --}}
                    <div>

                        <label
                            for="sku"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            SKU
                        </label>


                        <input
                            type="text"
                            id="sku"
                            name="sku"
                            value="{{ old('sku') }}"
                            maxlength="50"
                            placeholder="e.g. SKU-0001"
                            class="h-11 w-full
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4
                                   text-sm
                                   text-[#34483F]
                                   placeholder:text-[#9AA69F]
                                   focus:border-[#1F6F5B]
                                   focus:ring-4
                                   focus:ring-[#DDF3EC]/70"
                        >

                    </div>

                </div>

            </section>


            {{-- =================================================
                VARIATIONS
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white"
            >

                <div
                    class="flex items-center
                           justify-between gap-4
                           border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <div class="flex items-center gap-3">

                        <div
                            class="flex h-9 w-9
                                   items-center justify-center
                                   rounded-xl
                                   bg-[#EEF5F1]
                                   text-[#173F35]"
                        >

                            <i
                                data-lucide="list-tree"
                                class="h-4 w-4"
                            ></i>

                        </div>


                        <div>

                            <h3
                                class="text-sm
                                       font-semibold
                                       text-[#24312C]"
                            >
                                Product Variations
                            </h3>

                            <p
                                class="mt-0.5
                                       text-[11px]
                                       text-[#7C8983]"
                            >
                                Optional size, color, or other options.
                            </p>

                        </div>

                    </div>


                    <button
                        type="button"
                        id="addVariation"
                        class="inline-flex h-9
                               shrink-0
                               items-center gap-2
                               rounded-xl
                               border border-[#DDE6E1]
                               bg-white
                               px-3
                               text-[10px]
                               font-semibold
                               text-[#52635B]
                               transition
                               hover:bg-[#F5F8F6]
                               hover:text-[#173F35]"
                    >

                        <i
                            data-lucide="plus"
                            class="h-3.5 w-3.5"
                        ></i>

                        Add

                    </button>

                </div>


                <div class="p-5">

                    <div
                        id="variationList"
                        class="space-y-3"
                    >

                        @foreach($oldVariationNames as $index => $variationName)

                            <div
                                class="variation-row
                                       grid grid-cols-1
                                       gap-3
                                       sm:grid-cols-[1fr_1fr_auto]"
                            >

                                <input
                                    type="text"
                                    name="variation_name[]"
                                    value="{{ $variationName }}"
                                    placeholder="Variation name (e.g. Color)"
                                    class="h-10 w-full
                                           rounded-xl
                                           border border-[#DDE6E1]
                                           bg-white
                                           px-3
                                           text-xs
                                           text-[#34483F]
                                           placeholder:text-[#9AA69F]
                                           focus:border-[#1F6F5B]
                                           focus:ring-4
                                           focus:ring-[#DDF3EC]/70"
                                >


                                <input
                                    type="text"
                                    name="variation_value[]"
                                    value="{{ $oldVariationValues[$index] ?? '' }}"
                                    placeholder="Value (e.g. Black)"
                                    class="h-10 w-full
                                           rounded-xl
                                           border border-[#DDE6E1]
                                           bg-white
                                           px-3
                                           text-xs
                                           text-[#34483F]
                                           placeholder:text-[#9AA69F]
                                           focus:border-[#1F6F5B]
                                           focus:ring-4
                                           focus:ring-[#DDF3EC]/70"
                                >


                                <button
                                    type="button"
                                    title="Remove variation"
                                    class="remove-variation
                                           flex h-10
                                           items-center justify-center
                                           rounded-xl
                                           border border-[#E1E8E4]
                                           bg-white
                                           px-3
                                           text-[#89968F]
                                           transition
                                           hover:border-red-200
                                           hover:bg-red-50
                                           hover:text-red-600"
                                >

                                    <i
                                        data-lucide="trash-2"
                                        class="h-4 w-4"
                                    ></i>

                                </button>

                            </div>

                        @endforeach

                    </div>

                </div>

            </section>


            {{-- =================================================
                SHIPPING DETAILS
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white"
            >

                <div
                    class="flex items-center gap-3
                           border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <div
                        class="flex h-9 w-9
                               items-center justify-center
                               rounded-xl
                               bg-[#EEF5F1]
                               text-[#173F35]"
                    >

                        <i
                            data-lucide="truck"
                            class="h-4 w-4"
                        ></i>

                    </div>


                    <div>

                        <h3
                            class="text-sm
                                   font-semibold
                                   text-[#24312C]"
                        >
                            Shipping Details
                        </h3>

                        <p
                            class="mt-0.5
                                   text-[11px]
                                   text-[#7C8983]"
                        >
                            Optional package measurements and delivery options.
                        </p>

                    </div>

                </div>


                <div class="space-y-6 p-5">


                    {{-- WEIGHT --}}
                    <div>

                        <label
                            for="weight"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Package Weight
                        </label>


                        <div class="relative sm:max-w-sm">

                            <input
                                type="number"
                                id="weight"
                                name="weight"
                                value="{{ old('weight') }}"
                                min="0"
                                step="0.01"
                                placeholder="0"
                                class="h-11 w-full
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       px-4 pr-14
                                       text-sm
                                       text-[#34483F]
                                       placeholder:text-[#9AA69F]
                                       focus:border-[#1F6F5B]
                                       focus:ring-4
                                       focus:ring-[#DDF3EC]/70"
                            >


                            <span
                                class="absolute
                                       right-4 top-1/2
                                       -translate-y-1/2
                                       text-xs
                                       font-medium
                                       text-[#8A9791]"
                            >
                                kg
                            </span>

                        </div>

                    </div>


                    {{-- DIMENSIONS --}}
                    <div>

                        <p
                            class="mb-3
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Package Dimensions
                        </p>


                        <div
                            class="grid grid-cols-1
                                   gap-4
                                   sm:grid-cols-3"
                        >


                            {{-- LENGTH --}}
                            <div>

                                <label
                                    for="length"
                                    class="mb-1.5
                                           block
                                           text-[10px]
                                           font-medium
                                           text-[#849089]"
                                >
                                    Length
                                </label>


                                <div class="relative">

                                    <input
                                        type="number"
                                        id="length"
                                        name="length"
                                        value="{{ old('length') }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="h-10 w-full
                                               rounded-xl
                                               border border-[#DDE6E1]
                                               bg-white
                                               px-3 pr-12
                                               text-xs
                                               text-[#34483F]
                                               focus:border-[#1F6F5B]
                                               focus:ring-4
                                               focus:ring-[#DDF3EC]/70"
                                    >

                                    <span
                                        class="absolute
                                               right-3 top-1/2
                                               -translate-y-1/2
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        cm
                                    </span>

                                </div>

                            </div>


                            {{-- WIDTH --}}
                            <div>

                                <label
                                    for="width"
                                    class="mb-1.5
                                           block
                                           text-[10px]
                                           font-medium
                                           text-[#849089]"
                                >
                                    Width
                                </label>


                                <div class="relative">

                                    <input
                                        type="number"
                                        id="width"
                                        name="width"
                                        value="{{ old('width') }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="h-10 w-full
                                               rounded-xl
                                               border border-[#DDE6E1]
                                               bg-white
                                               px-3 pr-12
                                               text-xs
                                               text-[#34483F]
                                               focus:border-[#1F6F5B]
                                               focus:ring-4
                                               focus:ring-[#DDF3EC]/70"
                                    >

                                    <span
                                        class="absolute
                                               right-3 top-1/2
                                               -translate-y-1/2
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        cm
                                    </span>

                                </div>

                            </div>


                            {{-- HEIGHT --}}
                            <div>

                                <label
                                    for="height"
                                    class="mb-1.5
                                           block
                                           text-[10px]
                                           font-medium
                                           text-[#849089]"
                                >
                                    Height
                                </label>


                                <div class="relative">

                                    <input
                                        type="number"
                                        id="height"
                                        name="height"
                                        value="{{ old('height') }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="h-10 w-full
                                               rounded-xl
                                               border border-[#DDE6E1]
                                               bg-white
                                               px-3 pr-12
                                               text-xs
                                               text-[#34483F]
                                               focus:border-[#1F6F5B]
                                               focus:ring-4
                                               focus:ring-[#DDF3EC]/70"
                                    >

                                    <span
                                        class="absolute
                                               right-3 top-1/2
                                               -translate-y-1/2
                                               text-[10px]
                                               text-[#8A9791]"
                                    >
                                        cm
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    {{-- SHIPPING OPTIONS --}}
                    <div>

                        <p
                            class="mb-3
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Shipping Options
                        </p>


                        <div
                            class="grid grid-cols-1
                                   gap-3
                                   md:grid-cols-2"
                        >


                            {{-- SUKI RIDER --}}
                            <label
                                class="flex cursor-pointer
                                       items-start gap-3
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-[#FAFCFB]
                                       p-4
                                       transition
                                       hover:border-[#BFD2C9]"
                            >

                                <input
                                    type="checkbox"
                                    name="shipping_options[]"
                                    value="suki_rider"
                                    {{ in_array('suki_rider', $selectedShipping) ? 'checked' : '' }}
                                    class="mt-0.5
                                           h-4 w-4
                                           rounded
                                           border-[#C5D1CB]
                                           text-[#1F6F5B]
                                           focus:ring-[#1F6F5B]"
                                >


                                <div>

                                    <p
                                        class="text-xs
                                               font-semibold
                                               text-[#34483F]"
                                    >
                                        SUKI Rider
                                    </p>

                                    <p
                                        class="mt-1
                                               text-[10px]
                                               leading-5
                                               text-[#849089]"
                                    >
                                        Delivery fulfilled through the SUKI rider network.
                                    </p>

                                </div>

                            </label>


                            {{-- LOGISTICS --}}
                            <label
                                class="flex cursor-pointer
                                       items-start gap-3
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-[#FAFCFB]
                                       p-4
                                       transition
                                       hover:border-[#BFD2C9]"
                            >

                                <input
                                    type="checkbox"
                                    name="shipping_options[]"
                                    value="logistics_partner"
                                    {{ in_array('logistics_partner', $selectedShipping) ? 'checked' : '' }}
                                    class="mt-0.5
                                           h-4 w-4
                                           rounded
                                           border-[#C5D1CB]
                                           text-[#1F6F5B]
                                           focus:ring-[#1F6F5B]"
                                >


                                <div>

                                    <p
                                        class="text-xs
                                               font-semibold
                                               text-[#34483F]"
                                    >
                                        Logistics Partner
                                    </p>

                                    <p
                                        class="mt-1
                                               text-[10px]
                                               leading-5
                                               text-[#849089]"
                                    >
                                        Fulfillment through supported logistics partners.
                                    </p>

                                </div>

                            </label>

                        </div>

                    </div>

                </div>

            </section>

        </div>


        {{-- =====================================================
            RIGHT COLUMN
        ====================================================== --}}

        <aside class="space-y-5">


            {{-- =================================================
                PUBLISH CARD
            ================================================== --}}

            <div
                class="overflow-hidden
                       rounded-2xl
                       border border-[#E1E8E4]
                       bg-white
                       xl:sticky
                       xl:top-[98px]"
            >

                <div
                    class="border-b border-[#EDF1EF]
                           px-5 py-4"
                >

                    <h3
                        class="text-sm
                               font-semibold
                               text-[#24312C]"
                    >
                        Listing Settings
                    </h3>

                    <p
                        class="mt-0.5
                               text-[11px]
                               text-[#7C8983]"
                    >
                        Control product visibility.
                    </p>

                </div>


                <div class="space-y-5 p-5">


                    {{-- STATUS --}}
                    <div>

                        <label
                            for="status"
                            class="mb-2
                                   block
                                   text-xs
                                   font-semibold
                                   text-[#34483F]"
                        >
                            Product Status
                        </label>


                        <div class="relative">

                            <select
                                id="status"
                                name="status"
                                class="h-11 w-full
                                       appearance-none
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       px-4 pr-10
                                       text-sm
                                       text-[#52635B]
                                       focus:border-[#1F6F5B]
                                       focus:ring-4
                                       focus:ring-[#DDF3EC]/70"
                            >

                                <option
                                    value="active"
                                    {{ old('status', 'active') === 'active' ? 'selected' : '' }}
                                >
                                    Active
                                </option>

                                <option
                                    value="inactive"
                                    {{ old('status') === 'inactive' ? 'selected' : '' }}
                                >
                                    Inactive
                                </option>

                            </select>


                            <i
                                data-lucide="chevron-down"
                                class="pointer-events-none
                                       absolute
                                       right-3.5 top-1/2
                                       h-4 w-4
                                       -translate-y-1/2
                                       text-[#8A9791]"
                            ></i>

                        </div>


                        <p
                            class="mt-2
                                   text-[10px]
                                   leading-5
                                   text-[#8A9791]"
                        >
                            Active products are available for display in your store.
                        </p>

                    </div>


                    {{-- DIVIDER --}}
                    <div class="border-t border-[#EDF1EF]"></div>


                    {{-- CHECKLIST --}}
                    <div>

                        <p
                            class="text-[10px]
                                   font-semibold
                                   uppercase
                                   tracking-[0.12em]
                                   text-[#839189]"
                        >
                            Before publishing
                        </p>


                        <div class="mt-3 space-y-3">


                            <div class="flex items-start gap-2.5">

                                <i
                                    data-lucide="circle-check"
                                    class="mt-0.5
                                           h-4 w-4
                                           shrink-0
                                           text-[#1F6F5B]"
                                ></i>

                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-[#6F7E76]"
                                >
                                    Add a clear and searchable product name.
                                </p>

                            </div>


                            <div class="flex items-start gap-2.5">

                                <i
                                    data-lucide="circle-check"
                                    class="mt-0.5
                                           h-4 w-4
                                           shrink-0
                                           text-[#1F6F5B]"
                                ></i>

                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-[#6F7E76]"
                                >
                                    Verify price and available stock.
                                </p>

                            </div>


                            <div class="flex items-start gap-2.5">

                                <i
                                    data-lucide="circle-check"
                                    class="mt-0.5
                                           h-4 w-4
                                           shrink-0
                                           text-[#1F6F5B]"
                                ></i>

                                <p
                                    class="text-[10px]
                                           leading-5
                                           text-[#6F7E76]"
                                >
                                    Provide an accurate product description.
                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- ACTIONS --}}
                    <div class="space-y-2 pt-1">

                        <button
                            type="submit"
                            id="submitProductButton"
                            class="inline-flex h-11
                                   w-full
                                   items-center
                                   justify-center gap-2
                                   rounded-xl
                                   bg-[#173F35]
                                   px-4
                                   text-xs
                                   font-semibold
                                   text-white
                                   transition
                                   hover:bg-[#1F6F5B]
                                   disabled:cursor-not-allowed
                                   disabled:opacity-60"
                        >

                            <i
                                data-lucide="check"
                                class="h-4 w-4"
                            ></i>

                            Add Product

                        </button>


                        <a
                            href="{{ route('seller.products') }}"
                            class="inline-flex h-10
                                   w-full
                                   items-center
                                   justify-center
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4
                                   text-xs
                                   font-semibold
                                   text-[#52635B]
                                   transition
                                   hover:bg-[#F5F8F6]
                                   hover:text-[#173F35]"
                        >
                            Cancel
                        </a>

                    </div>

                </div>

            </div>

        </aside>

    </div>

</form>


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {


    /* =========================================================
       DESCRIPTION COUNTER
    ========================================================= */

    const description =
        document.getElementById('description');

    const descriptionCount =
        document.getElementById('descriptionCount');


    function updateDescriptionCount() {

        if (!description || !descriptionCount) {
            return;
        }

        descriptionCount.textContent =
            `${description.value.length} / 3000`;

    }


    description?.addEventListener(
        'input',
        updateDescriptionCount
    );

    updateDescriptionCount();


    /* =========================================================
       IMAGE PREVIEW
    ========================================================= */

    const imageInput =
        document.getElementById('images');

    const imagePreview =
        document.getElementById('imagePreview');


    imageInput?.addEventListener(
        'change',
        function () {

            if (!imagePreview) {
                return;
            }


            imagePreview.innerHTML = '';


            const files =
                Array.from(this.files || []);


            if (!files.length) {

                imagePreview.classList.add('hidden');

                return;

            }


            imagePreview.classList.remove('hidden');


            files.forEach(function (file) {

                const reader =
                    new FileReader();


                reader.onload = function (event) {

                    const wrapper =
                        document.createElement('div');


                    wrapper.className =
                        'relative aspect-square overflow-hidden rounded-xl border border-[#E1E8E4] bg-[#F1F4F2]';


                    const image =
                        document.createElement('img');


                    image.src =
                        event.target.result;

                    image.alt =
                        file.name;

                    image.className =
                        'h-full w-full object-cover';


                    wrapper.appendChild(image);

                    imagePreview.appendChild(wrapper);

                };


                reader.readAsDataURL(file);

            });

        }
    );


    /* =========================================================
       VARIATIONS
    ========================================================= */

    const variationList =
        document.getElementById('variationList');

    const addVariation =
        document.getElementById('addVariation');


    function refreshIcons() {

        if (
            typeof lucide !== 'undefined' &&
            typeof lucide.createIcons === 'function'
        ) {
            lucide.createIcons();
        }

    }


    function createVariationRow() {

        if (!variationList) {
            return;
        }


        const row =
            document.createElement('div');


        row.className =
            'variation-row grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto]';


        row.innerHTML = `

            <input
                type="text"
                name="variation_name[]"
                placeholder="Variation name (e.g. Size)"
                class="h-10 w-full rounded-xl border border-[#DDE6E1]
                       bg-white px-3 text-xs text-[#34483F]
                       placeholder:text-[#9AA69F]
                       focus:border-[#1F6F5B]
                       focus:ring-4 focus:ring-[#DDF3EC]/70"
            >

            <input
                type="text"
                name="variation_value[]"
                placeholder="Value (e.g. Medium)"
                class="h-10 w-full rounded-xl border border-[#DDE6E1]
                       bg-white px-3 text-xs text-[#34483F]
                       placeholder:text-[#9AA69F]
                       focus:border-[#1F6F5B]
                       focus:ring-4 focus:ring-[#DDF3EC]/70"
            >

            <button
                type="button"
                title="Remove variation"
                class="remove-variation flex h-10 items-center
                       justify-center rounded-xl
                       border border-[#E1E8E4]
                       bg-white px-3 text-[#89968F]
                       transition hover:border-red-200
                       hover:bg-red-50 hover:text-red-600"
            >
                <i
                    data-lucide="trash-2"
                    class="h-4 w-4"
                ></i>
            </button>

        `;


        variationList.appendChild(row);

        refreshIcons();

    }


    addVariation?.addEventListener(
        'click',
        createVariationRow
    );


    variationList?.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.remove-variation'
                );


            if (!button) {
                return;
            }


            const rows =
                variationList.querySelectorAll(
                    '.variation-row'
                );


            if (rows.length <= 1) {

                rows[0]
                    .querySelectorAll('input')
                    .forEach(function (input) {

                        input.value = '';

                    });

                return;

            }


            button
                .closest('.variation-row')
                ?.remove();

        }
    );


    /* =========================================================
       SUBMIT PROTECTION
    ========================================================= */

    const productForm =
        document.getElementById('productForm');

    const submitButton =
        document.getElementById(
            'submitProductButton'
        );


    productForm?.addEventListener(
        'submit',
        function () {

            if (!submitButton) {
                return;
            }


            submitButton.disabled = true;

            submitButton.innerHTML = `

                <svg
                    class="h-4 w-4 animate-spin"
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>

                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4
                           0 00-4 4H4z"
                    ></path>
                </svg>

                Saving Product

            `;

        }
    );


    refreshIcons();

});

</script>

@endpush

@endsection