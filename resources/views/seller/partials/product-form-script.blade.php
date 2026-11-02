@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('productForm');
    if (!form) return;
    const rows = document.getElementById('productVariantRows');
    const template = document.getElementById('productVariantTemplate');
    // Old input can contain sparse indexes after removing an unsaved row.
    let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('[name]'), input => Number(input.name.match(/^variants\[(\d+)\]/)?.[1] ?? -1))) + 1;
    function syncVariantChips() {
        const colorContainer = document.getElementById('variantColorChips');
        const sizeContainer = document.getElementById('variantSizeChips');

        if (!colorContainer || !sizeContainer) return;

        const colors = new Set();
        const sizes = new Set();

        rows.querySelectorAll('input[name*="[options][Color]"]').forEach((input) => {
            const value = input.value.trim();
            if (value) colors.add(value);
        });

        rows.querySelectorAll('input[name*="[options][Size]"]').forEach((input) => {
            const value = input.value.trim();
            if (value) sizes.add(value);
        });

        function render(container, values, emptyText) {
            container.replaceChildren();

            if (!values.size) {
                const empty = document.createElement('span');
                empty.className = 'spf-variant-empty';
                empty.textContent = emptyText;
                container.appendChild(empty);
                return;
            }

            values.forEach((value) => {
                const chip = document.createElement('span');
                chip.className = 'spf-variant-chip';
                chip.textContent = value;
                container.appendChild(chip);
            });
        }

        render(colorContainer, colors, 'No colors yet');
        render(sizeContainer, sizes, 'No sizes yet');
    }

    function updateStock(row) {
        const stock = row.querySelector('[data-variant-stock], #stock');
        const status = row.querySelector('[data-variant-status]');
        const output = row.querySelector('[data-stock-indicator]');
        const value = stock.value === '' ? NaN : Number(stock.value);
        const active = status.value === 'active';
        output.dataset.level = !active || !Number.isInteger(value) || value < 0 ? 'none' : value === 0 ? 'empty' : value <= 10 ? 'low' : 'in';
        output.textContent = !active ? 'Inactive variant: excluded from stock alerts' : !Number.isInteger(value) || value < 0 ? 'Enter a valid stock quantity' : value === 0 ? 'Out of Stock' : value <= 10 ? 'Low Stock' : 'In Stock';
    }
    form.querySelectorAll('[data-stock-row]').forEach(updateStock);
    syncVariantChips();

    form.addEventListener('input', event => {
        const row = event.target.closest('[data-stock-row]');
        if (row) updateStock(row);

        if (event.target.matches('input[name*="[options][Color]"], input[name*="[options][Size]"]')) {
            syncVariantChips();
        }
    });
    form.addEventListener('change', event => {
        const row = event.target.closest('[data-stock-row]');
        if (row) updateStock(row);
    });
    document.getElementById('addProductVariant').addEventListener('click', () => {
        if (rows.children.length >= 50) return;
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        const row = holder.firstElementChild;
        rows.appendChild(row);
        updateStock(row);
        syncVariantChips();
        row.querySelector('input:not([type=hidden])').focus();
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-product-variant');

        if (button) {
            button.closest('.product-variant-row').remove();
            syncVariantChips();
        }
    });
    const options = document.getElementById('primaryOptions');
    document.getElementById('addPrimaryOption').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'spf-option-row';
        row.innerHTML = '<label>Option name<input name="variation_name[]" maxlength="100" placeholder="Color"></label><label>Option value<input name="variation_value[]" maxlength="255" placeholder="Blue"></label><button type="button" data-remove-option aria-label="Remove option">Remove</button>';
        options.appendChild(row);
        row.querySelector('input').focus();
    });
    options.addEventListener('click', event => {
        if (!event.target.closest('[data-remove-option]')) return;
        const row = event.target.closest('.spf-option-row');
        if (options.children.length > 1) row.remove();
        else row.querySelectorAll('input').forEach(input => input.value = '');
    });
    const picker = document.getElementById('images');
    const preview = document.getElementById('imagePreview');
    const uploadStatus = document.getElementById('imageUploadStatus');
    const oldColors = {{ \Illuminate\Support\Js::from(old('image_colors', [])) }};
    let urls = [];
    picker.addEventListener('change', () => {
        urls.forEach(url => URL.revokeObjectURL(url));
        urls = [];
        preview.replaceChildren();
        const files = Array.from(picker.files);
        const invalid = files.length > 20 || files.some(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024);
        const message = invalid ? 'Choose up to 20 JPG, PNG or WebP images, each no larger than 4 MB.' : '';
        picker.setCustomValidity(message);
        uploadStatus.textContent = message;
        if (invalid) return;
        files.forEach((file, index) => {
            const figure = document.createElement('figure');
            const img = document.createElement('img');
            const url = URL.createObjectURL(file);
            urls.push(url);
            img.src = url;
            img.alt = file.name;
            const caption = document.createElement('figcaption');
            caption.textContent = file.name;
            const label = document.createElement('label');
            label.textContent = 'Color (optional)';
            const color = document.createElement('input');
            color.name = `image_colors[${index}]`;
            color.maxLength = 100;
            color.placeholder = 'e.g. Blue';
            color.value = oldColors[index] ?? '';
            label.appendChild(color);
            figure.append(img, caption, label);
            preview.appendChild(figure);
        });
    });
    const videoPicker = document.getElementById('video');
    const videoPreview = document.getElementById('videoPreview');
    const videoStatus = document.getElementById('videoUploadStatus');
    let videoUrl = null;

    videoPicker?.addEventListener('change', () => {
        if (videoUrl) URL.revokeObjectURL(videoUrl);
        videoUrl = null;
        videoPreview.replaceChildren();

        const file = videoPicker.files[0];
        if (!file) {
            videoPicker.setCustomValidity('');
            videoStatus.textContent = '';
            return;
        }

        const invalid = !['video/mp4', 'video/webm'].includes(file.type) || file.size > 25 * 1024 * 1024;
        const message = invalid ? 'Choose one MP4 or WebM video no larger than 25 MB.' : '';

        videoPicker.setCustomValidity(message);
        videoStatus.textContent = message;

        if (invalid) return;

        const video = document.createElement('video');
        videoUrl = URL.createObjectURL(file);
        video.src = videoUrl;
        video.controls = true;
        video.preload = 'metadata';
        video.style.width = '100%';
        video.style.maxHeight = '360px';
        video.style.marginTop = '12px';
        video.style.borderRadius = '12px';

        videoPreview.appendChild(video);
    });

    window.addEventListener('pagehide', () => {
        urls.forEach(url => URL.revokeObjectURL(url));
        if (videoUrl) URL.revokeObjectURL(videoUrl);
    });
});
</script>
@endpush
