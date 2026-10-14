@php
    $participant = $conversation->participants
        ->first(fn ($candidate) => (int) $candidate->user_id === (int) $message->sender_id);
    $isMine = (int) $message->sender_id === (int) auth()->id();
    $senderName = $message->sender?->full_name ?: $message->sender?->name ?: 'Former user';
    $roleContext = $participant?->role_context ?? 'SYSTEM';
@endphp

<article
    data-message-id="{{ $message->id }}"
    class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}"
>
    <div class="max-w-[85%] rounded-2xl px-4 py-3 sm:max-w-[72%] {{ $isMine ? 'bg-[#173F35] text-white' : 'border border-[#DCE5E0] bg-white text-gray-800' }}">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs {{ $isMine ? 'text-white/70' : 'text-gray-500' }}">
            <span class="font-semibold">{{ $isMine ? 'You' : $senderName }}</span>
            <span>{{ str($roleContext)->lower()->replace('_', ' ')->headline() }}</span>
        </div>
        <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $message->body }}</p>
        <time class="mt-2 block text-[10px] {{ $isMine ? 'text-white/60' : 'text-gray-400' }}">
            {{ $message->sent_at?->format('M d, Y h:i A') }}
        </time>
    </div>
</article>
