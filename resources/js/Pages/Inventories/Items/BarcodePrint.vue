<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import BarcodeLabel from '@/Components/inventory/BarcodeLabel.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { usePage } from '@inertiajs/vue3'
import { AlertCircle, Barcode, Eye, Printer, RotateCcw, Ruler, Trash2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'

const { t } = useI18n()
const page = usePage()
// The preview is the sheet that prints, so every label is a live SVG and each
// one re-renders when a size or symbology changes. Two hundred keeps typing in
// the width box responsive; beyond that the browser starts to stutter.
const MAX_TOTAL_LABELS = 200

const props = defineProps({
  // The ten newest items, so the sheet opens with something in it instead of
  // making the operator search before seeing anything.
  recentItems: { type: [Array, Object], default: () => [] },
})

const MM_PER_PX = 3.7795

const printArea = ref(null)
const pickedItem = ref(null)
const searchTerm = ref('')
const bulkQuantity = ref(1)
const showMargins = ref(false)

/* ------------------------------------------------------------------ *
 * Sheet layout
 * ------------------------------------------------------------------ */

// A4/A5 tile as many labels as fit across the page; a label roll is defined by
// its column count instead, because the stock itself is that wide.
const paperKinds = [
  { id: 'a4_portrait', pageWidthMm: 210, pageHeightMm: 297, columns: null },
  { id: 'a4_landscape', pageWidthMm: 297, pageHeightMm: 210, columns: null },
  { id: 'a5_portrait', pageWidthMm: 148, pageHeightMm: 210, columns: null },
  { id: 'a5_landscape', pageWidthMm: 210, pageHeightMm: 148, columns: null },
  { id: 'label', pageWidthMm: null, pageHeightMm: null, columns: 1 },
  { id: 'label_2', pageWidthMm: null, pageHeightMm: null, columns: 2 },
  { id: 'label_3', pageWidthMm: null, pageHeightMm: null, columns: 3 },
  { id: 'label_4', pageWidthMm: null, pageHeightMm: null, columns: 4 },
  { id: 'label_5', pageWidthMm: null, pageHeightMm: null, columns: 5 },
]

const paperKindOptions = computed(() =>
  paperKinds.map((kind) => ({ id: kind.id, name: t(`item.barcode_paper.${kind.id}`) })),
)

// Only the symbologies a shop realistically prints. Each one accepts a
// different shape of data, which is why the label reports its own failure.
const symbologies = ['CODE128', 'CODE128A', 'CODE128B', 'CODE128C', 'EAN13', 'EAN8', 'UPC', 'CODE39', 'ITF14', 'MSI', 'codabar']

const symbologyOptions = computed(() =>
  symbologies.map((id) => ({ id, name: id === 'codabar' ? 'Codabar' : id })),
)

// What the chosen symbology will actually accept, said at the moment of
// choosing. EAN/UPC/ITF are GS1 retail standards with a fixed length and a
// computed check digit, so an internally generated code cannot satisfy them —
// which is the whole reason a label would come out saying "does not fit".
const symbologyRule = computed(() =>
  t(`item.barcode_symbology_rules.${layout.value.symbology}`),
)

const DEFAULT_LAYOUT = {
  heading: '',
  paperKind: 'a4_portrait',
  labelWidthMm: 50,
  labelHeightMm: 30,
  hSpacingMm: 4,
  vSpacingMm: 4,
  symbology: 'CODE128',
  marginTopMm: 10,
  marginBottomMm: 10,
  marginLeftMm: 10,
  marginRightMm: 10,
}

const layout = ref({ ...DEFAULT_LAYOUT })

const printOptions = ref({
  code: true,
  name: true,
  variant: true,
  barcode: true,
  price: true,
  border: true,
})

const selectedPaperKind = computed(
  () => paperKinds.find((kind) => kind.id === layout.value.paperKind) ?? paperKinds[0],
)

const isLabelRoll = computed(() => selectedPaperKind.value.columns !== null)

const resetMargins = () => {
  layout.value.marginTopMm = DEFAULT_LAYOUT.marginTopMm
  layout.value.marginBottomMm = DEFAULT_LAYOUT.marginBottomMm
  layout.value.marginLeftMm = DEFAULT_LAYOUT.marginLeftMm
  layout.value.marginRightMm = DEFAULT_LAYOUT.marginRightMm
}

const toNumber = (value, fallback, { min = 0, max = 500 } = {}) => {
  const numeric = Number(value)
  if (!Number.isFinite(numeric)) return fallback
  return Math.min(Math.max(numeric, min), max)
}

// Everything downstream reads these, so a half-typed "" in a width box can never
// collapse the preview into nothing.
const geometry = computed(() => {
  const widthMm = toNumber(layout.value.labelWidthMm, DEFAULT_LAYOUT.labelWidthMm, { min: 10 })
  const heightMm = toNumber(layout.value.labelHeightMm, DEFAULT_LAYOUT.labelHeightMm, { min: 10 })
  const heightPx = heightMm * MM_PER_PX

  const clamp = (value, min, max) => Math.round(Math.min(Math.max(value, min), max))

  return {
    widthMm,
    heightMm,
    paddingMm: Math.min(2, heightMm * 0.06),
    // Type and bars are sized off the label itself, so a 20mm sticker and a
    // 60mm one both come out readable instead of one of them overflowing.
    titleFontPx: clamp(heightPx * 0.11, 7, 15),
    textFontPx: clamp(heightPx * 0.095, 6, 13),
    barcodeHeightPx: clamp(heightPx * 0.4, 16, 90),
    svgHeightPx: clamp(heightPx * 0.42, 18, 94),
    barWidth: Math.min(Math.max(widthMm / 45, 0.8), 2.2),
  }
})

const spacing = computed(() => ({
  h: toNumber(layout.value.hSpacingMm, DEFAULT_LAYOUT.hSpacingMm, { min: 0, max: 100 }),
  v: toNumber(layout.value.vSpacingMm, DEFAULT_LAYOUT.vSpacingMm, { min: 0, max: 100 }),
}))

const margins = computed(() => ({
  top: toNumber(layout.value.marginTopMm, DEFAULT_LAYOUT.marginTopMm, { min: 0, max: 100 }),
  bottom: toNumber(layout.value.marginBottomMm, DEFAULT_LAYOUT.marginBottomMm, { min: 0, max: 100 }),
  left: toNumber(layout.value.marginLeftMm, DEFAULT_LAYOUT.marginLeftMm, { min: 0, max: 100 }),
  right: toNumber(layout.value.marginRightMm, DEFAULT_LAYOUT.marginRightMm, { min: 0, max: 100 }),
}))

/** How many labels fit across the chosen stock, so the operator can sanity-check the fit. */
const columnsPerRow = computed(() => {
  if (isLabelRoll.value) return selectedPaperKind.value.columns

  const usable = selectedPaperKind.value.pageWidthMm - margins.value.left - margins.value.right
  const step = geometry.value.widthMm + spacing.value.h

  if (step <= 0) return 1

  return Math.max(1, Math.floor((usable + spacing.value.h) / step))
})

const sheetGridStyle = computed(() => ({
  display: 'grid',
  gridTemplateColumns: `repeat(${columnsPerRow.value}, ${geometry.value.widthMm}mm)`,
  columnGap: `${spacing.value.h}mm`,
  rowGap: `${spacing.value.v}mm`,
  justifyContent: 'start',
  alignContent: 'start',
}))

/* ------------------------------------------------------------------ *
 * Products
 * ------------------------------------------------------------------ */

/**
 * The printable labels an item offers.
 *
 * Every item carries at least one variant, and that is where the barcode and
 * the price live. A product with real choices yields one row per variant — each
 * with its own barcode — while a plain product yields exactly one. An item made
 * before variants existed falls back to its own mirrored columns.
 */
function labelsForItem(item) {
  const variants = Array.isArray(item.variants) ? item.variants : []

  if (!variants.length) {
    return [{
      key: `${item.id}:item`,
      item_id: item.id,
      variant_id: null,
      name: item.name,
      code: item.code,
      category: item.category ?? '',
      variant_label: '',
      barcode: String(item.barcode ?? '').trim(),
      sale_price: Number(item.sale_price ?? 0),
    }]
  }

  // A lone default variant is the item itself wearing another hat — labelling
  // it would just be noise on the sticker.
  const isSingle = variants.length === 1

  return variants.map((variant) => ({
    key: `${item.id}:${variant.id}`,
    item_id: item.id,
    variant_id: variant.id,
    name: item.name,
    code: variant.sku || item.code,
    category: item.category ?? '',
    variant_label: isSingle ? '' : String(variant.display_name ?? variant.name ?? '').trim(),
    barcode: String(variant.barcode ?? item.barcode ?? '').trim(),
    sale_price: Number(variant.sale_price ?? item.sale_price ?? 0),
  }))
}

const rows = ref([])

// Seed from the newest items. Nothing is ticked: the operator chooses, exactly
// as they would from a product list.
const seedRows = (items) => {
  rows.value = (items ?? []).flatMap((item) =>
    labelsForItem(item).map((row) => ({ ...row, selected: false, quantity: 1 })),
  )
}

seedRows(props.recentItems?.data ?? props.recentItems ?? [])

watch(
  () => props.recentItems,
  (items) => seedRows(items?.data ?? items ?? []),
)

const filteredRows = computed(() => {
  const query = searchTerm.value.trim().toLowerCase()
  if (!query) return rows.value

  return rows.value.filter((row) =>
    [row.name, row.code, row.barcode, row.category, row.variant_label]
      .some((field) => String(field ?? '').toLowerCase().includes(query)),
  )
})

const selectedRows = computed(() => rows.value.filter((row) => row.selected && row.barcode))

const allFilteredSelected = computed(
  () => filteredRows.value.length > 0 && filteredRows.value.every((row) => row.selected),
)

const toggleAll = (checked) => {
  filteredRows.value.forEach((row) => {
    // A row with no barcode has nothing to print, so it never gets ticked.
    row.selected = Boolean(checked) && Boolean(row.barcode)
  })
}

/** Add a searched product to the table, or surface the one already there. */
function addItem(item) {
  pickedItem.value = null
  if (!item) return

  const candidates = labelsForItem(item)
  let added = 0

  candidates.forEach((candidate) => {
    const existing = rows.value.find((row) => row.key === candidate.key)

    if (existing) {
      existing.selected = Boolean(existing.barcode)
      return
    }

    rows.value.unshift({ ...candidate, selected: Boolean(candidate.barcode), quantity: 1 })
    added += 1
  })

  const withoutBarcode = candidates.filter((candidate) => !candidate.barcode).length

  if (withoutBarcode === candidates.length) {
    toast.error(t('item.barcode_not_available'))
    return
  }

  if (withoutBarcode > 0) {
    toast.warning(t('item.barcode_variants_skipped', { count: withoutBarcode }))
  }

  if (!added) {
    toast.info(t('item.barcode_already_listed'))
  }
}

function removeRow(key) {
  rows.value = rows.value.filter((row) => row.key !== key)
}

const sanitizeQuantity = (value) => {
  const numeric = Number(value)
  return Number.isFinite(numeric) && numeric > 0 ? Math.round(numeric) : 1
}

function applyBulkQuantity() {
  const quantity = sanitizeQuantity(bulkQuantity.value)
  bulkQuantity.value = quantity

  if (!selectedRows.value.length) {
    toast.error(t('item.no_items_selected'))
    return
  }

  selectedRows.value.forEach((row) => { row.quantity = quantity })
}

/* ------------------------------------------------------------------ *
 * Preview & print
 * ------------------------------------------------------------------ */

const decimalPlaces = computed(() =>
  Number(page.props?.user_preferences?.appearance?.decimal_places ?? 2),
)

const showCurrencySymbol = computed(() =>
  Boolean(page.props?.user_preferences?.display?.show_currency_symbol ?? true),
)

const currencySymbol = computed(() =>
  showCurrencySymbol.value ? String(page.props?.homeCurrency?.symbol ?? '') : '',
)

const totalLabels = computed(() =>
  selectedRows.value.reduce((total, row) => total + sanitizeQuantity(row.quantity), 0),
)

const overLimit = computed(() => totalLabels.value > MAX_TOTAL_LABELS)

const labelInstances = computed(() => {
  if (overLimit.value) return []

  return selectedRows.value.flatMap((row) =>
    Array.from({ length: sanitizeQuantity(row.quantity) }, (_, index) => ({
      key: `${row.key}-${index}`,
      row,
    })),
  )
})

const hasVariantRows = computed(() => rows.value.some((row) => Boolean(row.variant_label)))

function printLabels() {
  if (!labelInstances.value.length || !printArea.value) {
    toast.error(t('item.no_items_selected'))
    return
  }

  const popup = window.open('', '_blank', 'width=1000,height=800')
  if (!popup) {
    toast.error(t('general.error'))
    return
  }

  const kind = selectedPaperKind.value
  const { left, right, top, bottom } = margins.value

  // A label roll has no page size of its own: the stock is exactly as wide as
  // its columns, and as long as the job needs.
  const rollWidthMm = geometry.value.widthMm * kind.columns
    + spacing.value.h * Math.max(0, kind.columns - 1)
    + left + right

  const pageSize = kind.columns === null
    ? `${kind.pageWidthMm}mm ${kind.pageHeightMm}mm`
    : `${rollWidthMm}mm auto`

  const headingHtml = layout.value.heading.trim()
    ? `<h1 class="barcode-heading">${escapeHtml(layout.value.heading.trim())}</h1>`
    : ''

  popup.document.write(`<!DOCTYPE html>
<html dir="${document.documentElement.dir || 'ltr'}">
  <head>
    <title>${escapeHtml(layout.value.heading.trim() || t('item.print_barcode'))}</title>
    <style>
      @page {
        size: ${pageSize};
        margin: ${top}mm ${right}mm ${bottom}mm ${left}mm;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: Poppins, Arial, sans-serif;
        background: white;
        color: black;
      }
      .barcode-heading {
        font-size: 14pt;
        font-weight: 600;
        text-align: center;
        margin: 0 0 4mm;
      }
      .barcode-print-sheet {
        display: grid;
        grid-template-columns: repeat(${columnsPerRow.value}, ${geometry.value.widthMm}mm);
        column-gap: ${spacing.value.h}mm;
        row-gap: ${spacing.value.v}mm;
        justify-content: start;
        align-content: start;
      }
      .barcode-label {
        page-break-inside: avoid;
        break-inside: avoid;
      }
      @media print {
        body {
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }
      }
    </style>
  </head>
  <body>${headingHtml}${printArea.value.innerHTML}</body>
</html>`)
  popup.document.close()
  popup.focus()

  window.setTimeout(() => {
    popup.print()
    popup.close()
  }, 250)
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]))
}
</script>

