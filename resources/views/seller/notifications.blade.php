@extends('layouts.seller')
@section('title','Notifications')
@section('page-title','Notifications')
@section('content')
<div class="seller-inbox">
    <header class="seller-inbox-heading"><div><h2>Notifications</h2><p><span data-seller-unread-total>{{ auth()->user()->unreadNotifications()->count() }}</span> unread</p></div><form method="POST" action="{{ route('seller.notifications.read-all') }}">@csrf @if(isset($criteria['filter']))<input type="hidden" name="filter" value="{{ $criteria['filter'] }}">@endif<button type="submit">Mark all as read</button></form></header>
    @if(session('success'))<p class="seller-inbox-success" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="seller-inbox-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <nav class="seller-inbox-tabs" aria-label="Notification filter"><a href="{{ route('seller.notifications.index') }}" @if(!isset($criteria['filter'])) aria-current="page" @endif>All Notifications</a><a href="{{ route('seller.notifications.index',['filter'=>'unread']) }}" @if(isset($criteria['filter'])) aria-current="page" @endif>Unread</a></nav>
    <section class="seller-inbox-list" aria-label="Your notifications">
        @forelse($rows as $notice)
            <article class="seller-inbox-item {{ $notice['unread'] ? 'is-unread' : '' }}">
                <div class="seller-inbox-item-heading"><h3>{{ $notice['title'] }}</h3><span>{{ $notice['unread'] ? 'Unread' : 'Read' }}</span></div>
                <p class="seller-inbox-message">{{ $notice['message'] }}</p>
                @if($notice['date'])<time datetime="{{ $notice['date']->toIso8601String() }}">{{ $notice['date']->format('M d, Y h:i A') }}</time>@endif
                <div class="seller-inbox-actions">
                    @if($notice['destination'])<form method="POST" action="{{ $notice['open_url'] }}">@csrf<button type="submit">Open notification</button></form>@endif
                    @if($notice['unread'])<form method="POST" action="{{ route('seller.notifications.read',$notice['id']) }}">@csrf @if(isset($criteria['filter']))<input type="hidden" name="filter" value="{{ $criteria['filter'] }}">@endif<button type="submit" class="seller-inbox-secondary">Mark as read</button></form>@endif
                </div>
            </article>
        @empty<p class="seller-inbox-empty">{{ isset($criteria['filter']) ? 'No unread notifications.' : 'No notifications yet.' }}</p>@endforelse
    </section>
    <div class="seller-inbox-pagination">{{ $notifications->links() }}</div>
</div>
@endsection
