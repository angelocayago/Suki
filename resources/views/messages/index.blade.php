@extends($portal['layout'])

@section('title', 'Messages')
@section('page-title', 'Messages')
@section('page-heading', 'Messages')
@section('page-subtitle', 'Marketplace conversations and support messages')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#173F35]">Messages</h1>
        <p class="mt-1 text-sm text-gray-500">Your authorized marketplace conversations.</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#DCE5E0] bg-white shadow-sm">
        @forelse($memberships as $membership)
            @php
                $conversation = $membership->conversation;
                $snapshot = $conversation->context_snapshot ?? [];
                $otherParticipant = $conversation->participants
                    ->first(fn ($participant) => (int) $participant->user_id !== (int) auth()->id());
                $title = $conversation->subject
                    ?: ($otherParticipant?->user?->full_name ?: $otherParticipant?->user?->name)
                    ?: ($snapshot['seller']['name'] ?? null)
                    ?: str($conversation->type->value)->lower()->replace('_', ' ')->headline();
                $contextReference = $snapshot['order']['order_number']
                    ?? $snapshot['shipment']['tracking_code']
                    ?? $snapshot['complaint']['reference']
                    ?? $snapshot['product']['name']
                    ?? $snapshot['seller']['name']
                    ?? 'General conversation';
                $latestMessage = $conversation->latestMessage;
                $unreadCount = (int) ($membership->unread_count ?? 0);
            @endphp

            <a
                href="{{ route($portal['route_prefix'].'.show', $conversation->reference) }}"
                class="flex items-start gap-4 border-b border-[#EDF1EF] px-5 py-5 transition last:border-b-0 hover:bg-[#F7FAF8]"
            >
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#DDF3EC] font-bold text-[#173F35]">
                    {{ str($title)->substr(0, 1)->upper() }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate font-semibold text-[#173F35]">{{ $title }}</h2>
                                <span class="rounded-full bg-[#EEF5F1] px-2 py-0.5 text-[10px] font-bold text-[#52635B]">
                                    {{ str($conversation->type->value)->lower()->replace('_', ' ')->headline() }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-xs text-gray-500">{{ $contextReference }}</p>
                        </div>

                        @if($unreadCount > 0)
                            <span class="flex min-w-6 items-center justify-center rounded-full bg-[#173F35] px-2 py-1 text-[10px] font-bold text-white">
                                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-4">
                        <p class="truncate text-sm {{ $unreadCount > 0 ? 'font-semibold text-gray-800' : 'text-gray-500' }}">
                            {{ $latestMessage ? \Illuminate\Support\Str::limit($latestMessage->body, 100) : 'No messages yet.' }}
                        </p>
                        <time class="shrink-0 text-xs text-gray-400">
                            {{ ($latestMessage?->sent_at ?? $conversation->created_at)->format('M d, Y h:i A') }}
                        </time>
                    </div>
                </div>
            </a>
        @empty
            <div class="px-6 py-16 text-center">
                <div class="text-lg font-semibold text-[#173F35]">No conversations yet</div>
                <p class="mt-2 text-sm text-gray-500">New authorized conversations will appear here.</p>
            </div>
        @endforelse

        @if($memberships->hasPages())
            <div class="border-t border-[#EDF1EF] px-5 py-4">{{ $memberships->links() }}</div>
        @endif
    </div>
</div>
@endsection
