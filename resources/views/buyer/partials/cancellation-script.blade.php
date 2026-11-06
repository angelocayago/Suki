<script>
(() => {
    const dialog = document.getElementById('buyerCancellationDialog');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    const form = dialog.querySelector('[data-cancellation-form]');
    const keep = dialog.querySelector('[data-cancellation-close]');
    const confirm = form.querySelector('[type="submit"]');
    const reference = dialog.querySelector('[data-cancellation-reference]');
    const feedback = dialog.querySelector('[data-cancellation-feedback]');
    const triggers = [...document.querySelectorAll('[data-cancel-order]')];
    let opener = null, submitting = false;
    let previousReference = dialog.dataset.errorReference || '';
    function open(trigger) {
        if (dialog.open || submitting) return;
        if (previousReference !== trigger.dataset.cancelOrder) form.querySelectorAll('[name="cancel_reason"]').forEach(input => input.checked = false);
        previousReference = trigger.dataset.cancelOrder;
        opener = trigger;
        form.action = trigger.dataset.cancelAction;
        form.querySelector('[name="cancel_order_reference"]').value = previousReference;
        reference.textContent = previousReference;
        feedback.textContent = '';
        dialog.showModal();
        keep.focus();
    }
    const close = () => { if (!submitting) dialog.close(); };
    triggers.forEach(trigger => trigger.addEventListener('click', () => open(trigger)));
    keep.addEventListener('click', close);
    dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
    });
    dialog.addEventListener('close', () => opener?.focus());
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button:not(:disabled), input:not([type="hidden"]):not(:disabled)')];
        const first = controls[0], last = controls[controls.length - 1];
        if (!first) { event.preventDefault(); return; }
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    form.addEventListener('submit', event => {
        if (submitting || !opener || !form.reportValidity()) { event.preventDefault(); return; }
        submitting = true;
        confirm.disabled = true; keep.disabled = true;
        feedback.textContent = 'Submitting cancellation. Please wait…';
        // Preserve the original form fields, CSRF token and selected reason.
    });
    window.addEventListener('pageshow', event => {
        if (!submitting && !event.persisted) return;
        submitting = false; confirm.disabled = false; keep.disabled = false; feedback.textContent = '';
    });
    const retry = triggers.find(trigger => trigger.dataset.cancelOrder === dialog.dataset.errorReference);
    if (retry) {
        open(retry);
        feedback.textContent = dialog.dataset.validationMessage || 'Please review your cancellation reason and try again.';
    } else document.querySelector('[data-cancel-errors]')?.focus();
})();
</script>
