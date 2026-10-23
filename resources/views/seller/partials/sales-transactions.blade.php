<section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#EDF1EF] px-5 py-4">
        <div><h3 class="text-sm font-semibold text-[#24312C]">Recent Transactions</h3><p class="mt-0.5 text-[11px] text-[#7B8982]">Saved seller-owned orders with recorded items, including pending transactions.</p></div>
        @include('seller.partials.filter-trigger')
    </div>
    @include('seller.partials.advanced-filter-panel', ['filterForm' => 'seller.partials.sales-filters'])
    @include('seller.partials.active-filter-chips')
    <p class="border-b border-[#EDF1EF] px-5 py-3 text-[11px] text-[#7B8982]">{{ $paginator->total() }} matching transactions. All four cards describe the complete filtered dataset, across pages.</p>
    <p class="px-5 py-3 text-xs leading-5 text-[#728078]">Gross item values exclude shipping and unallocated discounts. Completed/delivered orders do not prove payment. Verified received earnings, refunds, net revenue and payouts are unavailable; no seller settlement ledger is connected. Product filters restrict each transaction's value to matching items.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-xs">
            <thead class="border-b border-[#EDF1EF] bg-[#FAFCFB] text-[10px] text-[#839189]"><tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Order Created (PHT)</th><th class="px-5 py-3">Units</th><th class="px-5 py-3">Order Status</th><th class="px-5 py-3">Saved Method</th><th class="px-5 py-3 text-right">Gross Item Value</th></tr></thead>
            <tbody class="divide-y divide-[#EEF2F0]">
                @forelse($paginator as $transaction)
                    @php($created = \Carbon\CarbonImmutable::parse($transaction->transaction_at, config('app.timezone'))->setTimezone(config('seller_inventory.business_timezone')))
                    <tr class="text-[#52635B]"><td class="px-5 py-4 font-semibold text-[#24312C]">{{ $transaction->order_number }}</td><td class="px-5 py-4"><time datetime="{{ $created->toIso8601String() }}">{{ $created->format('M j, Y g:i A') }}</time></td><td class="px-5 py-4">{{ $transaction->units }}</td><td class="px-5 py-4">{{ ucwords(str_replace('_', ' ', $transaction->order_status)) }}</td><td class="px-5 py-4">{{ $transaction->payment_method ?: 'Not recorded' }}</td><td class="px-5 py-4 text-right font-semibold">₱{{ number_format($transaction->gross_amount, 2) }}</td></tr>
                @empty<tr><td colspan="6" class="px-5 py-14 text-center"><h4 class="text-sm font-semibold text-[#24312C]">No matching saved transactions</h4><p class="mt-2 text-xs text-[#7B8982]">Try changing or resetting filters. Orders without recorded seller-owned items are excluded.</p><a href="{{ route('seller.sales') }}" class="mt-4 inline-block font-semibold text-[#1F6F5B]">Reset Filters</a></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $paginator->links() }}</div>
</section>
