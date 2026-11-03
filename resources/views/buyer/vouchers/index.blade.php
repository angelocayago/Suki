@extends('layouts.app')
@section('content')
<style>
.buyer-vouchers{max-width:1100px;margin:auto;padding:32px 20px;color:#173f35}.bv-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}.bv-tabs a,.bv-action{border:1px solid #1f6f5b;border-radius:8px;padding:10px 16px;display:inline-block}.bv-tabs [aria-current=page],.bv-primary{background:#1f6f5b;color:white}.bv-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:18px}.bv-card{background:white;border:1px solid #dce7e1;border-radius:14px;padding:20px;overflow-wrap:anywhere}.bv-card h2{font-size:20px;font-weight:700;margin:8px 0}.bv-card p,.bv-card dl{margin:8px 0;font-size:14px}.bv-card dt{font-weight:600}.bv-card dd{margin-bottom:8px}.bv-code{width:100%;border:1px dashed #9cb7ab;padding:8px;margin:10px 0;background:#f5faf7}.bv-notice{padding:14px;border:1px solid #a4c5b4;border-radius:8px;margin:16px 0}.buyer-vouchers a:focus-visible,.buyer-vouchers button:focus-visible,.buyer-vouchers input:focus-visible{outline:3px solid #e5ab35;outline-offset:3px}
</style>
<section class="buyer-vouchers">
    <h1 class="text-2xl font-bold">My Vouchers</h1>
    <p>Discover shop offers, claim them, and apply them to eligible merchandise at checkout.</p>
    <p><a class="underline" href="{{ route('buyer.stores.following') }}">Followed stores</a> · <a class="underline" href="{{ route('buyer.cart') }}">Shopping cart</a></p>
    @if(session('success'))<p class="bv-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="bv-notice" role="alert">{{ $errors->first() }}</div>@endif
    <nav class="bv-tabs" aria-label="Voucher sections">
        @foreach(['available'=>'Available Vouchers','mine'=>'My Vouchers','history'=>'Used / Expired'] as $key=>$label)
            <a href="{{ route('buyer.vouchers.index',['tab'=>$key]) }}" @if($tab===$key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="bv-grid">
        @forelse($vouchers as $voucher)
            @php
                $state = \App\Support\SellerVouchers::status($voucher);
                if($voucher->per_buyer_limit !== null && $voucher->buyer_uses >= $voucher->per_buyer_limit) $state = 'Buyer usage limit reached';
                if($voucher->seller?->status !== 'approved' || $voucher->seller?->owner?->status !== 'active' || $voucher->seller?->owner?->is_suspended) $state = 'Shop unavailable';
            @endphp
            <article class="bv-card" id="voucher-{{ $voucher->id }}">
                <p>{{ $voucher->seller?->name ?? 'Issuing shop unavailable' }}</p>
                <h2>{{ $voucher->discount_type==='PERCENTAGE' ? \App\Support\SellerVouchers::decimal($voucher->discount_bps).'%' : 'PHP '.number_format($voucher->discount_minor/100,2) }} OFF</h2>
                @if($voucher->name)<p class="font-semibold">{{ $voucher->name }}</p>@endif
                @if($voucher->description)<p>{{ $voucher->description }}</p>@endif
                <label for="voucher-code-{{ $voucher->id }}">Voucher code</label>
                <input class="bv-code" id="voucher-code-{{ $voucher->id }}" readonly value="{{ $voucher->code }}">
                <dl>
                    <dt>Minimum spend at this shop</dt><dd>PHP {{ number_format($voucher->minimum_spend_minor/100,2) }}</dd>
                    @if($voucher->max_discount_minor !== null)<dt>Maximum discount</dt><dd>PHP {{ number_format($voucher->max_discount_minor/100,2) }}</dd>@endif
                    <dt>Expires</dt><dd>{{ $voucher->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'No expiration' }}</dd>
                    <dt>Eligibility</dt><dd>{{ $voucher->audience==='FOLLOWERS' ? 'Followers only when claiming; valid claims survive unfollowing.' : ($voucher->audience==='PUBLIC' ? 'Public shop voucher' : 'Existing code-only voucher') }}</dd>
                    <dt>Status</dt><dd>{{ $voucher->claimed ? 'Claimed · ' : '' }}{{ ucfirst($state) }}@if($voucher->buyer_uses) · Used {{ $voucher->buyer_uses }} time(s)@endif</dd>
                    <dt>Per-Buyer usage limit</dt><dd>{{ $voucher->per_buyer_limit ?? 'Unlimited' }}</dd>
                </dl>
                @if($tab==='available')
                    <form method="POST" action="{{ route('buyer.vouchers.claim',$voucher) }}" data-claim-voucher>@csrf<button class="bv-action bv-primary" type="submit">Claim Voucher</button></form>
                @elseif($tab==='mine')
                    <button type="button" class="bv-action" data-copy-voucher="voucher-code-{{ $voucher->id }}">Copy Code</button>
                @endif
                <p data-copy-status role="status" aria-live="polite"></p>
            </article>
        @empty
            <p class="bv-card">{{ $tab==='available' ? 'No eligible vouchers to claim. Follow more shops or check back later.' : ($tab==='mine' ? 'No claimed vouchers are currently available to use.' : 'No used or expired vouchers yet.') }}</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $vouchers->links() }}</div>
</section>
@include('buyer.vouchers.script')
@endsection
