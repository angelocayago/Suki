// Buyer action feedback. Native form submission remains the default, including Shop hearts.
(function (root) {
    const sessionMessage = 'Your session expired. Please refresh the page or log in again.';
    function quantityMessage(value, stock) {
        if (String(value).trim() === '') return 'Please enter a quantity.';
        if (!/^-?[0-9]+$/.test(String(value).trim())) return 'Quantity must be a whole number.';
        const quantity = Number(value);
        if (quantity < 1) return 'Please enter a quantity of at least 1.';
        if (quantity > Number(stock)) return Number(stock) < 1 ? 'This item is out of stock.' : 'Only ' + stock + ' available. Please lower your quantity.';
        return '';
    }
    async function responseMessage(response) {
        if (response.status === 401 || response.status === 419) return {success:false,message:sessionMessage,errors:{},code:response.status===419?'session_expired':'auth_required'};
        if (response.status >= 500) return {success:false,message:'Something went wrong. Please try again.',errors:{},code:'server_error'};
        try { const result = await response.json(); if (typeof result.message === 'string' && typeof result.success === 'boolean') return result; } catch (_) {}
        return {success:false,message:'Something went wrong. Please try again.',errors:{},code:'invalid_response'};
    }
    function show(message, type = 'error') {
        const box = root.document?.querySelector('[data-feedback-live]');
        if (box) {
            box.textContent = message; box.hidden = false;
            box.setAttribute?.('role', type === 'success' ? 'status' : 'alert');
            box.className = 'my-4 rounded-xl border p-4 text-sm ' + (type === 'success' ? 'border-green-200 bg-green-50 text-green-900' : 'border-red-200 bg-red-50 text-red-800');
            if (type === 'success') root.setTimeout(() => { if (box.textContent === message) box.hidden = true; }, 6000);
        }
    }
    function inline(field, message) {
        field.setAttribute('aria-invalid', String(Boolean(message)));
        const error = root.document.getElementById(field.getAttribute('aria-describedby'));
        if (error) error.textContent = message;
    }
    function validate(field) {
        const message = quantityMessage(field.value, field.dataset.stock);
        inline(field, message);
        const hint = root.document.getElementById('quantityLimitHint');
        if (hint && field.id === 'quantityInput') hint.textContent = !message && Number(field.value) === Number(field.dataset.stock) ? 'Max available' : '';
        const form = field.form;
        if (form?.id === 'addToCartForm') {
            form.querySelectorAll('[onclick="increaseQuantity()"], [onclick="decreaseQuantity()"]').forEach(button => button.disabled = button.getAttribute('onclick').startsWith('increase') ? Number(field.value) >= Number(field.dataset.stock) || Number(field.dataset.stock) < 1 : Number(field.value) <= 1);
        }
        return !message;
    }
    function restoreForm(form) {
        if (!form) return;
        delete form.dataset.buyerPending; form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-buyer-was-disabled]').forEach(button => {
            if (button.dataset.buyerWasDisabled === undefined) return;
            button.disabled = button.dataset.buyerWasDisabled === '1'; delete button.dataset.buyerWasDisabled;
        });
    }
    async function handleResponse(response, form) {
        const result = await responseMessage(response);
        restoreForm(form);
        if (!result.success) {
            const variation = result.errors?.variant_id?.[0] || result.errors?.variant?.[0];
            if (variation) {
                const status = root.document.getElementById('variantAvailability');
                if (status) status.textContent = variation;
                form?.querySelectorAll('[data-product-option], #productVariantChoice').forEach(control => control.setAttribute('aria-invalid', 'true'));
            }
            const field = variation ? null : form?.querySelector('[data-buyer-quantity]');
            if (field) { inline(field, result.errors?.quantity?.[0] || result.message); if (field.hasAttribute('data-cart-quantity')) field.value = field.dataset.lastValid; }
            show(result.message);
        } else {
            const field = form?.querySelector('[data-buyer-quantity]');
            if (field) { inline(field, ''); field.dataset.lastValid = field.value; }
            show(result.message, 'success');
        }
        return result;
    }
    const api = {quantityMessage,responseMessage,handleResponse,show,networkError:form=>{ restoreForm(form); show('Network error. Please check your connection and try again.'); }};
    root.SukiBuyerFeedback = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    const document = root.document;
    if (!document) return;
    const isAction = form => /\/(cart\/(add|change-variant|increase|decrease|update|remove|clear)|wishlist\/(add|remove|toggle|move-to-cart))(\/|$)/.test(new URL(form.action, root.location.href).pathname);
    document.addEventListener('input', event => { if (event.target.matches('[data-buyer-quantity]')) validate(event.target); });
    document.addEventListener('focusout', event => {
        const field = event.target;
        if (!field.matches('[data-buyer-quantity]')) return;
        if (validate(field)) {
            if (field.hasAttribute('data-cart-quantity') && field.value !== field.dataset.lastValid) field.form.requestSubmit();
        } else if (field.hasAttribute('data-cart-quantity')) field.value = field.dataset.lastValid;
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!isAction(form)) return;
        if (form.dataset.buyerPending) { event.preventDefault(); return; }
        const field = form.querySelector('[data-buyer-quantity]');
        if (field && !validate(field)) { event.preventDefault(); if (field.hasAttribute('data-cart-quantity')) field.value = field.dataset.lastValid; return; }
        form.dataset.buyerPending = '1'; form.setAttribute('aria-busy','true');
        // Wait until native submission has captured the submitter (including Buy Now's formaction).
        root.setTimeout(() => {
            if (!form.dataset.buyerPending) return;
            form.querySelectorAll('button[type="submit"]').forEach(button => { button.dataset.buyerWasDisabled = button.disabled ? '1' : '0'; button.disabled = true; });
        }, 0);
    });
    document.addEventListener('click', event => { const button = event.target.closest('[data-dismiss-feedback]'); if (button) button.parentElement.hidden = true; });
    root.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-buyer-pending]').forEach(restoreForm);
    });
    document.querySelectorAll('[data-feedback-success]').forEach(box => root.setTimeout(() => box.hidden = true, 6000));
})(typeof window === 'undefined' ? globalThis : window);
