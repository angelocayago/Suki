@props(['href' => null, 'label' => 'Notifications'])

@if(auth()->user()?->isBuyer() && $href === null)
    @include('buyer.partials.notification-bell')
@else

@php
    try {
        $notificationUnreadCount = auth()->user()?->unreadNotifications()->count() ?? 0;
    } catch (\Throwable $exception) {
        $notificationUnreadCount = 0;
    }
@endphp

<a href="{{ $href ?? route('notifications.page') }}"
   class="suki-notification-bell"
   aria-label="{{ $label }}{{ $notificationUnreadCount ? ', '.$notificationUnreadCount.' unread' : '' }}"
   title="{{ $label }}">
    <svg aria-hidden="true" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
    </svg>
    @if($notificationUnreadCount > 0)
        <span class="suki-notification-badge" aria-hidden="true">{{ $notificationUnreadCount > 99 ? '99+' : $notificationUnreadCount }}</span>
    @endif
</a>
@endif
