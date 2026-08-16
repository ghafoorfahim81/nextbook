<script setup>
/**
 * A receipt or a payment, as a full page.
 *
 * The two are mirrors, so they share this rather than two files that drift.
 * `kind` decides the wording and the routes; nothing else differs.
 *
 * This replaced a dialog. The extra room is not decoration — a settlement
 * voucher now carries the cash line, one receivable line per booking rate, and
 * an exchange difference, and the whole point of showing it is to answer "where
 * did this 1,000 afghani expense come from". A cramped modal could not.
 */
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { router } from '@inertiajs/vue3'
import { Button } from '@/Components/ui/button'
import {
  ArrowLeft,
  Calendar,
  Coins,
  FileText,
  Printer,
  SquarePen,
  User,
} from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { formatMoney, currencyLabel } from '@/utils/money'

const { t } = useI18n()
const { can } = useAuth()

const props = defineProps({
  voucher: { type: Object, required: true },
  /** Enriched settlement rows: which documents this voucher relieved. */
  settlements: { type: Array, default: () => [] },
  /** 'receipt' or 'payment'. */
  kind: { type: String, default: 'receipt' },
  indexRoute: { type: String, required: true },
  editRoute: { type: String, required: true },
  printRoute: { type: String, required: true },
  permission: { type: String, required: true },
})

const data = computed(() => props.voucher?.data ?? props.voucher ?? {})
const lines = computed(() => data.value.transaction?.lines || [])

const title = computed(() => (props.kind === 'payment' ? t('payment.payment') : t('receipt.receipt')))
const partyLabel = computed(() => (props.kind === 'payment'
  ? t('ledger.supplier.supplier')
  : t('ledger.customer.customer')))

/** Totals of the posted entry, in base currency — the only figure that ties. */
const baseTotals = computed(() => lines.value.reduce(
  (totals, line) => ({
    debit: totals.debit + Number(line.base_debit || 0),
    credit: totals.credit + Number(line.base_credit || 0),
  }),
  { debit: 0, credit: 0 },
))

const netForex = computed(() => props.settlements.reduce(
  (total, row) => total + Number(row.forex_amount || 0),
  0,
))

