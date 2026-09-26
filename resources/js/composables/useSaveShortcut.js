import { onBeforeUnmount, onMounted, ref, unref } from 'vue'

/**
 * Ctrl/Cmd + S saves the form on full-page Create / Edit screens.
 *
 * Scope note: only for modules that create/edit on their own page. Modal-based
 * forms (CreateEditModal.vue) are not covered — the shortcut belongs to the page.
 *
 * The keypress is routed through the form's submit button rather than straight to
 * `form.submit()`, so it behaves exactly like clicking Save: the per-module
 * "confirm before save" dialog in <SubmitButtons> still runs, and a disabled
 * button (already submitting, nothing to save) still blocks the save.
 *
 * Usage:
 *   const saveFormRef = useSaveShortcut({ form })
 *   <form ref="saveFormRef" @submit.prevent="handleSubmitAction(false)">
 *
 * @param {object} [options]
 * @param {import('@inertiajs/vue3').InertiaForm|object} [options.form] - useForm() instance; its `processing` flag suppresses repeat saves.
 * @param {boolean|import('vue').Ref<boolean>} [options.enabled=true] - Toggle the shortcut on/off reactively.
 * @param {Function} [options.onSave] - Custom save callback, used instead of the form's submit button.
 * @returns {import('vue').Ref} Template ref to bind on the <form> element.
 */
export function useSaveShortcut(options = {}) {
    const { form = null, enabled = true, onSave = null } = options

    const saveFormRef = ref(null)

    // A dialog on top owns the keyboard — saving the page behind it would surprise.
    const isOverlayOpen = () => Boolean(
        document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"], dialog[open]')
    )

    const resolveFormEl = () => {
        const target = unref(saveFormRef)
        if (!target) return null
        const el = target instanceof HTMLElement ? target : target.$el
        if (!(el instanceof HTMLElement)) return null
        return el instanceof HTMLFormElement ? el : (el.closest('form') ?? el.querySelector('form'))
    }

    const submitForm = () => {
        const formEl = resolveFormEl()
        if (!formEl) return

        const submitButton = formEl.querySelector('button[type="submit"]')
        if (submitButton) {
            // Click, don't submit: keeps the save-confirmation dialog in the loop.
            if (!submitButton.disabled) submitButton.click()
            return
        }

        formEl.requestSubmit()
    }

    // Match the physical S key, not the character it produces: on a Persian / Dari
    // (or any non-Latin) layout `event.key` is 'س', never 's'.
    const isSaveKey = (event) => event.code === 'KeyS' || String(event.key).toLowerCase() === 's'

    const handleKeydown = (event) => {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) return
        if (!isSaveKey(event)) return

        // Swallow the browser's own "Save page" dialog even when we skip the save.
        event.preventDefault()

        if (event.repeat) return
        if (!unref(enabled)) return
        if (form?.processing) return
        if (isOverlayOpen()) return

        // Clicking Save would blur the focused field; do the same so inputs that
        // commit on blur (formatted numbers, date pickers) are included.
        const active = document.activeElement
        if (active instanceof HTMLElement && ['INPUT', 'TEXTAREA'].includes(active.tagName)) {
            active.blur()
        }

        if (typeof onSave === 'function') {
            onSave()
            return
        }

        submitForm()
    }

    onMounted(() => window.addEventListener('keydown', handleKeydown, true))
    onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown, true))

    return saveFormRef
}
