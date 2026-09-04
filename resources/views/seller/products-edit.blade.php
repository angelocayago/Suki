@extends('layouts.app')

@section('content')

@php
    $productId = request()->route('product');

    $products = session('seller_products', []);

    $product = $products[$productId] ?? null;

    if (!$product) {
        abort(404);
    }
@endphp


<div class="min-h-screen bg-[#F8FAF8]">

    {{-- SELLER HEADER --}}
    <div class="bg-white border-b border-gray-200">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="h-20 flex items-center justify-between">

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
                    class="flex items-center gap-2
                           px-4 py-2 rounded-lg
                           border border-gray-200
                           text-sm font-medium text-gray-600
                           hover:bg-gray-50 transition"
                >
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>

                    <span class="hidden sm:inline">
                        Back to Products
                    </span>
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
                Edit Product
            </span>

        </div>


        {{-- TITLE --}}
        <div class="mb-6">

            <h2 class="text-2xl font-semibold text-gray-900">
                Edit Product
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Update your product information, pricing, and inventory.
            </p>

        </div>


        {{-- SUCCESS --}}
        @if(session('success'))

            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4">

                <div class="flex items-center gap-3">

                    <i
                        data-lucide="check-circle"
                        class="w-5 h-5 text-green-600"
                    ></i>

                    <p class="text-sm font-medium text-green-800">
                        {{ session('success') }}
                    </p>

                </div>

            </div>

        @endif


        {{-- ERRORS --}}
        @if($errors->any())

            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4">

                <div class="flex items-start gap-3">

                    <i
                        data-lucide="alert-circle"
                        class="w-5 h-5 text-red-600"
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
            action="{{ route('seller.products.update', $productId) }}"
            method="POST"
            id="editProductForm"
        >

            @csrf
            @method('PUT')


            {{-- BASIC INFORMATION --}}
            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Basic Information
                    </h3>

                    <p class="mt-0.5 text-xs text-gray-500">
                        Update the main details of your product.
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
                            value="{{ old('name', $product['name'] ?? '') }}"
                            required
                            maxlength="150"
                            class="w-full h-11 px-4 rounded-lg
                                   border border-gray-300
                                   text-sm
                                   focus:outline-none
                                   focus:ring-2
                                   focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]"
                        >

                    </div>


                    {{-- CATEGORY / BRAND --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


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
                                       bg-white text-sm
                                       focus:outline-none
                                       focus:ring-2
                                       focus:ring-[#1F6F5B]/20
                                       focus:border-[#1F6F5B]"
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
                                        {{ old('category', $product['category'] ?? '') === $category ? 'selected' : '' }}
                                    >
                                        {{ $category }}
                                    </option>

                                @endforeach

                            </select>

                        </div>


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
                                value="{{ old('brand', $product['brand'] ?? '') }}"
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
                            class="w-full px-4 py-3 rounded-lg
                                   border border-gray-300
                                   text-sm resize-none
                                   focus:outline-none
                                   focus:ring-2
                                   focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]"
                        >{{ old('description', $product['description'] ?? '') }}</textarea>

                        <div class="mt-1 text-right">

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


            {{-- PRICE & INVENTORY --}}
            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Price & Inventory
                    </h3>

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
                                    value="{{ old('price', $product['price'] ?? 0) }}"
                                    min="0"
                                    step="0.01"
                                    required
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
                                value="{{ old('stock', $product['stock'] ?? 0) }}"
                                min="0"
                                required
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
                                value="{{ old('sku', $product['sku'] ?? '') }}"
                                maxlength="50"
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


            {{-- SHIPPING --}}
            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Shipping Information
                    </h3>

                </div>


                <div class="p-5">

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">


                        <div>

                            <label
                                for="weight"
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Weight (kg)
                            </label>

                            <input
                                type="number"
                                id="weight"
                                name="weight"
                                value="{{ old('weight', $product['weight'] ?? '') }}"
                                min="0"
                                step="0.01"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:border-[#1F6F5B]"
                            >

                        </div>


                        <div>

                            <label
                                for="length"
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Length (cm)
                            </label>

                            <input
                                type="number"
                                id="length"
                                name="length"
                                value="{{ old('length', $product['length'] ?? '') }}"
                                min="0"
                                step="0.01"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:border-[#1F6F5B]"
                            >

                        </div>


                        <div>

                            <label
                                for="width"
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Width (cm)
                            </label>

                            <input
                                type="number"
                                id="width"
                                name="width"
                                value="{{ old('width', $product['width'] ?? '') }}"
                                min="0"
                                step="0.01"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:border-[#1F6F5B]"
                            >

                        </div>


                        <div>

                            <label
                                for="height"
                                class="block text-xs font-medium text-gray-600 mb-1.5"
                            >
                                Height (cm)
                            </label>

                            <input
                                type="number"
                                id="height"
                                name="height"
                                value="{{ old('height', $product['height'] ?? '') }}"
                                min="0"
                                step="0.01"
                                class="w-full h-10 px-3 rounded-lg
                                       border border-gray-300
                                       text-sm
                                       focus:outline-none
                                       focus:border-[#1F6F5B]"
                            >

                        </div>

                    </div>

                </div>

            </div>


            {{-- STATUS --}}
            <div class="bg-white border border-gray-200 rounded-xl mb-6">

                <div class="px-5 py-4 border-b border-gray-100">

                    <h3 class="text-sm font-semibold text-gray-900">
                        Listing Status
                    </h3>

                </div>


                <div class="p-5">

                    <select
                        id="status"
                        name="status"
                        class="w-full sm:max-w-sm h-11 px-3 rounded-lg
                               border border-gray-300
                               bg-white text-sm
                               focus:outline-none
                               focus:ring-2
                               focus:ring-[#1F6F5B]/20
                               focus:border-[#1F6F5B]"
                    >

                        <option
                            value="active"
                            {{ old('status', $product['status'] ?? 'active') === 'active' ? 'selected' : '' }}
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            {{ old('status', $product['status'] ?? '') === 'inactive' ? 'selected' : '' }}
                        >
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            {{-- ACTIONS --}}
            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">

                <a
                    href="{{ route('seller.products') }}"
                    class="px-5 py-2.5 rounded-lg
                           border border-gray-200
                           bg-white
                           text-sm font-medium
                           text-gray-600
                           text-center
                           hover:bg-gray-50"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-lg
                           bg-[#1F6F5B]
                           text-white
                           text-sm font-medium
                           hover:bg-[#155244]
                           flex items-center justify-center gap-2"
                >

                    <i data-lucide="save" class="w-4 h-4"></i>

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const description =
        document.getElementById('description');

    const counter =
        document.getElementById('descriptionCount');


    function updateCounter() {

        if (!description || !counter) {
            return;
        }

        counter.textContent =
            `${description.value.length} / 3000`;

    }


    if (description) {

        description.addEventListener(
            'input',
            updateCounter
        );

        updateCounter();

    }


    const form =
        document.getElementById('editProductForm');


    if (form) {

        form.addEventListener(
            'submit',
            function () {

                const button =
                    form.querySelector(
                        'button[type="submit"]'
                    );

                if (!button) {
                    return;
                }

                button.disabled = true;

                button.classList.add(
                    'opacity-70',
                    'cursor-not-allowed'
                );

            }
        );

    }


    if (
        typeof lucide !== 'undefined' &&
        typeof lucide.createIcons === 'function'
    ) {

        lucide.createIcons();

    }

});

</script>

@endpush

@endsection