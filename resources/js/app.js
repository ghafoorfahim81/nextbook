import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp ,Head,Link} from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Nextbook';

createInertiaApp({
    title: (title) => `Nextbook ${title}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`,
         import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Head',Head)
            .component('Link',Link) 
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

