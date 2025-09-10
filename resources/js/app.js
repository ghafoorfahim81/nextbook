import './bootstrap';
import '../css/app.css';
import '../css/vue-select.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import vSelect from 'vue-select'; // ✅ Import v-select
import Toaster from '@/Components/ui/toast/Toaster.vue'
import { createI18nInstance } from './lib/i18n'

const appName = import.meta.env.VITE_APP_NAME || 'Nextbook';

createInertiaApp({
    title: (title) => `Nextbook ${title}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const stored = (typeof localStorage !== 'undefined' && localStorage.getItem('locale')) || null
        const htmlLang = document.documentElement.getAttribute('lang') || 'en'
        const initial = (stored || htmlLang || 'en').replace(/^pa$/, 'ps')
        const i18n = createI18nInstance(initial)
        document.documentElement.setAttribute('lang', initial)
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .component('Head', Head)
            .component('Link', Link)
            .component('v-select', vSelect) // ✅ Register v-select globally
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
