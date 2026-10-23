@if($errors->any())<div role="alert" class="m-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<form method="GET" action="{{ route('seller.sales') }}" class="grid grid-cols-1 gap-3 border-b border-[#EDF1EF] bg-[#FBFCFB] p-4 sm:grid-cols-2 lg:grid-cols-4">
    @php
        $fields = ['product_id' => ['Product', $products->pluck('name', 'id')->all()],
            'category' => ['Main category', $mainCategory ? [$mainCategory->id => $mainCategory->name] : []],
            'subcategory' => ['Subcategory', $filterCategories->pluck('name', 'id')->all()],
            'order_status' => ['Order status', array_combine(\App\Support\SellerReports::STATUSES, array_map(fn($s) => ucwords(str_replace('_', ' ', $s)), \App\Support\SellerReports::STATUSES))],
            'payment_method' => ['Saved payment method', $paymentMethods->mapWithKeys(fn($v) => [$v => $v])->all()],
            'sort' => ['Sort', ['newest' => 'Newest First', 'oldest' => 'Oldest First', 'amount_desc' => 'Highest Transaction Amount', 'amount_asc' => 'Lowest Transaction Amount']],
            'date_range' => ['Order creation date', \App\Support\SellerDateFilter::PRESETS]];
    @endphp
    @foreach($fields as $name => [$label, $choices])
        <label class="min-w-0 text-xs font-semibold" for="sales-filter-{{ $name }}">{{ $label }}<select id="sales-filter-{{ $name }}" name="{{ $name }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] bg-white p-2 text-sm">
            @unless(in_array($name, ['sort','date_range']))<option value="">All</option>@endunless
            @foreach($choices as $value => $text)<option value="{{ $value }}" @selected((string)request($name, in_array($name, ['sort','date_range']) ? array_key_first($choices) : '') === (string)$value)>{{ $text }}</option>@endforeach
        </select></label>
    @endforeach
    @foreach(['from' => 'From (custom range)', 'to' => 'To (inclusive, custom range)'] as $name => $label)
        <label class="min-w-0 text-xs font-semibold">{{ $label }}<input type="date" name="{{ $name }}" value="{{ request($name) }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2 text-sm"></label>
    @endforeach
    <div class="flex items-end justify-end gap-3 sm:col-span-2 lg:col-span-4"><a href="{{ route('seller.sales') }}" class="rounded-lg border border-[#BFD2C9] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">Reset Filters</a><button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Apply Filters</button></div>
    <p class="text-xs text-[#68776F] sm:col-span-2 lg:col-span-4">Order creation dates use Asia/Manila. Last 7/30 Days include today and the previous 6/29 calendar days. Weeks begin Monday. Selected payment methods do not establish payment receipt.</p>
</form>
