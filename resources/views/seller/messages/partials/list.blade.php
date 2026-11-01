<aside class="inbox-sidebar" aria-label="Conversations">
    <header class="inbox-list-header">
        <h1>Messages <span>{{ $memberships->total() }}</span></h1>
        <form method="GET" action="{{ route('seller.messages.index') }}" class="inbox-search">
            <label class="inbox-sr-only" for="inboxSearch">Search conversations</label>
            <input id="inboxSearch" name="search" type="search" maxlength="100" placeholder="Search conversations" value="{{ $criteria['search'] ?? '' }}">
            @if(isset($criteria['filter']))<input type="hidden" name="filter" value="{{ $criteria['filter'] }}">@endif
            <button type="submit" aria-label="Search conversations"><i data-lucide="search" aria-hidden="true"></i></button>
        </form>
        <nav class="inbox-filters" aria-label="Conversation filters">
            @foreach(['all'=>'All','unread'=>'Unread','inquiry'=>'Store Inquiry','order'=>'Order-related'] as $key=>$label)
                <a href="{{ route('seller.messages.index', array_filter(['search'=>$criteria['search'] ?? null,'filter'=>$key==='all' ? null : $key])) }}" @if(($criteria['filter'] ?? 'all')===$key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @foreach(['search','filter','page'] as $field)
            @error($field)<p class="inbox-error" role="alert">{{ $message }}</p>@enderror
        @endforeach
    </header>
    <div class="inbox-conversations">
        @forelse($memberships as $membership)
            @php
                $threadConversation=$membership->conversation;
                $identity=$inboxPresenter->identity($threadConversation,auth()->user());
                $latest=$threadConversation->latestMessage;
                $unread=(int)$membership->unread_count;
                $selected=$conversation && $conversation->id===$threadConversation->id;
                $activity=$latest?->sent_at ?? $threadConversation->created_at;
            @endphp
            <a class="inbox-row {{ $selected ? 'is-selected' : '' }} {{ $unread ? 'is-unread' : '' }}" data-preview-id="{{ $latest?->id ?? 0 }}" data-conversation-reference="{{ $threadConversation->reference }}" href="{{ route('seller.messages.show',['conversation'=>$threadConversation->reference]+$listQuery) }}" @if($selected) aria-current="page" @endif>
                <span class="inbox-avatar" aria-hidden="true">{{ $identity['initial'] }}</span>
                <span class="inbox-row-content"><span class="inbox-row-heading"><strong>{{ $identity['name'] }}</strong><time datetime="{{ $activity?->toIso8601String() }}" title="{{ $activity?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A').' Manila' }}">{{ $activity?->diffForHumans(short:true) }}</time></span>
                    <span class="inbox-row-context">{{ $identity['context'] ?: $identity['roles'] }}</span>
                    <span class="inbox-row-preview">{{ \App\Services\MessageAttachmentService::preview($latest) }}</span>
                    <span class="inbox-row-bottom"><span class="inbox-type">{{ $identity['type_label'] }}</span><span class="inbox-unread" data-inbox-unread data-count="{{ $unread }}" @if(!$unread) hidden @endif aria-label="{{ $unread }} unread messages">{{ $unread>99 ? '99+' : $unread }}</span></span>
                </span>
            </a>
        @empty
            <div class="inbox-list-empty"><h2>{{ $criteria ? 'No matching conversations' : 'No conversations yet' }}</h2><p>{{ $criteria ? 'Try another search or filter.' : 'Your marketplace conversations will appear here.' }}</p>@if($criteria)<a href="{{ route('seller.messages.index') }}">Clear filters</a>@endif</div>
        @endforelse
    </div>
    @if($memberships->hasPages())<footer class="inbox-list-pagination">{{ $memberships->onEachSide(0)->links('pagination::simple-tailwind') }}</footer>@endif
</aside>
