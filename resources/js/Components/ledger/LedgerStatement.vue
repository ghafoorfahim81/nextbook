<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import NextDate from '@/Components/next/NextDatePicker.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import { Download, FileText, RotateCcw, Search } from 'lucide-vue-next'

const props = defineProps({
  // The `ledgerStatement` payload built by LedgerStatementService.
  statement: { type: Object, default: () => ({}) },
  // Route used to re-request the statement with new filters.
  routeName: { type: String, required: true },
  // Route parameter name for this ledger ('customer' | 'supplier').
  paramKey: { type: String, required: true },
  ledgerId: { type: String, required: true },
  exportRouteName: { type: String, default: '' },
})

const { t, locale } = useI18n()

const meta = computed(() => props.statement?.meta ?? {})
const sections = computed(() => props.statement?.sections ?? [])
const totals = computed(() => props.statement?.totals ?? {})
const currencyOptions = computed(() => [
  { id: '', name: t('general.all') },
  ...(props.statement?.currencies ?? []).map((currency) => ({
    id: currency.id,
    name: `${currency.code} — ${currency.name}`,
  })),
])

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

const filters = ref({
  from: meta.value.date_from ?? '',
  to: meta.value.date_to ?? '',
  currency: meta.value.currency_id ?? '',
})

// Keep the inputs in sync when a partial reload swaps the statement prop.
watch(meta, (next) => {
  filters.value = {
    from: next.date_from ?? '',
    to: next.date_to ?? '',
    currency: next.currency_id ?? '',
  }
})

const loading = ref(false)

