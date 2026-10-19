<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableEmpty,
} from '@/Components/ui/table'
import { Link, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { useSoundPreferences } from '@/composables/useSoundPreferences'
import { useDebounceFn } from '@vueuse/core'
import { formatMoney } from '@/utils/money'
import { Filter, Percent, RotateCcw, Save, TrendingDown, TrendingUp } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const { t, locale } = useI18n()
const { can } = useAuth()
const { play } = useSoundPreferences()

const props = defineProps({
    items: Object,
    filters: Object,
    perPageChoices: { type: Array, default: () => [10, 25, 50, 100, 250] },
    scopeChoices: { type: Array, default: () => ['all', 'category', 'brand'] },
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
})

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
const canEdit = computed(() => can('items.update'))

/* ------------------------------------------------------------------ *
 * Rows
 * ------------------------------------------------------------------ */

const toNumber = (value) => {
    const numeric = Number(value)
    return Number.isFinite(numeric) ? numeric : 0
}

// The cost a margin is measured against: the real average cost once stock has
// moved, otherwise what the item was last bought at.
const baseCost = (row) => toNumber(row.avg_cost) || toNumber(row.purchase_price)

const marginFor = (row, salePrice) => {
    const cost = baseCost(row)
    if (cost <= 0) return null

    return ((toNumber(salePrice) - cost) / cost) * 100
}

const buildRows = (data) => (data ?? []).map((row) => ({
    ...row,
    selected: false,
    // What the operator is typing, kept apart from the saved value so a row can
    // be shown as changed and reverted without another round trip.
    draft_price: row.sale_price === null || row.sale_price === undefined ? '' : String(row.sale_price),
}))

const form = useForm({ prices: [] })
const rows = ref(buildRows(props.items?.data))

watch(() => props.items?.data, (data) => { rows.value = buildRows(data) })

// A cleared box is not a change to zero — it is an unfinished edit, and saving
// it would silently make the item free.
const isChanged = (row) => {
    const draft = String(row.draft_price ?? '').trim()
    if (draft === '') return false

    return toNumber(draft) !== toNumber(row.sale_price)
}

const changedRows = computed(() => rows.value.filter(isChanged))
const selectedRows = computed(() => rows.value.filter((row) => row.selected))

const allSelected = computed(
    () => rows.value.length > 0 && rows.value.every((row) => row.selected),
)

const toggleAll = (checked) => {
    rows.value.forEach((row) => { row.selected = Boolean(checked) })
}

const revertRow = (row) => {
    row.draft_price = row.sale_price === null || row.sale_price === undefined ? '' : String(row.sale_price)
}

const revertAll = () => {
    rows.value.forEach(revertRow)
    toast.info(t('item.pricing_reverted'))
}

/* ------------------------------------------------------------------ *
 * Bulk pricing tools
 * ------------------------------------------------------------------ */

const bulkMode = ref('percent')
const bulkValue = ref('')
const bulkDirection = ref('increase')

const bulkModes = computed(() => [
    { id: 'percent', name: t('item.pricing_bulk_percent') },
    { id: 'margin', name: t('item.pricing_bulk_margin') },
    { id: 'amount', name: t('item.pricing_bulk_amount') },
    { id: 'round', name: t('item.pricing_bulk_round') },
])

// Only a relative change has a direction. Setting a margin or rounding to a step
// lands on an absolute figure, so up/down would mean nothing there.
const supportsDirection = computed(() => ['percent', 'amount'].includes(bulkMode.value))

// Discounting used to mean knowing to type "-10", which nobody should have to
// guess. The value is read as a magnitude and the toggle decides the sign, so a
// typed minus cannot cancel out a chosen "Decrease".
const signedBulkValue = computed(() => {
    const magnitude = Math.abs(Number(bulkValue.value))
    if (!Number.isFinite(magnitude)) return NaN

    return bulkDirection.value === 'decrease' ? -magnitude : magnitude
})

// Rounding to the nearest 5 or 10 is how shelf prices actually get set, and
// doing it by hand across a shelf is where mistakes come from.
const applyBulk = () => {
    const targets = selectedRows.value.length ? selectedRows.value : []

    if (!targets.length) {
        play('warning')
        toast.error(t('item.pricing_select_rows_first'))
        return
    }

    const raw = Number(bulkValue.value)
    if (!Number.isFinite(raw) || String(bulkValue.value).trim() === '') {
        play('warning')
        toast.error(t('item.pricing_enter_a_value'))
        return
    }

    // A relative change takes its sign from the direction toggle; the rest read
    // the figure as typed.
    const value = supportsDirection.value ? signedBulkValue.value : Math.abs(raw)

    let skipped = 0

    targets.forEach((row) => {
        const current = toNumber(row.draft_price || row.sale_price)
        const cost = baseCost(row)
        let next = current

        if (bulkMode.value === 'percent') {
            next = current * (1 + value / 100)
        } else if (bulkMode.value === 'amount') {
            next = current + value
        } else if (bulkMode.value === 'round') {
            // A step of zero would divide by nothing and blank the price.
            next = value > 0 ? Math.round(current / value) * value : current
        } else if (bulkMode.value === 'margin') {
            if (cost <= 0) {
                skipped += 1
                return
            }
            next = cost * (1 + value / 100)
        }

        row.draft_price = String(Math.max(0, Number(next.toFixed(2))))
    })

    if (skipped > 0) {
        // A margin needs something to measure against; an item that has never
        // been bought or counted has no cost yet.
        toast.warning(t('item.pricing_no_cost_skipped', { count: skipped }))
    }
}

/** Typing a margin sets the price, so either column can drive the other. */
const setMargin = (row, value) => {
    const cost = baseCost(row)

    if (cost <= 0) {
        play('warning')
        toast.error(t('item.pricing_no_cost_for_margin'))
        return
    }

    const margin = Number(value)
    if (!Number.isFinite(margin)) return

    row.draft_price = String(Math.max(0, Number((cost * (1 + margin / 100)).toFixed(2))))
}

/* ------------------------------------------------------------------ *
 * Search & paging
 * ------------------------------------------------------------------ */

const search = ref(props.filters?.search ?? '')
const perPage = ref(Number(props.filters?.perPage ?? 10))

const perPageOptions = computed(() =>
    props.perPageChoices.map((value) => ({ id: value, name: String(value) })),
)

/* ------------------------------------------------------------------ *
 * Scope filter — reprice a whole category or brand
 * ------------------------------------------------------------------ */

// The same shape a discount rule uses to say what it covers, because the
// question is the same one: which slice of the catalogue are we touching?
const scope = ref(props.filters?.scope ?? 'all')
const scopeId = ref(props.filters?.scope_id ?? null)

const scopeLabels = computed(() => ({
    all: t('item.pricing_scope_all'),
    category: t('admin.category.category'),
    brand: t('admin.brand.brand'),
}))

const scopeOptions = computed(() =>
    props.scopeChoices.map((id) => ({ id, name: scopeLabels.value[id] ?? id })),
)

const scopeTargets = computed(() => ({
    // Categories carry both labels; the picker prints the one for the locale.
    category: (props.categories || []).map((c) => ({ id: c.id, name: c.localized_name || c.name })),
    brand: (props.brands || []).map((b) => ({ id: b.id, name: b.name })),
}[scope.value] || []))

const needsScopeTarget = computed(() => scope.value !== 'all')

const scopeTargetLabel = computed(() => scopeLabels.value[scope.value] ?? t('general.name'))

// Switching to "all items" drops a target that no longer means anything.
watch(scope, () => { if (! needsScopeTarget.value) scopeId.value = null })

/** True while the grid is being refilled by an explicit Apply. */
const pendingScopeSelectAll = ref(false)

const reload = (overrides = {}, options = {}) => {
    router.get(route('item-pricing.index'), {
        search: search.value || undefined,
        perPage: perPage.value,
        scope: scope.value,
        scope_id: needsScopeTarget.value ? (scopeId.value || undefined) : undefined,
        ...overrides,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // Searching and paging refresh this grid in place — the operator never
        // leaves the page — so the full-screen navigation loader would just
        // blink over the rows they are reading.
        headers: { 'X-Silent-Loader': '1' },
        ...options,
    })
}

const applyScope = () => {
    if (needsScopeTarget.value && ! scopeId.value) {
        play('warning')
        toast.error(t('item.pricing_pick_a_target'))
        return
    }

    if (changedRows.value.length && !window.confirm(t('general.unsaved_changes_warning'))) {
        return
    }

    // Asking for a category and then having to tick every row of it is the
    // work the filter was meant to remove; the rows arrive ticked and the
    // operator unticks the few they want left alone.
    pendingScopeSelectAll.value = true

    // A request that never lands must not leave the flag armed, or the next
    // search would tick rows nobody asked for.
    reload({ page: 1 }, { onError: () => { pendingScopeSelectAll.value = false } })
}

const clearScope = () => {
    scope.value = 'all'
    scopeId.value = null
    reload({ page: 1 })
}

// Rows are rebuilt from the server payload, so the tick has to be re-applied
// once the replacement rows exist rather than on the click.
watch(rows, () => {
    if (! pendingScopeSelectAll.value) return

    pendingScopeSelectAll.value = false
    toggleAll(true)
})

const scopeIsFiltered = computed(() => needsScopeTarget.value && Boolean(props.filters?.scope_id))

const doSearch = useDebounceFn(() => {
    // Reloading would throw away anything half-typed, so ask first.
    if (changedRows.value.length && !window.confirm(t('general.unsaved_changes_warning'))) {
        return
    }
    reload()
}, 400)

watch(search, doSearch)

const changePerPage = (value) => {
    perPage.value = Number(value)
    reload()
}

/* ------------------------------------------------------------------ *
 * Keyboard navigation
 * ------------------------------------------------------------------ */

const NAV_KEYS = ['draft_price', 'margin']
const gridRef = ref(null)

const focusCell = (rowIndex, colKey) => {
    const cell = gridRef.value?.querySelector(`[data-cell="${rowIndex}:${colKey}"]`)
    const target = cell?.querySelector('input:not([disabled])')
    if (!target) return false

    target.focus()
    try { target.select() } catch (e) { /* number inputs refuse select() */ }
    return true
}

const onGridKeydown = (event) => {
    const key = event.key
    if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Enter'].includes(key)) return
    if (!(event.target instanceof HTMLInputElement)) return

    const cell = event.target.closest('[data-cell]')
    if (!cell) return

    const [rowText, colKey] = String(cell.dataset.cell).split(':')
    const rowIndex = Number(rowText)
    if (!Number.isInteger(rowIndex) || !NAV_KEYS.includes(colKey)) return

    if (key === 'ArrowLeft' || key === 'ArrowRight') {
        // In an RTL layout the grid runs right to left, so ArrowLeft advances.
        const delta = key === 'ArrowRight' ? (isRTL.value ? -1 : 1) : (isRTL.value ? 1 : -1)
        const next = NAV_KEYS.indexOf(colKey) + delta
        if (next < 0 || next >= NAV_KEYS.length) return

        event.preventDefault()
        focusCell(rowIndex, NAV_KEYS[next])
        return
    }

    event.preventDefault()
    focusCell(rowIndex + (key === 'ArrowUp' ? -1 : 1), colKey)
}

/* ------------------------------------------------------------------ *
 * Save
 * ------------------------------------------------------------------ */

const confirmOpen = ref(false)

const handleSave = () => {
    if (!changedRows.value.length) {
        play('warning')
        toast.error(t('item.pricing_nothing_changed'))
        return
    }

    confirmOpen.value = true
}

const submit = () => {
    confirmOpen.value = false

    const payload = changedRows.value.map((row) => ({
        variant_id: row.id,
        sale_price: toNumber(row.draft_price),
    }))

    form.transform(() => ({ prices: payload }))

    form.patch(route('item-pricing.bulk-update'), {
        preserveScroll: true,
        preserveState: true,
        // The grid saves in place, so the full-screen navigation loader would
        // only hide the rows being saved.
        headers: { 'X-Silent-Loader': '1' },
        onSuccess: () => {
            play('success')
            toast.success(t('item.pricing_saved', { count: payload.length }), {
                class: 'bg-green-600',
            })
            // The reload brings back the saved values; drafts rebuild from them.
            reload()
        },
        onError: () => {
            play('warning')
            toast.error(t('general.create_error_message'))
        },
    })
}

/* ------------------------------------------------------------------ *
 * Guards
 * ------------------------------------------------------------------ */

// Leaving with prices typed but unsaved would lose them silently. The shared
// useFormGuard reads an Inertia form's isDirty, and the drafts live outside the
// form here, so the same job is done against changedRows.
const isGuarding = () => changedRows.value.length > 0 && !form.processing

const onBeforeUnload = (event) => {
    if (!isGuarding()) return
    event.preventDefault()
    event.returnValue = t('general.unsaved_changes_warning')
    return event.returnValue
}

let removeInertiaHook = null

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload)

    removeInertiaHook = router.on('before', (event) => {
        if (!isGuarding()) return
        // Only guard real navigations away; the save itself must never prompt.
        if (String(event.detail?.visit?.method ?? 'get').toLowerCase() !== 'get') return
        if (!window.confirm(t('general.unsaved_changes_warning'))) event.preventDefault()
    })

    nextTick(() => focusCell(0, 'draft_price'))
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
    // Without this the listener outlives the page and starts prompting on
    // navigations that have nothing to do with pricing.
    if (typeof removeInertiaHook === 'function') removeInertiaHook()
})

function expiryBadgeDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleDateString()
}
</script>

<template>
    <AppLayout :title="t('sidebar.inventory.pricing')">
        <FormPageToolbar back-route="items.index" module="item_pricing" />

        <!-- Filters & bulk tools ----------------------------------------- -->
        <div class="mb-5 shrink-0 rounded-xl border p-4 shadow-sm border-primary relative">
            <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
                {{ t('sidebar.inventory.pricing') }}
            </div>

            <!-- Which slice of the catalogue to reprice ---------------- -->
            <div class="mt-3 grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2 lg:grid-cols-4 items-start">
                <NextSelect
                    v-model="scope"
                    :options="scopeOptions"
                    label-key="name"
                    value-key="id"
                    :floating-text="t('item.pricing_scope')"
                    :has-add-button="false"
                    :clearable="false"
                />
                <NextSelect
                    v-if="needsScopeTarget"
                    v-model="scopeId"
                    :options="scopeTargets"
                    label-key="name"
                    value-key="id"
                    :floating-text="scopeTargetLabel"
                    :has-add-button="false"
                />
                <div class="flex items-end gap-2">
                    <Button type="button" class="h-10 shrink-0 gap-1.5" @click="applyScope">
                        <Filter class="size-4" />
                        {{ t('item.pricing_apply_filter') }}
                    </Button>
                    <Button
                        v-if="scopeIsFiltered"
                        type="button"
                        variant="ghost"
                        class="h-10 shrink-0"
                        @click="clearScope"
                    >
                        {{ t('general.clear') }}
                    </Button>
                </div>
                <p class="self-center text-xs text-muted-foreground sm:col-span-2 lg:col-span-1">
                    {{ t('item.pricing_scope_hint') }}
                </p>
            </div>

            <div class="grid grid-cols-1 gap-x-4 gap-y-5 mt-5 border-t pt-5 sm:grid-cols-2 lg:grid-cols-4 items-start">
                <NextInput
                    :label="t('datatable.search')"
                    v-model="search"
                    :placeholder="t('item.pricing_search_placeholder')"
                    :hint="t('item.pricing_search_hint')"
                />
                <NextSelect
                    :model-value="perPage"
                    @update:modelValue="changePerPage"
                    :options="perPageOptions"
                    label-key="name"
                    value-key="id"
                    :floating-text="t('item.total_items_per_page')"
                    :has-add-button="false"
                    :clearable="false"
                />
                <NextSelect
                    v-model="bulkMode"
                    :options="bulkModes"
                    label-key="name"
                    value-key="id"
                    :floating-text="t('item.pricing_bulk_action')"
                    :has-add-button="false"
                    :clearable="false"
                />
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <NextInput
                            :label="t('item.pricing_bulk_value')"
                            type="number"
                            inputmode="decimal"
                            v-model="bulkValue"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        class="h-10 shrink-0 gap-1.5"
                        :disabled="!canEdit"
                        @click="applyBulk"
                    >
                        <Percent class="size-4" />
                        {{ t('item.pricing_apply') }}
                    </Button>
                </div>
            </div>

            <!-- Raising and lowering are equally common, so neither is hidden
                 behind knowing to type a minus sign. -->
            <div v-if="supportsDirection" class="mt-4 flex flex-wrap items-center gap-3">
                <span class="text-sm text-muted-foreground">{{ t('item.pricing_direction') }}</span>
                <div class="inline-flex overflow-hidden rounded-md border">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm transition-colors"
                        :class="bulkDirection === 'increase'
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-background hover:bg-muted'"
                        @click="bulkDirection = 'increase'"
                    >
                        <TrendingUp class="size-4" />
                        {{ t('item.pricing_increase') }}
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 border-s px-3 py-1.5 text-sm transition-colors"
                        :class="bulkDirection === 'decrease'
                            ? 'bg-destructive text-destructive-foreground'
                            : 'bg-background hover:bg-muted'"
                        @click="bulkDirection = 'decrease'"
                    >
                        <TrendingDown class="size-4" />
                        {{ t('item.pricing_decrease') }}
                    </button>
                </div>
                <span class="text-xs text-muted-foreground">{{ t('item.pricing_direction_hint') }}</span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p class="text-xs text-muted-foreground">{{ t('item.pricing_bulk_hint') }}</p>

                <div class="flex items-center gap-2">
                    <span v-if="changedRows.length" class="text-sm font-medium text-amber-500">
                        {{ t('item.pricing_changed_count', { count: changedRows.length }) }}
                    </span>
                    <Button
                        v-if="changedRows.length"
                        type="button"
                        variant="ghost"
                        class="h-9 gap-1.5"
                        @click="revertAll"
                    >
                        <RotateCcw class="size-4" />
                        {{ t('item.pricing_revert') }}
                    </Button>
                    <Button
                        type="button"
                        class="h-9 gap-1.5"
                        :disabled="!canEdit || form.processing || !changedRows.length"
                        @click="handleSave"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? t('general.saving') : t('general.save') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Grid --------------------------------------------------------- -->
        <!-- shrink-0 matters: `overflow-x-auto` drops this box's automatic
             minimum height to zero, so as a flex child of the layout's scroll
             region a short viewport crushed it down to the header row and the
             rows had nowhere to go. The page itself scrolls instead. -->
        <div ref="gridRef" class="mb-5 shrink-0 overflow-x-auto rounded-xl border" @keydown="onGridKeydown">
            <Table>
                <TableHeader>
                    <TableRow class="border-border bg-primary hover:bg-primary h-9">
                        <TableHead v-if="canEdit" class="w-12 text-white">
                            <!-- The default checkbox borders and fills itself in
                                 `primary`, which on a primary header row is the
                                 same colour as the header: invisible. -->
                            <Checkbox
                                :checked="allSelected"
                                :aria-label="t('item.pricing_select_all')"
                                :title="t('item.pricing_select_all')"
                                class="size-5 border-2 border-white bg-white/20 text-primary data-[state=checked]:bg-white data-[state=checked]:text-primary"
                                @update:checked="toggleAll"
                            />
                        </TableHead>
                        <TableHead class="w-10 text-white">#</TableHead>
                        <TableHead class="text-white">{{ t('general.name') }}</TableHead>
                        <TableHead class="text-white">{{ t('item.variant') }}</TableHead>
                        <TableHead class="text-white">{{ t('item.code') }}</TableHead>
                        <TableHead class="text-white text-end">{{ t('general.on_hand') }}</TableHead>
                        <TableHead class="text-white text-end">{{ t('item.cost') }}</TableHead>
                        <TableHead class="text-white text-end">{{ t('item.purchase_price') }}</TableHead>
                        <TableHead class="w-32 text-white">{{ t('item.sale_price') }}</TableHead>
                        <TableHead class="w-28 text-white">{{ t('item.margin_percentage') }}</TableHead>
                        <TableHead class="text-white">{{ t('item.expiry_status') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableEmpty v-if="!rows.length" :colspan="canEdit ? 11 : 10">
                        {{ t('general.no_data_found') }}
                    </TableEmpty>

                    <TableRow
                        v-for="(row, index) in rows"
                        :key="row.id"
                        :class="isChanged(row) ? 'bg-amber-500/10' : ''"
                    >
                        <TableCell v-if="canEdit">
                            <Checkbox
                                :checked="row.selected"
                                :aria-label="row.name"
                                class="size-5 border-2"
                                @update:checked="(v) => (row.selected = Boolean(v))"
                            />
                        </TableCell>

                        <TableCell class="text-xs text-muted-foreground">
                            {{ (items?.from ?? 1) + index }}
                        </TableCell>

                        <TableCell class="font-medium">{{ row.name }}</TableCell>

                        <TableCell class="text-muted-foreground">{{ row.variant_label || '—' }}</TableCell>

                        <TableCell class="text-muted-foreground">{{ row.code || '-' }}</TableCell>

                        <TableCell class="text-end tabular-nums">{{ formatMoney(row.on_hand) }}</TableCell>

                        <TableCell class="text-end tabular-nums text-muted-foreground">
                            {{ formatMoney(row.avg_cost) }}
                        </TableCell>

                        <TableCell class="text-end tabular-nums text-muted-foreground">
                            {{ formatMoney(row.purchase_price) }}
                        </TableCell>

                        <!-- Sale price -->
                        <TableCell :data-cell="`${index}:draft_price`">
                            <NextInput
                                label=""
                                type="number"
                                inputmode="decimal"
                                v-model="row.draft_price"
                                :disabled="!canEdit"
                            />
                            <p v-if="isChanged(row)" class="mt-1 text-[11px] text-muted-foreground">
                                {{ formatMoney(row.sale_price) }} →
                                <span class="font-medium text-amber-500">{{ formatMoney(row.draft_price) }}</span>
                            </p>
                        </TableCell>

                        <!-- Margin -->
                        <TableCell :data-cell="`${index}:margin`">
                            <NextInput
                                label=""
                                type="number"
                                inputmode="decimal"
                                :model-value="marginFor(row, row.draft_price) === null ? '' : marginFor(row, row.draft_price).toFixed(1)"
                                :disabled="!canEdit || baseCost(row) <= 0"
                                @update:modelValue="(value) => setMargin(row, value)"
                            />
                            <p
                                v-if="baseCost(row) > 0 && toNumber(row.draft_price) < baseCost(row)"
                                class="mt-1 text-[11px] font-medium text-destructive"
                            >
                                {{ t('item.pricing_below_cost') }}
                            </p>
                        </TableCell>

                        <TableCell>
                            <template v-if="!row.is_expiry_tracked">
                                <span class="text-xs text-muted-foreground">-</span>
                            </template>
                            <template v-else-if="row.expiry_status === 'expired'">
                                <Badge variant="destructive">{{ t('item.expired') }}</Badge>
                                <div class="mt-0.5 text-xs text-muted-foreground">{{ expiryBadgeDate(row.earliest_expiry) }}</div>
                            </template>
                            <template v-else-if="row.expiry_status === 'expiring_soon'">
                                <Badge class="border-amber-300 bg-amber-100 text-amber-800 hover:bg-amber-100">
                                    {{ t('item.expiring_soon') }}
                                </Badge>
                                <div class="mt-0.5 text-xs text-muted-foreground">{{ expiryBadgeDate(row.earliest_expiry) }}</div>
                            </template>
                            <template v-else-if="row.expiry_status === 'ok'">
                                <Badge variant="secondary">{{ expiryBadgeDate(row.earliest_expiry) }}</Badge>
                            </template>
                            <template v-else>
                                <span class="text-xs text-muted-foreground">-</span>
                            </template>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination --------------------------------------------------- -->
        <div v-if="items?.last_page > 1" class="mb-6 flex shrink-0 flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
            <span>{{ t('item.showing_results', { from: items.from, to: items.to, total: items.total }) }}</span>
            <div class="flex flex-wrap gap-1">
                <Link
                    v-for="link in items.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'rounded-md border px-3 py-1 text-sm transition-colors',
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-input bg-background hover:bg-muted',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    preserve-scroll
                    :headers="{ 'X-Silent-Loader': '1' }"
                    v-html="link.label"
                />
            </div>
        </div>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('item.pricing_confirm_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('item.pricing_confirm_description', { count: changedRows.length }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div class="max-h-60 overflow-y-auto rounded-lg border text-sm">
                    <div
                        v-for="row in changedRows"
                        :key="row.id"
                        class="flex items-center justify-between gap-3 border-b px-3 py-2 last:border-b-0"
                    >
                        <span class="truncate">
                            {{ row.name }}
                            <span v-if="row.variant_label" class="text-muted-foreground">· {{ row.variant_label }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums">
                            <span class="text-muted-foreground">{{ formatMoney(row.sale_price) }}</span>
                            →
                            <span class="font-semibold">{{ formatMoney(row.draft_price) }}</span>
                        </span>
                    </div>
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('general.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="submit">{{ t('general.confirm') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>

<style scoped>
/* Keep the dense grid from being pushed apart by the inputs' own spacing. */
:deep(input) {
    height: 2.25rem;
}

td[data-cell]:focus-within {
    outline: 2px solid hsl(var(--primary) / 0.35);
    outline-offset: -2px;
    border-radius: calc(var(--radius) - 2px);
}
</style>
