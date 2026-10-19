<script setup>
/**
 * A quiet note when the party on this document also trades the other way.
 *
 * Selling to someone who already owes us nothing while we owe them 3,000 is
 * perfectly correct — but whoever is entering it usually wants to know, since
 * a set-off costs nothing and moving money twice costs a transfer each way.
 *
 * Renders nothing unless the party is paired AND the other account actually
 * has something outstanding. A hint that fires on every party is noise, and
 * noise is what gets a warning ignored when it matters.
 */
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  /** The selected party option, as the picker hands it over. */
  ledger: { type: Object, default: null },
})

const { t } = useI18n()
const page = usePage()

const counterpart = computed(() => {
  const id = props.ledger?.counterpart_ledger_id

  if (!id) return null

  // Read from the list the picker itself was filled from. The pairing may
  // point outside it on a very large chart, and then the hint simply does
  // not appear — it is a nudge, not a control.
  return (page.props.ledgers?.data || []).find((entry) => entry.id === id) || null
})

const outstanding = computed(() => {
  const statement = counterpart.value?.statement

  if (!statement) return 0

  return counterpart.value.type === 'supplier'
    ? Number(statement.payable_amount || 0)
    : Number(statement.receivable_amount || 0)
})

const roleLabel = computed(() => (counterpart.value?.type === 'supplier'
  ? t('contra.supplier_account')
  : t('contra.customer_account')))

const money = (value) => Number(value || 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})
</script>

<template>
  <!-- The span lives here rather than on a wrapper in the form: a wrapper
       would always render, leaving an empty full-width grid cell that pushes
       the next field onto its own row whenever the hint has nothing to say. -->
  <p v-if="outstanding > 0" class="md:col-span-3 -mt-2 text-xs text-amber-600">
    {{ t('contra.also_trades_the_other_way', {
      role: roleLabel,
      amount: money(outstanding),
    }) }}
  </p>
</template>
