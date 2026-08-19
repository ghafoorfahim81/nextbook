<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import NextInput from '@/Components/next/NextInput.vue'
import { toHomeCurrency } from '@/utils/currency'
import { unwrap } from '@/utils/ledgerOpenings'

/**
 * Opening balances for a customer/supplier, one row per currency.
 *
 * A party can owe in several currencies at once (100 USD *and* 10,000 AFN), and
 * each becomes its own posted transaction because `transactions.currency_id`
 * holds a single currency per voucher. Every currency is listed up front rather
 * than behind an "add row" button, so entering a second currency costs one
 * keystroke and the user can see at a glance which currencies are already open.
 */
const props = defineProps({
  // [{ currency_id, rate, amount, remark }] — one entry per currency, in the
  // same order as `currencies`, so server-side error indices line up with rows.
  modelValue: { type: Array, default: () => [] },
  // Either a plain array or an Inertia resource collection (`{ data: [...] }`) —
  // the pages pass the shared `currencies` prop straight through.
  currencies: { type: [Array, Object], default: () => [] },
  homeCurrency: { type: Object, default: null },
  // The form's `errors` bag, read with `openings.<index>.<field>` keys.
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const { t, locale } = useI18n()

const isRTL = computed(() => ['fa', 'ps', 'pa'].includes(locale.value))

const isHome = (row) => !!props.homeCurrency && row.currency_id === props.homeCurrency.id

const updateRow = (index, field, value) => {
  const next = props.modelValue.map((row, i) => (
    i === index ? { ...row, [field]: value } : row
  ))

  emit('update:modelValue', next)
}

const errorFor = (index, field) => props.errors?.[`openings.${index}.${field}`]

const formatMoney = (value) => Number(value ?? 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const homeEquivalent = (row) => toHomeCurrency(row.amount, row.rate)

// Only rows the user actually filled in count — the rest are placeholders the
// backend discards.
const filledRows = computed(() => props.modelValue.filter((row) => Number(row.amount) > 0))

const homeTotal = computed(() => filledRows.value.reduce(
  (total, row) => total + homeEquivalent(row),
  0,
))

const currencyList = computed(() => unwrap(props.currencies))

const currencyFor = (row) => currencyList.value.find((currency) => currency.id === row.currency_id)
</script>

<template>
  <div class="rounded-xl border border-border bg-card shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
      <div>
        <h3 class="text-sm font-semibold text-violet-500">{{ t('ledger.opening.title') }}</h3>
        <p class="text-xs text-muted-foreground">{{ t('ledger.opening.hint') }}</p>
      </div>
      <div v-if="filledRows.length" class="text-xs text-muted-foreground">
        {{ t('ledger.opening.currencies_used', { count: filledRows.length }) }}
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="border-b border-border text-muted-foreground" :class="isRTL ? 'text-right' : 'text-left'">
            <th class="px-4 py-2 font-medium">{{ t('admin.currency.currency') }}</th>
            <th class="px-4 py-2 font-medium w-40">{{ t('general.rate') }}</th>
            <th class="px-4 py-2 font-medium w-44">{{ t('general.amount') }}</th>
            <th class="px-4 py-2 font-medium">{{ t('general.remark') }}</th>
            <th class="px-4 py-2 font-medium text-right rtl:text-left w-44">
              {{ homeCurrency ? t('ledger.opening.home_equivalent', { code: homeCurrency.code }) : t('general.total') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!modelValue.length">
            <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
              {{ t('general.no_data_found') }}
            </td>
          </tr>

          <tr
            v-for="(row, index) in modelValue"
            :key="row.currency_id"
            class="border-b border-border last:border-b-0"
            :class="Number(row.amount) > 0 ? 'bg-violet-500/5' : ''"
          >
            <td class="whitespace-nowrap px-4 py-2">
              <div class="flex items-center gap-2">
                <span class="font-medium text-foreground">{{ currencyFor(row)?.code }}</span>
                <span
                  v-if="isHome(row)"
                  class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                >
                  {{ t('ledger.opening.home') }}
                </span>
              </div>
              <div class="text-xs text-muted-foreground">{{ currencyFor(row)?.name }}</div>
            </td>

            <td class="px-4 py-2">
              <!--
                The home currency is by definition 1:1 with itself, so its rate is
                fixed rather than left editable.
              -->
              <NextInput
                type="number"
                step="any"
                :label="t('general.rate')"
                :placeholder="t('general.rate')"
                :disabled="disabled || isHome(row)"
                :model-value="row.rate"
                :error="errorFor(index, 'rate')"
                @update:modelValue="(value) => updateRow(index, 'rate', value)"
              />
            </td>

            <td class="px-4 py-2">
              <NextInput
                type="number"
                step="any"
                :label="t('general.amount')"
                :placeholder="t('general.amount')"
                :disabled="disabled"
                :model-value="row.amount"
                :error="errorFor(index, 'amount') || errorFor(index, 'currency_id')"
                @update:modelValue="(value) => updateRow(index, 'amount', value)"
              />
            </td>

            <td class="px-4 py-2">
              <NextInput
                :label="t('general.remark')"
                :placeholder="t('general.enter', { text: t('general.remark') })"
                :disabled="disabled"
                :model-value="row.remark"
                :error="errorFor(index, 'remark')"
                @update:modelValue="(value) => updateRow(index, 'remark', value)"
              />
            </td>

            <td class="whitespace-nowrap px-4 py-2 text-right rtl:text-left">
              <span v-if="Number(row.amount) > 0" class="font-medium text-foreground">
                {{ formatMoney(homeEquivalent(row)) }}
              </span>
              <span v-else class="text-muted-foreground">-</span>
            </td>
          </tr>
        </tbody>
        <tfoot v-if="filledRows.length && homeCurrency">
          <!--
            Currencies are never summed directly. This total is each row converted
            at its own rate, which is the figure that reaches the general ledger.
          -->
          <tr class="border-t border-border bg-muted/40">
            <td colspan="4" class="px-4 py-2 text-xs text-muted-foreground">
              {{ t('ledger.opening.total_note', { code: homeCurrency.code }) }}
            </td>
            <td class="whitespace-nowrap px-4 py-2 text-right rtl:text-left font-semibold text-foreground">
              {{ formatMoney(homeTotal) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>
