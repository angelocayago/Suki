@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('reportsFilterToggle');
    const form = document.getElementById('reportsFilterPanel');
    if (!toggle || !form) return;
    function openFilters(open) {
        form.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
    }
    toggle.addEventListener('click', () => openFilters(form.hidden));
    form.addEventListener('keydown', event => {
        if (event.key === 'Escape') { openFilters(false); toggle.focus(); }
    });
    form.addEventListener('invalid', () => openFilters(true), true);
    const dates = document.getElementById('reportsCustomDates');
    const from = document.getElementById('reportsFrom'), to = document.getElementById('reportsTo');
    function updateDates() {
        const custom = form.querySelector('[name=date]:checked').value === 'custom';
        dates.hidden = !custom;
        [from,to].forEach(input => { input.disabled = !custom; input.required = custom; });
        to.min = from.value;
    }
    form.querySelectorAll('[name=date]').forEach(radio => radio.addEventListener('change', updateDates));
    from.addEventListener('change', () => to.min = from.value);
    updateDates();
    const apply = document.getElementById('reportsApply');
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true; form.setAttribute('aria-busy', 'true');
        apply.disabled = true; apply.textContent = 'Applying…';
        const any = form.querySelector('[name=date]:checked');
        if (any.value === 'any') { any.disabled = true; setTimeout(() => any.disabled = false, 0); }
    });
    window.addEventListener('pageshow', () => {
        submitting = false; apply.disabled = false; apply.textContent = 'Apply Filters'; form.removeAttribute('aria-busy'); updateDates();
    });
    const tooltip = document.getElementById('reportsChartTooltip');
    document.querySelectorAll('[data-report-point]').forEach(point => {
        ['mouseenter','focus','click'].forEach(event => point.addEventListener(event, () => {
            if (tooltip) tooltip.textContent = point.dataset.tooltip;
        }));
    });
    // CSV uses the browser's real download behavior; no simulated progress.
});
</script>
@endpush
