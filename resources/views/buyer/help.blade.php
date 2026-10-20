@extends('layouts.app')

@section('content')
<section class="mx-auto max-w-6xl px-5 py-10 sm:px-8">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-[#1F6F5B]">Buyer support</p>
            <h1 class="mt-2 text-3xl font-bold text-[#173F35]">Help Center</h1>
            <p class="mt-2 text-sm text-[#52635B]">Helpful guidance for ordering, COD, delivery, and account concerns.</p>
        </div>
        <a href="{{ route('complaints.mine.index') }}" class="inline-flex items-center justify-center rounded-xl bg-[#1F6F5B] px-4 py-3 text-sm font-semibold text-white hover:bg-[#155244]">Report an issue</a>
    </div>

    @if($announcements->isNotEmpty())
        <div class="mb-7 rounded-2xl border border-[#BCE2D2] bg-[#F2FBF6] p-5">
            <h2 class="font-bold text-[#173F35]">Active announcements</h2>
            <ul class="mt-3 space-y-2 text-sm text-[#52635B]">
                @foreach($announcements as $announcement)
                    <li><span class="font-semibold text-[#173F35]">{{ $announcement->title }}:</span> {{ $announcement->message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        @foreach([
            ['Ordering and COD', 'Add available items to your cart, review each seller order at checkout, and pay the Rider only when your order is successfully delivered.'],
            ['Delivery and tracking', 'Use My Orders to follow each seller shipment. Delivery updates, failed attempts, reschedules, and return-to-sender status are shown with the order.'],
            ['Cancellation and delivery issues', 'Use the available cancellation action before fulfillment when eligible. For an order or delivery issue, open the relevant order and choose Submit a complaint so the correct order context is retained.'],
            ['Returns and refunds', 'Returns and refunds are not yet an in-app workflow. Read the policy and use the existing complaint flow for an issue that needs support.'],
            ['Account and profile', 'Use My Account to maintain your permitted profile information. Keep your password and verification details secure.'],
        ] as [$heading, $body])
            <article class="rounded-2xl border border-[#DCE5E0] bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-[#173F35]">{{ $heading }}</h2>
                <p class="mt-2 text-sm leading-6 text-[#52635B]">{{ $body }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-8 flex flex-wrap gap-3 text-sm font-semibold text-[#1F6F5B]">
        <a href="{{ route('buyer.my-orders') }}" class="rounded-xl border border-[#B9D8CC] px-4 py-2 hover:bg-[#F2FBF6]">My Orders &amp; order-specific reports</a>
        <a href="{{ route('policies.show', 'shipping-policy') }}" class="rounded-xl border border-[#B9D8CC] px-4 py-2 hover:bg-[#F2FBF6]">Shipping Policy</a>
        <a href="{{ route('policies.show', 'returns-refund-policy') }}" class="rounded-xl border border-[#B9D8CC] px-4 py-2 hover:bg-[#F2FBF6]">Returns &amp; Refund Policy</a>
    </div>
</section>
@endsection
