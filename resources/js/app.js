import './bootstrap';

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm-logout]');

    if (form && !window.confirm('Are you sure you want to log out?')) {
        event.preventDefault();
    }
});
