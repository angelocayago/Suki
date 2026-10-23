export function initSellerFilters(root) {
    if (!root || root.dataset.filtersInitialized) return;
    const toggle = root.querySelector('[data-filter-toggle]');
    const panel = root.querySelector('[data-filter-panel]');
    const close = root.querySelector('[data-filter-close]');
    if (!toggle || !panel || !close) return;
    root.dataset.filtersInitialized = 'true';
    const setOpen = (open, restoreFocus = false) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (restoreFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false, true));
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false, true); }
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-seller-filters]').forEach(initSellerFilters);
    });
}
