<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link } from '@inertiajs/vue3'
import { formatNumber } from './format'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  items: { type: Array, default: () => [] },
  // `in` for the money-in lists (sales, receivables), `out` for the money-out
  // ones (purchases, payables) — the same two hues the trend chart uses.
  tone: { type: String, default: 'in' },
  // Given a row, returns where it should link to. Rows stay inert without it.
  itemHref: { type: Function, default: null },
  // Unit shown after `row.count` — defaults to the transaction-count lists'
  // "records"; item-quantity lists (e.g. top selling items) pass their own.
  countLabel: { type: String, default: '' },
})

const { t } = useI18n()

const accent = computed(() => (props.tone === 'out' ? 'var(--viz-out)' : 'var(--viz-in)'))
const track = computed(() => (props.tone === 'out' ? 'var(--viz-out-soft)' : 'var(--viz-in-soft)'))

function amountOf(item) {
  return Number(item.total ?? item.balance ?? 0)
}

const total = computed(() => props.items.reduce((sum, item) => sum + amountOf(item), 0))

// Bars are scaled against the largest row, not the total: these are top-N lists,
// so the share of a partial total would overstate every row.
const largest = computed(() => Math.max(...props.items.map(amountOf), 0))

const rows = computed(() => props.items.map((item, index) => {
  const amount = amountOf(item)

  return {
    ...item,
    rank: index + 1,
    amount,
    share: largest.value > 0 ? Math.max((amount / largest.value) * 100, 2) : 0,
  }
}))
</script>

<template>
  <div class="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
    <div class="flex items-start justify-between gap-4 px-5 pt-5">
      <div>
        <h3 class="text-base font-semibold text-card-foreground">{{ title }}</h3>
        <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
      </div>
      <div v-if="rows.length" class="text-end">
        <p class="text-[11px] uppercase tracking-wide text-muted-foreground">{{ t('dashboard.total') }}</p>
        <p class="text-sm font-semibold text-card-foreground [font-variant-numeric:tabular-nums]">{{ formatNumber(total) }}</p>
      </div>
    </div>

    <div class="flex-1 px-5 pb-5 pt-4">
      <div v-if="!rows.length" class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
        {{ t('dashboard.no_data_available') }}
      </div>

      <ul v-else class="space-y-3.5">
        <li v-for="row in rows" :key="row.id">
          <component
            :is="itemHref ? Link : 'div'"
            :href="itemHref ? itemHref(row) : undefined"
            :class="[
              'block space-y-2 rounded-lg px-2 py-1.5 -mx-2 transition-colors',
              itemHref ? 'cursor-pointer hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring' : '',
            ]"
          >
          <div class="flex items-baseline justify-between gap-3">
            <div class="flex min-w-0 items-baseline gap-2">
              <span class="w-4 shrink-0 text-xs font-semibold text-muted-foreground [font-variant-numeric:tabular-nums]">{{ row.rank }}</span>
              <span class="truncate text-sm font-medium text-card-foreground" :title="row.name">{{ row.name }}</span>
              <span v-if="row.count !== undefined" class="shrink-0 text-xs text-muted-foreground">
                {{ formatNumber(row.count, 'count') }} {{ countLabel || t('dashboard.records') }}
              </span>
            </div>
            <span class="shrink-0 text-sm font-semibold text-card-foreground [font-variant-numeric:tabular-nums]">
              {{ formatNumber(row.amount) }}
            </span>
          </div>

          <div class="h-1.5 w-full overflow-hidden rounded-full" :style="{ backgroundColor: track }">
            <div class="h-full rounded-full" :style="{ width: `${row.share}%`, backgroundColor: accent }" />
          </div>
          </component>
        </li>
      </ul>
    </div>
  </div>
</template>
