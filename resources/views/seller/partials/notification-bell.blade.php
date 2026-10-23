@if(!$sellerNotificationSummary['available'])
    <p class="mt-2 text-xs text-[#52635B]">Notifications are unavailable. No unread count is available.</p>
@else
    <p class="my-2 text-xs text-[#52635B]">{{ $sellerNotificationSummary['unread'] }} unread across your inbox.</p>
    <ul class="divide-y divide-[#EDF1EF]">
        @forelse($sellerNotificationSummary['recent'] as $notification)
            <li class="py-3 {{ $notification->read_at ? '' : 'font-semibold' }}">
                <p class="text-xs">{{ $notification->title }} @if(!$notification->read_at)<span class="text-[#1F6F5B]">· Unread</span>@endif</p>
                <p class="mt-1 text-xs font-normal text-[#52635B]">{{ $notification->message }}</p>
                <time class="mt-1 block text-[10px] font-normal text-[#728078]" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->copy()->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</time>
                @if($notification->url)<a href="{{ $notification->url }}" class="mt-2 inline-block text-xs text-[#1F6F5B]">{{ $notification->link_label }}</a>@endif
                @if(!$notification->read_at)<form method="POST" action="{{ route('seller.notifications.read', $notification->id) }}" class="mt-2">@csrf<button class="text-xs text-[#1F6F5B]">Mark as Read</button></form>@endif
            </li>
        @empty<li class="py-3 text-xs text-[#52635B]">No notifications yet. No historical events have been imported.</li>@endforelse
    </ul>
@endif
<a href="{{ route('seller.notifications.index') }}" class="mt-3 inline-block text-xs font-semibold text-[#1F6F5B]">View all notifications</a>
