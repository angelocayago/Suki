@extends('layouts.app')
@section('title', 'My reviews')
@section('content')
<main class="mx-auto max-w-4xl p-6">
    <h1 class="text-2xl font-bold">My reviews</h1>
    @if (session('success')) <p>{{ session('success') }}</p> @endif
    @if ($errors->any()) <div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
    @forelse ($orders as $order)
        <section class="my-5 border rounded p-4">
            <h2 class="font-bold">{{ $order->seller->name }} · {{ $order->order->order_number }}</h2>
            @foreach ($order->items as $item)
                @php($review = $reviews->get('item:'.$item->id))
                <h3 class="font-semibold mt-4">{{ $item->product_name }}</h3>
                @if (! $item->product_id || ! $item->product || $order->completed_at->lt(now()->subDays(30)))
                    <p>This item is not currently eligible for a review.</p>
                @elseif ($review && ($review->edited || $review->hidden_at))
                    <p>Your review is no longer editable.</p>
                @else
                    <form method="POST" action="{{ $review ? route('reviews.edit', $review) : route('reviews.product', $item) }}">
                        @csrf
                        @if ($review) @method('PATCH') @endif
                        @include('reviews.fields', ['review' => $review])
                    </form>
                @endif
            @endforeach
            @php($review = $reviews->get('seller-order:'.$order->id))
            <h3 class="font-semibold mt-4">Store review</h3>
            @if ($review)
                <p>Submitted: {{ $review->rating }}/5</p>
            @else
                <form method="POST" action="{{ route('reviews.seller', $order) }}">@csrf @include('reviews.fields', ['review' => null])</form>
            @endif
            @php($review = $reviews->get('rider-order:'.$order->id))
            @php($rider = $riders->get($order->shipment?->delivered_by))
            <h3 class="font-semibold mt-4">Delivery rider</h3>
            @if ($review)
                <p>Submitted: {{ $review->rating }}/5</p>
            @elseif ($rider && $order->shipment?->proof_delivered_at)
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
