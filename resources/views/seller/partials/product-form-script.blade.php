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
        const panel = document.getElementById('variantOptionChips');
        if (!panel) return;
        const groups = new Map();
        const add = (name, value) => {
            name = name.trim(); value = value.trim();
            if (!name || !value) return;
            if (!groups.has(name)) groups.set(name, new Set());
            groups.get(name).add(value);
        };
        rows.querySelectorAll('input[name*="[options]"]').forEach(input => {
            const name = input.name.match(/\[options\]\[([^\]]+)\]$/)?.[1];
            if (name) add(name, input.value);
        });
        const primaryNames = form.querySelectorAll('input[name="variation_name[]"]');
        const primaryValues = form.querySelectorAll('input[name="variation_value[]"]');
        primaryNames.forEach((input, index) => add(input.value, primaryValues[index]?.value || ''));
        panel.replaceChildren();
        if (!groups.size) {
            const empty = document.createElement('span');
            empty.className = 'spf-variant-empty'; empty.textContent = 'No options yet'; panel.appendChild(empty);
        }
        groups.forEach((values, name) => {
            const group = document.createElement('div'); group.className = 'spf-variant-chip-group';
            const label = document.createElement('span'); label.className = 'spf-variant-chip-label'; label.textContent = name;
            const chips = document.createElement('div'); chips.className = 'spf-variant-chips';
            values.forEach(value => {
                const chip = document.createElement('span'); chip.className = 'spf-variant-chip'; chip.textContent = value; chips.appendChild(chip);
            });
            group.append(label, chips); panel.appendChild(group);
        });
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

        if ((event.target.name || '').includes('[options]') || (event.target.name || '').startsWith('variation_')) {
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
        syncVariantChips();
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
    window.addEventListener('pagehide', () => {
        urls.forEach(url => URL.revokeObjectURL(url));

    });
});
</script>
@endpush
