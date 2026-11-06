@extends('layouts.app')
@section('content')
<section class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-[#173F35]">Notifications</h1><p class="mt-1 text-sm text-gray-500">Your purchase updates · {{ $unreadCount }} unread</p></div>
        @if($unreadCount)
            <form method="POST" action="{{ route('notifications.read-all', ['filter' => $filter]) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-[#1F6F5B] px-4 py-2.5 text-sm font-semibold text-[#1F6F5B]">Mark all as read</button>
            </form>
        @endif
    </div>
    <nav class="my-6 flex gap-2" aria-label="Notification filters">
        @foreach(['all' => 'All notifications', 'unread' => 'Unread'] as $key => $label)
            <a href="{{ route('notifications.page', ['filter' => $key]) }}" aria-current="{{ $filter === $key ? 'page' : 'false' }}" class="rounded-xl border px-4 py-2.5 text-sm font-semibold {{ $filter === $key ? 'border-[#1F6F5B] bg-[#1F6F5B] text-white' : 'border-gray-200 bg-white text-gray-600' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="space-y-3">
        @forelse($rows as $row)
            <article class="rounded-2xl border border-gray-200 bg-white p-5 {{ $row['unread'] ? 'border-l-4 border-l-[#1F6F5B]' : '' }}">
                <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="break-words font-semibold text-gray-900">{{ $row['title'] }}</h2><span class="rounded-full bg-[#EEF5F1] px-2 py-1 text-xs text-[#1F6F5B]">{{ $row['unread'] ? 'Unread' : 'Read' }}</span></div>
                @if($row['reference'])<p class="mt-2 break-all text-xs font-semibold text-[#1F6F5B]">{{ $row['reference'] }}</p>@endif
                <p class="mt-2 break-words text-sm text-gray-600">{{ $row['message'] }}</p>
                <time datetime="{{ $row['iso_date'] }}" class="mt-2 block text-xs text-gray-500">{{ $row['date'] }} PHT</time>
                <div class="mt-4 flex flex-wrap gap-3">
                    @if($row['destination'])
                        <form method="POST" action="{{ $row['open_url'] }}">@csrf<button type="submit" class="rounded-lg bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white">View update</button></form>
                    @endif
                    @if($row['unread'])
                        <form method="POST" action="{{ route('notifications.read', ['notification' => $row['id'], 'filter' => $filter]) }}">@csrf<button type="submit" class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-[#1F6F5B]">Mark as read</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center"><h2 class="font-semibold text-[#173F35]">{{ $filter === 'unread' ? 'You’re all caught up' : 'No notifications yet' }}</h2><p class="mt-2 text-sm text-gray-500">{{ $filter === 'unread' ? 'There are no unread updates.' : 'Updates about your purchases will appear here.' }}</p><a href="{{ route('buyer.my-orders') }}" class="mt-5 inline-block font-semibold text-[#1F6F5B]">My Purchases</a></div>
        @endforelse
    </div>
    <div class="mt-6">{{ $notifications->links() }}</div>
</section>
@endsection
