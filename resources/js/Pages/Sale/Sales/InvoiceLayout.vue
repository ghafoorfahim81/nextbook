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
const numberLocale = computed(() => locale.value === 'fa' ? 'fa-IR' : locale.value === 'ps' ? 'fa-AF' : 'en-US')

const toNumber = (value) => {
  const parsed = Number(value ?? 0)
  return Number.isFinite(parsed) ? parsed : 0
}

const preferences = computed(() => page.props?.user_preferences ?? {})
const salePreferences = computed(() => preferences.value?.sale ?? {})
const decimalPlaces = computed(() => preferences.value?.appearance?.decimal_places ?? 2)

const formatNumber = (value) => new Intl.NumberFormat(numberLocale.value, {
  minimumFractionDigits: decimalPlaces.value,
  maximumFractionDigits: decimalPlaces.value,
}).format(toNumber(value))

const formatBalanceAmount = (amount, nature) => {
  if (!toNumber(amount)) return formatNumber(0)
  return `${formatNumber(amount)} ${String(nature || '').toUpperCase()}`
}

const companyName = computed(() => {
  if (locale.value === 'ps') return props.company?.name_pa || props.company?.name_fa || props.company?.name_en || 'Nextbook'
  if (locale.value === 'fa') return props.company?.name_fa || props.company?.name_pa || props.company?.name_en || 'Nextbook'
  return props.company?.name_en || props.company?.name_fa || props.company?.name_pa || 'Nextbook'
})

const companyPhone = computed(() => props.company?.phone || props.company?.phone_no || t('invoice.not_available'))
const companyEmail = computed(() => props.company?.email || t('invoice.not_available'))
const companyWebsite = computed(() => props.company?.website || t('invoice.not_available'))
const companyAddress = computed(() => [props.company?.address, props.company?.city, props.company?.country].filter(Boolean).join(', ') || t('invoice.not_available'))
const companyInitial = computed(() => companyName.value?.trim()?.charAt(0)?.toUpperCase() || 'N')
const companyLogo = computed(() => props.company?.logo_url || null)

const customerLabel = computed(() => t('invoice.customer'))



const customer = computed(() => props.invoice?.customer ?? {})
const customerName = computed(() => customer.value?.name)
const customerAddress = computed(() => customer.value?.address )
const customerPhone = computed(() => customer.value?.phone || customer.value?.phone_no )
const customerEmail = computed(() => customer.value?.email )

const currencyLabel = computed(() => props.invoice?.transaction?.currency?.code || props.invoice?.transaction?.currency?.name || t('invoice.not_available'))
const storeName = computed(() => props.invoice?.warehouse?.name || t('invoice.not_available'))
const invoicePrefix = computed(() => salePreferences.value?.invoice_prefix || 'INV-')
const invoiceNumberText = computed(() => `${invoicePrefix.value}${props.invoice?.number ?? ''}`)
const issueDateText = computed(() => props.invoice?.date || t('invoice.not_available'))
const dueDateText = computed(() => props.invoice?.due_date || props.invoice?.date || t('invoice.not_available'))
const notesText = computed(() => props.invoice?.description || t('invoice.notes_placeholder'))
const termsText = computed(() => salePreferences.value?.terms || props.company?.invoice_description || '')



const companyDetails = computed(() => [
  { label: t('invoice.address'), value: companyAddress.value },
  { label: t('invoice.phone'), value: companyPhone.value },
  { label: t('invoice.email'), value: companyEmail.value },
])

const rows = computed(() => (props.invoice?.items ?? []).map((item, index) => {
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
    unit: item.unit_measure_name || item.unit_measure?.name || t('invoice.unit'),
    quantity,
    unitPrice,
    discount,
    tax,
    baseTotal,
    total: baseTotal - discount,
  }
}))