<template>
  <AppLayout :title="t('item.print_barcode')">
    <FormPageToolbar back-route="items.index" module="barcode_print" />

    <!-- Sheet layout ------------------------------------------------- -->
    <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
      <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
        {{ t('item.barcode_layout') }}
      </div>

      <div class="grid grid-cols-1 gap-x-4 gap-y-5 mt-3 sm:grid-cols-2 lg:grid-cols-4 items-start">
        <NextInput
          :label="t('item.barcode_heading')"
          v-model="layout.heading"
          :placeholder="t('general.enter', { text: t('item.barcode_heading') })"
          :hint="t('item.barcode_heading_hint')"
        />
        <NextSelect
          v-model="layout.paperKind"
          :options="paperKindOptions"
          label-key="name"
          value-key="id"
          :floating-text="t('item.barcode_paper_kind')"
          :has-add-button="false"
          :clearable="false"
        />
        <NextInput
          :label="t('item.barcode_label_width')"
          type="number"
          inputmode="decimal"
          v-model="layout.labelWidthMm"
        />
        <NextInput
          :label="t('item.barcode_label_height')"
          type="number"
          inputmode="decimal"
          v-model="layout.labelHeightMm"
        />
        <NextInput
          :label="t('item.barcode_h_spacing')"
          type="number"
          inputmode="decimal"
          v-model="layout.hSpacingMm"
        />
        <NextInput
          :label="t('item.barcode_v_spacing')"
          type="number"
          inputmode="decimal"
          v-model="layout.vSpacingMm"
        />
        <NextSelect
          v-model="layout.symbology"
          :options="symbologyOptions"
          label-key="name"
          value-key="id"
          :floating-text="t('item.barcode_symbology')"
          :has-add-button="false"
          :clearable="false"
          :hint="symbologyRule"
        />
        <Button
          type="button"
          variant="outline"
          class="h-10 w-full gap-2 border-primary/60"
          @click="showMargins = !showMargins"
        >
          <Ruler class="size-4 text-primary" />
          {{ t('item.barcode_margins') }}
        </Button>
      </div>

      <div v-if="showMargins" class="mt-5 grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2 lg:grid-cols-5 items-center">
        <NextInput :label="t('item.barcode_margin_top')" type="number" inputmode="decimal" v-model="layout.marginTopMm" />
        <NextInput :label="t('item.barcode_margin_bottom')" type="number" inputmode="decimal" v-model="layout.marginBottomMm" />
        <NextInput :label="t('item.barcode_margin_left')" type="number" inputmode="decimal" v-model="layout.marginLeftMm" />
        <NextInput :label="t('item.barcode_margin_right')" type="number" inputmode="decimal" v-model="layout.marginRightMm" />
        <Button type="button" variant="ghost" class="h-10 gap-2 justify-self-start" @click="resetMargins">
          <RotateCcw class="size-4 text-primary" />
          {{ t('item.barcode_reset_margins') }}
        </Button>
      </div>

      <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3 border-t pt-4">
        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.code" @update:checked="(v) => (printOptions.code = Boolean(v))" />
          <span>{{ t('item.draw_code') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.name" @update:checked="(v) => (printOptions.name = Boolean(v))" />
          <span>{{ t('item.draw_name') }}</span>
        </label>
        <label v-if="hasVariantRows" class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.variant" @update:checked="(v) => (printOptions.variant = Boolean(v))" />
          <span>{{ t('item.draw_variant') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.barcode" @update:checked="(v) => (printOptions.barcode = Boolean(v))" />
          <span>{{ t('item.draw_barcode') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.price" @update:checked="(v) => (printOptions.price = Boolean(v))" />
          <span>{{ t('item.draw_price') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
          <Checkbox :checked="printOptions.border" @update:checked="(v) => (printOptions.border = Boolean(v))" />
          <span>{{ t('item.draw_border') }}</span>
        </label>

        <span class="ms-auto text-xs text-muted-foreground">
          {{ t('item.barcode_per_row', { count: columnsPerRow }) }}
        </span>
      </div>
    </div>

    <!-- Products ----------------------------------------------------- -->
    <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
      <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
        {{ t('item.barcode_products') }}
      </div>

      <div class="grid grid-cols-1 gap-x-4 gap-y-5 mt-3 lg:grid-cols-3 items-start">
        <NextSelect
          v-model="pickedItem"
          :options="[]"
          :reduce="(option) => option"
          label-key="name"
          value-key="id"
          :searchable="true"
          resource-type="items"
          :search-fields="['name', 'code', 'generic_name', 'packing', 'barcode', 'fast_search']"
          :search-options="{ limit: 25, with_variants: 1 }"
          :floating-text="t('item.add_product')"
          :placeholder="t('item.add_product_placeholder')"
          :has-add-button="false"
          append-to-body
          @update:modelValue="addItem"
        />

        <NextInput
          :label="t('datatable.search')"
          v-model="searchTerm"
          :placeholder="t('item.barcode_filter_placeholder')"
        />

        <div class="flex items-end gap-2">
          <div class="flex-1">
            <NextInput
              :label="t('item.barcode_number_of_barcodes')"
              type="number"
              inputmode="numeric"
              v-model="bulkQuantity"
            />
          </div>
          <Button type="button" variant="secondary" class="h-10 shrink-0" @click="applyBulkQuantity">
            {{ t('item.barcode_apply_to_selected') }}
          </Button>
        </div>
      </div>

      <Alert
        v-if="overLimit"
        variant="destructive"
        class="mt-4 flex items-start gap-3 [&>svg]:static [&>svg]:left-auto [&>svg]:top-auto [&>svg~*]:pl-0"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <div class="space-y-1">
          <AlertTitle class="mb-0">{{ t('item.barcode_total_limit_title', { limit: MAX_TOTAL_LABELS }) }}</AlertTitle>
          <AlertDescription>
            {{ t('item.barcode_over_limit', { count: totalLabels, limit: MAX_TOTAL_LABELS }) }}
          </AlertDescription>
        </div>
      </Alert>

      <div class="mt-4 overflow-x-auto rounded-xl border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead class="w-10">
                <Checkbox :checked="allFilteredSelected" @update:checked="toggleAll" />
              </TableHead>
              <TableHead class="w-10">#</TableHead>
              <TableHead>{{ t('item.code') }}</TableHead>
              <TableHead>{{ t('item.barcode') }}</TableHead>
              <TableHead>{{ t('admin.category.category') }}</TableHead>
              <TableHead>{{ t('general.name') }}</TableHead>
              <TableHead v-if="hasVariantRows">{{ t('item.variant') }}</TableHead>
              <TableHead class="text-end">{{ t('item.sale_price') }}</TableHead>
              <TableHead class="w-28">{{ t('item.quantity') }}</TableHead>
              <TableHead class="w-16 text-end">{{ t('general.actions') }}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-if="!filteredRows.length">
              <TableCell :colspan="hasVariantRows ? 10 : 9" class="py-10 text-center text-muted-foreground">
                {{ t('item.no_items_selected') }}
              </TableCell>
            </TableRow>
            <TableRow
              v-for="(row, index) in filteredRows"
              :key="row.key"
              :class="row.selected ? 'bg-primary/5' : ''"
            >
              <TableCell>
                <Checkbox
                  :checked="row.selected"
                  :disabled="!row.barcode"
                  @update:checked="(v) => (row.selected = Boolean(v))"
                />
              </TableCell>
              <TableCell class="text-xs text-muted-foreground">{{ index + 1 }}</TableCell>
              <TableCell>{{ row.code }}</TableCell>
              <TableCell>
                <span v-if="row.barcode" class="font-mono text-xs">{{ row.barcode }}</span>
                <span v-else class="text-xs text-destructive">{{ t('item.barcode_missing') }}</span>
              </TableCell>
              <TableCell class="text-muted-foreground">{{ row.category || '—' }}</TableCell>
              <TableCell class="font-medium">{{ row.name }}</TableCell>
              <TableCell v-if="hasVariantRows" class="text-muted-foreground">{{ row.variant_label || '—' }}</TableCell>
              <TableCell class="text-end">{{ row.sale_price }}</TableCell>
              <TableCell>
                <NextInput
                  label=""
                  type="number"
                  inputmode="numeric"
                  :model-value="row.quantity"
                  :disabled="!row.selected"
                  @update:modelValue="(value) => (row.quantity = value)"
                />
              </TableCell>
              <TableCell class="text-end">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="text-destructive hover:text-destructive"
                  @click="removeRow(row.key)"
                >
                  <Trash2 class="size-4" />
                  <span class="sr-only">{{ t('item.remove_item') }}</span>
                </Button>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>

      <p class="mt-2 text-xs text-muted-foreground">{{ t('item.no_barcode_items') }}</p>
    </div>

    <!-- Preview ------------------------------------------------------ -->
    <div class="mb-5 rounded-xl border p-4 shadow-sm border-primary relative">
      <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
        {{ t('item.preview') }}
      </div>

      <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-b pb-3">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <Eye class="size-4 text-primary" />
          <span>{{ t('item.labels_ready_with_limit', { count: totalLabels, limit: MAX_TOTAL_LABELS }) }}</span>
        </div>
        <Button type="button" :disabled="!labelInstances.length" @click="printLabels">
          <Printer class="mr-2 size-4 rtl:ml-2 rtl:mr-0" />
          {{ t('general.print') }}
        </Button>
      </div>

      <div v-if="labelInstances.length" class="mt-4 overflow-x-auto rounded-xl border bg-muted/20 p-4">
        <div ref="printArea" class="barcode-print-sheet" :style="sheetGridStyle">
          <BarcodeLabel
            v-for="label in labelInstances"
            :key="label.key"
            :item="label.row"
            :size="geometry"
            :symbology="layout.symbology"
            :show-name="printOptions.name"
            :show-variant="printOptions.variant"
            :show-code="printOptions.code"
            :show-price="printOptions.price"
            :show-barcode="printOptions.barcode"
            :show-border="printOptions.border"
            :currency-symbol="currencySymbol"
            :decimal-places="decimalPlaces"
          />
        </div>
      </div>
      <div
        v-else
        class="mt-4 flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed bg-muted/20 px-6 text-center"
      >
        <Barcode class="mb-3 size-10 text-muted-foreground" />
        <p class="font-medium">{{ t('item.no_items_selected') }}</p>
        <p class="mt-2 max-w-md text-sm text-muted-foreground">{{ t('item.preview_description') }}</p>
      </div>
    </div>
  </AppLayout>
</template>
