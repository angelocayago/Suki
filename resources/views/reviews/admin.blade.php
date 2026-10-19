@extends('admin.layout')
@section('title', 'Review moderation')
@section('content')
<main class="mx-auto max-w-4xl p-6">
    <h1 class="text-2xl font-bold">Review moderation</h1>
    @if (session('success')) <p>{{ session('success') }}</p> @endif
    @if ($errors->any()) <div role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
    @forelse ($reviews as $review)
        <article class="my-4 border rounded p-4">
            <h2>Review #{{ $review->id }} · {{ ucfirst($review->kind) }} · {{ $review->rating }}/5 · {{ $review->hidden_at ? 'Hidden' : 'Visible' }}</h2>
            <p>{{ $review->body }}</p>
            @if ($review->seller_response)<p>Seller response: {{ $review->seller_response }}</p>@endif
            <form method="POST" action="{{ route('reviews.moderate', $review) }}">
                @csrf
                <input type="hidden" name="action" value="{{ $review->hidden_at ? 'restore' : 'hide' }}">
                <label>Reason <input name="reason" required maxlength="500" class="border rounded p-2"></label>
                <button type="submit" class="underline">{{ $review->hidden_at ? 'Restore' : 'Hide' }}</button>
            </form>
            @foreach ($events->get($review->id, []) as $event)
                <p>{{ $event->action }} by admin #{{ $event->admin_id }}: {{ $event->reason }} · {{ $event->created_at }}</p>
            @endforeach
        </article>
    @empty <p>No reviews yet.</p> @endforelse
    {{ $reviews->links() }}
</main>
@endsection
