<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/Layout.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import KpiCard from '@/Components/dashboard/KpiCard.vue'
import TrendChart from '@/Components/dashboard/TrendChart.vue'
import ActivityTable from '@/Components/dashboard/ActivityTable.vue'
import MetricListCard from '@/Components/dashboard/MetricListCard.vue'
import AlertPanel from '@/Components/dashboard/AlertPanel.vue'
import { RefreshCw } from 'lucide-vue-next'

const props = defineProps({
  dashboard: { type: Object, required: true },
  dashboardDataUrl: { type: String, required: true },
})

const page = usePage()
const { t, locale } = useI18n()
const state = ref(props.dashboard)
const refreshing = ref(false)
const refreshError = ref('')

const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')

watch(
  () => props.dashboard,
  (value) => {
    state.value = value
  },
)

async function refreshDashboard() {
  refreshing.value = true
  refreshError.value = ''

  try {
    const response = await axios.get(props.dashboardDataUrl)
    state.value = response.data
  } catch (error) {
    refreshError.value = error?.response?.data?.message || t('dashboard.refresh_failed')
  } finally {
    refreshing.value = false
  }
}

const kpiCards = computed(() => ([
  {
    label: t('dashboard.kpis.cash_bank_balance'),
    value: state.value?.kpis?.cash_bank_balance,
    help: t('dashboard.kpis.cash_bank_balance_help'),
  },
  {
    label: t('dashboard.kpis.accounts_receivable'),
    value: state.value?.kpis?.accounts_receivable,
    help: t('dashboard.kpis.accounts_receivable_help'),
  },
  {
    label: t('dashboard.kpis.accounts_payable'),
    value: state.value?.kpis?.accounts_payable,
    help: t('dashboard.kpis.accounts_payable_help'),
  },
  {
    label: t('dashboard.kpis.todays_sales'),
    value: state.value?.kpis?.today_sales_total,
    help: t('dashboard.kpis.todays_sales_help'),
  },
  {
    label: t('dashboard.kpis.todays_purchases'),
    value: state.value?.kpis?.today_purchases_total,
    help: t('dashboard.kpis.todays_purchases_help'),
  },
  {
    label: t('dashboard.kpis.todays_cash_received'),
    value: state.value?.kpis?.today_cash_received,
    help: t('dashboard.kpis.todays_cash_received_help'),
  },
  {
    label: t('dashboard.kpis.todays_cash_paid'),
    value: state.value?.kpis?.today_cash_paid,
    help: t('dashboard.kpis.todays_cash_paid_help'),
  },
]))

