<script setup>
import { useI18n } from 'vue-i18n'
import { CalendarClock, FileClock, PackageMinus, TrendingDown } from 'lucide-vue-next'
import { formatNumber } from './format'

defineProps({
  alerts: { type: Array, default: () => [] },
})

const { t } = useI18n()

// Severity is never carried by colour alone — each alert ships an icon and a
// counted label alongside its tint.
const STYLES = {
  low_stock: { icon: PackageMinus, chip: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
  expiring_soon: { icon: CalendarClock, chip: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
  negative_stock: { icon: TrendingDown, chip: 'bg-rose-500/10 text-rose-600 dark:text-rose-400' },
  unposted_transactions: { icon: FileClock, chip: 'bg-primary/10 text-primary' },
}

const fallback = { icon: FileClock, chip: 'bg-muted text-muted-foreground' }

function styleFor(key) {
  return STYLES[key] || fallback
}
</script>

<template>
  <div class="grid gap-4 xl:grid-cols-2">
    <div
      v-for="alert in alerts"
      :key="alert.key"
      class="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
    >
      <div class="flex items-center gap-3 px-5 pt-5">
        <span :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', styleFor(alert.key).chip]">
          <component :is="styleFor(alert.key).icon" class="h-[18px] w-[18px]" />
        </span>
        <h3 class="flex-1 text-sm font-semibold text-card-foreground">
          {{ t(`dashboard.alerts.${alert.key}`) }}
        </h3>
        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold [font-variant-numeric:tabular-nums]', styleFor(alert.key).chip]">
          {{ formatNumber(alert.count, 'count') }}
        </span>
      </div>

      <div class="flex-1 px-5 pb-5 pt-4">
        <div v-if="!alert.items?.length" class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
          {{ t('dashboard.no_active_alerts') }}
        </div>

        <ul v-else class="space-y-0">
          <li
            v-for="item in alert.items"
            :key="item.id"
            class="border-t border-dashed border-border py-2.5 first:border-t-0 first:pt-0"
          >
            <p class="truncate text-sm font-medium text-card-foreground" :title="item.title">{{ item.title }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">{{ item.meta }}</p>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
