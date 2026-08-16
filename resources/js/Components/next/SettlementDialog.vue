<script setup>
/**
 * Matching a receipt or payment to the claims it settles.
 *
 * Used by both the Receipt (customer) and Payment (supplier) forms — they are
 * mirror images, so they share one component rather than two that drift apart.
 * `direction` picks the wording; nothing else differs.
 *
 * The exchange gain or loss is shown LIVE, per row and net, before anything is
 * saved. An invoice booked at 60 and paid at 55 puts a 1,000 afghani expense on
 * the voucher, and the user should see that while they can still change the
 * rate — not discover it in next month's P&L.
 */
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import NextInput from '@/Components/next/NextInput.vue'
import { formatMoney, currencyLabel } from '@/utils/money'

const { t } = useI18n()

const props = defineProps({
  open: Boolean,
  /**
   * Which way the cash moves: 'in' for a receipt, 'out' for a payment.
   *
   * Set by the MODULE, never by the party. Money in relieves what the party
   * owes; money out relieves what is owed to them — so a customer being
   * refunded and a supplier returning an advance both work, and the entry is
   * right in every combination.
   */
  direction: { type: String, default: 'in' },
  ledgerId: { type: String, default: '' },
  /** Currency of the CASH being received or paid. */
  currencyId: { type: String, default: '' },
  currencyCode: { type: String, default: '' },
  /** Cash amount on the voucher, in the cash currency. */
  amount: { type: [Number, String], default: 0 },
  /** Today's rate for the cash currency. */
  rate: { type: [Number, String], default: 1 },
  allocations: { type: Array, default: () => [] },
  /**
   * Cash allocated to each currency the claims are in, for currencies other
   * than the cash's own: [{ currency_id, amount }]. A map rather than a single
   * number because one voucher can settle claims in several currencies.
   */
  appliedCash: { type: Array, default: () => [] },
  /**
   * The voucher being edited, if any.
   *
   * Its own applications are excluded from "already settled", so the documents
   * it paid appear as open again with its amounts pre-ticked. Without this,
   * editing a receipt that closed everything shows an empty list and reads as
   * though the receipt had settled nothing.
   */
  excludeTransactionId: { type: String, default: '' },
})

const emit = defineEmits(['update:open', 'save', 'update:allocations'])

const loading = ref(false)
const previewing = ref(false)
const openItems = ref([])
const rows = ref([])
const balances = ref({ currencies: [], base_total: '0' })
const netForex = ref(0)
const advanceAccount = ref(null)
const loadError = ref('')
/** 'customer' or 'supplier' — decides which advance account leftovers land in. */
const partyType = ref('customer')

const cashAmount = computed(() => Number(props.amount || 0))

/**
 * Claims grouped by their own currency.
 *
 * A customer can owe in several at once — an opening balance in afghanis and
 * another in dollars is the normal case here — and one payment settles them
 * all. The totals are kept per currency and NEVER summed: 10,000 AFN plus $200
 * is not 10,200 of anything, and showing it as one figure is how a receipt gets
 * saved having applied 200 afghanis to a 200-dollar debt.
 */
const currencies = computed(() => {
  const groups = new Map()

  rows.value.forEach((row) => {
    if (!groups.has(row.currency_id)) {
      groups.set(row.currency_id, {
        currency_id: row.currency_id,
        currency_code: row.currency_code,
        is_cross_currency: row.currency_id !== props.currencyId,
        applied: 0,
        rows: [],
      })
    }

    const group = groups.get(row.currency_id)
    group.rows.push(row)

    if (row.selected) {
      group.applied += Number(row.amount || 0)
    }
  })

  return Array.from(groups.values())
})

/** Currencies actually being settled, in a currency other than the cash's. */
const foreignGroups = computed(() => currencies.value.filter(
  (group) => group.is_cross_currency && group.applied > 0,
))

const isCrossCurrency = computed(() => foreignGroups.value.length > 0)

/**
 * How much cash the user says goes to each non-cash currency.
 *
 * Kept as a map rather than a single number because a voucher can settle more
 * than one foreign currency at a time. Defaults to the claim's own booking
 * rate, which realises no exchange difference — a sensible starting point that
 * the user is expected to correct when the agreed rate was something else.
 */
const cashByCurrency = ref({})

