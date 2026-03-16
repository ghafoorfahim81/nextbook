<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  series: { type: Array, default: () => [] },
})

const { t } = useI18n()
const width = 720
const height = 260
const padding = 24

const maxValue = computed(() => {
  const values = props.series.flatMap((point) => [Number(point.sales || 0), Number(point.purchases || 0)])
  return Math.max(...values, 0)
})

function buildPath(key) {
  if (!props.series.length) return ''

  const innerWidth = width - padding * 2
  const innerHeight = height - padding * 2
  const divisor = Math.max(maxValue.value, 1)

  return props.series
    .map((point, index) => {
      const x = padding + (innerWidth * index) / Math.max(props.series.length - 1, 1)
      const y = padding + innerHeight - (Number(point[key] || 0) / divisor) * innerHeight
      return `${index === 0 ? 'M' : 'L'} ${x} ${y}`
    })
    .join(' ')
}

const salesPath = computed(() => buildPath('sales'))
const purchasesPath = computed(() => buildPath('purchases'))

const ticks = computed(() => {
  const innerHeight = height - padding * 2

  return [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
    y: padding + innerHeight - innerHeight * ratio,
    value: (maxValue.value * ratio).toLocaleString(undefined, {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }),
  }))
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-end gap-4 text-sm text-muted-foreground">
      <div class="flex items-center gap-2">
        <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.45)]" />
        {{ t('dashboard.chart.sales') }}
      </div>
      <div class="flex items-center gap-2">
        <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.45)]" />
        {{ t('dashboard.chart.purchases') }}
      </div>
    </div>

    <div v-if="series.length" class="overflow-x-auto rounded-2xl border border-border bg-background p-4">
      <svg :viewBox="`0 0 ${width} ${height}`" class="min-w-[680px]">
        <rect
          :x="padding"
          :y="padding"
          :width="width - padding * 2"
          :height="height - padding * 2"
          rx="16"
          class="fill-muted/40"
        />

        <line
          v-for="tick in ticks"
          :key="tick.y"
          :x1="padding"
          :x2="width - padding"
          :y1="tick.y"
          :y2="tick.y"
          stroke="currentColor"
          class="text-border"
          stroke-dasharray="4 4"
        />

        <text
          v-for="tick in ticks"
          :key="`label-${tick.y}`"
          :x="padding - 8"
          :y="tick.y + 4"
          text-anchor="end"
          class="fill-muted-foreground text-[10px]"
        >
          {{ tick.value }}
        </text>

        <path :d="salesPath" fill="none" stroke="#34d399" stroke-width="3.5" stroke-linecap="round" />
        <path :d="purchasesPath" fill="none" stroke="#f59e0b" stroke-width="3.5" stroke-linecap="round" />
      </svg>
    </div>

    <div class="grid grid-cols-3 gap-2 text-xs sm:grid-cols-6 xl:grid-cols-10">
      <div
        v-for="point in series.slice(-10)"
        :key="point.date"
        class="rounded-xl border border-border bg-card px-2 py-2 text-center text-muted-foreground"
      >
        <div class="font-medium text-foreground">{{ point.label }}</div>
        <div>{{ t('dashboard.chart.sales_short') }} {{ Number(point.sales || 0).toLocaleString() }}</div>
        <div>{{ t('dashboard.chart.purchases_short') }} {{ Number(point.purchases || 0).toLocaleString() }}</div>
      </div>
    </div>
  </div>
</template>
