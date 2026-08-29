<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import KpiCard from '@/Components/dashboard/KpiCard.vue'
import TrendChart from '@/Components/dashboard/TrendChart.vue'
import ActivityTable from '@/Components/dashboard/ActivityTable.vue'
import MetricListCard from '@/Components/dashboard/MetricListCard.vue'
import AlertPanel from '@/Components/dashboard/AlertPanel.vue'
import AnimatedNumber from '@/Components/dashboard/AnimatedNumber.vue'
import CashPositionCard from '@/Components/dashboard/CashPositionCard.vue'
import { formatNumber, formatPercent } from '@/Components/dashboard/format'
import {
  ArrowDownLeft,
  ArrowDownRight,
  ArrowUpRight,
  Boxes,
  CalendarClock,
  Coins,
  HandCoins,
  PackageMinus,
  PackageX,
  ReceiptText,
  RefreshCw,
  ShoppingCart,
  Truck,
  Wallet,
} from 'lucide-vue-next'

const props = defineProps({
  dashboard: { type: Object, required: true },
  dashboardDataUrl: { type: String, required: true },
  period: { type: String, default: 'this_year' },
})

const page = usePage()
const { t, locale } = useI18n()
const state = ref(props.dashboard)
const refreshing = ref(false)
const refreshError = ref('')
const period = ref(props.period)

const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')

watch(
  () => props.dashboard,
  (value) => {
    state.value = value
  },
)

// Fiscal-year and season presets are left out: this system tracks calendar
// week / month / year only, no fiscal year or season concept.
const PERIOD_KEYS = ['this_week', 'last_week', 'last_30_days', 'this_month', 'this_year', 'last_month', 'last_year']

const periodOptions = computed(() => PERIOD_KEYS.map((key) => ({
  value: key,
  label: t(`dashboard.period.${key}`),
})))

async function fetchDashboard() {
  refreshing.value = true
  refreshError.value = ''

  try {
    const response = await axios.get(props.dashboardDataUrl, { params: { period: period.value } })
    state.value = response.data
    window.history.replaceState(window.history.state, '', route('dashboard', { period: period.value }))
  } catch (error) {
    refreshError.value = error?.response?.data?.message || t('dashboard.refresh_failed')
  } finally {
    refreshing.value = false
  }
}

function refreshDashboard() {
  return fetchDashboard()
}

watch(period, fetchDashboard)

function trendFor(key) {
  return state.value?.kpi_trends?.[key] || null
}

// The seven headline KPIs, in the order the branch reads them: what is on hand
// first, then what moved today.
//
// The two kinds differ in what sits under the figure. Today's flows carry a
// comparison against the same figure for yesterday; the balances above them are
// point-in-time snapshots with no prior period to be held against, so they keep
// their explanatory help text instead of a delta.
//
// `goodDirection` is what colours a delta: money in is good when it rises, while
// purchases and cash paid rising is neither good nor bad, so those two show the
// direction without a verdict colour.
const today = computed(() => state.value?.meta?.today_date)

function reportLink(params) {
  return route('reports.index', params)
}

function todayReportLink(report) {
  return reportLink({ report, date_from: today.value, date_to: today.value })
}

const kpiCards = computed(() => ([
  {
    key: 'cash_bank_balance',
    href: reportLink({ report: 'cash_position_by_currency' }),
    label: t('dashboard.kpis.cash_bank_balance'),
    help: t('dashboard.kpis.cash_bank_balance_help'),
    icon: Wallet,
    tone: 'neutral',
  },
  {
    key: 'accounts_receivable',
    href: reportLink({ report: 'customer_statement', balance_type: 'debtor' }),
    label: t('dashboard.kpis.accounts_receivable'),
    help: t('dashboard.kpis.accounts_receivable_help'),
    icon: HandCoins,
    tone: 'in',
  },
  {
    key: 'accounts_payable',
    href: reportLink({ report: 'supplier_statement', balance_type: 'creditor' }),
    label: t('dashboard.kpis.accounts_payable'),
    help: t('dashboard.kpis.accounts_payable_help'),
    icon: ReceiptText,
    tone: 'out',
  },
  {
    key: 'today_sales_total',
    href: todayReportLink('sales_report'),
    label: t('dashboard.kpis.todays_sales'),
    help: t('dashboard.kpis.todays_sales_help'),
    icon: ShoppingCart,
    tone: 'in',
    goodDirection: 'up',
  },
  {
    key: 'today_purchases_total',
    href: todayReportLink('purchase_report'),
    label: t('dashboard.kpis.todays_purchases'),
    help: t('dashboard.kpis.todays_purchases_help'),
    icon: Truck,
    tone: 'out',
    goodDirection: 'neutral',
  },
  {
    key: 'today_cash_received',
    href: todayReportLink('receipt_report'),
    label: t('dashboard.kpis.todays_cash_received'),
    help: t('dashboard.kpis.todays_cash_received_help'),
    icon: ArrowDownLeft,
    tone: 'in',
    goodDirection: 'up',
  },
  {
    key: 'today_cash_paid',
    href: todayReportLink('payment_report'),
    label: t('dashboard.kpis.todays_cash_paid'),
    help: t('dashboard.kpis.todays_cash_paid_help'),
    icon: ArrowUpRight,
    tone: 'out',
    goodDirection: 'neutral',
  },
]).map((card) => ({
  ...card,
  value: state.value?.kpis?.[card.key],
  trend: trendFor(card.key),
})))

