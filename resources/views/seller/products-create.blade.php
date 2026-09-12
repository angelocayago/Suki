@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-[#F8FAF8]">

    {{-- SELLER HEADER --}}
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="h-20 flex items-center justify-between gap-4">

                <div class="flex items-center gap-4">

                    <div class="w-11 h-11 rounded-xl bg-[#EEF8F3] flex items-center justify-center">
                        <i data-lucide="store" class="w-5 h-5 text-[#1F6F5B]"></i>
                    </div>

                    <div>
                        <p class="text-xs text-gray-500">
                            Seller Centre
                        </p>

                        <h1 class="text-lg font-semibold text-gray-900">
                            Everyday Finds PH
                        </h1>
                    </div>

                </div>

                <a
                    href="{{ route('seller.products') }}"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg
                           border border-gray-200 bg-white
                           text-sm font-medium text-gray-600
                           hover:bg-gray-50 transition"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Back to Products</span>
                </a>

            </div>

        </div>
    </div>


    {{-- MAIN --}}
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- BREADCRUMB --}}
        <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">

            <a
                href="{{ route('seller.dashboard') }}"
                class="hover:text-[#1F6F5B]"
            >
                Dashboard
            </a>

            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>

            <a
                href="{{ route('seller.products') }}"
                class="hover:text-[#1F6F5B]"
            >
                Products
            </a>

            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>

            <span>
                Add Product
            </span>

        </div>


        {{-- TITLE --}}
        <div class="mb-6">

            <h2 class="text-2xl font-semibold text-gray-900">
                Add New Product
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Create a product listing for your SUKI SHOP store.
            </p>

        </div>


        {{-- VALIDATION ERRORS --}}
        @if($errors->any())

            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="alert-circle"
                        class="w-5 h-5 text-red-600 shrink-0 mt-0.5"
                    ></i>

                    <div>

                        <p class="text-sm font-semibold text-red-800">
                            Please check the following:
                        </p>

                        <ul class="mt-2 space-y-1 text-xs text-red-700">

                            @foreach($errors->all() as $error)

                                <li>
                                    • {{ $error }}
                                </li>

                            @endforeach

                        </ul>

                    </div>

                </div>

            </div>

        @endif


        {{-- FORM --}}
        <form
            action="{{ route('seller.products.store') }}"
            method="POST"
            enctype="multipart/form-data"
            id="productForm"
        >

            @csrf


            {{-- ================================================= --}}
            {{-- BASIC INFORMATION --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Basic Information
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Provide the main details of your product.
                    </p>

                </div>


                <div class="p-5 space-y-5">


                    {{-- PRODUCT NAME --}}
                    <div>

                        <label
                            for="name"
                            class="block text-sm font-medium text-gray-700 mb-1.5"
                        >
                            Product Name
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Enter product name"
                            required
                            maxlength="150"
                            class="w-full h-11 px-4 rounded-lg
                                   border border-gray-300
                                   text-sm text-gray-900
                                   placeholder:text-gray-400
                                   focus:outline-none
                                   focus:ring-2
                                   focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]"
                        >

                        <p class="mt-1.5 text-xs text-gray-400">
                            Use a clear and descriptive product name.
                        </p>

                    </div>


                    {{-- CATEGORY + BRAND --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                        {{-- CATEGORY --}}
                        <div>

                            <label
                                for="category"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
                            >
                                Category
                                <span class="text-red-500">*</span>
                            </label>

                            <select
                                id="category"
                                name="category"
                                required
                                class="w-full h-11 px-3 rounded-lg
                                       border border-gray-300
                                       bg-white
                                       text-sm text-gray-700
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                                <option value="">
                                    Select category
                                </option>

                                <option value="Electronics" {{ old('category') === 'Electronics' ? 'selected' : '' }}>
                                    Electronics
                                </option>

                                <option value="Fashion" {{ old('category') === 'Fashion' ? 'selected' : '' }}>
                                    Fashion
                                </option>

                                <option value="Beauty" {{ old('category') === 'Beauty' ? 'selected' : '' }}>
                                    Beauty
                                </option>

                                <option value="Home & Living" {{ old('category') === 'Home & Living' ? 'selected' : '' }}>
                                    Home & Living
                                </option>

                                <option value="Groceries" {{ old('category') === 'Groceries' ? 'selected' : '' }}>
                                    Groceries
                                </option>

                                <option value="Health" {{ old('category') === 'Health' ? 'selected' : '' }}>
                                    Health
                                </option>

                                <option value="Sports" {{ old('category') === 'Sports' ? 'selected' : '' }}>
                                    Sports
                                </option>

                                <option value="Toys & Hobbies" {{ old('category') === 'Toys & Hobbies' ? 'selected' : '' }}>
                                    Toys & Hobbies
                                </option>

                                <option value="Automotive" {{ old('category') === 'Automotive' ? 'selected' : '' }}>
                                    Automotive
                                </option>

                                <option value="Pet Supplies" {{ old('category') === 'Pet Supplies' ? 'selected' : '' }}>
                                    Pet Supplies
                                </option>

                                <option value="Books & Stationery" {{ old('category') === 'Books & Stationery' ? 'selected' : '' }}>
                                    Books & Stationery
                                </option>

                                <option value="Others" {{ old('category') === 'Others' ? 'selected' : '' }}>
                                    Others
                                </option>

                            </select>

                        </div>


                        {{-- BRAND --}}
                        <div>

                            <label
                                for="brand"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
                            >
                                Brand
                            </label>

                            <input
                                type="text"
                                id="brand"
                                name="brand"
                                value="{{ old('brand') }}"
                                placeholder="Enter brand name"
                                class="w-full h-11 px-4 rounded-lg
                                       border border-gray-300
                                       text-sm text-gray-900
                                       placeholder:text-gray-400
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                        </div>

                    </div>


                    {{-- DESCRIPTION --}}
                    <div>

                        <label
                            for="description"
                            class="block text-sm font-medium text-gray-700 mb-1.5"
                        >
                            Product Description
                            <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            required
                            maxlength="3000"
                            placeholder="Describe your product, its features, materials, size, usage, and other important details..."
                            class="w-full px-4 py-3 rounded-lg
                                   border border-gray-300
                                   text-sm text-gray-900
                                   placeholder:text-gray-400
                                   resize-none
                                   focus:outline-none
                                   focus:ring-2
                                   focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]"
                        >{{ old('description') }}</textarea>

                        <div class="mt-1.5 flex justify-between">

                            <p class="text-xs text-gray-400">
                                Give buyers enough information to understand the product.
                            </p>

                            <span
                                id="descriptionCount"
                                class="text-xs text-gray-400"
                            >
                                0 / 3000
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- PRODUCT IMAGES --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Product Images
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Upload clear images of your product.
                    </p>

                </div>


                <div class="p-5">

                    <label
                        for="images"
                        class="block cursor-pointer"
                    >

                        <div
                            class="border-2 border-dashed
                                   border-gray-300
                                   rounded-xl
                                   p-8
                                   text-center
                                   hover:border-[#1F6F5B]
                                   hover:bg-[#F8FAF8]
                                   transition"
                        >

                            <div
                                class="mx-auto w-12 h-12
                                       rounded-xl
                                       bg-[#EEF8F3]
                                       flex items-center justify-center"
                            >

                                <i
                                    data-lucide="image-plus"
                                    class="w-6 h-6 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <p class="mt-4 text-sm font-medium text-gray-900">
                                Click to upload product images
                            </p>

                            <p class="mt-1 text-xs text-gray-500">
                                JPG, JPEG, PNG or WEBP
                            </p>

                            <p class="mt-1 text-xs text-gray-400">
                                You may upload multiple images.
                            </p>

                        </div>

                        <input
                            type="file"
                            id="images"
                            name="images[]"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="hidden"
                        >

                    </label>


                    {{-- IMAGE PREVIEW --}}
                    <div
                        id="imagePreview"
                        class="hidden grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5"
                    ></div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- PRICE & INVENTORY --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Price & Inventory
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Set your product price and available stock.
                    </p>

                </div>


                <div class="p-5">


                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">


                        {{-- PRICE --}}
                        <div>

                            <label
                                for="price"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
                            >
                                Price
                                <span class="text-red-500">*</span>
                            </label>

                            <div class="relative">

                                <span
                                    class="absolute left-3 top-1/2
                                           -translate-y-1/2
                                           text-sm text-gray-500"
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
                                    class="w-full h-11 pl-8 pr-4 rounded-lg
                                           border border-gray-300
                                           text-sm
                                           focus:outline-none
                                           focus:ring-2
                                           focus:ring-[#1F6F5B]/20
                                           focus:border-[#1F6F5B]"
                                >

                            </div>

                        </div>


                        {{-- STOCK --}}
                        <div>

                            <label
                                for="stock"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
                            >
                                Stock
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                type="number"
                                id="stock"
                                name="stock"
                                value="{{ old('stock', 0) }}"
                                min="0"
                                required
                                placeholder="0"
                                class="w-full h-11 px-4 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                        </div>


                        {{-- SKU --}}
                        <div>

                            <label
                                for="sku"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
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
                                class="w-full h-11 px-4 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                        </div>

                    </div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- VARIATIONS --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <div class="flex items-center justify-between gap-4">

                        <div>

                            <h3 class="text-sm font-semibold text-gray-900">
                                Product Variations
                            </h3>

                            <p class="mt-0.5 text-xs text-gray-500">
                                Add options such as size, color, or other variations.
                            </p>

                        </div>

                        <span
                            class="hidden sm:inline-flex
                                   px-2.5 py-1 rounded-full
                                   bg-gray-100 text-gray-500
                                   text-[10px] font-medium"
                        >
                            Optional
                        </span>

                    </div>

                </div>


                <div class="p-5">

                    <div
                        id="variationList"
                        class="space-y-3"
                    >

                        <div class="variation-row grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3">

                            <input
                                type="text"
                                name="variation_name[]"
                                placeholder="Variation name (e.g. Color)"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                            <input
                                type="text"
                                name="variation_value[]"
                                placeholder="Value (e.g. Black)"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
                            >

                            <button
                                type="button"
                                class="remove-variation
                                       h-10 px-3
                                       rounded-lg
                                       border border-gray-200
                                       text-gray-400
                                       hover:text-red-600
                                       hover:bg-red-50
                                       transition"
                            >
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>

                        </div>

                    </div>


                    <button
                        type="button"
                        id="addVariation"
                        class="mt-4 inline-flex items-center gap-2
                               text-sm font-medium
                               text-[#1F6F5B]
                               hover:text-[#155244]"
                    >

                        <i data-lucide="plus-circle" class="w-4 h-4"></i>

                        Add Variation

                    </button>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- SHIPPING --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Shipping Information
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Provide basic package information for fulfillment.
                    </p>

                </div>


                <div class="p-5">


                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">


                        {{-- WEIGHT --}}
                        <div>

                            <label
                                for="weight"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
                            >
                                Weight
                            </label>

                            <div class="relative">

                                <input
                                    type="number"
                                    id="weight"
                                    name="weight"
                                    value="{{ old('weight') }}"
                                    min="0"
                                    step="0.01"
                                    placeholder="0"
                                    class="w-full h-11 px-4 pr-12 rounded-lg
                                           border border-gray-300
                                           text-sm
                                           focus:outline-none
                                           focus:ring-2
                                           focus:ring-[#1F6F5B]/20
                                           focus:border-[#1F6F5B]"
                                >

                                <span
                                    class="absolute right-3 top-1/2
                                           -translate-y-1/2
                                           text-xs text-gray-400"
                                >
                                    kg
                                </span>

                            </div>

                        </div>


                        {{-- LENGTH --}}
                        <div>

                            <label
                                for="length"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
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
                                    class="w-full h-11 px-4 pr-12 rounded-lg
                                           border border-gray-300
                                           text-sm
                                           focus:outline-none
                                           focus:ring-2
                                           focus:ring-[#1F6F5B]/20
                                           focus:border-[#1F6F5B]"
                                >

                                <span
                                    class="absolute right-3 top-1/2
                                           -translate-y-1/2
                                           text-xs text-gray-400"
                                >
                                    cm
                                </span>

                            </div>

                        </div>


                        {{-- WIDTH --}}
                        <div>

                            <label
                                for="width"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
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
                                    class="w-full h-11 px-4 pr-12 rounded-lg
                                           border border-gray-300
                                           text-sm
                                           focus:outline-none
                                           focus:ring-2
                                           focus:ring-[#1F6F5B]/20
                                           focus:border-[#1F6F5B]"
                                >

                                <span
                                    class="absolute right-3 top-1/2
                                           -translate-y-1/2
                                           text-xs text-gray-400"
                                >
                                    cm
                                </span>

                            </div>

                        </div>


                        {{-- HEIGHT --}}
                        <div>

                            <label
                                for="height"
                                class="block text-sm font-medium text-gray-700 mb-1.5"
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
                                    class="w-full h-11 px-4 pr-12 rounded-lg
                                           border border-gray-300
                                           text-sm
                                           focus:outline-none
                                           focus:ring-2
                                           focus:ring-[#1F6F5B]/20
                                           focus:border-[#1F6F5B]"
                                >

                                <span
                                    class="absolute right-3 top-1/2
                                           -translate-y-1/2
                                           text-xs text-gray-400"
                                >
                                    cm
                                </span>

                            </div>

                        </div>

                    </div>


                    {{-- SHIPPING OPTIONS --}}
                    <div class="mt-6">

                        <p class="text-sm font-medium text-gray-700 mb-3">
                            Shipping Options
                        </p>

                        <div class="space-y-3">


                            <label
                                class="flex items-center gap-3
                                       p-3 rounded-lg
                                       border border-gray-200
                                       cursor-pointer
                                       hover:bg-gray-50"
                            >

                                <input
                                    type="checkbox"
                                    name="shipping_options[]"
                                    value="suki_rider"
                                    checked
                                    class="w-4 h-4 rounded
                                           border-gray-300
                                           text-[#1F6F5B]
                                           focus:ring-[#1F6F5B]"
                                >

                                <div>

                                    <p class="text-sm font-medium text-gray-800">
                                        SUKI SHOP Rider
                                    </p>

                                    <p class="text-xs text-gray-500">
                                        Pickup and delivery through SUKI SHOP's rider network.
                                    </p>

                                </div>

                            </label>


                            <label
                                class="flex items-center gap-3
                                       p-3 rounded-lg
                                       border border-gray-200
                                       cursor-pointer
                                       hover:bg-gray-50"
                            >

                                <input
                                    type="checkbox"
                                    name="shipping_options[]"
                                    value="logistics_partner"
                                    checked
                                    class="w-4 h-4 rounded
                                           border-gray-300
                                           text-[#1F6F5B]
                                           focus:ring-[#1F6F5B]"
                                >

                                <div>

                                    <p class="text-sm font-medium text-gray-800">
                                        Logistics Partners
                                    </p>

                                    <p class="text-xs text-gray-500">
                                        Available partner couriers may include J&T, Flash, SPX, and LBC.
                                    </p>

                                </div>

                            </label>

                        </div>

                    </div>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- PRODUCT STATUS --}}
            {{-- ================================================= --}}

            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Listing Status
                    </h3>

                </div>


                <div class="p-5">

                    <label
                        for="status"
                        class="block text-sm font-medium text-gray-700 mb-1.5"
                    >
                        Product Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        class="w-full sm:max-w-sm h-11 px-3 rounded-lg
                               border border-gray-300
                               bg-white
                               text-sm text-gray-700
                               focus:outline-none
                               focus:ring-2
                               focus:ring-[#1F6F5B]/20
                               focus:border-[#1F6F5B]"
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

                    <p class="mt-1.5 text-xs text-gray-400">
                        Active products can be displayed in your store.
                    </p>

                </div>

            </div>


            {{-- ================================================= --}}
            {{-- ACTION BUTTONS --}}
            {{-- ================================================= --}}

            <div
                class="flex flex-col-reverse sm:flex-row
                       sm:items-center sm:justify-end
                       gap-3"
            >

                <a
                    href="{{ route('seller.products') }}"
                    class="w-full sm:w-auto
                           px-5 py-2.5
                           rounded-lg
                           border border-gray-200
                           bg-white
                           text-sm font-medium
                           text-gray-600
                           text-center
                           hover:bg-gray-50
                           transition"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="w-full sm:w-auto
                           px-5 py-2.5
                           rounded-lg
                           bg-[#1F6F5B]
                           text-white
                           text-sm font-medium
                           hover:bg-[#155244]
                           transition
                           flex items-center justify-center gap-2"
                >

                    <i data-lucide="check" class="w-4 h-4"></i>

                    Add Product

                </button>

            </div>

        </form>

    </div>

</div>


{{-- ============================================================= --}}
{{-- PAGE SCRIPT --}}
{{-- ============================================================= --}}

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


    if (description) {

        description.addEventListener(
            'input',
            updateDescriptionCount
        );

        updateDescriptionCount();

    }


    /* =========================================================
       IMAGE PREVIEW
    ========================================================= */

    const imageInput =
        document.getElementById('images');

    const imagePreview =
        document.getElementById('imagePreview');


    if (imageInput && imagePreview) {

        imageInput.addEventListener(
            'change',
            function () {

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
                            'aspect-square rounded-lg overflow-hidden border border-gray-200 bg-gray-100';


                        const image =
                            document.createElement('img');

                        image.src =
                            event.target.result;

                        image.alt =
                            file.name;

                        image.className =
                            'w-full h-full object-cover';


                        wrapper.appendChild(image);

                        imagePreview.appendChild(wrapper);

                    };


                    reader.readAsDataURL(file);

                });

            }
        );

    }


    /* =========================================================
       VARIATIONS
    ========================================================= */

    const variationList =
        document.getElementById('variationList');

    const addVariation =
        document.getElementById('addVariation');


    function refreshIcons() {

        if (
            typeof lucide !== 'undefined'
            && typeof lucide.createIcons === 'function'
        ) {

            lucide.createIcons();

        }

    }


    function addVariationRow() {

        if (!variationList) {
            return;
        }


        const row =
            document.createElement('div');

        row.className =
            'variation-row grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-3';


        row.innerHTML = `

            <input
                type="text"
                name="variation_name[]"
                placeholder="Variation name (e.g. Size)"
                class="w-full h-10 px-3 rounded-lg
                       border border-gray-300
                       text-sm
                       focus:outline-none
                       focus:ring-2
                       focus:ring-[#1F6F5B]/20
                       focus:border-[#1F6F5B]"
            >

            <input
                type="text"
                name="variation_value[]"
                placeholder="Value (e.g. Medium)"
                class="w-full h-10 px-3 rounded-lg
                       border border-gray-300
                       text-sm
                       focus:outline-none
                       focus:ring-2
                       focus:ring-[#1F6F5B]/20
                       focus:border-[#1F6F5B]"
            >

            <button
                type="button"
                class="remove-variation
                       h-10 px-3
                       rounded-lg
                       border border-gray-200
                       text-gray-400
                       hover:text-red-600
                       hover:bg-red-50
                       transition"
            >

                <i data-lucide="trash-2" class="w-4 h-4"></i>

            </button>

        `;


        variationList.appendChild(row);

        refreshIcons();

    }


    if (addVariation) {

        addVariation.addEventListener(
            'click',
            addVariationRow
        );

    }


    if (variationList) {

        variationList.addEventListener(
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
                    .remove();

            }
        );

    }


    /* =========================================================
       FORM SUBMIT PROTECTION
    ========================================================= */

    const productForm =
        document.getElementById('productForm');


    if (productForm) {

        productForm.addEventListener(
            'submit',
            function () {

                const submitButton =
                    productForm.querySelector(
                        'button[type="submit"]'
                    );


                if (!submitButton) {
                    return;
                }


                submitButton.disabled = true;

                submitButton.classList.add(
                    'opacity-70',
                    'cursor-not-allowed'
                );

            }
        );

    }

});

</script>

@endpush

@endsection