@php
    $rowName = $side === 'seller' ? (trim($row->buyer_name ?? '') ?: 'Buyer') : $row->shop_name;
    $isSelected = $selectedConversation && $selectedConversation->id === $row->id;
@endphp
<li>
    <a data-conversation-id="{{ $row->id }}" @if($isSelected) aria-current="page" @endif href="{{ \App\Support\ChatWorkspace::conversationUrl($side, $row->id, $listCriteria) }}" class="chat-conversation {{ $isSelected ? 'is-selected' : '' }}">
        <span class="chat-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(mb_substr($rowName, 0, 1)) }}</span>
        <span class="chat-conversation-copy">
            <span class="chat-conversation-heading"><strong>{{ $rowName }}</strong>@if($row->last_message_at)<time datetime="{{ $row->last_message_at->toIso8601String() }}" title="{{ $row->last_message_at->copy()->setTimezone('Asia/Manila')->format('M j, Y g:i A') }} PHT">{{ $row->last_message_at->diffForHumans() }}</time>@endif</span>
            <span class="chat-conversation-preview">{{ $row->last_body === null ? 'No messages yet.' : ($row->last_body === '' ? 'Picture message' : \Illuminate\Support\Str::limit($row->last_body, 100)) }}</span>
            @if($row->unread_count > 0)<span class="chat-unread" aria-label="{{ $row->unread_count }} unread incoming messages">{{ $row->unread_count }} unread</span>@endif
        </span>
    </a>
</li>
