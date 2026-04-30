import { ref, computed, watch } from 'vue'
import Fuse from 'fuse.js'
import { useDebounceFn } from '@vueuse/core'
import { router } from '@inertiajs/vue3'

const PEOPLE_TYPES  = ['customer', 'supplier', 'owner', 'user']
const ITEM_TYPES    = ['item']
const FINANCE_TYPES = ['sale', 'purchase', 'receipt', 'payment', 'expense', 'account']

// ── Module-level singleton ───────────────────────────────────────────────────
export const searchIndex = ref([])
export const isLoading   = ref(false)
let fuseInstance = null
let indexLoaded  = false
let loadPromise  = null   // deduplicate concurrent calls

function buildFuse() {
    fuseInstance = new Fuse(searchIndex.value, {
        keys: [
            { name: 'name',       weight: 0.5 },
            { name: 'local_name', weight: 0.3 },
            { name: 'code',       weight: 0.15 },
            { name: 'subtitle',   weight: 0.05 },
        ],
        threshold: 0.4,
        includeMatches: true,
        includeScore: true,
        minMatchCharLength: 2,
        ignoreLocation: true,
    })
    console.log('[GlobalSearch] Fuse built with', searchIndex.value.length, 'records')
}

export async function loadIndex(force = false) {
    // If already loaded and not forced, skip
    if (!force && indexLoaded) {
        console.log('[GlobalSearch] Index already loaded:', searchIndex.value.length, 'items')
        return
    }

    // Deduplicate: if a load is already in flight, wait for it
    if (loadPromise) {
        console.log('[GlobalSearch] Load already in progress, waiting...')
        return loadPromise
    }

    isLoading.value = true
    console.log('[GlobalSearch] Fetching /search/global ...')

    const url = force ? '/search/global?refresh=1' : '/search/global'

    loadPromise = fetch(url, {
        method: 'GET',
        credentials: 'include',
        cache: 'no-store',
        headers: {
            'Accept': 'application/json',
            'Cache-Control': 'no-cache',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then(res => {
            console.log('[GlobalSearch] Response status:', res.status)
            if (!res.ok) throw new Error(`HTTP ${res.status}`)
            return res.json()
        })
        .then(json => {
            const data = json.data ?? []
            console.log('[GlobalSearch] Received', data.length, 'records')
            searchIndex.value = data
            buildFuse()
            indexLoaded = true
        })
        .catch(e => {
            console.error('[GlobalSearch] Load failed:', e)
            fuseInstance = null
            indexLoaded = false
        })
        .finally(() => {
            isLoading.value = false
            loadPromise = null
        })

    return loadPromise
}

// Refresh after every Inertia navigation
router.on('finish', () => {
    if (indexLoaded) loadIndex(true)
})

// ── Per-instance composable ──────────────────────────────────────────────────
export function useGlobalSearch() {
    const query   = ref('')
    const results = ref([])

    const debouncedSearch = useDebounceFn((q) => {
        const trimmed = (q ?? '').trim()
        if (trimmed.length < 2) { results.value = []; return }

        if (!fuseInstance) {
            loadIndex().then(() => {
                if (fuseInstance) {
                    results.value = fuseInstance.search(trimmed, { limit: 40 })
                }
            })
            return
        }
        results.value = fuseInstance.search(trimmed, { limit: 40 })
    }, 200)

    watch(query, q => debouncedSearch(q))

    const people  = computed(() => results.value.filter(r => PEOPLE_TYPES.includes(r.item.type)))
    const items   = computed(() => results.value.filter(r => ITEM_TYPES.includes(r.item.type)))
    const finance = computed(() => results.value.filter(r => FINANCE_TYPES.includes(r.item.type)))

    const counts = computed(() => ({
        all:     results.value.length,
        people:  people.value.length,
        items:   items.value.length,
        finance: finance.value.length,
    }))

    return { query, results, people, items, finance, counts, isLoading, loadIndex }
}