const defaultCashFor = (group) => group.rows.reduce(
  (total, row) => total + (row.selected ? Number(row.amount || 0) * Number(row.target_rate || 1) : 0),
  0,
) / Number(props.rate || 1)

const cashFor = (currencyId) => {
  const stated = cashByCurrency.value[currencyId]
  if (stated !== undefined && stated !== '' && stated !== null) return Number(stated)

  const group = currencies.value.find((row) => row.currency_id === currencyId)
  return group ? defaultCashFor(group) : 0
}

const setCashFor = (currencyId, value) => {
  cashByCurrency.value = { ...cashByCurrency.value, [currencyId]: value }
}

/** Cash consumed by same-currency claims plus the stated foreign splits. */
const cashApplied = computed(() => currencies.value.reduce((total, group) => total + (
  group.is_cross_currency ? cashFor(group.currency_id) : group.applied
), 0))

const unapplied = computed(() => Math.max(cashAmount.value - cashApplied.value, 0))

const isOverapplied = computed(() => cashApplied.value - cashAmount.value > 0.00001)

const isOverpayment = computed(() => unapplied.value > 0.00001)

/**
 * The rate each foreign currency ends up settling at. Shown so the user can
 * sanity-check what they typed — the form sends both amounts and the server
 * keeps them as entered rather than deriving one from the other.
 */
const impliedRate = (group) => (group.applied > 0
  ? (cashFor(group.currency_id) * Number(props.rate || 1)) / group.applied
  : null)

const advanceAccountLabel = computed(() => (partyType.value === 'supplier'
  ? t('settlement.supplier_advances')
  : t('settlement.customer_advances')))

const isValid = computed(() => !isOverapplied.value && (
  rows.value.some((row) => row.selected && Number(row.amount || 0) > 0) || isOverpayment.value
))

async function loadOpenItems() {
  if (!props.ledgerId) {
    openItems.value = []
    rows.value = []
    return
  }

  loading.value = true
  loadError.value = ''

  try {
    const { data } = await axios.get('/settlements/open-items', {
      params: {
        ledger_id: props.ledgerId,
        // The MODULE's direction, not the party's type. Money in relieves what
        // they owe, money out relieves what is owed to them — which is what
        // lets a refund to a customer and a rebate from a supplier both work.
        direction: props.direction,
        exclude_transaction_id: props.excludeTransactionId || undefined,
      },
    })

    openItems.value = data?.data || []
    balances.value = data?.meta?.balances || { currencies: [], base_total: '0' }
    partyType.value = data?.meta?.ledger_type || 'customer'
    buildRows()

    // Restore a previously saved cash split when reopening the dialog.
    cashByCurrency.value = Object.fromEntries(
      (props.appliedCash || []).map((entry) => [entry.currency_id, entry.amount]),
    )
  } catch (error) {
    loadError.value = error?.response?.data?.message || t('general.error')
    openItems.value = []
    rows.value = []
  } finally {
    loading.value = false
  }
}

function buildRows() {
  const existing = new Map(
    (props.allocations || []).map((allocation) => [
      String(allocation.target_line_id),
      Number(allocation.amount || 0),
    ]),
  )

  rows.value = openItems.value.map((item) => {
    const applied = existing.get(String(item.target_line_id))

    return {
      ...item,
      selected: typeof applied !== 'undefined',
      amount: applied ?? 0,
      forex_amount: 0,
    }
  })
}

function toggleRow(row, checked) {
  row.selected = checked

  if (!checked) {
    row.amount = 0
    row.forex_amount = 0
    return
  }

  if (!Number(row.amount)) {
    const cashLeft = Math.max(cashAmount.value - cashApplied.value, 0)

    // Same currency as the cash: one unit settles one unit, whatever the rates
    // are. Only a claim in ANOTHER currency needs converting, and it converts
    // at its own booking rate.
    const affordable = row.currency_id === props.currencyId
      ? cashLeft
      : cashLeft * Number(props.rate || 1) / Number(row.target_rate || 1)

    row.amount = Math.min(Number(row.remaining_amount || 0), affordable)
  }
}

