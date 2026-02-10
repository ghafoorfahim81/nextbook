
import { createApp, h, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n' 
import { toast } from 'vue-sonner'

export function useDeleteResource() {
    const { t } = useI18n()

    const deleteResource = (routeName, id, options = {}) => {
        console.log(routeName, id, options)
        const isOpen = ref(true)
        const container = document.createElement('div')
        document.body.appendChild(container)

        const app = createApp({
            setup() {
                const handleConfirm = () => {
                    router.delete(route(routeName, id), {
                        onSuccess: (page) => {
                            // Check server flashed error (e.g., main branch or dependency)
                            const flashedError = page?.props?.flash?.error || page?.props?.error
                            if (flashedError) {
                                toast.error(flashedError, {
                                    description: flashedError,
                                    className: 'bg-pink-600 text-white',
                                    duration: 8000,
                                })
                                app.unmount()
                                container.remove()
                                options?.onError?.()
                                return
                            }

                            // Show success notification with undo option
                            let dismissed = false

                            const handleUndo = () => {
                                if (dismissed) return
                                dismissed = true

                                router.patch(route(routeName.replace('.destroy', '.restore'), id), {}, {
                                    onSuccess: () => {
                                        toast.success(t('general.restore_successful'), {
                                            description: t('general.restore_success', { name: options.name }),
                                            class: 'bg-green-600',
                                            duration: 4000,
                                        })
                                        options?.onUndo?.()
                                    },
                                    onError: (errors) => {
                                        toast.error(errors?.message || t('general.restore_error_message'), {
                                            description: errors?.message || t('general.restore_error_message'),
                                            className: 'bg-pink-600 text-white',
                                            duration: 6000,
                                        })
                                        options?.onError?.()
                                    }
                                })
                            }

                            toast.success( t('general.delete_sucessfully'), {
                                description: options.successMessage || t('general.delete_success', { name: options.name }),
                                action: {
                                    label: t('general.undo'),
                                    onClick: handleUndo,
                                },
                                class: 'bg-green-600',
                                duration: 5000,
                                onAutoClose: () => {
                                    dismissed = true
                                }
                            })

                            app.unmount()
                            container.remove()
                            options?.onSuccess?.()
                        },
                        onError: (errors) => {
                            // Determine dependency error for custom messaging/styling
                            let errorMessage = t('general.delete_error_message')
                            let isDependencyError = false

                            if (errors?.category) {
                                errorMessage = errors.category
                                isDependencyError = true
                            } else if (errors?.message) {
                                errorMessage = errors.message
                                isDependencyError =
                                    errorMessage.includes('Cannot delete this record') ||
                                    errorMessage.includes('dependencies') ||
                                    errorMessage.includes('used in')
                            } else if (errors?.error) {
                                errorMessage = errors.error
                                isDependencyError =
                                    errorMessage.includes('Cannot delete this record') ||
                                    errorMessage.includes('dependencies') ||
                                    errorMessage.includes('used in')
                            }

                            toast.error(errorMessage, {
                                description: errorMessage,
                                className: 'bg-pink-600 text-white',
                                duration: isDependencyError ? 10000 : 7000,
                                action: !isDependencyError
                                    ? {
                                        label: t('general.try_again'),
                                        onClick: () => {},
                                    }
                                    : undefined,
                            })

                            app.unmount()
                            container.remove()
                            options?.onError?.()
                        }
                    })
                }

                const handleClose = () => {
                    isOpen.value = false
                    app.unmount()
                    container.remove()
                }

                return () =>
                    h(ConfirmDeleteDialog, {
                        open: isOpen.value,
                        cancelText: t('general.cancel'),
                        continueText: t('general.confirm'),
                        title: options.title || t('general.are_you_sure'),
                        description: options.description || t('general.action_cannot_be_undone'),
                        'onUpdate:open': (val) => {
                            if (!val) handleClose()
                        },
                        onConfirm: handleConfirm
                    })
            }
        })

        app.mount(container)
    }

    return { deleteResource }
}
