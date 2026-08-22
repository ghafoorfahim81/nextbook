<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatNumber } from './format'

const props = defineProps({
  series: { type: Array, default: () => [] },
})

const { t } = useI18n()

const plot = ref(null)
const width = ref(760)
const height = 260
const padding = { top: 16, right: 20, bottom: 30, left: 64 }

// The SVG is drawn at its real pixel width rather than being stretched to fit:
// scaling a fixed viewBox with preserveAspectRatio="none" squashes the strokes
// and the tick text along with the plot.
let observer = null

onMounted(() => {
  if (!plot.value || typeof ResizeObserver === 'undefined') return

  observer = new ResizeObserver((entries) => {
    const measured = entries[0]?.contentRect?.width
    if (measured) width.value = Math.max(measured, 320)
  })

  observer.observe(plot.value)
})

onBeforeUnmount(() => observer?.disconnect())

const innerWidth = computed(() => width.value - padding.left - padding.right)
const innerHeight = height - padding.top - padding.bottom

const maxValue = computed(() => {
  const values = props.series.flatMap((point) => [Number(point.sales || 0), Number(point.purchases || 0)])
  return Math.max(...values, 0)
})

const scale = computed(() => Math.max(maxValue.value, 1))

function x(index) {
  return padding.left + (innerWidth.value * index) / Math.max(props.series.length - 1, 1)
}

function y(value) {
  return padding.top + innerHeight - (Number(value || 0) / scale.value) * innerHeight
}

function linePath(key) {
  if (!props.series.length) return ''

  return props.series
    .map((point, index) => `${index === 0 ? 'M' : 'L'} ${x(index)} ${y(point[key])}`)
    .join(' ')
}

const salesPath = computed(() => linePath('sales'))
const purchasesPath = computed(() => linePath('purchases'))

// Only the sales series carries an area fill. Two filled areas on one baseline
// occlude each other and misread as a stack.
const salesArea = computed(() => {
  if (!props.series.length) return ''

  const baseline = padding.top + innerHeight

  return `${salesPath.value} L ${x(props.series.length - 1)} ${baseline} L ${x(0)} ${baseline} Z`
})

const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
  y: padding.top + innerHeight - innerHeight * ratio,
  value: formatNumber(maxValue.value * ratio, 'count'),
})))

// Thirty daily labels collide at this width, so label the ends and the thirds.
const xLabels = computed(() => {
  const count = props.series.length
  if (!count) return []

  const positions = [...new Set([0, Math.floor(count / 3), Math.floor((count * 2) / 3), count - 1])]

  // Centred on the first and last point the end labels overhang the plot and get
  // clipped, so their anchor point is nudged inward by roughly half a label. The
  // labels stay centre-anchored: SVG resolves start/end against the writing
  // direction, which would swap the two ends under RTL.
  const inset = 30

  return positions.map((index) => ({
    index,
    x: Math.min(Math.max(x(index), padding.left + inset), width.value - padding.right - inset),
    label: props.series[index]?.label,
  }))
})

const hoverIndex = ref(null)
const hovered = computed(() => (hoverIndex.value === null ? null : props.series[hoverIndex.value]))

function onPointerMove(event) {
  if (!props.series.length || !plot.value) return

  const bounds = plot.value.getBoundingClientRect()
  const offset = event.clientX - bounds.left - padding.left
  const step = innerWidth.value / Math.max(props.series.length - 1, 1)
  const index = Math.round(offset / step)

  hoverIndex.value = Math.min(Math.max(index, 0), props.series.length - 1)
}

// Near the right edge the tooltip flips to the other side of the crosshair so it
// never spills out of the card.
const tooltipStyle = computed(() => {
  if (hoverIndex.value === null) return {}

  const anchor = x(hoverIndex.value)
  const flip = anchor > width.value - 160

  return {
    left: `${anchor}px`,
    transform: flip ? 'translateX(-100%) translateX(-12px)' : 'translateX(12px)',
  }
})
</script>

