import { initSellerFilters } from './seller-filters.js';

export function initProductListing(root) {
    if (!root || root.dataset.listingInitialized) return;
    root.dataset.listingInitialized = 'true';
    initSellerFilters(root);

    const boxes = [...root.querySelectorAll('[data-product-checkbox]')];
    const headers = [...root.querySelectorAll('[data-select-page]')];
    const rows = [...root.querySelectorAll('[data-product-row]')];
    const ids = new Set(boxes.map(box => box.value));
    const selected = new Set();
    const count = root.querySelector('[data-selected-count]');
    const clear = root.querySelector('[data-clear-selection]');
    const render = () => {
        boxes.forEach(box => { box.checked = selected.has(box.value); });
        headers.forEach(box => {
            box.checked = ids.size > 0 && selected.size === ids.size;
            box.indeterminate = selected.size > 0 && selected.size < ids.size;
            box.disabled = ids.size === 0;
        });
        rows.forEach(row => { row.dataset.selected = String(selected.has(row.dataset.productRow)); });
        count.textContent = `${selected.size} selected on this page`;
        clear.disabled = selected.size === 0;
    };
    const reset = () => { selected.clear(); render(); };
    boxes.forEach(box => box.addEventListener('change', () => {
        if (box.checked) selected.add(box.value); else selected.delete(box.value);
        render();
    }));
    headers.forEach(box => box.addEventListener('change', () => {
        selected.clear();
        if (box.checked) ids.forEach(id => selected.add(id));
        render();
    }));
    clear.addEventListener('click', reset);
    // No storage or cross-page selection. Clear browser-restored checkbox state too.
    root.ownerDocument.defaultView?.addEventListener('pageshow', reset);
    root.ownerDocument.defaultView?.addEventListener('pagehide', reset);
    render();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-product-listing]').forEach(initProductListing);
    });
}
