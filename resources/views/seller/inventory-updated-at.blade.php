<div class="inventory-record-updated">
    @if(($product['updated_at'] ?? null) instanceof \Carbon\CarbonInterface)
        <time datetime="{{ $product['updated_at']->toIso8601String() }}" title="General variation record update; includes stock and other edits. Asia/Manila.">
            {{ $product['updated_at']->format('M j, Y') }}
            <small>{{ $product['updated_at']->format('g:i A') }} PHT</small>
        </time>
    @else
        <span>Not recorded</span>
    @endif
</div>
