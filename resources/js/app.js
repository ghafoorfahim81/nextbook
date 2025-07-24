import './bootstrap';
import '../css/app.css';
import '../css/vue-select.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import vSelect from 'vue-select'; // ✅ Import v-select
import Toaster from '@/components/ui/toast/Toaster.vue'

const appName = import.meta.env.VITE_APP_NAME || 'Nextbook';

createInertiaApp({
    title: (title) => `Nextbook ${title}`,
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
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
