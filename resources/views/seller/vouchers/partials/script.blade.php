@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-voucher-archive]').forEach(form => {
        let submitting = false;
        form.addEventListener('submit', event => {
            if (submitting) { event.preventDefault(); return; }
            submitting = true; form.querySelector('button').disabled = true;
        });
        window.addEventListener('pageshow', () => { submitting = false; form.querySelector('button').disabled = false; });
    });
    const form = document.getElementById('shopVoucherForm');
    if (!form) return;
    const type = document.getElementById('voucherDiscountType');
    const value = document.getElementById('voucherDiscountValue');
    const label = document.getElementById('voucherDiscountLabel');
    function updateType() {
        const percentage = type.value === 'PERCENTAGE';
        value.max = percentage ? '100' : '21474836.47';
        label.textContent = percentage ? 'Discount Value (%)' : 'Discount Value (PHP)';
    }
    type.addEventListener('change', updateType); updateType();
    const starts = document.getElementById('voucherStartsAt');
    const expires = document.getElementById('voucherExpiresAt');
    function validateDates() {
        const start = starts.value.length === 16 ? starts.value + ':00' : starts.value;
        const end = expires.value.length === 16 ? expires.value + ':00' : expires.value;
        expires.setCustomValidity(start && end && end <= start ? 'Expiration must be later than the start date.' : '');
    }
    starts.addEventListener('input', validateDates); expires.addEventListener('input', validateDates);
    const code = document.getElementById('voucherCode');
    const generate = document.getElementById('generateVoucherCode');
    const status = document.getElementById('voucherCodeStatus');
    const save = document.getElementById('saveShopVoucher');
    const saveLabel = save.textContent;
    let generating = false, submitting = false, revision = 0;
    code.addEventListener('input', () => revision++);
    generate.addEventListener('click', async () => {
        if (generating || submitting) return;
        const version = revision;
        generating = true; generate.disabled = true; generate.textContent = 'Generating…';
        status.textContent = '';
        try {
            const response = await fetch(generate.dataset.url, {headers:{Accept:'application/json'}, cache:'no-store'});
            const data = await response.json();
            if (!response.ok || typeof data.code !== 'string') throw new Error('Unable to generate code');
            if (version === revision) {
                code.value = data.code;
                status.textContent = 'Code generated.';
            } else status.textContent = 'Your typed code was kept. Click Generate Code again to replace it.';
        } catch (error) {
            status.textContent = 'A code could not be generated. Try again or leave the code empty to generate it when saving.';
        } finally {
            generating = false; generate.disabled = false; generate.textContent = 'Generate Code';
        }
    });
    form.addEventListener('submit', event => {
        if (submitting || generating) {
            event.preventDefault();
            if (generating) status.textContent = 'Please wait for code generation to finish.';
            return;
        }
        validateDates();
        if (!form.reportValidity()) { event.preventDefault(); return; }
        submitting = true; form.setAttribute('aria-busy', 'true');
        save.disabled = true; generate.disabled = true; save.textContent = 'Saving…';
    });
    window.addEventListener('pageshow', () => {
        submitting = false; save.disabled = false; generate.disabled = generating;
        save.textContent = saveLabel; form.removeAttribute('aria-busy');
    });
});
</script>
@endpush