const minimumRows = computed(() => ({ format1: 4, format2: 12, format3: 8, format4: 4, format5: 0 }[themeId.value] ?? 4))
const fillerRows = computed(() => Array.from({ length: Math.max(0, minimumRows.value - rows.value.length) }, (_, index) => index))
const format1FillerRows = computed(() => {
  if (themeId.value !== 'format1' || rows.value.length >= 4) {
    return []
  }

  return Array.from({ length: 6 }, (_, index) => index)
})
const subtotal = computed(() => rows.value.reduce((sum, item) => sum + item.baseTotal, 0))
const itemDiscountTotal = computed(() => rows.value.reduce((sum, item) => sum + item.discount, 0))
const billDiscountRaw = computed(() => toNumber(props.invoice?.discount))
const billDiscountAmount = computed(() => props.invoice?.discount_type === 'percentage' ? subtotal.value * (billDiscountRaw.value / 100) : billDiscountRaw.value)
const totalDiscount = computed(() => itemDiscountTotal.value + billDiscountAmount.value)
const invoiceTotal = computed(() => subtotal.value - totalDiscount.value)
const remainingAmount = computed(() => toNumber(props.invoice?.remaining_amount ?? props.invoice?.receivable_amount))
const oldBalance = computed(() => toNumber(props.invoice?.old_balance))
const oldBalanceNature = computed(() => props.invoice?.old_balance_nature || 'dr')
const summaryRows = computed(() => [
  { label: t('invoice.subtotal'), value: formatNumber(subtotal.value) },
  { label: t('invoice.discount'), value: formatNumber(totalDiscount.value) },
  { label: t('invoice.old_balance'), value: formatBalanceAmount(oldBalance.value, oldBalanceNature.value) },
  { label: t('invoice.remaining_amount'), value: formatNumber(remainingAmount.value) },
  { label: t('invoice.total_due'), value: formatNumber(invoiceTotal.value), strong: true },
])

const signatureLabel = computed(() => t('invoice.signature'))
</script>

