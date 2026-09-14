<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { Checkbox } from '@/Components/ui/checkbox'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
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
import { useBusinessProfile } from '@/composables/useBusinessProfile'
import { useFormGuard } from '@/composables/useFormGuard'
import { formatMoney } from '@/utils/money'
import { Info, ListOrdered, Settings2 } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

import { useI18n } from 'vue-i18n'
const { t, locale } = useI18n()
import { useSidebar } from '@/Components/ui/sidebar/utils'
import { ref, reactive, watch, onMounted, onUnmounted, computed, nextTick, toRef } from 'vue'

const props = defineProps({
    warehouses: { type: [Array, Object], default: () => [] },
    unitMeasures: { type: [Array, Object], default: () => [] },
    categories: { type: [Array, Object], default: () => [] },
    brands: { type: [Array, Object], default: () => [] },
    maxCode: { type: Number, required: true },
    // Resolved fresh server-side from the company's business type, so a
    // just-changed trade decides the batch/expiry columns without waiting for
    // the cached `business_profile` shared prop to expire.
    businessProfile: { type: Object, default: null },
})

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

const warehouses   = computed(() => props.warehouses?.data ?? props.warehouses ?? [])
const unitMeasures = computed(() => props.unitMeasures?.data ?? props.unitMeasures ?? [])
const categories   = computed(() => props.categories?.data ?? props.categories ?? [])

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))


/* ------------------------------------------------------------------ *
 * Which columns this trade shows
 * ------------------------------------------------------------------ */

const { defaults: profileDefaults } = useBusinessProfile(toRef(props, 'businessProfile'))

// The full item form shows the batch / expiry inputs when the item's tracking
// flags are on, and those flags start from the trade's defaults. Fast entry has
// no per-item flags, so the trade's defaults decide the columns directly — a
// pharmacy gets both, a clothing shop gets neither. Column settings then layer
// on top for the rows that need an exception.
const COLUMN_SETTINGS_KEY = 'nextbook.fast_entry.columns'

const columnSettings = reactive({
    category_id: true,
    quantity: true,
    batch: Boolean(profileDefaults.value?.is_batch_tracked),
    expire_date: Boolean(profileDefaults.value?.is_expiry_tracked),
    warehouse_id: true,
    purchase_price: true,
    sale_price: true,
    cost: true,
})

// Remembered in this browser only — a column layout is a per-operator habit,
// not company data, so it never reaches the database.
onMounted(() => {
    try {
        const stored = JSON.parse(window.localStorage.getItem(COLUMN_SETTINGS_KEY) || 'null')
        if (stored && typeof stored === 'object') {
            Object.keys(columnSettings).forEach((key) => {
                if (typeof stored[key] === 'boolean') columnSettings[key] = stored[key]
            })
        }
    } catch (e) {
        // A blocked or cleared storage just means the defaults above stand.
    }
})

watch(columnSettings, (value) => {
    try {
        window.localStorage.setItem(COLUMN_SETTINGS_KEY, JSON.stringify({ ...value }))
    } catch (e) {
        // Ignore — hiding a column must never fail the screen.
    }
}, { deep: true })

// The tips are worth the space the first few times and in the way after that,
// so dismissing them sticks — and the header keeps a button to bring them back.
const TIPS_KEY = 'nextbook.fast_entry.tips_dismissed'
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

