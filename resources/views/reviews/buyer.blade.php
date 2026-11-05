@extends('layouts.app')
@section('title', 'My reviews')
@section('content')
<main class="mx-auto max-w-4xl p-4 sm:p-6">
    <h1 class="text-2xl font-bold">My reviews</h1>
    <p class="mt-2 text-sm text-gray-600">Rate your purchased products and the shop separately. Product reviews can be submitted within 30 days of completion and edited once during that window.</p>
    @if($selected)<a href="{{ route('reviews.buyer-page') }}" class="mt-3 inline-block text-sm font-semibold text-[#1F6F5B] underline">All my reviews</a>@endif
    @if (session('success')) <p>{{ session('success') }}</p> @endif
    @if ($errors->any()) <div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
    @forelse ($orders as $order)
        <section class="my-5 rounded-xl border border-gray-200 bg-white p-4 sm:p-6">
            <h2 class="break-words font-bold">{{ $order->seller?->name ?? 'Seller' }} · {{ $order->order->order_number }}</h2>
            <a href="{{ route('buyer.order-details', $order->order->order_number) }}" class="mt-2 inline-block text-sm font-semibold text-[#1F6F5B] underline">Back to Order Details</a>
            @foreach ($order->items as $item)
                @php($review = $reviews->get('item:'.$item->id))
                @php($state = app(\App\Support\BuyerReviews::class)->productState($item, $order, $review, (int) auth()->id()))
                <article id="product-review-{{ $item->id }}" class="mt-5 scroll-mt-24 rounded-lg border border-gray-200 p-4" aria-labelledby="review-item-{{ $item->id }}">
                <h3 id="review-item-{{ $item->id }}" class="break-words font-semibold">{{ $item->product_name }}</h3>
                @if($item->variant_name)<p class="mt-1 text-sm text-gray-600">Purchased variant: {{ $item->variant_name }}</p>@endif
                @if(is_array($item->variation))<dl class="mt-1 text-sm text-gray-600">@foreach(\Illuminate\Support\Arr::dot($item->variation) as $name => $value)<div><dt class="inline">{{ $name }}:</dt> <dd class="inline">{{ $value }}</dd></div>@endforeach</dl>@endif
                @if($review)
                    <p class="mt-3 font-semibold">Your review: {{ $review->rating }}/5</p>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $review->body ?: 'No written comment.' }}</p>
                    @if($review->hidden_at)<p class="mt-2 text-sm">Hidden by moderation. This review is shown here privately to you.</p>@endif
                    @if($review->seller_response)<blockquote class="mt-3 border-l-2 border-[#1F6F5B] pl-3 text-sm"><strong>Seller reply</strong><p>{{ $review->seller_response }}</p></blockquote>@endif
                    @if($state['edit'])
                        <details class="mt-3" @if((int) $editItem === (int) $item->id || old('_review_form') === 'item:'.$item->id) open @endif>
                            <summary class="cursor-pointer font-semibold text-[#1F6F5B]">Edit Review (one edit available)</summary>
                            <form method="POST" action="{{ route('reviews.edit', $review) }}">@csrf @method('PATCH') @include('reviews.fields', ['review' => $review, 'formKey' => 'item:'.$item->id])</form>
                        </details>
                    @else
                        <p class="mt-2 text-sm text-gray-600">Editing no longer available. {{ $order->completed_at && $order->completed_at->lt(now()->subDays(30)) ? 'The 30-day product review window has expired.' : '' }}</p>
                    @endif
                @elseif($state['create'])
                    <p class="mt-3 text-sm text-gray-600">Awaiting product review</p>
                    <form method="POST" action="{{ route('reviews.product', $item) }}">
                        @csrf
                        @include('reviews.fields', ['review' => null, 'formKey' => 'item:'.$item->id])
                    </form>
                @else
                    <p class="mt-3 text-sm text-gray-600">{{ $state['reason'] }}</p>
                @endif
                </article>
            @endforeach
            @php($review = $reviews->get('seller-order:'.$order->id))
            <section id="seller-review-{{ $order->id }}" class="mt-5 scroll-mt-24 rounded-lg border border-gray-200 p-4">
            <h3 class="font-semibold mt-4">Store review</h3>
            <p class="mt-1 text-sm text-gray-600">Rate your experience with {{ $order->seller?->name ?? 'this Seller' }} separately from product quality.</p>
            @if ($review)
                <p class="mt-3 font-semibold">Your Seller rating: {{ $review->rating }}/5</p>
                <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $review->body ?: 'No written comment.' }}</p>
                @if($review->hidden_at)<p class="mt-2 text-sm">Hidden by moderation. This rating is shown here privately to you.</p>@endif
                @if($review->seller_response)<blockquote class="mt-3 border-l-2 border-[#1F6F5B] pl-3 text-sm"><strong>Seller reply</strong><p>{{ $review->seller_response }}</p></blockquote>@endif
                <p class="mt-2 text-sm text-gray-600">Submitted. Seller ratings cannot be edited.</p>
            @elseif(app(\App\Services\TransactionReviewEligibility::class)->order($order, (int) auth()->id()))
                <p class="mt-3 text-sm text-gray-600">Awaiting Seller rating</p>
                <form method="POST" action="{{ route('reviews.seller', $order) }}">@csrf @include('reviews.fields', ['review' => null, 'formKey' => 'seller-order:'.$order->id])</form>
            @else
                <p class="mt-3 text-sm text-gray-600">This transaction is not currently eligible for a Seller rating.</p>
            @endif
            </section>
            @php($review = $reviews->get('rider-order:'.$order->id))
            @php($rider = $riders->get($order->shipment?->delivered_by))
            <h3 class="font-semibold mt-4">Delivery rider</h3>
            @if ($review)
                <p>Submitted: {{ $review->rating }}/5</p>
            @elseif ($rider && $order->shipment?->proof_delivered_at && app(\App\Services\TransactionReviewEligibility::class)->order($order, (int) auth()->id()))
                <p>{{ $rider->user?->name }}</p>
                <form method="POST" action="{{ route('reviews.rider', $order) }}">@csrf @include('reviews.fields', ['review' => null])</form>
            @else
                <p>No verified delivery rider is available.</p>
            @endif
        </section>
    @empty
        <p>No completed transactions yet.</p>
    @endforelse
    {{ $orders->links() }}
</main>
@endsection
