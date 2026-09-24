@extends('layouts.seller')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')

@section('content')

@php

    $productId = request()->route('product');

    $variationNames = old(
        'variation_name',
        $product['variation_names'] ?? ['']
    );

    $variationValues = old(
        'variation_value',
        $product['variation_values'] ?? ['']
    );

    if (!is_array($variationNames) || empty($variationNames)) {
        $variationNames = [''];
    }

    if (!is_array($variationValues)) {
        $variationValues = [''];
    }

    $selectedShipping = old(
        'shipping_options',
        $product['shipping_options'] ?? []
    );

    if (!is_array($selectedShipping)) {
        $selectedShipping = [];
    }

@endphp


{{-- =========================================================
    INTRO
========================================================= --}}

<div
    class="mb-7
           flex flex-col gap-4
           sm:flex-row
           sm:items-end
           sm:justify-between"
>

    <div>

        {{-- BREADCRUMB --}}
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
                Edit Product
            </span>

        </div>


        <h2
            class="text-2xl
                   font-semibold
                   tracking-[-0.04em]
                   text-[#24312C]
                   sm:text-[28px]"
        >
            Edit Product
        </h2>


        <p
            class="mt-1.5
                   max-w-2xl
                   text-sm
                   leading-6
                   text-[#728078]"
        >
            Update product information, pricing,
            inventory, and listing availability.
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
    VALIDATION
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
    action="{{ route('seller.products.update', $productId) }}"
    method="POST"
    id="editProductForm"
>

    @csrf
    @method('PUT')


    <div
        class="grid grid-cols-1
               gap-6
               xl:grid-cols-[minmax(0,1fr)_320px]"
    >


        {{-- =====================================================
            LEFT SIDE
        ====================================================== --}}

        <div class="space-y-6">


            {{-- =================================================
                CURRENT PRODUCT
            ================================================== --}}

            <section
                class="overflow-hidden
                       rounded-2xl
                       border border-[#DDE6E1]
                       bg-[#173F35]
                       text-white"
            >

                <div
                    class="flex flex-col gap-5
                           p-5
                           sm:flex-row
                           sm:items-center"
                >

                    {{-- PRODUCT IMAGE --}}
                    <div
                        class="flex h-20 w-20
                               shrink-0
                               items-center justify-center
                               overflow-hidden
                               rounded-2xl
                               border border-white/10
                               bg-white/10"
                    >

                        @if(!empty($product['image']))

                            <img
                                src="{{ $product['image'] }}"
                                alt="{{ $product['name'] ?? 'Product' }}"
                                class="h-full w-full object-cover"
                            >

                        @else

                            <i
                                data-lucide="package"
                                class="h-7 w-7 text-white/60"
                            ></i>

                        @endif

                    </div>


                    {{-- DETAILS --}}
                    <div class="min-w-0 flex-1">

                        <p
                            class="text-[9px]
                                   font-semibold
                                   uppercase
                                   tracking-[0.16em]
                                   text-white/45"
                        >
                            Current Listing
                        </p>


                        <h3
                            class="mt-1
                                   truncate
                                   text-lg
                                   font-semibold
                                   tracking-[-0.03em]"
                        >
                            {{ $product['name'] ?? 'Product' }}
                        </h3>


                        <div
                            class="mt-3
                                   flex flex-wrap
                                   items-center gap-2"
                        >

                            <span
                                class="rounded-full
                                       bg-white/10
                                       px-2.5 py-1
                                       text-[10px]
                                       font-medium
                                       text-white/70"
                            >
                                {{ $product['category'] ?? 'Uncategorized' }}
                            </span>


                            @if(!empty($product['sku']))

                                <span
                                    class="rounded-full
                                           bg-white/10
                                           px-2.5 py-1
                                           text-[10px]
                                           font-medium
                                           text-white/70"
                                >
                                    SKU:
                                    {{ $product['sku'] }}
                                </span>

                            @endif


                            <span
                                class="
                                    rounded-full
                                    px-2.5 py-1
                                    text-[10px]
                                    font-semibold

                                    {{ ($product['status'] ?? 'active') === 'active'
                                        ? 'bg-[#DDF3EC] text-[#173F35]'
                                        : 'bg-white/10 text-white/65' }}
                                "
                            >
                                {{ ucfirst($product['status'] ?? 'active') }}
                            </span>

                        </div>

                    </div>


                    {{-- PRICE --}}
                    <div class="sm:text-right">

                        <p
                            class="text-[9px]
                                   font-semibold
                                   uppercase
                                   tracking-[0.12em]
                                   text-white/40"
                        >
                            Current Price
                        </p>

                        <p
                            class="mt-1
                                   text-xl
                                   font-semibold
                                   tracking-[-0.03em]"
                        >
                            ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                        </p>

                    </div>

                </div>

            </section>


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
                            data-lucide="file-pen-line"
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
                            Update the information customers see.
                        </p>

                    </div>

                </div>


                <div class="space-y-5 p-5">


                    {{-- NAME --}}
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
                            value="{{ old('name', $product['name'] ?? '') }}"
                            maxlength="150"
                            required
                            placeholder="Enter product name"
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


                    {{-- CATEGORY / BRAND --}}
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
                                            {{ old(
                                                'category',
                                                $product['category'] ?? ''
                                            ) === $category ? 'selected' : '' }}
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
                                value="{{ old(
                                    'brand',
                                    $product['brand'] ?? ''
                                ) }}"
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
                                Product Description

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
                            placeholder="Describe your product..."
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
                        >{{ old(
                            'description',
                            $product['description'] ?? ''
                        ) }}</textarea>

                    </div>

                </div>

            </section>


            {{-- =================================================
                PRICE & INVENTORY
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
                            Update price, stock, and SKU.
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
                                value="{{ old(
                                    'price',
                                    $product['price'] ?? 0
                                ) }}"
                                min="0"
                                step="0.01"
                                required
                                class="h-11 w-full
                                       rounded-xl
                                       border border-[#DDE6E1]
                                       bg-white
                                       pl-9 pr-4
                                       text-sm
                                       text-[#34483F]
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
                            value="{{ old(
                                'stock',
                                $product['stock'] ?? 0
                            ) }}"
                            min="0"
                            required
                            class="h-11 w-full
                                   rounded-xl
                                   border border-[#DDE6E1]
                                   bg-white
                                   px-4
                                   text-sm
                                   text-[#34483F]
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
                            value="{{ old(
                                'sku',
                                $product['sku'] ?? ''
                            ) }}"
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
                                Manage size, color, or other options.
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

                        @foreach($variationNames as $index => $variationName)

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
                                    value="{{ $variationValues[$index] ?? '' }}"
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
                SHIPPING
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
                            Shipping Information
                        </h3>

                        <p
                            class="mt-0.5
                                   text-[11px]
                                   text-[#7C8983]"
                        >
                            Update package dimensions and fulfillment options.
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
                                value="{{ old(
                                    'weight',
                                    $product['weight'] ?? ''
                                ) }}"
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
                                        value="{{ old(
                                            'length',
                                            $product['length'] ?? ''
                                        ) }}"
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
                                        value="{{ old(
                                            'width',
                                            $product['width'] ?? ''
                                        ) }}"
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
                                        value="{{ old(
                                            'height',
                                            $product['height'] ?? ''
                                        ) }}"
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


                    {{-- DELIVERY OPTIONS --}}
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


                            {{-- RIDER --}}
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
                                    {{ in_array(
                                        'suki_rider',
                                        $selectedShipping
                                    ) ? 'checked' : '' }}
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
                                        Fulfilled through the SUKI rider network.
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
                                    {{ in_array(
                                        'logistics_partner',
                                        $selectedShipping
                                    ) ? 'checked' : '' }}
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
                                        Fulfilled through supported logistics partners.
                                    </p>

                                </div>

                            </label>

                        </div>

                    </div>

                </div>

            </section>

        </div>


        {{-- =====================================================
            RIGHT SIDE
        ====================================================== --}}

        <aside>

            <section
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
                        Manage visibility and save updates.
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

                                <option
                                    value="active"
                                    {{ old(
                                        'status',
                                        $product['status'] ?? 'active'
                                    ) === 'active'
                                        ? 'selected'
                                        : '' }}
                                >
                                    Active
                                </option>


                                <option
                                    value="inactive"
                                    {{ old(
                                        'status',
                                        $product['status'] ?? ''
                                    ) === 'inactive'
                                        ? 'selected'
                                        : '' }}
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
                            Active products remain visible to buyers.
                        </p>

                    </div>


                    <div class="border-t border-[#EDF1EF]"></div>


                    {{-- PRODUCT INFO --}}
                    <div>

                        <p
                            class="text-[10px]
                                   font-semibold
                                   uppercase
                                   tracking-[0.12em]
                                   text-[#839189]"
                        >
                            Listing Information
                        </p>


                        <div class="mt-4 space-y-3">


                            <div
                                class="flex items-center
                                       justify-between gap-4"
                            >

                                <span
                                    class="text-[10px]
                                           text-[#849089]"
                                >
                                    Current stock
                                </span>

                                <span
                                    class="text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ $product['stock'] ?? 0 }}
                                </span>

                            </div>


                            <div
                                class="flex items-center
                                       justify-between gap-4"
                            >

                                <span
                                    class="text-[10px]
                                           text-[#849089]"
                                >
                                    Category
                                </span>

                                <span
                                    class="max-w-[150px]
                                           truncate
                                           text-xs
                                           font-semibold
                                           text-[#34483F]"
                                >
                                    {{ $product['category'] ?? '—' }}
                                </span>

                            </div>


                            @if(!empty($product['updated_at']))

                                <div
                                    class="flex items-center
                                           justify-between gap-4"
                                >

                                    <span
                                        class="text-[10px]
                                               text-[#849089]"
                                    >
                                        Last updated
                                    </span>

                                    <span
                                        class="text-right
                                               text-[10px]
                                               font-medium
                                               text-[#52635B]"
                                    >
                                        {{ \Carbon\Carbon::parse(
                                            $product['updated_at']
                                        )->format('M d, Y') }}
                                    </span>

                                </div>

                            @endif

                        </div>

                    </div>


                    <div class="border-t border-[#EDF1EF]"></div>


                    {{-- ACTIONS --}}
                    <div class="space-y-2">

                        <button
                            type="submit"
                            id="saveProductButton"
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
                                data-lucide="save"
                                class="h-4 w-4"
                            ></i>

                            Save Changes

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

            </section>

        </aside>

    </div>

</form>


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        /* =====================================================
           DESCRIPTION COUNTER
        ====================================================== */

        const description =
            document.getElementById('description');

        const descriptionCount =
            document.getElementById(
                'descriptionCount'
            );


        function updateDescriptionCount() {

            if (
                !description ||
                !descriptionCount
            ) {
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


        /* =====================================================
           VARIATIONS
        ====================================================== */

        const variationList =
            document.getElementById(
                'variationList'
            );

        const addVariation =
            document.getElementById(
                'addVariation'
            );


        function refreshIcons() {

            if (
                typeof lucide !== 'undefined' &&
                typeof lucide.createIcons ===
                    'function'
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
                    class="h-10 w-full rounded-xl
                           border border-[#DDE6E1]
                           bg-white px-3
                           text-xs text-[#34483F]
                           placeholder:text-[#9AA69F]
                           focus:border-[#1F6F5B]
                           focus:ring-4
                           focus:ring-[#DDF3EC]/70"
                >

                <input
                    type="text"
                    name="variation_value[]"
                    placeholder="Value (e.g. Medium)"
                    class="h-10 w-full rounded-xl
                           border border-[#DDE6E1]
                           bg-white px-3
                           text-xs text-[#34483F]
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
                           items-center
                           justify-center
                           rounded-xl
                           border border-[#E1E8E4]
                           bg-white px-3
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
                        .forEach(
                            function (input) {

                                input.value = '';

                            }
                        );

                    return;

                }


                button
                    .closest('.variation-row')
                    ?.remove();

            }
        );


        /* =====================================================
           SUBMIT PROTECTION
        ====================================================== */

        const form =
            document.getElementById(
                'editProductForm'
            );

        const saveButton =
            document.getElementById(
                'saveProductButton'
            );


        form?.addEventListener(
            'submit',
            function () {

                if (!saveButton) {
                    return;
                }


                saveButton.disabled = true;


                saveButton.innerHTML = `

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
                            d="M4 12a8 8 0 018-8v4a4
                               4 0 00-4 4H4z"
                        ></path>

                    </svg>

                    Saving Changes

                `;

            }
        );


        refreshIcons();

    }
);

</script>

@endpush

@endsection