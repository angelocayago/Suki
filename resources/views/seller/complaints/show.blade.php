@extends('layouts.seller')
@section('title', 'Complaint Details')
@section('page-title', 'Complaints')
@section('content')
@include('seller.returns.style')
@include('seller.complaints.style')
<div class="seller-returns seller-complaints">
    <a class="rr-back" href="{{ route('seller.complaints.index') }}">&larr; Complaints</a>
    <header class="rr-heading"><div><span class="rr-eyebrow">Complaint details</span><h1>{{ $complaint->reference }}</h1><p>Submitted {{ $complaint->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</p></div><span class="rr-badge cmp-{{ strtolower($complaint->status->value) }}">{{ str($complaint->status->value)->lower()->replace('_', ' ')->headline() }}</span></header>
    @if(session('success'))<div class="cmp-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rr-feedback" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($complaint->assigned_to && $complaint->complainant_role === 'SELLER')
        <form class="cmp-contact" method="POST" action="{{ route('complaints.messages.context.store', $complaint) }}">@csrf<button class="rr-button rr-secondary" type="submit">Message Admin</button></form>
    @endif
    <div class="rr-detail-grid"><div class="rr-detail-main">
        <section class="rr-card"><h2>{{ $complaint->subject }}</h2><dl class="rr-facts">
            <div><dt>Category</dt><dd>{{ str($complaint->category->value)->lower()->replace('_', ' ')->headline() }}</dd></div>
            <div><dt>Complainant</dt><dd>{{ $complaint->complainant?->full_name ?: ($complaint->complainant?->name ?? 'Complainant unavailable') }}</dd></div>
            <div><dt>Order reference</dt><dd>{{ $complaint->order?->order_number ?? 'Not linked to an order' }}</dd></div>
            <div><dt>Product</dt><dd>{{ $complaint->product?->name ?? 'Not linked to a product' }}</dd></div>
            @if($complaint->sellerOrder)<div><dt>Seller order</dt><dd><a href="{{ route('seller.orders.show', $complaint->sellerOrder->id) }}">View your order &rarr;</a></dd></div>@endif
            @if($complaint->waiting_on)<div><dt>Waiting for</dt><dd>{{ str($complaint->waiting_on->value)->lower()->replace('_', ' ')->headline() }}</dd></div>@endif
        </dl><div class="rr-divider"><h3>Description</h3><p class="rr-preserve">{{ $complaint->description }}</p></div>
            @if($complaint->status->isTerminal())<div class="rr-divider"><h3>{{ $complaint->status === \App\Enums\ComplaintStatus::Resolved ? 'Resolved' : 'Dismissed' }}</h3><p>{{ $complaint->resolved_at?->timezone('Asia/Manila')->format('M j, Y · g:i A') ?? 'No closure date recorded.' }}{{ $complaint->resolved_at ? ' PHT' : '' }}</p></div>@endif
            <p class="rr-muted rr-divider">Admin manages review and closure. Administrative notes and assignments remain private.</p>
        </section>
        <section class="rr-card"><h2>Status history</h2><ol class="rr-timeline">@forelse($complaint->events as $event)<li><strong>{{ str($event->action)->lower()->replace('_', ' ')->headline() }}</strong><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone('Asia/Manila')->format('M j, Y · g:i A') }} PHT</time>@if($event->previous_status || $event->new_status)<span>{{ $event->previous_status?->value ?? 'New' }} &rarr; {{ $event->new_status?->value ?? 'No status change' }}</span>@endif @if($event->waiting_on)<span>Waiting for {{ str($event->waiting_on->value)->lower()->replace('_', ' ')->headline() }}</span>@endif</li>@empty<li class="rr-muted">No recorded status history.</li>@endforelse</ol></section>
    </div><div class="rr-detail-side">
        <section class="rr-card"><h2>My evidence</h2><p class="rr-muted">Only you and administrators can access evidence you upload.</p><div class="cmp-evidence">
            @forelse($complaint->evidence as $evidence)<div class="rr-divider"><strong>{{ $evidence->original_name }}</strong><small>{{ number_format($evidence->size / 1024, 1) }} KB · {{ $evidence->mime_type }}</small>@if($evidence->description)<p class="rr-preserve">{{ $evidence->description }}</p>@endif<div class="rr-actions"><a href="{{ route('complaints.evidence.show', [$complaint, $evidence]) }}" target="_blank" rel="noopener">View</a><a href="{{ route('complaints.evidence.download', [$complaint, $evidence]) }}">Download</a></div></div>@empty<p class="rr-muted">No evidence uploaded.</p>@endforelse
        </div>
        @if(!$complaint->status->isTerminal())<form class="cmp-evidence-form rr-divider" method="POST" enctype="multipart/form-data" action="{{ route('complaints.evidence.store', $complaint) }}">
            @csrf<label for="complaint-evidence">Add evidence</label><input id="complaint-evidence" type="file" name="evidence" required accept="{{ '.'.implode(',.', config('complaints.evidence_extensions')) }}">
            <label for="evidence-description">Description (optional)</label><textarea id="evidence-description" name="description" maxlength="1000" rows="3">{{ old('description') }}</textarea>
            <p class="rr-muted">{{ strtoupper(implode(', ', config('complaints.evidence_extensions'))) }}. Maximum {{ number_format(config('complaints.evidence_max_kilobytes') / 1024, 0) }} MB. Stored privately.</p><button class="rr-button" type="submit">Upload evidence</button>
        </form>@else<p class="rr-muted rr-divider">Evidence uploads are closed for this complaint.</p>@endif
        </section>
    </div></div>
</div>
@endsection
