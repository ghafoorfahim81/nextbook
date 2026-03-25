<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  invoice: { type: Object, default: () => ({}) },
  company: { type: Object, default: () => ({}) },
  invoiceTheme: { type: String, default: 'format1' },
})

const page = usePage()
const { t, locale } = useI18n()

const supportedThemes = ['format1', 'format2', 'format3', 'format4', 'format5']
const themeId = computed(() => supportedThemes.includes(props.invoiceTheme) ? props.invoiceTheme : 'format1')
const isRTL = computed(() => ['fa', 'ps', 'pa', 'ar', 'ur'].includes(locale.value))
const direction = computed(() => (isRTL.value ? 'rtl' : 'ltr'))
const numberLocale = computed(() => {
  if (locale.value === 'fa') return 'fa-IR'
  if (locale.value === 'ps') return 'fa-AF'
  return 'en-US'
})

const toNumber = (value) => {
  const parsed = Number(value ?? 0)
  return Number.isFinite(parsed) ? parsed : 0
}

const preferences = computed(() => page.props?.user_preferences ?? {})
const salePreferences = computed(() => preferences.value?.sale ?? {})
const decimalPlaces = computed(() => preferences.value?.appearance?.decimal_places ?? 2)

const formatNumber = (value) => {
  return new Intl.NumberFormat(numberLocale.value, {
    minimumFractionDigits: decimalPlaces.value,
    maximumFractionDigits: decimalPlaces.value,
  }).format(toNumber(value))
}

const companyName = computed(() => {
  if (locale.value === 'ps') {
    return props.company?.name_pa || props.company?.name_fa || props.company?.name_en || 'Nextbook'
  }

  if (locale.value === 'fa') {
    return props.company?.name_fa || props.company?.name_pa || props.company?.name_en || 'Nextbook'
  }

  return props.company?.name_en || props.company?.name_fa || props.company?.name_pa || 'Nextbook'
})

const companyAddress = computed(() => {
  return [
    props.company?.address,
    props.company?.city,
    props.company?.country,
  ].filter(Boolean).join(', ') || t('invoice.not_available')
})

const companyInitial = computed(() => companyName.value?.trim()?.charAt(0)?.toUpperCase() || 'N')
const companyLogo = computed(() => props.company?.logo_url || null)
const customer = computed(() => props.invoice?.customer ?? {})
const customerName = computed(() => customer.value?.name || t('invoice.no_customer'))
const customerAddress = computed(() => customer.value?.address || t('invoice.not_available'))
const customerPhone = computed(() => customer.value?.phone || t('invoice.not_available'))
const customerEmail = computed(() => customer.value?.email || t('invoice.not_available'))
const currencyLabel = computed(() => {
  return props.invoice?.transaction?.currency?.code
    || props.invoice?.transaction?.currency?.name
    || t('invoice.not_available')
})
const storeName = computed(() => props.invoice?.warehouse?.name || t('invoice.not_available'))
const invoicePrefix = computed(() => salePreferences.value?.invoice_prefix || 'INV-')
const invoiceNumberText = computed(() => `${invoicePrefix.value}${props.invoice?.number ?? ''}`)
const issueDateText = computed(() => props.invoice?.date || t('invoice.not_available'))
const dueDateText = computed(() => props.invoice?.due_date || props.invoice?.date || t('invoice.not_available'))
const notesText = computed(() => props.invoice?.description || t('invoice.notes_placeholder'))
const termsText = computed(() => salePreferences.value?.terms || props.company?.invoice_description || t('invoice.terms_placeholder'))

const rows = computed(() => {
  return (props.invoice?.items ?? []).map((item, index) => {
    const quantity = toNumber(item.quantity)
    const unitPrice = toNumber(item.unit_price)
    const discount = toNumber(item.discount)
    const tax = toNumber(item.tax)
    const baseTotal = quantity * unitPrice

    return {
      id: item.id ?? `row-${index}`,
      row: index + 1,
      name: item.item_name || item.item?.name || t('invoice.item'),
      code: item.item_code || item.item?.code || '-',
      unit: item.unit_measure_name || t('invoice.unit'),
      quantity,
      unitPrice,
      discount,
      tax,
      baseTotal,
      total: baseTotal - discount + tax,
    }
  })
})

