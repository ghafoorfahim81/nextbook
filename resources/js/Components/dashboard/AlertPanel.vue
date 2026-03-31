<script setup>
import { useI18n } from 'vue-i18n'

const props = defineProps({
  alerts: { type: Array, default: () => [] },
})

const { t } = useI18n()
</script>

<template>
  <div class="grid gap-4 xl:grid-cols-2">
    <div
      v-for="alert in alerts"
      :key="alert.key"
      class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
    >
      <div class="relative border-b border-border px-5 py-4">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
        <div class="flex items-center justify-between gap-3">
          <div class="text-base font-semibold text-card-foreground">{{ t(`dashboard.alerts.${alert.key}`) }}</div>
          <span class="rounded-full bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-600 dark:text-rose-300">
            {{ Number(alert.count || 0).toLocaleString() }}
          </span>
        </div>
      </div>

      <div class="space-y-3 p-5">
        <div v-if="!alert.items?.length" class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
          {{ t('dashboard.no_active_alerts') }}
        </div>

        <div
          v-for="item in alert.items"
          :key="item.id"
          class="rounded-xl border border-border bg-background px-4 py-3"
        >
          <div class="font-medium text-card-foreground">{{ item.title }}</div>
          <div class="text-sm text-muted-foreground">{{ item.meta }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
