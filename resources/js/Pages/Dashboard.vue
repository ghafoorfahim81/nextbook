<script setup>
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
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

const { t } = useI18n()
const state = ref(props.dashboard)
const refreshing = ref(false)
const refreshError = ref('')

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

  return new Date(value).toLocaleString()
})
</script>

<template>
  <AppLayout>
    <Head :title="t('dashboard.dashboard')" />

    <div class="space-y-6 text-foreground">
      <section class="overflow-hidden rounded-3xl border border-border bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white shadow-lg">
        <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.8fr_0.8fr] lg:px-8">
          <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-300">{{ t('dashboard.operational_overview') }}</p>
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ t('dashboard.branch_dashboard') }}</h1>
            <p class="max-w-2xl text-sm text-slate-300 sm:text-base">
              {{ t('dashboard.hero_description') }}
            </p>
          </div>

          <div class="rounded-2xl border border-white/10 bg-white/10 p-5 backdrop-blur">
            <div class="text-sm text-slate-300">{{ t('dashboard.generated') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ generatedAt }}</div>
            <div class="mt-4 text-sm text-slate-300">{{ t('dashboard.today') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ state.meta?.today }}</div>
            <div class="mt-5 flex items-center gap-3">
              <Button
                variant="secondary"
                class="gap-2 border-0 bg-white text-slate-900 hover:bg-slate-100"
                :disabled="refreshing"
                @click="refreshDashboard"
              >
                <RefreshCw class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" />
                {{ t('dashboard.refresh_data') }}
              </Button>
            </div>
            <p v-if="refreshError" class="mt-3 text-sm text-rose-300">
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
        <Card class="border-border bg-card shadow-sm">
          <CardHeader>
            <CardTitle class="text-card-foreground">{{ t('dashboard.sales_vs_purchases') }}</CardTitle>
            <CardDescription class="text-muted-foreground">{{ t('dashboard.daily_posted_last_30_days') }}</CardDescription>
          </CardHeader>
          <CardContent>
            <TrendChart :series="state.sales_purchase_chart?.series || []" />
          </CardContent>
        </Card>

        <Card class="border-border bg-card shadow-sm">
          <CardHeader>
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
