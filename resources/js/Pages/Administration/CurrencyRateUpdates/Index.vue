<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import DataTable from '@/Components/DataTable.vue'
import NextInput from '@/Components/next/NextInput.vue'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuth } from '@/composables/useAuth'
import { CalendarDays, Globe2, History, RefreshCcw } from 'lucide-vue-next'

const props = defineProps({
  homeCurrency: { type: Object, required: true },
  currencies: { type: Object, required: true },
  history: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  effectiveDate: { type: String, required: true },
})

const { t, locale } = useI18n()
const { can } = useAuth()

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))
const homeCurrency = computed(() => props.homeCurrency?.data ?? props.homeCurrency ?? {})
const currencyRows = computed(() => props.currencies?.data ?? props.currencies ?? [])

const form = useForm({
  date: props.effectiveDate,
  updates: [],
})

const formatEditableRate = (value) => {
  const parsed = Number(value)

  if (!Number.isFinite(parsed)) {
    return '0.00'
  }

  return parsed.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 8,
    useGrouping: false,
  })
}

const syncUpdates = (currencies) => {
  form.date = props.effectiveDate
  form.updates = (currencies || []).map((currency) => ({
    currency_id: currency.id,
    exchange_rate: formatEditableRate(currency.exchange_rate),
  }))
}

watch(currencyRows, (currencies) => {
  syncUpdates(currencies)
}, { immediate: true })

const canUpdateRates = computed(() => can('currencies.update'))
const originalRateByCurrencyId = computed(() => new Map(
  currencyRows.value.map((currency) => [currency.id, Number(currency.exchange_rate ?? 0)]),
))

const changedUpdates = computed(() => form.updates.filter((update) => {
  const originalRate = originalRateByCurrencyId.value.get(update.currency_id)
  return Number(update.exchange_rate ?? 0) !== Number(originalRate ?? 0)
}))

const hasChanges = computed(() => changedUpdates.value.length > 0)

const historyColumns = computed(() => ([
  {
    key: 'currency.name',
    label: t('general.name'),
    render: (row) => row.currency?.name ?? '-',
  },
  {
    key: 'currency.code',
    label: t('admin.currency.code'),
    render: (row) => row.currency?.code ?? '-',
  },
  {
    key: 'currency.symbol',
    label: t('admin.shared.symbol'),
    render: (row) => row.currency?.symbol ?? '-',
  },
  {
    key: 'currency.format',
    label: t('admin.currency.format'),
    render: (row) => row.currency?.format ?? '-',
  },
  {
    key: 'exchange_rate',
    label: t('admin.currency.exchange_rate'),
    sortable: true,
    render: (row) => formatRate(row.exchange_rate),
  },
  {
    key: 'date',
    label: t('general.date'),
    sortable: true,
  },
]))

const formatRate = (value) => {
  const parsed = Number(value)

  if (!Number.isFinite(parsed)) {
    return '0.00'
  }

  return parsed.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 8,
  })
}

const submit = () => {
  form.transform((data) => ({
    ...data,
    updates: changedUpdates.value,
  })).post(route('currency-rate-updates.store'), {
    preserveScroll: true,
    onFinish: () => form.transform((data) => data),
  })
}
</script>

<template>
  <AppLayout :title="t('admin.currency.rate_updates')">
    <div class="space-y-6">
      <Card class="border-primary/20 shadow-sm">
        <CardHeader class="space-y-4">
          <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
            <div class="space-y-2">
              <CardTitle class="flex items-center gap-2 text-xl text-primary">
                <RefreshCcw class="size-5" />
                {{ t('admin.currency.rate_updates') }}
              </CardTitle>
              <CardDescription>
                {{ t('admin.currency.rate_updates_description') }}
              </CardDescription>
            </div>

            <div class="flex flex-wrap gap-3">
              <div class="inline-flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm">
                <Globe2 class="size-4" />
                {{ t('admin.currency.home_currency') }}: {{ homeCurrency.code }}
              </div>
              <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                <CalendarDays class="size-4" />
                {{ t('admin.currency.effective_date') }}: {{ effectiveDate }}
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-primary/15 bg-primary/5 px-4 py-3 text-sm text-primary">
            {{ t('admin.currency.based_on_home_currency', { code: homeCurrency.code || '-' }) }}
          </div>
        </CardHeader>

        <CardContent class="space-y-4">
          <div
            v-if="currencyRows.length"
            class="grid gap-4"
          >
            <div
              v-for="(currency, index) in currencyRows"
              :key="currency.id"
              class="grid gap-3 rounded-2xl border border-border/80 bg-background/90 p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_280px] md:items-center"
            >
              <div class="flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary shadow-sm">
                  {{ currency.code?.slice(0, 2) || '?' }}
                </div>

                <div class="min-w-0">
                  <div class="font-medium text-foreground">
                    {{ currency.name }}
                  </div>
                  <div class="text-sm text-muted-foreground">
                    {{ currency.code }} - {{ currency.symbol || '-' }} - {{ formatRate(currency.exchange_rate) }}
                  </div>
                </div>
              </div>

              <NextInput
                v-if="form.updates[index]"
                v-model="form.updates[index].exchange_rate"
                :label="t('admin.currency.exchange_rate')"
                type="number"
                step="any"
                :disabled="form.processing || !canUpdateRates"
                :error="form.errors[`updates.${index}.exchange_rate`]"
              />
            </div>
          </div>

          <div
            v-else
            class="rounded-2xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
          >
            {{ t('admin.currency.no_currencies_to_update') }}
          </div>

          <div
            v-if="form.errors.updates"
            class="text-sm text-destructive"
            :class="isRTL ? 'text-right' : 'text-left'"
          >
            {{ form.errors.updates }}
          </div>

          <div class="flex justify-end">
            <Button
              type="button"
              class="min-w-36"
              :disabled="form.processing || !canUpdateRates || !currencyRows.length || !hasChanges"
              @click="submit"
            >
              {{ form.processing ? t('general.saving') : t('admin.currency.update_all') }}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card class="border-primary/15 shadow-sm">
        <CardHeader class="pb-2">
          <CardTitle class="flex items-center gap-2 text-lg">
            <History class="size-5 text-primary" />
            {{ t('admin.currency.rate_updates_history') }}
          </CardTitle>
          <CardDescription>
            {{ t('admin.currency.rate_updates_history_description') }}
          </CardDescription>
        </CardHeader>

        <CardContent>
          <DataTable
            can="currencies"
            :items="history"
            :columns="historyColumns"
            :filters="filters"
            :title="t('admin.currency.rate_updates_history')"
            :url="`currency-rate-updates.index`"
            :show-add-button="false"
          />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
