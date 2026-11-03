<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-copy-voucher]').forEach(button => {
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyVoucher);
            const status = button.closest('article').querySelector('[data-copy-status]');
            try { await navigator.clipboard.writeText(input.value); status.textContent = 'Voucher code copied.'; }
            catch { input.focus(); input.select(); status.textContent = 'Select and copy the highlighted code.'; }
        });
    });
    document.querySelectorAll('[data-claim-voucher]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1';
            form.querySelector('button').disabled = true;
        });
        window.addEventListener('pageshow', () => { delete form.dataset.submitting; form.querySelector('button').disabled = false; });
    });
});
</script>
