@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-storefront]');
    if (!root) return;
    root.querySelectorAll('[data-sf-image]').forEach(image => {
        const fallback = () => { image.hidden = true; };
        image.addEventListener('error', fallback);
        if (image.complete && !image.naturalWidth) fallback();
    });
    const share = root.querySelector('[data-sf-share]');
    const feedback = root.querySelector('[data-sf-share-status]');
    if (share) {
        share.hidden = false;
        share.addEventListener('click', async () => {
            if (share.disabled) return;
            share.disabled = true;
            feedback.textContent = '';
            try {
                if (typeof navigator.share === 'function') {
                    await navigator.share({title:share.dataset.title, url:share.dataset.url});
                    feedback.textContent = 'Store shared.';
                } else {
                    if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
                    await navigator.clipboard.writeText(share.dataset.url);
                    feedback.textContent = 'Store link copied.';
                }
            } catch (error) {
                if (error.name !== 'AbortError') feedback.textContent = 'Unable to share. Copy the store URL from your address bar.';
            } finally { share.disabled = false; }
        });
    }
    const links = root.querySelectorAll('.sf-nav a');
    const syncNavigation = () => {
        const hash = ['#products', '#about', '#reviews'].includes(location.hash) ? location.hash : '#products';
        links.forEach(link => { if (link.getAttribute('href') === hash) link.setAttribute('aria-current','location'); else link.removeAttribute('aria-current'); });
    };
    window.addEventListener('hashchange', syncNavigation);
    syncNavigation();
});
</script>
@endpush
