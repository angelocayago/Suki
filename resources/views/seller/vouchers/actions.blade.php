<div class="flex flex-wrap gap-2">
    <a href="{{ route('seller.vouchers.show', $voucher->id) }}" class="rounded-lg border border-[#DDE6E1] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">View</a>
    @if($voucher->archived_at === null)
        <a href="{{ route('seller.vouchers.edit', $voucher->id) }}" class="rounded-lg border border-[#DDE6E1] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">Edit</a>
        @foreach([$voucher->status === 'active' ? 'pause' : 'activate', 'archive'] as $action)
            <form method="POST" action="{{ route('seller.vouchers.'.$action, $voucher->id) }}">@csrf<button type="submit" class="rounded-lg border border-[#DDE6E1] px-3 py-2 text-xs font-semibold text-[#1F6F5B]">{{ ucfirst($action) }}</button></form>
        @endforeach
    @endif
</div>
