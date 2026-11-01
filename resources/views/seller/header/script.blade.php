<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = Array.from(document.querySelectorAll('[data-seller-dropdown]')).map(root => ({
        root, toggle:root.querySelector('[data-seller-dropdown-toggle]'), panel:root.querySelector('.seller-header-panel'),
    }));
    function close(item, focus = false) {
        item.panel.hidden = true; item.toggle.setAttribute('aria-expanded','false');
        if (focus) item.toggle.focus();
    }
    dropdowns.forEach(item => {
        item.toggle.addEventListener('click', () => {
            const open = item.panel.hidden;
            dropdowns.forEach(other => close(other));
            item.panel.hidden = !open; item.toggle.setAttribute('aria-expanded',String(open));
            if (open && item.panel.id === 'sellerNotificationPanel') refreshPreview();
        });
        item.root.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !item.panel.hidden) { event.preventDefault(); close(item,true); }
            if (event.key === 'ArrowDown' && document.activeElement === item.toggle) {
                event.preventDefault(); dropdowns.forEach(other => close(other));
                item.panel.hidden = false; item.toggle.setAttribute('aria-expanded','true');
                item.panel.querySelector('a[href],button')?.focus();
                if (item.panel.id === 'sellerNotificationPanel') refreshPreview();
            }
        });
        item.root.addEventListener('focusout', event => {
            if (event.relatedTarget && !item.root.contains(event.relatedTarget)) close(item);
        });
    });
    document.addEventListener('click', event => dropdowns.forEach(item => {
        if (!item.root.contains(event.target)) close(item);
    }));
    document.querySelectorAll('[data-seller-avatar-photo]').forEach(image => {
        const fallback = image.parentElement.querySelector('[data-seller-avatar-fallback]');
        const failed = () => { image.hidden = true; fallback.hidden = false; };
        image.addEventListener('error',failed);
        if (image.complete && image.naturalWidth === 0) failed();
    });
    const panel = document.getElementById('sellerNotificationPanel');
    const preview = document.getElementById('sellerNotificationPreview');
    const status = document.getElementById('sellerNotificationLoadStatus');
    let pending = false;
    async function refreshPreview() {
        if (!panel || pending) return;
        pending = true; panel.setAttribute('aria-busy','true');
        status.textContent = 'Loading notifications…';
        try {
            const response = await fetch(panel.dataset.previewUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) throw new Error('Inbox unavailable');
            const data = await response.json();
            const count = Number(data.unread_count);
            if (!Number.isSafeInteger(count) || count < 0 || !Array.isArray(data.notifications)) throw new Error('Invalid inbox response');
            document.querySelectorAll('[data-seller-unread-badge]').forEach(badge => {
                badge.hidden = count === 0; badge.textContent = count ? (count > 99 ? '99+' : String(count)) : '';
            });
            document.querySelectorAll('[data-seller-unread-total]').forEach(total => total.textContent = String(count));
            document.getElementById('sellerNotificationBell').setAttribute('aria-label',count ? `Notifications, ${count} unread` : 'Notifications');
            // Keep server-rendered, focused entries in place during keyboard use.
            if (!preview.contains(document.activeElement)) {
                preview.replaceChildren();
                if (!data.notifications.length) {
                    const empty = document.createElement('p'); empty.className = 'seller-header-empty'; empty.textContent = 'No notifications yet.'; preview.appendChild(empty);
                }
                data.notifications.forEach(notice => {
                    const action = new URL(notice.open_url,window.location.href);
                    if (action.origin !== window.location.origin) return;
                    const form = document.createElement('form'); form.method = 'POST'; form.action = action.href;
                    const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = panel.dataset.csrf;
                    const button = document.createElement('button'); button.type = 'submit'; button.className = 'seller-notification-item'+(notice.unread?' is-unread':'');
                    const title = document.createElement('strong'); title.textContent = notice.title;
                    const message = document.createElement('span'); message.className = 'seller-notification-message'; message.textContent = notice.message;
                    button.append(title,message);
                    if (notice.date) { const time = document.createElement('time'); time.textContent = notice.date; time.dateTime = notice.iso_date; button.appendChild(time); }
                    form.append(csrf,button); preview.appendChild(form);
                });
            }
            status.textContent = '';
        } catch(error) { status.textContent = 'Could not refresh notifications. View All Notifications to try again.'; }
        finally { pending = false; panel.removeAttribute('aria-busy'); }
    }
});
</script>
