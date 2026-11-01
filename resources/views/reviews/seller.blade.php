@extends('layouts.seller')
@section('title', 'Reviews')
@section('page-title', 'Reviews')
@section('content')
@include('reviews.seller-partials.style')
<div class="seller-reviews">
    <header class="sr-heading"><h2>Customer Reviews</h2><p>{{ $reviews->total() }} review(s)</p></header>
    @if(session('success'))<p class="sr-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sr-errors" role="alert"><strong>Please review the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('reviews.seller-partials.filters')
    @forelse($reviews as $review)
        @php($replyFailed = $errors->has('response') && (string) old('_reply_review') === (string) $review['id'])
        <article class="sr-card" id="review-{{ $review['id'] }}" aria-labelledby="reviewer-{{ $review['id'] }}">
            <header class="sr-reviewer">
                <span class="sr-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($review['buyer'],0,1)) }}</span>
                <div class="sr-reviewer-info"><div class="sr-reviewer-line"><h3 id="reviewer-{{ $review['id'] }}">{{ $review['buyer'] }}</h3>@if($review['hidden'])<span class="sr-hidden-badge">Hidden by moderation</span>@endif</div>
                    @if($review['date'])<time datetime="{{ $review['date']->toIso8601String() }}">{{ $review['date']->format('M d, Y') }} &middot; {{ $review['date']->format('h:i A') }}</time>@endif
                    <div class="sr-rating" role="img" aria-label="{{ $review['rating'] }} out of 5 stars"><span aria-hidden="true">@for($star=1;$star<=5;$star++)<span class="{{ $star <= $review['rating'] ? 'sr-star-filled' : 'sr-star-empty' }}">&#9733;</span>@endfor</span><span class="sr-rating-number">{{ $review['rating'] }}/5</span></div>
                </div>
            </header>
            <div class="sr-product-header">
                @if($review['image'])<img class="sr-product-image" src="{{ $review['image'] }}" alt="" loading="lazy" data-review-product-image>@endif
                <div class="sr-product-info"><span class="sr-group-label">{{ $review['kind'] === 'product' ? 'Product review' : 'Shop review' }}</span>
                    @if($review['product'])<h4>{{ $review['product'] }}</h4>@endif
                    @if($review['variant'])<p class="sr-variant">Variation: {{ $review['variant'] }}</p>@endif
                    @if($review['options'])<dl class="sr-options">@foreach($review['options'] as $name=>$value)<div><dt>{{ $name }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>@endif
                </div>
            </div>
            @if(filled($review['body']))<p class="sr-review-body">{{ $review['body'] }}</p>@else<p class="sr-muted">No written review.</p>@endif
            @if(filled($review['response']))<section class="sr-seller-reply" aria-label="Seller reply"><h4>Seller reply</h4><p class="sr-reply-body">{{ $review['response'] }}</p></section>@endif
            <details class="sr-reply-editor" @if($replyFailed) open @endif><summary>{{ filled($review['response']) ? 'Edit reply' : 'Reply to review' }}</summary>
                <form method="POST" action="{{ route('reviews.respond',$review['id']) }}" data-review-reply>
                    @csrf
                    <input type="hidden" name="_reply_review" value="{{ $review['id'] }}">
                    <label for="reply-{{ $review['id'] }}">Your reply</label><textarea id="reply-{{ $review['id'] }}" name="response" rows="3" required maxlength="500" aria-describedby="reply-error-{{ $review['id'] }}" aria-invalid="{{ $replyFailed ? 'true' : 'false' }}">{{ $replyFailed ? old('response') : $review['response'] }}</textarea>
                    <p id="reply-error-{{ $review['id'] }}" class="sr-field-error">@if($replyFailed){{ $errors->first('response') }}@endif</p>
                    <div class="sr-reply-actions"><span>Up to 500 characters</span><button type="submit">Save reply</button></div>
                </form>
            </details>
        </article>
    @empty
        <section class="sr-card sr-empty"><h3>{{ count($chips) ? 'No reviews match your filters.' : 'No reviews yet.' }}</h3>@if(count($chips))<a href="{{ route('reviews.seller-page') }}">Clear Filters</a>@endif</section>
    @endforelse
    <div class="sr-pagination">{{ $reviews->links() }}</div>
</div>
@include('reviews.seller-partials.script')
@endsection
