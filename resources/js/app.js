import './bootstrap';
import '../css/app.css';
import '../css/vue-select.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import vSelect from 'vue-select'; // ✅ Import v-select
import Toaster from '@/Components/ui/toast/Toaster.vue'
import { createI18nInstance } from './lib/i18n'
import NextDate from '@/Components/next/NextDatePicker.vue'

const appName = import.meta.env.VITE_APP_NAME || 'Nextbook';

function applyDocumentLocale(locale, direction) {
    if (!locale) return
    document.documentElement.setAttribute('lang', locale)
    document.documentElement.setAttribute('dir', direction || (['fa', 'ps'].includes(locale) ? 'rtl' : 'ltr'))
}

createInertiaApp({
    title: (title) => `Nextbook ${title}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const initialPage = props.initialPage
        const initialLocale = initialPage?.props?.locale || document.documentElement.getAttribute('lang') || 'en'
        const initialDirection = initialPage?.props?.direction

        const i18n = createI18nInstance(initialLocale)
        applyDocumentLocale(initialLocale, initialDirection)

        const applyFromPage = (page) => {
            const nextLocale = page?.props?.locale
            const nextDirection = page?.props?.direction
            if (!nextLocale) return
            i18n.global.locale.value = nextLocale
            applyDocumentLocale(nextLocale, nextDirection)
        }

        // "navigate" is mainly for GET visits; language switching is a POST + redirect.
        // "success" fires after any successful Inertia visit (GET/POST/etc) and includes the updated page props.
        router.on('navigate', (event) => applyFromPage(event.detail.page))
        router.on('success', (event) => applyFromPage(event.detail.page))

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .component('Head', Head)
            .component('Link', Link)
            .component('v-select', vSelect) // ✅ Register v-select globally
            .component('NextDate', NextDate)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
