@extends('layouts.seller')
@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-subtitle', 'Marketplace conversations and support messages')
@section('content')
@php
    $inboxPresenter=app(\App\Support\SellerInbox::class);
    $listQuery=$criteria + ($memberships->currentPage()>1 ? ['page'=>$memberships->currentPage()] : []);
@endphp
@include('seller.messages.style')
<div class="seller-inbox {{ $conversation ? 'has-thread' : '' }}" id="sellerInbox">
    @include('seller.messages.partials.list')
    @if($conversation)
        @include('seller.messages.partials.thread')
    @else
        <section class="inbox-selection" aria-label="Conversation"><div><i data-lucide="messages-square" aria-hidden="true"></i><h2>Select a conversation</h2><p>Choose a conversation to read and reply.</p></div></section>
    @endif
</div>
@include('messages.partials.attachment-style')
@include('messages.partials.attachment-lightbox')
@include('messages.partials.attachment-script')
@include('seller.messages.script')
@endsection