const inventoryCards = computed(() => ([
  {
    key: 'total_inventory_quantity',
    href: reportLink({ report: 'inventory_stock' }),
    label: t('dashboard.inventory.total_inventory_quantity'),
    type: 'quantity',
    icon: Boxes,
    tone: 'neutral',
  },
  {
    key: 'total_inventory_value',
    href: reportLink({ report: 'inventory_valuation' }),
    label: t('dashboard.inventory.total_inventory_value'),
    type: 'money',
    icon: Coins,
    tone: 'neutral',
  },
  {
    key: 'low_stock_items',
    href: reportLink({ report: 'low_stock' }),
    label: t('dashboard.inventory.low_stock_items'),
    type: 'count',
    icon: PackageMinus,
    tone: 'warning',
  },
  {
    key: 'out_of_stock_items',
    href: reportLink({ report: 'zero_on_hand_report' }),
    label: t('dashboard.inventory.out_of_stock_items'),
    type: 'count',
    icon: PackageX,
    tone: 'critical',
  },
  {
    key: 'expiring_batches',
    href: reportLink({ report: 'near_expiry_report' }),
    label: t('dashboard.inventory.expiring_batches'),
    type: 'count',
    icon: CalendarClock,
    tone: 'warning',
  },
]).map((card) => ({ ...card, value: state.value?.inventory_overview?.[card.key] })))

const INVENTORY_TONES = {
  neutral: 'bg-primary/10 text-primary',
  warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  critical: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
}

function inventoryToneClass(tone) {
  return INVENTORY_TONES[tone] || INVENTORY_TONES.neutral
}

// The ranked lists hold ledger ids; both customer and supplier pages are ledger
// pages, and each list is already segregated by party type.
const customerHref = (row) => route('customers.show', row.id)
const supplierHref = (row) => route('suppliers.show', row.id)
const saleHref = (row) => route('sales.show', row.id)
const purchaseHref = (row) => route('purchases.show', row.id)
// A stock movement row opens the item it moved, not the movement itself.
const stockItemHref = (row) => route('items.show', row.item_id)

const chartTotals = computed(() => state.value?.sales_purchase_chart?.totals || {})

// The one hero figure on the page: 30 days of posted sales, against the 30 days
// before it.
const heroChange = computed(() => {
  const value = chartTotals.value?.sales_change_percent
  return value === null || value === undefined ? null : Number(value)
})

const heroDeltaClass = computed(() => {
  if (heroChange.value === null || heroChange.value === 0) return 'bg-muted text-muted-foreground'

  return heroChange.value > 0
    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
    : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
})

