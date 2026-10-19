import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // Pages are loaded through import.meta.glob (dynamic imports), so Vite's
    // startup scanner never sees the deps used inside Pages/** or Components/**.
    // Without this list it discovers them one by one on first load, re-optimizing
    // and full-reloading the browser each time — a multi-minute white screen.
    optimizeDeps: {
        include: [
            '@inertiajs/vue3',
            '@tanstack/vue-table',
            '@vueuse/core',
            '@iconify/vue',
            'axios',
            'class-variance-authority',
            'clsx',
            'fuse.js',
            'jsbarcode',
            'lodash',
            'lucide-vue-next',
            'radix-vue',
            'reka-ui',
            'tailwind-merge',
            'vue',
            'vue-i18n',
            'vue-select',
            'vue-sonner',
            'vue-persian-datetime-picker',
            'vue3-persian-datetime-picker',
        ],
    },
    server: {
        // Transform the entry + layout eagerly instead of waiting for the browser.
        warmup: {
            clientFiles: [
                './resources/js/app.js',
                './resources/js/Layouts/Layout.vue',
                './resources/js/Components/**/*.vue',
            ],
        },
    },
});