<template>
  <div class="flex h-full flex-col gap-4">
    <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-muted-foreground">
      <span class="inline-flex items-center gap-2">
        <span class="inline-block h-2 w-4 rounded-full bg-[var(--viz-in)]" />
        {{ t('dashboard.chart.sales') }}
      </span>
      <span class="inline-flex items-center gap-2">
        <span class="inline-block h-2 w-4 rounded-full bg-[var(--viz-out)]" />
        {{ t('dashboard.chart.purchases') }}
      </span>
    </div>

    <div v-if="series.length" ref="plot" class="relative min-h-0 flex-1">
      <svg
        :viewBox="`0 0 ${width} ${height}`"
        :width="width"
        :height="height"
        class="w-full touch-none"
        role="img"
        :aria-label="t('dashboard.sales_vs_purchases')"
        @pointermove="onPointerMove"
        @pointerleave="hoverIndex = null"
      >
        <defs>
          <linearGradient id="trend-sales-fill" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--viz-in)" stop-opacity="0.22" />
            <stop offset="100%" stop-color="var(--viz-in)" stop-opacity="0" />
          </linearGradient>
        </defs>

        <line
          v-for="tick in ticks"
          :key="`grid-${tick.y}`"
          :x1="padding.left"
          :x2="width - padding.right"
          :y1="tick.y"
          :y2="tick.y"
          stroke="var(--viz-grid)"
          stroke-width="1"
        />

        <text
          v-for="tick in ticks"
          :key="`tick-${tick.y}`"
          :x="padding.left - 10"
          :y="tick.y + 4"
          text-anchor="end"
          class="fill-muted-foreground text-[10px] [font-variant-numeric:tabular-nums]"
        >
          {{ tick.value }}
        </text>

        <text
          v-for="label in xLabels"
          :key="`x-${label.index}`"
          :x="label.x"
          :y="height - 8"
          text-anchor="middle"
          class="fill-muted-foreground text-[10px]"
        >
          {{ label.label }}
        </text>

        <path :d="salesArea" fill="url(#trend-sales-fill)" />
        <path :d="salesPath" fill="none" stroke="var(--viz-in)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        <path :d="purchasesPath" fill="none" stroke="var(--viz-out)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

        <g v-if="hoverIndex !== null">
          <line
            :x1="x(hoverIndex)"
            :x2="x(hoverIndex)"
            :y1="padding.top"
            :y2="padding.top + innerHeight"
            stroke="var(--viz-axis)"
            stroke-width="1"
          />
          <circle :cx="x(hoverIndex)" :cy="y(hovered?.sales)" r="5" fill="var(--viz-in)" stroke="hsl(var(--card))" stroke-width="2" />
          <circle :cx="x(hoverIndex)" :cy="y(hovered?.purchases)" r="5" fill="var(--viz-out)" stroke="hsl(var(--card))" stroke-width="2" />
        </g>
      </svg>

      <div
        v-if="hovered"
        class="pointer-events-none absolute top-2 z-10 w-max rounded-xl border border-border bg-popover px-3 py-2 shadow-lg"
        :style="tooltipStyle"
      >
        <p class="text-xs font-semibold text-popover-foreground">{{ hovered.label }}</p>
        <p class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
          <span class="inline-block h-2 w-2 rounded-full bg-[var(--viz-in)]" />
          {{ t('dashboard.chart.sales') }}
          <span class="ms-auto font-semibold text-popover-foreground [font-variant-numeric:tabular-nums]">{{ formatNumber(hovered.sales) }}</span>
        </p>
        <p class="mt-0.5 flex items-center gap-3 text-xs text-muted-foreground">
          <span class="inline-block h-2 w-2 rounded-full bg-[var(--viz-out)]" />
          {{ t('dashboard.chart.purchases') }}
          <span class="ms-auto font-semibold text-popover-foreground [font-variant-numeric:tabular-nums]">{{ formatNumber(hovered.purchases) }}</span>
        </p>
      </div>
    </div>

    <div v-else class="flex min-h-[200px] flex-1 items-center justify-center rounded-xl border border-dashed border-border text-sm text-muted-foreground">
      {{ t('dashboard.no_data_available') }}
    </div>
  </div>
</template>
