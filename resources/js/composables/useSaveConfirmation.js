import { createApp, h, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

/**
 * Save-confirmation helper for forms that don't use the shared <SubmitButtons>
 * component (e.g. custom Edit pages). Shows a confirmation dialog before running
 * the provided save callback, gated by the per-module preference
 * user_preferences.confirmations[module] (defaults to enabled).
 *
 * Usage:
 *   const { confirmSave } = useSaveConfirmation()
 *   <form @submit.prevent="confirmSave('expense', () => submit('update'))">
 */
export function useSaveConfirmation() {
    const page = usePage()
    const { t } = useI18n()

    const isEnabled = (module) => {
        if (!module) return false
        return page.props?.user_preferences?.confirmations?.[module] ?? true
    }

    const confirmSave = (module, proceed) => {
        if (typeof proceed !== 'function') return
        if (!isEnabled(module)) {
            proceed()
            return
        }

        const isOpen = ref(true)
        const container = document.createElement('div')
        document.body.appendChild(container)

        const cleanup = () => {
            app.unmount()
            container.remove()
        }

        const app = createApp({
            setup() {
                return () =>
                    h(ConfirmDeleteDialog, {
                        open: isOpen.value,
                        title: t('general.save_confirmation_title'),
                        description: t('general.save_confirmation_message'),
                        cancelText: t('general.cancel'),
                        continueText: t('general.confirm'),
                        contentClass: '!top-[28%]',
                        'onUpdate:open': (val) => {
                            if (!val) {
                                isOpen.value = false
                                cleanup()
                            }
                        },
                        onConfirm: () => {
                            isOpen.value = false
                            cleanup()
                            proceed()
                        },
                    })
            },
        })

        app.mount(container)
    }

    return { confirmSave }
}
