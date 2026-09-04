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


                <div class="flex items-center gap-2">

                    <a
                        href="{{ route('buyer.home') }}"
                        class="hidden sm:flex items-center gap-2
                               px-4 py-2 rounded-lg
                               border border-gray-200
                               text-sm font-medium text-gray-600
                               hover:bg-gray-50 transition"
                    >
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        View Store
                    </a>

                </div>

            </div>

        </div>

    </div>


    {{-- MAIN CONTENT --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">


        {{-- PAGE HEADER --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">

            <div>

                <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">

                    <a
                        href="{{ route('seller.dashboard') }}"
                        class="hover:text-[#1F6F5B]"
                    >
                        Dashboard
                    </a>

                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>

                    <span>
                        Products
                    </span>

                </div>

                <h2 class="text-2xl font-semibold text-gray-900">
                    Products
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Manage your store's products and inventory.
                </p>

            </div>


            <a
                href="{{ route('seller.products.create') }}"
                class="inline-flex items-center justify-center gap-2
                       px-4 py-2.5
                       rounded-lg
                       bg-[#1F6F5B]
                       text-white
                       text-sm font-medium
                       hover:bg-[#155244]
                       transition"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                Add Product
            </a>

        </div>


        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))

            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4">

                <div class="flex items-center gap-3">

                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center">

                        <i
                            data-lucide="check-circle"
                            class="w-5 h-5 text-green-600"
                        ></i>

                    </div>

                    <p class="text-sm font-medium text-green-800">
                        {{ session('success') }}
                    </p>

                </div>

            </div>

        @endif


        {{-- PRODUCT STATS --}}
        @php

            $products = session('seller_products', []);

            $totalProducts = count($products);

            $activeProducts = collect($products)
                ->where('status', 'active')
                ->count();

            $inactiveProducts = collect($products)
                ->where('status', 'inactive')
                ->count();

            $lowStockProducts = collect($products)
                ->filter(function ($product) {
                    return (int) ($product['stock'] ?? 0) <= 10;
                })
                ->count();

        @endphp


        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">


            {{-- TOTAL --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-xs text-gray-500">
                            Total Products
                        </p>

                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $totalProducts }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">

                        <i
                            data-lucide="package"
                            class="w-5 h-5 text-gray-600"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- ACTIVE --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-xs text-gray-500">
                            Active
                        </p>

                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $activeProducts }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-[#EEF8F3] flex items-center justify-center">

                        <i
                            data-lucide="check-circle"
                            class="w-5 h-5 text-[#1F6F5B]"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- INACTIVE --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-xs text-gray-500">
                            Inactive
                        </p>

                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $inactiveProducts }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">

                        <i
                            data-lucide="eye-off"
                            class="w-5 h-5 text-gray-500"
                        ></i>

                    </div>

                </div>

            </div>


            {{-- LOW STOCK --}}
            <div class="bg-white border border-gray-200 rounded-xl p-4">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-xs text-gray-500">
                            Low Stock
                        </p>

                        <p class="mt-1 text-2xl font-semibold text-gray-900">
                            {{ $lowStockProducts }}
                        </p>

                    </div>

                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">

                        <i
                            data-lucide="alert-triangle"
                            class="w-5 h-5 text-amber-600"
                        ></i>

                    </div>

                </div>

            </div>

        </div>


        {{-- PRODUCT MANAGEMENT CARD --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">


            {{-- TOOLBAR --}}
            <div class="p-4 border-b border-gray-100">

                <div class="flex flex-col lg:flex-row gap-3">


                    {{-- SEARCH --}}
                    <div class="relative flex-1">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2
                                   -translate-y-1/2
                                   w-4 h-4
                                   text-gray-400"
                        ></i>

                        <input
                            type="text"
                            id="productSearch"
                            placeholder="Search products..."
                            class="w-full h-10 pl-9 pr-4
                                   rounded-lg
                                   border border-gray-300
                                   text-sm
                                   focus:outline-none
                                   focus:ring-2
                                   focus:ring-[#1F6F5B]/20
                                   focus:border-[#1F6F5B]"
                        >

                    </div>


                    {{-- STATUS --}}
                    <select
                        id="statusFilter"
                        class="h-10 px-3
                               rounded-lg
                               border border-gray-300
                               bg-white
                               text-sm text-gray-600
                               focus:outline-none
                               focus:ring-2
                               focus:ring-[#1F6F5B]/20
                               focus:border-[#1F6F5B]"
                    >

                        <option value="all">
                            All Status
                        </option>

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>


                    {{-- CATEGORY --}}
                    <select
                        id="categoryFilter"
                        class="h-10 px-3
                               rounded-lg
                               border border-gray-300
                               bg-white
                               text-sm text-gray-600
                               focus:outline-none
                               focus:ring-2
                               focus:ring-[#1F6F5B]/20
                               focus:border-[#1F6F5B]"
                    >

                        <option value="all">
                            All Categories
                        </option>

                        <option value="Electronics">
                            Electronics
                        </option>

                        <option value="Fashion">
                            Fashion
                        </option>

                        <option value="Beauty">
                            Beauty
                        </option>

                        <option value="Home & Living">
                            Home & Living
                        </option>

                        <option value="Groceries">
                            Groceries
                        </option>

                        <option value="Health">
                            Health
                        </option>

                        <option value="Sports">
                            Sports
                        </option>

                        <option value="Toys & Hobbies">
                            Toys & Hobbies
                        </option>

                        <option value="Automotive">
                            Automotive
                        </option>

                        <option value="Pet Supplies">
                            Pet Supplies
                        </option>

                        <option value="Books & Stationery">
                            Books & Stationery
                        </option>

                        <option value="Others">
                            Others
                        </option>

                    </select>

                </div>

            </div>


            {{-- DESKTOP TABLE --}}
            <div class="hidden md:block overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-50 border-b border-gray-100">

                        <tr>

                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                Product
                            </th>

                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                Category
                            </th>

                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                Price
                            </th>

                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                Stock
                            </th>

                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500">
                                Status
                            </th>

                            <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody
                        id="productTable"
                        class="divide-y divide-gray-100"
                    >


                        @forelse($products as $product)

                            @php

                                $stock = (int) ($product['stock'] ?? 0);

                                if ($stock <= 0) {
                                    $stockLabel = 'Out of stock';
                                    $stockClass = 'text-red-600';
                                } elseif ($stock <= 10) {
                                    $stockLabel = $stock . ' left';
                                    $stockClass = 'text-amber-600';
                                } else {
                                    $stockLabel = $stock;
                                    $stockClass = 'text-gray-700';
                                }

                            @endphp


                            <tr
                                class="product-row hover:bg-gray-50 transition"
                                data-name="{{ strtolower($product['name'] ?? '') }}"
                                data-status="{{ $product['status'] ?? 'active' }}"
                                data-category="{{ $product['category'] ?? '' }}"
                            >

                                {{-- PRODUCT --}}
                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-3">

                                        <div
                                            class="w-12 h-12 rounded-lg
                                                   bg-[#EEF8F3]
                                                   flex items-center justify-center
                                                   shrink-0"
                                        >

                                            <i
                                                data-lucide="package"
                                                class="w-5 h-5 text-[#1F6F5B]"
                                            ></i>

                                        </div>

                                        <div class="min-w-0">

                                            <p class="text-sm font-medium text-gray-900 truncate max-w-xs">
                                                {{ $product['name'] ?? 'Unnamed Product' }}
                                            </p>

                                            @if(!empty($product['sku']))

                                                <p class="text-xs text-gray-400 mt-0.5">
                                                    SKU: {{ $product['sku'] }}
                                                </p>

                                            @endif

                                        </div>

                                    </div>

                                </td>


                                {{-- CATEGORY --}}
                                <td class="px-5 py-4">

                                    <span class="text-sm text-gray-600">
                                        {{ $product['category'] ?? '—' }}
                                    </span>

                                </td>


                                {{-- PRICE --}}
                                <td class="px-5 py-4">

                                    <span class="text-sm font-medium text-gray-900">
                                        ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                    </span>

                                </td>


                                {{-- STOCK --}}
                                <td class="px-5 py-4">

                                    <span class="text-sm font-medium {{ $stockClass }}">
                                        {{ $stockLabel }}
                                    </span>

                                </td>


                                {{-- STATUS --}}
                                <td class="px-5 py-4">

                                    @if(($product['status'] ?? 'active') === 'active')

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   rounded-full
                                                   bg-green-50
                                                   text-green-700
                                                   text-[11px]
                                                   font-medium"
                                        >

                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>

                                            Active

                                        </span>

                                    @else

                                        <span
                                            class="inline-flex items-center gap-1.5
                                                   px-2.5 py-1
                                                   rounded-full
                                                   bg-gray-100
                                                   text-gray-600
                                                   text-[11px]
                                                   font-medium"
                                        >

                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>

                                            Inactive

                                        </span>

                                    @endif

                                </td>


                                {{-- ACTION --}}
                                <td class="px-5 py-4">

                                    <div class="flex justify-end items-center gap-2">

                                        <button
                                            type="button"
                                            class="p-2 rounded-lg
                                                   text-gray-500
                                                   hover:bg-gray-100
                                                   hover:text-[#1F6F5B]
                                                   transition"
                                            title="View Product"
                                        >
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="p-2 rounded-lg
                                                   text-gray-500
                                                   hover:bg-gray-100
                                                   hover:text-[#1F6F5B]
                                                   transition"
                                            title="Edit Product"
                                        >
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>

                                    </div>

                                </td>

                            </tr>


                        @empty

                            <tr id="emptyProductRow">

                                <td colspan="6" class="px-5 py-16 text-center">

                                    <div class="flex flex-col items-center">

                                        <div
                                            class="w-14 h-14 rounded-xl
                                                   bg-[#EEF8F3]
                                                   flex items-center justify-center"
                                        >

                                            <i
                                                data-lucide="package-open"
                                                class="w-7 h-7 text-[#1F6F5B]"
                                            ></i>

                                        </div>

                                        <h3 class="mt-4 text-sm font-semibold text-gray-900">
                                            No products yet
                                        </h3>

                                        <p class="mt-1 text-xs text-gray-500 max-w-sm">
                                            Start adding products to build your SUKI store.
                                        </p>

                                        <a
                                            href="{{ route('seller.products.create') }}"
                                            class="mt-4 inline-flex items-center gap-2
                                                   px-4 py-2
                                                   rounded-lg
                                                   bg-[#1F6F5B]
                                                   text-white
                                                   text-sm font-medium
                                                   hover:bg-[#155244]"
                                        >
                                            <i data-lucide="plus" class="w-4 h-4"></i>
                                            Add Product
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        @endforelse


                    </tbody>

                </table>

            </div>


            {{-- MOBILE PRODUCT CARDS --}}
            <div
                id="mobileProducts"
                class="md:hidden divide-y divide-gray-100"
            >

                @forelse($products as $product)

                    @php

                        $stock = (int) ($product['stock'] ?? 0);

                        if ($stock <= 0) {
                            $stockLabel = 'Out of stock';
                            $stockClass = 'text-red-600';
                        } elseif ($stock <= 10) {
                            $stockLabel = $stock . ' left';
                            $stockClass = 'text-amber-600';
                        } else {
                            $stockLabel = $stock . ' in stock';
                            $stockClass = 'text-gray-600';
                        }

                    @endphp


                    <div
                        class="mobile-product p-4"
                        data-name="{{ strtolower($product['name'] ?? '') }}"
                        data-status="{{ $product['status'] ?? 'active' }}"
                        data-category="{{ $product['category'] ?? '' }}"
                    >

                        <div class="flex items-start gap-3">

                            <div
                                class="w-14 h-14 rounded-lg
                                       bg-[#EEF8F3]
                                       flex items-center justify-center
                                       shrink-0"
                            >

                                <i
                                    data-lucide="package"
                                    class="w-6 h-6 text-[#1F6F5B]"
                                ></i>

                            </div>


                            <div class="flex-1 min-w-0">

                                <div class="flex items-start justify-between gap-3">

                                    <div class="min-w-0">

                                        <h3 class="text-sm font-medium text-gray-900 truncate">
                                            {{ $product['name'] ?? 'Unnamed Product' }}
                                        </h3>

                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ $product['category'] ?? '—' }}
                                        </p>

                                    </div>


                                    @if(($product['status'] ?? 'active') === 'active')

                                        <span
                                            class="shrink-0
                                                   px-2 py-1
                                                   rounded-full
                                                   bg-green-50
                                                   text-green-700
                                                   text-[10px]
                                                   font-medium"
                                        >
                                            Active
                                        </span>

                                    @else

                                        <span
                                            class="shrink-0
                                                   px-2 py-1
                                                   rounded-full
                                                   bg-gray-100
                                                   text-gray-600
                                                   text-[10px]
                                                   font-medium"
                                        >
                                            Inactive
                                        </span>

                                    @endif

                                </div>


                                <div class="mt-3 flex items-center justify-between">

                                    <div>

                                        <p class="text-sm font-semibold text-gray-900">
                                            ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                        </p>

                                        <p class="mt-0.5 text-xs {{ $stockClass }}">
                                            {{ $stockLabel }}
                                        </p>

                                    </div>


                                    <div class="flex items-center gap-1">

                                        <button
                                            type="button"
                                            class="p-2 rounded-lg
                                                   text-gray-500
                                                   hover:bg-gray-100
                                                   hover:text-[#1F6F5B]"
                                            title="View Product"
                                        >
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="p-2 rounded-lg
                                                   text-gray-500
                                                   hover:bg-gray-100
                                                   hover:text-[#1F6F5B]"
                                            title="Edit Product"
                                        >
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                @empty

                    <div class="px-5 py-16 text-center">

                        <div class="flex flex-col items-center">

                            <div
                                class="w-14 h-14 rounded-xl
                                       bg-[#EEF8F3]
                                       flex items-center justify-center"
                            >

                                <i
                                    data-lucide="package-open"
                                    class="w-7 h-7 text-[#1F6F5B]"
                                ></i>

                            </div>

                            <h3 class="mt-4 text-sm font-semibold text-gray-900">
                                No products yet
                            </h3>

                            <p class="mt-1 text-xs text-gray-500">
                                Start adding products to build your SUKI store.
                            </p>

                            <a
                                href="{{ route('seller.products.create') }}"
                                class="mt-4 inline-flex items-center gap-2
                                       px-4 py-2
                                       rounded-lg
                                       bg-[#1F6F5B]
                                       text-white
                                       text-sm font-medium
                                       hover:bg-[#155244]"
                            >
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Add Product
                            </a>

                        </div>

                    </div>

                @endforelse

            </div>


            {{-- NO SEARCH RESULTS --}}
            <div
                id="noResults"
                class="hidden px-5 py-16 text-center"
            >

                <div class="flex flex-col items-center">

                    <div
                        class="w-14 h-14 rounded-xl
                               bg-gray-100
                               flex items-center justify-center"
                    >

                        <i
                            data-lucide="search-x"
                            class="w-7 h-7 text-gray-400"
                        ></i>

                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-gray-900">
                        No products found
                    </h3>

                    <p class="mt-1 text-xs text-gray-500">
                        Try changing your search or filter.
                    </p>

                </div>

            </div>


        </div>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput =
        document.getElementById('productSearch');

    const statusFilter =
        document.getElementById('statusFilter');

    const categoryFilter =
        document.getElementById('categoryFilter');

    const desktopRows =
        Array.from(document.querySelectorAll('.product-row'));

    const mobileProducts =
        Array.from(document.querySelectorAll('.mobile-product'));

    const noResults =
        document.getElementById('noResults');


    function filterProducts() {

        const search =
            (searchInput?.value || '').toLowerCase().trim();

        const status =
            statusFilter?.value || 'all';

        const category =
            categoryFilter?.value || 'all';


        let visibleCount = 0;


        desktopRows.forEach(function (row) {

            const name =
                row.dataset.name || '';

            const rowStatus =
                row.dataset.status || '';

            const rowCategory =
                row.dataset.category || '';


            const matchesSearch =
                !search || name.includes(search);

            const matchesStatus =
                status === 'all' || rowStatus === status;

            const matchesCategory =
                category === 'all' || rowCategory === category;


            const visible =
                matchesSearch &&
                matchesStatus &&
                matchesCategory;


            row.style.display =
                visible ? '' : 'none';


            if (visible) {
                visibleCount++;
            }

        });


        mobileProducts.forEach(function (card) {

            const name =
                card.dataset.name || '';

            const cardStatus =
                card.dataset.status || '';

            const cardCategory =
                card.dataset.category || '';


            const matchesSearch =
                !search || name.includes(search);

            const matchesStatus =
                status === 'all' || cardStatus === status;

            const matchesCategory =
                category === 'all' || cardCategory === category;


            const visible =
                matchesSearch &&
                matchesStatus &&
                matchesCategory;


            card.style.display =
                visible ? '' : 'none';

        });


        if (noResults) {

            if (desktopRows.length > 0 && visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }

        }

    }


    if (searchInput) {
        searchInput.addEventListener(
            'input',
            filterProducts
        );
    }


    if (statusFilter) {
        statusFilter.addEventListener(
            'change',
            filterProducts
        );
    }


    if (categoryFilter) {
        categoryFilter.addEventListener(
            'change',
            filterProducts
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