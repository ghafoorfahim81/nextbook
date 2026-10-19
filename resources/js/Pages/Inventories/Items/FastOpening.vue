<script setup>
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/Layout.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import { Button } from '@/Components/ui/button'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { useFormGuard } from '@/composables/useFormGuard'
import { useSoundPreferences } from '@/composables/useSoundPreferences'
import { formatMoney } from '@/utils/money'
import { Info, ListOrdered } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { useI18n } from 'vue-i18n'
import { useSidebar } from '@/Components/ui/sidebar/utils'
import { ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue'

const { t, locale } = useI18n()
const { play } = useSoundPreferences()

let sidebar = null
try {
    sidebar = useSidebar()
} catch (e) {
    sidebar = null
}
const prevSidebarOpen = ref(true)
onMounted(() => {
    if (sidebar) {
        prevSidebarOpen.value = sidebar.open.value
        sidebar.setOpen(false)
    }
})
onUnmounted(() => {
    if (sidebar) {
        sidebar.setOpen(prevSidebarOpen.value)
    }
})

const props = defineProps({
    items: { type: [Array, Object], required: true },
    // Both arrive as shared Inertia props and may be resource collections
    // ({ data: [...] }) rather than plain arrays.
    unitMeasures: { type: [Array, Object], default: () => [] },
    warehouses: { type: [Array, Object], default: () => [] },
})

const paginatedItems = computed(() => props.items?.data ?? props.items ?? [])
const warehouses = computed(() => props.warehouses?.data ?? props.warehouses ?? [])

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

const buildRows = () => paginatedItems.value.map(item => ({
    _key: item.id,
    item_id: item.id,
    name: item.name,
    batch: item.batch ?? '',
    is_batch_tracked: item.is_batch_tracked,
    is_expiry_tracked: item.is_expiry_tracked,
    warehouse_id: null,
    unit_measure_id: item.unit_measure_id,
    measure_name: item.unit_measure?.name ?? '',
    quantity: item.quantity ?? '',
    expire_date: item.expire_date ?? '',
    cost: item.cost ?? '',
}))

const form = useForm({ items: buildRows() })

/* ------------------------------------------------------------------ *
 * Instructions
 * ------------------------------------------------------------------ */

// Worth the space the first few times and in the way after that, so dismissing
// sticks — and the header keeps a button to bring them back.
const TIPS_KEY = 'nextbook.fast_opening.tips_dismissed'
const showTips = ref(true)

onMounted(() => {
    try {
        showTips.value = window.localStorage.getItem(TIPS_KEY) !== '1'
    } catch (e) {
        // Storage unavailable — showing them is the safe default.
    }
})

watch(showTips, (visible) => {
    try {
        window.localStorage.setItem(TIPS_KEY, visible ? '0' : '1')
    } catch (e) {
        // Ignore.
    }
})

/* ------------------------------------------------------------------ *
 * Search
 * ------------------------------------------------------------------ */

const searchTerm = ref('')

const filteredItemIndexes = computed(() => {
    if (!searchTerm.value) {
        return form.items.map((_, idx) => idx)
    }

    const q = searchTerm.value.toLowerCase()

    return form.items
        .map((row, idx) => ({ row, idx }))
        .filter(({ row }) => {
            const name = (row.name ?? '').toLowerCase()
            const batch = (row.batch ?? '').toLowerCase()
            return name.includes(q) || batch.includes(q)
        })
        .map(({ idx }) => idx)
})

const removeRow = (idx) => {
    if (form.items.length <= 1) return
    form.items.splice(idx, 1)
}

/* ------------------------------------------------------------------ *
 * Defaults that save keystrokes
 * ------------------------------------------------------------------ */

const defaultWarehouseId = computed(() => {
    const list = warehouses.value || []
    const match = list.find((w) => w?.is_main === true) || list[0]
    return match?.id ?? null
})

// Typing a count is the operator saying "this one is real", so the row gets the
// main warehouse without them having to pick it every time.
watch(
    () => form.items.map((item) => item.quantity),
    (quantities) => {
        quantities.forEach((quantity, idx) => {
            const row = form.items[idx]
            if (!row) return

            const amount = Number(quantity)
            if (Number.isFinite(amount) && amount > 0 && !row.warehouse_id) {
                row.warehouse_id = defaultWarehouseId.value
            }
        })
    }
)

/* ------------------------------------------------------------------ *
 * Validation
 * ------------------------------------------------------------------ */

const clientErrors = ref({})
const serverErrors = ref({})

const fieldError = (idx, field) =>
    clientErrors.value[`items.${idx}.${field}`] || serverErrors.value[`items.${idx}.${field}`]

const ERROR_KEYS = ['quantity', 'cost', 'batch', 'expire_date', 'warehouse_id', 'unit_measure_id']

const rowHasError = (idx) => ERROR_KEYS.some((key) => Boolean(fieldError(idx, key)))

// A row is only in play once it carries a count — everything else on it is
// ignored, which is how the operator skips an item without deleting it.
const isCountedRow = (row) => Number(row.quantity) > 0

const countedRows = computed(() => form.items.filter(isCountedRow))

const openingValue = computed(() =>
    countedRows.value.reduce((total, row) => total + (Number(row.quantity) * Number(row.cost || 0)), 0)
)

const validateRows = () => {
    const errors = {}
    const problems = []

    form.items.forEach((row, idx) => {
        if (!isCountedRow(row)) return

        const rowNumber = idx + 1

        if (!row.warehouse_id) {
            errors[`items.${idx}.warehouse_id`] = t('general.required_field')
            problems.push(t('item.warehouse_required_in_row', { row: rowNumber }))
        }

        if (row.cost === '' || row.cost === null || Number(row.cost) <= 0) {
            errors[`items.${idx}.cost`] = t('general.required_field')
            problems.push(t('item.cost_required_in_row', { row: rowNumber }))
        }
    })

    clientErrors.value = errors

    return problems
}

/* ------------------------------------------------------------------ *
 * Keyboard navigation — the grid behaves like a spreadsheet
 * ------------------------------------------------------------------ */

// Order matters: it is the order of arrow-key navigation. The item name and the
// unit are read-only, so they are not stops.
const NAV_KEYS = ['quantity', 'batch', 'expire_date', 'cost', 'warehouse_id']

const gridRef = ref(null)

/** @returns {boolean} whether the cell had anything to put the caret in. */
const focusCell = (rowIndex, colKey) => {
    if (!colKey) return false

    const cell = gridRef.value?.querySelector(`[data-cell="${rowIndex}:${colKey}"]`)
    if (!cell) return false

    // The date cell hands focus to its wrapper, never to the picker's own input:
    // focusing that input is what opens the calendar, and the picker blurs itself
    // on the way, which would drop the operator out of the grid entirely.
    const target = cell.querySelector('[data-grid-focus]')
        || cell.querySelector('input:not([disabled]):not([readonly]), .vs__search')
    if (!target) return false

    target.focus()
    if (typeof target.select === 'function') {
        try { target.select() } catch (e) { /* number inputs refuse select() in some browsers */ }
    }

    return true
}

/** Open the calendar for the date cell the operator is standing on. */
const openDatePicker = (event) => {
    const input = event.currentTarget?.querySelector('input:not([disabled])')
    // The picker toggles itself open from its own focus handler.
    input?.focus()
}

const caretAllowsMove = (el, delta) => {
    if (!el) return true

    // Number inputs support no selection: the getter returns null, and on some
    // browsers throws outright. Either way there is no caret to get in the way.
    let start = null
    let end = null
    try {
        start = el.selectionStart
        end = el.selectionEnd
    } catch (e) {
        return true
    }

    if (typeof start !== 'number') return true

    const length = String(el.value ?? '').length

    if (start !== end) {
        // A whole-value selection is what focusCell leaves behind — the operator
        // has not started editing yet, so the arrow should still move on.
        return start === 0 && end === length
    }

    return delta > 0 ? start === length : start === 0
}

// Rows are navigated in the order they are *displayed*, so arrowing down while
// a search filter is on follows what the operator can actually see.
const visibleRowAt = (position) => filteredItemIndexes.value[position] ?? null

const moveColumn = (rowIndex, colKey, delta) => {
    let next = NAV_KEYS.indexOf(colKey) + delta

    // Batch and expiry are closed on items that are not tracked that way, so
    // they have nothing to focus. Step over them rather than stopping dead.
    while (next >= 0 && next < NAV_KEYS.length) {
        if (focusCell(rowIndex, NAV_KEYS[next])) return
        next += delta
    }
}

const moveRow = (rowIndex, colKey, delta) => {
    const position = filteredItemIndexes.value.indexOf(rowIndex)
    if (position === -1) return

    let next = position + delta

    while (next >= 0 && next < filteredItemIndexes.value.length) {
        const target = visibleRowAt(next)
        if (target !== null && focusCell(target, colKey)) return
        next += delta
    }
}

const onGridKeydown = (event) => {
    const key = event.key
    if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'].includes(key)) return

    // Only the cell editors drive navigation. Anything else that can take a key
    // inside the grid — a calendar day, a dropdown option — keeps its own keys.
    const target = event.target
    const isEditor = target instanceof HTMLInputElement
        || (target instanceof HTMLElement && target.hasAttribute('data-grid-focus'))
    if (!isEditor) return

    const cell = target.closest('[data-cell]')
    if (!cell) return

    const [rowText, colKey] = String(cell.dataset.cell).split(':')
    const rowIndex = Number(rowText)
    if (!Number.isInteger(rowIndex) || !NAV_KEYS.includes(colKey)) return

    // In an RTL layout the grid runs right to left, so ArrowLeft advances.
    const isSideways = key === 'ArrowLeft' || key === 'ArrowRight'
    const delta = key === 'ArrowRight' ? (isRTL.value ? -1 : 1) : (isRTL.value ? 1 : -1)

    // A dropdown opens as soon as it takes focus, and it owns the keys it walks
    // its options with — up, down and Enter. Left and right are not among them,
    // so they stay with the grid: without that, arrowing into a warehouse cell
    // would trap the operator there with no way out but the mouse.
    const select = target.closest?.('.v-select')
    if (select?.classList.contains('vs--open') && !isSideways) return

    if (isSideways) {
        if (!caretAllowsMove(target, delta)) return

        event.preventDefault()
        moveColumn(rowIndex, colKey, delta)
        return
    }

    if (key === 'Enter' || key === 'ArrowDown') {
        event.preventDefault()
        moveRow(rowIndex, colKey, 1)
        return
    }

    event.preventDefault()
    moveRow(rowIndex, colKey, -1)
}

/* ------------------------------------------------------------------ *
 * Submit
 * ------------------------------------------------------------------ */

// Which grid row each submitted row came from, so a server error on `items.2`
// can be put back in front of the operator.
let submittedRowIndexes = []

const normalize = () => {
    submittedRowIndexes = []

    // The rows are built fresh rather than written back over form.items: the old
    // code normalised in place and dropped the skipped rows out of the grid, so
    // a validation error came back pointing at rows that were no longer there.
    return form.items.reduce((rows, row, gridIndex) => {
        if (!isCountedRow(row)) return rows

        submittedRowIndexes.push(gridIndex)
        rows.push({
            item_id: row.item_id,
            quantity: Number(row.quantity),
            cost: row.cost === '' || row.cost === null ? null : Number(row.cost),
            batch: row.batch || null,
            expire_date: row.expire_date || null,
            unit_measure_id: row.unit_measure_id ?? null,
            warehouse_id: row.warehouse_id ?? null,
        })

        return rows
    }, [])
}

// Both sounds are the operator's own: they follow preferences → notifications →
// sound, use whichever file they picked there, and stay silent when they have
// switched that slot off.
const notifySound = (type) => play(type === 'success' ? 'success' : 'warning')

const confirmOpen = ref(false)

const focusFirstError = (errors) => {
    const firstKey = Object.keys(errors)[0]
    if (!firstKey) return

    const match = firstKey.match(/^items\.(\d+)\.(.+)$/)
    if (!match) return

    const rowIndex = Number(match[1])

    nextTick(() => {
        if (!focusCell(rowIndex, match[2])) {
            focusCell(rowIndex, NAV_KEYS[0])
        }
    })
}

const handleSubmit = () => {
    form.clearErrors()
    serverErrors.value = {}

    const rows = normalize()

    if (!rows.length) {
        notifySound('error')
        toast.error(t('item.no_opening_to_save'), {
            class: 'bg-red-600',
            description: t('item.no_opening_to_save'),
            title: t('general.error'),
        })
        return
    }

    const problems = validateRows()
    if (problems.length) {
        notifySound('error')
        toast.error(t('item.fix_errors'), {
            class: 'bg-red-600',
            description: problems.slice(0, 4).join('\n'),
            title: t('general.error'),
        })

        focusFirstError(clientErrors.value)
        return
    }

    confirmOpen.value = true
}

const submit = () => {
    confirmOpen.value = false

    const rows = normalize()
    const itemCount = rows.length

    form.transform(() => ({ items: rows }))

    form.post(route('fast-opening.store'), {
        preserveScroll: true,
        // The grid saves in place, so the full-screen navigation loader would
        // only hide the rows being saved. The button shows the progress instead.
        headers: { 'X-Silent-Loader': '1' },
        onSuccess: () => {
            notifySound('success')
            toast.success(t('general.create_success', { name: t('item.opening') }), {
                class: 'bg-green-600',
                description: itemCount + ' ' + t('item.items') + ' ' + t('general.created_successfully'),
                title: t('general.success'),
            })

            // The controller redirects back to a freshly queried list — the posted
            // items have dropped out of it — so the grid is rebuilt from the new
            // props instead of the old window.location.reload().
            form.items = buildRows()
            form.clearErrors()
            clientErrors.value = {}
            serverErrors.value = {}
        },
        onError: (errors) => {
            const remapped = {}

            Object.entries(errors).forEach(([key, message]) => {
                const match = key.match(/^items\.(\d+)\.(.+)$/)
                if (!match) {
                    remapped[key] = message
                    return
                }

                const gridIndex = submittedRowIndexes[Number(match[1])] ?? Number(match[1])
                remapped[`items.${gridIndex}.${match[2]}`] = message
            })

            serverErrors.value = remapped

            notifySound('error')
            toast.error(t('item.fix_errors'), {
                class: 'bg-red-600',
                description: Object.values(remapped).slice(0, 4).join('\n') || t('general.create_error_message'),
                title: t('general.error'),
            })

            focusFirstError(remapped)
        },
    })
}

// A page change would throw away everything typed into the grid, so it is
// rebuilt from whatever the server just sent.
watch(paginatedItems, () => {
    form.items = buildRows()
    clientErrors.value = {}
    serverErrors.value = {}
})

const handleWarehouseChange = (rowIndex, value) => {
    form.items[rowIndex].warehouse_id = value
}

const goToItemList = () => {
    router.visit(route('items.index'))
}

const pageOptions = [10, 15, 20, 50, 100]
const perPageOptions = pageOptions.map(value => ({ id: value, name: String(value) }))
const serverPerPage = computed(() => Number(props.items?.meta?.per_page ?? props.items?.per_page ?? pageOptions[0]))
const itemsPerPage = ref(serverPerPage.value)

watch(serverPerPage, (value) => {
    itemsPerPage.value = Number(value)
})

const updateItemsPerPage = (value) => {
    itemsPerPage.value = Number(value)
    router.get(
        route('item.fast.opening'),
        { perPage: itemsPerPage.value },
        {
            preserveScroll: true,
            preserveState: false,
            replace: true,
            only: ['items'],
        }
    )
}

// Guards Inertia navigation and tab close while counts are unsaved.
useFormGuard(form)

/* Ctrl/Cmd+S saves, same as Sales. Routed through handleSubmit() so the grid
   validation and the confirm dialog behave exactly like pressing Save. */
const handleGlobalKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.code === 'KeyS' || e.key === 's' || e.key === 'S')) {
        e.preventDefault()
        if (!form.processing) handleSubmit()
    }
}

