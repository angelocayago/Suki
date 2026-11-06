<script>
(() => {
    const dialog = document.getElementById('buyerReceiptDialog');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    const cancel = dialog.querySelector('[data-receipt-cancel]');
    const confirm = dialog.querySelector('[data-receipt-confirm]');
    const feedback = dialog.querySelector('[data-receipt-feedback]');
    const forms = [...document.querySelectorAll('[data-receipt-form]')];
    let pendingForm = null, opener = null, submitting = false;
    const dismiss = () => { if (!submitting && dialog.open) dialog.close(); };
    forms.forEach(form => {
        const trigger = form.querySelector('[type="submit"]');
        trigger.disabled = false;
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (dialog.open || submitting) return;
            pendingForm = form;
            opener = event.submitter || trigger;
            feedback.textContent = '';
            dialog.showModal();
            cancel.focus();
        });
    });
    cancel.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
    dialog.addEventListener('close', () => { pendingForm = null; opener?.focus(); });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dismiss();
    });
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [cancel, confirm].filter(button => !button.disabled);
        if (!controls.length) { event.preventDefault(); return; }
        if (event.shiftKey && document.activeElement === controls[0]) { event.preventDefault(); controls.at(-1).focus(); }
        else if (!event.shiftKey && document.activeElement === controls.at(-1)) { event.preventDefault(); controls[0].focus(); }
    });
    confirm.addEventListener('click', () => {
        if (!pendingForm || !dialog.open || submitting) return;
        submitting = true;
        cancel.disabled = true;
        confirm.disabled = true;
        forms.forEach(form => form.querySelector('[type="submit"]').disabled = true);
        feedback.textContent = 'Confirming receipt. Please wait…';
        // Submit the original authorized form with its unchanged CSRF token and status.
        HTMLFormElement.prototype.submit.call(pendingForm);
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        cancel.disabled = false;
        confirm.disabled = false;
        forms.forEach(form => form.querySelector('[type="submit"]').disabled = false);
        feedback.textContent = '';
        dismiss();
    });
})();
</script>
