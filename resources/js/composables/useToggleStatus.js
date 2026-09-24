import { createApp, h, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'

/**
 * Activate or deactivate a record, behind the same confirmation step a delete
 * gets.
 *
 * Deactivating is not destructive, but it is not nothing either: the record
 * drops out of every picker in the app, so anyone filling in a form stops
 * being offered it. That deserves the same "are you sure" as a delete — which
 * is what the user asked for — and it reuses the same dialog so the two read
 * as the same kind of decision.
 */
export function useToggleStatus() {
    const { t } = useI18n()

    /**
     * @param {string} routeName  e.g. 'warehouses.toggle-status'
     * @param {string} id
     * @param {object} options    { isActive, name, onSuccess }
     */
    const toggleStatus = (routeName, id, options = {}) => {
        const isOpen = ref(true)
        const isSaving = ref(false)
        const container = document.createElement('div')
        document.body.appendChild(container)

        const active = Boolean(options.isActive)
        const name = options.name ?? t('general.record')

        const app = createApp({
            setup() {
                const handleConfirm = () => {
                    if (isSaving.value) return
                    isSaving.value = true

                    router.patch(route(routeName, id), {}, {
                        // The row updates in place; a full-screen loader would
                        // just hide the thing the operator is watching.
                        headers: { 'X-Silent-Loader': '1' },
                        preserveScroll: true,
                        onSuccess: (page) => {
                            const flashedError = page?.props?.flash?.error || page?.props?.error

                            if (flashedError) {
                                toast.error(flashedError, { class: 'bg-pink-600 text-white', duration: 8000 })
                            } else {
                                toast.success(
                                    active
                                        ? t('general.deactivated', { name })
                                        : t('general.activated', { name }),
                                    { class: 'bg-green-600', duration: 4000 }
                                )
                            }

                            app.unmount()
                            container.remove()
                            options?.onSuccess?.()
                        },
                        onError: (errors) => {
                            toast.error(errors?.message || t('general.something_went_wrong'), {
                                class: 'bg-pink-600 text-white',
                                duration: 7000,
                            })
                            app.unmount()
                            container.remove()
                            options?.onError?.()
                        },
                    })
                }

                const handleClose = () => {
                    if (isSaving.value) return
                    isOpen.value = false
                    app.unmount()
                    container.remove()
                }

                return () =>
                    h(ConfirmDeleteDialog, {
                        open: isOpen.value,
                        cancelText: t('general.cancel'),
                        continueText: active ? t('general.deactivate') : t('general.activate'),
                        title: active
                            ? t('general.deactivate_title', { name })
                            : t('general.activate_title', { name }),
                        description: active
                            ? t('general.deactivate_description', { name })
                            : t('general.activate_description', { name }),
                        loading: isSaving.value,
                        loadingText: t('general.saving'),
                        'onUpdate:open': (val) => {
                            if (!val) handleClose()
                        },
                        onConfirm: handleConfirm,
                    })
            },
        })

        app.mount(container)
    }

    return { toggleStatus }
}
