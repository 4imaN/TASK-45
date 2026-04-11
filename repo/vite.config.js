import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    ],
    resolve: { alias: { '@': '/resources/js/app' } },
    test: {
        globals: true,
        environment: 'jsdom',
        coverage: { provider: 'v8', reporter: ['text', 'json', 'html'], include: ['resources/js/app/**/*.{js,vue}'] },
    },
});
