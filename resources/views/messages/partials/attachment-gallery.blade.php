@if(($attachmentsAvailable ?? false) && $message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
    @php($imageItems=$message->attachments->map(fn ($file) => app(\App\Services\MessageAttachmentService::class)->metadata($file,$conversation,$portal['route_prefix']))->values()->all())
    <div class="message-image-gallery {{ count($imageItems)===1 ? 'is-single' : '' }}" data-message-gallery data-conversation="{{ $conversation->reference }}" data-items="{{ json_encode($imageItems) }}">
        @foreach(array_slice($imageItems,0,4) as $index=>$imageItem)
            <button type="button" data-image-index="{{ $index }}" aria-label="View image {{ $index+1 }} of {{ count($imageItems) }}">
                <img src="{{ $imageItem['thumbnail_url'] }}" width="320" height="240" loading="lazy" alt="{{ $imageItem['original_name'] }}">
                <span class="message-image-fallback" hidden>Image unavailable</span>
                @if($index===3 && count($imageItems)>4)<span class="message-image-more">+{{ count($imageItems)-4 }}</span>@endif
            </button>
        @endforeach
    </div>
@endif
