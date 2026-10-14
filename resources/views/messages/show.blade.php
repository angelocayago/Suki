@extends($portal['layout'])

@section('title', 'Conversation')
@section('page-title', 'Conversation')
@section('page-heading', 'Conversation')
@section('page-subtitle', 'Secure marketplace messaging')

@section('content')
@php
    $snapshot = $conversation->context_snapshot ?? [];
    $contextReference = $snapshot['order']['order_number']
        ?? $snapshot['shipment']['tracking_code']
        ?? $snapshot['complaint']['reference']
        ?? $snapshot['product']['name']
        ?? $snapshot['seller']['name']
        ?? 'General conversation';
    $title = $conversation->subject
        ?: ($snapshot['seller']['name'] ?? null)
        ?: str($conversation->type->value)->lower()->replace('_', ' ')->headline();
    $isActive = $conversation->status === \App\Enums\ConversationStatus::Active;
    $lastMessageId = $messages->last()?->id ?? 0;
@endphp

<div class="mx-auto max-w-5xl px-4 py-6 sm:px-6">
    <a href="{{ route($portal['route_prefix'].'.index') }}" class="text-sm font-semibold text-[#1F6F5B] hover:underline">
        &larr; Back to messages
    </a>

    <section class="mt-4 overflow-hidden rounded-2xl border border-[#DCE5E0] bg-[#F7FAF8] shadow-sm">
        <header class="border-b border-[#DCE5E0] bg-white px-5 py-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-[#173F35]">{{ $title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $contextReference }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-[#EEF5F1] px-3 py-1 text-xs font-semibold text-[#52635B]">
                        {{ str($conversation->type->value)->lower()->replace('_', ' ')->headline() }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-600' }}">
                        {{ str($conversation->status->value)->lower()->headline() }}
                    </span>
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="mx-5 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-5 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div
            id="message-thread"
            data-poll-url="{{ route($portal['route_prefix'].'.poll', $conversation->reference) }}"
            data-read-url="{{ route($portal['route_prefix'].'.read', $conversation->reference) }}"
            data-current-user="{{ auth()->id() }}"
            data-last-message-id="{{ $lastMessageId }}"
            class="h-[55vh] min-h-[360px] space-y-4 overflow-y-auto px-5 py-5"
            aria-live="polite"
        >
            @forelse($messages as $message)
                @include('messages.partials.message', ['message' => $message, 'conversation' => $conversation])
            @empty
                <p id="empty-thread" class="py-16 text-center text-sm text-gray-500">No messages have been sent yet.</p>
            @endforelse
        </div>

        <footer class="border-t border-[#DCE5E0] bg-white p-4">
            @if($isActive)
                <form method="POST" action="{{ route($portal['route_prefix'].'.store', $conversation->reference) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <label for="message-body" class="sr-only">Message</label>
                        <textarea
                            id="message-body"
                            name="body"
                            required
                            maxlength="10000"
                            rows="3"
                            placeholder="Write a message"
                            class="w-full resize-none rounded-xl border border-[#DCE5E0] px-4 py-3 text-sm focus:border-[#1F6F5B] focus:ring-[#DDF3EC]"
                        >{{ old('body') }}</textarea>
                    </div>
                    <button type="submit" class="rounded-xl bg-[#173F35] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#1F6F5B]">
                        Send message
                    </button>
                </form>
            @else
                <div class="rounded-xl bg-[#F3F6F4] px-4 py-3 text-sm text-gray-600">
                    This conversation is {{ strtolower($conversation->status->value) }} and cannot receive new messages.
                </div>
            @endif
        </footer>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const thread = document.getElementById('message-thread');
    if (!thread) return;

    const csrfToken = @json(csrf_token());
    const currentUserId = Number(thread.dataset.currentUser);
    let lastMessageId = Number(thread.dataset.lastMessageId || 0);
    let polling = false;
    let timer = null;

    const scrollToBottom = () => { thread.scrollTop = thread.scrollHeight; };

    const markRead = async () => {
        if (!lastMessageId) return;
        try {
            await fetch(thread.dataset.readUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message_id: lastMessageId }),
            });
        } catch (_) {}
    };

    const appendMessage = (message) => {
        if (thread.querySelector(`[data-message-id="${message.id}"]`)) return;
        document.getElementById('empty-thread')?.remove();

        const mine = Number(message.sender.id) === currentUserId;
        const article = document.createElement('article');
        article.dataset.messageId = message.id;
        article.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;

        const bubble = document.createElement('div');
        bubble.className = `max-w-[85%] rounded-2xl px-4 py-3 sm:max-w-[72%] ${mine ? 'bg-[#173F35] text-white' : 'border border-[#DCE5E0] bg-white text-gray-800'}`;

        const meta = document.createElement('div');
        meta.className = `flex flex-wrap items-center gap-x-2 gap-y-1 text-xs ${mine ? 'text-white/70' : 'text-gray-500'}`;
        const sender = document.createElement('span');
        sender.className = 'font-semibold';
        sender.textContent = mine ? 'You' : message.sender.name;
        const role = document.createElement('span');
        role.textContent = message.sender.role_context.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
        meta.append(sender, role);

        const body = document.createElement('p');
        body.className = 'mt-2 whitespace-pre-wrap break-words text-sm';
        body.textContent = message.body;

        const time = document.createElement('time');
        time.className = `mt-2 block text-[10px] ${mine ? 'text-white/60' : 'text-gray-400'}`;
        time.textContent = message.sent_at ? new Date(message.sent_at).toLocaleString() : '';

        bubble.append(meta, body, time);
        article.appendChild(bubble);
        thread.appendChild(article);
        lastMessageId = Math.max(lastMessageId, Number(message.id));
    };

    const schedule = (delay = 5000) => {
        clearTimeout(timer);
        timer = setTimeout(poll, delay);
    };

    const poll = async () => {
        if (polling || document.hidden) return schedule();
        polling = true;
        try {
            const url = new URL(thread.dataset.pollUrl, window.location.origin);
            url.searchParams.set('after_id', lastMessageId);
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Polling failed');
            const payload = await response.json();
            payload.messages.forEach(appendMessage);
            if (payload.messages.length) {
                scrollToBottom();
                await markRead();
            }
            schedule();
        } catch (_) {
            schedule(15000);
        } finally {
            polling = false;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) poll();
    });

    scrollToBottom();
    markRead();
    schedule();
});
</script>
@endsection
