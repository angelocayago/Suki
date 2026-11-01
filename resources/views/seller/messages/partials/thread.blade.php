@php
    $identity=$inboxPresenter->identity($conversation,auth()->user());
    $isActive=$conversation->status===\App\Enums\ConversationStatus::Active;
    $previousDay=null;
    $previousSender=null;
@endphp
<section class="inbox-thread" id="inboxThread" aria-label="Conversation with {{ $identity['name'] }}" data-reference="{{ $conversation->reference }}" data-user-id="{{ auth()->id() }}" data-poll-url="{{ route('seller.messages.poll',$conversation->reference) }}" data-read-url="{{ route('seller.messages.read',$conversation->reference) }}" data-csrf="{{ csrf_token() }}" data-status="{{ $conversation->status->value }}" data-read-id="{{ $conversation->participants->first(fn ($p) => (int)$p->user_id===(int)auth()->id() && $p->role_context==='SELLER' && !$p->left_at)?->last_read_message_id ?? 0 }}" data-last-id="{{ $messages->last()?->id ?? 0 }}">
    <header class="inbox-thread-header">
        <a class="inbox-back" href="{{ route('seller.messages.index',$listQuery) }}" aria-label="Back to messages"><i data-lucide="arrow-left" aria-hidden="true"></i></a>
        <span class="inbox-avatar" aria-hidden="true">{{ $identity['initial'] }}</span>
        <div class="inbox-thread-identity"><h2>{{ $identity['name'] }}</h2><p>{{ $identity['context'] ?: $identity['roles'] }}</p>
            @if($conversation->seller_order_id)<a href="{{ route('seller.orders.show',$conversation->seller_order_id) }}">View order</a>@endif
        </div>
        <div class="inbox-thread-badges"><span class="inbox-type">{{ $identity['type_label'] }}</span><span class="inbox-status" id="inboxConversationStatus">{{ str($conversation->status->value)->lower()->headline() }}</span></div>
    </header>
    <div class="inbox-notice" id="inboxPollNotice" role="status" hidden><span id="inboxPollError"></span><button type="button" id="inboxRetry">Retry</button></div>
    <div class="inbox-history" id="inboxHistory" role="log" aria-label="Message history, times in Asia/Manila" aria-live="polite" aria-relevant="additions" tabindex="0">
        <div class="inbox-older"><button type="button" id="inboxLoadOlder" @if(!$hasOlder) hidden @endif>Load older messages</button><span id="inboxOlderStatus" role="status"></span></div>
        <div id="inboxMessages">
            @forelse($messages as $message)
                @php
                    $display=$inboxPresenter->messageDisplay($message);
                    $mine=(int)$message->sender_id===(int)auth()->id();
                    $senderKey=$message->sender_id ?? 'system';
                    $grouped=$previousDay===$display['day'] && $previousSender===$senderKey;
                    $sender=$message->sender?->full_name ?: $message->sender?->name ?: 'Former user';
                    $role=$conversation->participants->first(fn ($p) => (int)$p->user_id===(int)$message->sender_id)?->role_context ?? 'SYSTEM';
                @endphp
                @if($previousDay!==$display['day'])<div class="inbox-date" data-date="{{ $display['day'] }}">{{ $display['day_label'] }}</div>@endif
                <article class="inbox-message {{ $mine ? 'is-mine' : '' }} {{ $grouped ? 'is-grouped' : '' }}" data-message-id="{{ $message->id }}" data-sender-id="{{ $senderKey }}" data-day="{{ $display['day'] }}" data-day-label="{{ $display['day_label'] }}">
                    <div class="inbox-bubble"><div class="inbox-sender"><strong>{{ $mine ? 'You' : $sender }}</strong><span>{{ str($role)->lower()->replace('_',' ')->headline() }}</span></div><p>{{ $message->body }}</p>
                        @include('messages.partials.attachment-gallery')
                        <time datetime="{{ $message->sent_at->toIso8601String() }}" title="Asia/Manila">{{ $display['time'] }}</time></div>
                </article>
                @php($previousDay=$display['day'])
                @php($previousSender=$senderKey)
            @empty
                <p class="inbox-thread-empty" id="inboxEmptyThread">No messages yet. Start the conversation below.</p>
            @endforelse
        </div>
    </div>
    <button type="button" class="inbox-new-messages" id="inboxNewMessages" hidden>New messages ↓</button>
    <footer class="inbox-composer">
        @if(session('success'))<p class="inbox-feedback" role="status">{{ session('success') }}</p>@endif
        @if($errors->has('body') || $errors->has('conversation'))<p class="inbox-error" role="alert">{{ $errors->first('body') ?: $errors->first('conversation') }}</p>@endif
        <p id="inboxSendError" class="inbox-error" role="alert" hidden></p>
        <p id="inboxClosed" class="inbox-closed" @if($isActive) hidden @endif>This conversation is {{ strtolower($conversation->status->value) }} and cannot receive new messages.</p>
        <form id="inboxComposer" action="{{ route('seller.messages.store',$conversation->reference) }}" method="POST" @if(!$isActive) hidden @endif>
            @csrf
            <label class="inbox-sr-only" for="inboxBody">Message</label>
            <textarea id="inboxBody" name="body" rows="1" maxlength="10000" required placeholder="Write a message…">{{ old('body') }}</textarea>
            <button type="submit" id="inboxSend" aria-label="Send message"><i data-lucide="send" aria-hidden="true"></i><span>Send</span></button>
        </form>
        <noscript><p>Messages send using the standard form. Live updates and older history require JavaScript.</p></noscript>
    </footer>
</section>
