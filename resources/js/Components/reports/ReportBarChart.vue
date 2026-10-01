<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCompact, formatNumber } from '@/Components/dashboard/format'

/**
 * A small bar chart for report summaries (e.g. revenue vs. costs vs. profit).
 *
 * Drawn as SVG at its real pixel width, like the dashboard charts, so text and
 * strokes are never stretched. Values may be negative — a net loss hangs below
 * the zero line instead of being clipped.
 */
const props = defineProps({
  title: { type: String, default: '' },
  // [{ key, label, value, tone }] — tone is one of the TONES keys below.
  bars: { type: Array, default: () => [] },
})

const { locale } = useI18n()
const isRTL = computed(() => ['fa', 'ps'].includes(String(locale.value).toLowerCase()))

// Written out in full so Tailwind generates them.
const TONES = {
  emerald: 'fill-emerald-500',
  amber: 'fill-amber-500',
  sky: 'fill-sky-500',
  rose: 'fill-rose-500',
  violet: 'fill-violet-500',
}

const plot = ref(null)
const width = ref(480)
const height = 260
const padding = { top: 24, right: 16, bottom: 34, left: 64 }

let observer = null

onMounted(() => {
  if (!plot.value || typeof ResizeObserver === 'undefined') return

  observer = new ResizeObserver((entries) => {
    const measured = entries[0]?.contentRect?.width
    if (measured) width.value = Math.max(measured, 260)
  })
  observer.observe(plot.value)
})

onBeforeUnmount(() => observer?.disconnect())

// Reading order: in Persian and Pashto the first bar sits on the right.
const orderedBars = computed(() => (isRTL.value ? [...props.bars].reverse() : props.bars))

const innerWidth = computed(() => width.value - padding.left - padding.right)
const innerHeight = height - padding.top - padding.bottom

/** A rounded step (1, 2, 2.5, 5 × 10ⁿ) so gridlines land on readable values. */
function niceStep(range, targetTicks = 4) {
  const raw = Math.max(range, 1) / targetTicks
  const magnitude = 10 ** Math.floor(Math.log10(raw))
  const normalized = raw / magnitude
  const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 2.5 ? 2.5 : normalized <= 5 ? 5 : 10
  return step * magnitude
}

// Always include zero, so bars grow from a real baseline in either direction.
const domain = computed(() => {
  const values = props.bars.map((bar) => Number(bar.value || 0))
  const min = Math.min(0, ...values)
  const max = Math.max(0, ...values)
  const step = niceStep(max - min)

  return {
    min: Math.floor(min / step) * step,
    max: Math.ceil(max / step) * step || step,
    step,
  }
})

function y(value) {
  const { min, max } = domain.value
  return padding.top + innerHeight - ((Number(value || 0) - min) / (max - min)) * innerHeight
}

const ticks = computed(() => {
  const { min, max, step } = domain.value
  const list = []
  for (let value = min; value <= max + step / 2; value += step) {
    list.push(Number(value.toFixed(6)))
  }
  return list
})

const slot = computed(() => innerWidth.value / Math.max(orderedBars.value.length, 1))
const barWidth = computed(() => Math.min(56, slot.value * 0.55))

const shapes = computed(() => orderedBars.value.map((bar, index) => {
  const value = Number(bar.value || 0)
  const top = y(Math.max(value, 0))
  const bottom = y(Math.min(value, 0))
  const centre = padding.left + slot.value * index + slot.value / 2

  return {
    ...bar,
    value,
    x: centre - barWidth.value / 2,
    centre,
    top,
    height: Math.max(bottom - top, value === 0 ? 0 : 1),
    labelY: value >= 0 ? top - 6 : bottom + 14,
    toneClass: TONES[bar.tone] ?? TONES.violet,
  }
}))
</script>

<template>
  <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
    <h3 v-if="title" class="mb-3 text-base font-semibold text-card-foreground">{{ title }}</h3>

    <div ref="plot" class="w-full" dir="ltr">
      <svg :width="width" :height="height" role="img" :aria-label="title">
        <!-- Gridlines and their values -->
        <g>
          <template v-for="tick in ticks" :key="tick">
            <line
              :x1="padding.left"
              :x2="width - padding.right"
              :y1="y(tick)"
              :y2="y(tick)"
              class="stroke-border"
              :stroke-width="tick === 0 ? 1.5 : 1"
              :stroke-dasharray="tick === 0 ? '' : '3 4'"
            />
            <text
              :x="padding.left - 8"
              :y="y(tick)"
              text-anchor="end"
              dominant-baseline="middle"
              class="fill-muted-foreground text-[11px] tabular-nums"
            >{{ formatCompact(tick) }}</text>
          </template>
        </g>

        <!-- Bars -->
        <g v-for="bar in shapes" :key="bar.key">
          <rect
            :x="bar.x"
            :y="bar.top"
            :width="barWidth"
            :height="bar.height"
            rx="6"
            :class="bar.toneClass"
          >
            <title>{{ bar.label }}: {{ formatNumber(bar.value) }}</title>
          </rect>
          <text
            :x="bar.centre"
            :y="bar.labelY"
            text-anchor="middle"
            class="fill-card-foreground text-[11px] font-semibold tabular-nums"
          >{{ formatCompact(bar.value) }}</text>
          <text
            :x="bar.centre"
            :y="height - 12"
            text-anchor="middle"
            class="fill-muted-foreground text-xs"
          >{{ bar.label }}</text>
        </g>
      </svg>
    </div>
  </div>
</template>
