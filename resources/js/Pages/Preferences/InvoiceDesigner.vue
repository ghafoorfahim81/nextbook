<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import axios from 'axios'
import {
  FileText, Plus, Copy, Trash2, Star, StarOff, ChevronDown, ChevronRight,
  Eye, EyeOff, Save, X, RotateCcw, Settings, Columns, Layout,
  PanelLeft, Type, SlidersHorizontal, Printer, ChevronUp
} from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import { Checkbox } from '@/Components/ui/checkbox'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/Components/ui/dialog'
import CustomInvoiceLayout from '@/Pages/Sale/Sales/CustomInvoiceLayout.vue'
import { vHighlightSearch } from '@/directives/highlightSearch'

const props = defineProps({
  invoiceThemes:       { type: Array,  default: () => [] },
  invoiceFormats:      { type: Array,  default: () => [] },
  invoiceFormatDefaults: { type: Object, default: () => ({}) },
  currentTheme:        { type: String, default: 'format1' },
  searchQuery:         { type: String, default: '' },
  searchToken:         { type: String, default: '' },
})

const emit = defineEmits(['selectTheme'])

const { t, locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps', 'ar', 'ur'].includes(String(locale.value).toLowerCase()))
const uiDir = computed(() => isRTL.value ? 'rtl' : 'ltr')
const searchHighlightContext = computed(() => ({
  query: props.searchQuery,
  token: props.searchToken,
}))

// ─── local state ─────────────────────────────────────────────────────────────
const formats    = ref([...props.invoiceFormats])
const selected   = ref(null)   // id of selected (custom) format, or null
const editing    = ref(false)  // is a custom format editor open?
const previewOpen = ref(false)
const saving     = ref(false)
const delConfirm = ref(false)

// The working copy of the format being edited/created
const draft = reactive({
  id:               null,
  name:             '',
  is_default:       false,
  paper_size:       'a4',
  paper_orientation:'portrait',
  language:         'en',
  direction:        'ltr',
  margins:          { top: 10, right: 10, bottom: 10, left: 10 },
  header_config: {
    show_logo:              true,
    show_company_name:      true,
    show_company_address:   true,
    show_company_phone:     true,
    show_invoice_number:    true,
    show_date:              true,
    show_due_date:          true,
    show_customer_name:     true,
    show_customer_address:  false,
    show_customer_phone:    false,
    show_store_name:        false,
    logo_max_height:        64,
    title_text:             'INVOICE',
  },
  item_columns: {
    visible:            ['row', 'name', 'quantity', 'unit_price', 'discount', 'total'],
    column_labels:      { row: '#', code: 'Code', name: 'Item', unit: 'Unit', quantity: 'Qty', unit_price: 'Rate', discount: 'Discount', tax: 'Tax', total: 'Amount' },
    header_bg_color:    '#1e293b',
    header_text_color:  '#ffffff',
    header_font_size:   13,
    row_font_size:      13,
    stripe_rows:        false,
    stripe_color:       '#f8fafc',
    show_borders:       true,
  },
  optional_sections: {
    show_notes:              true,
    show_terms:              true,
    show_footer:             false,
    show_signature:          false,
    show_qr:                 false,
    show_barcode:            false,
    show_bank_details:       false,
    show_customer_tax_number:false,
    show_thank_you:          false,
    show_summary_subtotal:   true,
    show_summary_discount:   true,
    show_summary_tax:        false,
    items_per_page:          0,
    tax_display:             'per_item',
  },
  appearance: {
    bg_color:          '#ffffff',
    font_family:       'sans-serif',
    font_size:         14,
    font_color:        '#0f172a',
    border_show:       true,
    border_color:      '#cbd5e1',
    border_width:      1,
    summary_bg_color:  '#f1f5f9',
    summary_text_color:'#0f172a',
  },
  watermark_text: '',
  footer_text:    '',
  bank_details:   '',
  thank_you_text: '',
  custom_css:     '',
})

const ITEM_COLUMN_OPTIONS = computed(() => [
  { key: 'row',        label: '#' },
  { key: 'code',       label: t('preferences.item_fields.code') },
  { key: 'name',       label: t('preferences.invoice_designer.col_name') },
  { key: 'unit',       label: t('preferences.invoice_designer.col_unit') },
  { key: 'quantity',   label: t('preferences.invoice_designer.col_quantity') },
  { key: 'unit_price', label: t('preferences.invoice_designer.col_unit_price') },
  { key: 'discount',   label: t('preferences.fields.discount') },
  { key: 'tax',        label: t('preferences.fields.tax') },
  { key: 'total',      label: t('preferences.invoice_designer.col_total') },
])

const FONT_FAMILIES = [
  { value: 'sans-serif', label: 'Sans Serif (Default)' },
  { value: 'serif',      label: 'Serif' },
  { value: 'monospace',  label: 'Monospace' },
  { value: 'Arial, sans-serif',   label: 'Arial' },
  { value: '"Times New Roman", serif', label: 'Times New Roman' },
  { value: '"Courier New", monospace', label: 'Courier New' },
  { value: '"Noto Naskh Arabic", serif', label: 'Noto Naskh Arabic' },
  { value: '"Scheherazade New", serif',  label: 'Scheherazade (Dari/Pashto)' },
]

const PAPER_SIZES = [
  { value: 'a4',          label: 'A4 (210 × 297 mm)' },
  { value: 'a5',          label: 'A5 (148 × 210 mm)' },
  { value: 'letter',      label: 'Letter (216 × 279 mm)' },
  { value: 'thermal_80mm',label: 'Thermal 80mm' },
]

const optionalSectionItems = computed(() => [
  { key: 'show_notes',               label: t('preferences.invoice_designer.notes') },
  { key: 'show_terms',               label: t('preferences.invoice_designer.terms') },
  { key: 'show_footer',              label: t('preferences.invoice_designer.footer_text') },
  { key: 'show_signature',           label: t('preferences.invoice_designer.signature') },
  { key: 'show_qr',                  label: t('preferences.invoice_designer.qr') },
  { key: 'show_barcode',             label: t('preferences.invoice_designer.barcode') },
  { key: 'show_bank_details',        label: t('preferences.invoice_designer.bank_details') },
  { key: 'show_customer_tax_number', label: t('preferences.invoice_designer.customer_tax') },
  { key: 'show_thank_you',           label: t('preferences.invoice_designer.thank_you') },
  { key: 'show_summary_subtotal',    label: t('preferences.invoice_designer.summary_subtotal') },
  { key: 'show_summary_discount',    label: t('preferences.invoice_designer.summary_discount') },
  { key: 'show_summary_tax',         label: t('preferences.invoice_designer.summary_tax') },
])

// ─── section accordion state ──────────────────────────────────────────────────
const openSections = reactive({
  header:   true,
  columns:  true,
  sections: false,
  paper:    false,
  appearance: false,
  advanced: false,
})

const toggleSection = (key) => { openSections[key] = !openSections[key] }

// ─── helpers ─────────────────────────────────────────────────────────────────
const isBuiltin = (id) => ['format1','format2','format3','format4','format5'].includes(id)
const isColVisible = (key) => draft.item_columns.visible.includes(key)

const toggleColumn = (key) => {
  const idx = draft.item_columns.visible.indexOf(key)
  if (idx === -1) draft.item_columns.visible.push(key)
  else            draft.item_columns.visible.splice(idx, 1)
}

// Visible columns in the order they will be printed
const orderedVisibleColumns = computed(() =>
  draft.item_columns.visible
    .map(key => ITEM_COLUMN_OPTIONS.value.find(c => c.key === key))
    .filter(Boolean)
)

const moveColumn = (key, direction) => {
  const list = draft.item_columns.visible
  const idx = list.indexOf(key)
  const swapWith = idx + direction
  if (idx === -1 || swapWith < 0 || swapWith >= list.length) return
  ;[list[idx], list[swapWith]] = [list[swapWith], list[idx]]
}

// Populate draft from a format object
const loadDraft = (fmt) => {
  if (!fmt) return
  draft.id               = fmt.id
  draft.name             = fmt.name
  draft.is_default       = fmt.is_default
  draft.paper_size       = fmt.paper_size       || 'a4'
  draft.paper_orientation= fmt.paper_orientation || 'portrait'
  draft.language         = fmt.language         || 'en'
  draft.direction        = fmt.direction        || 'ltr'
  draft.watermark_text   = fmt.watermark_text   || ''
  draft.footer_text      = fmt.footer_text      || ''
  draft.bank_details     = fmt.bank_details     || ''
  draft.thank_you_text   = fmt.thank_you_text   || ''
  draft.custom_css       = fmt.custom_css       || ''
  Object.assign(draft.margins,           props.invoiceFormatDefaults.margins,          fmt.margins          || {})
  Object.assign(draft.header_config,     props.invoiceFormatDefaults.header_config,    fmt.header_config    || {})
  Object.assign(draft.item_columns,      props.invoiceFormatDefaults.item_columns,     fmt.item_columns     || {})
  Object.assign(draft.optional_sections, props.invoiceFormatDefaults.optional_sections,fmt.optional_sections|| {})
  Object.assign(draft.appearance,        props.invoiceFormatDefaults.appearance,       fmt.appearance       || {})
  if (Array.isArray((fmt.item_columns || {}).visible)) {
    draft.item_columns.visible = [...fmt.item_columns.visible]
  }
  // Deep-merge column_labels so saved labels override defaults
  draft.item_columns.column_labels = {
    ...draft.item_columns.column_labels,
    ...(fmt.item_columns?.column_labels || {}),
  }
}

const resetDraft = () => {
  draft.id = null
  draft.name = ''
  draft.is_default = false
  draft.watermark_text = ''
  draft.footer_text = ''
  draft.bank_details = ''
  draft.thank_you_text = ''
  draft.custom_css = ''
  const def = props.invoiceFormatDefaults
  Object.assign(draft, {
    paper_size: def.paper_size, paper_orientation: def.paper_orientation,
    language: def.language, direction: def.direction,
  })
  Object.assign(draft.margins,           def.margins)
  Object.assign(draft.header_config,     def.header_config)
  Object.assign(draft.item_columns,      def.item_columns)
  Object.assign(draft.optional_sections, def.optional_sections)
  Object.assign(draft.appearance,        def.appearance)
  draft.item_columns.visible = [...def.item_columns.visible]
}

// ─── actions ─────────────────────────────────────────────────────────────────
const openNew = () => {
  resetDraft()
  editing.value = true
  selected.value = null
}

const openEdit = (fmt) => {
  loadDraft(fmt)
  editing.value = true
  selected.value = fmt.id
}

const cancelEdit = () => {
  editing.value = false
  selected.value = null
}

const payload = () => ({
  name:               draft.name,
  is_default:         draft.is_default,
  paper_size:         draft.paper_size,
  paper_orientation:  draft.paper_orientation,
  language:           draft.language,
  direction:          draft.direction,
  margins:            { ...draft.margins },
  header_config:      { ...draft.header_config },
  item_columns:       { ...draft.item_columns, visible: [...draft.item_columns.visible] },
  optional_sections:  { ...draft.optional_sections },
  appearance:         { ...draft.appearance },
  watermark_text:     draft.watermark_text || null,
  footer_text:        draft.footer_text    || null,
  bank_details:       draft.bank_details   || null,
  thank_you_text:     draft.thank_you_text || null,
  custom_css:         draft.custom_css     || null,
})

const save = async () => {
  if (!draft.name.trim()) { toast.error(t('preferences.invoice_designer.name_required')); return }
  saving.value = true
  try {
    if (draft.id) {
      const { data } = await axios.put(`/invoice-formats/${draft.id}`, payload())
      const idx = formats.value.findIndex(f => f.id === draft.id)
      if (idx !== -1) formats.value.splice(idx, 1, data.data)
      toast.success(t('preferences.invoice_designer.updated'))
    } else {
      const { data } = await axios.post('/invoice-formats', payload())
      formats.value.push(data.data)
      draft.id = data.data.id
      selected.value = data.data.id
      toast.success(t('preferences.invoice_designer.created'))
    }
    if (draft.is_default) {
      formats.value.forEach(f => { if (f.id !== draft.id) f.is_default = false })
    }
  } catch (err) {
    const msg = err?.response?.data?.message || t('preferences.invoice_designer.save_error')
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

const confirmDelete = () => { delConfirm.value = true }
const doDelete = async () => {
  if (!draft.id) return
  try {
    await axios.delete(`/invoice-formats/${draft.id}`)
    formats.value = formats.value.filter(f => f.id !== draft.id)
    toast.success(t('preferences.invoice_designer.deleted'))
    delConfirm.value = false
    cancelEdit()
  } catch {
    toast.error(t('preferences.invoice_designer.delete_error'))
  }
}

const cloneFormat = async (fmt) => {
  try {
    const { data } = await axios.post(`/invoice-formats/${fmt.id}/clone`)
    formats.value.push(data.data)
    openEdit(data.data)
    toast.success(`"${data.data.name}" ${t('preferences.invoice_designer.cloned')}`)
  } catch {
    toast.error(t('preferences.invoice_designer.clone_error'))
  }
}

const setDefault = async (fmt) => {
  try {
    const { data } = await axios.patch(`/invoice-formats/${fmt.id}/set-default`)
    formats.value.forEach(f => { f.is_default = f.id === fmt.id })
    toast.success(`"${fmt.name}" ${t('preferences.invoice_designer.default_set')}`)
  } catch {
    toast.error(t('preferences.invoice_designer.save_error'))
  }
}

const selectTheme = (themeId) => { emit('selectTheme', themeId) }

// ─── preview sample data ─────────────────────────────────────────────────────
const sampleInvoice = {
  number: '1001',
  date: new Date().toISOString().slice(0, 10),
  due_date: null,
  description: 'Sample note for the invoice.',
  discount: 50,
  discount_type: 'fixed',
  remaining_amount: 950,
  old_balance: 0,
  customer: { name: 'Sample Customer', address: 'Kabul, Afghanistan', phone: '+93 700 000 000' },
  warehouse: { name: 'Main Store' },
  transaction: { currency: { code: 'AFN' } },
  items: [
    { id: '1', item_name: 'Product A', item_code: 'P001', unit_measure_name: 'Pcs', quantity: 2, unit_price: 300, discount: 0, tax: 0 },
    { id: '2', item_name: 'Product B', item_code: 'P002', unit_measure_name: 'Box', quantity: 1, unit_price: 450, discount: 50, tax: 0 },
  ],
}

const previewFormat = computed(() => ({
  ...draft,
  margins:           { ...draft.margins },
  header_config:     { ...draft.header_config },
  item_columns:      { ...draft.item_columns, visible: [...draft.item_columns.visible] },
  optional_sections: { ...draft.optional_sections },
  appearance:        { ...draft.appearance },
}))

// ─── paper size helpers for preview ──────────────────────────────────────────
const PAPER_DIMS = {
  a4:           { w: 210, h: 297 },
  a5:           { w: 148, h: 210 },
  letter:       { w: 216, h: 279 },
  thermal_80mm: { w: 80,  h: 200 },
}

const paperStyle = computed(() => {
  const dims = PAPER_DIMS[draft.paper_size] ?? PAPER_DIMS.a4
  const isLandscape = draft.paper_orientation === 'landscape'
  const wMm = isLandscape ? dims.h : dims.w
  const hMm = isLandscape ? dims.w : dims.h
  // Scale to a max width of ~750px in the preview
  const scale = Math.min(750 / (wMm * 3.78), 1)
  return {
    width:     `${wMm * 3.78}px`,
    minHeight: `${hMm * 3.78}px`,
    transform: `scale(${scale})`,
    transformOrigin: 'top left',
    marginBottom: `${(scale - 1) * hMm * 3.78}px`,
    outline: '1px solid #e2e8f0',
    background: '#ffffff',
  }
})

const paperLabel = computed(() => {
  const ps = draft.paper_size?.toUpperCase().replace('_', ' ') ?? 'A4'
  const or = draft.paper_orientation === 'landscape' ? 'Landscape' : 'Portrait'
  return `${ps} · ${or}`
})
</script>

<template>
  <div v-highlight-search="searchHighlightContext" class="flex gap-6 min-h-[600px]" :dir="uiDir">

    <!-- ── LEFT: Format list ─────────────────────────────── -->
    <div class="w-72 shrink-0 flex flex-col gap-3">

      <!-- Built-in formats -->
      <div>
        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2">{{ t('preferences.invoice_designer.builtin_formats') }}</p>
        <div class="flex flex-col gap-1">
          <button
            v-for="theme in invoiceThemes"
            :key="theme.id"
            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm text-start transition-colors hover:bg-accent"
            :class="currentTheme === theme.id ? 'border-primary bg-accent font-medium' : 'border-transparent'"
            @click="selectTheme(theme.id)"
          >
            <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
            <span class="flex-1 truncate">{{ theme.name ? $t(theme.name, theme.id) : theme.id }}</span>
            <span v-if="currentTheme === theme.id" class="text-xs text-primary font-medium">{{ t('preferences.invoice_designer.active') }}</span>
          </button>
        </div>
      </div>

      <!-- Custom formats -->
      <div>
        <div class="flex items-center justify-between mb-2">
          <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ t('preferences.invoice_designer.custom_formats') }}</p>
          <Button variant="ghost" size="sm" class="h-6 px-2 text-xs" @click="openNew">
            <Plus class="h-3 w-3 mr-1" /> {{ t('preferences.invoice_designer.new') }}
          </Button>
        </div>

        <div v-if="!formats.length" class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
          {{ t('preferences.invoice_designer.empty') }}<br />{{ t('preferences.invoice_designer.empty_hint') }}
        </div>

        <div class="flex flex-col gap-1">
          <div
            v-for="fmt in formats"
            :key="fmt.id"
            class="group flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-accent cursor-pointer"
            :class="[
              selected === fmt.id && editing ? 'border-primary bg-accent' : 'border-transparent',
            ]"
            @click="openEdit(fmt)"
          >
            <FileText class="h-4 w-4 shrink-0 text-muted-foreground" />
            <span class="flex-1 truncate">{{ fmt.name }}</span>
            <Star v-if="fmt.is_default" class="h-3 w-3 text-amber-500 shrink-0" />
            <span v-if="currentTheme === fmt.id" class="text-xs text-primary font-medium shrink-0">{{ t('preferences.invoice_designer.active') }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: Editor ─────────────────────────────────── -->
    <div class="flex-1 min-w-0">

      <!-- Empty state -->
      <div
        v-if="!editing"
        class="flex h-full flex-col items-center justify-center gap-4 rounded-xl border border-dashed p-12 text-center text-muted-foreground"
      >
        <Layout class="h-12 w-12 opacity-30" />
        <div>
          <p class="font-medium">{{ t('preferences.invoice_designer.empty_state') }}</p>
          <p class="text-sm mt-1">{{ t('preferences.invoice_designer.empty_state_hint') }}</p>
        </div>
        <Button variant="outline" size="sm" @click="openNew">
          <Plus class="h-4 w-4 mr-2" /> {{ t('preferences.invoice_designer.create') }}
        </Button>
      </div>

      <!-- Editor -->
      <div v-else class="flex flex-col gap-4">

        <!-- Toolbar -->
        <div class="flex items-center gap-2 flex-wrap">
          <Input v-model="draft.name" :placeholder="t('preferences.invoice_designer.format_name')" class="w-56" />

          <Button :disabled="saving" @click="save">
            <Save class="h-4 w-4 mr-2" />
            {{ saving ? t('preferences.invoice_designer.saving') : (draft.id ? t('preferences.invoice_designer.save') : t('preferences.invoice_designer.create_btn')) }}
          </Button>

          <Button variant="outline" @click="previewOpen = true">
            <Eye class="h-4 w-4 mr-2" /> {{ t('preferences.invoice_designer.preview') }}
          </Button>

          <Button
            v-if="draft.id"
            variant="outline"
            :class="draft.is_default ? 'text-amber-600' : ''"
            @click="draft.is_default = !draft.is_default; draft.id && setDefault({ id: draft.id })"
            :title="draft.is_default ? 'Remove as default' : 'Set as default'"
          >
            <Star class="h-4 w-4" :class="draft.is_default ? 'fill-amber-500 text-amber-500' : ''" />
          </Button>

          <Button
            v-if="draft.id"
            variant="outline"
            @click="selectTheme(draft.id)"
            :title="t('preferences.invoice_designer.use_for_printing')"
          >
            <Printer class="h-4 w-4 mr-2" />
            {{ currentTheme === draft.id ? t('preferences.invoice_designer.active') : t('preferences.invoice_designer.use_for_printing') }}
          </Button>

          <div class="flex-1" />

          <Button v-if="draft.id" variant="ghost" size="icon" title="Clone" @click="cloneFormat({ id: draft.id })">
            <Copy class="h-4 w-4" />
          </Button>
          <Button v-if="draft.id" variant="ghost" size="icon" title="Delete" class="text-destructive" @click="confirmDelete">
            <Trash2 class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" title="Cancel" @click="cancelEdit">
            <X class="h-4 w-4" />
          </Button>
        </div>

        <!-- Accordion sections -->
        <div class="flex flex-col gap-2 overflow-y-auto max-h-[72vh] pr-1">

          <!-- ── 1. Header ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('header')"
            >
              <Layout class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.header_section') }}
              <ChevronDown v-if="openSections.header" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.header" class="border-t px-4 py-4 grid grid-cols-2 gap-x-8 gap-y-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_logo" @update:checked="v => draft.header_config.show_logo = v" />
                {{ t('preferences.invoice_designer.show_logo') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_company_name" @update:checked="v => draft.header_config.show_company_name = v" />
                {{ t('preferences.invoice_designer.company_name') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_company_address" @update:checked="v => draft.header_config.show_company_address = v" />
                {{ t('preferences.invoice_designer.company_address') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_company_phone" @update:checked="v => draft.header_config.show_company_phone = v" />
                {{ t('preferences.invoice_designer.company_phone') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_invoice_number" @update:checked="v => draft.header_config.show_invoice_number = v" />
                {{ t('preferences.invoice_designer.invoice_number') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_date" @update:checked="v => draft.header_config.show_date = v" />
                {{ t('preferences.invoice_designer.issue_date') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_due_date" @update:checked="v => draft.header_config.show_due_date = v" />
                {{ t('preferences.invoice_designer.due_date') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_customer_name" @update:checked="v => draft.header_config.show_customer_name = v" />
                {{ t('preferences.invoice_designer.customer_name') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_customer_address" @update:checked="v => draft.header_config.show_customer_address = v" />
                {{ t('preferences.invoice_designer.customer_address') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_customer_phone" @update:checked="v => draft.header_config.show_customer_phone = v" />
                {{ t('preferences.invoice_designer.customer_phone') }}
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                <Checkbox :checked="draft.header_config.show_store_name" @update:checked="v => draft.header_config.show_store_name = v" />
                {{ t('preferences.invoice_designer.store_warehouse') }}
              </label>

              <div class="col-span-2 grid grid-cols-2 gap-4 mt-2">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.title_text') }}</Label>
                  <Input v-model="draft.header_config.title_text" placeholder="INVOICE" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.logo_max_height') }}</Label>
                  <Input v-model.number="draft.header_config.logo_max_height" type="number" min="20" max="200" />
                </div>
              </div>
            </div>
          </div>

          <!-- ── 2. Item Columns ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('columns')"
            >
              <Columns class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.item_columns') }}
              <ChevronDown v-if="openSections.columns" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.columns" class="border-t px-4 py-4 space-y-4">
              <div class="grid grid-cols-3 gap-2">
                <label
                  v-for="col in ITEM_COLUMN_OPTIONS"
                  :key="col.key"
                  class="flex items-center gap-2 text-sm cursor-pointer select-none"
                >
                  <Checkbox :checked="isColVisible(col.key)" @update:checked="() => toggleColumn(col.key)" />
                  {{ col.label }}
                </label>
              </div>

              <!-- Column header labels + order -->
              <div>
                <div class="flex items-center justify-between mb-2">
                  <Label class="text-xs font-semibold">{{ t('preferences.invoice_designer.column_labels') }}</Label>
                  <span class="text-[10px] text-muted-foreground">{{ t('preferences.invoice_designer.column_order_hint') }}</span>
                </div>
                <div class="flex flex-col gap-1.5">
                  <div
                    v-for="(col, index) in orderedVisibleColumns"
                    :key="col.key"
                    class="flex items-center gap-2 rounded-md border px-2 py-1"
                  >
                    <div class="flex flex-col shrink-0">
                      <button
                        type="button"
                        class="h-4 w-4 flex items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-25 disabled:pointer-events-none"
                        :disabled="index === 0"
                        :title="t('preferences.invoice_designer.column_order_hint')"
                        @click="moveColumn(col.key, -1)"
                      >
                        <ChevronUp class="h-3.5 w-3.5" />
                      </button>
                      <button
                        type="button"
                        class="h-4 w-4 flex items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-25 disabled:pointer-events-none"
                        :disabled="index === orderedVisibleColumns.length - 1"
                        :title="t('preferences.invoice_designer.column_order_hint')"
                        @click="moveColumn(col.key, 1)"
                      >
                        <ChevronDown class="h-3.5 w-3.5" />
                      </button>
                    </div>
                    <span class="text-xs text-muted-foreground w-20 shrink-0 truncate">{{ col.label }}</span>
                    <Input
                      v-model="draft.item_columns.column_labels[col.key]"
                      class="h-7 text-xs flex-1"
                      :placeholder="col.label"
                    />
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.header_bg') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.item_columns.header_bg_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.item_columns.header_bg_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.header_text_color') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.item_columns.header_text_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.item_columns.header_text_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.header_font_size') }}</Label>
                  <Input v-model.number="draft.item_columns.header_font_size" type="number" min="8" max="30" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.row_font_size') }}</Label>
                  <Input v-model.number="draft.item_columns.row_font_size" type="number" min="8" max="30" />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                  <Checkbox :checked="draft.item_columns.stripe_rows" @update:checked="v => draft.item_columns.stripe_rows = v" />
                  {{ t('preferences.invoice_designer.stripe_rows') }}
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                  <Checkbox :checked="draft.item_columns.show_borders" @update:checked="v => draft.item_columns.show_borders = v" />
                  {{ t('preferences.invoice_designer.show_borders') }}
                </label>
                <div v-if="draft.item_columns.stripe_rows" class="col-span-2 space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.stripe_color') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.item_columns.stripe_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.item_columns.stripe_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── 3. Optional Sections ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('sections')"
            >
              <SlidersHorizontal class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.optional_sections') }}
              <ChevronDown v-if="openSections.sections" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.sections" class="border-t px-4 py-4 space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none" v-for="item in optionalSectionItems" :key="item.key">
                  <Checkbox :checked="draft.optional_sections[item.key]" @update:checked="v => draft.optional_sections[item.key] = v" />
                  {{ item.label }}
                </label>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.items_per_page') }}</Label>
                  <Input v-model.number="draft.optional_sections.items_per_page" type="number" min="0" max="100" />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.tax_display') }}</Label>
                  <Select v-model="draft.optional_sections.tax_display">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem value="per_item">{{ t('preferences.invoice_designer.tax_per_item') }}</SelectItem>
                      <SelectItem value="grouped">{{ t('preferences.invoice_designer.tax_grouped') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div v-if="draft.optional_sections.show_footer" class="space-y-1">
                <Label class="text-xs">{{ t('preferences.invoice_designer.footer_text_label') }}</Label>
                <Textarea v-model="draft.footer_text" rows="2" placeholder="Footer content…" />
              </div>
              <div v-if="draft.optional_sections.show_bank_details" class="space-y-1">
                <Label class="text-xs">{{ t('preferences.invoice_designer.bank_details_label') }}</Label>
                <Textarea v-model="draft.bank_details" rows="3" placeholder="Bank name, account number…" />
              </div>
              <div v-if="draft.optional_sections.show_thank_you" class="space-y-1">
                <Label class="text-xs">{{ t('preferences.invoice_designer.thank_you_label') }}</Label>
                <Input v-model="draft.thank_you_text" placeholder="Thank you for your business!" />
              </div>
            </div>
          </div>

          <!-- ── 4. Paper Settings ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('paper')"
            >
              <PanelLeft class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.paper_layout') }}
              <ChevronDown v-if="openSections.paper" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.paper" class="border-t px-4 py-4 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.paper_size') }}</Label>
                  <Select v-model="draft.paper_size">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem v-for="ps in PAPER_SIZES" :key="ps.value" :value="ps.value">{{ ps.label }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.orientation') }}</Label>
                  <Select v-model="draft.paper_orientation">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem value="portrait">{{ t('preferences.invoice_designer.portrait') }}</SelectItem>
                      <SelectItem value="landscape">{{ t('preferences.invoice_designer.landscape') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.language') }}</Label>
                  <Select v-model="draft.language">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem value="en">English</SelectItem>
                      <SelectItem value="fa">Dari (دری)</SelectItem>
                      <SelectItem value="ps">Pashto (پښتو)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.direction') }}</Label>
                  <Select v-model="draft.direction">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem value="ltr">{{ t('preferences.invoice_designer.ltr') }}</SelectItem>
                      <SelectItem value="rtl">{{ t('preferences.invoice_designer.rtl') }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div>
                <Label class="text-xs mb-2 block">{{ t('preferences.invoice_designer.margins') }}</Label>
                <div class="grid grid-cols-4 gap-2">
                  <div class="space-y-0.5">
                    <Label class="text-[10px] text-muted-foreground">Top</Label>
                    <Input v-model.number="draft.margins.top" type="number" min="0" max="50" class="h-8" />
                  </div>
                  <div class="space-y-0.5">
                    <Label class="text-[10px] text-muted-foreground">Right</Label>
                    <Input v-model.number="draft.margins.right" type="number" min="0" max="50" class="h-8" />
                  </div>
                  <div class="space-y-0.5">
                    <Label class="text-[10px] text-muted-foreground">Bottom</Label>
                    <Input v-model.number="draft.margins.bottom" type="number" min="0" max="50" class="h-8" />
                  </div>
                  <div class="space-y-0.5">
                    <Label class="text-[10px] text-muted-foreground">Left</Label>
                    <Input v-model.number="draft.margins.left" type="number" min="0" max="50" class="h-8" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── 5. Appearance ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('appearance')"
            >
              <Type class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.colors_fonts') }}
              <ChevronDown v-if="openSections.appearance" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.appearance" class="border-t px-4 py-4 space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.bg_color') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.appearance.bg_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.appearance.bg_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.text_color') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.appearance.font_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.appearance.font_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
                <div class="space-y-1 col-span-2">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.font_family') }}</Label>
                  <Select v-model="draft.appearance.font_family">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent v-highlight-search="searchHighlightContext">
                      <SelectItem v-for="f in FONT_FAMILIES" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.font_size') }}</Label>
                  <Input v-model.number="draft.appearance.font_size" type="number" min="8" max="30" />
                </div>
              </div>

              <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm cursor-pointer select-none">
                  <Checkbox :checked="draft.appearance.border_show" @update:checked="v => draft.appearance.border_show = v" />
                  {{ t('preferences.invoice_designer.borders') }}
                </label>
                <div v-if="draft.appearance.border_show" class="grid grid-cols-2 gap-4">
                  <div class="space-y-1">
                    <Label class="text-xs">{{ t('preferences.invoice_designer.border_color') }}</Label>
                    <div class="flex gap-2 items-center">
                      <input type="color" v-model="draft.appearance.border_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                      <Input v-model="draft.appearance.border_color" class="font-mono text-xs h-8" maxlength="7" />
                    </div>
                  </div>
                  <div class="space-y-1">
                    <Label class="text-xs">{{ t('preferences.invoice_designer.border_width') }}</Label>
                    <Input v-model.number="draft.appearance.border_width" type="number" min="0" max="5" />
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.summary_bg') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.appearance.summary_bg_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.appearance.summary_bg_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs">{{ t('preferences.invoice_designer.summary_text') }}</Label>
                  <div class="flex gap-2 items-center">
                    <input type="color" v-model="draft.appearance.summary_text_color" class="h-8 w-12 cursor-pointer rounded border p-0.5" />
                    <Input v-model="draft.appearance.summary_text_color" class="font-mono text-xs h-8" maxlength="7" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── 6. Advanced ── -->
          <div class="rounded-lg border">
            <button
              class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium hover:bg-accent/50 transition-colors"
              @click="toggleSection('advanced')"
            >
              <Settings class="h-4 w-4 text-muted-foreground" />
              {{ t('preferences.invoice_designer.advanced') }}
              <ChevronDown v-if="openSections.advanced" class="ml-auto h-4 w-4 text-muted-foreground" />
              <ChevronRight v-else class="ml-auto h-4 w-4 text-muted-foreground" />
            </button>
            <div v-if="openSections.advanced" class="border-t px-4 py-4 space-y-4">
              <div class="space-y-1">
                <Label class="text-xs">{{ t('preferences.invoice_designer.watermark') }}</Label>
                <Input v-model="draft.watermark_text" placeholder="Leave blank for no watermark" maxlength="50" />
              </div>
              <div class="space-y-1">
                <Label class="text-xs">{{ t('preferences.invoice_designer.custom_css') }}</Label>
                <Textarea
                  v-model="draft.custom_css"
                  rows="6"
                  class="font-mono text-xs"
                  placeholder=".ci-table { font-size: 12px; }"
                />
              </div>
            </div>
          </div>

        </div><!-- end accordion -->
      </div>
    </div>
  </div>

  <!-- ── Preview Modal ── -->
  <Dialog v-model:open="previewOpen">
    <DialogContent class="max-w-5xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle>{{ t('preferences.invoice_designer.preview_title') }} — {{ draft.name || 'Untitled' }}</DialogTitle>
        <DialogDescription>
          {{ t('preferences.invoice_designer.preview_sample') }} · {{ paperLabel }}
        </DialogDescription>
      </DialogHeader>
      <!-- Paper wrapper: mimics physical paper dimensions scaled to fit -->
      <div class="mt-3 overflow-x-auto">
        <div :style="paperStyle">
          <CustomInvoiceLayout
            :invoice="sampleInvoice"
            :company="{ name_en: 'Your Company Name', address: 'Kabul, Afghanistan', phone: '+93 700 000 000' }"
            :custom-format="previewFormat"
          />
        </div>
      </div>
    </DialogContent>
  </Dialog>

  <!-- ── Delete confirmation ── -->
  <Dialog v-model:open="delConfirm">
    <DialogContent class="max-w-sm">
      <DialogHeader>
        <DialogTitle>{{ t('preferences.invoice_designer.delete_title') }}</DialogTitle>
        <DialogDescription>{{ t('preferences.invoice_designer.delete_confirm', { name: draft.name }) }}</DialogDescription>
      </DialogHeader>
      <div class="flex justify-end gap-2 mt-4">
        <Button variant="outline" @click="delConfirm = false">{{ t('preferences.invoice_designer.delete_cancel') }}</Button>
        <Button variant="destructive" @click="doDelete">{{ t('preferences.invoice_designer.delete_confirm_btn') }}</Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
