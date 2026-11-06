<script>
(() => {
    const bell = document.querySelector('[data-buyer-notifications]');
    if (!bell || bell.dataset.initialized) return;
    bell.dataset.initialized = 'true';
    const summary = bell.querySelector('summary');
    const count = bell.querySelector('[data-buyer-notification-count]');
    const list = bell.querySelector('[data-buyer-notification-items]');
    const status = bell.querySelector('[data-buyer-notification-status]');
    let pending = false;
    const localUrl = value => {
        if (typeof value !== 'string') return null;
        const url = new URL(value, window.location.href);
        return url.origin === window.location.origin ? url.href : null;
    };
    async function refresh() {
        if (pending) return;
        const endpoint = localUrl(bell.dataset.previewUrl);
        if (!endpoint) return;
        pending = true;
        status.textContent = 'Refreshing notifications…';
        try {
            const response = await fetch(endpoint, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store'});
            if (!response.ok) throw new Error('Notification refresh failed');
            const data = await response.json();
            if (!Number.isInteger(data.unread_count) || data.unread_count < 0 || !Array.isArray(data.notifications)) throw new Error('Invalid notification response');
            count.hidden = data.unread_count === 0;
            count.textContent = data.unread_count > 99 ? '99+' : String(data.unread_count);
            summary.setAttribute('aria-label', `Notifications, ${data.unread_count} unread`);
            const fragment = document.createDocumentFragment();
            data.notifications.slice(0, 5).forEach(notice => {
                const action = localUrl(notice.open_url);
                if (!action) return;
                const form = document.createElement('form'); form.method = 'POST'; form.action = action;
                const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = bell.dataset.csrf;
                const button = document.createElement('button'); button.type = 'submit'; button.className = 'bn-item'+(notice.unread ? ' bn-unread' : '');
                const title = document.createElement('strong'); title.textContent = notice.title;
                const message = document.createElement('span'); message.textContent = notice.message;
                button.appendChild(title);
                if (notice.reference) { const reference = document.createElement('span'); reference.className = 'bn-reference'; reference.textContent = notice.reference; button.appendChild(reference); }
                button.appendChild(message);
                const time = document.createElement('time'); time.dateTime = notice.iso_date || ''; time.textContent = `${notice.date || ''} PHT · ${notice.unread ? 'Unread' : 'Read'}`;
                button.appendChild(time); form.appendChild(csrf); form.appendChild(button); fragment.appendChild(form);
            });
            if (!fragment.childNodes.length) { const empty = document.createElement('p'); empty.className = 'bn-empty'; empty.textContent = 'No notifications yet. Purchase updates will appear here.'; fragment.appendChild(empty); }
            list.replaceChildren(fragment);
            status.textContent = '';
        } catch (_) {
            status.textContent = 'Could not refresh. View all notifications to try again.';
        } finally { pending = false; }
    }
    bell.addEventListener('toggle', () => { if (bell.open) refresh(); });
    bell.addEventListener('keydown', event => { if (event.key === 'Escape') { bell.open = false; summary.focus(); } });
    document.addEventListener('click', event => { if (!bell.contains(event.target)) bell.open = false; });
    window.addEventListener('focus', refresh);
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
})();
</script>
