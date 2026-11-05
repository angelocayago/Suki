<script>
(() => {
    const dialog = document.getElementById('deliveryCompletionDialog');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    const confirm = document.getElementById('deliveryCompletionConfirm');
    let current = null, previousFocus = null, submitting = false;
    function valid(form) {
        // Reuse the upload panel's type/size validation and inline errors.
        const proof = form.elements.namedItem('proof_photo');
        if (proof) proof.dispatchEvent(new Event('change', {bubbles: true}));
        return form.reportValidity();
    }
    function display(id, value) { document.getElementById(id).textContent = value; }
    document.querySelectorAll('[data-delivery-completion]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (submitting || !valid(form)) return;
            current = form;
            previousFocus = event.submitter || document.activeElement;
            const proof = form.elements.namedItem('proof_photo');
            const recipient = form.elements.namedItem('received_by');
            const cod = form.elements.namedItem('cod_collected');
            display('deliveryCompletionOrder', form.dataset.order);
            display('deliveryCompletionBuyer', form.dataset.buyer);
            display('deliveryCompletionRecipient', recipient?.value.trim() || 'Not recorded');
            display('deliveryCompletionAmount', form.dataset.codAmount);
            display('deliveryCompletionCod', form.dataset.codRequired === 'true'
                ? (cod?.checked ? 'Required — Rider confirmed collection' : 'Required — not confirmed')
                : 'Not required');
            display('deliveryCompletionProof', proof?.files.length ? 'Photo selected: ' + proof.files[0].name : 'Not available for this legacy task');
            if (!dialog.open) dialog.showModal();
        });
    });
    document.getElementById('deliveryCompletionCancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (!submitting) { current = null; previousFocus?.focus(); }
    });
    // Native modal dialog contains focus, blocks background interaction and supports Escape.
    confirm.addEventListener('click', () => {
        if (submitting || !current) return;
        if (!valid(current)) { dialog.close(); return; }
        const form = current;
        submitting = true;
        confirm.disabled = true;
        confirm.textContent = 'Submitting…';
        form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = true);
        dialog.close();
        // Submit the original multipart form; retain its File, token and all field values.
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