/**
 * Oldest first, until the money runs out — across every currency the party owes
 * in, converting at each claim's own booking rate.
 *
 * That conversion is the default, not a decision: settling at the booking rate
 * realises no exchange difference, which is the honest starting point when
 * nobody has stated an agreed rate. The user can then correct the cash split
 * per currency, and the FX preview updates.
 *
 * The button exists because FIFO is the common case, but it never runs on its
 * own — customers here routinely say "this is for invoice 254", and silently
 * overriding that produces a subledger that disagrees with what they think they
 * paid.
 */
async function autoFifo() {
  if (!props.ledgerId || !cashAmount.value) return

  previewing.value = true

  try {
    // The server does the spreading. Doing it here meant two implementations
    // of "how far does this cash go", and the one on screen was the wrong one:
    // it converted through the rates even for claims already in the cash's own
    // currency, so 200 USD against a 200 USD opening applied 183.33 and left
    // the customer owing money they had paid.
    const { data } = await axios.post('/settlements/preview', {
      ledger_id: props.ledgerId,
      direction: props.direction,
      cash_currency_id: props.currencyId,
      cash_rate: Number(props.rate || 1),
      cash_amount: cashAmount.value,
      strategy: 'fifo',
      exclude_transaction_id: props.excludeTransactionId || undefined,
    })

    applyServerPlan(data?.data)
  } catch (error) {
    loadError.value = error?.response?.data?.message || t('general.error')
  } finally {
    previewing.value = false
  }
}

/** Write a server-computed plan onto the rows. */
function applyServerPlan(plan) {
  const applied = new Map(
    (plan?.allocations || []).map((row) => [String(row.target_line_id), row]),
  )

  rows.value.forEach((row) => {
    const match = applied.get(String(row.target_line_id))

    row.selected = Boolean(match)
    row.amount = match ? Number(match.amount_applied) : 0
    row.forex_amount = match ? Number(match.forex_amount || 0) : 0
  })

  cashByCurrency.value = Object.fromEntries(
    (plan?.currencies || [])
      .filter((group) => group.is_cross_currency)
      .map((group) => [group.currency_id, Number(group.cash_applied)]),
  )

  netForex.value = Number(plan?.net_forex || 0)
}

function clearAll() {
  rows.value.forEach((row) => {
    row.selected = false
    row.amount = 0
    row.forex_amount = 0
  })

  cashByCurrency.value = {}
}

/**
 * Ask the server what FX each row would realise.
 *
 * The arithmetic is simple enough to do here, but doing it here would mean two
 * implementations of the rule that decides what lands in the P&L. One of them
 * would eventually be wrong, and it would be the one the user was looking at.
 */
async function refreshPreview() {
  const selected = serialize()

  if (!props.ledgerId || !cashAmount.value || selected.length === 0) {
    netForex.value = 0
    advanceAccount.value = isOverpayment.value ? advanceAccountLabel.value : null
    rows.value.forEach((row) => { row.forex_amount = 0 })
    return
  }

  previewing.value = true

  try {
    const { data } = await axios.post('/settlements/preview', {
      ledger_id: props.ledgerId,
      cash_currency_id: props.currencyId,
      cash_rate: Number(props.rate || 1),
      cash_amount: cashAmount.value,
      allocations: selected,
      applied_cash: serializeCashSplit(),
      exclude_transaction_id: props.excludeTransactionId || undefined,
      direction: props.direction,
    })

    const byLine = new Map(
      (data?.data?.allocations || []).map((row) => [String(row.target_line_id), Number(row.forex_amount || 0)]),
    )

    rows.value.forEach((row) => {
      row.forex_amount = byLine.get(String(row.target_line_id)) ?? 0
    })

    netForex.value = Number(data?.data?.net_forex || 0)
  } catch {
    // A failed preview must not block the form — the server recomputes and
    // validates everything on save regardless.
    netForex.value = 0
  } finally {
    previewing.value = false
    advanceAccount.value = isOverpayment.value ? advanceAccountLabel.value : null
  }
}

function serialize() {
  return rows.value
    .filter((row) => row.selected && Number(row.amount || 0) > 0)
    .map((row) => ({
      target_line_id: row.target_line_id,
      amount: Number(row.amount || 0),
    }))
}

/** One entry per foreign currency being settled. */
function serializeCashSplit() {
  return foreignGroups.value.map((group) => ({
    currency_id: group.currency_id,
    amount: Number(cashFor(group.currency_id).toFixed(4)),
  }))
}

function close() {
  emit('update:open', false)
}

