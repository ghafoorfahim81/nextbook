
import { createApp, h, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { router } from '@inertiajs/vue3'
import { ToastAction } from '@/Components/ui/toast'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/Components/ui/toast/use-toast'

export function useDeleteResource() {
    // Move composables to the top level
    const { t } = useI18n()
    const { toast } = useToast()

    const deleteResource = (routeName, id, options = {}) => {
        const isOpen = ref(true)
        const container = document.createElement('div')
        document.body.appendChild(container)

        const app = createApp({
            setup() {
                const handleConfirm = () => {
                    router.delete(route(routeName, id), {
                        onSuccess: (page) => {
                            // Check if there's an error in the response (dependency error)
                            if (page.props.error) {
                                toast({
                                    title: t('general.dependencies_found'),
                                    description: page.props.error,
                                    variant: 'destructive',
                                    class:'bg-pink-600 text-white',
                                    duration: Infinity,
                                });
                                app.unmount();
                                container.remove();
                                options?.onError?.();
                                return;
                            }

                            // Show success toast with undo option
                            let dismissed = false;

                            const handleUndo = () => {
                                if (dismissed) return;
                                dismissed = true;

                                // Restore the record
                                router.patch(route(routeName.replace('.destroy', '.restore'), id), {}, {
                                    onSuccess: () => {
                                        toast({
                                            title: t('general.success'),
                                            variant: 'success',
                                            description: t('general.restore_success', { name: options.name }),
                                        });
                                        options?.onUndo?.()
                                    },
                                    onError: (errors) => {
                                        toast({
                                            title: t('general.error'),
                                            description: errors?.message || t('general.restore_error_message'),
                                            variant: 'destructive',
                                        });
                                        options?.onError?.()
                                    }
                                });
                            };

                            const toastInstance = toast({
                                title: t('general.success'),
                                variant: 'success',
                                description: options.successMessage || t('general.delete_success', { name: options.name }),
                                action: h(ToastAction, {
                                    altText: t('general.undo'),
                                    onClick: handleUndo
                                }, {
                                    default: () => t('general.undo'),
                                }),
                                duration: 5000, // 5 seconds to undo
                                onDismiss: () => {
                                    dismissed = true;
                                }
                            });

                            app.unmount()
                            container.remove()
                            options?.onSuccess?.()
                        },
                        onError: (errors) => {
                            // Check if it's a dependency error (Laravel validation errors)
                            let errorMessage = t('general.delete_error_message');
                            let isDependencyError = false;

                            if (errors?.category) {
                                errorMessage = errors.category;
                                isDependencyError = true;
                            } else if (errors?.message) {
                                errorMessage = errors.message;
                                isDependencyError = errorMessage.includes('Cannot delete this record') ||
                                                   errorMessage.includes('dependencies') ||
                                                   errorMessage.includes('used in');
                            } else if (errors?.error) {
                                errorMessage = errors.error;
                                isDependencyError = errorMessage.includes('Cannot delete this record') ||
                                                   errorMessage.includes('dependencies') ||
                                                   errorMessage.includes('used in');
                            }
                            toast({
                                title: isDependencyError ? t('general.dependencies_found') : t('general.error'),
                                description: errorMessage,
                                variant: 'destructive',
                                class:'bg-pink-600 text-white',
                                duration: Infinity,
                                    action: isDependencyError ? null : h(ToastAction, {
                                    altText: t('general.try_again'),
                                }, {
                                    default: () => t('general.try_again'),
                                }),
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
                        cancelText:   t('general.cancel'),
                        continueText:   t('general.confirm'),
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
