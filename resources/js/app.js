import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

document.addEventListener('submit', (event) => {
    // A dedicated dialog may already own this submission (Seller logout).
    if (event.defaultPrevented) return;
    const form = event.target.closest('form[data-confirm-logout]');

    if (form && !window.confirm('Are you sure you want to log out?')) {
        event.preventDefault();
    }
});