onMounted(() => window.addEventListener('keydown', handleGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleGlobalKeydown))
</script>

<template>
    <AppLayout :title="t('item.fast_opening')" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="rounded-2xl border bg-card text-card-foreground shadow-sm p-1">
                <div class="p-4 border-b flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ t('item.fast_opening') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ t('item.remove_unwanted_items') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="!showTips"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1.5"
                            @click="showTips = true"
                        >
                            <Info class="size-4 text-primary" />
                            <span>{{ t('item.fast_opening_tips_title') }}</span>
                        </Button>

                        <Button type="button" variant="outline" size="sm" class="h-8 gap-1.5" @click="goToItemList">
                            <ListOrdered class="size-4 text-primary" />
                            <span>{{ t('item.go_to_item_list') }}</span>
                        </Button>

                        <ModuleHelpButton module="fast_opening" toolbar class="shrink-0" />
                    </div>
                </div>

                <div v-if="showTips" class="px-4 pt-3">
                    <Alert>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <Info class="size-4 text-primary" />
                                <AlertTitle class="text-primary">{{ t('item.fast_opening_tips_title') }}</AlertTitle>
                            </div>
                            <button
                                type="button"
                                class="text-xs text-muted-foreground hover:text-foreground"
                                @click="showTips = false"
                            >
                                {{ t('general.close') }}
                            </button>
                        </div>
                        <AlertDescription class="mt-2">
                            <ul class="list-inside list-disc space-y-1 text-xs text-muted-foreground">
                                <li>{{ t('item.fast_opening_tip_scope') }}</li>
                                <li>{{ t('item.fast_opening_tip_once') }}</li>
                                <li>{{ t('item.fast_opening_tip_skip') }}</li>
                                <li>{{ t('item.fast_opening_tip_cost') }}</li>
                                <li>{{ t('item.fast_opening_tip_keyboard') }}</li>
                                <li>{{ t('item.fast_opening_tip_locked') }}</li>
                                <li>{{ t('item.fast_opening_tip_page') }}</li>
                            </ul>
                        </AlertDescription>
                    </Alert>
                </div>

                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <!-- LEFT SIDE (Search) -->
                    <div class="w-72">
                        <NextInput
                            v-model="searchTerm"
                            type="text"
                            :placeholder="t('item.search_item')"
                            :label="t('datatable.search')"
                        />
                    </div>

                    <!-- RIGHT SIDE (Per Page) -->
                    <div class="flex items-center">
                        <div class="w-40">
                            <NextSelect v-model="itemsPerPage"
                                @update:modelValue="updateItemsPerPage"
                                :options="perPageOptions"
                                label-key="name"
                                value-key="id"
                                :searchable="false" :search-fields="[]"
                                :search-options="{}" :show-arrow="false"
                                :floating-text="t('item.total_items_per_page')" >
                             </NextSelect>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!form.items.length"
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    {{ t('item.fast_opening_done') }}
                </div>

                <div
                    v-else
                    ref="gridRef"
                    class="rounded-xl border bg-card shadow-sm overflow-x-auto max-h-[1500px] overflow-y-auto p-3 mt-1 mb-1"
                    @keydown="onGridKeydown"
                >
                    <table class="w-full table-fixed min-w-[1000px]">
                        <thead class="sticky top-0 z-10 bg-muted/40">
                        <tr class="font-semibold text-sm text-white bg-primary">
                            <th class="px-1 py-1 w-8 min-w-8">#</th>
                            <th class="px-1 py-1 w-40">{{ t('item.item') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('item.opening_amount') }}</th>
                            <th class="px-1 py-1 w-24">{{ t('admin.unit_measure.unit_measure') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('item.batch') }}</th>
                            <th class="px-1 py-1 w-36">{{ t('item.expire_date') }}</th>
                            <th class="px-1 py-1 w-28">{{ t('item.final_cost') }} <span class="text-red-300">*</span></th>
                            <th class="px-1 py-1 w-40">{{ t('admin.warehouse.warehouse') }} <span class="text-red-300">*</span></th>
                            <th class="px-1 py-1 w-16">{{ t('general.actions') }}</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="(rowIndex, displayIndex) in filteredItemIndexes"
                            :key="form.items[rowIndex]._key"
                            class="border-t"
                            :class="rowHasError(rowIndex) ? 'bg-red-500/5' : ''"
                        >
                            <td class="px-1 py-2 align-top text-center text-xs text-muted-foreground">
                                {{ displayIndex + 1 }}
                            </td>

                            <!-- Item Name (readonly) -->
                            <td class="px-1 py-2 align-top">
                                <NextInput label="" v-model="form.items[rowIndex].name" :disabled="true" />
                            </td>

                            <!-- Quantity (editable) -->
                            <td class="px-1 py-2 align-top" :data-cell="`${rowIndex}:quantity`">
                                <NextInput
                                    label=""
                                    v-model="form.items[rowIndex].quantity"
                                    type="number"
                                    inputmode="decimal"
                                    :error="fieldError(rowIndex, 'quantity')"
                                />
                            </td>

                            <!-- Unit Measure -->
                            <td class="px-1 py-2 align-top text-center text-sm">
                                 {{ form.items[rowIndex].measure_name }}
                            </td>

                            <!-- Batch -->
                            <td class="px-1 py-2 align-top" :data-cell="`${rowIndex}:batch`">
                                <NextInput
                                    label=""
                                    v-model="form.items[rowIndex].batch"
                                    type="text"
                                    :readonly="!form.items[rowIndex].is_batch_tracked"
                                    :error="fieldError(rowIndex, 'batch')"
                                />
                            </td>

                            <!-- Expiration Date -->
                            <td class="px-1 py-2 align-top" :data-cell="`${rowIndex}:expire_date`">
                                <!-- The wrapper is what the arrow keys land on. Focusing the
                                     picker's own input is what opens the calendar, and it blurs
                                     itself doing so — so opening is an explicit Enter, and the
                                     grid keeps its place either way. -->
                                <div
                                    v-if="form.items[rowIndex].is_expiry_tracked"
                                    data-grid-focus
                                    tabindex="-1"
                                    class="outline-none"
                                    :title="t('item.expiry_open_hint')"
                                    @keydown.enter.stop.prevent="openDatePicker"
                                >
                                    <NextDate
                                        v-model="form.items[rowIndex].expire_date"
                                        :lock-future-dates="false"
                                        :show-icon="false"
                                        popover="top-left"
                                        :error="fieldError(rowIndex, 'expire_date')"
                                        :placeholder="t('item.expiry_placeholder')"
                                        @change="() => focusCell(rowIndex, 'expire_date')"
                                    />
                                </div>
                                <NextDate
                                    v-else
                                    :model-value="form.items[rowIndex].expire_date"
                                    disabled
                                    :show-icon="false"
                                    :placeholder="t('item.expiry_placeholder')"
                                />
                            </td>

                            <!-- Final cost -->
                            <td class="px-1 py-2 align-top" :data-cell="`${rowIndex}:cost`">
                                <NextInput
                                    label=""
                                    type="number"
                                    v-model="form.items[rowIndex].cost"
                                    inputmode="decimal"
                                    :error="fieldError(rowIndex, 'cost')"
                                />
                            </td>

                            <!-- Warehouse Selection -->
                            <td class="px-1 py-2 align-top" :data-cell="`${rowIndex}:warehouse_id`">
                                <NextSelect
                                    v-model="form.items[rowIndex].warehouse_id"
                                    :options="warehouses"
                                    @update:modelValue="(value) => handleWarehouseChange(rowIndex, value)"
                                    resource-type="warehouses"
                                    :searchable="true"
                                    :search-fields="['name', 'address']"
                                    label-key="name"
                                    value-key="id"
                                    :show-arrow="false"
                                    append-to-body
                                    :error="fieldError(rowIndex, 'warehouse_id')"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="px-1 py-2 align-top text-center">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    class="text-fuchsia-500"
                                    size="sm"
                                    @click="removeRow(rowIndex)"
                                    :disabled="form.items.length === 1"
                                >
                                    −
                                </Button>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <p class="mt-2 text-xs text-muted-foreground">{{ t('item.final_cost_hint') }}</p>
                </div>

                <div class="p-4 border-t flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-muted-foreground">
                        <span>
                            {{ t('general.rows') }}:
                            <span class="font-medium text-foreground">{{ form.items.length }}</span>
                        </span>
                        <span>
                            {{ t('item.counted_rows') }}:
                            <span class="font-medium text-foreground">{{ countedRows.length }}</span>
                        </span>
                        <span>
                            {{ t('item.opening_value') }}:
                            <span class="font-medium text-foreground">{{ formatMoney(openingValue) }}</span>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing || !form.items.length">
                            {{ form.processing ? t('general.saving') : t('general.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </form>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('item.confirm_opening_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('item.confirm_opening_description', {
                            items: countedRows.length,
                            value: formatMoney(openingValue),
                        }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <dl class="grid grid-cols-2 gap-3 rounded-lg border p-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('item.items_to_post') }}</dt>
                        <dd class="font-semibold">{{ countedRows.length }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('item.opening_value') }}</dt>
                        <dd class="font-semibold">{{ formatMoney(openingValue) }}</dd>
                    </div>
                </dl>

                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('general.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="submit">{{ t('general.confirm') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>

<style scoped>
/* Keep dense grid cells from being pushed apart by the inputs' own spacing. */
:deep(.vpd-input-group),
:deep(.vs__dropdown-toggle) {
    height: 2.25rem;
    min-height: 2.25rem;
}

:deep(input) {
    height: 2.25rem;
}

/* The field inside draws its own 2px ring on focus now, so a second outline
   on the cell read as a stray box around the box. */
</style>
