<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('walletPayoutForm');
    const input = document.getElementById('walletPayoutAmount');
    const dialog = document.getElementById('walletPayoutDialog');
    const cancel = document.getElementById('walletPayoutCancel');
    const confirm = document.getElementById('walletPayoutConfirm');
    const submit = document.getElementById('walletPayoutSubmit');
    let pendingValue = null, submitting = false;

    function validateAmount() {
        input.setCustomValidity('');
        if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(input.value)) {
            input.setCustomValidity('Enter a positive amount with up to two decimal places.');
            return null;
        }
        const [whole, fraction = ''] = input.value.split('.');
        const minor = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'));
        if (minor < 1n) input.setCustomValidity('Enter a positive payout amount.');
        else if (minor > BigInt(form.dataset.availableMinor)) input.setCustomValidity('Amount exceeds available balance.');
        return minor;
    }
    input.addEventListener('input', validateAmount);
    form.addEventListener('submit', event => {
        event.preventDefault();
        if (submitting || dialog.open) return;
        const minor = validateAmount();
        if (!form.reportValidity() || minor === null) return;
        // Format integer centavos without floating-point currency arithmetic.
        const whole = (minor / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('walletConfirmAmount').textContent = '₱' + whole + '.' + (minor % 100n).toString().padStart(2, '0');
        pendingValue = input.value;
        dialog.showModal(); cancel.focus();
    });
    function dismiss() {
        if (submitting) return;
        dialog.close(); pendingValue = null; submit.focus();
    }
    cancel.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(); });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dismiss();
    });
    dialog.addEventListener('keydown', event => {
        event.stopPropagation();
        if (event.key === 'Tab') {
            if (event.shiftKey && document.activeElement === cancel) { event.preventDefault(); confirm.focus(); }
            else if (!event.shiftKey && document.activeElement === confirm) { event.preventDefault(); cancel.focus(); }
        }
    });
    confirm.addEventListener('click', () => {
        if (submitting || pendingValue === null) return;
        if (input.value !== pendingValue) { dismiss(); return; }
        validateAmount();
        if (!form.reportValidity()) { dismiss(); return; }
        submitting = true; confirm.disabled = true; cancel.disabled = true; submit.disabled = true;
        HTMLFormElement.prototype.submit.call(form);
    });
});
</script>
