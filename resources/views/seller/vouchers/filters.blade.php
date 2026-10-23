<form method="GET" action="{{ route('seller.vouchers.index') }}" class="grid gap-3 bg-[#FBFCFB] p-4 sm:grid-cols-2 lg:grid-cols-4">
    <label class="text-xs font-semibold">Search name or code<input type="search" name="search" value="{{ $criteria['search'] ?? '' }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2"></label>
    @foreach(['availability' => ['Availability', \App\Support\SellerVouchers::STATUSES], 'discount_type' => ['Discount type', ['percentage' => 'Percentage', 'fixed' => 'Fixed Amount']], 'product_scope' => ['Product eligibility', ['all' => 'All Seller Products', 'selected' => 'Selected Products']], 'product_id' => ['Eligible product', $products->pluck('name', 'id')->all()], 'date_range' => ['Date Created', \App\Support\SellerDateFilter::PRESETS], 'sort' => ['Sort', \App\Support\SellerVouchers::SORTS]] as $name => [$label, $choices])
        <label class="text-xs font-semibold">{{ $label }}<select name="{{ $name }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2">
            @unless(in_array($name, ['sort', 'date_range']))<option value="">All</option>@endunless
            @foreach($choices as $value => $text)<option value="{{ $value }}" @selected((string)($criteria[$name] ?? (in_array($name, ['sort', 'date_range']) ? array_key_first($choices) : '')) === (string)$value)>{{ $text }}</option>@endforeach
        </select></label>
    @endforeach
    @foreach(['from' => 'From (custom)', 'to' => 'To (inclusive, custom)'] as $name => $label)<label class="text-xs font-semibold">{{ $label }}<input type="date" name="{{ $name }}" value="{{ $criteria[$name] ?? '' }}" class="mt-1 w-full rounded-lg border border-[#DDE6E1] p-2"></label>@endforeach
    <div class="flex justify-end gap-3 sm:col-span-2 lg:col-span-4"><a href="{{ route('seller.vouchers.index') }}" class="rounded-lg border border-[#BFD2C9] px-4 py-2 text-xs font-semibold text-[#1F6F5B]">Reset Filters</a><button class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-xs font-semibold text-white">Apply Filters</button></div>
    <p class="text-xs text-[#728078] sm:col-span-2 lg:col-span-4">Date Created uses Asia/Manila calendar boundaries. Schedule dates are shown separately; an active configuration is not proof of Buyer redeemability.</p>
</form>
