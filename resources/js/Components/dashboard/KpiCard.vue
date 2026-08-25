<script setup>
import { computed } from 'vue'
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import { ArrowUpRight as LinkArrow } from 'lucide-vue-next'
import AnimatedNumber from './AnimatedNumber.vue'
import { formatNumber, formatPercent } from './format'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], required: true },
  help: { type: String, default: '' },
  type: { type: String, default: 'money' },
  icon: { type: [Object, Function], default: null },
  // Which accent the icon chip wears. `in` for money coming in, `out` for money
  // going out, `neutral` for holdings and counts that are neither.
  tone: { type: String, default: 'neutral' },
  // { previous, change_percent } straight off the dashboard payload. Omitted for
  // point-in-time balances, which have nothing to be compared against.
  trend: { type: Object, default: null },
  // Whether a rise is a good thing. Purchases and cash paid rising is neither
  // good nor bad, so those tiles show the direction without a verdict colour.
  goodDirection: { type: String, default: 'neutral' },
  // When set, the whole tile becomes a link through to the report behind it.
  href: { type: String, default: '' },
})

const { t } = useI18n()

const change = computed(() => {
  const value = props.trend?.change_percent
  return value === null || value === undefined ? null : Number(value)
})

const direction = computed(() => {
  if (change.value === null || change.value === 0) return 'flat'
  return change.value > 0 ? 'up' : 'down'
})

const deltaIcon = computed(() => {
  if (direction.value === 'up') return ArrowUpRight
  if (direction.value === 'down') return ArrowDownRight
  return Minus
})

const deltaClass = computed(() => {
  if (props.goodDirection === 'neutral' || direction.value === 'flat') {
    return 'bg-muted text-muted-foreground'
  }

  const isGood = direction.value === props.goodDirection

  return isGood
    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
    : 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
})

const toneClass = computed(() => ({
  in: 'bg-[var(--viz-in-soft)] text-[var(--viz-in)]',
  out: 'bg-[var(--viz-out-soft)] text-[var(--viz-out)]',
  neutral: 'bg-primary/10 text-primary',
  warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
  critical: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
}[props.tone] || 'bg-primary/10 text-primary'))
</script>

<template>
  <component
    :is="href ? Link : 'div'"
    :href="href || undefined"
    :class="[
      'group block rounded-2xl border border-border bg-card p-5 text-start shadow-sm transition-shadow duration-200 hover:shadow-md',
      href ? 'cursor-pointer hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2' : '',
    ]"
  >
    <div class="flex items-start justify-between gap-3">
      <p class="flex items-center gap-1.5 text-sm font-semibold leading-5 text-card-foreground">
        {{ label }}
        <LinkArrow
          v-if="href"
          class="h-3.5 w-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
        />
      </p>
      <span v-if="icon" :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', toneClass]">
        <component :is="icon" class="h-[18px] w-[18px]" />
      </span>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2">
      <!-- The headline figure counts up on load; the comparison figure below it
           stays put, so one thing moves per tile rather than two. -->
      <span class="text-[26px] font-semibold leading-none tracking-tight text-card-foreground">
        <AnimatedNumber :value="value" :type="type" />
      </span>

      <span
        v-if="change !== null"
        :class="['inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold', deltaClass]"
      >
        <component :is="deltaIcon" class="h-3.5 w-3.5" />
        {{ formatPercent(change) }}
      </span>
    </div>

    <p v-if="trend" class="mt-2.5 text-xs leading-5 text-muted-foreground">
      {{ t('dashboard.vs_yesterday', { value: formatNumber(trend.previous, type) }) }}
    </p>
    <p v-else-if="help" class="mt-2.5 text-xs leading-5 text-muted-foreground">
      {{ help }}
    </p>
  </component>
</template>
