<section id="productImageControls" class="space-y-4 rounded-2xl border border-[#DDE6E1] bg-white p-5">
    <h3 class="font-semibold text-[#173F35]">Product images</h3>
    <p class="text-sm text-[#728078]">The first available image is the cover. Changes take effect when you save.</p>
    @foreach($product['image_records'] ?? [] as $imageRecord)
        <div data-image-row class="flex flex-wrap items-center gap-3 rounded-xl border border-[#DDE6E1] p-3">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#F1F5F3]">
                <img data-preview @if($imageRecord['url']) src="{{ $imageRecord['url'] }}" @endif
                     alt="Product image {{ $loop->iteration }}" @class(['h-full w-full object-cover', 'hidden' => !$imageRecord['url']])
                     onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                <span data-placeholder @class(['text-xs text-[#728078]', 'hidden' => (bool) $imageRecord['url']])>No image</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <input type="file" hidden disabled data-replacement name="replacement_images[{{ $imageRecord['id'] }}]" accept="image/jpeg,image/png,image/webp" aria-label="Replacement for image {{ $loop->iteration }}">
                <input type="hidden" data-removal name="remove_image_ids[]" value="{{ $imageRecord['id'] }}" @disabled(!in_array($imageRecord['id'], old('remove_image_ids', [])))>
                <button type="button" data-replace class="rounded-lg bg-[#1F6F5B] px-3 py-2 text-sm font-semibold text-white hover:bg-[#173F35]" aria-label="Replace image {{ $loop->iteration }}">Replace</button>
                <button type="button" data-remove class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50" aria-label="Remove image {{ $loop->iteration }}">Remove</button>
                <button type="button" data-cancel-replacement hidden class="rounded-lg border border-[#DDE6E1] px-3 py-2 text-sm">Cancel replacement</button>
            </div>
            <p data-image-status role="status" aria-live="polite" class="w-full text-xs text-[#728078]"></p>
        </div>
    @endforeach
    <input type="file" hidden id="editAddImages" name="add_images[]" accept="image/jpeg,image/png,image/webp" multiple>
    <button type="button" id="editAddImagesButton" class="rounded-xl bg-[#1F6F5B] px-4 py-2 text-sm font-semibold text-white hover:bg-[#173F35]">Add Images</button>
    <div id="editAddedPreviews" class="flex flex-wrap gap-3"></div>
    <p id="editImageStatus" role="status" aria-live="polite" class="text-sm text-red-600"></p>
    <p class="text-xs text-[#728078]">Up to 5 images. JPG, PNG or WebP, maximum 5 MB each. Choose files again after a validation error.</p>
    @foreach(['replacement_images', 'replacement_images.*', 'add_images', 'add_images.*', 'remove_image_ids', 'remove_image_ids.*'] as $imageField)
        @foreach($errors->get($imageField) as $messages)
            @foreach((array) $messages as $message)
                <p class="text-sm text-red-600">{{ $message }}</p>
            @endforeach
        @endforeach
    @endforeach
    <noscript><p class="text-sm">Enable JavaScript to change product images.</p></noscript>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('productImageControls');
    const rows = Array.from(root.querySelectorAll('[data-image-row]'));
    const addInput = document.getElementById('editAddImages');
    const addedPreviews = document.getElementById('editAddedPreviews');
    const status = document.getElementById('editImageStatus');
    let added = [];
    let addedUrls = [];
    const valid = file => ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 5 * 1024 * 1024;
    const remainingCount = () => rows.filter(row => row.querySelector('[data-removal]').disabled).length;
    function renderAdded() {
        addedUrls.forEach(url => URL.revokeObjectURL(url));
        addedUrls = [];
        addedPreviews.replaceChildren();
        const transfer = new DataTransfer();
        added.forEach((file, index) => {
            transfer.items.add(file);
            const wrapper = document.createElement('div');
            wrapper.className = 'space-y-2';
            const preview = document.createElement('img');
            const url = URL.createObjectURL(file);
            addedUrls.push(url);
            preview.src = url;
            preview.alt = `New product image ${index + 1}`;
            preview.className = 'h-20 w-20 rounded-xl object-cover';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Remove';
            remove.className = 'rounded-lg px-2 py-1 text-sm text-red-600';
            remove.addEventListener('click', () => { added.splice(index, 1); renderAdded(); });
            wrapper.append(preview, remove);
            addedPreviews.append(wrapper);
        });
        addInput.files = transfer.files;
    }
    rows.forEach(row => {
        const input = row.querySelector('[data-replacement]');
        const removal = row.querySelector('[data-removal]');
        const preview = row.querySelector('[data-preview]');
        const placeholder = row.querySelector('[data-placeholder]');
        const replace = row.querySelector('[data-replace]');
        const remove = row.querySelector('[data-remove]');
        const cancel = row.querySelector('[data-cancel-replacement]');
        const message = row.querySelector('[data-image-status]');
        const original = preview.getAttribute('src');
        let previewUrl = null;
        function update() {
            const removing = !removal.disabled;
            input.disabled = removing || !input.files.length;
            replace.disabled = removing;
            remove.textContent = removing ? 'Undo removal' : 'Remove';
            remove.setAttribute('aria-label', removing ? 'Undo image removal' : 'Remove image');
            row.classList.toggle('opacity-60', removing);
            cancel.hidden = !input.files.length || removing;
            message.textContent = removing ? 'Will be removed when you save.' : input.files.length ? 'Will be replaced when you save.' : '';
        }
        function resetReplacement() {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = null;
            input.value = '';
            if (original) preview.src = original;
            else preview.removeAttribute('src');
            preview.classList.toggle('hidden', !original);
            placeholder.classList.toggle('hidden', !!original);
            update();
        }
        replace.addEventListener('click', () => { input.disabled = false; input.click(); });
        input.addEventListener('cancel', update);
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            if (!valid(file)) {
                resetReplacement();
                message.textContent = 'Choose a JPG, PNG or WebP image up to 5 MB.';
                return;
            }
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            update();
        });
        cancel.addEventListener('click', resetReplacement);
        remove.addEventListener('click', () => {
            if (!removal.disabled && remainingCount() + added.length >= 5) {
                status.textContent = 'Remove a new image before undoing this removal. Maximum 5 images.';
                return;
            }
            removal.disabled = !removal.disabled;
            status.textContent = '';
            update();
        });
        update();
    });
    document.getElementById('editAddImagesButton').addEventListener('click', () => addInput.click());
    addInput.addEventListener('change', () => {
        const files = Array.from(addInput.files);
        if (files.some(file => !valid(file))) {
            status.textContent = 'Choose JPG, PNG or WebP images up to 5 MB each.';
        } else if (remainingCount() + added.length + files.length > 5) {
            status.textContent = 'You can upload up to 5 product images.';
        } else {
            added.push(...files);
            status.textContent = '';
        }
        renderAdded();
    });
});
</script>
@endpush
