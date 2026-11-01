<form id="salesFilterForm" class="sales-filters" action="{{ route('seller.sales') }}" method="GET">
    <div class="sales-filter-toolbar">
        <label for="salesSearch" class="sales-search-label">Search sales
            <input id="salesSearch" type="search" name="search" maxlength="100" value="{{ $criteria['search'] ?? '' }}" placeholder="Order, customer or product name">
        </label>
        <button id="salesFilterToggle" type="button" aria-expanded="false" aria-controls="salesFilterPanel">Filters @if(count($chips))<span class="sales-filter-badge">{{ count($chips) }}</span>@endif</button>
    </div>
    @if($errors->any())<div class="sales-filter-errors" role="alert"><strong>Please review your filters.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div id="salesFilterBackdrop" class="sales-filter-backdrop" hidden></div>
    <section id="salesFilterPanel" class="sales-filter-panel" aria-labelledby="salesFilterTitle" tabindex="-1" hidden>
        <header><h3 id="salesFilterTitle">Filter sales</h3><button id="salesFilterClose" type="button" aria-label="Close filters">Close &times;</button></header>
        <div class="sales-filter-content">
            <div class="sales-filter-grid">
                @foreach(['date'=>'Order date', 'status'=>'Order status', 'payment'=>'Payment status', 'fulfillment'=>'Fulfillment status', 'voucher'=>'Voucher / Promotion'] as $key=>$label)
                    <fieldset><legend>{{ $label }}</legend>
                        @foreach($groups[$key] as $value=>$text)
                            <label class="sales-radio"><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked(($criteria[$key] ?? ($key === 'date' ? 'any' : 'all')) === $value)>{{ $text }}</label>
                        @endforeach
                        @if($key === 'date')
                            <div id="salesCustomDates" @if(($criteria['date'] ?? '') !== 'custom') hidden @endif>
                                <label for="salesFrom">From date</label><input id="salesFrom" type="date" name="from" value="{{ $criteria['from'] ?? '' }}">
                                <label for="salesTo">To date</label><input id="salesTo" type="date" name="to" value="{{ $criteria['to'] ?? '' }}">
                            </div>
                            <p class="sales-filter-hint">Calendar days in Asia/Manila.</p>
                        @elseif($key === 'payment')
                            <p class="sales-filter-hint">Uses recorded payment or this shop's COD collection. Refunds due are not completed refunds.</p>
                        @elseif($key === 'status')
                            <p class="sales-filter-hint">Completed includes Delivered, as in the existing Sales cards. Returned means returned to Seller; returns in progress remain pending.</p>
                        @endif
                    </fieldset>
                @endforeach
                <div>
                    <fieldset><legend>Amount range (PHP)</legend>
                        <label for="salesMin">Minimum amount</label><input id="salesMin" type="number" name="min" min="0" max="9999999999.99" step="0.01" value="{{ $criteria['min'] ?? '' }}" placeholder="0.00">
                        <label for="salesMax">Maximum amount</label><input id="salesMax" type="number" name="max" min="0" max="9999999999.99" step="0.01" value="{{ $criteria['max'] ?? '' }}" placeholder="No maximum">
                        <p class="sales-filter-hint">Seller order total after vouchers, including shipping. This is not wallet earnings or a net payout.</p>
                    </fieldset>
                    <label for="salesSort">Sort by</label><select id="salesSort" name="sort">
                        @foreach(['newest'=>'Newest', 'oldest'=>'Oldest', 'highest'=>'Highest Amount', 'lowest'=>'Lowest Amount'] as $value=>$label)<option value="{{ $value }}" @selected(($criteria['sort'] ?? 'newest') === $value)>{{ $label }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="sales-product-picker">
                <label for="salesProductSearch">Product</label>
                <input id="salesProductSearch" type="search" maxlength="100" autocomplete="off" value="{{ $selectedProduct?->name ?? '' }}" placeholder="Search your products" aria-controls="salesProductResults" data-url="{{ route('seller.sales.products') }}">
                <input id="salesProductId" type="hidden" name="product" value="{{ $criteria['product'] ?? '' }}">
                <ul id="salesProductResults" aria-label="Matching products" hidden></ul>
                <p id="salesProductStatus" class="sales-filter-hint" role="status" aria-live="polite">{{ $selectedProduct ? 'Selected: '.$selectedProduct->name : 'Select a product to filter. Historical products are included.' }}</p>
                <button id="salesProductClear" type="button">Clear product</button>
            </div>
        </div>
        <footer><a href="{{ route('seller.sales') }}">Clear All</a><button type="submit" id="salesApply">Apply Filters</button></footer>
    </section>
</form>
<div class="sales-filter-chips" aria-label="Active filters">
    @foreach($chips as $chip)<a href="{{ $chip['url'] }}" aria-label="Remove filter: {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">&times;</span></a>@endforeach
    @if(count($chips))<a class="sales-clear-all" href="{{ route('seller.sales') }}">Clear All</a>@endif
</div>
<noscript><style>#salesFilterPanel[hidden]{display:block;position:static;max-height:none}.sales-filter-panel .sales-filter-content{overflow:visible}#salesFilterToggle{display:none}#salesCustomDates[hidden]{display:block}</style><p>Filters remain available with JavaScript disabled. Product-name search is available through Search sales.</p></noscript>
