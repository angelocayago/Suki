import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/js/app.js',
                'resources/css/landing.css',
                'resources/js/landing.js',
                'resources/css/registration-wizard.css',
                'resources/js/registration-wizard.js',
                'resources/js/shipment-scanner.js',
                'resources/js/sorting-zone-address.js',
            ],
            refresh: true,
        }),

        tailwindcss(),
    ],
});
