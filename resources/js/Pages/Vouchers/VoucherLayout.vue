<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { numberToWords } from '@/lib/numberToWords'

const props = defineProps({
  voucher: { type: Object, default: () => ({}) },
  company: { type: Object, default: () => ({}) },
  voucherType: { type: String, default: 'receipt' },
})

const page = usePage()
const { t, locale } = useI18n()

const isPayment = computed(() => props.voucherType === 'payment')
const isRTL = computed(() => ['fa', 'ps', 'pa', 'ar', 'ur'].includes(locale.value))
const direction = computed(() => (isRTL.value ? 'rtl' : 'ltr'))
const numberLocale = computed(() => {
  if (locale.value === 'fa') return 'fa-IR'
  if (locale.value === 'ps') return 'fa-AF'
  return 'en-US'
})

const preferences = computed(() => page.props?.user_preferences ?? {})
const decimalPlaces = computed(() => preferences.value?.appearance?.decimal_places ?? 2)
const branchName = computed(() => page.props?.auth?.user?.branch_name || t('invoice.not_available'))

const sheetStyle = computed(() => ({
  '--voucher-primary': 'hsl(var(--primary))',
  '--voucher-primary-soft': 'hsl(var(--primary) / 0.1)',
  '--voucher-accent': 'hsl(var(--accent))',
  '--voucher-accent-soft': 'hsl(var(--accent) / 0.18)',
  '--voucher-border': 'hsl(var(--border))',
  '--voucher-text': 'hsl(var(--foreground))',
  '--voucher-muted': 'hsl(var(--muted-foreground))',
}))

const toNumber = (value) => {
  const parsed = Number(value ?? 0)
  return Number.isFinite(parsed) ? parsed : 0
}

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

const companyLogo = computed(() => {
  if (props.company?.logo_url) return props.company.logo_url
  if (props.company?.logo) return `/storage/${props.company.logo}`
  return null
})
const companyAddress = computed(() => {
  return [
    props.company?.address,
    props.company?.city,
    props.company?.country,
  ].filter(Boolean).join(', ') || t('invoice.not_available')
})

const party = computed(() => props.voucher?.ledger ?? {})
const partyName = computed(() => party.value?.name || props.voucher?.ledger_name || t('invoice.not_available'))
const currencyLabel = computed(() => {
  return props.voucher?.transaction?.currency?.code
    || props.voucher?.currency_code
    || props.voucher?.transaction?.currency?.name
    || ''
})

const amount = computed(() => toNumber(props.voucher?.amount))
const amountText = computed(() => `${currencyLabel.value} ${formatNumber(amount.value)}`.trim())
const bankAccountName = computed(() => {
  return props.voucher?.bank_account?.name
    || props.voucher?.transaction?.lines?.find((line) => line.account_id === props.voucher?.bank_account_id)?.account?.name
    || '-'
})

const amountInWords = computed(() => {
  if (amount.value <= 0) return t('voucher.no_amount')
  const words = numberToWords(amount.value, locale.value === 'en' ? 'en' : 'fa')
  return `${words} ${currencyLabel.value}`.trim()
})

const voucherNumber = computed(() => props.voucher?.number || '-')
const voucherDate = computed(() => props.voucher?.date || '-')
const titleText = computed(() => isPayment.value ? t('voucher.payment_voucher') : t('voucher.receipt_voucher'))
const partyLabel = computed(() => isPayment.value ? t('voucher.paid_to') : t('voucher.received_from'))
const paidLabel = computed(() => isPayment.value ? t('voucher.paid_short') : t('voucher.received_short'))
</script>

<template>
  <div class="invoice-print-root voucher-print-root" :dir="direction" :style="sheetStyle">
    <section class="print-surface voucher-sheet">
      <div class="voucher-header">
        <div class="voucher-brand">
          <div class="voucher-logo-wrap">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="voucher-logo-image">
            <div v-else class="voucher-logo-placeholder"></div>
          </div>
          <div class="voucher-brand-copy">
            <div class="voucher-company">{{ companyName }}</div>
            <div class="voucher-sub">{{ t('voucher.tagline') }}</div>
          </div>
        </div>

        <div class="voucher-title-wrap">
          <h1 class="voucher-title">{{ titleText }}</h1>
          <div class="voucher-contact">{{ props.company?.phone || '-' }}</div>
        </div>

        <div class="voucher-meta-company">
          <div class="voucher-meta-company-name">{{ companyName }}</div>
          <div>{{ companyAddress }}</div>
          <div>{{ branchName }}</div>
        </div>
      </div>

      <div class="voucher-meta-row">
        <div class="meta-line">
          <span class="meta-label">{{ t('voucher.voucher_no_short') }}</span>
          <span class="dot-fill">{{ voucherNumber }}</span>
        </div>
        <div class="meta-line meta-line-right">
          <span class="meta-label">{{ t('general.date') }}</span>
          <span class="dot-fill">{{ voucherDate }}</span>
        </div>
      </div>

      <div class="accent-rule"></div>

      <div class="voucher-content">
        <div class="field-row">
          <span class="field-label">{{ partyLabel }}</span>
          <span class="field-fill">{{ partyName }}</span>
        </div>

        <div class="field-row">
          <span class="field-label">{{ t('general.amount') }}</span>
          <span class="field-fill">{{ amountText }}</span>
        </div>

        <div class="field-row">
          <span class="field-label">{{ t('voucher.amount_in_words') }}</span>
          <span class="field-fill">{{ amountInWords }}</span>
        </div>

        <div class="dual-row">
          <div class="field-row">
            <span class="field-label">{{ t('voucher.for_label') }}</span>
            <span class="field-fill">{{ partyName }}</span>
          </div>
          <div class="field-row compact">
            <span class="field-label">{{ t('general.branch') }}</span>
            <span class="field-fill">{{ branchName }}</span>
          </div>
        </div>

        <div class="triple-row">
          <div class="field-row compact">
            <span class="field-label">{{ t('voucher.account_short') }}</span>
            <span class="field-fill">{{ bankAccountName }}</span>
          </div>
          <div class="field-row compact">
            <span class="field-label">{{ paidLabel }}</span>
            <span class="field-fill">{{ amountText }}</span>
          </div>
          <div class="field-row compact">
            <span class="field-label">{{ t('voucher.due_short') }}</span>
            <span class="field-fill">{{ currencyLabel }} 0</span>
          </div>
        </div>

        <div class="amount-panel">
          <div class="amount-panel-label">{{ t('voucher.total_amount') }}</div>
          <div class="amount-panel-box">{{ amountText }}</div>
        </div>
      </div>

      <div class="voucher-footer">
        <div class="signature-block">
          <div class="signature-line"></div>
          <div class="signature-label">{{ t('voucher.authorised_signature') }}</div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.voucher-sheet {
  min-height: 257mm;
  padding: 0;
  overflow: hidden;
  color: var(--voucher-text);
  font-family: "Poppins", "Tahoma", sans-serif;
}

