@extends('layouts.seller')
@section('title', 'Reviews')
@section('page-title', 'Reviews')
@section('content')
<main class="mx-auto max-w-4xl p-6">
    <h1 class="text-2xl font-bold">Reviews</h1>
    @if (session('success')) <p>{{ session('success') }}</p> @endif
    @if ($errors->any()) <div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
    @forelse ($reviews as $review)
        <article class="my-4 border rounded p-4">
            <h2>{{ ucfirst($review->kind) }} review · {{ $review->rating }}/5 @if ($review->hidden_at) (hidden) @endif</h2>
            <p>{{ $review->body }}</p>
            <form method="POST" action="{{ route('reviews.respond', $review) }}">
                @csrf
                <label>Response <textarea name="response" required maxlength="500" class="block w-full border rounded p-2">{{ $review->seller_response }}</textarea></label>
                <button class="underline" type="submit">Save response</button>
            </form>
        </article>
    @empty <p>No reviews yet.</p> @endforelse
    {{ $reviews->links() }}
</main>
@endsection