const generatedAt = computed(() => {
  const value = state.value?.meta?.generated_at
  if (!value) return 'N/A'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const localeWithCalendar = calendarType.value === 'jalali'
    ? `${locale.value}-u-ca-persian`
    : `${locale.value}-u-ca-gregory`

  return new Intl.DateTimeFormat(localeWithCalendar, {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(date)
})
</script>

<template>
  <AppLayout>
    <Head :title="t('dashboard.dashboard')" />

    <div class="space-y-6 text-foreground">
      <header class="flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
        <div>
          <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
            {{ t('dashboard.operational_overview') }}
          </p>
          <h1 class="mt-1 text-2xl font-semibold tracking-tight text-foreground">
            {{ t('dashboard.branch_dashboard') }}
          </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="flex items-center gap-2 rounded-md border border-border bg-card px-2.5 py-1">
            <div>
              <p class="text-[10px] leading-none text-muted-foreground">{{ t('dashboard.today') }}</p>
              <p class="mt-0.5 text-xs font-semibold text-card-foreground">{{ state.meta?.today }}</p>
            </div>
            <div class="h-5 w-px bg-border" />
            <div>
              <p class="text-[10px] leading-none text-muted-foreground">{{ t('dashboard.generated') }}</p>
              <p class="mt-0.5 text-xs font-semibold text-card-foreground">{{ generatedAt }}</p>
            </div>
          </div>

          <Select v-model="period">
            <SelectTrigger class="h-7 w-[130px] text-xs border-input md:w-[150px]">
              <SelectValue :placeholder="t('dashboard.period.label')" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in periodOptions"
                :key="option.value"
                :value="option.value"
                class="px-5 py-2 text-xs data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[highlighted]:bg-primary data-[highlighted]:text-primary-foreground"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>

          <Button variant="outline" size="sm" :disabled="refreshing" @click="refreshDashboard">
            <RefreshCw class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
            {{ t('dashboard.refresh_data') }}
          </Button>
        </div>
      </header>

      <p v-if="refreshError" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-sm text-rose-700 dark:text-rose-300">
        {{ refreshError }}
      </p>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          v-for="card in kpiCards"
          :key="card.key"
          :label="card.label"
          :value="card.value"
          :help="card.help"
          :icon="card.icon"
          :tone="card.tone"
          :trend="card.trend"
          :good-direction="card.goodDirection"
          :href="card.href"
        />
      </section>

      <section class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
        <div class="flex flex-col rounded-2xl border border-border bg-card p-5 shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h2 class="text-base font-semibold text-card-foreground">{{ t('dashboard.sales_vs_purchases') }}</h2>
              <p class="mt-0.5 text-xs text-muted-foreground">{{ t('dashboard.daily_posted_for_period', { period: t(`dashboard.period.${period}`) }) }}</p>
            </div>
            <div class="text-end">
              <p class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ t('dashboard.chart.sales') }}</p>
              <div class="mt-1 flex items-center justify-end gap-2">
                <span :title="formatNumber(chartTotals.sales)" class="text-2xl font-semibold leading-none tracking-tight text-card-foreground">
                  <AnimatedNumber :value="chartTotals.sales" compact />
                </span>
                <span
                  v-if="heroChange !== null"
                  :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold', heroDeltaClass]"
                >
                  <component :is="heroChange >= 0 ? ArrowUpRight : ArrowDownRight" class="h-3.5 w-3.5" />
                  {{ formatPercent(heroChange) }}
                </span>
              </div>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ t('dashboard.vs_previous_period', { value: formatNumber(chartTotals.previous_sales) }) }}
              </p>
            </div>
          </div>

          <div class="mt-5 flex-1">
            <TrendChart :series="state.sales_purchase_chart?.series || []" />
          </div>
        </div>

        <div class="flex flex-col rounded-2xl border border-border bg-card p-5 shadow-sm">
          <div>
            <h2 class="text-base font-semibold text-card-foreground">{{ t('dashboard.inventory_overview') }}</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">{{ t('dashboard.inventory_overview_description') }}</p>
          </div>

          <div class="mt-4 grid flex-1 content-start gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <Link
              v-for="card in inventoryCards"
              :key="card.key"
              :href="card.href"
              class="flex items-center gap-3 rounded-xl border border-border bg-background px-4 py-3 transition-colors hover:border-primary/40 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', inventoryToneClass(card.tone)]">
                <component :is="card.icon" class="h-[18px] w-[18px]" />
              </span>
              <p class="min-w-0 flex-1 text-sm font-medium text-card-foreground">{{ card.label }}</p>
              <p class="shrink-0 text-base font-semibold text-card-foreground [font-variant-numeric:tabular-nums]">
                <AnimatedNumber :value="card.value" :type="card.type" />
              </p>
            </Link>
          </div>
        </div>
      </section>

      <section class="grid items-start gap-4 xl:grid-cols-2">
        <MetricListCard
          :title="t('dashboard.top_lists.top_customers_title')"
          :description="t('dashboard.top_lists.top_customers_description')"
          :items="state.top_lists?.customers_by_sales || []"
          tone="in"
          :item-href="customerHref"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.top_suppliers_title')"
          :description="t('dashboard.top_lists.top_suppliers_description')"
          :items="state.top_lists?.suppliers_by_purchases || []"
          tone="out"
          :item-href="supplierHref"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.receivables_title')"
          :description="t('dashboard.top_lists.receivables_description')"
          :items="state.top_lists?.receivable_balances || []"
          tone="in"
          :item-href="customerHref"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.payables_title')"
          :description="t('dashboard.top_lists.payables_description')"
          :items="state.top_lists?.payable_balances || []"
          tone="out"
          :item-href="supplierHref"
        />
      </section>

      <section class="grid gap-4 xl:grid-cols-3">
        <ActivityTable
          :title="t('dashboard.recent_activity.sales_title')"
          :description="t('dashboard.recent_activity.sales_description')"
          :rows="state.recent_activity?.sales || []"
          :row-href="saleHref"
        />
        <ActivityTable
          :title="t('dashboard.recent_activity.purchases_title')"
          :description="t('dashboard.recent_activity.purchases_description')"
          :rows="state.recent_activity?.purchases || []"
          :row-href="purchaseHref"
        />
        <ActivityTable
          :title="t('dashboard.recent_activity.stock_movements_title')"
          :description="t('dashboard.recent_activity.stock_movements_description')"
          :rows="state.recent_activity?.stock_movements || []"
          row-type="stock"
          :row-href="stockItemHref"
        />
      </section>

      <CashPositionCard
        :position="state.cash_position || {}"
        :href="reportLink({ report: 'cash_position_by_currency' })"
      />

      <section class="space-y-3">
        <div>
          <h2 class="text-base font-semibold text-foreground">{{ t('dashboard.alerts_title') }}</h2>
          <p class="text-xs text-muted-foreground">{{ t('dashboard.alerts_description') }}</p>
        </div>
        <AlertPanel :alerts="state.alerts || []" />
      </section>
    </div>
  </AppLayout>
</template>