// Order matters: it is the order of the grid and of arrow-key navigation.
const columns = computed(() => [
    { key: 'name',           label: t('general.name'),                        width: 'w-40',  required: true,  optional: false },
    { key: 'code',           label: t('item.code'),                           width: 'w-20',  required: false, optional: false },
    { key: 'measure_id',     label: t('admin.unit_measure.unit_measure'),     width: 'w-28',  required: true,  optional: false },
    { key: 'category_id',    label: t('admin.category.category'),             width: 'w-32',  required: false, optional: true },
    { key: 'quantity',       label: t('item.opening_amount'),                 width: 'w-24',  required: false, optional: true },
    // The batch column keeps its own name. Borrowing the trade's lot name made
    // it read "Expiry" for a supermarket — the same header as the column next
    // to it, which is the one that actually holds the date.
    { key: 'batch',          label: t('item.batch'),                          width: 'w-24',  required: false, optional: true },
    { key: 'expire_date',    label: t('item.expire_date'),                    width: 'w-32',  required: false, optional: true },
    { key: 'warehouse_id',   label: t('admin.warehouse.warehouse'),           width: 'w-32',  required: false, optional: true },
    { key: 'purchase_price', label: t('item.purchase_price'),                 width: 'w-24',  required: false, optional: true },
    { key: 'sale_price',     label: t('item.sale_price'),                     width: 'w-24',  required: false, optional: true },
    { key: 'cost',           label: t('item.final_cost'),                     width: 'w-24',  required: false, optional: true },
])

const visibleColumns = computed(() => columns.value.filter((c) => !c.optional || columnSettings[c.key]))
const visibleKeys = computed(() => visibleColumns.value.map((c) => c.key))
const optionalColumns = computed(() => columns.value.filter((c) => c.optional))

/* ------------------------------------------------------------------ *
 * Rows
 * ------------------------------------------------------------------ */

const currentMaxCode = ref(Number(props.maxCode))
let rowKeySeed = 0

// Format code with leading zeros based on the number
const formatCode = (number) => {
    const num = Number(number)
    if (num < 10) {
        return num.toString().padStart(3, '0') // 001-009
    } else if (num < 100) {
        return num.toString().padStart(3, '0') // 010-099
    } else {
        return num.toString() // 100+
    }
}

const generateBarcode = () => {
    const random = Math.floor(100000000 + Math.random() * 900000000)
    return `ITM${random}`
}

const blankRow = (code = currentMaxCode.value) => ({
    _key: ++rowKeySeed,
    name: '',
    code: formatCode(code),
    barcode: '',
    measure_id: null,
    category_id: null,
    purchase_price: '',
    sale_price: '',
    cost: '',
    batch: '',
    expire_date: '',
    quantity: '',
    warehouse_id: null,
})

const buildRows = (count = 6) =>
    Array.from({ length: count }, (_, idx) => blankRow(currentMaxCode.value + idx))

const form = useForm({ items: buildRows() })

// Codes are a running sequence, so they are re-derived whenever the row count
// changes rather than patched row by row.
const renumberCodes = () => {
    form.items.forEach((row, idx) => {
        row.code = formatCode(currentMaxCode.value + idx)
    })
}

const appendRow = () => {
    form.items.push(blankRow())
    renumberCodes()
}

const addRow = () => {
    appendRow()
    nextTick(() => focusCell(form.items.length - 1, visibleKeys.value[0]))
}

const removeRow = (idx) => {
    if (form.items.length <= 1) return
    form.items.splice(idx, 1)
    renumberCodes()
}

// Check if a row is effectively empty (ignoring the auto code and barcode)
const VALUE_KEYS = [
    'name',
    'measure_id',
    'category_id',
    'quantity',
    'batch',
    'expire_date',
    'warehouse_id',
    'purchase_price',
    'sale_price',
    'cost',
]

const isEmptyRow = (row) =>
    !VALUE_KEYS.some((key) => {
        const value = row[key]
        return value !== '' && value !== null && value !== undefined
    })

const filledRows = computed(() => form.items.filter((row) => !isEmptyRow(row)))

/* ------------------------------------------------------------------ *
 * Defaults that save keystrokes
 * ------------------------------------------------------------------ */