const inventoryCards = computed(() => ([
  {
    label: t('dashboard.inventory.total_inventory_quantity'),
    value: state.value?.inventory_overview?.total_inventory_quantity,
    type: 'quantity',
  },
  {
    label: t('dashboard.inventory.total_inventory_value'),
    value: state.value?.inventory_overview?.total_inventory_value,
    type: 'money',
  },
  {
    label: t('dashboard.inventory.low_stock_items'),
    value: state.value?.inventory_overview?.low_stock_items,
    type: 'count',
  },
  {
    label: t('dashboard.inventory.out_of_stock_items'),
    value: state.value?.inventory_overview?.out_of_stock_items,
    type: 'count',
  },
  {
    label: t('dashboard.inventory.expiring_batches'),
    value: state.value?.inventory_overview?.expiring_batches,
    type: 'count',
  },
]))

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

    <div class="space-y-5 text-foreground">
      <section class="relative overflow-hidden rounded-[32px] border border-border bg-gradient-to-br from-primary to-primary/70 via-secondary/35 to-primary/10 shadow-sm dark:from-slate-950 dark:via-slate-900 dark:to-slate-800 dark:text-white dark:shadow-lg">
        <div class="absolute inset-y-0 start-0 hidden w-56 bg-[radial-gradient(circle_at_center,hsl(var(--primary)/0.18),transparent_72%)] lg:block dark:bg-[radial-gradient(circle_at_center,hsl(var(--primary)/0.22),transparent_70%)]" />
        <div class="absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent" />
        <div class="flex flex-col gap-4 px-6 py-7 lg:flex-row lg:items-center lg:justify-between lg:px-10">
          <div class="space-y-3 lg:max-w-3xl">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-primary dark:text-cyan-300">{{ t('dashboard.operational_overview') }}</p>
            <h1 class="text-3xl font-semibold tracking-tight text-foreground sm:text-4xl dark:text-white">{{ t('dashboard.branch_dashboard') }}</h1>
            <p class="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base dark:text-slate-300">
              {{ t('dashboard.hero_description') }}
            </p>
          </div>
          <div class="max-w-xs rounded-2xl border border-white/60 bg-primary/10 dark:bg-gray-800 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/10 lg:shrink-0">
            <div class="text-sm text-muted-foreground dark:text-slate-300">{{ t('dashboard.generated') }}</div>
            <div class="mt-1 text-lg font-semibold text-foreground dark:text-white">{{ generatedAt }}</div>
            <div class="mt-4 text-sm text-muted-foreground dark:text-slate-300">{{ t('dashboard.today') }}</div>
            <div class="mt-1 text-lg font-semibold text-foreground dark:text-white">{{ state.meta?.today }}</div>
            <div class="mt-5 flex items-center gap-3">
              <Button
                variant="secondary"
                class="gap-2 border border-border bg-background/90 text-foreground hover:bg-background dark:border-0 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                :disabled="refreshing"
                @click="refreshDashboard"
              >
                <RefreshCw class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
                {{ t('dashboard.refresh_data') }}
              </Button>
            </div>
            <p v-if="refreshError" class="mt-3 text-sm text-destructive dark:text-rose-300">
              {{ refreshError }}
            </p>
          </div>
        </div>
      </section>

      <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          v-for="card in kpiCards"
          :key="card.label"
          :label="card.label"
          :value="card.value"
          :help="card.help"
        />
      </section>

      <section class="grid gap-4 xl:grid-cols-[1.7fr_1fr]">
        <Card class="overflow-hidden border-border bg-gradient-to-b from-card via-card to-muted/20 shadow-sm">
          <CardHeader class="relative border-b border-border/70">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
            <CardTitle class="text-card-foreground">{{ t('dashboard.sales_vs_purchases') }}</CardTitle>
            <CardDescription class="text-muted-foreground">{{ t('dashboard.daily_posted_last_30_days') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <TrendChart :series="state.sales_purchase_chart?.series || []" />
          </CardContent>
        </Card>

        <Card class="overflow-hidden border-border bg-gradient-to-b from-card via-card to-muted/20 shadow-sm">
          <CardHeader class="relative border-b border-border/70">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
            <CardTitle class="text-card-foreground">{{ t('dashboard.inventory_overview') }}</CardTitle>
            <CardDescription class="text-muted-foreground">{{ t('dashboard.inventory_overview_description') }}</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
            <KpiCard
              v-for="card in inventoryCards"
              :key="card.label"
              :label="card.label"
              :value="card.value"
              :type="card.type"
            />
          </CardContent>
        </Card>
      </section>

      <section class="grid gap-4 xl:grid-cols-2">
        <MetricListCard
          :title="t('dashboard.top_lists.top_customers_title')"
          :description="t('dashboard.top_lists.top_customers_description')"
          :items="state.top_lists?.customers_by_sales || []"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.top_suppliers_title')"
          :description="t('dashboard.top_lists.top_suppliers_description')"
          :items="state.top_lists?.suppliers_by_purchases || []"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.receivables_title')"
          :description="t('dashboard.top_lists.receivables_description')"
          :items="state.top_lists?.receivable_balances || []"
        />
        <MetricListCard
          :title="t('dashboard.top_lists.payables_title')"
          :description="t('dashboard.top_lists.payables_description')"
          :items="state.top_lists?.payable_balances || []"
        />
      </section>

      <section class="grid gap-4 xl:grid-cols-3">
        <ActivityTable
          :title="t('dashboard.recent_activity.sales_title')"
          :description="t('dashboard.recent_activity.sales_description')"
          :rows="state.recent_activity?.sales || []"
        />
        <ActivityTable
          :title="t('dashboard.recent_activity.purchases_title')"
          :description="t('dashboard.recent_activity.purchases_description')"
          :rows="state.recent_activity?.purchases || []"
        />
        <ActivityTable
          :title="t('dashboard.recent_activity.stock_movements_title')"
          :description="t('dashboard.recent_activity.stock_movements_description')"
          :rows="state.recent_activity?.stock_movements || []"
          row-type="stock"
        />
      </section>

      <section class="space-y-4">
        <div>
          <h2 class="text-xl font-semibold tracking-tight text-foreground">{{ t('dashboard.alerts_title') }}</h2>
          <p class="text-sm text-muted-foreground">
            {{ t('dashboard.alerts_description') }}
          </p>
        </div>
        <AlertPanel :alerts="state.alerts || []" />
      </section>
    </div>
  </AppLayout>
</template>
