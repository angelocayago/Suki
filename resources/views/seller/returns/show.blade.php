@extends('layouts.seller')
@section('title', 'Return / Refund Request')
@section('page-title', 'Return / Refund Request')
@section('content')
@include('seller.returns.style')
@php($return = $returnRefundRequest)
<div class="seller-returns">
    <a class="rr-back" href="{{ route('seller.return-refunds.index') }}">&larr; Returns / Refunds</a>
    <header class="rr-heading"><div><span class="rr-eyebrow">{{ $return->type === 'RETURN' ? 'Return request' : ($return->type === 'REFUND' ? 'Refund request' : $return->type) }}</span><h1>Request RR-{{ $return->id }}</h1><p>Submitted {{ $return->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') ?? 'date unavailable' }} PHT</p></div><span class="rr-badge rr-status-{{ strtolower($return->status) }}">{{ $statuses[$return->status] ?? str_replace('_', ' ', $return->status) }}</span></header>
    <div class="rr-detail-grid"><div class="rr-detail-main">
        <section class="rr-card"><h2>Request details</h2><dl class="rr-facts">
            <div><dt>Buyer</dt><dd>{{ $return->buyer?->full_name ?: ($return->buyer?->name ?? 'Buyer') }}</dd></div>
            <div><dt>Order reference</dt><dd><a href="{{ route('seller.orders.show', $return->seller_order_id) }}">{{ $return->order?->order_number ?: ($return->order?->reference ?? '#'.$return->order_id) }}</a></dd></div>
            <div><dt>Product</dt><dd>{{ $return->item?->product_name ?? 'Item unavailable' }}</dd></div>
            <div><dt>Quantity requested</dt><dd>{{ $return->quantity }}</dd></div>
            <div><dt>Requested resolution</dt><dd>{{ $return->type === 'RETURN' ? 'Return' : ($return->type === 'REFUND' ? 'Refund' : $return->type) }}</dd></div>
            <div><dt>Reason</dt><dd>{{ $return->buyer_reason }}</dd></div>
        </dl><div class="rr-divider"><h3>Buyer description</h3><p class="rr-preserve">{{ $return->buyer_description }}</p></div></section>
        <section class="rr-card"><h2>Private evidence</h2><div class="rr-evidence">
            @forelse($evidence as $file)<a href="{{ route('return-refunds.evidence.show', [$return, $file]) }}"><strong>Download evidence {{ $loop->iteration }}</strong><span>{{ $file->description ?: 'Buyer attachment' }}</span><small>{{ $file->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</small></a>@empty<p class="rr-muted">No evidence attached.</p>@endforelse
        </div></section>
        <section class="rr-card"><h2>Request timeline</h2><ol class="rr-timeline">
            @forelse($return->events as $event)<li><strong>{{ $statuses[$event->action] ?? str_replace('_', ' ', $event->action) }}</strong><time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') ?? 'Date unavailable' }} PHT</time></li>@empty<li class="rr-muted">No recorded status history.</li>@endforelse
        </ol></section>
    </div><div class="rr-detail-side">
        <section class="rr-card"><h2>Seller recommendation</h2><p class="rr-muted">Your recommendation helps Admin review this request. Admin makes the final decision.</p>
            @if($errors->any())<div class="rr-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @if($return->seller_recommendation)<div class="rr-divider"><strong>{{ $return->seller_recommendation === 'APPROVE' ? 'Recommend approval' : 'Recommend rejection' }}</strong><p class="rr-preserve">{{ $return->seller_note }}</p><small class="rr-muted">{{ $return->seller_responded_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</small></div>@endif
            @if($return->status === 'SUBMITTED')
                <form id="rrRecommendation" method="POST" action="{{ route('seller.return-refunds.respond', $return) }}" class="rr-recommendation">
                    @csrf @method('PATCH')
                    <label for="rrRecommendationValue">Recommendation <span aria-hidden="true">*</span></label><select id="rrRecommendationValue" name="recommendation" required><option value="APPROVE" @selected(old('recommendation') === 'APPROVE')>Recommend approval</option><option value="REJECT" @selected(old('recommendation') === 'REJECT')>Recommend rejection</option></select>
                    <label for="rrNote">Explanation <span aria-hidden="true">*</span></label><textarea id="rrNote" name="note" required maxlength="5000" rows="4" placeholder="Explain your recommendation for Admin review.">{{ old('note') }}</textarea>
                    <p id="rrSubmitStatus" role="status" aria-live="polite" hidden></p><div id="rrSubmitError" class="rr-feedback" role="alert" hidden></div>
                    <button id="rrSubmit" class="rr-button" type="submit">Review recommendation</button>
                    <noscript><p>Submitting records your recommendation for Admin review.</p><label><input type="checkbox" required> I confirm this recommendation.</label></noscript>
                </form>
                <dialog id="rrConfirm" class="rr-confirm" aria-labelledby="rrConfirmTitle" aria-describedby="rrConfirmDescription"><h2 id="rrConfirmTitle">Submit recommendation?</h2><p id="rrConfirmDescription">This records your recommendation for Admin review. It cannot be submitted again.</p><strong id="rrConfirmChoice"></strong><div class="rr-actions"><button type="button" id="rrCancel" class="rr-button rr-secondary">Go back</button><button type="button" id="rrConfirmSubmit" class="rr-button">Submit recommendation</button></div></dialog>
            @elseif(!$return->seller_recommendation)<p class="rr-muted rr-divider">No seller recommendation was submitted. This request is no longer awaiting your response.</p>@endif
        </section>
        <section class="rr-card"><h2>Admin decision</h2>
            @if($return->admin_decision)<p><span class="rr-badge rr-status-{{ strtolower($return->status) }}">{{ $return->admin_decision === 'APPROVED' ? 'Approved — Refund Due' : 'Rejected by Admin' }}</span></p><p class="rr-preserve">{{ $return->admin_reason }}</p><small class="rr-muted">{{ $return->decided_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</small>
                @if($return->admin_decision === 'APPROVED')<div class="rr-divider"><span class="rr-eyebrow">Buyer refund due</span><strong class="rr-money">{{ $return->refund_minor !== null ? '₱'.number_format($return->refund_minor / 100, 2) : 'Unavailable' }}</strong><p class="rr-muted">Approval records an amount due. It does not confirm payment to the buyer.</p></div>@endif
            @else<p class="rr-muted">Awaiting Admin decision.</p>@endif
        </section>
    </div></div>
</div>
@include('seller.returns.script')
@endsection
