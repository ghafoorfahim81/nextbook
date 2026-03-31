<script setup>
import { useI18n } from 'vue-i18n'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  items: { type: Array, default: () => [] },
})

const { t } = useI18n()

function formatTotal(value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}
</script>

<template>
  <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
    <div class="relative border-b border-border px-5 py-4">
      <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-primary/80 to-primary/45" />
      <div class="text-base font-semibold text-card-foreground">{{ title }}</div>
      <div v-if="description" class="text-sm text-muted-foreground">{{ description }}</div>
    </div>

    <div class="space-y-3 p-5">
      <div v-if="!items.length" class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground">
        {{ t('dashboard.no_data_available') }}
      </div>

      <div
        v-for="item in items"
        :key="item.id"
        class="flex items-center justify-between rounded-xl border border-border bg-background px-4 py-3"
      >
        <div>
          <div class="font-medium text-card-foreground">{{ item.name }}</div>
          <div v-if="item.count !== undefined" class="text-xs text-muted-foreground">
            {{ Number(item.count || 0).toLocaleString() }} {{ t('dashboard.records') }}
          </div>
        </div>
        <div class="text-right font-semibold text-card-foreground">
          {{ formatTotal(item.total || item.balance) }}
        </div>
      </div>
    </div>
  </div>
</template>
