@php
    $draftText = is_string(old('body')) ? old('body') : '';
    $draftKey = is_string(old('submission_key')) ? old('submission_key') : (string) \Illuminate\Support\Str::uuid();
@endphp
<form method="POST" enctype="multipart/form-data" action="{{ $action }}" data-chat-composer class="chat-composer">
    @csrf<input type="hidden" name="submission_key" value="{{ $draftKey }}">
    <label class="chat-message-label">Message<textarea data-chat-body name="body" maxlength="5000" rows="2" placeholder="Write a message…" class="chat-message-input">{{ $draftText }}</textarea></label>
    <div class="chat-composer-toolbar"><label class="chat-attach">Attach Picture<input data-chat-images type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"></label><span data-chat-counter class="chat-counter" aria-live="polite">{{ mb_strlen($draftText) }}/5000</span><button data-chat-submit class="chat-primary">Send Message</button></div>
    <p data-chat-error role="alert" class="chat-upload-error"></p><div data-chat-previews class="chat-previews"></div>
    <div class="chat-composer-meta"><details><summary>Picture limits &amp; privacy</summary><p>Send text, pictures or both. Up to 5 JPEG, PNG or WebP images, 5 MB each (maximum 4096 pixels per edge and 8 megapixels). Images are private to this conversation. Refresh for replies.</p></details><span data-chat-send-status role="status"></span></div>
</form>
