<template>
    <!-- `title` sits on the wrapper: a disabled input fires no hover events of its own. -->
    <div ref="rootRef" class="relative w-full" :title="disabled ? disabledHint : undefined">
        <NextInput
            ref="inputRef"
            v-model="query"
            :label="label || t('general.scan_or_search_item')"
            :disabled="disabled"
            autocomplete="off"
            @keydown="onKeydown"
            @focusin="onFocus"
        />

        <!-- Suggestion list: partial matches for whatever the user typed -->
        <div
            v-if="isOpen"
            class="absolute start-0 top-full z-50 mt-1 w-full min-w-[22rem] max-w-[min(32rem,90vw)]
                   overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-lg"
        >
            <div v-if="isLoading" class="flex items-center justify-center gap-2 py-3 text-sm text-muted-foreground">
                <Spinner class="size-4" />
                <span>{{ t('general.loading') }}</span>
            </div>

            <div v-else-if="!suggestions.length" class="py-3 text-center text-sm text-muted-foreground">
                {{ t('general.no_results') }}
            </div>

            <div v-else class="max-h-72 overflow-y-auto py-1">
                <button
                    v-for="(item, index) in suggestions"
                    :key="item.id"
                    type="button"
                    :ref="(el) => { if (el) optionEls[index] = el }"
                    class="flex w-full items-center gap-3 px-3 py-2 text-start transition-colors"
                    :class="index === activeIndex ? 'bg-primary text-primary-foreground' : 'hover:bg-primary/15'"
                    @mousedown.prevent="choose(item)"
                    @mouseenter="activeIndex = index"
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium leading-tight" v-html="highlight(item.name)" />
                        <div
                            class="mt-0.5 truncate text-[11px]"
                            :class="index === activeIndex ? 'text-primary-foreground/75' : 'text-muted-foreground'"
                        >
                            {{ subtitle(item) }}
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium"
                        :class="index === activeIndex ? 'bg-white/20 text-primary-foreground' : 'bg-muted text-muted-foreground'"
                    >
                        {{ t('general.on_hand') }}: {{ formatQty(item.on_hand) }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
/* Barcode field that doubles as an item autocomplete.
 *
 * A hardware scanner types the whole code then sends Enter, so Enter with no
 * highlighted suggestion resolves the exact barcode (the scanner path). Typing a
 * keyword instead shows partial matches after a short debounce; ↑/↓ highlight one
 * and Enter picks it. Either way the parent gets a `select` with the item row from
 * `search.items-list`, which is the same shape the item column selects use. */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import NextInput from '@/Components/next/NextInput.vue'
import { Spinner } from '@/Components/ui/spinner'

const props = defineProps({
    warehouseId: { type: String, default: '' },
    // Purchases can receive items that hold no stock yet; sales cannot.
    inStockOnly: { type: Boolean, default: true },
    disabled: { type: Boolean, default: false },
    // Tooltip explaining the lock, so a not-allowed cursor is never unexplained.
    disabledHint: { type: String, default: '' },
    label: { type: String, default: '' },
    limit: { type: Number, default: 15 },
    minSearchLength: { type: Number, default: 2 },
    debounceMs: { type: Number, default: 250 },
})

const emit = defineEmits(['select', 'not-found', 'error'])

const { t } = useI18n()

const rootRef = ref(null)
const inputRef = ref(null)
const optionEls = ref({})

const query = ref('')
const suggestions = ref([])
const activeIndex = ref(-1)
const isOpen = ref(false)
const isLoading = ref(false)
// Term the current `suggestions` belong to, so Enter can skip a redundant round trip.
const resolvedTerm = ref('')

let debounceTimer = null
let requestSeq = 0

const focus = () => inputRef.value?.focus?.()

const close = () => {
    isOpen.value = false
    activeIndex.value = -1
}

const reset = () => {
    clearTimeout(debounceTimer)
    query.value = ''
    suggestions.value = []
    resolvedTerm.value = ''
    close()
}

const fetchItems = async (term) => {
    const seq = ++requestSeq
    isLoading.value = true
    try {
        const params = {
            search: term,
            limit: props.limit,
            in_stock_only: props.inStockOnly,
        }
        // Omitted rather than sent empty: the endpoint then falls back to the main warehouse,
        // which is what the form itself defaults to once its warehouse prop resolves.
        if (props.warehouseId) params.warehouse_id = props.warehouseId

        const { data } = await axios.get(route('search.items-list'), { params })
        const results = data?.data || []
        // Ignore responses overtaken by a newer keystroke.
        if (seq === requestSeq) {
            suggestions.value = results
            resolvedTerm.value = term
            activeIndex.value = -1
        }
        return results
    } finally {
        if (seq === requestSeq) isLoading.value = false
    }
}

watch(query, (value) => {
    clearTimeout(debounceTimer)
    const term = String(value ?? '').trim()

    if (term.length < props.minSearchLength) {
        suggestions.value = []
        resolvedTerm.value = ''
        close()
        return
    }

    debounceTimer = setTimeout(async () => {
        try {
            await fetchItems(term)
            if (String(query.value ?? '').trim() === term) isOpen.value = true
        } catch (error) {
            console.error('Item search failed', error)
        }
    }, props.debounceMs)
})

const choose = (item) => {
    reset()
    emit('select', item)
    focus()
}

/* Enter without a highlighted row is treated as a scan: exact barcode wins, a
 * single result is taken as the intended item, and anything else falls back to
 * showing the matches so the user can pick. */
const resolveScan = async () => {
    clearTimeout(debounceTimer)
    const code = String(query.value ?? '').trim()
    if (!code) return

    try {
        const results = (resolvedTerm.value === code && !isLoading.value)
            ? suggestions.value
            : await fetchItems(code)

        let item = results.find(i => String(i.barcode ?? '').trim() === code)
        if (!item && results.length === 1) item = results[0]

        if (!item) {
            if (results.length > 1) {
                isOpen.value = true
                activeIndex.value = 0
                scrollToActive()
                return
            }
            reset()
            emit('not-found', code)
            return
        }

        choose(item)
    } catch (error) {
        console.error('Item lookup failed', error)
        reset()
        emit('error', error)
    }
}

const scrollToActive = () => {
    nextTick(() => optionEls.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' }))
}

const move = (step) => {
    if (!suggestions.value.length) return
    if (!isOpen.value) {
        isOpen.value = true
        return
    }
    const last = suggestions.value.length - 1
    activeIndex.value = step > 0
        ? Math.min(activeIndex.value + 1, last)
        : Math.max(activeIndex.value - 1, -1)
    scrollToActive()
}

const onKeydown = (event) => {
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault()
            move(1)
            break
        case 'ArrowUp':
            event.preventDefault()
            move(-1)
            break
        case 'Enter':
            event.preventDefault()
            if (isOpen.value && activeIndex.value >= 0 && suggestions.value[activeIndex.value]) {
                choose(suggestions.value[activeIndex.value])
            } else {
                resolveScan()
            }
            break
        case 'Escape':
            if (isOpen.value) {
                event.preventDefault()
                event.stopPropagation()
                close()
            }
            break
        case 'Tab':
            close()
            break
    }
}

const onFocus = () => {
    if (suggestions.value.length) isOpen.value = true
}

const onDocumentPointerDown = (event) => {
    if (!rootRef.value?.contains(event.target)) close()
}

onMounted(() => document.addEventListener('mousedown', onDocumentPointerDown))
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentPointerDown)
    clearTimeout(debounceTimer)
})

// Stale suggestions from another warehouse must never be selectable.
watch(() => props.warehouseId, () => reset())

/* ---------------- DISPLAY HELPERS ---------------- */

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

const highlight = (name) => {
    const text = String(name ?? '')
    const term = String(query.value ?? '').trim()
    if (!term) return escapeHtml(text)

    const start = text.toLowerCase().indexOf(term.toLowerCase())
    if (start === -1) return escapeHtml(text)

    const end = start + term.length
    return escapeHtml(text.slice(0, start))
        + `<mark class="bg-transparent font-bold text-inherit underline">${escapeHtml(text.slice(start, end))}</mark>`
        + escapeHtml(text.slice(end))
}

const subtitle = (item) => [item.code, item.barcode, item.packing, item.generic_name]
    .map(part => String(part ?? '').trim())
    .filter(Boolean)
    .join(' · ')

const formatQty = (value) => Number(value ?? 0).toLocaleString()

defineExpose({ focus, reset })
</script>
