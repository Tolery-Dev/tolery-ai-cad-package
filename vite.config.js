import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/chatbot.scss'],
            publicDirectory: 'resources',
            buildDirectory: 'dist',
        }),
    ],
});
