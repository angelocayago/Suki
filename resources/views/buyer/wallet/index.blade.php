@extends('layouts.app')
@section('content')
<x-buyer.account-shell active="wallet">
    @php($money = fn(int $minor)=>\App\Support\SellerWalletPage::money($minor))
    <h1 class="text-2xl font-bold text-[#173F35]">Suki Wallet</h1>
    <p class="mt-2 text-sm text-gray-600">Your internal SUKI SHOP balance. This is not a bank account or confirmation of an external cash transfer.</p>
    <div class="my-6 grid gap-4 sm:grid-cols-2">
        <section class="rounded-2xl border bg-white p-5"><h2 class="font-semibold">Suki Wallet Balance</h2><p class="mt-2 text-2xl font-bold text-[#1F6F5B]">₱{{ $money($wallet['balance_minor']) }}</p></section>
        <section class="rounded-2xl border bg-white p-5"><h2 class="font-semibold">Available to Spend</h2><p class="mt-2 text-2xl font-bold text-[#1F6F5B]">₱{{ $money($checkoutAvailable ? $wallet['available_minor'] : 0) }}</p>@unless($checkoutAvailable)<p class="mt-2 text-sm text-gray-600">Wallet checkout is currently unavailable. Your credited balance is retained.</p>@endunless</section>
    </div>
    <p class="mb-5 rounded-xl border border-[#D6E8E1] bg-[#F1F8F5] p-4 text-sm">Approved refunds are not spendable until funding is verified and the wallet credit is posted. {{ $checkoutAvailable ? 'Use your available backed balance for eligible single-shop purchases, including shipping.' : 'Wallet payments at Checkout are not available yet.' }}</p>
    <section class="mb-6 overflow-hidden rounded-2xl border bg-white">
        <h2 class="p-5 font-semibold">Recent Transactions</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-y"><th class="p-3">Date (PHT)</th><th class="p-3">Type</th><th class="p-3">Reference</th><th class="p-3">Amount</th><th class="p-3">Status</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr class="border-b"><td class="p-3">{{ \App\Support\ManilaTime::format($entry->created_at) }}</td><td class="p-3">{{ ['REFUND_CREDIT'=>'Refund Credit','ORDER_PAYMENT'=>'Order Payment','PAYMENT_REVERSAL'=>'Payment Reversal'][$entry->type] }}</td><td class="p-3">@if($entry->returnRequest)<a class="underline" href="{{ route('buyer.return-refunds.show',$entry->returnRequest) }}">RR-{{ $entry->returnRequest->id }}</a><div class="text-xs text-gray-600">{{ $entry->returnRequest->order?->reference }}</div>@elseif($entry->order){{ $entry->order->order_number ?? $entry->order->reference }}@endif</td><td class="p-3 whitespace-nowrap">₱{{ $money($entry->amount_minor) }}</td><td class="p-3">{{ $entry->status }}</td></tr>
        @empty<tr><td colspan="5" class="p-6 text-gray-600">No wallet transactions yet. Your starting balance is ₱0.00.</td></tr>@endforelse
        </tbody></table></div><div class="p-4">{{ $entries->links() }}</div>
    </section>
    <section class="overflow-hidden rounded-2xl border bg-white"><h2 class="p-5 font-semibold">Approved Refunds</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-y"><th class="p-3">Request</th><th class="p-3">Refund amount</th><th class="p-3">Funding status</th></tr></thead><tbody>
        @forelse($fundings as $funding)<tr class="border-b"><td class="p-3">@if($funding->returnRequest)<a class="underline" href="{{ route('buyer.return-refunds.show',$funding->returnRequest) }}">RR-{{ $funding->returnRequest->id }}</a>@endif</td><td class="p-3">₱{{ $money($funding->buyer_refund_minor) }}</td><td class="p-3">{{ $funding->statusLabel() }}</td></tr>
        @empty<tr><td colspan="3" class="p-6 text-gray-600">No refund funding records yet.</td></tr>@endforelse
        </tbody></table></div><div class="p-4">{{ $fundings->links() }}</div>
    </section>
    @if($historical->total())
    <section class="mt-6 overflow-hidden rounded-2xl border bg-white">
        <h2 class="p-5 font-semibold">Approved Refunds Without a Wallet Funding Record</h2>
        <p class="px-5 pb-4 text-sm text-gray-600">These existing approvals are shown for reference only. Funding and wallet credit have not been verified here. They do not increase your spendable balance.</p>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-y"><th class="p-3">Request / Order</th><th class="p-3">Approved amount</th><th class="p-3">Wallet status</th></tr></thead><tbody>
        @foreach($historical as $refund)<tr class="border-b"><td class="p-3"><a class="underline" href="{{ route('buyer.return-refunds.show',$refund) }}">RR-{{ $refund->id }}</a><div class="text-xs text-gray-600">{{ $refund->order?->reference }}</div></td><td class="p-3">₱{{ $money($refund->refund_minor) }}</td><td class="p-3">No wallet funding record — not credited here</td></tr>@endforeach
        </tbody></table></div><div class="p-4">{{ $historical->links() }}</div>
    </section>
    @endif
</x-buyer.account-shell>
@endsection
