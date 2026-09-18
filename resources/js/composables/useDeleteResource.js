
import { createApp, h, markRaw, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import UndoToast from '@/Components/next/UndoToast.vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { moduleLabelForRoute } from '@/lib/modules'
import { toast } from 'vue-sonner'
import { useSoundPreferences } from '@/composables/useSoundPreferences'

export function useDeleteResource() {
    const { t } = useI18n()
    const { play } = useSoundPreferences()
    const page = usePage()

    /**
     * Name the module the record is leaving and how long it stays recoverable,
     * so the operator knows both what they are about to lose and where to go
     * looking for it. Routes outside the grouped resources fall back to the
     * generic warning rather than naming a module we had to guess.
     */
    const describeDelete = (routeName, options) => {
        if (options.description) {
            return options.description
        }

        const module = moduleLabelForRoute(routeName, t)

        if (!module) {
            return t('general.action_cannot_be_undone')
        }

        return t('general.delete_module_description', {
            module,
            trash: t('sidebar.main.trash'),
            days: page.props?.app?.deleted_records_retention_days ?? 30,
        })
    }

    // Gmail gives you three seconds to change your mind; the ring in the toast
    // is drawn against this same number.
    const UNDO_WINDOW_MS = 3000

    const deleteResource = (routeName, id, options = {}) => {
        const isOpen = ref(true)
        const isDeleting = ref(false)
        const container = document.createElement('div')
        document.body.appendChild(container)

        const app = createApp({
            setup() {
                const handleConfirm = () => {
                    if (isDeleting.value) return
                    isDeleting.value = true

                    router.delete(route(routeName, id), {
                        // A delete leaves the operator on the same screen, so the
                        // full-screen BookLoader has nothing to announce.
                        headers: { 'X-Silent-Loader': '1' },
                        onSuccess: (page) => {
                            // Check server flashed error (e.g., main branch or dependency)
                            const flashedError = page?.props?.flash?.error || page?.props?.error
                            if (flashedError) {
                                play('warning')
                                toast.error(flashedError, {
                                    description: flashedError,
                                    class: 'bg-pink-600 text-white',
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
                                    // Restoring swaps a row back into the list in
                                    // place; a full-screen loader over it just
                                    // hides the thing the operator is watching for.
                                    headers: { 'X-Silent-Loader': '1' },
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
                                            class: 'bg-pink-600 text-white',
                                            duration: 6000,
                                        })
                                        options?.onError?.()
                                    }
                                })
                            }

                            // Gmail's pattern: state what happened in one plain
                            // sentence, offer Undo, and show the time left to take
                            // it. The styled success toast said "deleted
                            // successfully" twice and gave no sense of the window.
                            // The toast never expires on its own: the component
                            // runs the countdown and dismisses itself, so hovering
                            // cannot hold the undo window open past the ring.
                            const toastId = toast.custom(markRaw(UndoToast), {
                                duration: Infinity,
                                // The shared Toaster styles every toast as a
                                // top-aligned p-4 card; this row is a single line,
                                // so centre it and tighten the padding. Marked
                                // important because both class strings are merged
                                // and Tailwind would otherwise resolve p-4 vs p-3
                                // by stylesheet order.
                                classes: { toast: 'items-center !px-3 !py-2.5' },
                                componentProps: {
                                    message: options.trashMessage
                                        || t('general.moved_to_trash', { name: options.name ?? t('general.record') }),
                                    undoLabel: t('general.undo'),
                                    closeLabel: t('general.close'),
                                    duration: UNDO_WINDOW_MS,
                                    onUndo: () => {
                                        toast.dismiss(toastId)
                                        handleUndo()
                                    },
                                    onClose: () => {
                                        dismissed = true
                                        toast.dismiss(toastId)
                                    },
                                    onExpire: () => {
                                        dismissed = true
                                        toast.dismiss(toastId)
                                    },
                                },
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

                            if (isDependencyError) play('warning')

                            toast.error(errorMessage?.title ? errorMessage.title : errorMessage, {
                                description: errorMessage,
                                class: 'bg-pink-600 text-white',
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
                    // A delete in flight owns the dialog; dismissing it here
                    // would tear down the component that is still waiting on
                    // the response.
                    if (isDeleting.value) return

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
                        description: describeDelete(routeName, options),
                        loading: isDeleting.value,
                        loadingText: t('general.deleting'),
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
