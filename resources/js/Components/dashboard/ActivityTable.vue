<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Link, router } from '@inertiajs/vue3'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-vue-next'
import { formatNumber } from './format'

const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  rows: { type: Array, default: () => [] },
  rowType: { type: String, default: 'transaction' },
  // Given a row, returns where it should link to. Rows stay inert without it.
  rowHref: { type: Function, default: null },
})

const { t } = useI18n()

const isStock = computed(() => props.rowType === 'stock')

// Posted is the settled state; anything else is still in flight and reads as a
// neutral pill rather than a success one.
// The first cell holds a real anchor so the row is reachable by keyboard; this
// makes the rest of the row clickable too, without nesting links inside cells.
function openRow(row, event) {
  if (!props.rowHref || event.target.closest('a')) return

  router.visit(props.rowHref(row))
}

function statusClass(status) {
  return String(status || '').toLowerCase() === 'posted'
    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
    : 'bg-muted text-muted-foreground'
}
</script>

<template>
  <div class="flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
    <div class="px-5 pt-5">
      <h3 class="text-base font-semibold text-card-foreground">{{ title }}</h3>
      <p v-if="description" class="mt-0.5 text-xs text-muted-foreground">{{ description }}</p>
    </div>

    <div class="mt-4 max-h-80 flex-1 overflow-auto px-5 pb-5">
      <div v-if="!rows.length" class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
        {{ t('dashboard.table.no_records') }}
      </div>

      <table v-else class="w-full border-collapse text-sm">
        <thead>
          <tr class="text-[11px] uppercase tracking-wide text-muted-foreground">
            <th class="sticky top-0 bg-card pb-2 text-start font-medium">
              {{ isStock ? t('dashboard.table.item') : t('dashboard.table.reference') }}
            </th>
            <th class="sticky top-0 hidden bg-card pb-2 text-start font-medium sm:table-cell">
              {{ isStock ? t('dashboard.table.location') : t('dashboard.table.party') }}
            </th>
            <th class="sticky top-0 bg-card pb-2 text-end font-medium">
              {{ isStock ? t('dashboard.table.quantity') : t('dashboard.table.amount') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            :class="[
              'border-t border-dashed border-border first:border-t-0',
              rowHref ? 'cursor-pointer transition-colors hover:bg-muted/60' : '',
            ]"
            @click="openRow(row, $event)"
          >
            <td class="py-3 pe-3 align-top">
              <component
                :is="rowHref ? Link : 'div'"
                :href="rowHref ? rowHref(row) : undefined"
                class="flex items-start gap-2.5"
              >
                <span
                  v-if="isStock"
                  :class="[
                    'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg',
                    row.movement_type === 'in'
                      ? 'bg-[var(--viz-in-soft)] text-[var(--viz-in)]'
                      : 'bg-[var(--viz-out-soft)] text-[var(--viz-out)]',
                  ]"
                >
                  <component :is="row.movement_type === 'in' ? ArrowDownLeft : ArrowUpRight" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                  <p class="truncate font-medium text-card-foreground" :title="isStock ? row.item_name : `#${row.number}`">
                    {{ isStock ? row.item_name : `#${row.number}` }}
                  </p>
                  <p class="mt-0.5 text-xs text-muted-foreground">
                    <span>{{ row.date }}</span>
                    <span v-if="isStock && row.batch"> · {{ t('item.batch') }} {{ row.batch }}</span>
                    <span v-if="isStock && row.expire_date"> · {{ row.expire_date }}</span>
                  </p>
                  <p class="mt-1 truncate text-xs text-muted-foreground sm:hidden">
                    {{ isStock ? row.warehouse_name : row.party_name }}
                  </p>
                </div>
              </component>
            </td>

            <td class="hidden py-3 pe-3 align-top sm:table-cell">
              <p class="truncate text-muted-foreground" :title="isStock ? row.warehouse_name : row.party_name">
                {{ isStock ? row.warehouse_name : row.party_name }}
              </p>
              <span
                v-if="!isStock && row.status"
                :class="['mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium capitalize', statusClass(row.status)]"
              >
                {{ row.status }}
              </span>
              <span
                v-if="isStock"
                class="mt-1 inline-flex rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
              >
                {{ row.movement_type === 'in' ? t('item.in') : t('item.out') }}
              </span>
            </td>

            <td class="py-3 text-end align-top font-semibold text-card-foreground [font-variant-numeric:tabular-nums]">
              {{ isStock ? formatNumber(row.quantity, 'quantity') : formatNumber(row.amount) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
