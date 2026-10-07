import { router } from '@inertiajs/vue3'

// Browser history for the reports page: an open report always has the report
// catalog as the entry right behind it, so the back button (and the "Back to
// reports" button) returns to the list rather than to whatever page came
// before /reports.
//
// The marker is kept in Inertia's remembered state, which survives the
// filter, paging and view-toggle visits a report makes (same page component,
// preserveState) and rides along when the browser restores the entry.

const CATALOG_BEHIND = 'reports.catalogBehind'
const CATALOG_URL = '/reports'

// A reload drops Inertia's remembered state, but a report being reloaded was
// already given its catalog entry when it was first opened. Only the first
// page of a document can be a reload.
let firstCheck = true

function isFirstCheckAfterReload() {
    if (!firstCheck) return false
    firstCheck = false
    const [navigation] = window.performance?.getEntriesByType?.('navigation') ?? []
    return navigation?.type === 'reload'
}

export function hasCatalogBehind() {
    return Boolean(router.restore(CATALOG_BEHIND))
}

/**
 * Called whenever a report is on screen. `openedFromCatalog` means this entry
 * was just pushed on top of the catalog, so nothing needs inserting.
 */
export function ensureCatalogBehind({ openedFromCatalog = false } = {}) {
    const reloaded = isFirstCheckAfterReload()

    if (hasCatalogBehind()) return

    if (!openedFromCatalog && !reloaded) {
        const current = window.history.state
        if (!current?.component) return

        // Reached the report some other way (a link from elsewhere, a pasted
        // URL): turn this entry into the catalog and push the report on top.
        // The catalog draws from reportOptions alone, so the report's own
        // figures and picker lists are dropped from the copy.
        window.history.replaceState({
            ...current,
            url: CATALOG_URL,
            props: {
                ...current.props,
                reportSelected: false,
                filterOptions: {},
                result: { rows: [], summary: {}, meta: {}, pagination: null },
            },
            rememberedState: {},
            scrollRegions: [],
        }, '', CATALOG_URL)
        window.history.pushState(current, '', current.url)
    }

    router.remember(true, CATALOG_BEHIND)
}
