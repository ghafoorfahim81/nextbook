import './bootstrap';
import '../css/app.css';
import '../css/vue-select.css';
import 'vue-sonner/style.css';
import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import vSelect from 'vue-select'; // ✅ Import v-select
import Toaster from '@/Components/ui/toast/Toaster.vue'
import { createI18nInstance } from './lib/i18n'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { applyAppearanceTheme } from './lib/theme'

const appName = import.meta.env.VITE_APP_NAME || 'Nextbook';

// Guard against `DataCloneError: ... could not be cloned` from
// history.pushState/replaceState. Inertia calls these synchronously as part
// of every router.visit() (e.g. saveScrollPositions() runs before a form's
// .post() even sends its request), so if the current page's state ever picks
// up a value the structured-clone algorithm can't serialize, the error is
// thrown inside the click handler and the visit — including a plain form
// submit — never happens. Losing scroll-position/history state is harmless;
// silently failing to submit a form is not, so this degrades instead of
// throwing: retry with a JSON-safe copy of the state (drops whatever wasn't
// serializable), and if even that fails, skip the history write and warn.
;['pushState', 'replaceState'].forEach((method) => {
    const original = window.history[method].bind(window.history)
    window.history[method] = function (state, title, url) {
        try {
            return original(state, title, url)
        } catch (error) {
            if (!(error instanceof DOMException) || error.name !== 'DataCloneError') throw error
            console.warn(`[history.${method}] page state contained a non-serializable value; retrying with a sanitized copy.`, error)
            let safeState = null
            try { safeState = JSON.parse(JSON.stringify(state)) } catch { /* leave null */ }
            if (safeState === null) return
            try {
                return original(safeState, title, url)
            } catch {
                // Sanitized copy still failed — proceed without updating history state.
            }
        }
    }
})

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
        applyAppearanceTheme(initialPage?.props?.user_preferences)

        const applyFromPage = (page) => {
            const nextLocale = page?.props?.locale
            const nextDirection = page?.props?.direction
            if (!nextLocale) return
            i18n.global.locale.value = nextLocale
            applyDocumentLocale(nextLocale, nextDirection)
            applyAppearanceTheme(page?.props?.user_preferences)
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
    progress: false,
});
