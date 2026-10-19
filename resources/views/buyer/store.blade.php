@extends('layouts.app')
@section('title', $seller->name)
@section('content')
<div class="mx-auto max-w-6xl space-y-5 p-4"><section class="rounded border bg-white p-5"><div class="flex items-start justify-between gap-4"><div>@if($seller->logo_path)<img src="{{ asset('storage/'.$seller->logo_path) }}" alt="" class="mb-3 h-16 w-16 rounded object-cover">@endif<h1 class="text-2xl font-bold">{{ $seller->name }}</h1><p class="text-sm text-gray-600">{{ $seller->category?->name }}</p>@if($seller->description)<p class="mt-2">{{ $seller->description }}</p>@endif</div>@auth @if(auth()->user()->isBuyer())<form method="POST" action="{{ $following ? route('buyer.stores.unfollow', $seller) : route('buyer.stores.follow', $seller) }}">@csrf @if($following) @method('DELETE') @endif<button class="rounded border px-3 py-2">{{ $following ? 'Unfollow' : 'Follow store' }}</button></form>@endif @endauth</div></section><section><h2 class="mb-3 text-xl font-bold">Products</h2><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@forelse($products as $product)<a class="rounded border bg-white p-3" href="{{ route('buyer.product', $product->slug) }}"><p class="font-semibold">{{ $product->name }}</p><p class="text-sm">{{ $product->category?->name }}</p><p>₱{{ number_format(($product->variants->first()?->price_minor ?? 0)/100,2) }}</p></a>@empty<p>No visible products.</p>@endforelse</div><div class="mt-4">{{ $products->links() }}</div></section></div>
<section class="mx-auto max-w-6xl p-4">
    <h2 class="text-xl font-bold">Store reviews ({{ $seller->visible_reviews_count }})</h2>
    @if ($seller->visible_reviews_count)<p>{{ number_format((float) $seller->visible_reviews_avg_rating, 1) }}/5</p>@endif
    @forelse ($reviews as $review)
        <article class="border-b py-4"><p>{{ $review->rating }}/5</p><p>{{ $review->body }}</p>@if($review->seller_response)<p>Seller response: {{ $review->seller_response }}</p>@endif</article>
    @empty <p>No store reviews yet.</p> @endforelse
    {{ $reviews->links() }}
</section>
@endsection
