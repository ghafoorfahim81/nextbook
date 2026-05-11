<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  invoice:      { type: Object, default: () => ({}) },
  company:      { type: Object, default: () => ({}) },
  customFormat: { type: Object, required: true },
})

const page  = usePage()
const { t, locale } = useI18n()

// ---------- helpers ----------
const toNumber = (v) => { const n = Number(v ?? 0); return Number.isFinite(n) ? n : 0 }

const preferences    = computed(() => page.props?.user_preferences ?? {})
const decimalPlaces  = computed(() => preferences.value?.appearance?.decimal_places ?? 2)
const salePrefs      = computed(() => preferences.value?.sale ?? {})

// ---------- format config with safe defaults ----------
const fmt = computed(() => props.customFormat ?? {})

const hdr  = computed(() => fmt.value.header_config   ?? {})
const cols = computed(() => fmt.value.item_columns     ?? {})
const sec  = computed(() => fmt.value.optional_sections ?? {})
const app  = computed(() => fmt.value.appearance       ?? {})
const mar  = computed(() => fmt.value.margins          ?? { top: 10, right: 10, bottom: 10, left: 10 })

const direction  = computed(() => fmt.value.direction ?? 'ltr')
const isRTL      = computed(() => direction.value === 'rtl')
const numLocale  = computed(() => {
  const lang = fmt.value.language ?? locale.value
  return lang === 'fa' ? 'fa-IR' : lang === 'ps' ? 'fa-AF' : 'en-US'
})

const formatNum = (v) => new Intl.NumberFormat(numLocale.value, {
  minimumFractionDigits: decimalPlaces.value,
  maximumFractionDigits: decimalPlaces.value,
}).format(toNumber(v))