const forexClass = (kind) => (kind === 'gain'
  ? 'text-emerald-600 dark:text-emerald-400'
  : (kind === 'loss' ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'))
</script>

<template>
  <div class="space-y-6">
    <!-- Page header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <Button variant="outline" size="sm" @click="router.visit(route(indexRoute))">
        <ArrowLeft class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
        {{ t('general.back') }}
      </Button>
      <div class="flex items-center gap-2">
        <a :href="route(printRoute, data.id)" target="_blank" rel="noopener noreferrer">
          <Button variant="outline" size="sm">
            <Printer class="h-4 w-4 ltr:mr-1 rtl:ml-1" />
            {{ t('general.print') }}
          </Button>
        </a>
        <Button
          v-if="can(permission) && data.id"
          variant="default"
          size="sm"
          class="gap-1.5 bg-primary text-primary-foreground"
          @click="router.visit(route(editRoute, data.id))"
        >
          <SquarePen class="h-4 w-4" />
          {{ t('datatable.edit') }}
        </Button>
      </div>
    </div>

    <!-- Details -->
    <fieldset class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm">
      <legend class="px-2">
        <span class="text-sm font-semibold text-primary">{{ title }} #{{ data.number }}</span>
      </legend>
      <div class="mb-4 flex items-center gap-2">
        <FileText class="h-5 w-5 text-primary" />
        <h3 class="text-base font-semibold text-foreground">{{ t('general.details') }}</h3>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Calendar class="h-3 w-3" />{{ t('general.date') }}
          </div>
          <div class="text-sm font-medium text-foreground">{{ data.date || '-' }}</div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <User class="h-3 w-3" />{{ partyLabel }}
          </div>
          <div class="text-sm font-medium text-foreground">
            {{ data.ledger?.name || data.ledger_name || '-' }}
          </div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Coins class="h-3 w-3" />{{ t('general.amount') }}
          </div>
          <div class="text-sm font-semibold text-foreground tabular-nums">
            {{ formatMoney(data.amount) }}
            <span class="text-xs font-normal text-muted-foreground">{{ currencyLabel(data.currency_code) }}</span>
          </div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <Coins class="h-3 w-3" />{{ t('general.rate') }}
          </div>
          <div class="text-sm font-medium text-foreground tabular-nums">{{ data.rate ?? '-' }}</div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <FileText class="h-3 w-3" />{{ t('expense.bank_account') }}
          </div>
          <div class="text-sm font-medium text-foreground">{{ data.bank_account_name || '-' }}</div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <FileText class="h-3 w-3" />{{ t('general.payment_mode') }}
          </div>
          <div class="text-sm font-medium text-foreground">
            {{ data.payment_mode_label || data.payment_mode || '-' }}
          </div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <FileText class="h-3 w-3" />{{ t('receipt.cheque_no') }}
          </div>
          <div class="text-sm font-medium text-foreground">{{ data.cheque_no || '-' }}</div>
        </div>
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-xs text-muted-foreground">
            <User class="h-3 w-3" />{{ t('general.created_by') }}
          </div>
          <div class="text-sm font-medium text-foreground">{{ data.created_by?.name || '-' }}</div>
        </div>
      </div>

      <div v-if="data.narration" class="mt-4 rounded-lg border border-border bg-muted/20 p-3">
        <div class="text-xs text-muted-foreground">{{ t('general.narration') }}</div>
        <div class="mt-1 text-sm text-foreground">{{ data.narration }}</div>
      </div>
    </fieldset>

    <!-- What it settled. Both rates side by side, because that pair IS the
         explanation for any exchange gain or loss on the voucher. -->
    <fieldset
      v-if="settlements.length"
      class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm"
    >
      <legend class="px-2">
        <span class="text-sm font-semibold text-primary">{{ t('settlement.settled_documents') }}</span>
      </legend>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-muted/40">
            <tr>
              <th class="px-3 py-2 text-left">{{ t('general.date') }}</th>
              <th class="px-3 py-2 text-left">{{ t('settlement.document') }}</th>
              <th class="px-3 py-2 text-right">{{ t('general.amount') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.booking_rate') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.settlement_rate') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.forex') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="settlement in settlements" :key="settlement.id" class="border-t border-border">
              <td class="px-3 py-2 whitespace-nowrap">{{ settlement.date || '-' }}</td>
              <td class="px-3 py-2 font-medium">
                {{ settlement.document_type }}
                <span v-if="settlement.document_number">#{{ settlement.document_number }}</span>
                <span
                  v-if="settlement.is_cross_currency"
                  class="ml-1 rounded bg-primary/10 px-1.5 py-0.5 text-xs text-primary"
                >{{ t('settlement.cross_currency') }}</span>
                <div class="text-xs text-muted-foreground">{{ currencyLabel(settlement.currency_code) }}</div>
              </td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(settlement.amount_applied) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(settlement.target_rate) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(settlement.settlement_rate) }}</td>
              <td class="px-3 py-2 text-right tabular-nums" :class="forexClass(settlement.forex_kind)">
                {{ formatMoney(Math.abs(settlement.forex_amount)) }}
                <span v-if="settlement.forex_kind !== 'none'" class="text-xs">
                  {{ t('settlement.' + settlement.forex_kind) }}
                </span>
              </td>
            </tr>
          </tbody>
          <tfoot v-if="Math.abs(netForex) > 0.00001">
            <tr class="border-t border-border bg-muted/20 font-semibold">
              <td class="px-3 py-2" colspan="5">{{ t('settlement.net_forex') }}</td>
              <td class="px-3 py-2 text-right tabular-nums" :class="forexClass(netForex > 0 ? 'gain' : 'loss')">
                {{ formatMoney(Math.abs(netForex)) }}
                <span class="text-xs">{{ netForex > 0 ? t('settlement.gain') : t('settlement.loss') }}</span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </fieldset>

    <!-- The entry as posted. Every line carries its own currency and rate, so
         the receivable relieved at 60 and the cash taken at 55 sit side by side
         and the exchange line between them is self-explanatory. -->
    <fieldset
      v-if="lines.length"
      class="rounded-xl border border-border bg-card px-5 pb-5 pt-3 text-card-foreground shadow-sm"
    >
      <legend class="px-2">
        <span class="text-sm font-semibold text-primary">{{ t('settlement.journal_entry') }}</span>
      </legend>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-muted/40">
            <tr>
              <th class="px-3 py-2 text-left">{{ t('general.account') }}</th>
              <th class="px-3 py-2 text-left">{{ t('admin.currency.currency') }}</th>
              <th class="px-3 py-2 text-right">{{ t('general.rate') }}</th>
              <th class="px-3 py-2 text-right">{{ t('general.debit') }}</th>
              <th class="px-3 py-2 text-right">{{ t('general.credit') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.base_debit') }}</th>
              <th class="px-3 py-2 text-right">{{ t('settlement.base_credit') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in lines" :key="line.id" class="border-t border-border">
              <td class="px-3 py-2">
                {{ line.account?.name || '-' }}
                <div v-if="line.remark" class="text-xs text-muted-foreground">{{ line.remark }}</div>
              </td>
              <td class="px-3 py-2">{{ currencyLabel(line.currency_code) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(line.rate, 4) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">
                {{ Number(line.debit) ? formatMoney(line.debit) : '-' }}
              </td>
              <td class="px-3 py-2 text-right tabular-nums">
                {{ Number(line.credit) ? formatMoney(line.credit) : '-' }}
              </td>
              <td class="px-3 py-2 text-right tabular-nums text-muted-foreground">
                {{ Number(line.base_debit) ? formatMoney(line.base_debit) : '-' }}
              </td>
              <td class="px-3 py-2 text-right tabular-nums text-muted-foreground">
                {{ Number(line.base_credit) ? formatMoney(line.base_credit) : '-' }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t border-border bg-muted/20 font-semibold">
              <!-- Only the BASE totals are summed. Document amounts across
                   currencies do not add up to anything meaningful. -->
              <td class="px-3 py-2" colspan="5">{{ t('settlement.base_total') }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(baseTotals.debit) }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(baseTotals.credit) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </fieldset>
  </div>
</template>