const formatMoney = (value) => Number(value ?? 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const formatRate = (value) => Number(value ?? 0).toLocaleString(undefined, {
  minimumFractionDigits: 0,
  maximumFractionDigits: 4,
})

// Debit-positive balances are coloured as "they owe us", credit-positive as
// "we owe them", matching the Dr/Cr suffix the backend already produced. On a
// supplier the credit side is a payable, so it is toned as a warning.
const isPayableLedger = computed(() => props.paramKey === 'supplier')

const balanceClass = (value) => {
  if (Number(value ?? 0) >= 0) {
    return 'text-blue-600 dark:text-blue-400'
  }

  return isPayableLedger.value
    ? 'text-amber-600 dark:text-amber-400'
    : 'text-emerald-600 dark:text-emerald-400'
}

const queryPayload = () => ({
  statement_from: filters.value.from || undefined,
  statement_to: filters.value.to || undefined,
  statement_currency: filters.value.currency || undefined,
})

const applyFilters = () => {
  loading.value = true
  router.get(route(props.routeName, { [props.paramKey]: props.ledgerId }), queryPayload(), {
    preserveState: true,
    preserveScroll: true,
    only: ['ledgerStatement'],
    onFinish: () => { loading.value = false },
  })
}

const resetFilters = () => {
  filters.value = { from: '', to: '', currency: '' }
  applyFilters()
}

const exportStatement = (format = 'xlsx') => {
  if (!props.exportRouteName) return
  const params = { [props.paramKey]: props.ledgerId, list: 'statement', format, ...queryPayload() }
  window.location.href = route(props.exportRouteName, params)
}

const agingBuckets = computed(() => [
  { key: 'current', label: t('report.aging.current') },
  { key: 'days_30', label: t('report.aging.days_30') },
  { key: 'days_60', label: t('report.aging.days_60') },
  { key: 'days_90', label: t('report.aging.days_90') },
  { key: 'days_90_plus', label: t('report.aging.days_90_plus') },
])
</script>

<template>
  <div class="space-y-4">
    <!-- Filters -->
    <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <NextDate v-model="filters.from" :placeholder="t('report.filters.date_from')" />
        <NextDate v-model="filters.to" :placeholder="t('report.filters.date_to')" />
        <NextSelect
          :floating-text="t('admin.currency.currency')"
          :model-value="filters.currency"
          :options="currencyOptions"
          label-key="name"
          value-key="id"
          :clearable="false"
          @update:modelValue="filters.currency = $event"
        />
        <div class="flex flex-wrap items-center gap-2">
          <Button size="sm" :disabled="loading" class="gap-1.5" @click="applyFilters">
            <Search class="h-4 w-4" />
            {{ t('general.filter') }}
          </Button>
          <Button size="sm" variant="outline" :disabled="loading" class="gap-1.5" @click="resetFilters">
            <RotateCcw class="h-4 w-4" />
            {{ t('general.reset') }}
          </Button>
          <Button v-if="exportRouteName" size="sm" variant="outline" class="gap-1.5" @click="exportStatement('xlsx')">
            <Download class="h-4 w-4" />
            {{ t('report.export_excel') }}
          </Button>
          <Button v-if="exportRouteName" size="sm" variant="outline" class="gap-1.5" @click="exportStatement('pdf')">
            <FileText class="h-4 w-4" />
            {{ t('report.export_pdf') }}
          </Button>
        </div>
      </div>

      <p class="mt-3 text-xs text-muted-foreground">
        {{ meta.date_from_display
          ? t('report.statement_period', { from: meta.date_from_display, to: meta.date_to_display })
          : t('report.statement_up_to', { to: meta.date_to_display }) }}
      </p>
    </div>

    <!-- Multi-currency summary -->
    <div v-if="sections.length" class="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div class="mb-3 text-sm font-semibold text-foreground">
        {{ t('report.summary.closing_balance') }}
      </div>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div
          v-for="section in sections"
          :key="`summary-${section.currency_id}`"
          class="rounded-lg border border-border bg-background px-4 py-3"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
              {{ section.currency_code }}
            </span>
            <span v-if="section.is_base_currency" class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
              {{ t('report.home_currency') }}
            </span>
          </div>
          <div class="mt-1 text-lg font-semibold" :class="balanceClass(section.closing_balance)">
            {{ section.closing_label }}
          </div>
          <div class="mt-1 text-xs text-muted-foreground">{{ section.currency_name }}</div>
        </div>
      </div>

      <!--
        Currencies are never summed into one balance. The only cross-currency
        figure shown is the home-currency equivalent at historical rates, which
        is what ties back to the general ledger.
      -->
      <div v-if="meta.home_currency" class="mt-4 flex flex-wrap items-baseline justify-between gap-2 border-t border-border pt-3">
        <span class="text-xs text-muted-foreground">
          {{ t('report.home_equivalent_note', { code: meta.home_currency.code }) }}
        </span>
        <span class="text-sm font-semibold" :class="balanceClass(totals.home_equivalent)">
          {{ formatMoney(Math.abs(totals.home_equivalent)) }}
          {{ totals.home_equivalent_nature === 'cr' ? 'Cr' : 'Dr' }}
        </span>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="!sections.length"
      class="rounded-xl border border-dashed border-border bg-card px-4 py-10 text-center text-sm text-muted-foreground"
    >
      {{ t('general.no_data_found') }}
    </div>

    <!-- One ledger per currency -->
    <div
      v-for="section in sections"
      :key="section.currency_id"
      class="rounded-xl border border-border bg-card shadow-sm"
    >
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
        <div>
          <h3 class="text-sm font-semibold text-foreground">
            {{ section.currency_code }} — {{ section.currency_name }}
          </h3>
          <p class="text-xs text-muted-foreground">
            {{ t('report.summary.total_debit') }}: {{ formatMoney(section.total_debit) }}
            · {{ t('report.summary.total_credit') }}: {{ formatMoney(section.total_credit) }}
          </p>
        </div>
        <div class="text-right rtl:text-left">
          <div class="text-xs text-muted-foreground">{{ t('report.summary.closing_balance') }}</div>
          <div class="text-base font-semibold" :class="balanceClass(section.closing_balance)">
            {{ section.closing_label }}
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-border text-muted-foreground" :class="isRTL ? 'text-right' : 'text-left'">
              <th class="px-4 py-2 font-medium">{{ t('general.date') }}</th>
              <th class="px-4 py-2 font-medium">{{ t('general.number') }}</th>
              <th class="px-4 py-2 font-medium">{{ t('general.type') }}</th>
              <th class="px-4 py-2 font-medium">{{ t('general.description') }}</th>
              <th v-if="!section.is_base_currency" class="px-4 py-2 font-medium text-right rtl:text-left">{{ t('general.rate') }}</th>
              <th class="px-4 py-2 font-medium text-right rtl:text-left">{{ t('general.debit') }}</th>
              <th class="px-4 py-2 font-medium text-right rtl:text-left">{{ t('general.credit') }}</th>
              <th class="px-4 py-2 font-medium text-right rtl:text-left">{{ t('general.balance') }}</th>
            </tr>
          </thead>
          <tbody>
            <!-- Brought-forward row: only meaningful once a from-date narrows the window. -->
            <tr v-if="meta.date_from" class="border-b border-border bg-muted/40 font-medium text-foreground">
              <td class="px-4 py-2">{{ meta.date_from_display }}</td>
              <td class="px-4 py-2">-</td>
              <td class="px-4 py-2" :colspan="section.is_base_currency ? 2 : 3">
                {{ t('report.columns.opening_balance') }}
              </td>
              <td class="px-4 py-2 text-right rtl:text-left">-</td>
              <td class="px-4 py-2 text-right rtl:text-left">-</td>
              <td class="px-4 py-2 text-right rtl:text-left" :class="balanceClass(section.opening_balance)">
                {{ section.opening_label }}
              </td>
            </tr>

            <tr v-if="!section.rows.length">
              <td :colspan="section.is_base_currency ? 7 : 8" class="px-4 py-6 text-center text-muted-foreground">
                {{ t('general.no_data_found') }}
              </td>
            </tr>

            <tr
              v-for="row in section.rows"
              :key="row.id"
              class="border-b border-border last:border-b-0 text-foreground"
            >
              <td class="whitespace-nowrap px-4 py-2">{{ row.date_display }}</td>
              <td class="whitespace-nowrap px-4 py-2">{{ row.voucher_number }}</td>
              <td class="whitespace-nowrap px-4 py-2">{{ row.document_type }}</td>
              <td class="px-4 py-2">{{ row.description }}</td>
              <td v-if="!section.is_base_currency" class="px-4 py-2 text-right rtl:text-left text-muted-foreground">
                {{ formatRate(row.rate) }}
              </td>
              <td class="px-4 py-2 text-right rtl:text-left">{{ row.debit ? formatMoney(row.debit) : '-' }}</td>
              <td class="px-4 py-2 text-right rtl:text-left">{{ row.credit ? formatMoney(row.credit) : '-' }}</td>
              <td class="whitespace-nowrap px-4 py-2 text-right rtl:text-left font-medium" :class="balanceClass(row.balance)">
                {{ row.balance_label }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t border-border bg-muted/40 font-semibold text-foreground">
              <td class="px-4 py-2" :colspan="section.is_base_currency ? 4 : 5">
                {{ t('report.columns.closing_balance') }}
              </td>
              <td class="px-4 py-2 text-right rtl:text-left">{{ formatMoney(section.total_debit) }}</td>
              <td class="px-4 py-2 text-right rtl:text-left">{{ formatMoney(section.total_credit) }}</td>
              <td class="px-4 py-2 text-right rtl:text-left" :class="balanceClass(section.closing_balance)">
                {{ section.closing_label }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Aging for this currency -->
      <div v-if="section.aging && section.aging.total > 0" class="border-t border-border px-4 py-3">
        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          {{ t('report.aging.title') }}
        </div>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
          <div
            v-for="bucket in agingBuckets"
            :key="bucket.key"
            class="rounded-lg border border-border bg-background px-3 py-2"
          >
            <div class="text-[11px] text-muted-foreground">{{ bucket.label }}</div>
            <div class="text-sm font-semibold text-foreground">
              {{ formatMoney(section.aging[bucket.key]) }}
            </div>
          </div>
        </div>
        <p v-if="section.aging.unapplied_credit > 0" class="mt-2 text-xs text-muted-foreground">
          {{ t('report.aging.unapplied_credit') }}: {{ formatMoney(section.aging.unapplied_credit) }}
        </p>
      </div>
    </div>
  </div>
</template>
