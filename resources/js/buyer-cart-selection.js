// The server owns the draft and final money. Local merchandise arithmetic is a labelled preview only.
(function (root) {
    function state(rows) {
        const selectable = rows.filter(row => row.eligible || row.checked);
        const selected = selectable.filter(row => row.checked).length;
        return { checked: selectable.length > 0 && selected === selectable.length, indeterminate: selected > 0 && selected < selectable.length, disabled: selectable.length === 0 };
    }
    function toggle(rows, checked) {
        return rows.map(row => ({ ...row, checked: checked ? row.checked || row.eligible : false }));
    }
    function merchandisePreview(rows) {
        let total = 0;
        for (const row of rows.filter(row => row.checked)) {
            if (!/^\d+$/.test(String(row.priceMinor)) || !/^[1-9]\d*$/.test(String(row.quantity))) return null;
            const price = Number(row.priceMinor), quantity = Number(row.quantity);
            if (row.priceMinor === undefined || row.quantity === undefined || !Number.isSafeInteger(price) || price < 0 || !Number.isSafeInteger(quantity) || quantity < 1) return null;
            total += price * quantity;
            if (!Number.isSafeInteger(total)) return null;
        }
        return total;
    }
    const api = { state, toggle, merchandisePreview };
    root.SukiCartSelection = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    const document = root.document;
    if (!document) return;
    function mount() {
        const cart = document.querySelector('[data-buyer-cart]');
        const form = document.getElementById('cart-selection-form');
        if (!cart || !form || form.dataset.cartSelectionReady) return;
        form.dataset.cartSelectionReady = '1';
        const revision = form.querySelector('[data-draft-revision]');
        const status = cart.querySelector('[data-cart-selection-status]');
        const rows = () => [...cart.querySelectorAll('[data-cart-select]')];
        let dirty = false, pending = null, failed = false, replaying = false;
        const read = inputs => inputs.map(input => ({ eligible: input.dataset.eligible === '1', checked: input.checked }));
        const selectionKey = () => JSON.stringify(rows().filter(input => input.checked && !input.disabled).map(input => input.value));
        function preview() {
            const all = rows(), count = all.filter(input => input.checked).length;
            const amount = merchandisePreview(all.map(input => ({ checked: input.checked, priceMinor: input.dataset.unitPriceMinor, quantity: input.dataset.quantity })));
            cart.querySelectorAll('[data-selected-count]').forEach(node => node.textContent = count + ' selected item' + (count === 1 ? '' : 's') + ' · confirming');
            cart.querySelector('[data-mobile-count]').textContent = count + ' selected · confirming';
            const subtotal = cart.querySelector('[data-selected-subtotal]');
            if (subtotal) subtotal.textContent = amount === null ? 'Confirming…' : '₱' + (amount / 100).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' (preview)';
            cart.querySelectorAll('[data-selected-shipping], [data-selected-discount], [data-selected-total], [data-voucher-result]').forEach(node => node.textContent = 'Confirming…');
            const sellers = cart.querySelector('[data-selected-sellers]');
            if (sellers) sellers.textContent = '';
            cart.querySelector('[data-mobile-total]').textContent = 'Confirming…';
            cart.querySelector('[data-checkout-reason]').textContent = 'Confirming current prices, shipping and vouchers before checkout.';
            status.textContent = 'Merchandise preview only. Selection and final totals are not confirmed yet.';
            busy(true);
        }
        function checkboxes() {
            const all = rows();
            cart.querySelectorAll('[data-cart-all], [data-cart-store]').forEach(input => {
                const group = input.hasAttribute('data-cart-store') ? all.filter(row => row.dataset.store === input.dataset.cartStore) : all;
                Object.assign(input, state(read(group)));
                input.setAttribute('aria-checked', input.indeterminate ? 'mixed' : String(input.checked));
            });
            all.forEach(input => input.closest('[data-cart-row]').classList.toggle('is-selected', input.checked));
            cart.querySelectorAll('[data-store-count]').forEach(count => {
                count.textContent = all.filter(input => input.dataset.store === count.dataset.storeCount && input.checked && input.dataset.eligible === '1').length;
            });
        }
        function busy(value) {
            cart.setAttribute('aria-busy', String(value));
            // Prevent checkout with totals that belong to an earlier selection.
            cart.querySelectorAll('[data-cart-checkout]').forEach(button => {
                if (value) button.disabled = true;
            });
        }
        function unconfirmed() {
            cart.querySelector('[data-mobile-total]').textContent = 'Unconfirmed';
            cart.querySelector('[data-selected-total]').textContent = 'Unconfirmed';
            cart.querySelector('[data-checkout-reason]').textContent = 'Your selection is unconfirmed. Refresh the Cart or use Update Selection to retry.';
        }
        function render(data) {
            revision.value = data.selection.revision;
            const current = Object.values(data.cart);
            rows().forEach(input => {
                const line = current.find(line => String(line.id) === input.value);
                input.checked = data.selection.ids.some(id => String(id) === input.value);
                input.dataset.eligible = line?.available ? '1' : '0';
                if (line) { input.dataset.unitPriceMinor = line.price_minor; input.dataset.quantity = line.quantity; }
                input.disabled = !line?.available && !input.checked;
                const warning = input.closest('[data-cart-row]').querySelector('[data-cart-availability]');
                warning.textContent = line?.available ? line.stock + ' available' : 'Unavailable: ' + (line?.availability_issue || 'This item is no longer in your Cart. Refresh to review it.');
                warning.className = line?.available ? 'cart-muted' : 'cart-warning';
                const quantity = input.closest('[data-cart-row]').querySelector('[data-cart-quantity]');
                if (line && quantity) quantity.dataset.stock = line.stock;
                const increase = input.closest('[data-cart-row]').querySelector('[data-cart-increase]');
                if (increase) increase.disabled = !line?.available || line.quantity >= line.stock;
            });
            // Preserve an entered voucher and its native submitter during recalculation.
            const voucherForm = cart.querySelector('.cart-voucher-form');
            cart.querySelector('[data-cart-summary]').innerHTML = data.summary_html;
            if (voucherForm) cart.querySelector('.cart-voucher-form').replaceWith(voucherForm);
            cart.querySelector('[data-mobile-total]').textContent = cart.querySelector('[data-selected-total]').textContent;
            cart.querySelector('[data-mobile-count]').textContent = data.selection.ids.length + ' selected';
            const blocked = !data.selection.ids.length || data.selected_summary.has_unavailable_items || Boolean(data.selected_summary.voucher_error);
            cart.querySelectorAll('[data-cart-checkout]').forEach(button => { button.disabled = blocked; button.setAttribute('aria-disabled', String(blocked)); });
            checkboxes();
        }
        async function save() {
            if (pending) return pending;
            pending = (async () => {
                busy(true); failed = false;
                while (dirty) {
                    dirty = false;
                    status.textContent = 'Updating selection and totals…';
                    const data = new root.FormData(form);
                    const sentKey = selectionKey();
                    data.set('intent', 'update');
                    try {
                        const response = await root.fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        const result = await root.SukiBuyerFeedback.responseMessage(response);
                        // Never overwrite checkbox edits made while a request is in flight.
                        if (result.data) revision.value = result.data.selection.revision;
                        if (!result.success) {
                            dirty = false; failed = true;
                            if (result.data) render(result.data);
                            else unconfirmed();
                            status.textContent = result.message;
                            root.SukiBuyerFeedback.show(result.message);
                            break;
                        }
                        if (dirty && sentKey === selectionKey()) dirty = false;
                        if (!dirty) render(result.data);
                        status.textContent = 'Selection saved. Totals updated.';
                    } catch (_) {
                        dirty = false; failed = true;
                        status.textContent = 'Selection could not be saved. Refresh the Cart to restore it, or use Update Selection to retry.';
                        unconfirmed();
                        root.SukiBuyerFeedback.show('Network error. Please check your connection and try again.');
                        break;
                    }
                }
                cart.setAttribute('aria-busy', 'false');
                if (failed) cart.querySelectorAll('[data-cart-checkout]').forEach(button => button.disabled = true);
            })();
            try { await pending; } finally { pending = null; }
            return !failed;
        }
        cart.querySelectorAll('.cart-enhanced-control').forEach(label => label.hidden = false);
        checkboxes();
        cart.addEventListener('change', event => {
            const input = event.target;
            if (!input.matches('[data-cart-select], [data-cart-all], [data-cart-store]')) return;
            if (!input.hasAttribute('data-cart-select')) {
                const group = rows().filter(row => !input.hasAttribute('data-cart-store') || row.dataset.store === input.dataset.cartStore);
                toggle(read(group), input.checked).forEach((value, index) => {
                    group[index].checked = value.checked;
                    group[index].disabled = group[index].dataset.eligible !== '1' && !value.checked;
                });
            } else if (input.dataset.eligible !== '1' && !input.checked) input.disabled = true;
            dirty = true; checkboxes(); preview(); void save();
        });
        // Native row actions keep working, but first persist any pending edits.
        cart.addEventListener('submit', event => {
            const submitted = event.target, submitter = event.submitter;
            if (submitted === form && form.dataset.pending) { event.preventDefault(); return; }
            const update = submitted === form && submitter?.value !== 'checkout';
            if (update && !replaying) {
                event.preventDefault(); event.stopImmediatePropagation(); dirty = true; preview(); void save(); return;
            }
            if (!replaying && (pending || dirty || failed && submitted === form)) {
                event.preventDefault(); event.stopImmediatePropagation();
                const action = submitted.action;
                void (async () => {
                    if (failed && submitted === form) dirty = true;
                    await save();
                    if (failed && submitted === form) return;
                    replaying = true;
                    try {
                        const target = submitted.isConnected ? submitted : [...cart.querySelectorAll('form')].find(candidate => candidate.action === action);
                        if (!target) { status.textContent = 'The Cart changed. Please review it before continuing.'; return; }
                        const button = submitted === form ? cart.querySelector('[data-cart-checkout]') : submitter?.form === target ? submitter : target.querySelector('button[type="submit"]');
                        if (submitted === form && (!button || button.disabled)) return;
                        if (button) target.requestSubmit(button); else target.requestSubmit();
                    } finally { replaying = false; }
                })();
                return;
            }
            if (submitted === form) {
                if (form.dataset.pending) { event.preventDefault(); return; }
                form.dataset.pending = '1';
                root.setTimeout(() => cart.querySelectorAll('[data-cart-checkout], [data-update-selection]').forEach(button => button.disabled = true), 0);
            }
        }, true);
        const details = cart.querySelector('[data-cart-summary-details]');
        details.open = !root.matchMedia('(max-width: 639px)').matches;
        root.addEventListener('pageshow', event => {
            delete form.dataset.pending;
            if (event.persisted) root.location.reload(); // restore authoritative draft after Back/Forward cache
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount); else mount();
})(typeof window === 'undefined' ? globalThis : window);
