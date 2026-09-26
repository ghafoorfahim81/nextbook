import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'

/**
 * The one-click document actions: post, reverse, complete, cancel.
 *
 * These requests carry no form fields, so a refusal has nowhere to land. The
 * server's message goes into an error bag nothing is bound to, the confirm
 * dialog stays open, and the screen simply does nothing — which reads as the
 * button being broken rather than the action being refused. Reversing a
 * purchase whose goods have already been sold did exactly that.
 *
 * Every refusal is shown here instead, with the server's own wording, so the
 * operator learns what stopped it and what to do next.
 */
export function useDocumentAction() {
    const { t } = useI18n()

    /**
     * @param {string} url
     * @param {object} data
     * @param {object} options  Inertia visit options; `onSuccess` is yours to pass.
     */
    const submit = (url, data = {}, options = {}) => {
        router.post(url, data, {
            preserveScroll: true,
            ...options,
            onError: (errors) => {
                const message = firstServerError(errors)

                toast.error(t('general.error'), {
                    description: message || t('general.something_went_wrong'),
                    class: 'bg-pink-600 text-white',
                    duration: 8000,
                })

                options.onError?.(errors)
            },
        })
    }

    return { submit }
}

/**
 * The first thing the server actually said, or null when it said nothing.
 *
 * A document form's own "could not save" line is a guess; the server's message
 * is the reason. Refusing a sale of 999 from a shelf of 12 names the item, the
 * warehouse and both numbers — none of which survives being replaced by a
 * fixed sentence.
 */
export function firstServerError(errors) {
    return Object.values(errors ?? {})
        .flat()
        .filter((message) => typeof message === 'string' && message.trim() !== '')[0] ?? null
}