function save() {
  if (!isValid.value) return

  emit('save', {
    allocations: serialize(),
    applied_cash: serializeCashSplit(),
  })

  close()
}

const forexClass = (value) => {
  if (value > 0.00001) return 'text-emerald-500'
  if (value < -0.00001) return 'text-red-500'
  return 'text-muted-foreground'
}

watch(() => props.open, (isOpen) => {
  if (isOpen) loadOpenItems()
}, { immediate: true })

watch(() => props.ledgerId, () => {
  if (props.open) loadOpenItems()
})

watch(rows, () => {
  emit('update:allocations', serialize())
  refreshPreview()
}, { deep: true })

watch([() => props.rate, () => props.amount, cashByCurrency], () => {
  if (props.open) refreshPreview()
}, { deep: true })
</script>

<template>
  <Dialog :open="open" @update:open="close">
    <DialogContent class="max-w-6xl overflow-y-auto max-h-[90vh]">
      <DialogHeader>
        <DialogTitle>
          {{ direction === 'out' ? t('settlement.settle_bills') : t('settlement.settle_invoices') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('settlement.relieved_at_booking_rate') }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="loading" class="py-10 text-center text-muted-foreground">
        {{ t('general.loading') }}...
      </div>

      <div v-else-if="loadError" class="rounded-lg border border-red-500/40 bg-red-500/5 p-4 text-sm text-red-500">
        {{ loadError }}
      </div>


      <div v-else class="space-y-4">
        <!-- Running totals in the CASH's currency. "Applied" is the cash
             consumed, not a sum of the amounts applied — those are in different
             currencies and adding them is meaningless. -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('settlement.voucher_amount') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums">
              {{ formatMoney(cashAmount) }} <span class="text-xs font-normal text-muted-foreground">{{ currencyLabel(currencyCode) }}</span>
            </div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('settlement.cash_used') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums" :class="isOverapplied ? 'text-red-500' : ''">
              {{ formatMoney(cashApplied) }} <span class="text-xs font-normal text-muted-foreground">{{ currencyLabel(currencyCode) }}</span>
            </div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('settlement.unapplied') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums" :class="isOverpayment ? 'text-amber-500' : ''">
              {{ formatMoney(unapplied) }} <span class="text-xs font-normal text-muted-foreground">{{ currencyLabel(currencyCode) }}</span>
            </div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('settlement.net_forex') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums" :class="forexClass(netForex)">
              {{ formatMoney(Math.abs(netForex)) }}
              <span class="text-xs font-normal">
                {{ netForex > 0 ? t('settlement.gain') : (netForex < 0 ? t('settlement.loss') : '') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Applied per currency. Never one figure: 10,000 AFN and $200 are two
             separate debts and two separate settlements. -->
        <div v-if="currencies.length" class="flex flex-wrap gap-2">
          <div
            v-for="group in currencies"
            :key="group.currency_id"
            class="rounded-lg border px-3 py-1.5 text-sm"
            :class="group.applied > 0 ? 'border-violet-500/40 bg-violet-500/5' : 'bg-muted/20'"
          >
            <span class="text-xs text-muted-foreground">{{ t('settlement.applied') }}</span>
            <span class="ml-2 font-semibold tabular-nums">{{ formatMoney(group.applied) }}</span>
            <span class="ml-1 text-xs text-muted-foreground">{{ currencyLabel(group.currency_code) }}</span>
          </div>
        </div>

        <div
          v-if="isOverapplied"
          class="rounded-lg border border-red-500/40 bg-red-500/5 p-3 text-sm text-red-500"
        >
          {{ t('settlement.over_applied', {
            amount: formatMoney(cashApplied - cashAmount),
            currency: currencyLabel(currencyCode),
          }) }}
        </div>

        <!-- Overpayment: name the account it will land in. "Where did my extra
             300 go" is a question worth answering before it is asked. -->
        <div
          v-if="isOverpayment"
          class="rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm"
        >
          <span class="font-semibold text-amber-500">{{ t('settlement.overpayment') }}:</span>
          {{ t('settlement.overpayment_hint', {
            amount: formatMoney(unapplied),
            currency: currencyLabel(currencyCode),
            account: advanceAccountLabel,
          }) }}
        </div>

        <!-- Cross-currency: the cash going to each foreign claim is stated, not
             derived. One block per currency, because a single voucher can
             settle several. Pre-filled at each claim's booking rate — which
             realises no exchange difference — for the user to correct when the
             agreed rate was something else. -->
        <div
          v-for="group in foreignGroups"
          :key="'cash-' + group.currency_id"
          class="grid grid-cols-1 gap-3 rounded-lg border border-violet-500/40 bg-violet-500/5 p-3 md:grid-cols-3"
        >
          <div class="md:col-span-3 text-xs font-semibold text-violet-500">
            {{ t('settlement.cross_currency_hint', {
              cash: currencyLabel(currencyCode),
              claim: currencyLabel(group.currency_code),
            }) }}
          </div>
          <NextInput
            :model-value="cashFor(group.currency_id)"
            type="number"
            step="any"
            :label="t('settlement.cash_applied', { currency: currencyLabel(currencyCode) })"
            @update:model-value="(value) => setCashFor(group.currency_id, value)"
          />
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">
              {{ t('settlement.amount_settled', { currency: currencyLabel(group.currency_code) }) }}
            </div>
            <div class="mt-1 text-lg font-bold tabular-nums">{{ formatMoney(group.applied) }}</div>
          </div>
          <div class="rounded-lg border bg-muted/30 p-3">
            <div class="text-xs font-semibold text-violet-500">{{ t('settlement.implied_rate') }}</div>
            <div class="mt-1 text-lg font-bold tabular-nums">
              {{ impliedRate(group) ? formatMoney(impliedRate(group), 4) : '-' }}
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="text-xs text-muted-foreground">
            <span v-for="balance in balances.currencies" :key="balance.currency_id" class="mr-3">
              {{ currencyLabel(balance.currency_code) }}
              <span class="font-semibold tabular-nums">{{ formatMoney(balance.balance) }}</span>
            </span>
          </div>
          <div class="flex gap-2">
            <Button type="button" variant="ghost" @click="clearAll">{{ t('general.clear') }}</Button>
            <Button type="button" variant="outline" @click="autoFifo">
              {{ t('settlement.auto_fifo') }}
            </Button>
          </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40">
              <tr>
                <th class="px-3 py-2 text-left">{{ t('general.select') }}</th>
                <th class="px-3 py-2 text-left">{{ t('general.date') }}</th>
                <th class="px-3 py-2 text-left">{{ t('settlement.document') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.original_amount') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.already_settled') }}</th>
                <th class="px-3 py-2 text-right">{{ t('general.remaining_amount') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.booking_rate') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.apply') }}</th>
                <th class="px-3 py-2 text-right">{{ t('settlement.forex') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.target_line_id" class="border-t">
                <td class="px-3 py-2">
                  <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-border"
                    :checked="row.selected"
                    @change="toggleRow(row, $event.target.checked)"
                  >
                </td>
                <td class="px-3 py-2 whitespace-nowrap">{{ row.date || '-' }}</td>
                <td class="px-3 py-2 font-medium">
                  {{ row.document_type }}
                  <span v-if="row.document_number">#{{ row.document_number }}</span>
                  <div class="text-xs text-muted-foreground">{{ currencyLabel(row.currency_code) }}</div>
                </td>
                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.original_amount) }}</td>
                <td class="px-3 py-2 text-right tabular-nums text-muted-foreground">{{ formatMoney(row.settled_amount) }}</td>
                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.remaining_amount) }}</td>
                <!-- The rate the claim was BOOKED at. The unpaid part keeps it. -->
                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row.target_rate) }}</td>
                <td class="px-3 py-2 text-right">
                  <NextInput
                    v-model="row.amount"
                    :disabled="!row.selected"
                    type="number"
                    step="any"
                    min="0"
                    :placeholder="t('general.enter', { text: t('general.amount') })"
                  />
                </td>
                <td class="px-3 py-2 text-right tabular-nums" :class="forexClass(row.forex_amount)">
                  <span v-if="row.selected">{{ formatMoney(Math.abs(row.forex_amount)) }}</span>
                  <span v-else class="text-muted-foreground">-</span>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="9" class="px-3 py-6 text-center text-muted-foreground">
                  {{ t('settlement.nothing_open') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button type="button" variant="outline" @click="close">{{ t('general.cancel') }}</Button>
        <Button type="button" :disabled="!isValid || previewing" @click="save">{{ t('general.save') }}</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
