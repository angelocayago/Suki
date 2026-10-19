@php
    try {
        $unreadNotifications = auth()->user()?->unreadNotifications()->count() ?? 0;
    } catch (\Throwable $exception) {
        $unreadNotifications = 0;
    }
@endphp
<a href="{{ route('notifications.page') }}" {{ $attributes }}>
    Notifications @if ($unreadNotifications) <span aria-label="{{ $unreadNotifications }} unread">({{ $unreadNotifications }})</span> @endif
</a>
