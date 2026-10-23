@if($errors->any())<div role="alert" class="m-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<form method="GET" action="{{ route('seller.orders') }}" class="grid grid-cols-1 gap-3 border-b border-[#EDF1EF] bg-[#FBFCFB] p-4 sm:grid-cols-2 lg:grid-cols-4">
    <label class="min-w-0 text-xs font-semibold" for="orders-search">Search order reference or seller item<input id="orders-search" type="search" name="search" maxlength="200" value="{{ $criteria['search'] ?? '' }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] bg-white p-2 text-sm"></label>
    @foreach(['order_status' => ['Fulfillment status', $statuses], 'payment_method' => ['Saved payment method', $methods], 'sort' => ['Sort', \App\Support\SellerOrders::SORTS], 'date_range' => ['Date Placed', \App\Support\SellerDateFilter::PRESETS]] as $name => [$label, $choices])
        <label class="min-w-0 text-xs font-semibold" for="orders-{{ $name }}">{{ $label }}<select id="orders-{{ $name }}" name="{{ $name }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] bg-white p-2 text-sm">
            @unless(in_array($name, ['sort', 'date_range']))<option value="">All</option>@endunless
            @foreach($choices as $value => $text)<option value="{{ $value }}" @selected((string)($criteria[$name] ?? (in_array($name, ['sort', 'date_range']) ? array_key_first($choices) : '')) === (string)$value)>{{ $text }}</option>@endforeach
        </select></label>
    @endforeach
    @foreach(['from' => 'From (custom range)', 'to' => 'To (inclusive, custom range)'] as $name => $label)
        <label class="min-w-0 text-xs font-semibold" for="orders-{{ $name }}">{{ $label }}<input id="orders-{{ $name }}" type="date" name="{{ $name }}" value="{{ $criteria[$name] ?? '' }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2 text-sm"></label>
    @endforeach
    <div class="flex items-end justify-end gap-3 sm:col-span-2 lg:col-span-4"><a href="{{ route('seller.orders') }}" class="rounded-lg border border-[#BFD2C9] px-4 py-2 text-sm font-semibold text-[#1F6F5B]">Reset Filters</a><button type="submit" class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">Apply Filters</button></div>
    <p class="text-xs text-[#68776F] sm:col-span-2 lg:col-span-4">Date Placed uses Asia/Manila. Last 7/30 Days include today. Weeks begin Monday. The selected end date is inclusive.</p>
</form>
