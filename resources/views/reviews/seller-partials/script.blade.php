@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-review-product-image]').forEach(image => {
        const hideBroken = () => image.hidden = true;
        image.addEventListener('error', hideBroken);
        if (image.complete && image.naturalWidth === 0) hideBroken();
    });
    document.querySelectorAll('[data-review-reply]').forEach(form => {
        const button = form.querySelector('button');
        let submitting = false;
        form.addEventListener('submit', event => {
            if (submitting || !form.reportValidity()) { event.preventDefault(); return; }
            submitting = true; button.disabled = true; button.textContent = 'Saving…'; form.setAttribute('aria-busy','true');
        });
        window.addEventListener('pageshow', () => {
            submitting = false; button.disabled = false; button.textContent = 'Save reply'; form.removeAttribute('aria-busy');
        });
    });
    const form = document.getElementById('sellerReviewFilters');
    if (!form) return;
    const toggle = document.getElementById('reviewFilterToggle');
    const panel = document.getElementById('reviewFilterPanel');
    function openFilters(open) {
        panel.hidden = !open; toggle.setAttribute('aria-expanded',String(open));
    }
    toggle.addEventListener('click', () => openFilters(panel.hidden));
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { openFilters(false); toggle.focus(); }
    });
    const search = document.getElementById('reviewSearch');
    const original = search.value.trim();
    const apply = document.getElementById('reviewApply');
    let timer, submitting = false;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        if (search.value.trim() !== original && !submitting) timer = setTimeout(() => { if (!submitting) form.requestSubmit(); },300);
    });
    form.addEventListener('submit', event => {
        clearTimeout(timer);
        if (submitting) { event.preventDefault(); return; }
        submitting = true; apply.disabled = true; apply.textContent = 'Applying…'; form.setAttribute('aria-busy','true');
        const empty = Array.from(form.querySelectorAll('[name]')).filter(input => !input.disabled && input.value === '');
        empty.forEach(input => input.disabled = true);
        setTimeout(() => empty.forEach(input => input.disabled = false),0);
    });
    window.addEventListener('pageshow', () => {
        submitting = false; apply.disabled = false; apply.textContent = 'Apply Filters'; form.removeAttribute('aria-busy');
    });
    const picker = document.getElementById('reviewProductSearch');
    const id = document.getElementById('reviewProductId');
    const results = document.getElementById('reviewProductResults');
    const status = document.getElementById('reviewProductStatus');
    let productTimer, controller, sequence = 0, lastTerm = null;
    async function products() {
        const term = picker.value.trim();
        if (term === lastTerm) { results.hidden = results.children.length === 0; return; }
        lastTerm = term; controller?.abort(); controller = new AbortController();
        const current = ++sequence;
        status.textContent = 'Searching products…';
        try {
            const url = new URL(picker.dataset.url,window.location.href); url.searchParams.set('q',term);
            const response = await fetch(url,{signal:controller.signal,headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Product lookup failed');
            const rows = await response.json();
            if (current !== sequence) return;
            results.replaceChildren();
            rows.forEach(product => {
                const item = document.createElement('li'), button = document.createElement('button');
                button.type = 'button'; button.textContent = product.name;
                button.addEventListener('click', () => {
                    id.value = product.id; picker.value = product.name; results.hidden = true; status.textContent = 'Selected: '+product.name;
                });
                item.appendChild(button); results.appendChild(item);
            });
            results.hidden = rows.length === 0;
            status.textContent = rows.length ? 'Select a product.' : 'No matching products.';
        } catch(error) {
            if (error.name === 'AbortError' || current !== sequence) return;
            lastTerm = null; results.hidden = true; status.textContent = 'Products could not be loaded. Try again.';
        }
    }
    picker.addEventListener('input', () => {
        clearTimeout(productTimer); controller?.abort(); ++sequence; lastTerm = null;
        id.value = ''; results.hidden = true; productTimer = setTimeout(products,300);
    });
    picker.addEventListener('focus', () => { if (!id.value) products(); });
    picker.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !id.value) { event.preventDefault(); clearTimeout(productTimer); products(); }
        if (event.key === 'ArrowDown' && !results.hidden) { event.preventDefault(); results.querySelector('button')?.focus(); }
    });
    document.getElementById('reviewProductClear').addEventListener('click', () => {
        clearTimeout(productTimer); controller?.abort(); ++sequence; lastTerm = null;
        id.value = ''; picker.value = ''; results.replaceChildren(); results.hidden = true; status.textContent = '';
    });
});
</script>
@endpush
