@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const forms = [...document.querySelectorAll('[data-inventory-stock]')];
    let pending = false;
    forms.forEach(form => {
        const input = form.querySelector('[name="stock"]');
        const feedback = form.querySelector('[data-stock-feedback]');
        const steps = [...form.querySelectorAll('[data-stock-step]')];
        const save = form.querySelector('[data-stock-save]');
        const editable = !input.disabled;
        const valid = () => /^\d+$/.test(input.value) && Number.isSafeInteger(Number(input.value)) && Number(input.value) <= Number(input.max);
        const sync = () => {
            steps.forEach(button => { button.disabled = !editable || pending || !valid() || (button.dataset.stockStep === '-1' ? Number(input.value) <= 0 : Number(input.value) >= Number(input.max)); });
        };
        steps.forEach(button => button.addEventListener('click', () => {
            if (!editable || pending || !valid() || button.disabled) return;
            input.value = String(Number(input.value) + Number(button.dataset.stockStep));
            feedback.textContent = 'Unsaved quantity. Use Save to apply.';
            sync();
        }));
        input.addEventListener('input', () => { feedback.textContent = 'Unsaved quantity. Use Save to apply.'; sync(); });
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); if (!pending) save.focus(); }
        });
        form.addEventListener('submit', event => {
            if (pending || !editable || !valid() || !form.reportValidity()) {
                event.preventDefault();
                if (!pending) feedback.textContent = 'Enter a whole number between 0 and ' + input.max + '.';
                return;
            }
            pending = true;
            feedback.textContent = 'Saving...';
            form.setAttribute('aria-busy','true');
            // Keep native form inputs enabled so stock and the snapshot are submitted.
            forms.forEach(other => other.querySelectorAll('button').forEach(button => { button.disabled = true; }));
        });
        window.addEventListener('pageshow', () => {
            pending = false;
            form.removeAttribute('aria-busy');
            save.disabled = !editable;
            feedback.textContent = '';
            sync();
        });
        sync();
    });
    document.querySelectorAll('[data-inventory-image]').forEach(image => {
        const fallback = () => { image.hidden = true; const placeholder = image.parentElement.querySelector('[data-inventory-placeholder]'); if (placeholder) placeholder.hidden = false; };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    });
});
</script>
@endpush