// ---------- company ----------
const companyName = computed(() => {
  const lang = fmt.value.language ?? locale.value
  if (lang === 'ps') return props.company?.name_pa || props.company?.name_fa || props.company?.name_en || ''
  if (lang === 'fa') return props.company?.name_fa || props.company?.name_pa || props.company?.name_en || ''
  return props.company?.name_en || props.company?.name_fa || ''
})
const companyAddress = computed(() => [props.company?.address, props.company?.city, props.company?.country].filter(Boolean).join(', '))
const companyPhone   = computed(() => props.company?.phone || '')
const companyInitial = computed(() => companyName.value?.trim()?.charAt(0)?.toUpperCase() || 'N')
const companyLogo    = computed(() => {
  const url = props.company?.logo_url
  if (url) return url
  const logo = props.company?.logo
  if (!logo) return null
  if (typeof logo === 'string') {
    if (/^https?:\/\//i.test(logo) || logo.startsWith('data:')) return logo
    return `/storage/${String(logo).replace(/^\/+/, '')}`
  }
  return logo?.url || null
})

// ---------- invoice ----------
const invoicePrefix = computed(() => salePrefs.value?.invoice_prefix || 'INV-')
const invoiceNumber = computed(() => `${invoicePrefix.value}${props.invoice?.number ?? ''}`)
const issueDate     = computed(() => props.invoice?.date ?? '')
const dueDate       = computed(() => props.invoice?.due_date || props.invoice?.date || '')
const customerName  = computed(() => props.invoice?.customer?.name ?? '')
const customerAddr  = computed(() => props.invoice?.customer?.address ?? '')
const customerPhone = computed(() => props.invoice?.customer?.phone ?? '')
const storeName     = computed(() => props.invoice?.warehouse?.name ?? '')
const notesText     = computed(() => props.invoice?.description ?? '')
const termsText     = computed(() => salePrefs.value?.terms ?? '')

// ---------- rows ----------
const COLUMN_DEFS = [
  { key: 'row',        label: '#' },
  { key: 'code',       label: computed(() => t('invoice.code', 'Code')) },
  { key: 'name',       label: computed(() => t('invoice.item', 'Item')) },
  { key: 'unit',       label: computed(() => t('invoice.unit', 'Unit')) },
  { key: 'quantity',   label: computed(() => t('invoice.quantity', 'Qty')) },
  { key: 'unit_price', label: computed(() => t('invoice.rate', 'Rate')) },
  { key: 'discount',   label: computed(() => t('invoice.discount', 'Discount')) },
  { key: 'tax',        label: computed(() => t('invoice.tax', 'Tax')) },
  { key: 'total',      label: computed(() => t('invoice.amount', 'Amount')) },
]

// When tax_display = 'grouped', hide per-item tax column even if selected
const taxIsGrouped = computed(() => sec.value.tax_display === 'grouped')

const visibleCols = computed(() => {
  const visible = cols.value.visible ?? ['row', 'name', 'quantity', 'unit_price', 'discount', 'total']
  return COLUMN_DEFS.filter(c => {
    if (c.key === 'tax' && taxIsGrouped.value) return false
    return visible.includes(c.key)
  })
})

const rows = computed(() => (props.invoice?.items ?? []).map((item, index) => {
  const quantity  = toNumber(item.quantity)
  const unitPrice = toNumber(item.unit_price)
  const discount  = toNumber(item.discount)
  const tax       = toNumber(item.tax)
  const baseTotal = quantity * unitPrice
  return {
    row:        index + 1,
    code:       item.item_code || item.item?.code || '-',
    name:       item.item_name || item.item?.name || '',
    unit:       item.unit_measure_name || item.unit_measure?.name || '',
    quantity,
    unit_price: unitPrice,
    discount,
    tax,
    total:      baseTotal - discount,
    id:         item.id ?? `r${index}`,
  }
}))

// Pagination — split rows into pages
const itemsPerPage = computed(() => toNumber(sec.value.items_per_page) || 0)
const pages = computed(() => {
  if (!itemsPerPage.value) return [rows.value]
  const chunks = []
  for (let i = 0; i < rows.value.length; i += itemsPerPage.value) {
    chunks.push(rows.value.slice(i, i + itemsPerPage.value))
  }
  return chunks.length ? chunks : [[]]
})

// ---------- totals ----------
const subtotal          = computed(() => rows.value.reduce((s, r) => s + r.quantity * r.unit_price, 0))
const itemDiscountTotal = computed(() => rows.value.reduce((s, r) => s + r.discount, 0))
const billDiscRaw       = computed(() => toNumber(props.invoice?.discount))
const billDiscAmt       = computed(() => props.invoice?.discount_type === 'percentage' ? subtotal.value * (billDiscRaw.value / 100) : billDiscRaw.value)
const totalDiscount     = computed(() => itemDiscountTotal.value + billDiscAmt.value)
const taxTotal          = computed(() => rows.value.reduce((s, r) => s + r.tax, 0))
const grandTotal        = computed(() => subtotal.value - totalDiscount.value)
const remaining         = computed(() => toNumber(props.invoice?.remaining_amount ?? props.invoice?.receivable_amount))
const oldBalance        = computed(() => toNumber(props.invoice?.old_balance))

const summaryRows = computed(() => {
  const items = []
  if (sec.value.show_summary_subtotal !== false) items.push({ label: t('invoice.subtotal', 'Subtotal'), value: formatNum(subtotal.value) })
  if (sec.value.show_summary_discount !== false) items.push({ label: t('invoice.discount', 'Discount'), value: formatNum(totalDiscount.value) })
  // Show tax: either when explicitly enabled, or when tax_display=grouped (always show then)
  if (sec.value.show_summary_tax || taxIsGrouped.value) {
    items.push({ label: t('invoice.tax', 'Tax'), value: formatNum(taxTotal.value) })
  }
  if (oldBalance.value) items.push({ label: t('invoice.old_balance', 'Old Balance'), value: formatNum(oldBalance.value) })
  items.push({ label: t('invoice.total_due', 'Total Due'), value: formatNum(grandTotal.value + oldBalance.value), bold: true })
  return items
})

// ---------- CSS variables from format config ----------
const borderValue = computed(() => {
  if (app.value.border_show === false) return 'none'
  return `${app.value.border_width || 1}px solid ${app.value.border_color || '#cbd5e1'}`
})

const cssVars = computed(() => ({
  '--inv-bg':           app.value.bg_color       || '#ffffff',
  '--inv-font':         app.value.font_family     || 'sans-serif',
  '--inv-font-size':    `${app.value.font_size    || 14}px`,
  '--inv-font-color':   app.value.font_color      || '#0f172a',
  // Outer table border (controlled by appearance.border_show)
  '--inv-border':       borderValue.value,
  // Per-cell borders (controlled by item_columns.show_borders)
  '--inv-cell-border':  cols.value.show_borders !== false ? borderValue.value : 'none',
  '--inv-th-bg':        cols.value.header_bg_color   || '#1e293b',
  '--inv-th-color':     cols.value.header_text_color || '#ffffff',
  '--inv-th-fs':        `${cols.value.header_font_size || 13}px`,
  '--inv-td-fs':        `${cols.value.row_font_size   || 13}px`,
  '--inv-sum-bg':       app.value.summary_bg_color    || '#f1f5f9',
  '--inv-sum-color':    app.value.summary_text_color  || '#0f172a',
  '--inv-mt':           `${mar.value.top    || 10}mm`,
  '--inv-mr':           `${mar.value.right  || 10}mm`,
  '--inv-mb':           `${mar.value.bottom || 10}mm`,
  '--inv-ml':           `${mar.value.left   || 10}mm`,
}))

const stripeColor = computed(() => cols.value.stripe_color || '#f8fafc')

const titleText = computed(() => hdr.value.title_text || t('invoice.sale_invoice', 'INVOICE'))
</script>

<template>
  <div class="ci-root" :dir="direction" :style="cssVars">
    <!-- Watermark -->
    <div v-if="fmt.watermark_text" class="ci-watermark" aria-hidden="true">{{ fmt.watermark_text }}</div>

    <!-- Custom CSS injection -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <component :is="'style'" v-if="fmt.custom_css">{{ fmt.custom_css }}</component>

    <template v-for="(pageRows, pageIdx) in pages" :key="`page-${pageIdx}`">
      <section
        class="ci-page"
        :style="pageIdx < pages.length - 1 ? 'break-after: page; page-break-after: always;' : ''"
      >
        <!-- ── HEADER ── -->
        <header class="ci-header">
          <div class="ci-header-top">
            <!-- Logo + Company -->
            <div class="ci-company">
              <template v-if="hdr.show_logo !== false">
                <img
                  v-if="companyLogo"
                  :src="companyLogo"
                  :alt="companyName"
                  class="ci-logo"
                  :style="{ maxHeight: `${hdr.logo_max_height || 64}px` }"
                />
                <div v-else class="ci-logo-placeholder">{{ companyInitial }}</div>
              </template>
              <div class="ci-company-info">
                <p v-if="hdr.show_company_name !== false" class="ci-company-name">{{ companyName }}</p>
                <p v-if="hdr.show_company_address && companyAddress" class="ci-company-detail">{{ companyAddress }}</p>
                <p v-if="hdr.show_company_phone && companyPhone" class="ci-company-detail">{{ companyPhone }}</p>
              </div>
            </div>

            <!-- Title + Meta -->
            <div class="ci-meta">
              <p class="ci-title">{{ titleText }}</p>
              <div class="ci-meta-rows">
                <div v-if="hdr.show_invoice_number !== false" class="ci-meta-row">
                  <span>{{ t('invoice.invoice_number', 'Invoice #') }}</span>
                  <span dir="ltr">{{ invoiceNumber }}</span>
                </div>
                <div v-if="hdr.show_date !== false" class="ci-meta-row">
                  <span>{{ t('invoice.issue_date', 'Date') }}</span>
                  <span dir="ltr">{{ issueDate }}</span>
                </div>
                <div v-if="hdr.show_due_date && dueDate" class="ci-meta-row">
                  <span>{{ t('invoice.due_date', 'Due Date') }}</span>
                  <span dir="ltr">{{ dueDate }}</span>
                </div>
                <div v-if="hdr.show_store_name && storeName" class="ci-meta-row">
                  <span>{{ t('invoice.store', 'Store') }}</span>
                  <span>{{ storeName }}</span>
                </div>
              </div>
            </div>
          </div>

          <hr class="ci-divider" />

          <!-- Customer info -->
          <div v-if="hdr.show_customer_name !== false || hdr.show_customer_address || hdr.show_customer_phone" class="ci-customer">
            <p v-if="hdr.show_customer_name !== false && customerName" class="ci-customer-name">
              {{ t('ledger.customer.customer', 'Customer') }}: {{ customerName }}
            </p>
            <p v-if="hdr.show_customer_address && customerAddr" class="ci-customer-detail">{{ customerAddr }}</p>
            <p v-if="hdr.show_customer_phone && customerPhone" class="ci-customer-detail">{{ customerPhone }}</p>
          </div>
        </header>

        <!-- ── ITEM TABLE ── -->
        <table class="ci-table">
          <thead>
            <tr>
              <th v-for="col in visibleCols" :key="col.key">
                {{ cols.column_labels?.[col.key] ?? (typeof col.label === 'object' ? col.label.value : col.label) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, rIdx) in pageRows"
              :key="row.id"
              :style="cols.stripe_rows && rIdx % 2 === 1 ? { backgroundColor: stripeColor } : {}"
            >
              <td v-for="col in visibleCols" :key="col.key" :class="col.key === 'name' ? 'ci-td-name' : ''">
                <template v-if="col.key === 'row'">{{ row.row }}</template>
                <template v-else-if="col.key === 'name'">{{ row.name }}</template>
                <template v-else-if="col.key === 'code'">{{ row.code }}</template>
                <template v-else-if="col.key === 'unit'">{{ row.unit }}</template>
                <template v-else>{{ formatNum(row[col.key]) }}</template>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- ── SUMMARY (last page only) ── -->
        <div v-if="pageIdx === pages.length - 1" class="ci-footer-area">
          <div class="ci-summary">
            <div v-for="row in summaryRows" :key="row.label" class="ci-summary-row" :class="{ 'ci-summary-bold': row.bold }">
              <span>{{ row.label }}</span>
              <span dir="ltr">{{ row.value }}</span>
            </div>
          </div>

          <!-- Optional sections -->
          <div v-if="sec.show_notes && notesText" class="ci-section">
            <p class="ci-section-label">{{ t('invoice.notes', 'Notes') }}</p>
            <p class="ci-section-body">{{ notesText }}</p>
          </div>

          <div v-if="sec.show_terms && termsText" class="ci-section">
            <p class="ci-section-label">{{ t('invoice.terms', 'Terms & Conditions') }}</p>
            <p class="ci-section-body">{{ termsText }}</p>
          </div>

          <div v-if="sec.show_bank_details && fmt.bank_details" class="ci-section">
            <p class="ci-section-label">{{ t('invoice.bank_details', 'Bank Details') }}</p>
            <p class="ci-section-body">{{ fmt.bank_details }}</p>
          </div>

          <div v-if="sec.show_thank_you" class="ci-thank-you">
            {{ fmt.thank_you_text || t('invoice.thank_you', 'Thank you for your business!') }}
          </div>

          <div v-if="sec.show_signature" class="ci-signature">
            <div class="ci-signature-line"></div>
            <p>{{ t('invoice.signature', 'Authorized Signature') }}</p>
          </div>

          <div v-if="sec.show_footer && fmt.footer_text" class="ci-footer-text">
            {{ fmt.footer_text }}
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
/* ---------- Custom-format invoice styles ---------- */
.ci-root {
  background: var(--inv-bg);
  color: var(--inv-font-color);
  font-family: var(--inv-font);
  font-size: var(--inv-font-size);
  position: relative;
}

.ci-page {
  padding: var(--inv-mt) var(--inv-mr) var(--inv-mb) var(--inv-ml);
  min-height: 270mm;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* ── Watermark ── */
.ci-watermark {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-35deg);
  font-size: 80px;
  font-weight: 900;
  color: rgba(0, 0, 0, 0.06);
  pointer-events: none;
  white-space: nowrap;
  z-index: 0;
  letter-spacing: 0.1em;
}

/* ── Header ── */
.ci-header {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.ci-header-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
}

.ci-company {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  flex: 1;
}

.ci-logo {
  width: auto;
  max-width: 160px;
  object-fit: contain;
  border-radius: 6px;
}

.ci-logo-placeholder {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  background: #475569;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 22px;
  flex-shrink: 0;
}

.ci-company-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ci-company-name {
  font-size: 1.25em;
  font-weight: 700;
  margin: 0;
}

.ci-company-detail {
  font-size: 0.85em;
  margin: 0;
  opacity: 0.75;
}

.ci-meta {
  text-align: end;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.ci-title {
  font-size: 1.6em;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
}

.ci-meta-rows {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ci-meta-row {
  display: flex;
  gap: 8px;
  font-size: 0.9em;
}

.ci-divider {
  border: none;
  border-top: 2px solid var(--inv-font-color);
  margin: 4px 0;
  opacity: 0.3;
}

.ci-customer {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ci-customer-name {
  font-weight: 600;
  margin: 0;
}

.ci-customer-detail {
  font-size: 0.85em;
  margin: 0;
  opacity: 0.75;
}

/* ── Table ── */
.ci-table {
  width: 100%;
  border-collapse: collapse;
  border: var(--inv-border);
  flex: 1;
}

.ci-table thead tr {
  background: var(--inv-th-bg);
  color: var(--inv-th-color);
}

.ci-table th {
  padding: 8px 10px;
  font-size: var(--inv-th-fs);
  font-weight: 600;
  border: var(--inv-cell-border);
  white-space: nowrap;
}

.ci-table td {
  padding: 6px 10px;
  font-size: var(--inv-td-fs);
  border: var(--inv-cell-border);
  vertical-align: middle;
}

.ci-td-name {
  font-weight: 500;
}

/* ── Footer area ── */
.ci-footer-area {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: auto;
}

.ci-summary {
  align-self: flex-end;
  min-width: 220px;
  background: var(--inv-sum-bg);
  color: var(--inv-sum-color);
  border-radius: 6px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ci-summary-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  font-size: 0.9em;
}

.ci-summary-bold {
  font-weight: 700;
  font-size: 1em;
  border-top: 1px solid currentColor;
  margin-top: 4px;
  padding-top: 4px;
}

.ci-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ci-section-label {
  font-weight: 600;
  font-size: 0.9em;
  margin: 0;
}

.ci-section-body {
  font-size: 0.85em;
  margin: 0;
  white-space: pre-wrap;
  opacity: 0.8;
}

.ci-thank-you {
  text-align: center;
  font-size: 0.95em;
  font-weight: 600;
  opacity: 0.7;
  padding: 6px 0;
}

.ci-signature {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  margin-top: 20px;
}

.ci-signature-line {
  width: 180px;
  border-bottom: 1px solid currentColor;
  opacity: 0.5;
}

.ci-signature p {
  font-size: 0.8em;
  margin: 0;
  opacity: 0.6;
}

.ci-footer-text {
  text-align: center;
  font-size: 0.8em;
  opacity: 0.6;
  white-space: pre-wrap;
}

/* ── RTL tweaks ──
   dir="rtl" already makes flex rows flow right-to-left, so no flex-direction
   reversal is needed. Only adjust alignment for elements that need to anchor
   to the physical start (right) side in RTL.                                */
[dir="rtl"] .ci-summary   { align-self: flex-start; }   /* anchor summary to right */
[dir="rtl"] .ci-signature { align-items: flex-start; }  /* anchor signature to right */

/* ── Print overrides ── */
@media print {
  .ci-root { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .ci-page { padding: var(--inv-mt) var(--inv-mr) var(--inv-mb) var(--inv-ml); }
}
</style>
