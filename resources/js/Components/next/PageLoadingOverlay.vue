<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import BookLoader from '@/Components/next/BookLoader.vue'

const { t } = useI18n()
const visible = ref(false)

// Avoid flicker on fast navigations, and avoid an instant flash-hide once shown.
const SHOW_DELAY_MS = 150
const MIN_VISIBLE_MS = 400

let showTimer = null
let hideTimer = null
let shownAt = 0

// The DataTable component tags its refreshes with an X-DataTable-Refresh
// header. Its GETs are already covered by isSamePageRefresh; the header still
// matters for the in-place PATCH requests (e.g. activate/deactivate on a
// show page) that reuse it to stay silent.
function isDataTableRefresh(event) {
    try {
        const headers = event?.detail?.visit?.headers ?? {}
        return headers['X-DataTable-Refresh'] === '1'
    } catch {
        return false
    }
}

// Saving preferences (and the other mutations on that page) refreshes props in
// place, and the Preferences page renders its own inline spinners for each
// action. The full-screen BookLoader on top of that just double-loads, so skip
// it for any non-GET request to a /preferences endpoint.
function isPreferencesMutation(event) {
    try {
        const visit = event?.detail?.visit ?? {}
        const method = String(visit.method ?? 'get').toLowerCase()
        const path = visit.url?.pathname ?? ''
        return method !== 'get' && (path === '/preferences' || path.startsWith('/preferences/'))
    } catch {
        return false
    }
}

// The overlay is a *navigation* affordance — it tells the operator the page is
// being replaced. A screen that saves in place (the fast entry / fast opening
// grids) stays exactly where it is, so covering it with a full-screen book
// loader just hides the work. Those requests opt out with this header and show
// their own in-button progress instead.
function isSilentVisit(event) {
    try {
        const headers = event?.detail?.visit?.headers ?? {}
        return headers['X-Silent-Loader'] === '1'
    } catch {
        return false
    }
}

// Deleting a record never navigates anywhere — the operator stays on the list
// and the row simply leaves it. Covering the screen with a book loader hides
// that, and hides the undo toast that follows. This is checked on the method so
// it holds for every delete in the system, including pages that call
// router.delete() directly rather than going through useDeleteResource().
function isDelete(event) {
    try {
        return String(event?.detail?.visit?.method ?? 'get').toLowerCase() === 'delete'
    } catch {
        return false
    }
}

// A save or update submitted from a modal keeps the operator on the same page;
// the dialog's own button already shows "Saving…". Covering the dialog with the
// book loader hides that and reads as a page change, so any non-GET visit that
// starts while a dialog is open stays silent.
function isDialogSubmit(event) {
    try {
        const method = String(event?.detail?.visit?.method ?? 'get').toLowerCase()
        return method !== 'get'
            && document.querySelector('[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]') !== null
    } catch {
        return false
    }
}

// A GET back to the page already on screen is an in-place refresh — search,
// filters, sorting, pagination, a partial reload of some props. The operator
// is not leaving, the page shows its own loading state, and a full-screen
// book over it hid the list being searched. Only ~12 of the ~100 places that
// refresh this way sent a header to opt out, so it is decided here instead:
// the overlay is only for moving to a different page.
function isSamePageRefresh(event) {
    try {
        const visit = event?.detail?.visit ?? {}
        const method = String(visit.method ?? 'get').toLowerCase()
        if (method !== 'get') return false

        const isPartialReload = Array.isArray(visit.only) && visit.only.length > 0
        const targetPath = visit.url?.pathname ?? ''

        return isPartialReload || targetPath === window.location.pathname
    } catch {
        return false
    }
}

function handleStart(event) {
    if (
        isSamePageRefresh(event)
        || isDataTableRefresh(event)
        || isPreferencesMutation(event)
        || isSilentVisit(event)
        || isDelete(event)
        || isDialogSubmit(event)
    ) return

    clearTimeout(hideTimer)
    clearTimeout(showTimer)
    showTimer = setTimeout(() => {
        visible.value = true
        shownAt = Date.now()
    }, SHOW_DELAY_MS)
}

function handleFinish() {
    clearTimeout(showTimer)
    if (!visible.value) return

    const remaining = MIN_VISIBLE_MS - (Date.now() - shownAt)
    if (remaining > 0) {
        hideTimer = setTimeout(() => { visible.value = false }, remaining)
    } else {
        visible.value = false
    }
}

let offStart
let offFinish

onMounted(() => {
    offStart = router.on('start', handleStart)
    offFinish = router.on('finish', handleFinish)
})

onBeforeUnmount(() => {
    clearTimeout(showTimer)
    clearTimeout(hideTimer)
    offStart?.()
    offFinish?.()
})
</script>

<template>
    <Transition name="page-loader-fade">
        <div
            v-if="visible"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-4 bg-background/70 backdrop-blur-sm"
            role="status"
            aria-live="polite"
            :aria-label="t('general.loading')"
        >
            <BookLoader :size="140" />
            <span class="text-sm font-medium text-muted-foreground">{{ t('general.loading') }}</span>
        </div>
    </Transition>
</template>

<style scoped>
.page-loader-fade-enter-active,
.page-loader-fade-leave-active {
    transition: opacity 0.15s ease;
}

.page-loader-fade-enter-from,
.page-loader-fade-leave-to {
    opacity: 0;
}
</style>
