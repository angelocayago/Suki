@php $field = fn ($key) => $filterPrefix ? $filterPrefix.'['.$key.']' : $key; @endphp
<form method="GET" action="{{ $filterAction }}" class="chat-filter-form">
    @if($selectedConversation && $historyPage > 1)<input type="hidden" name="page" value="{{ $historyPage }}">@endif
    <label>Search {{ $side === 'seller' ? 'Buyer' : 'shop' }} name<input type="search" name="{{ $field('search') }}" maxlength="100" value="{{ $listCriteria['search'] ?? '' }}" placeholder="Search conversations"></label>
    @if($side === 'seller')
        <label>Read status<select name="{{ $field('read_status') }}">@foreach(['' => 'All conversations', 'unread' => 'Unread incoming messages', 'read' => 'No unread incoming messages'] as $value => $label)<option value="{{ $value }}" @selected(($listCriteria['read_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Sort<select name="{{ $field('sort') }}"><option value="recent" @selected(($listCriteria['sort'] ?? 'recent') === 'recent')>Most Recent</option><option value="oldest" @selected(($listCriteria['sort'] ?? '') === 'oldest')>Oldest</option></select></label>
    @endif
    <div class="flex flex-wrap gap-3"><button class="chat-primary">{{ $side === 'seller' ? 'Apply Filters' : 'Search' }}</button><a href="{{ $filterReset }}" class="chat-reset">Reset Filters</a></div>
</form>
