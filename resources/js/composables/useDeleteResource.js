import { createApp, h, ref } from 'vue'
import ConfirmDeleteDialog from '@/Components/next/ConfirmDeleteDialog.vue'
import { router } from '@inertiajs/vue3'
import { ToastAction } from '@/components/ui/toast'
import { useToast } from '@/components/ui/toast/use-toast'

const { toast } = useToast()
export function useDeleteResource() {
    const deleteResource = (routeName, id, options = {}) => {
        const isOpen = ref(true)

        const container = document.createElement('div')
        document.body.appendChild(container)

        const app = createApp({
            setup() {
                const handleConfirm = () => {
                    router.delete(route(routeName, id), {
                        onSuccess: () => {
                            toast({
                                title: 'Success',
                                variant: 'success',
                                description: options.successMessage || 'The resource has been deleted successfully.',
                            });
                            app.unmount()
                            container.remove()
                            options?.onSuccess?.()
                        },
                        onError: () => {
                            toast({
                                title: 'Uh oh! Something went wrong.',
                                description: 'There was a problem with your request.',
                                variant: 'destructive',
                                action: h(ToastAction, {
                                    altText: 'Try again',
                                }, {
                                    default: () => 'Try again',
                                }),
                            });
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
                        title: options.title || 'Are you sure?',
                        description: options.description || 'This action cannot be undone.',
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
