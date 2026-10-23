@extends('layouts.seller')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('content')
@php
    $filterId = 'notifications-advanced-filters';
    $filtersOpen = count($filterChips) > 0 || $errors->any();
@endphp
<div data-seller-filters>
    <nav aria-label="Breadcrumb" class="mb-3 text-xs text-[#728078]"><a href="{{ route('seller.dashboard') }}">Dashboard</a> / Notifications</nav>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl font-semibold text-[#24312C]">Notifications</h2><div class="flex flex-wrap items-center gap-3">@include('seller.partials.filter-trigger')<form method="POST" action="{{ route('seller.notifications.read-all') }}">@csrf<button class="rounded-xl bg-[#1F6F5B] px-4 py-2 text-xs font-semibold text-white">Mark All as Read</button></form></div></div>
    @if(session('success'))<p role="status" class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<ul role="alert" class="mb-4 text-sm text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <p class="mb-4 text-xs leading-5 text-[#728078]">Saved notifications for your shop. Opening this page does not mark anything as read. New voucher configurations and incoming Buyer messages generate notifications; account decisions, order events, stock warnings and scheduled expiry alerts are not connected. Chat and notification read states are independent. Counts refresh on page load; live push is not enabled.</p>
    <section class="overflow-hidden rounded-2xl border border-[#E1E8E4] bg-white">
        @include('seller.partials.advanced-filter-panel', ['filterForm' => 'seller.partials.notification-filters'])
        @include('seller.partials.active-filter-chips')
        <p class="border-b border-[#EDF1EF] px-5 py-3 text-xs text-[#728078]">@if($paginator->count())Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} matching notifications.@else {{ $paginator->total() }} matching notifications. No notifications on this page.@endif</p>
        <ul class="divide-y divide-[#EDF1EF]">
            @forelse($notifications as $notification)
                <li class="p-5 {{ $notification->read_at ? '' : 'bg-[#F3F8F5]' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-sm font-semibold text-[#24312C]">{{ $notification->title }}</h3><p class="mt-2 text-sm text-[#52635B]">{{ $notification->message }}</p></div><span class="rounded-full border border-[#DDE6E1] px-2 py-1 text-xs text-[#1F6F5B]">{{ $notification->read_at ? 'Read' : 'Unread' }}</span></div>
                    <p class="mt-3 text-xs text-[#728078]">{{ $notification->type }} · <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->copy()->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</time></p>
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-[#1F6F5B]">
                        @if($notification->url)<a href="{{ $notification->url }}" class="font-semibold">{{ $notification->link_label }}</a>@else<span class="text-[#728078]">Destination unavailable</span>@endif
                        <form method="POST" action="{{ route('seller.notifications.'.($notification->read_at ? 'unread' : 'read'), $notification->id) }}">@csrf<button class="font-semibold">Mark as {{ $notification->read_at ? 'Unread' : 'Read' }}</button></form>
                    </div>
                </li>
            @empty<li class="p-8 text-center text-sm text-[#728078]">No notifications found. Future supported events will appear here; no history has been fabricated.</li>@endforelse
        </ul>
        <div class="border-t border-[#EDF1EF] p-4">{{ $paginator->links() }}</div>
    </section>
</div>
@endsection
