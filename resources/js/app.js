import './buyer-action-feedback';
import './buyer-cart-selection';
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const buttons = [...document.querySelectorAll('[data-suki-theme-toggle]')];
    let theme = root.dataset.theme || document.body.dataset.theme || 'light';

    const applyTheme = (nextTheme) => {
        theme = nextTheme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = theme;
        document.body.dataset.theme = theme;
        const dark = theme === 'dark';
        buttons.forEach((button) => {
            const label = dark ? 'Switch to light mode' : 'Switch to dark mode';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', String(dark));
            button.innerHTML = `<i data-lucide="${dark ? 'sun' : 'moon'}" aria-hidden="true"></i>`;
        });
        window.lucide?.createIcons();
    };

    try {
        const saved = localStorage.getItem('suki-landing-theme');
        if (saved === 'dark' || saved === 'light') theme = saved;
    } catch (_) {}

    applyTheme(theme);
    buttons.forEach((button) => button.addEventListener('click', () => {
        const nextTheme = theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('suki-landing-theme', nextTheme); } catch (_) {}
        applyTheme(nextTheme);
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    const setupSidebar = ({ sidebar, overlay, open, close, bodyClass, main, desktopCollapse }) => {
        const isMobile = window.matchMedia('(max-width: 1023px)');
        if (!sidebar || !overlay || !open) return;
        if (sidebar.dataset.sukiSidebarInitialized) return;
        sidebar.dataset.sukiSidebarInitialized = 'true';
        let mobileOpen = false;
        const focusables = () => [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter((item) => item.offsetParent !== null);
        const update = () => {
            if (isMobile.matches) {
                sidebar.classList.toggle('-translate-x-full', !mobileOpen);
                sidebar.inert = !mobileOpen;
                if (main) main.inert = mobileOpen;
                overlay.hidden = !mobileOpen;
                overlay.classList.toggle('hidden', !mobileOpen);
                open.setAttribute('aria-expanded', String(mobileOpen));
                document.body.classList.toggle('overflow-hidden', mobileOpen);
            } else {
                mobileOpen = false;
                sidebar.classList.add('-translate-x-full');
                sidebar.inert = false;
                if (main) main.inert = false;
                overlay.hidden = true;
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                open.setAttribute('aria-expanded', 'false');
            }
        };
        const closeMobile = (restore = true) => {
            mobileOpen = false;
            update();
            if (restore) open.focus();
        };
        open.addEventListener('click', () => {
            if (!isMobile.matches) return;
            mobileOpen = true;
            update();
            focusables()[0]?.focus();
        });
        close?.addEventListener('click', () => {
            if (isMobile.matches) closeMobile();
            else {
                const collapsed = document.body.classList.toggle(bodyClass);
                close.setAttribute('aria-expanded', String(!collapsed));
                close.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                close.innerHTML = `<i data-lucide="${collapsed ? 'panel-left-open' : 'panel-left-close'}" aria-hidden="true"></i>`;
                window.lucide?.createIcons();
            }
        });
        desktopCollapse?.addEventListener('click', () => {
            const collapsed = document.body.classList.toggle(bodyClass);
            desktopCollapse.setAttribute('aria-expanded', String(!collapsed));
            desktopCollapse.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            desktopCollapse.innerHTML = `<i data-lucide="${collapsed ? 'panel-left-open' : 'panel-left-close'}" aria-hidden="true"></i>`;
            window.lucide?.createIcons();
        });
        overlay.addEventListener('click', () => closeMobile());
        document.addEventListener('keydown', (event) => {
            if (!mobileOpen || !isMobile.matches) return;
            if (document.querySelector('dialog[open]')) return;
            if (event.key === 'Escape') { event.preventDefault(); closeMobile(); }
            if (event.key === 'Tab') {
                const items = focusables();
                if (!items.length) return;
                if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items.at(-1).focus(); }
                else if (!event.shiftKey && document.activeElement === items.at(-1)) { event.preventDefault(); items[0].focus(); }
            }
        });
        window.addEventListener('resize', update);
        sidebar.addEventListener('click', (event) => {
            if (isMobile.matches && event.target.closest('a[href]')) closeMobile(false);
        });
        update();
    };

    setupSidebar({
        sidebar: document.getElementById('logisticsSidebar'),
        overlay: document.getElementById('logisticsSidebarOverlay'),
        open: document.getElementById('openLogisticsSidebar'),
        close: document.getElementById('closeLogisticsSidebar'),
        bodyClass: 'logistics-sidebar-collapsed',
        main: document.querySelector('body > div.min-h-screen'),
    });
    setupSidebar({
        sidebar: document.getElementById('riderSidebar'),
        overlay: document.getElementById('riderSidebarOverlay'),
        open: document.getElementById('riderSidebarOpen'),
        desktopCollapse: document.getElementById('riderSidebarCollapse'),
        bodyClass: 'rider-sidebar-collapsed',
        main: document.querySelector('body > div.min-h-screen'),
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-suki-logout-dialog]');
    if (!dialog) return;

    const cancel = dialog.querySelector('[data-suki-logout-cancel]');
    const confirm = dialog.querySelector('[data-suki-logout-confirm]');
    let pendingForm = null;
    let trigger = null;
    let submitting = false;

    const dismiss = () => {
        if (submitting || !dialog.open) return;
        dialog.close();
        pendingForm = null;
        trigger?.focus();
    };

    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        const form = event.target instanceof HTMLFormElement
            ? event.target
            : event.target.closest?.('form[data-confirm-logout], form[data-seller-logout]');

        if (!form?.matches('form[data-confirm-logout], form[data-seller-logout]')) return;
        event.preventDefault();
        if (dialog.open || submitting) return;

        pendingForm = form;
        trigger = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        dialog.showModal();
        cancel.focus();
    });

    cancel.addEventListener('click', dismiss);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        dismiss();
    });
    dialog.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const controls = [cancel, confirm].filter((control) => !control.disabled);
        if (!controls.length) return;
        const first = controls[0];
        const last = controls.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    confirm.addEventListener('click', () => {
        if (!pendingForm || submitting) return;
        submitting = true;
        cancel.disabled = true;
        confirm.disabled = true;
        HTMLFormElement.prototype.submit.call(pendingForm);
    });
});
