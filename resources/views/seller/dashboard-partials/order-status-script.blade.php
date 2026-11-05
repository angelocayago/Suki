<script>
(() => {
    const widget = document.querySelector('[data-order-status]');
    if (!widget || widget.dataset.initialized) return;
    widget.dataset.initialized = 'true';
    const segments = [...widget.querySelectorAll('[data-status-segment]')];
    const choices = [...widget.querySelectorAll('[data-status-choice]')];
    const detail = widget.querySelector('[data-status-detail]');
    const initial = detail.textContent;
    let selected = null;
    function show(target) {
        const key = target.dataset.statusChoice || target.dataset.statusSegment;
        selected = key;
        segments.forEach(segment => {
            segment.classList.toggle('is-highlighted', segment.dataset.statusSegment === key);
            segment.classList.toggle('is-dimmed', segment.dataset.statusSegment !== key);
        });
        choices.forEach(choice => choice.setAttribute('aria-pressed', String(choice.dataset.statusChoice === key)));
        detail.style.setProperty('--status-color', target.style.getPropertyValue('--status-color') || getComputedStyle(target).getPropertyValue('--status-color'));
        detail.classList.add('has-selection');
        detail.textContent = `${target.dataset.statusLabel}: ${Number(target.dataset.statusCount).toLocaleString('en-PH')} orders · ${target.dataset.statusPercent}% of all Seller Orders`;
    }
    function reset() {
        selected = null;
        segments.forEach(segment => segment.classList.remove('is-highlighted', 'is-dimmed'));
        choices.forEach(choice => choice.setAttribute('aria-pressed', 'false'));
        detail.classList.remove('has-selection');
        detail.textContent = initial;
    }
    [...segments, ...choices].forEach(target => {
        target.addEventListener('mouseenter', () => show(target));
        target.addEventListener('focus', () => show(target));
        target.addEventListener('click', () => show(target));
    });
    widget.addEventListener('keydown', event => {
        if (event.key === 'Escape' && selected !== null) { reset(); event.stopPropagation(); }
    });
})();
</script>
