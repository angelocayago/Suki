<script>
(() => {
    const dialog = document.getElementById('pickupAssignmentDialog');
    if (!dialog || dialog.dataset.initialized) return;
    dialog.dataset.initialized = 'true';
    const confirm = document.getElementById('pickupAssignmentConfirm');
    let current = null, previousFocus = null, submitting = false;
    document.querySelectorAll('[data-pickup-assignment]').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (submitting || !form.reportValidity()) return;
            const rider = form.elements.namedItem('pickup_rider_id');
            if (!rider.value) return;
            current = form;
            previousFocus = event.submitter || document.activeElement;
            document.getElementById('pickupAssignmentOrder').textContent = form.dataset.order;
            document.getElementById('pickupAssignmentRider').textContent = rider.selectedOptions[0].textContent;
            document.getElementById('pickupAssignmentLocation').textContent = form.dataset.pickupLocation || 'Pickup address unavailable';
            if (!dialog.open) dialog.showModal();
        });
    });
    document.getElementById('pickupAssignmentCancel').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => {
        if (!submitting) { current = null; previousFocus?.focus(); }
    });
    // Native dialog provides a modal focus boundary and Escape-to-cancel behavior.
    confirm.addEventListener('click', () => {
        if (submitting || !current || !current.reportValidity()) return;
        const form = current;
        submitting = true;
        confirm.disabled = true;
        confirm.textContent = 'Submitting…';
        form.querySelectorAll('button[type="submit"]').forEach(button => button.disabled = true);
        dialog.close();
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
