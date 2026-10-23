@if($errors->any())
    <div role="alert" class="m-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif
<form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 gap-3 border-b border-[#EDF1EF] bg-[#FBFCFB] p-4 sm:grid-cols-2 lg:grid-cols-4">
    @php
        $report = $report ?? false;
        $selects = $report ? [
            'product_id' => ['Product', $products->pluck('name', 'id')->all()],
            'order_status' => ['Order status', array_combine(\App\Support\SellerReports::STATUSES, \App\Support\SellerReports::STATUSES)],
            'payment_method' => ['Payment method', $paymentMethods->mapWithKeys(fn($v) => [$v => $v])->all()],
            'sort' => ['Product breakdown sort', ['sales_desc' => 'Highest Sales', 'sales_asc' => 'Lowest Sales', 'units_desc' => 'Most Units Sold', 'recent' => 'Most Recent']],
        ] : [
            'status' => ['Listing status', ['active' => 'Active', 'inactive' => 'Inactive', 'draft' => 'Draft']],
            'stock_status' => ['Aggregate stock status', ['in-stock' => 'In Stock', 'low-stock' => 'Low Stock', 'out-of-stock' => 'Out of Stock']],
            'product_type' => ['Product type', ['simple' => 'Simple products', 'variable' => 'Products with variations']],
            'variant_id' => ['Specific variant', $filterVariants->pluck('sku', 'id')->all()],
            'sort' => ['Sort', ['name_asc' => 'Product Name A–Z', 'name_desc' => 'Product Name Z–A', 'stock_asc' => 'Lowest Stock First', 'stock_desc' => 'Highest Stock First', 'updated' => 'Recently Updated', 'price_asc' => 'Lowest Price First', 'price_desc' => 'Highest Price First']],
            'date_field' => ['Date field', ['created_at' => 'Date Added', 'updated_at' => 'Last Updated']],
        ];
        $selects += ['category' => ['Main category', $mainCategory ? [$mainCategory->id => $mainCategory->name] : []],
            'subcategory' => ['Subcategory', $filterCategories->pluck('name', 'id')->all()],
            'date_range' => ['Date range', \App\Support\SellerDateFilter::PRESETS]];
    @endphp
    @unless($report)
        <label class="text-xs font-semibold">Search name, SKU or variation
            <input name="search" value="{{ request('search') }}" maxlength="150" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2 text-sm">
        </label>
    @endunless
    @foreach($selects as $name => [$label, $choices])
        <label for="filter-{{ $name }}" class="text-xs font-semibold">{{ $label }}
            <select id="filter-{{ $name }}" name="{{ $name }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] bg-white p-2 text-sm">
                @unless(in_array($name, ['sort','date_field','date_range']))<option value="">All</option>@endunless
                @foreach($choices as $value => $text)<option value="{{ $value }}" @selected((string)request($name, in_array($name, ['sort','date_field','date_range']) ? array_key_first($choices) : '') === (string)$value)>{{ $text }}</option>@endforeach
            </select>
        </label>
    @endforeach
    @foreach(['from' => 'From (custom range)', 'to' => 'To (inclusive, custom range)'] as $name => $label)
        <label class="text-xs font-semibold">{{ $label }}<input type="date" name="{{ $name }}" value="{{ request($name) }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2 text-sm"></label>
    @endforeach
    @unless($report)
        @foreach(['min_stock' => 'Minimum stock', 'max_stock' => 'Maximum stock', 'min_price' => 'Minimum price (PHP)', 'max_price' => 'Maximum price (PHP)'] as $name => $label)
            <label class="text-xs font-semibold">{{ $label }}<input type="number" min="0" step="{{ str_contains($name, 'price') ? '0.01' : '1' }}" name="{{ $name }}" value="{{ request($name) }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2 text-sm"></label>
        @endforeach
    @endunless
    @if($compact ?? false)
        <div class="flex items-end justify-end gap-3 sm:col-span-2 lg:col-span-4"><a href="{{ url()->current() }}" class="rounded-lg border border-[#BFD2C9] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">Reset Filters</a><button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Apply Filters</button></div>
    @else
        <div class="flex items-end gap-3"><button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Apply Filters</button><a href="{{ url()->current() }}" class="py-2 text-sm font-semibold text-[#1F6F5B]">Reset Filters</a></div>
    @endif
    <p class="text-xs text-[#68776F] sm:col-span-2 lg:col-span-4">Dates use Asia/Manila. Last 7/30 Days include today and the previous 6/29 calendar days. Weeks begin Monday. @unless($report)Stock filters use totals across active variants; individual alerts appear below.@endunless</p>
</form>
