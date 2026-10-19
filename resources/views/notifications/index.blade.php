@extends($layout)

@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('page-heading', 'Notifications')

@section('content')
<main class="mx-auto max-w-4xl p-6">
    <h1 class="text-2xl font-bold">Notifications <span aria-label="Unread count">({{ $unreadCount }} unread)</span></h1>
    <form method="POST" action="{{ route('notifications.read-all') }}" class="my-4">
        @csrf
        <button type="submit" class="underline">Mark all as read</button>
    </form>
    @forelse ($notifications as $notification)
        <article class="border-b py-4">
            <h2 class="font-semibold">{{ $notification->data['title'] ?? 'Notification' }}</h2>
            <p>{{ $notification->data['message'] ?? '' }}</p>
            <time>{{ $notification->created_at }}</time>
            @if (! $notification->read_at)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" class="underline">Mark as read</button>
                </form>
            @endif
        </article>
    @empty
        <p>No notifications yet.</p>
    @endforelse
    {{ $notifications->links() }}
</main>
@endsection
