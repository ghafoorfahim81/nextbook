<script setup>
/**
 * The page-change indicator: Inertia's own progress bar and spinner (NProgress,
 * the library Inertia's progress uses) at the top of the screen. It replaced a
 * full-screen book animation.
 *
 * Inertia's built-in progress (`progress` in app.js) stays off: it shows for
 * every request, and Inertia 1.x has no way to skip one. This drives the same
 * bar itself, so the rules below still keep it off for anything that does not
 * leave the page (searches, in-place saves, deletes, dialog submits).
 */
import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
// Inertia's own dependency (@inertiajs/core), so it is always installed.
import NProgress from 'nprogress'

NProgress.configure({ showSpinner: true, minimum: 0.08, trickleSpeed: 200 })

// Inertia's default: no bar for a navigation that finishes within this.
const SHOW_DELAY_MS = 250

let showTimer = null
let started = false

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
// action. The page loading bar on top of that just double-loads, so skip
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
// and the row simply leaves it. Covering the screen with a loading indicator hides
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

    clearTimeout(showTimer)
    showTimer = setTimeout(() => {
        started = true
        NProgress.start()
    }, SHOW_DELAY_MS)
}

// An upload reports how far it got; the bar follows it.
function handleProgress(event) {
    const percentage = event?.detail?.progress?.percentage
    if (started && percentage) NProgress.set(Math.max(NProgress.status ?? 0, (percentage / 100) * 0.9))
}

function handleFinish() {
    clearTimeout(showTimer)
    if (!started) return

    started = false
    NProgress.done()
}

let offStart
let offProgress
let offFinish

onMounted(() => {
    offStart = router.on('start', handleStart)
    offProgress = router.on('progress', handleProgress)
    offFinish = router.on('finish', handleFinish)
})

onBeforeUnmount(() => {
    clearTimeout(showTimer)
    if (started) NProgress.done()
    offStart?.()
    offProgress?.()
    offFinish?.()
})
</script>

<template>
    <!-- NProgress draws its bar and spinner straight into the body. -->
</template>

<style>
/* Inertia's progress styles (its injected CSS), in the brand colour. */
#nprogress {
    pointer-events: none;
}
#nprogress .bar {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1031;
    width: 100%;
    height: 2px;
    background: hsl(var(--primary));
}
#nprogress .peg {
    position: absolute;
    right: 0;
    display: block;
    width: 100px;
    height: 100%;
    box-shadow: 0 0 10px hsl(var(--primary)), 0 0 5px hsl(var(--primary));
    opacity: 1;
    transform: rotate(3deg) translate(0, -4px);
}
#nprogress .spinner {
    position: fixed;
    top: 15px;
    right: 15px;
    z-index: 1031;
    display: block;
}
[dir='rtl'] #nprogress .spinner {
    right: auto;
    left: 15px;
}
#nprogress .spinner-icon {
    box-sizing: border-box;
    width: 18px;
    height: 18px;
    border: solid 2px transparent;
    border-top-color: hsl(var(--primary));
    border-left-color: hsl(var(--primary));
    border-radius: 50%;
    animation: nprogress-spinner 400ms linear infinite;
}
@keyframes nprogress-spinner {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