<template>
  <div class="invoice-print-root" :dir="direction">
    <section v-if="themeId === 'format1'" class="print-surface theme-format1 space-y-5" :class="isRTL ? 'text-right' : 'text-left'">
      <header class="space-y-4 pb-4" dir="ltr">
        <div class="flex items-start gap-10">
          <div class="min-w-0 flex-1 space-y-7">
            <div class="flex items-center gap-4">
              <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-14 w-14 rounded-xl border border-slate-200 object-cover" />
              <div v-else class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-700 text-xl font-bold text-white">{{ companyInitial }}</div>
              <p class="text-[30px] font-semibold text-slate-900" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ companyName }}</p>
            </div>
            <div v-if="!isRTL" class="w-fit space-y-2 text-left text-slate-900">
              <p class="flex items-baseline gap-2 whitespace-nowrap text-[18px] font-semibold" :class="isRTL ? 'justify-end' : 'justify-start'" :dir="isRTL ? 'rtl' : 'ltr'">
                <span>{{ t('invoice.invoice_number') }}:</span>
                <span dir="ltr">{{ invoiceNumberText }}</span>
              </p>
              <p class="flex items-baseline gap-2 whitespace-nowrap text-[18px] font-medium" :class="isRTL ? 'justify-end' : 'justify-start'" :dir="isRTL ? 'rtl' : 'ltr'">
                <span>{{ t('invoice.issue_date') }}:</span>
                <span dir="ltr">{{ issueDateText }}</span>
              </p>
            </div>
          </div>

          <div class="ml-auto shrink-0 pt-1">
            <div class="inline-flex min-w-[340px] justify-center bg-slate-700 px-6 py-2 text-[58px] font-bold uppercase leading-none tracking-[0.08em] text-white">
              {{ t('invoice.invoice') }}
            </div>
            <div v-if="isRTL" class="mt-6 ml-auto w-fit space-y-2 text-right text-slate-900">
              <p class="flex items-baseline justify-end gap-2 whitespace-nowrap text-[18px] font-semibold" dir="rtl">
                <span>{{ t('invoice.invoice_number') }}:</span>
                <span dir="ltr">{{ invoiceNumberText }}</span>
              </p>
              <p class="flex items-baseline justify-end gap-2 whitespace-nowrap text-[18px] font-medium" dir="rtl">
                <span>{{ t('invoice.issue_date') }}:</span>
                <span dir="ltr">{{ issueDateText }}</span>
              </p>
            </div>
          </div>
        </div>

        <div class="border-t-2 border-slate-500"></div>

        <div class="grid grid-cols-2 gap-12 pt-4 text-slate-800">
          <div class="space-y-2" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <p class="text-[20px] font-medium">{{ t('invoice.bill_from') }}:</p>
            <p class="text-[18px]"> {{ companyName }}</p>
            <p class="text-[18px]">{{ companyAddress }}</p>
            <p class="text-[18px]">{{ companyPhone }}</p>
          </div>

          <div class="space-y-2" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <p class="text-[20px] font-medium">{{ t('invoice.bill_to') }}:</p>
            <p class="text-[18px]">{{ t('ledger.customer.customer') }}: {{ customerName }}</p>
            <p class="text-[18px]">{{ t('invoice.address') }}: {{ customerAddress }}</p>
            <p class="text-[18px]">{{ t('invoice.phone') }}: {{ customerPhone }}</p>
          </div>
        </div>
      </header>

      <table class="invoice-grid-table theme-format1-table">
        <thead>
          <tr>
            <th>{{ t('invoice.item') }}</th>
            <th>{{ t('invoice.unit') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.rate') }}</th>
            <th>{{ t('invoice.tax') }}</th>
            <th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ row.unit }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ formatNumber(row.tax) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in format1FillerRows" :key="`f1-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-auto flex items-end justify-between gap-12" dir="ltr" :class="isRTL ? 'flex-row-reverse' : ''">
        <div :class="isRTL ? 'w-[360px] shrink-0 space-y-4' : 'min-w-0 flex-1 space-y-4'">
          <div class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ t('invoice.terms_conditions') }}</h2>
            <p class="text-sm leading-6 text-slate-600" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ termsText }}</p>
          </div>
          <div class="w-full pt-8">
            <div class="border-t border-slate-400"></div>
            <p class="pt-2 text-sm font-medium text-slate-700" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ signatureLabel }}</p>
          </div>
        </div>
        <div class="w-[280px] shrink-0 space-y-2">
          <div class="space-y-2">
            <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-1.5 text-sm" :class="isRTL ? 'flex-row-reverse' : ''">
              <span class="text-slate-600">{{ entry.label }}</span>
              <span :class="entry.strong ? 'text-lg font-semibold text-slate-900' : 'font-medium text-slate-700'">{{ entry.value }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-else-if="themeId === 'format2'" class="print-surface theme-format2 space-y-6" :class="isRTL ? 'text-right' : 'text-left'">
      <header class="space-y-6 pb-2">
        <div class="flex items-start justify-between gap-8" dir="ltr">
          <div class="flex h-20 w-72 items-center justify-center border-2 border-slate-500 bg-white">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-contain p-2" />
            <span v-else class="text-2xl font-semibold text-slate-700">Place logo here</span>
          </div>
          <h1 class="text-7xl font-black tracking-tight text-black" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ t('invoice.invoice') }}</h1>
        </div>

        <div class="flex items-start justify-between gap-8 text-[11px] leading-4 text-slate-300" dir="ltr">
          <div class="min-w-0 flex-1 space-y-1" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <p class="text-[11px] font-semibold uppercase">{{ t('invoice.bill_from') }}:</p>
            <p class="text-lg font-semibold leading-none text-slate-900">{{ companyName }}</p>
            <p class="text-[10px] leading-4">{{ companyAddress }}</p>
            <p class="text-[10px] leading-4">{{ companyPhone }}</p>
          </div>

          <div class="min-w-0 flex-1 space-y-1" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <p class="text-[11px] font-semibold uppercase">{{ t('invoice.bill_to') }}:</p>
            <p class="text-lg font-semibold leading-none text-slate-900">{{ customerName }}</p>
            <p class="text-[10px] leading-4">{{ customerAddress }}</p>
          </div>

          <div class="w-[240px] shrink-0 space-y-1" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <div class="flex items-center justify-between gap-4 whitespace-nowrap">
              <span class="text-[11px] font-semibold">{{ t('invoice.invoice_number') }}:</span>
              <span class="text-lg font-semibold leading-none text-slate-900">{{ invoiceNumberText }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 whitespace-nowrap">
              <span class="text-[11px] font-semibold">{{ t('invoice.issue_date') }}:</span>
              <span class="text-lg font-semibold leading-none text-slate-900">{{ issueDateText }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 whitespace-nowrap">
              <span class="text-[11px] font-semibold">{{ t('invoice.due_date') }}:</span>
              <span class="text-lg font-semibold leading-none text-slate-900">{{ dueDateText }}</span>
            </div>
          </div>
        </div>
      </header>

      <table class="invoice-grid-table invoice-peach-table theme-format2-table">
        <thead>
          <tr>
            <th class="w-[44%]">{{ t('invoice.description') }}</th>
            <th>{{ t('invoice.price') }}</th>
            <th>{{ t('invoice.unit') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
            <td>{{ row.unit }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f2-filler-${index}`" class="filler-row">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
          </tr>
        </tbody>
      </table>

      <div class="grid grid-cols-[1fr_260px] gap-10 items-start border-t border-slate-200 pt-6">
        <div class="space-y-6">
          <div class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900">{{ t('invoice.payment_terms') }}</h2>
            <p class="text-sm leading-6 text-slate-500">{{ termsText }}</p>
          </div>
          <div class="space-y-2">
            <h2 class="text-base font-semibold text-slate-900">{{ t('invoice.notes') }}</h2>
            <p class="text-sm leading-6 text-slate-500">{{ notesText }}</p>
          </div>
          <div class="w-full max-w-[220px] pt-10">
            <div class="border-t border-slate-400"></div>
            <p class="pt-2 text-sm font-medium text-slate-700">{{ signatureLabel }}</p>
          </div>
        </div>
        <div class="space-y-2">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span class="font-medium text-slate-900" :class="entry.strong ? 'text-base font-semibold' : ''">{{ entry.value }}</span>
          </div>
        </div>
      </div>
    </section>

    <section v-else-if="themeId === 'format3'" class="print-surface theme-format3 space-y-8" :class="isRTL ? 'text-right' : 'text-left'">
      <header class="flex items-start justify-between gap-6 border-b-4 border-slate-600 pb-5">
        <div class="flex items-start gap-4">
          <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-16 w-16 rounded-2xl border border-slate-200 object-cover" />
          <div v-else class="flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-500 text-2xl font-bold text-white">{{ companyInitial }}</div>
          <div class="space-y-1 text-sm text-slate-500">
            <p class="font-semibold text-slate-900">{{ companyName }}</p>
            <p>{{ companyAddress }}</p>
            <p>{{ companyWebsite !== t('invoice.not_available') ? companyWebsite : companyEmail }}</p>
          </div>
        </div>
        <div class="space-y-2 text-sm text-slate-500">
          <div class="flex justify-between gap-4"><span>{{ t('invoice.invoice_number') }}</span><span class="font-semibold text-slate-900">{{ invoiceNumberText }}</span></div>
          <div class="flex justify-between gap-4"><span>{{ t('invoice.issue_date') }}</span><span class="font-semibold text-slate-900">{{ issueDateText }}</span></div>
          <div class="flex justify-between gap-4"><span>{{ t('invoice.due_date') }}</span><span class="font-semibold text-slate-900">{{ dueDateText }}</span></div>
        </div>
      </header>
      <div class="grid grid-cols-3 gap-4 text-sm">
        <div class=" border-slate-200 p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.bill_to') }}</p>
          <div class="space-y-1 text-slate-600">
            <!-- <p class="font-semibold text-slate-900">{{ customerName }}</p> -->
            <p class="text-[18px]">{{ t('ledger.customer.customer') }}: {{ customerName }}</p>
            <p class="text-[18px]">{{ t('invoice.address') }}: {{ customerAddress }}</p>
            <p class="text-[18px]">{{ t('invoice.phone') }}: {{ customerPhone }}</p>
            <!-- <p v-for="entry in customerDetails" :key="entry.label"><span class="font-medium text-slate-500">{{ entry.label }}:</span> {{ entry.value }}</p> -->
          </div>
        </div>
        <div class=" border-slate-200 p-4">
          <!-- <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.notes') }}</p>
          <p class="text-sm leading-6 text-slate-600">{{ notesText }}</p> -->
        </div>
        <div class=" border-slate-200 p-4">
          <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">{{ t('invoice.payment') }}</p>
          <div class="space-y-1 text-slate-600">
            <p>{{ t('invoice.due_date') }}: {{ dueDateText }}</p>
            <p>{{ t('invoice.currency') }}: {{ currencyLabel }}</p>
            <p>{{ t('invoice.remaining_amount') }}: {{ formatNumber(remainingAmount) }}</p>
          </div>
        </div>
      </div>
      <table class="invoice-grid-table invoice-soft-table">
        <thead>
          <tr>
            <th>{{ t('invoice.item') }}</th><th>{{ t('invoice.unit') }}</th><th>{{ t('invoice.quantity') }}</th><th>{{ t('invoice.price') }}</th><th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td><div class="font-medium text-slate-800">{{ row.name }}</div><div class="text-xs text-slate-400">{{ row.code }}</div></td>
            <td>{{ row.unit }}</td><td>{{ formatNumber(row.quantity) }}</td><td>{{ formatNumber(row.unitPrice) }}</td><td>{{ formatNumber(row.total) }}</td>
          </tr>
          <tr v-for="index in fillerRows" :key="`f3-filler-${index}`" class="filler-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        </tbody>
      </table>
      <div class="grid grid-cols-[1fr_320px] gap-8 items-start">
        <div class="space-y-4">
          <div class="space-y-2 text-sm text-slate-600">
            <p class="font-medium text-slate-700">{{ t('invoice.terms_conditions') }}</p>
            <p>{{ termsText }}</p>
          </div>
          <div class="w-full max-w-[280px] pt-10"><div class="border-t border-slate-400"></div><p class="pt-2 text-sm font-medium text-slate-700">{{ signatureLabel }}</p></div>
        </div>
        <div class="space-y-2">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-200 py-2 text-sm">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span :class="entry.strong ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'">{{ entry.value }}</span>
          </div>
        </div>
      </div>
    </section>

    <section v-else-if="themeId === 'format4'" class="print-surface theme-format4 space-y-5" :class="isRTL ? 'text-right' : 'text-left'">
      <header class="flex items-start justify-between gap-8" :class="isRTL ? 'flex-row-reverse' : ''" dir="ltr">
        <div class="w-[220px] shrink-0 space-y-4">
          <div class="flex h-20 w-20 items-center justify-center bg-slate-100 text-3xl font-bold text-slate-400" :class="isRTL ? 'ml-auto' : ''">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-cover" />
            <span v-else>{{ companyInitial }}</span>
          </div>

          <div class="space-y-1 text-sm text-slate-700" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">
            <p class="text-[20px] font-black uppercase tracking-wide text-slate-900">{{ t('invoice.bill_to') }}:</p>
            <p class="font-semibold text-slate-900">{{ t('ledger.customer.customer') }}: {{ customerName }}</p>
            <p> {{ t('invoice.address') }}: {{ customerAddress }}</p>
            <p>{{ t('invoice.phone') }}: {{ customerPhone }}</p>
          </div>
        </div>

        <div class="min-w-0 flex-1 space-y-4" :class="isRTL ? 'max-w-[720px] ml-auto' : ''">
          <div class="flex" :class="isRTL ? 'justify-start' : 'justify-end'">
            <div :dir="direction" class="text-right">
              <p class="text-[54px] font-light uppercase leading-none tracking-tight text-slate-900">{{ t('invoice.invoice') }}</p>
              <p class="mt-2 text-lg font-semibold text-slate-900">{{ companyName }}</p>
              <p class="text-sm text-slate-500">{{ companyAddress }}</p>
            </div>
          </div>

          <div class="flex" :class="isRTL ? 'justify-start' : 'justify-end'">
            <div class="w-[320px] bg-slate-100 px-5 py-3 text-sm text-slate-700">
              <div class="flex items-center justify-between gap-4 py-1.5" :dir="isRTL ? 'rtl' : 'ltr'">
                <span class="text-slate-500">{{ t('invoice.invoice_number') }}</span>
                <span class="font-semibold text-slate-900">{{ invoiceNumberText }}</span>
              </div>
              <div class="flex items-center justify-between gap-4 py-1.5" :dir="isRTL ? 'rtl' : 'ltr'">
                <span class="text-slate-500">{{ t('invoice.issue_date') }}</span>
                <span class="font-semibold text-slate-900" dir="ltr">{{ issueDateText }}</span>
              </div>
              <div class="flex items-center justify-between gap-4 py-1.5" :dir="isRTL ? 'rtl' : 'ltr'">
                <span class="text-slate-500">{{ t('invoice.due_date') }}</span>
                <span class="font-semibold text-slate-900" dir="ltr">{{ dueDateText }}</span>
              </div>
              <div class="flex items-center justify-between gap-4 py-1.5" :dir="isRTL ? 'rtl' : 'ltr'">
                <span class="text-slate-500">{{ t('invoice.currency') }}</span>
                <span class="font-semibold text-slate-900">{{ currencyLabel }}</span>
              </div>
            </div>
          </div>
        </div>
      </header>

      <table class="invoice-grid-table invoice-dark-header-table">
        <thead>
          <tr>
            <th class="w-[42%]">{{ t('invoice.description') }}</th>
            <th>{{ t('invoice.unit') }}</th>
            <th>{{ t('invoice.quantity') }}</th>
            <th>{{ t('invoice.unit_price') }}</th>
            <th>{{ t('invoice.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td class="font-medium text-slate-800">{{ row.name }}</td>
            <td>{{ row.unit }}</td>
            <td>{{ formatNumber(row.quantity) }}</td>
            <td>{{ formatNumber(row.unitPrice) }}</td>
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

      <div class="flex justify-end">
        <div class="w-[260px] space-y-1" :dir="direction">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between py-1 text-sm" :class="isRTL ? 'flex-row-reverse' : ''">
            <span class="text-slate-600">{{ entry.label }}</span>
            <span :class="entry.strong ? 'bg-slate-100 px-3 py-1 font-semibold text-slate-900' : 'font-medium text-slate-800'">{{ entry.value }}</span>
          </div>
        </div>
      </div>

      <div class="space-y-3 pt-2">
        <p class="text-2xl font-black uppercase tracking-tight text-slate-900" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ t('invoice.notes') }}:</p>
        <div class="min-h-[120px] bg-slate-100 p-5">
          <p class="text-sm leading-6 text-slate-700" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ notesText }}</p>
          <p class="mt-4 text-sm leading-6 text-slate-600" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ termsText }}</p>
        </div>
        <div class="flex pt-2" :class="isRTL ? 'justify-start' : 'justify-end'">
          <div class="w-full max-w-[240px]">
            <div class="border-t border-slate-400"></div>
            <p class="pt-2 text-sm font-medium text-slate-700" :dir="direction" :class="isRTL ? 'text-right' : 'text-left'">{{ signatureLabel }}</p>
          </div>
        </div>
      </div>
    </section>

    <section v-else class="print-surface theme-format5 space-y-4" :class="isRTL ? 'text-right' : 'text-left'">
      <header class="space-y-3">
        <div class="flex items-start justify-between text-xs text-slate-600">
          <span>{{ issueDateText }}</span>
          <span class="font-semibold">Nextbook</span>
          <div class="h-14 w-28 overflow-hidden border border-slate-200 bg-white">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="h-full w-full object-cover" />
            <div v-else class="flex h-full w-full items-center justify-center bg-slate-700 text-xl font-bold text-white">{{ companyInitial }}</div>
          </div>
        </div>
        <div class="text-center">
          <p class="text-2xl font-semibold text-sky-600">{{ companyName }}</p>
        </div>
        <div class="flex h-1.5 overflow-hidden rounded-full"><div class="w-1/2 bg-sky-500"></div><div class="w-1/2 bg-rose-500"></div></div>
        <div class="grid grid-cols-3 gap-4 text-sm text-slate-700">
          <div class="space-y-1">
            <p class="font-semibold">{{ t('invoice.invoice_number') }}: {{ invoiceNumberText }}</p>
            <p class="font-semibold">{{ t('invoice.issue_date') }}: {{ issueDateText }}</p>
          </div>
          <div class="text-center font-semibold">{{ t('invoice.sale_invoice') }}</div>
          <div class="text-right font-semibold">{{ companyName }}</div>
        </div>
      </header>
      <div class="rounded-md border border-slate-400">
        <div class="bg-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-900">{{ t('invoice.buyer_details') }}</div>
        <div class="grid grid-cols-[190px_1fr] border-t border-slate-300 px-4 py-2 text-sm"><span class="font-semibold text-slate-700">{{ t('invoice.customer_name') }}</span><span>{{ customerName }}</span></div>
        <div class="grid grid-cols-[190px_1fr] border-t border-slate-300 px-4 py-2 text-sm"><span class="font-semibold text-slate-700">{{ t('invoice.address') }}</span><span>{{ customerAddress }}</span></div>
        <div class="grid grid-cols-[190px_1fr] border-t border-slate-300 px-4 py-2 text-sm"><span class="font-semibold text-slate-700">{{ t('invoice.phone') }}</span><span>{{ customerPhone }}</span></div>
        <div class="grid grid-cols-[190px_1fr] border-t border-slate-300 px-4 py-2 text-sm"><span class="font-semibold text-slate-700">{{ t('invoice.email') }}</span><span>{{ customerEmail }}</span></div>
      </div>
      <table class="invoice-grid-table invoice-classic-table">
        <thead>
          <tr>
            <th>{{ t('invoice.item') }}</th><th>{{ t('invoice.item_code') }}</th><th>{{ t('invoice.quantity') }}</th><th>{{ t('invoice.rate') }}</th><th>{{ t('invoice.amount') }}</th><th>{{ t('invoice.discount') }}</th><th>{{ t('invoice.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.name }}</td><td>{{ row.code }}</td><td>{{ formatNumber(row.quantity) }}</td><td>{{ formatNumber(row.unitPrice) }}</td><td>{{ formatNumber(row.baseTotal) }}</td><td>{{ formatNumber(row.discount) }}</td><td>{{ formatNumber(row.total) }}</td>
          </tr>
        </tbody>
      </table>
      <div class="grid grid-cols-[1fr_300px] gap-4">
        <div class="rounded-md border border-slate-400 p-4"><p class="mb-2 text-sm font-semibold text-slate-900">{{ t('invoice.notes') }}</p><p class="text-sm leading-6 text-slate-600">{{ notesText }}</p></div>
        <div class="rounded-md border border-slate-400 p-3">
          <div v-for="entry in summaryRows" :key="entry.label" class="flex items-center justify-between border-b border-slate-300 py-2 text-sm last:border-b-0">
            <span class="font-medium text-slate-700">{{ entry.label }}</span>
            <span :class="entry.strong ? 'font-semibold text-slate-900' : 'text-slate-700'">{{ entry.value }}</span>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4 pt-6 text-center text-sm font-semibold text-slate-700"><div>{{ t('invoice.signature_sales_manager') }}</div><div>{{ signatureLabel }}</div><div>{{ t('invoice.signature_cashier') }}</div></div>
      <footer class="space-y-3 pt-3 text-center text-xs text-slate-500">
        <div class="flex h-1.5 overflow-hidden rounded-full"><div class="w-1/2 bg-sky-500"></div><div class="w-1/2 bg-rose-500"></div></div>
        <p class="font-semibold text-sky-700">{{ companyAddress }}</p>
        <p>{{ companyPhone }} <span v-if="companyPhone !== t('invoice.not_available') && companyEmail !== t('invoice.not_available')">|</span> {{ companyEmail }}</p>
      </footer>
    </section>
  </div>
</template>
