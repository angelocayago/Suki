@if($message->relationLoaded('attachments') && $message->attachments->isNotEmpty())
    <div class="chat-image-grid">@foreach($message->attachments as $image)<a href="{{ route('messages.images.show', $image->id) }}" target="_blank" rel="noopener" class="chat-image-tile"><img src="{{ route('messages.images.show', $image->id) }}" alt="Chat picture {{ $loop->iteration }} — open full image" loading="lazy" width="{{ $image->width }}" height="{{ $image->height }}"><span>View full image</span></a>@endforeach</div>
@endif
