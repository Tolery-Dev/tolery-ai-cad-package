import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            publicDirectory: 'resources',
            buildDirectory: 'dist',
        }),
    ],
});
