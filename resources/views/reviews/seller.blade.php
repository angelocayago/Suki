@extends('layouts.seller')
@section('title', 'Reviews')
@section('page-title', 'Reviews')
@section('content')
@include('reviews.seller-partials.style')
<div class="seller-reviews">
    <header class="sr-heading"><div><h2>Customer Reviews</h2><p>Manage feedback and connect with your customers.</p></div></header>
    <nav class="sr-kind-tabs" aria-label="Review type">
        @foreach(['product'=>'Product Reviews','shop'=>'Shop Reviews'] as $tab=>$label)
            <a href="{{ route('reviews.seller-page', array_merge(\Illuminate\Support\Arr::except($criteria, $tab === 'shop' ? ['tab','product'] : ['tab']), ['tab'=>$tab])) }}" @if($activeTab === $tab) aria-current="page" @endif>{{ $label }} <span>{{ number_format($summaries[$tab]['total']) }}</span></a>
        @endforeach
    </nav>
    <div class="sr-summary" aria-label="Review inbox summary">
        <section class="sr-summary-card"><h3>{{ $activeTab === 'product' ? 'Average Product Rating' : 'Average Shop Rating' }}</h3><strong>{{ $summary['average'] === null ? 'No ratings yet' : number_format($summary['average'], 1) }}</strong>
            @if($summary['average'] !== null)<div class="sr-rating" role="img" aria-label="{{ number_format($summary['average'], 1) }} out of 5 stars"><span aria-hidden="true">@for($star=1;$star<=5;$star++)<span class="{{ $star <= round($summary['average']) ? 'sr-star-filled' : 'sr-star-empty' }}">&#9733;</span>@endfor</span></div>@endif
            <p>{{ $activeTab === 'product' ? 'Product review inbox' : 'Shop review inbox' }}</p></section>
        <section class="sr-summary-card"><h3>{{ $activeTab === 'product' ? 'Total Product Reviews' : 'Total Shop Reviews' }}</h3><strong>{{ number_format($summary['total']) }}</strong><p>{{ $activeTab === 'product' ? 'Product feedback' : 'Shop and service feedback' }}</p></section>
        <section class="sr-summary-card"><h3>{{ $activeTab === 'product' ? 'Product Reviews Needing Reply' : 'Shop Reviews Needing Reply' }}</h3><strong>{{ number_format($summary['needs_reply']) }}</strong><p>Unanswered reviews</p></section>
    </div>
    <p class="sr-summary-note">Summary covers all {{ $activeTab === 'product' ? 'Product Reviews' : 'Shop Reviews' }} in your inbox, including reviews hidden by moderation, before search or reply filters. Public product and shop ratings remain separate.</p>
    @if(session('success'))<p class="sr-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sr-errors" role="alert"><strong>Please review the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('reviews.seller-partials.filters')
    <nav class="sr-status-tabs" aria-label="Reply status">
        @foreach(['' => ['All Reviews', $summary['total']], 'needs_reply' => ['Needs Reply', $summary['needs_reply']], 'replied' => ['Replied', $summary['replied']]] as $status => [$label, $count])
            <a href="{{ route('reviews.seller-page', array_merge(\Illuminate\Support\Arr::except($criteria, 'reply'), $status ? ['reply'=>$status] : [])) }}" @if(($criteria['reply'] ?? '') === $status) aria-current="page" @endif>{{ $label }} <span>{{ number_format($count) }}</span></a>
        @endforeach
    </nav>
    <p class="sr-result-count">{{ $reviews->total() }} matching review(s)</p>
    @forelse($reviews as $review)
        @php($replyFailed = $errors->has('response') && (string) old('_reply_review') === (string) $review['id'])
        <article class="sr-card" id="review-{{ $review['id'] }}" aria-labelledby="reviewer-{{ $review['id'] }}">
            <header class="sr-reviewer">
                <span class="sr-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($review['buyer'],0,1)) }}</span>
                <div class="sr-reviewer-info"><div class="sr-reviewer-line"><h3 id="reviewer-{{ $review['id'] }}">{{ $review['buyer'] }}</h3><span class="sr-reply-status {{ filled($review['response']) ? 'is-replied' : 'needs-reply' }}">{{ filled($review['response']) ? 'Replied' : 'Needs Reply' }}</span>@if($review['hidden'])<span class="sr-hidden-badge">Hidden by moderation</span>@endif</div>
                    @if($review['date'])<time datetime="{{ $review['date']->toIso8601String() }}">{{ \App\Support\ManilaTime::format($review['date'], 'M d, Y') }} &middot; {{ \App\Support\ManilaTime::format($review['date'], 'h:i A') PHT }}</time>@endif
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
            <details class="sr-reply-editor" @if($replyFailed || !filled($review['response'])) open @endif><summary>{{ filled($review['response']) ? 'Edit reply' : 'Reply to review' }}</summary>
                <form method="POST" action="{{ route('reviews.respond',$review['id']) }}" data-review-reply>
                    @csrf
                    <input type="hidden" name="_reply_review" value="{{ $review['id'] }}">
                    @foreach($criteria as $key=>$value)<input type="hidden" name="_review_filters[{{ $key }}]" value="{{ $value }}">@endforeach
                    <p class="sr-quick-heading">Quick replies <span>Choose a starting point, then edit freely.</span></p>
                    <div class="sr-quick-replies" aria-label="Quick reply templates">
                        @foreach([
                            'Thank you!' => 'Thank you for your review! We appreciate your support and hope to serve you again.',
                            'Salamat po' => 'Maraming salamat po sa inyong review at suporta sa aming shop!',
                            'Appreciate support' => 'We truly appreciate your feedback and support. Thank you for choosing our shop!',
                            'Great rating' => "Thank you for the great rating! We're glad you had a positive experience.",
                        ] as $label=>$message)
                            <button type="button" data-review-template="{{ $message }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <label for="reply-{{ $review['id'] }}">Your reply</label><textarea id="reply-{{ $review['id'] }}" name="response" rows="3" required maxlength="500" aria-describedby="reply-error-{{ $review['id'] }}" aria-invalid="{{ $replyFailed ? 'true' : 'false' }}">{{ $replyFailed ? old('response') : $review['response'] }}</textarea>
                    <p id="reply-error-{{ $review['id'] }}" class="sr-field-error" role="alert">@if($replyFailed){{ $errors->first('response') }}@endif</p>
                    <p class="sr-template-status" data-review-template-status role="status" aria-live="polite"></p>
                    <div class="sr-reply-actions"><span data-review-counter aria-live="polite">{{ mb_strlen((string)($replyFailed ? old('response') : $review['response'])) }}/500 characters</span><div class="sr-composer-actions"><button type="button" class="sr-secondary-button" data-review-undo hidden>Undo template</button><button type="button" class="sr-secondary-button" data-review-cancel>Cancel</button><button type="submit">Save reply</button></div></div>
                </form>
            </details>
        </article>
    @empty
        <section class="sr-card sr-empty"><h3>{{ count($chips) ? 'No reviews match your filters.' : 'No reviews yet.' }}</h3><p>No {{ $activeTab === 'product' ? 'Product Reviews' : 'Shop Reviews' }} {{ count($chips) ? 'match these filters.' : 'have been received yet.' }}</p>@if(count($chips))<a href="{{ route('reviews.seller-page', ['tab'=>$activeTab]) }}">Clear Filters</a>@endif</section>
    @endforelse
    <div class="sr-pagination">{{ $reviews->links() }}</div>
</div>
@include('reviews.seller-partials.script')
@endsection
