<div class="seller-header-dropdown" data-seller-dropdown>
    <button type="button" class="seller-header-icon" id="sellerNotificationBell" aria-controls="sellerNotificationPanel" aria-expanded="false" aria-label="Notifications{{ $sellerHeader['unread_count'] ? ', '.$sellerHeader['unread_count'].' unread' : '' }}" data-seller-dropdown-toggle>
        <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        <span class="seller-header-badge" data-seller-unread-badge aria-hidden="true" @if(!$sellerHeader['unread_count']) hidden @endif>{{ $sellerHeader['unread_count'] ? ($sellerHeader['unread_count'] > 99 ? '99+' : $sellerHeader['unread_count']) : '' }}</span>
    </button>
    <section id="sellerNotificationPanel" class="seller-header-panel seller-notification-panel" role="region" aria-label="Recent notifications" hidden data-preview-url="{{ route('seller.notifications.preview') }}" data-csrf="{{ csrf_token() }}">
        <header><strong>Notifications</strong><span><span data-seller-unread-total>{{ $sellerHeader['unread_count'] }}</span> unread</span></header>
        <div id="sellerNotificationPreview">
            @forelse($sellerHeader['recent'] as $notice)
                <form method="POST" action="{{ $notice['open_url'] }}">@csrf<button type="submit" class="seller-notification-item {{ $notice['unread'] ? 'is-unread' : '' }}"><strong>{{ $notice['title'] }}</strong><span class="seller-notification-message">{{ $notice['message'] }}</span>@if($notice['date'])<time datetime="{{ $notice['date']->toIso8601String() }}">{{ $notice['date']->format('M d, Y h:i A') }}</time>@endif</button></form>
            @empty<p class="seller-header-empty">No notifications yet.</p>@endforelse
        </div>
        <p id="sellerNotificationLoadStatus" class="seller-header-status" role="status" aria-live="polite"></p>
        <footer><a href="{{ route('seller.notifications.index') }}">View All Notifications</a></footer>
    </section>
</div>
<div class="seller-header-dropdown" data-seller-dropdown>
    <button type="button" class="seller-header-avatar" id="sellerProfileToggle" aria-label="{{ $sellerHeader['name'] }} profile menu" aria-controls="sellerProfilePanel" aria-expanded="false" data-seller-dropdown-toggle>
        @if($sellerHeader['photo'])<img src="{{ $sellerHeader['photo'] }}" alt="" data-seller-avatar-photo>@endif
        <span data-seller-avatar-fallback @if($sellerHeader['photo']) hidden @endif>{{ $sellerHeader['initial'] }}</span>
    </button>
    <section id="sellerProfilePanel" class="seller-header-panel seller-profile-panel" role="region" aria-label="Seller profile menu" hidden>
        <header><strong>{{ $sellerHeader['name'] }}</strong></header>
        <nav aria-label="Seller account"><a href="{{ route('seller.store.profile') }}">Store Profile</a>@if($sellerHeader['store_url'])<a href="{{ $sellerHeader['store_url'] }}" target="_blank" rel="noopener noreferrer">View Store</a>@endif</nav>
        <form action="{{ route('logout') }}" method="POST" data-confirm-logout>@csrf<button type="submit" class="seller-profile-logout">Sign Out</button></form>
    </section>
</div>