.voucher-header {
  display: grid;
  grid-template-columns: 180px 1fr 210px;
  gap: 16px;
  align-items: start;
  padding: 18px 24px 6px;
}

.voucher-brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.voucher-logo-wrap {
  width: 56px;
  height: 56px;
  border: 1px solid var(--voucher-border);
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.voucher-logo-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: 5px;
}

.voucher-logo-placeholder {
  width: 100%;
  height: 100%;
  background: color-mix(in srgb, var(--voucher-primary-soft) 40%, white);
}

.voucher-brand-copy {
  min-width: 0;
}

.voucher-company {
  font-size: 15px;
  font-weight: 700;
  line-height: 1.25;
}

.voucher-sub {
  margin-top: 2px;
  font-size: 10px;
  color: var(--voucher-muted);
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

.voucher-title-wrap {
  text-align: center;
}

.voucher-title {
  margin: 0;
  color: var(--voucher-primary);
  font-size: 24px;
  font-weight: 800;
  line-height: 1.1;
}

.voucher-contact {
  margin-top: 6px;
  font-size: 12px;
  color: var(--voucher-muted);
}

.voucher-meta-company {
  font-size: 11px;
  line-height: 1.5;
  color: var(--voucher-muted);
}

.voucher-meta-company-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--voucher-text);
}

.voucher-meta-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  padding: 4px 24px 8px;
}

.meta-line {
  display: flex;
  align-items: end;
  gap: 8px;
}

.meta-line-right {
  justify-content: flex-end;
}

.meta-label {
  white-space: nowrap;
  font-size: 15px;
  font-weight: 700;
}

.dot-fill {
  flex: 1;
  min-width: 0;
  border-bottom: 2px dotted color-mix(in srgb, var(--voucher-text) 40%, transparent);
  padding: 0 4px 4px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.2;
}

.accent-rule {
  height: 7px;
  margin: 0 0 14px;
  background: var(--voucher-accent);
}

.voucher-content {
  padding: 0 32px;
}

.field-row {
  display: flex;
  align-items: end;
  gap: 8px;
  min-height: 34px;
  margin-bottom: 8px;
}

.field-label {
  white-space: nowrap;
  font-size: 14px;
  font-weight: 700;
}

.field-fill {
  flex: 1;
  min-width: 0;
  border-bottom: 2px dotted color-mix(in srgb, var(--voucher-text) 40%, transparent);
  padding: 0 4px 5px;
  font-size: 13px;
  line-height: 1.35;
}

.dual-row {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(180px, 0.8fr);
  gap: 18px;
}

.triple-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-top: 2px;
}

.compact .field-label {
  font-size: 13px;
}

.compact .field-fill {
  font-size: 12px;
}

.amount-panel {
  margin-top: 18px;
  margin-inline-start: auto;
  width: 300px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  border: 1px solid var(--voucher-border);
  background: color-mix(in srgb, var(--voucher-primary-soft) 55%, white);
  padding: 10px 12px;
}

.amount-panel-label {
  font-size: 14px;
  font-weight: 800;
}

.amount-panel-box {
  min-width: 150px;
  border: 1px solid color-mix(in srgb, var(--voucher-primary) 18%, white);
  background: white;
  padding: 5px 10px;
  text-align: center;
  font-size: 14px;
  font-weight: 800;
}

.voucher-footer {
  margin-top: 34px;
  padding: 0 32px 24px;
  display: flex;
  justify-content: center;
}

.signature-block {
  text-align: center;
  width: min(320px, 100%);
}

.signature-line {
  border-top: 2px dotted color-mix(in srgb, var(--voucher-text) 40%, transparent);
}

.signature-label {
  margin-top: 8px;
  font-size: 14px;
  font-weight: 700;
}

@media screen and (max-width: 900px) {
  .voucher-header,
  .voucher-meta-row,
  .dual-row,
  .triple-row,
  .voucher-footer {
    grid-template-columns: 1fr;
  }

  .voucher-title-wrap,
  .voucher-meta-company,
  .meta-line-right {
    text-align: start;
    justify-content: flex-start;
  }

  .amount-panel {
    width: 100%;
  }
}
</style>
