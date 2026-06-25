import { onMounted, onBeforeUnmount, unref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

/**
 * Guards a Create/Edit form against losing unsaved changes.
 *
 * Protects against:
 *  - Closing the browser tab / window (native `beforeunload` prompt)
 *  - Inertia client-side navigation (links, buttons, programmatic visits)
 *
 * The guard stays silent while the form is being submitted (`form.processing`),
 * so the post-submit redirect is never blocked.
 *
 * Scope note: do NOT use this on Administration module forms.
 *
 * @param {import('@inertiajs/vue3').InertiaForm|object} form - An Inertia useForm() instance (exposes `isDirty` and `processing`).
 * @param {object} [options]
 * @param {boolean|import('vue').Ref<boolean>} [options.enabled=true] - Toggle the guard on/off reactively.
 * @param {string} [options.message] - Confirmation message shown when navigating away.
 */
export function useFormGuard(form, options = {}) {
    const enabled = options.enabled ?? true

    let t = null
    try {
        ({ t } = useI18n())
    } catch (e) {
        t = null
    }

    const message = options.message
        || (t ? t('general.unsaved_changes_warning') : 'You have unsaved changes. Are you sure you want to leave?')

    const isGuarding = () =>
        unref(enabled) && form?.isDirty && !form?.processing

    const handleBeforeUnload = (event) => {
        if (!isGuarding()) return
        event.preventDefault()
        // Required for Chrome / legacy browsers to trigger the native prompt.
        event.returnValue = message
        return message
    }

    let removeInertiaHook = null

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload)
        removeInertiaHook = router.on('before', (event) => {
            if (!isGuarding()) return
            // Only guard real navigations away (GET). Form submissions (post/put/
            // patch/delete) — i.e. Create / Create & New / Save — must never prompt.
            const method = String(event.detail?.visit?.method ?? 'get').toLowerCase()
            if (method !== 'get') return
            if (!window.confirm(message)) {
                event.preventDefault()
            }
        })
    })

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload)
        if (typeof removeInertiaHook === 'function') removeInertiaHook()
    })
}