// "دانه" (each) is the unit almost every row uses, so it is pre-selected the
// moment a name is typed. Falls back to its symbol, then the main unit.
const defaultMeasureId = computed(() => {
    const list = unitMeasures.value || []
    const match =
        list.find((m) => String(m?.name ?? '').trim() === 'دانه') ||
        list.find((m) => String(m?.symbol ?? '').toLowerCase() === 'ea') ||
        list.find((m) => m?.is_main === true) ||
        list[0]
    return match?.id ?? null
})

const defaultWarehouseId = computed(() => {
    const list = warehouses.value || []
    const match = list.find((w) => w?.is_main === true) || list[0]
    return match?.id ?? null
})

watch(
    () => form.items.map((item) => String(item.name ?? '').trim()),
    (names) => {
        names.forEach((name, idx) => {
            const item = form.items[idx]
            if (!item) return

            if (name) {
                if (!item.barcode) item.barcode = generateBarcode()
                if (!item.measure_id) item.measure_id = defaultMeasureId.value
            } else {
                item.barcode = ''
            }
        })
    },
    { immediate: true }
)

watch(
    () => form.items.map((item) => item.quantity),
    (quantities) => {
        quantities.forEach((quantity, idx) => {
            const item = form.items[idx]
            if (!item) return

            const amount = Number(quantity)
            if (Number.isFinite(amount) && amount > 0 && !item.warehouse_id) {
                item.warehouse_id = defaultWarehouseId.value
            }
        })
    }
)

// Typing anywhere in the last row grows the grid, so the operator never has to
// stop and press "Add row".
watch(
    () => (form.items.length ? isEmptyRow(form.items[form.items.length - 1]) : true),
    (lastIsEmpty) => {
        if (!lastIsEmpty) appendRow()
    }
)

/* ------------------------------------------------------------------ *
 * Validation
 * ------------------------------------------------------------------ */

// Client-side checks run before the confirmation dialog so the operator is not
// asked to confirm a save that the server will reject.
const clientErrors = ref({})

// Server errors arrive keyed by their position in the *submitted* array, which
// skips the empty rows — `items.0` can be grid row 3. They are re-keyed to grid
// rows on the way in so the message lands in the cell the operator typed.
const serverErrors = ref({})

const fieldError = (idx, field) =>
    clientErrors.value[`items.${idx}.${field}`] || serverErrors.value[`items.${idx}.${field}`]

const rowHasError = (idx) =>
    [...VALUE_KEYS, 'code', 'barcode'].some((key) => Boolean(fieldError(idx, key)))

const validateRows = () => {
    const errors = {}
    const problems = []
    const seenNames = new Map()

    form.items.forEach((row, idx) => {
        if (isEmptyRow(row)) return

        const rowNumber = idx + 1
        const name = String(row.name ?? '').trim()
        const quantity = Number(row.quantity)

        if (!name) {
            errors[`items.${idx}.name`] = t('general.required_field')
            problems.push(t('item.name_required_in_row', { row: rowNumber }))
        } else {
            const key = name.toLowerCase()
            if (seenNames.has(key)) {
                errors[`items.${idx}.name`] = t('item.duplicate_name_in_row', { row: rowNumber })
                problems.push(t('item.duplicate_name_in_row', { row: rowNumber }))
            } else {
                seenNames.set(key, idx)
            }
        }

        if (!row.measure_id) {
            errors[`items.${idx}.measure_id`] = t('general.required_field')
            problems.push(t('item.measure_required_in_row', { row: rowNumber }))
        }

        if (Number.isFinite(quantity) && quantity > 0 && !row.warehouse_id) {
            errors[`items.${idx}.warehouse_id`] = t('general.required_field')
            problems.push(t('item.warehouse_required_in_row', { row: rowNumber }))
        }

        const hasLot = String(row.batch ?? '').trim() !== '' || String(row.expire_date ?? '').trim() !== ''
        if (hasLot && !(Number.isFinite(quantity) && quantity > 0)) {
            errors[`items.${idx}.quantity`] = t('general.required_field')
            problems.push(t('item.quantity_required_in_row', { row: rowNumber }))
        }
    })

    clientErrors.value = errors

    return problems
}

