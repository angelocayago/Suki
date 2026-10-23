@php
    $draftShop = $draftShop ?? null;
    $hasChat = $selectedConversation || $draftShop;
    $headerName = $participantName ?? $draftShop?->name;
    $filterChips = $workspaceFilterChips;
    $filterId = $side.'-messages-advanced-filters';
    $filtersOpen = count($filterChips) > 0 || $errors->any();
@endphp
<div class="chat-intro"><div><p class="chat-eyebrow">Communication</p><h2>Messages</h2><p>Keep your conversations together. Refresh for new replies.</p></div><p class="chat-global-unread">{{ $unreadCount }} unread incoming messages</p></div>
<div class="chat-workspace {{ $hasChat ? 'has-conversation' : '' }}" data-chat-workspace>
    <aside class="chat-sidebar" aria-label="Conversations" data-seller-filters>
        <div class="chat-sidebar-heading"><h3>Conversations <span>({{ $inboxPaginator->total() }})</span></h3>@if($side === 'seller')@include('seller.partials.filter-trigger')@endif</div>
        @if($side === 'seller')
            @include('seller.partials.advanced-filter-panel', ['filterForm' => 'shared.chat-filters'])
            @include('seller.partials.active-filter-chips')
        @else
            @include('shared.chat-filters')
        @endif
        <p class="chat-list-scope">@if($inboxPaginator->count())Showing {{ $inboxPaginator->firstItem() }}–{{ $inboxPaginator->lastItem() }} of {{ $inboxPaginator->total() }} matching conversations.@else {{ $inboxPaginator->total() }} matching conversations.@endif</p>
        <nav class="chat-list" aria-label="Choose a conversation">
            @if($selectedPreview)<p class="chat-pinned-label">Selected conversation outside this list page or filter</p><ul>@include('shared.chat-conversation', ['row' => $selectedPreview])</ul>@endif
            <ul>@forelse($inboxPaginator as $row)@include('shared.chat-conversation', ['row' => $row])@empty<li class="chat-list-empty">No conversations found.</li>@endforelse</ul>
        </nav>
        <div class="chat-list-pagination">{{ $inboxPaginator->onEachSide(0)->links() }}</div>
    </aside>
    <section class="chat-panel" aria-label="{{ $hasChat ? 'Selected conversation' : 'Conversation selection' }}">
        @if($hasChat)
            <header class="chat-panel-header"><a class="chat-mobile-back" href="{{ $listUrl }}">← Back to Conversations</a><span class="chat-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(mb_substr($headerName, 0, 1)) }}</span><div><h3>{{ $headerName }}</h3><p>{{ $side === 'seller' ? 'Buyer conversation' : 'Shop conversation' }}</p>@if($side === 'buyer')@include('buyer.messages.shop-status', ['shop' => $draftShop ?? $shop])@endif</div><a class="chat-desktop-back" href="{{ $listUrl }}">All conversations</a></header>
            @include('shared.chat-errors')
            @if($selectedConversation)
                <div class="chat-history" data-chat-history data-latest-page="{{ $historyPage === 1 ? 'true' : 'false' }}" tabindex="0" aria-label="Conversation history">
                    <div class="chat-history-pagination">{{ $historyPaginator->onEachSide(1)->links() }}</div>
                    <p class="chat-history-note">Latest 50 messages on page one; higher pages show older history. Only displayed incoming messages are marked read.</p>
                    <ol class="chat-messages">@forelse($messages as $message)<li class="chat-message {{ $message->sender_role === $side ? 'is-outgoing' : 'is-incoming' }}"><div class="chat-bubble"><p class="chat-author">{{ $message->sender_role === $side ? ($side === 'seller' ? 'Your shop' : 'You') : $headerName }}</p>@if($message->body !== '')<p class="chat-body">{{ $message->body }}</p>@endif @include('shared.chat-images')<time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->copy()->setTimezone('Asia/Manila')->format('M j, Y g:i:s A') }} PHT</time></div></li>@empty<li class="chat-history-note">No messages on this page.</li>@endforelse</ol>
                </div>
                @if($side === 'seller' ? $canReply : $canSend)
                    @include('shared.chat-composer', ['action' => route($side === 'seller' ? 'seller.messages.reply' : 'buyer.messages.send', ['conversation' => $selectedConversation->id] + ($listCriteria ? ['list' => $listCriteria] : []))])
                @else<p class="chat-unavailable">{{ $side === 'seller' ? 'This Buyer account is currently unavailable for replies. Saved history remains visible.' : 'This shop is currently unavailable for new messages.' }}</p>@endif
            @else
                <div class="chat-history chat-draft"><h3>Start a conversation</h3>@if($draftProduct ?? null)<p>About saved product: {{ $draftProduct->name }}</p>@endif<p>Your first message opens or reuses your private conversation with this shop.</p></div>
                @include('shared.chat-composer', ['action' => $draftAction])
            @endif
        @else
            <div class="chat-welcome"><span class="chat-welcome-icon" aria-hidden="true">↔</span><h3>Your conversations, in one place</h3><p>Select a conversation to read messages and reply.</p><p>Only real saved conversations appear here; no chat history has been imported.</p>@if($side === 'buyer')<a href="#chat-shop-contacts" class="chat-reset">Contact a shop</a>@endif</div>
        @endif
    </section>
</div>
@if($side === 'buyer' && !$hasChat)<section id="chat-shop-contacts">@include('shared.chat-contacts')</section>@endif
