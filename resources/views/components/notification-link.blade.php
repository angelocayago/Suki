@props(['withIcon' => false])

@php
    try {
        $unreadNotifications = auth()->user()?->unreadNotifications()->count() ?? 0;
    } catch (\Throwable $exception) {
        $unreadNotifications = 0;
    }
@endphp
<a href="{{ route('notifications.page') }}" {{ $attributes }}>
    @if($withIcon)
        <x-icons.notification-bell size="16" class="h-4 w-4 shrink-0" />
        <span>Notifications @if ($unreadNotifications) <span aria-label="{{ $unreadNotifications }} unread">({{ $unreadNotifications }})</span> @endif</span>
    @else
    Notifications @if ($unreadNotifications) <span aria-label="{{ $unreadNotifications }} unread">({{ $unreadNotifications }})</span> @endif
    @endif
</a>