/* ------------------------------------------------------------------ *
 * Keyboard navigation — the grid behaves like a spreadsheet
 * ------------------------------------------------------------------ */

const gridRef = ref(null)

/** @returns {boolean} whether the cell had anything to put the caret in. */
const focusCell = (rowIndex, colKey) => {
    if (rowIndex < 0 || rowIndex >= form.items.length) return false
    if (!colKey) return false

    const cell = gridRef.value?.querySelector(`[data-cell="${rowIndex}:${colKey}"]`)
    if (!cell) return false

    // The date cell hands focus to its wrapper, never to the picker's own input:
    // focusing that input is what opens the calendar, and the picker blurs itself
    // on the way, which would drop the operator out of the grid entirely.
    const target = cell.querySelector('[data-grid-focus]')
        || cell.querySelector('input:not([disabled]), .vs__search')
    if (!target) return false

    target.focus()
    if (typeof target.select === 'function') {
        try { target.select() } catch (e) { /* number inputs refuse select() in some browsers */ }
    }

    return true
}

/** Open the calendar for the date cell the operator is standing on. */
const openDatePicker = (event) => {
    const input = event.currentTarget?.querySelector('input')
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

const moveColumn = (rowIndex, colKey, delta) => {
    const keys = visibleKeys.value
    let next = keys.indexOf(colKey) + delta

    // The code cell is generated and disabled, so it has nothing to focus. Step
    // over it rather than stopping dead — otherwise the very first hop out of
    // the name column goes nowhere and the unit measure is unreachable.
    while (next >= 0 && next < keys.length) {
        if (focusCell(rowIndex, keys[next])) return
        next += delta
    }
}

const moveRow = (rowIndex, colKey, delta) => {
    const next = rowIndex + delta
    if (next < 0) return
    if (next >= form.items.length) {
        appendRow()
        nextTick(() => focusCell(next, colKey))
        return
    }
    focusCell(next, colKey)
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
    if (!Number.isInteger(rowIndex) || !visibleKeys.value.includes(colKey)) return

    // In an RTL layout the grid runs right to left, so ArrowLeft advances.
    const isSideways = key === 'ArrowLeft' || key === 'ArrowRight'
    const delta = key === 'ArrowRight' ? (isRTL.value ? -1 : 1) : (isRTL.value ? 1 : -1)

    // A dropdown opens as soon as it takes focus, and it owns the keys it walks
    // its options with — up, down and Enter. Left and right are not among them,
    // so they stay with the grid: without that, arrowing into any select cell
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

// Return a normalized copy of items without mutating the UI rows
const toNumberOrNull = (value) => (value === '' || value === null || value === undefined ? null : Number(value))

// Which grid row each submitted row came from, so a server error on `items.2`
// can be put back in front of the operator.
let submittedRowIndexes = []

const normalize = () => {
    submittedRowIndexes = []

    return form.items.reduce((rows, row, gridIndex) => {
        if (isEmptyRow(row)) return rows

        const { _key, ...values } = row
        submittedRowIndexes.push(gridIndex)
        rows.push({
            ...values,
            quantity: toNumberOrNull(values.quantity),
            purchase_price: toNumberOrNull(values.purchase_price),
            sale_price: toNumberOrNull(values.sale_price),
            cost: toNumberOrNull(values.cost),
            category_id: values.category_id ?? null,
            measure_id: values.measure_id ?? null,
            warehouse_id: values.warehouse_id ?? null,
            expire_date: values.expire_date || null,
        })

        return rows
    }, [])
}

// The opening layer is costed at the final cost, and falls back to the purchase
// price when it is blank — the same rule the controller applies.
const rowOpeningValue = (row) => {
    const quantity = Number(row.quantity)
    if (!Number.isFinite(quantity) || quantity <= 0) return 0

    const cost = toNumberOrNull(row.cost) ?? toNumberOrNull(row.purchase_price) ?? 0
    return quantity * cost
}

const openingRows = computed(() => filledRows.value.filter((row) => Number(row.quantity) > 0))
const openingValue = computed(() => filledRows.value.reduce((total, row) => total + rowOpeningValue(row), 0))

const notifySound = (type) => {
    const file = type === 'success' ? 'filling-your-inbox' : 'glass-breaking'
    try {
        const sound = new Audio(`/notify_sounds/${file}.mp3`)
        sound.play().catch(() => {})
    } catch (e) {
        // Sound is a nicety; never let it break the save.
    }
}

const confirmOpen = ref(false)

const focusFirstError = (errors) => {
    const firstKey = Object.keys(errors)[0]
    if (!firstKey) return

    const match = firstKey.match(/^items\.(\d+)\.(.+)$/)
    if (!match) return

    const rowIndex = Number(match[1])

    nextTick(() => {
        // A duplicate-code error points at a disabled cell; landing the operator
        // on the right row is still better than not moving at all.
        if (!focusCell(rowIndex, match[2])) {
            focusCell(rowIndex, visibleKeys.value[0])
        }
    })
}

const handleSubmit = () => {
    form.clearErrors()
    serverErrors.value = {}

    const rows = normalize()

    // If everything is empty, there is nothing to submit
    if (!rows.length) {
        notifySound('error')
        toast.error(t('item.no_data_to_save'), {
            class: 'bg-red-600',
            description: t('item.no_data_to_save'),
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

    form.post(route('item.fast.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const highestCode = rows.reduce((max, row) => {
                const codeNum = Number(row.code) || 0
                return codeNum > max ? codeNum : max
            }, currentMaxCode.value)

            currentMaxCode.value = highestCode + 1

            notifySound('success')
            toast.success(itemCount + ' ' + t('item.items') + ' ' + t('general.created_successfully'), {
                class: 'bg-green-600',
                description: itemCount + ' ' + t('item.items') + ' ' + t('general.created_successfully'),
                title: t('general.success'),
            })

            // Rebuild the grid so new rows start after the latest saved code.
            // Inertia re-baselines the form's defaults from this data once the
            // callback returns, so the navigation guard sees a clean grid again
            // — calling form.defaults() here would alias the defaults to the
            // live rows and stop isDirty ever flipping.
            form.items = buildRows()
            form.clearErrors()
            clientErrors.value = {}
            serverErrors.value = {}

            nextTick(() => focusCell(0, visibleKeys.value[0]))
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

const goToItemList = () => {
    router.visit(route('items.index'))
}

// Guards Inertia navigation and tab close while rows are unsaved.
useFormGuard(form)

onMounted(() => {
    nextTick(() => focusCell(0, visibleKeys.value[0]))
})
</script>

<template>
    <AppLayout :title="t('item.fast_entry')" :sidebar-collapsed="true">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="rounded-2xl border bg-card text-card-foreground shadow-sm p-1 border-primary">
                <div class="p-4 border-b flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">{{ t('item.fast_entry') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ t('item.add_multiple_items_quickly') }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button type="button" variant="outline" size="sm" class="h-8 gap-1.5">
                                    <Settings2 class="size-4 text-primary" />
                                    <span>{{ t('item.column_settings') }}</span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-64" align="end">
                                <p class="mb-2 text-sm font-semibold">{{ t('item.column_settings') }}</p>
                                <div class="space-y-2">
                                    <label
                                        v-for="column in optionalColumns"
                                        :key="column.key"
                                        class="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            :checked="columnSettings[column.key]"
                                            @update:checked="(value) => (columnSettings[column.key] = value)"
                                        />
                                        <span>{{ column.label }}</span>
                                    </label>
                                </div>
                                <p class="mt-3 text-xs text-muted-foreground">{{ t('item.fast_entry_tip_columns') }}</p>
                            </PopoverContent>
                        </Popover>

                        <Button
                            v-if="!showTips"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-8 gap-1.5"
                            @click="showTips = true"
                        >
                            <Info class="size-4 text-primary" />
                            <span>{{ t('item.fast_entry_tips_title') }}</span>
                        </Button>

                        <Button type="button" variant="outline" size="sm" class="h-8 gap-1.5" @click="goToItemList">
                            <ListOrdered class="size-4 text-primary" />
                            <span>{{ t('item.go_to_item_list') }}</span>
                        </Button>

                        <ModuleHelpButton module="fast_entry" toolbar class="shrink-0" />
                    </div>
                </div>

                <div v-if="showTips" class="px-4 pt-3">
                    <Alert>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <Info class="size-4 text-primary" />
                                <AlertTitle class="text-primary">{{ t('item.fast_entry_tips_title') }}</AlertTitle>
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
                                <li>{{ t('item.fast_entry_tip_scope') }}</li>
                                <li>{{ t('item.fast_entry_tip_keyboard') }}</li>
                                <li>{{ t('item.fast_entry_tip_defaults') }}</li>
                                <li>{{ t('item.fast_entry_tip_cost') }}</li>
                                <li>{{ t('item.fast_entry_tip_empty') }}</li>
                            </ul>
                        </AlertDescription>
                    </Alert>
                </div>

                <div
                    ref="gridRef"
                    class="rounded-xl border border-violet-400 bg-card shadow-sm overflow-x-auto p-3 mt-3 mb-1"
                    @keydown="onGridKeydown"
                >
                    <table class="w-full table-fixed min-w-[1000px]">
                        <thead class="sticky top-0 z-10 bg-muted/40">
                            <tr class="text-muted-foreground font-semibold text-sm text-white bg-primary">
                                <th class="px-1 py-1 w-8 min-w-8">#</th>
                                <th
                                    v-for="column in visibleColumns"
                                    :key="column.key"
                                    class="px-1 py-1"
                                    :class="column.width"
                                >
                                    {{ column.label }}
                                    <span v-if="column.required" class="text-red-300">*</span>
                                </th>
                                <th class="px-1 py-1 w-14">{{ t('general.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(item, index) in form.items"
                                :key="item._key"
                                class="border-t"
                                :class="rowHasError(index) ? 'bg-red-500/5' : ''"
                            >
                                <td class="px-1 py-2 align-top w-8 text-center text-xs text-muted-foreground">
                                    {{ index + 1 }}
                                </td>

                                <template v-for="column in visibleColumns" :key="column.key">
                                    <td
                                        class="px-1 py-2 align-top"
                                        :class="column.width"
                                        :data-cell="`${index}:${column.key}`"
                                    >
                                        <NextInput
                                            v-if="column.key === 'name'"
                                            label=""
                                            v-model="item.name"
                                            :error="fieldError(index, 'name')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'code'"
                                            label=""
                                            v-model="item.code"
                                            disabled
                                            :error="fieldError(index, 'code')"
                                        />

                                        <NextSelect
                                            v-else-if="column.key === 'measure_id'"
                                            v-model="item.measure_id"
                                            :options="unitMeasures"
                                            label-key="name"
                                            value-key="id"
                                            :show-arrow="false"
                                            :has-add-button="false"
                                            :searchable="true"
                                            resource-type="unit_measures"
                                            :search-fields="['name', 'unit', 'symbol']"
                                            append-to-body
                                            :error="fieldError(index, 'measure_id')"
                                        />

                                        <NextSelect
                                            v-else-if="column.key === 'category_id'"
                                            v-model="item.category_id"
                                            :options="categories"
                                            label-key="name"
                                            value-key="id"
                                            :show-arrow="false"
                                            :searchable="true"
                                            resource-type="categories"
                                            :search-fields="['name']"
                                            append-to-body
                                            :error="fieldError(index, 'category_id')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'quantity'"
                                            label=""
                                            type="number"
                                            inputmode="decimal"
                                            v-model="item.quantity"
                                            :error="fieldError(index, 'quantity')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'batch'"
                                            label=""
                                            v-model="item.batch"
                                            :error="fieldError(index, 'batch')"
                                        />

                                        <!-- The wrapper is what the arrow keys land on. Focusing the
                                             picker's own input is what opens the calendar, and it blurs
                                             itself doing so — so opening is an explicit Enter, and the
                                             grid keeps its place either way. -->
                                        <div
                                            v-else-if="column.key === 'expire_date'"
                                            data-grid-focus
                                            tabindex="-1"
                                            class="outline-none"
                                            :title="t('item.expiry_open_hint')"
                                            @keydown.enter.stop.prevent="openDatePicker"
                                        >
                                            <NextDate
                                                v-model="item.expire_date"
                                                :lock-future-dates="false"
                                                :show-icon="false"
                                                popover="top-left"
                                                :error="fieldError(index, 'expire_date')"
                                                :placeholder="t('item.expiry_placeholder')"
                                                @change="() => focusCell(index, 'expire_date')"
                                            />
                                        </div>

                                        <NextSelect
                                            v-else-if="column.key === 'warehouse_id'"
                                            v-model="item.warehouse_id"
                                            :options="warehouses"
                                            label-key="name"
                                            value-key="id"
                                            :show-arrow="false"
                                            :searchable="true"
                                            resource-type="warehouses"
                                            :search-fields="['name', 'address']"
                                            append-to-body
                                            :error="fieldError(index, 'warehouse_id')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'purchase_price'"
                                            label=""
                                            type="number"
                                            inputmode="decimal"
                                            v-model="item.purchase_price"
                                            :error="fieldError(index, 'purchase_price')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'sale_price'"
                                            label=""
                                            type="number"
                                            inputmode="decimal"
                                            v-model="item.sale_price"
                                            :error="fieldError(index, 'sale_price')"
                                        />

                                        <NextInput
                                            v-else-if="column.key === 'cost'"
                                            label=""
                                            type="number"
                                            inputmode="decimal"
                                            v-model="item.cost"
                                            :placeholder="String(item.purchase_price ?? '')"
                                            :error="fieldError(index, 'cost')"
                                        />
                                    </td>
                                </template>

                                <td class="px-1 py-2 align-top text-center">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        class="text-fuchsia-500"
                                        size="sm"
                                        @click="removeRow(index)"
                                        :disabled="form.items.length === 1"
                                    >−</Button>
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
                            {{ t('item.items_to_create') }}:
                            <span class="font-medium text-foreground">{{ filledRows.length }}</span>
                        </span>
                        <span v-if="columnSettings.quantity">
                            {{ t('item.opening_value') }}:
                            <span class="font-medium text-foreground">{{ formatMoney(openingValue) }}</span>
                        </span>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="secondary" @click="addRow()">
                            {{ t('general.add_row') }}
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? t('general.saving') : t('general.save') }}
                        </Button>
                    </div>
                </div>
            </div>
        </form>

        <AlertDialog v-model:open="confirmOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('item.confirm_save_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('item.confirm_save_description', {
                            items: filledRows.length,
                            openings: openingRows.length,
                            value: formatMoney(openingValue),
                        }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <dl class="grid grid-cols-3 gap-3 rounded-lg border p-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('item.items_to_create') }}</dt>
                        <dd class="font-semibold">{{ filledRows.length }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ t('item.with_opening_stock') }}</dt>
                        <dd class="font-semibold">{{ openingRows.length }}</dd>
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

/* The focused cell has to be obvious when the caret is being driven by the
   arrow keys rather than the mouse. */
td[data-cell]:focus-within {
    outline: 2px solid hsl(var(--primary) / 0.35);
    outline-offset: -2px;
    border-radius: calc(var(--radius) - 2px);
}
</style>
