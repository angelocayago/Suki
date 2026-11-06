@php
    $buyerNotificationUser = auth()->user();
    $buyerNotificationPresenter = app(\App\Support\BuyerNotifications::class);
    $buyerNotificationCount = $buyerNotificationUser->unreadNotifications()->count();
    $buyerNotificationRows = $buyerNotificationPresenter->present($buyerNotificationUser, $buyerNotificationPresenter->recent($buyerNotificationUser));
@endphp
<details class="bn-bell" data-buyer-notifications data-preview-url="{{ route('notifications.preview') }}" data-csrf="{{ csrf_token() }}">
    <summary class="suki-notification-bell" aria-label="Notifications, {{ $buyerNotificationCount }} unread" title="Notifications" aria-controls="buyerNotificationPreview">
        <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        <span class="suki-notification-badge" data-buyer-notification-count aria-hidden="true" @if(!$buyerNotificationCount) hidden @endif>{{ $buyerNotificationCount > 99 ? '99+' : $buyerNotificationCount }}</span>
    </summary>
    <section id="buyerNotificationPreview" class="bn-panel" aria-label="Recent notifications">
        <header><strong>Notifications</strong><a href="{{ route('notifications.page') }}">View all notifications</a></header>
        <div data-buyer-notification-items>
            @forelse($buyerNotificationRows as $notice)
                <form method="POST" action="{{ $notice['open_url'] }}">
                    @csrf
                    <button type="submit" class="bn-item {{ $notice['unread'] ? 'bn-unread' : '' }}">
                        <strong>{{ $notice['title'] }}</strong>
                        @if($notice['reference'])<span class="bn-reference">{{ $notice['reference'] }}</span>@endif
                        <span>{{ $notice['message'] }}</span>
                        <time datetime="{{ $notice['iso_date'] }}">{{ $notice['date'] }} PHT · {{ $notice['unread'] ? 'Unread' : 'Read' }}</time>
                    </button>
                </form>
            @empty
                <p class="bn-empty">No notifications yet. Purchase updates will appear here.</p>
            @endforelse
        </div>
        <p class="bn-status" role="status" aria-live="polite" data-buyer-notification-status></p>
    </section>
</details>
<style>
.bn-bell{position:relative;flex-shrink:0}.bn-bell summary{cursor:pointer;list-style:none}.bn-bell summary::-webkit-details-marker{display:none}.bn-bell [hidden]{display:none!important}.bn-panel{position:absolute;right:0;top:calc(100% + 10px);width:360px;max-width:calc(100vw - 24px);max-height:70dvh;overflow:auto;border:1px solid #dde6e1;border-radius:16px;background:#fff;color:#244538;box-shadow:0 18px 48px #173f3526}.bn-panel header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px;border-bottom:1px solid #e5ece7;font-size:14px}.bn-panel header a{font-size:11px;color:#1f6f5b;text-decoration:underline;text-underline-offset:3px}.bn-item{display:flex;flex-direction:column;gap:5px;width:100%;padding:14px 16px;text-align:left;border:0;border-bottom:1px solid #edf1ee;background:#fff;cursor:pointer;overflow-wrap:anywhere}.bn-item strong{font-size:13px}.bn-item span{font-size:12px;color:#61766b}.bn-item .bn-reference{font-size:11px;color:#1f6f5b}.bn-item time{font-size:10px;color:#708378}.bn-item.bn-unread{background:#f0f8f4;border-left:3px solid #1f6f5b}.bn-item:hover{background:#e7f3ec}.bn-panel :is(button,a):focus-visible{outline:3px solid #72a991;outline-offset:-3px}.bn-empty{padding:28px 18px;font-size:13px;color:#61766b;text-align:center}.bn-status{font-size:11px;color:#61766b;padding:8px 16px}.bn-status:empty{display:none}@media(max-width:640px){.bn-panel{position:fixed;top:78px;left:12px;right:12px;width:auto;max-width:none}}html[data-theme=dark] .bn-panel,html[data-theme=dark] .bn-item{background:#192b23;color:#e2eee7;border-color:#405b4b}html[data-theme=dark] .bn-item.bn-unread{background:#244b3a}html[data-theme=dark] .bn-panel :is(span,time,a,.bn-status,.bn-empty){color:#b4c9bc}
</style>
@include('buyer.partials.notification-script')
