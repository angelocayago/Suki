@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('salesFilterForm');
    if (!form) return;
    const panel = document.getElementById('salesFilterPanel');
    const toggle = document.getElementById('salesFilterToggle');
    const close = document.getElementById('salesFilterClose');
    const backdrop = document.getElementById('salesFilterBackdrop');
    const mobile = window.matchMedia('(max-width: 640px)');
    let inertElements = [], previousOverflow = '';
    function releaseModal() {
        inertElements.forEach(element => element.inert = false);
        inertElements = [];
        if (panel.getAttribute('aria-modal') === 'true') document.body.style.overflow = previousOverflow;
        panel.removeAttribute('aria-modal');
        panel.removeAttribute('role');
        backdrop.hidden = true;
    }
    function modalMode() {
        releaseModal();
        if (panel.hidden || !mobile.matches) return;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        backdrop.hidden = false;
        let node = panel;
        while (node.parentElement && node !== document.body) {
            Array.from(node.parentElement.children).forEach(sibling => {
                if (sibling !== node && sibling !== backdrop && !sibling.inert) {
                    sibling.inert = true; inertElements.push(sibling);
                }
            });
            node = node.parentElement;
        }
    }
    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        modalMode();
        if (open) close.focus();
        else toggle.focus();
    }
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    mobile.addEventListener('change', modalMode);
    document.addEventListener('keydown', event => {
        if (panel.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key === 'Tab' && mobile.matches) {
            const focusable = Array.from(panel.querySelectorAll('button, a[href], input:not([type=hidden]), select')).filter(element => !element.disabled && element.getClientRects().length);
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    const dates = document.getElementById('salesCustomDates');
    const from = document.getElementById('salesFrom'), to = document.getElementById('salesTo');
    function customDates() {
        const custom = form.querySelector('[name=date]:checked').value === 'custom';
        dates.hidden = !custom;
        [from, to].forEach(input => { input.disabled = !custom; input.required = custom; });
        to.min = from.value;
    }
    form.querySelectorAll('[name=date]').forEach(radio => radio.addEventListener('change', customDates));
    from.addEventListener('change', () => to.min = from.value);
    customDates();
    form.addEventListener('invalid', () => setOpen(true), true);
    const search = document.getElementById('salesSearch');
    const originalSearch = search.value.trim();
    const apply = document.getElementById('salesApply');
    let searchTimer, submitting = false;
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        if (search.value.trim() === originalSearch || submitting) return;
        searchTimer = setTimeout(() => { if (!submitting) form.requestSubmit(); }, 300);
    });
    form.addEventListener('submit', event => {
        clearTimeout(searchTimer);
        if (submitting) { event.preventDefault(); return; }
        const min = document.getElementById('salesMin'), max = document.getElementById('salesMax');
        max.setCustomValidity(min.value !== '' && max.value !== '' && Number(min.value) > Number(max.value) ? 'Maximum amount must be at least the minimum amount.' : '');
        if (!form.reportValidity()) { event.preventDefault(); setOpen(true); return; }
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        apply.disabled = true; apply.textContent = 'Applying…';
        // Normalize the actual GET submission without changing visible choices.
        // The next request starts on page one because no page field is submitted.
        const defaults = {date:'any', status:'all', payment:'all', fulfillment:'all', voucher:'all', sort:'newest'};
        const disabled = [];
        form.querySelectorAll('[name]').forEach(input => {
            if (!input.disabled && (input.value === '' || defaults[input.name] === input.value)) {
                input.disabled = true; disabled.push(input);
            }
        });
        setTimeout(() => disabled.forEach(input => input.disabled = false), 0);
    });
    ['salesMin','salesMax'].forEach(id => document.getElementById(id).addEventListener('input', () => document.getElementById('salesMax').setCustomValidity('')));
    window.addEventListener('pageshow', () => {
        submitting = false; apply.disabled = false; apply.textContent = 'Apply Filters'; form.removeAttribute('aria-busy');
        customDates();
    });

    const productSearch = document.getElementById('salesProductSearch');
    const productId = document.getElementById('salesProductId');
    const results = document.getElementById('salesProductResults');
    const productStatus = document.getElementById('salesProductStatus');
    let productTimer, controller, requestSequence = 0, lastTerm = null;
    async function findProducts() {
        const term = productSearch.value.trim();
        if (term === lastTerm) { results.hidden = results.children.length === 0; return; }
        lastTerm = term;
        controller?.abort(); controller = new AbortController();
        const sequence = ++requestSequence;
        productStatus.textContent = 'Searching products…';
        try {
            const url = new URL(productSearch.dataset.url, window.location.href);
            url.searchParams.set('q', term);
            const response = await fetch(url, {signal:controller.signal, headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Product lookup failed');
            const products = await response.json();
            if (sequence !== requestSequence) return;
            results.replaceChildren();
            products.forEach(product => {
                const item = document.createElement('li'), button = document.createElement('button');
                button.type = 'button'; button.textContent = product.name;
                button.addEventListener('click', () => {
                    productId.value = product.id; productSearch.value = product.name;
                    productStatus.textContent = 'Selected: ' + product.name; results.hidden = true;
                });
                item.appendChild(button); results.appendChild(item);
            });
            results.hidden = products.length === 0;
            productStatus.textContent = products.length ? 'Choose a matching product.' : 'No matching products.';
        } catch (error) {
            if (error.name === 'AbortError' || sequence !== requestSequence) return;
            lastTerm = null; results.hidden = true;
            productStatus.textContent = 'Products could not be loaded. Try again.';
        }
    }
    productSearch.addEventListener('input', () => {
        clearTimeout(productTimer); controller?.abort(); ++requestSequence;
        lastTerm = null;
        productId.value = ''; results.hidden = true;
        productTimer = setTimeout(findProducts, 300);
    });
    productSearch.addEventListener('focus', () => { if (!productId.value) findProducts(); });
    document.getElementById('salesProductClear').addEventListener('click', () => {
        clearTimeout(productTimer); controller?.abort(); ++requestSequence; lastTerm = null;
        productId.value = ''; productSearch.value = ''; results.replaceChildren(); results.hidden = true;
        productStatus.textContent = 'No product selected.';
    });
});
</script>
@endpush
