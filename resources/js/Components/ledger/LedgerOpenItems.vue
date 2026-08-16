<script setup>
/**
 * What a customer still owes, or what is still owed to a supplier.
 *
 * Grouped by currency, because a party's balances in different currencies never
 * net against each other — $500 outstanding and 500 AFN outstanding are two
 * separate claims, and adding them produces a number that means nothing.
 *
 * Each row expands to the settlements that have been applied to it, with the
 * booking rate and the settlement rate side by side. That pair is the whole
 * explanation for any exchange gain or loss on the account.
 */
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatMoney, currencyLabel } from '@/utils/money'

const { t } = useI18n()

const props = defineProps({
  openItems: { type: Array, default: () => [] },
  /** target_line_id -> settlements applied to it. */
  history: { type: Object, default: () => ({}) },
  balances: { type: Object, default: () => ({ currencies: [], base_total: '0' }) },
})

const expanded = ref(new Set())

const byCurrency = computed(() => {
  const groups = new Map()

  props.openItems.forEach((item) => {
    const key = item.currency_id
    if (!groups.has(key)) {
      groups.set(key, { currency_id: key, currency_code: item.currency_code, items: [], total: 0 })
    }
    const group = groups.get(key)
    group.items.push(item)
    group.total += Number(item.remaining_amount || 0)
  })

  return Array.from(groups.values())
})

const toggle = (id) => {
  const next = new Set(expanded.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expanded.value = next
}

const historyFor = (id) => props.history?.[id] || []

const forexClass = (kind) => (kind === 'gain'
  ? 'text-emerald-600 dark:text-emerald-400'
  : (kind === 'loss' ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'))
</script>

<template>
  <div class="space-y-4">
    <!-- Balance per currency, and the AFN value of all of them together. The
         base total is the only number that may combine currencies. -->
    <div class="bg-card text-card-foreground rounded-xl shadow-sm border border-border p-4">
      <div class="text-sm font-semibold mb-3 text-primary">{{ t('settlement.balance_per_currency') }}</div>
      <div class="flex flex-wrap gap-4">
        <div v-for="balance in balances.currencies" :key="balance.currency_id" class="min-w-32">
          <div class="text-xs text-muted-foreground">{{ currencyLabel(balance.currency_code) }}</div>
          <div class="text-lg font-semibold tabular-nums">{{ formatMoney(balance.balance) }}</div>
        </div>
        <div class="min-w-32 border-l border-border pl-4">
          <div class="text-xs text-muted-foreground">{{ t('settlement.base_total') }}</div>
          <div class="text-lg font-semibold tabular-nums text-primary">{{ formatMoney(balances.base_total) }}</div>
        </div>
      </div>
    </div>

    <div
      v-for="group in byCurrency"
      :key="group.currency_id"
      class="bg-card text-card-foreground rounded-xl shadow-sm border border-border overflow-hidden"
    >
      <div class="flex items-center justify-between px-4 py-3 border-b border-border">
        <div class="text-sm font-semibold text-primary">
          {{ t('settlement.open_items') }} — {{ currencyLabel(group.currency_code) }}
        </div>
        <div class="text-sm font-semibold tabular-nums">{{ formatMoney(group.total) }}</div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-muted/40">
            <tr>
              <th class="px-3 py-2 text-left">{{ t('general.date') }}</th>
              <th class="px-3 py-2 text-left">{{ t('settlement.document') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.original_amount') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.already_settled') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.remaining') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.booking_rate') }}</th>
              <th class="px-3 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="item in group.items" :key="item.target_line_id">
              <tr class="border-t border-border">
                <td class="px-3 py-2 whitespace-nowrap">{{ item.date || '-' }}</td>
                <td class="px-3 py-2 font-medium">
                  {{ item.document_type }}
                  <span v-if="item.document_number">#{{ item.document_number }}</span>
                </td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(item.original_amount) }}</td>
                <td class="px-3 py-2 text-right tabular-nums text-muted-foreground">{{ formatMoney(item.settled_amount) }}</td>
                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(item.remaining_amount) }}</td>
                <!-- The rate the claim was booked at. The unpaid part keeps it,
                     however many payments have been applied since. -->
                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(item.target_rate) }}</td>
                <td class="px-3 py-2 text-right">
                  <button
                    v-if="historyFor(item.target_line_id).length"
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click="toggle(item.target_line_id)"
                  >
                    {{ expanded.has(item.target_line_id) ? '−' : '+' }}
                  </button>
                </td>
              </tr>

              <tr v-if="expanded.has(item.target_line_id)" class="bg-muted/20">
                <td colspan="7" class="px-6 py-3">
                  <div class="text-xs font-semibold mb-2 text-muted-foreground">
                    {{ t('settlement.settlement_history') }}
                  </div>
                  <table class="min-w-full text-xs">
                    <thead>
                      <tr class="text-muted-foreground">
                        <th class="px-2 py-1 text-left">{{ t('general.date') }}</th>
                        <th class="px-2 py-1 text-left">{{ t('settlement.document') }}</th>
                        <th class="px-2 py-1 text-right">{{ t('general.amount') }}</th>
                        <th class="px-2 py-1 text-right">{{ t('settlement.booking_rate') }}</th>
                        <th class="px-2 py-1 text-right">{{ t('settlement.settlement_rate') }}</th>
                        <th class="px-2 py-1 text-right">{{ t('settlement.forex') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="entry in historyFor(item.target_line_id)" :key="entry.id" class="border-t border-border/60">
                        <td class="px-2 py-1">{{ entry.date || '-' }}</td>
                        <td class="px-2 py-1">
                          {{ entry.document_type }}
                          <span v-if="entry.voucher_number">{{ entry.voucher_number }}</span>
                          <span v-if="entry.is_cross_currency" class="ml-1 rounded bg-primary/10 px-1 text-primary">
                            {{ t('settlement.cross_currency') }}
                          </span>
                        </td>
                        <td class="px-2 py-1 text-right tabular-nums">{{ formatMoney(entry.amount_applied) }}</td>
                        <td class="px-2 py-1 text-right tabular-nums">{{ formatMoney(entry.target_rate) }}</td>
                        <td class="px-2 py-1 text-right tabular-nums">{{ formatMoney(entry.settlement_rate) }}</td>
                        <td class="px-2 py-1 text-right tabular-nums" :class="forexClass(entry.forex_kind)">
                          {{ formatMoney(Math.abs(entry.forex_amount)) }}
                          <span v-if="entry.forex_kind !== 'none'">{{ t('settlement.' + entry.forex_kind) }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="!byCurrency.length" class="rounded-xl border border-border bg-card p-6 text-center text-sm text-muted-foreground">
      {{ t('settlement.nothing_open') }}
    </div>
  </div>
</template>
