@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('universalProductForm');
    if (!form) return;
    const engine = globalThis.SukiProductOptions;
    const oldGroups = {{ \Illuminate\Support\Js::from(old('option_groups', [])) }};
    const oldVariants = {{ \Illuminate\Support\Js::from(old('variants', [])) }};
    const oldMode = {{ \Illuminate\Support\Js::from(old('product_mode', 'simple')) }};
    const oldColors = {{ \Illuminate\Support\Js::from(old('image_colors', [])) }};
    const groups = document.getElementById('optionGroups'), rows = document.getElementById('generatedVariants');
    const simple = document.getElementById('simplePricing'), mode = document.getElementById('productMode');
    const feedback = document.getElementById('optionFeedback'), count = document.getElementById('variantCount');
    const generate = document.getElementById('generateVariants'), add = document.getElementById('addOption');
    const previous = new Map();
    const canSubmit = !form.querySelector('button[type=submit]').disabled;
    let dirty = false;
    const text = (tag, value) => { const node = document.createElement(tag); node.textContent = value; return node; };
    const rawGroups = () => Array.from(groups.children, row => ({name:row.querySelector('[data-option-name]').value,values:row.querySelector('[data-option-values]').value}));
    function reindexGroups() {
        Array.from(groups.children).forEach((row,index) => {
            row.querySelector('[data-option-name]').name = `option_groups[${index}][name]`;
            row.querySelector('[data-option-values]').name = `option_groups[${index}][values]`;
        });
        add.disabled = groups.children.length >= 3;
        generate.hidden = groups.children.length === 0;
    }
    function capture() {
        Array.from(rows.children).forEach(row => {
            const value = {options:row.optionValues};
            row.querySelectorAll('[data-field]').forEach(input => value[input.dataset.field] = input.value);
            previous.set(engine.identity(value.options), value);
        });
    }
    function setMode(variantMode) {
        mode.value = variantMode ? 'variants' : 'simple';
        simple.hidden = variantMode;
        simple.querySelectorAll('input').forEach(input => input.disabled = variantMode);
        document.getElementById('pricingHelp').textContent = variantMode ? 'Each generated combination has its own price and stock below.' : 'Set a single price and stock count, or generate options below.';
        count.textContent = variantMode ? `${rows.children.length} combinations. Enter a price and stock for each.` : 'No options needed? Keep the single price and stock above.';
    }
    function addGroup(value = {}) {
        if (groups.children.length >= 3) return;
        const row = document.createElement('div'); row.className = 'spf-option-row';
        const nameLabel = text('label','Option Name'), valuesLabel = text('label','Values, comma-separated');
        const name = document.createElement('input'), values = document.createElement('input');
        name.dataset.optionName = ''; values.dataset.optionValues = '';
        name.maxLength = 40; values.maxLength = 2100;
        name.value = value.name || ''; values.value = value.values || '';
        name.placeholder = 'e.g. Flavor'; values.placeholder = 'e.g. Chocolate, Vanilla';
        nameLabel.append(name); valuesLabel.append(values);
        const remove = text('button','Remove'); remove.type = 'button'; remove.setAttribute('aria-label','Remove option group');
        remove.addEventListener('click', () => {
            if (groups.children.length === 1 && rows.children.length && !window.confirm('Return to a simple product? Generated rows will not be submitted.')) return;
            capture(); row.remove(); reindexGroups(); dirty = true;
            if (!groups.children.length) { rows.replaceChildren(); setMode(false); dirty = false; feedback.textContent = ''; }
            else feedback.textContent = 'Options changed. Generate again to update the combinations.';
        });
        row.append(nameLabel,valuesLabel,remove); groups.append(row); reindexGroups();
        return name;
    }
    function renderVariants(variants) {
        rows.replaceChildren();
        variants.forEach((variant,index) => {
            const row = document.createElement('article'); row.optionValues = variant.options || {};
            const heading = text('h4', Object.entries(row.optionValues).map(([name,value]) => `${name}: ${value}`).join(' / '));
            row.append(heading);
            for (const [name,value] of Object.entries(row.optionValues)) {
                const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = `variants[${index}][options][${name}]`; hidden.value = value; row.append(hidden);
            }
            const fields = document.createElement('div'); fields.className = 'spf-grid';
            for (const [field,label,type,step,max] of [
                ['price','Price (PHP) *','number','0.01','21474836.47'],
                ['stock','Stock Quantity *','number','1','2147483647'],
                ['sku','SKU (optional)','text',null,null],
                ['weight','Weight Override (kg, optional)','number','0.001','2147483.647'],
            ]) {
                const wrapper = text('label',label), input = document.createElement('input');
                input.type = type; input.name = `variants[${index}][${field}]`; input.dataset.field = field;
                input.value = variant[field] ?? ''; input.setAttribute('aria-label',`${heading.textContent} — ${label}`);
                if (type === 'number') { input.min = '0'; input.step = step; input.max = max; }
                if (field === 'sku') { input.maxLength = 50; input.placeholder = 'Generate automatically'; }
                if (field === 'weight') input.placeholder = 'Use product weight';
                input.required = field === 'price' || field === 'stock';
                wrapper.append(input); fields.append(wrapper);
            }
            row.append(fields); rows.append(row);
        });
        setMode(variants.length > 0);
    }
    add.addEventListener('click', () => { const input = addGroup(); input?.focus(); });
    groups.addEventListener('input', () => { dirty = true; feedback.textContent = 'Options changed. Generate combinations before submitting.'; });
    generate.addEventListener('click', () => {
        try {
            capture();
            const next = engine.regenerate(engine.normalize(rawGroups()), previous);
            const retained = new Set(next.map(row => engine.identity(row.options)));
            const removed = Array.from(rows.children).filter(row => !retained.has(engine.identity(row.optionValues))).length;
            if (removed && !window.confirm(`${removed} combinations will be removed from this submission. Continue?`)) return;
            renderVariants(next); dirty = false; feedback.textContent = '';
        } catch (error) { feedback.textContent = error.message; }
    });
    Object.values(oldGroups || {}).forEach(addGroup);
    if (oldMode === 'variants' && Object.values(oldVariants || {}).length) {
        renderVariants(Object.values(oldVariants));
        capture();
    }
    form.addEventListener('submit', event => {
        try {
            const expected = engine.combinations(engine.normalize(rawGroups()));
            if (dirty || (expected.length && mode.value !== 'variants')) throw Error('Generate the current options before submitting.');
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1';
            const button = form.querySelector('button[type=submit]'); button.disabled = true; button.textContent = 'Submitting…';
        } catch (error) { event.preventDefault(); feedback.textContent = error.message; feedback.scrollIntoView({block:'center'}); }
    });
    window.addEventListener('pageshow', () => {
        delete form.dataset.submitting;
        const button = form.querySelector('button[type=submit]'); button.disabled = !canSubmit; button.textContent = 'Submit Product for Review';
    });

    const picker = document.getElementById('images'), preview = document.getElementById('imagePreview'), uploadStatus = document.getElementById('imageUploadStatus');
    let photos = [], urls = [];
    function renderPhotos() {
        urls.forEach(url => URL.revokeObjectURL(url)); urls = []; preview.replaceChildren();
        const invalid = photos.length > 20 || photos.some(({file}) => !['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 4*1024*1024);
        const message = invalid ? 'Choose up to 20 JPG, PNG or WebP photos, each no larger than 4 MB.' : '';
        picker.setCustomValidity(message); uploadStatus.textContent = message;
        photos.forEach((photo,index) => {
            const figure = document.createElement('figure'), image = document.createElement('img');
            const url = URL.createObjectURL(photo.file); urls.push(url); image.src = url; image.alt = photo.file.name;
            const caption = text('figcaption',`${index === 0 ? 'Cover · ' : ''}${photo.file.name}`);
            const label = text('label','Color association (optional)'), color = document.createElement('input');
            color.name = `image_colors[${index}]`; color.maxLength = 100; color.value = photo.color;
            color.addEventListener('input', () => photo.color = color.value); label.append(color);
            const remove = text('button','Remove photo'); remove.type = 'button'; remove.dataset.removePhoto = '';
            if (typeof DataTransfer !== 'function') { remove.disabled = true; remove.title = 'Reselect files to change this selection in your browser.'; }
            remove.addEventListener('click', () => {
                photos.splice(index,1); const transfer = new DataTransfer(); photos.forEach(({file}) => transfer.items.add(file)); picker.files = transfer.files; renderPhotos();
            });
            figure.append(image,caption,label,remove); preview.append(figure);
        });
    }
    picker.addEventListener('change', () => { photos = Array.from(picker.files, (file,index) => ({file,color:oldColors[index] || ''})); renderPhotos(); });
    window.addEventListener('pagehide', () => urls.forEach(url => URL.revokeObjectURL(url)));
});
</script>
@endpush
