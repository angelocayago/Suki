@extends('layouts.app')

@section('content')

@php
    $products = session('seller_products', []);

    $totalProducts = count($products);
    $inStock = collect($products)->where('stock', '>', 10)->count();
    $lowStock = collect($products)->whereBetween('stock', [1, 10])->count();
    $outOfStock = collect($products)->where('stock', '<=', 0)->count();
@endphp

<div class="min-h-screen bg-[#F8FAF8]">

    {{-- SELLER HEADER --}}
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div>
                    <p class="text-sm text-gray-500">Seller Centre</p>

                    <h1 class="text-xl sm:text-2xl font-semibold text-[#1F2937]">
                        Everyday Finds PH
                    </h1>

                    <p class="text-sm text-gray-500 mt-1">
                        Inventory Management
                    </p>
                </div>

                <a
                    href="{{ route('seller.products.create') }}"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-medium hover:bg-[#155244] transition"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Add Product
                </a>

            </div>

        </div>
    </div>


    {{-- CONTENT --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- BREADCRUMB --}}
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <a href="{{ route('seller.products') }}" class="hover:text-[#1F6F5B]">
                Products
            </a>

            <i data-lucide="chevron-right" class="w-4 h-4"></i>

            <span class="text-gray-800 font-medium">
                Inventory
            </span>
        </div>


        {{-- PAGE TITLE --}}
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-gray-900">
                Inventory
            </h2>

            <p class="text-sm text-gray-500 mt-1">
                Monitor and manage your product stock.
            </p>
        </div>


        {{-- SUCCESS MESSAGE --}}
        @if(session('success'))
            <div class="mb-6 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                <i data-lucide="check-circle" class="w-5 h-5 mt-0.5 shrink-0"></i>

                <span>
                    {{ session('success') }}
                </span>
            </div>
        @endif


        {{-- STATS --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

            {{-- TOTAL --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Total Products
                        </p>

                        <p class="text-2xl font-semibold text-gray-900 mt-2">
                            {{ $totalProducts }}
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                        <i data-lucide="package" class="w-5 h-5 text-gray-600"></i>
                    </div>

                </div>
            </div>


            {{-- IN STOCK --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            In Stock
                        </p>

                        <p class="text-2xl font-semibold text-gray-900 mt-2">
                            {{ $inStock }}
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center">
                        <i data-lucide="package-check" class="w-5 h-5 text-[#1F6F5B]"></i>
                    </div>

                </div>
            </div>


            {{-- LOW STOCK --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Low Stock
                        </p>

                        <p class="text-2xl font-semibold text-gray-900 mt-2">
                            {{ $lowStock }}
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                        <i data-lucide="triangle-alert" class="w-5 h-5 text-amber-600"></i>
                    </div>

                </div>
            </div>


            {{-- OUT OF STOCK --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <div class="flex items-center justify-between">

                    <div>
                        <p class="text-sm text-gray-500">
                            Out of Stock
                        </p>

                        <p class="text-2xl font-semibold text-gray-900 mt-2">
                            {{ $outOfStock }}
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                        <i data-lucide="package-x" class="w-5 h-5 text-red-500"></i>
                    </div>

                </div>
            </div>

        </div>


        {{-- INVENTORY CARD --}}
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

            {{-- TOOLBAR --}}
            <div class="p-4 border-b border-gray-200">

                <div class="flex flex-col lg:flex-row gap-3">

                    {{-- SEARCH --}}
                    <div class="relative flex-1">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                        ></i>

                        <input
                            type="text"
                            id="inventorySearch"
                            placeholder="Search product name or SKU..."
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-[#1F6F5B]/20 focus:border-[#1F6F5B]"
                        >

                    </div>


                    {{-- CATEGORY --}}
                    <select
                        id="categoryFilter"
                        class="w-full lg:w-48 px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white outline-none focus:ring-2 focus:ring-[#1F6F5B]/20 focus:border-[#1F6F5B]"
                    >
                        <option value="all">All Categories</option>

                        @foreach(collect($products)->pluck('category')->filter()->unique()->sort() as $category)
                            <option value="{{ strtolower($category) }}">
                                {{ $category }}
                            </option>
                        @endforeach

                    </select>


                    {{-- STOCK STATUS --}}
                    <select
                        id="stockFilter"
                        class="w-full lg:w-48 px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white outline-none focus:ring-2 focus:ring-[#1F6F5B]/20 focus:border-[#1F6F5B]"
                    >
                        <option value="all">All Stock Status</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>

                </div>

            </div>


            {{-- DESKTOP TABLE --}}
            <div class="hidden md:block overflow-x-auto">

                <table class="w-full">

                    <thead class="bg-gray-50 border-b border-gray-200">

                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">

                            <th class="px-6 py-4">
                                Product
                            </th>

                            <th class="px-6 py-4">
                                Category
                            </th>

                            <th class="px-6 py-4">
                                SKU
                            </th>

                            <th class="px-6 py-4">
                                Price
                            </th>

                            <th class="px-6 py-4 text-center">
                                Stock
                            </th>

                            <th class="px-6 py-4">
                                Status
                            </th>

                            <th class="px-6 py-4 text-right">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody id="inventoryTable" class="divide-y divide-gray-100">

                        @forelse($products as $product)

                            @php
                                $stock = (int) ($product['stock'] ?? 0);

                                if ($stock <= 0) {
                                    $stockStatus = 'out-of-stock';
                                    $stockLabel = 'Out of Stock';
                                } elseif ($stock <= 10) {
                                    $stockStatus = 'low-stock';
                                    $stockLabel = 'Low Stock';
                                } else {
                                    $stockStatus = 'in-stock';
                                    $stockLabel = 'In Stock';
                                }
                            @endphp

                            <tr
                                class="inventory-row hover:bg-gray-50 transition"
                                data-name="{{ strtolower($product['name'] ?? '') }}"
                                data-sku="{{ strtolower($product['sku'] ?? '') }}"
                                data-category="{{ strtolower($product['category'] ?? '') }}"
                                data-stock-status="{{ $stockStatus }}"
                            >

                                {{-- PRODUCT --}}
                                <td class="px-6 py-4">

                                    <div class="flex items-center gap-3">

                                        <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                                            <i data-lucide="image" class="w-5 h-5 text-gray-400"></i>
                                        </div>

                                        <div class="min-w-0">

                                            <p class="font-medium text-gray-900 truncate max-w-[240px]">
                                                {{ $product['name'] ?? 'Unnamed Product' }}
                                            </p>

                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $product['brand'] ?? 'No brand' }}
                                            </p>

                                        </div>

                                    </div>

                                </td>


                                {{-- CATEGORY --}}
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ $product['category'] ?? '—' }}
                                </td>


                                {{-- SKU --}}
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $product['sku'] ?? '—' }}
                                </td>


                                {{-- PRICE --}}
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                </td>


                                {{-- STOCK --}}
                                <td class="px-6 py-4">

                                    <div class="flex items-center justify-center gap-2">

                                        <form
                                            action="{{ route('seller.inventory.decrease', $product['id']) }}"
                                            method="POST"
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="w-8 h-8 rounded-lg border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition"
                                            >
                                                <i data-lucide="minus" class="w-4 h-4"></i>
                                            </button>

                                        </form>


                                        <span class="min-w-[45px] text-center font-semibold text-gray-900">
                                            {{ $stock }}
                                        </span>


                                        <form
                                            action="{{ route('seller.inventory.increase', $product['id']) }}"
                                            method="POST"
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="w-8 h-8 rounded-lg border border-gray-300 flex items-center justify-center text-gray-600 hover:bg-gray-100 transition"
                                            >
                                                <i data-lucide="plus" class="w-4 h-4"></i>
                                            </button>

                                        </form>

                                    </div>

                                </td>


                                {{-- STATUS --}}
                                <td class="px-6 py-4">

                                    @if($stockStatus === 'in-stock')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-green-50 text-green-700 text-xs font-medium">
                                            In Stock
                                        </span>

                                    @elseif($stockStatus === 'low-stock')

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 text-xs font-medium">
                                            Low Stock
                                        </span>

                                    @else

                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-red-50 text-red-600 text-xs font-medium">
                                            Out of Stock
                                        </span>

                                    @endif

                                </td>


                                {{-- ACTION --}}
                                <td class="px-6 py-4 text-right">

                                    <a
                                        href="{{ route('seller.products.edit', $product['id']) }}"
                                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 hover:text-[#1F6F5B] transition"
                                    >
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                        Edit
                                    </a>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">

                                    <div class="flex flex-col items-center">

                                        <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                            <i data-lucide="package-open" class="w-6 h-6 text-gray-400"></i>
                                        </div>

                                        <h3 class="font-medium text-gray-900">
                                            No products yet
                                        </h3>

                                        <p class="text-sm text-gray-500 mt-1">
                                            Add your first product to start managing inventory.
                                        </p>

                                        <a
                                            href="{{ route('seller.products.create') }}"
                                            class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-[#1F6F5B] text-white text-sm font-medium hover:bg-[#155244]"
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


            {{-- MOBILE --}}
            <div id="inventoryMobile" class="md:hidden divide-y divide-gray-100">

                @forelse($products as $product)

                    @php
                        $stock = (int) ($product['stock'] ?? 0);

                        if ($stock <= 0) {
                            $stockStatus = 'out-of-stock';
                        } elseif ($stock <= 10) {
                            $stockStatus = 'low-stock';
                        } else {
                            $stockStatus = 'in-stock';
                        }
                    @endphp

                    <div
                        class="inventory-mobile-row p-4"
                        data-name="{{ strtolower($product['name'] ?? '') }}"
                        data-sku="{{ strtolower($product['sku'] ?? '') }}"
                        data-category="{{ strtolower($product['category'] ?? '') }}"
                        data-stock-status="{{ $stockStatus }}"
                    >

                        <div class="flex gap-3">

                            <div class="w-14 h-14 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                                <i data-lucide="image" class="w-5 h-5 text-gray-400"></i>
                            </div>

                            <div class="flex-1 min-w-0">

                                <div class="flex items-start justify-between gap-3">

                                    <div class="min-w-0">

                                        <h3 class="font-medium text-gray-900 truncate">
                                            {{ $product['name'] ?? 'Unnamed Product' }}
                                        </h3>

                                        <p class="text-xs text-gray-500 mt-1">
                                            SKU: {{ $product['sku'] ?? '—' }}
                                        </p>

                                    </div>

                                    @if($stockStatus === 'in-stock')

                                        <span class="shrink-0 px-2 py-1 rounded-full bg-green-50 text-green-700 text-[11px] font-medium">
                                            In Stock
                                        </span>

                                    @elseif($stockStatus === 'low-stock')

                                        <span class="shrink-0 px-2 py-1 rounded-full bg-amber-50 text-amber-700 text-[11px] font-medium">
                                            Low Stock
                                        </span>

                                    @else

                                        <span class="shrink-0 px-2 py-1 rounded-full bg-red-50 text-red-600 text-[11px] font-medium">
                                            Out of Stock
                                        </span>

                                    @endif

                                </div>


                                <div class="flex items-center justify-between mt-4">

                                    <div>

                                        <p class="text-xs text-gray-500">
                                            Price
                                        </p>

                                        <p class="font-semibold text-gray-900">
                                            ₱{{ number_format((float) ($product['price'] ?? 0), 2) }}
                                        </p>

                                    </div>


                                    <div>

                                        <p class="text-xs text-gray-500 text-center mb-1">
                                            Stock
                                        </p>

                                        <div class="flex items-center gap-2">

                                            <form
                                                action="{{ route('seller.inventory.decrease', $product['id']) }}"
                                                method="POST"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="w-8 h-8 rounded-lg border border-gray-300 flex items-center justify-center"
                                                >
                                                    <i data-lucide="minus" class="w-4 h-4"></i>
                                                </button>

                                            </form>


                                            <span class="font-semibold min-w-[30px] text-center">
                                                {{ $stock }}
                                            </span>


                                            <form
                                                action="{{ route('seller.inventory.increase', $product['id']) }}"
                                                method="POST"
                                            >
                                                @csrf

                                                <button
                                                    type="submit"
                                                    class="w-8 h-8 rounded-lg border border-gray-300 flex items-center justify-center"
                                                >
                                                    <i data-lucide="plus" class="w-4 h-4"></i>
                                                </button>

                                            </form>

                                        </div>

                                    </div>

                                </div>


                                <a
                                    href="{{ route('seller.products.edit', $product['id']) }}"
                                    class="mt-4 w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-gray-50 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                    Edit Product
                                </a>

                            </div>

                        </div>

                    </div>

                @empty

                    <div class="px-6 py-16 text-center">

                        <i data-lucide="package-open" class="w-8 h-8 text-gray-400 mx-auto mb-3"></i>

                        <p class="font-medium text-gray-900">
                            No products yet
                        </p>

                        <p class="text-sm text-gray-500 mt-1">
                            Add a product to manage your inventory.
                        </p>

                    </div>

                @endforelse

            </div>


            {{-- NO SEARCH RESULT --}}
            <div
                id="noInventoryResults"
                class="hidden px-6 py-16 text-center"
            >
                <i data-lucide="search-x" class="w-8 h-8 text-gray-400 mx-auto mb-3"></i>

                <p class="font-medium text-gray-900">
                    No matching products
                </p>

                <p class="text-sm text-gray-500 mt-1">
                    Try another search or filter.
                </p>
            </div>

        </div>

    </div>

</div>


@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function () {

    const searchInput = document.getElementById('inventorySearch');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const rows = document.querySelectorAll('.inventory-row');
    const mobileRows = document.querySelectorAll('.inventory-mobile-row');
    const noResults = document.getElementById('noInventoryResults');

    function filterInventory() {

        const search = searchInput.value.toLowerCase().trim();
        const category = categoryFilter.value;
        const stock = stockFilter.value;

        let visibleCount = 0;

        rows.forEach(row => {

            const name = row.dataset.name || '';
            const sku = row.dataset.sku || '';
            const rowCategory = row.dataset.category || '';
            const rowStock = row.dataset.stockStatus || '';

            const matchesSearch =
                name.includes(search) ||
                sku.includes(search);

            const matchesCategory =
                category === 'all' ||
                rowCategory === category;

            const matchesStock =
                stock === 'all' ||
                rowStock === stock;

            const visible =
                matchesSearch &&
                matchesCategory &&
                matchesStock;

            row.classList.toggle('hidden', !visible);

            if (visible) {
                visibleCount++;
            }

        });


        mobileRows.forEach(row => {

            const name = row.dataset.name || '';
            const sku = row.dataset.sku || '';
            const rowCategory = row.dataset.category || '';
            const rowStock = row.dataset.stockStatus || '';

            const matchesSearch =
                name.includes(search) ||
                sku.includes(search);

            const matchesCategory =
                category === 'all' ||
                rowCategory === category;

            const matchesStock =
                stock === 'all' ||
                rowStock === stock;

            const visible =
                matchesSearch &&
                matchesCategory &&
                matchesStock;

            row.classList.toggle('hidden', !visible);

        });


        noResults.classList.toggle('hidden', visibleCount > 0);

    }


    searchInput.addEventListener('input', filterInventory);
    categoryFilter.addEventListener('change', filterInventory);
    stockFilter.addEventListener('change', filterInventory);

});

</script>

@endpush

@endsection