const minimumRows = computed(() => {
  return {
    format1: 4,
    format2: 6,
    format3: 5,
    format4: 6,
    format5: 12,
  }[themeId.value] ?? 5
})

const fillerRows = computed(() => {
  return Array.from({ length: Math.max(0, minimumRows.value - rows.value.length) }, (_, index) => index)
})

const subtotal = computed(() => rows.value.reduce((sum, item) => sum + item.baseTotal, 0))
const itemDiscountTotal = computed(() => rows.value.reduce((sum, item) => sum + item.discount, 0))
const billDiscount = computed(() => toNumber(props.invoice?.discount))
const totalDiscount = computed(() => itemDiscountTotal.value + billDiscount.value)
const taxTotal = computed(() => rows.value.reduce((sum, item) => sum + item.tax, 0))
const grandTotal = computed(() => subtotal.value - totalDiscount.value + taxTotal.value)

const topSummary = computed(() => [
  { label: t('invoice.invoice_number'), value: invoiceNumberText.value },
  { label: t('invoice.issue_date'), value: issueDateText.value },
  { label: t('invoice.due_date'), value: dueDateText.value },
  { label: t('invoice.currency'), value: currencyLabel.value },
  { label: t('invoice.store'), value: storeName.value },
])

const summaryRows = computed(() => [
  { label: t('invoice.subtotal'), value: formatNumber(subtotal.value) },
  { label: t('invoice.discount'), value: formatNumber(totalDiscount.value) },
  { label: t('invoice.tax_total'), value: formatNumber(taxTotal.value) },
  { label: t('invoice.total_due'), value: formatNumber(grandTotal.value), strong: true },
])
</script>

