import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/portal-auth.css', 'resources/css/simak-theme.css', 'resources/js/public-landing.jsx', 'resources/js/api-docs.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