<template>
  <div class="invoice-print-root" :dir="direction">
    <section
      v-if="themeId === 'format1'"
      class="print-surface theme-format1 space-y-6"
      :class="isRTL ? 'text-right' : 'text-left'"
    >
      <header class="flex items-start justify-between gap-6 border-b-2 border-slate-300 pb-6">
        <div class="flex items-center gap-4">
          <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-16 w-16 rounded-xl border border-slate-200 object-cover" />
          <div v-else class="flex h-16 w-16 items-center justify-center rounded-xl bg-slate-700 text-2xl font-bold text-white">
            {{ companyInitial }}
          </div>

          <div class="space-y-1">
            <h1 class="text-2xl font-semibold text-slate-900">{{ companyName }}</h1>
            <p class="text-sm text-slate-500">{{ companyAddress }}</p>
            <p class="text-sm text-slate-500">{{ props.company?.phone || t('invoice.not_available') }}</p>
          </div>
        </div>

        <div class="min-w-[220px] space-y-4">
          <div class="bg-slate-700 px-5 py-3 text-center text-4xl font-bold uppercase tracking-[0.25em] text-white">
            {{ t('invoice.invoice') }}
          </div>

          <div class="space-y-1 text-sm text-slate-700">
            <div v-for="entry in topSummary.slice(0, 2)" :key="entry.label" class="flex justify-between gap-4">
              <span class="font-semibold">{{ entry.label }}</span>
              <span>{{ entry.value }}</span>
            </div>
          </div>
        </div>
      </header>

      <div class="grid grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-300 p-4">
          <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('invoice.bill_from') }}</p>
          <div class="space-y-1 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">{{ companyName }}</p>
            <p>{{ companyAddress }}</p>
            <p>{{ props.company?.phone || t('invoice.not_available') }}</p>
            <p>{{ props.company?.email || t('invoice.not_available') }}</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-300 p-4">
          <p class="mb-3 text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">{{ t('invoice.bill_to') }}</p>
          <div class="space-y-1 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">{{ customerName }}</p>
            <p>{{ customerAddress }}</p>
            <p>{{ customerPhone }}</p>
            <p>{{ customerEmail }}</p>
          </div>
        </div>
      </div>

      <table class="invoice-grid-table">
        <thead class="bg-slate-100">
          <tr>
            <th>{{ t('invoice.item') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.rate') }}</th>
            <th>{{ t('invoice.tax') }}</th>
            <th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ formatNumber(row.quantity) }} {{ row.unit }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.tax) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f1-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-[1fr_260px] gap-8">
        <div class="space-y-3">
          <h2 class="text-base font-semibold text-slate-900">{{ t('invoice.terms_conditions') }}</h2>
          <p class="min-h-[120px] rounded-xl border border-slate-300 p-4 text-sm leading-6 text-slate-600">{{ termsText }}</p>
        </div>

        <div class="space-y-2">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-1 text-sm">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span :class="entry.strong ? 'text-lg font-semibold text-slate-900' : 'font-medium text-slate-700'">{{ entry.value }}</span>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <div class="min-w-[280px] bg-slate-700 px-6 py-3 text-white">
          <div class="flex items-center justify-between text-3xl font-semibold">
            <span>{{ t('invoice.total') }}</span>
            <span>{{ formatNumber(grandTotal) }}</span>
          </div>
        </div>
      </div>
    </section>

    <section
      v-else-if="themeId === 'format2'"
      class="print-surface theme-format2 space-y-8"
      :class="isRTL ? 'text-right' : 'text-left'"
    >
      <header class="flex items-start justify-between gap-6">
        <div class="space-y-4">
          <h1 class="text-5xl font-semibold tracking-tight text-black">{{ t('invoice.invoice') }}</h1>
          <div class="grid grid-cols-2 gap-10 text-sm text-slate-500">
            <div class="space-y-1">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.bill_from') }}</p>
              <p class="font-semibold text-slate-900">{{ companyName }}</p>
              <p>{{ companyAddress }}</p>
              <p>{{ props.company?.phone || t('invoice.not_available') }}</p>
            </div>
            <div class="space-y-1">
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.bill_to') }}</p>
              <p class="font-semibold text-slate-900">{{ customerName }}</p>
              <p>{{ customerAddress }}</p>
              <p>{{ customerPhone }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="flex h-16 w-40 items-center justify-center border border-slate-500 bg-white">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-contain p-2" />
            <span v-else class="text-sm font-medium text-slate-500">Logo</span>
          </div>

          <div class="space-y-1 text-sm">
            <div v-for="entry in topSummary.slice(0, 3)" :key="entry.label" class="flex justify-between gap-5">
              <span class="text-slate-400">{{ entry.label }}</span>
              <span class="font-semibold text-slate-900">{{ entry.value }}</span>
            </div>
          </div>
        </div>
      </header>

      <table class="invoice-grid-table invoice-peach-table">
        <thead>
          <tr>
            <th class="w-[45%]">{{ t('invoice.description') }}</th>
            <th>{{ t('invoice.price') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f2-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-[1fr_240px] gap-8">
        <div class="space-y-6">
          <div class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900">{{ t('invoice.payment_terms') }}</h2>
            <p class="text-sm leading-6 text-slate-500">{{ termsText }}</p>
          </div>
          <div class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900">{{ t('invoice.notes') }}</h2>
            <p class="text-sm leading-6 text-slate-500">{{ notesText }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span class="font-medium text-slate-900">{{ entry.value }}</span>
          </div>
        </div>
      </div>
    </section>

    <section
      v-else-if="themeId === 'format3'"
      class="print-surface theme-format3 space-y-8"
      :class="isRTL ? 'text-right' : 'text-left'"
    >
      <header class="flex items-start justify-between gap-6 border-b-4 border-slate-600 pb-5">
        <div class="flex items-start gap-4">
          <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-16 w-16 rounded-2xl border border-slate-200 object-cover" />
          <div v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-500 text-2xl font-bold text-white">
            {{ companyInitial }}
          </div>

          <div class="space-y-1 text-sm text-slate-500">
            <p class="font-semibold text-slate-900">{{ companyName }}</p>
            <p>{{ companyAddress }}</p>
            <p>{{ props.company?.website || props.company?.email || t('invoice.not_available') }}</p>
          </div>
        </div>

        <div class="space-y-1 text-sm text-slate-500">
          <div class="flex justify-between gap-4">
            <span>{{ t('invoice.invoice_number') }}</span>
            <span class="font-semibold text-slate-900">{{ invoiceNumberText }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span>{{ t('invoice.issue_date') }}</span>
            <span class="font-semibold text-slate-900">{{ issueDateText }}</span>
          </div>
          <div class="flex justify-between gap-4">
            <span>{{ t('invoice.due_date') }}</span>
            <span class="font-semibold text-slate-900">{{ dueDateText }}</span>
          </div>
        </div>
      </header>

      <div class="space-y-2">
        <h1 class="text-4xl font-semibold text-slate-900">{{ companyName }}</h1>
        <p class="text-sm text-slate-500">{{ t('invoice.message_for_customer') }}</p>
      </div>

      <div class="grid grid-cols-3 gap-4 text-sm">
        <div class="rounded-xl border border-slate-200 p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.bill_to') }}</p>
          <div class="space-y-1 text-slate-600">
            <p class="font-semibold text-slate-900">{{ customerName }}</p>
            <p>{{ customerEmail }}</p>
            <p>{{ customerPhone }}</p>
            <p>{{ customerAddress }}</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.details') }}</p>
          <div class="space-y-1 text-slate-600">
            <p>{{ notesText }}</p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.payment') }}</p>
          <div class="space-y-1 text-slate-600">
            <p>{{ t('invoice.due_date') }} {{ dueDateText }}</p>
            <p>{{ t('invoice.currency') }}: {{ currencyLabel }}</p>
            <p>{{ t('invoice.total_due') }}: {{ formatNumber(grandTotal) }}</p>
          </div>
        </div>
      </div>

      <table class="invoice-grid-table invoice-soft-table">
        <thead>
          <tr>
            <th>{{ t('invoice.item') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.price') }}</th>
            <th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>
              <div class="font-medium text-slate-800">{{ row.name }}</div>
              <div class="text-xs text-slate-400">{{ row.code }}</div>
            </td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f3-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="ml-auto w-full max-w-[320px] space-y-2">
        <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
          <span class="text-slate-600">{{ entry.label }}</span>
          <span :class="entry.strong ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'">{{ entry.value }}</span>
        </div>
      </div>

      <footer class="mt-auto flex items-end justify-between pt-10 text-xs text-slate-400">
        <div>
          <p class="font-medium text-slate-600">{{ t('invoice.thank_you') }}</p>
          <p>{{ t('invoice.terms_conditions') }}: {{ termsText }}</p>
        </div>
        <div>{{ t('invoice.page') }} 1</div>
      </footer>
    </section>

    <section
      v-else-if="themeId === 'format4'"
      class="print-surface theme-format4 space-y-6"
      :class="isRTL ? 'text-right' : 'text-left'"
    >
      <header class="grid grid-cols-[1fr_260px] gap-8">
        <div class="space-y-4">
          <div class="flex items-center gap-4">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-20 w-20 border border-slate-200 object-cover" />
            <div v-else class="flex h-20 w-20 items-center justify-center bg-slate-100 text-3xl font-bold text-slate-400">
              {{ companyInitial }}
            </div>
            <div class="space-y-1 text-sm text-slate-600">
              <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">{{ t('invoice.bill_to') }}</p>
              <p class="text-lg font-semibold text-slate-900">{{ customerName }}</p>
              <p>{{ customerAddress }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-3 text-right">
          <div>
            <p class="text-4xl font-light uppercase tracking-[0.15em] text-slate-900">{{ t('invoice.invoice') }}</p>
            <p class="text-base font-semibold text-slate-700">{{ companyName }}</p>
            <p class="text-sm text-slate-500">{{ companyAddress }}</p>
          </div>

          <div class="space-y-2 bg-slate-100 p-4 text-sm">
            <div v-for="entry in topSummary.slice(0, 4)" :key="entry.label" class="flex items-center justify-between gap-4">
              <span class="text-slate-500">{{ entry.label }}</span>
              <span class="font-medium text-slate-900">{{ entry.value }}</span>
            </div>
          </div>
        </div>
      </header>

      <table class="invoice-grid-table invoice-dark-header-table">
        <thead>
          <tr>
            <th class="w-[40%]">{{ t('invoice.description') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.unit_cost') || t('invoice.unit_price') }}</th>
            <th>{{ t('invoice.tax') }}</th>
            <th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.tax) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f4-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-[1fr_220px] gap-8">
        <div class="space-y-3">
          <h2 class="text-3xl font-semibold uppercase tracking-[0.08em] text-slate-700">{{ t('invoice.notes') }}:</h2>
          <div class="min-h-[160px] bg-slate-50 p-4 text-sm leading-6 text-slate-600">
            <p>{{ t('invoice.thank_you') }}</p>
            <p class="mt-4">{{ notesText }}</p>
          </div>
        </div>

        <div class="space-y-2">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between py-1 text-sm">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span :class="entry.strong ? 'bg-slate-100 px-3 py-1 font-semibold text-slate-900' : 'font-medium text-slate-800'">
              {{ entry.value }}
            </span>
          </div>
        </div>
      </div>

      <footer class="mt-auto flex items-end justify-between pt-10 text-xs text-slate-400">
        <div>{{ props.company?.website || props.company?.email || companyName }}</div>
        <div>{{ t('invoice.page') }} 1 / 1</div>
      </footer>
    </section>

    <section
      v-else
      class="print-surface theme-format5 space-y-5"
      :class="isRTL ? 'text-right' : 'text-left'"
    >
      <header class="flex items-start justify-between text-xs text-slate-600">
        <span>{{ issueDateText }}</span>
        <span class="font-semibold">{{ companyName }}</span>
        <span>{{ props.company?.phone || props.company?.website || '' }}</span>
      </header>

      <div class="flex items-start justify-between gap-6">
        <div class="space-y-2">
          <div class="h-16 w-16 overflow-hidden rounded-md border border-slate-200 bg-white">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-cover" />
            <div v-else class="flex h-full w-full items-center justify-center bg-slate-700 text-2xl font-bold text-white">
              {{ companyInitial }}
            </div>
          </div>
        </div>

        <div class="flex-1 text-center">
          <h1 class="text-3xl font-semibold text-sky-700">{{ companyName }}</h1>
          <p class="mt-1 text-sm text-slate-500">{{ t('invoice.sale_invoice') }}</p>
        </div>

        <div class="w-16"></div>
      </div>

      <div class="flex h-1.5 overflow-hidden rounded-full">
        <div class="w-1/2 bg-sky-500"></div>
        <div class="w-1/2 bg-rose-500"></div>
      </div>

      <div class="grid grid-cols-2 gap-6 text-sm">
        <div class="space-y-1">
          <p class="font-semibold text-slate-900">{{ t('invoice.invoice_number') }}: {{ invoiceNumberText }}</p>
          <p class="font-semibold text-slate-900">{{ t('invoice.date') }}: {{ issueDateText }}</p>
        </div>
        <div class="space-y-1 text-right">
          <p class="font-semibold text-slate-900">{{ t('invoice.invoice') }}</p>
          <p class="text-slate-600">{{ t('invoice.store') }}: {{ storeName }}</p>
        </div>
      </div>

      <div class="rounded-md border border-slate-400">
        <div class="bg-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-900">{{ t('invoice.buyer_details') }}</div>
        <div class="grid grid-cols-[220px_1fr] border-t border-slate-300 px-4 py-2 text-sm">
          <span class="font-semibold text-slate-700">{{ t('invoice.customer_name') }}</span>
          <span>{{ customerName }}</span>
        </div>
        <div class="grid grid-cols-[220px_1fr] border-t border-slate-300 px-4 py-2 text-sm">
          <span class="font-semibold text-slate-700">{{ t('invoice.address') }}</span>
          <span>{{ customerAddress }}</span>
        </div>
      </div>

      <table class="invoice-grid-table invoice-classic-table">
        <thead>
          <tr>
            <th>{{ t('invoice.item') }}</th>
            <th>{{ t('invoice.item_code') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.rate') }}</th>
            <th>{{ t('invoice.amount') }}</th>
            <th>{{ t('invoice.discount') }}</th>
            <th>{{ t('invoice.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.name }}</td>
            <td>{{ row.code }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.baseTotal) }}</td>
            <td>{{ formatNumber(row.discount) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f5-filler-${index}`" class="filler-row tall-filler">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
          <tr class="bg-slate-200 font-semibold">
            <td colspan="4">{{ t('invoice.subtotal') }}</td>
            <td>{{ formatNumber(subtotal) }}</td>
            <td>{{ formatNumber(totalDiscount) }}</td>
            <td>{{ formatNumber(grandTotal) }}</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-[1fr_320px] gap-6">
        <div class="rounded-md border border-slate-400 p-4">
          <p class="mb-2 text-sm font-semibold text-slate-900">{{ t('invoice.notes') }}</p>
          <p class="min-h-[96px] text-sm leading-6 text-slate-600">{{ notesText }}</p>
        </div>

        <div class="rounded-md border border-slate-400">
          <div class="grid grid-cols-[1fr_1fr_1fr] border-b border-slate-300 bg-slate-100 text-center text-sm font-semibold text-slate-800">
            <div class="border-r border-slate-300 px-3 py-2">{{ t('invoice.row') || '#' }}</div>
            <div class="border-r border-slate-300 px-3 py-2">{{ t('invoice.payment') }}</div>
            <div class="px-3 py-2">{{ t('invoice.amount') }}</div>
          </div>
          <div class="grid grid-cols-[1fr_1fr_1fr] text-center text-sm">
            <div class="border-r border-slate-300 px-3 py-3">1</div>
            <div class="border-r border-slate-300 px-3 py-3">{{ currencyLabel }}</div>
            <div class="px-3 py-3">{{ formatNumber(grandTotal) }}</div>
          </div>
          <div class="grid grid-cols-[2fr_1fr] border-t border-slate-300 bg-slate-200 text-center text-sm font-semibold">
            <div class="border-r border-slate-300 px-3 py-2">{{ t('invoice.total_due') }}</div>
            <div class="px-3 py-2">{{ formatNumber(grandTotal) }}</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-6 pt-12 text-center text-sm font-semibold text-slate-700">
        <div>{{ t('invoice.signature_sales_manager') }}</div>
        <div>{{ t('invoice.signature_notice') }}</div>
        <div>{{ t('invoice.signature_cashier') }}</div>
      </div>

      <footer class="mt-auto space-y-3 pt-8 text-center text-xs text-slate-500">
        <div class="flex h-1.5 overflow-hidden rounded-full">
          <div class="w-1/2 bg-sky-500"></div>
          <div class="w-1/2 bg-rose-500"></div>
        </div>
        <p class="font-semibold text-sky-700">{{ companyAddress }}</p>
        <p>{{ props.company?.phone || '' }} <span v-if="props.company?.phone && props.company?.email">|</span> {{ props.company?.email || '' }}</p>
      </footer>
    </section>
  </div>
</template>
