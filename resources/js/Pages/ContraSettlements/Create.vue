<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SettlementDialog from '@/Components/next/SettlementDialog.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import { useFormGuard } from '@/composables/useFormGuard'
import { useSaveShortcut } from '@/composables/useSaveShortcut'
import { useLazyProps } from '@/composables/useLazyProps'
import { todayValueForCalendar } from '@/utils/dateDefaults'

const { t } = useI18n()
const page = usePage()
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')

useLazyProps(page.props, ['ledgers'])

const ledgers = computed(() => page.props.ledgers?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])

const form = useForm({
  number: page.props.latestNumber ?? '',
  date: '',
  customer_ledger_id: '',
  selected_customer: null,
  supplier_ledger_id: '',
  selected_supplier: null,
  currency_id: '',
  selected_currency: null,
  rate: '',
  amount: '',
  narration: '',
  customer_allocations: [],
  supplier_allocations: [],
})

// What each side actually has outstanding, straight from the settlement
// engine. The form will not let the amount run past the smaller of the two:
// anything beyond the overlap is not a set-off, it is a prepayment nobody
// agreed to.
const openReceivable = ref(null)
const openPayable = ref(null)
const loadingSides = ref(false)

const maxOffset = computed(() => {
  if (openReceivable.value === null || openPayable.value === null) return null
  return Math.min(openReceivable.value, openPayable.value)
})

const overTheOverlap = computed(() => {
  const amount = Number(form.amount || 0)
  return maxOffset.value !== null && amount > 0 && amount > maxOffset.value + 0.0001
})

const showCustomerDialog = ref(false)
const showSupplierDialog = ref(false)
const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')

const currencyCode = computed(() => form.selected_currency?.code || '')

const money = (value) => Number(value || 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

async function loadSide(ledgerId, direction) {
  if (!ledgerId) return null

  const { data } = await axios.get(route('settlements.open-items'), {
    params: {
      ledger_id: ledgerId,
      currency_id: form.currency_id || undefined,
      direction,
    },
  })

  return (data?.data || []).reduce((total, row) => total + Number(row.remaining_amount || 0), 0)
}

async function refreshSides() {
  if (!form.customer_ledger_id && !form.supplier_ledger_id) {
    openReceivable.value = null
    openPayable.value = null
    return
  }

  loadingSides.value = true

  try {
    const [receivable, payable] = await Promise.all([
      loadSide(form.customer_ledger_id, 'in'),
      loadSide(form.supplier_ledger_id, 'out'),
    ])

    openReceivable.value = receivable
    openPayable.value = payable
  } catch (error) {
    openReceivable.value = null
    openPayable.value = null
  } finally {
    loadingSides.value = false
  }
}

watch(
  () => [form.customer_ledger_id, form.supplier_ledger_id, form.currency_id],
  () => { refreshSides() },
)

// Changing a side invalidates what was picked against it.
watch(() => form.customer_ledger_id, () => { form.customer_allocations = [] })
watch(() => form.supplier_ledger_id, () => { form.supplier_allocations = [] })

watch(currencies, (list) => {
  if (list && list.length && !form.currency_id) {
    const base = list.find((c) => c.is_base_currency)
    if (base) {
      form.selected_currency = base
      form.currency_id = base.id
      form.rate = base.exchange_rate
    }
  }
}, { immediate: true })

function handleSelectChange(field, value) {
  form[field] = value

  if (field === 'currency_id') {
    const chosen = currencies.value.find((c) => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

/**
 * Pick one side and the other fills itself.
 *
 * The two accounts of one person are paired on the party record, so the form
 * knows which supplier goes with which customer. Only ever fills an EMPTY box:
 * someone who deliberately chose an unpaired pair is not overruled.
 */
function autofillCounterpart(option, targetField, targetSelected) {
  const counterpartId = option?.counterpart_ledger_id

  if (!counterpartId || form[targetField]) return

  const match = ledgers.value.find((ledger) => ledger.id === counterpartId)

  if (!match) return

  form[targetSelected] = match
  form[targetField] = match.id
}

function chooseCustomer(option) {
  handleSelectChange('customer_ledger_id', option?.id ?? '')
  autofillCounterpart(option, 'supplier_ledger_id', 'selected_supplier')
}

function chooseSupplier(option) {
  handleSelectChange('supplier_ledger_id', option?.id ?? '')
  autofillCounterpart(option, 'customer_ledger_id', 'selected_customer')
}

function useTheMaximum() {
  if (maxOffset.value !== null) form.amount = maxOffset.value
}

onMounted(() => {
  form.date = todayValueForCalendar(calendarType.value)
})

function submit() {
  submitAction.value = 'create'

  form.post(route('contra-settlements.store'), {
    onSuccess: () => {
      toast.success(t('general.success'), {
        description: t('general.create_success', { name: t('contra.contra_settlement') }),
        class: 'bg-green-600',
      })
    },
    onError: (errors) => {
      const first = errors ? Object.values(errors)[0] : null
      toast.error(t('general.error'), {
        description: first || t('general.create_error', { name: t('contra.contra_settlement') }),
        class: 'bg-red-600',
      })
    },
  })
}

useFormGuard(form)
const saveFormRef = useSaveShortcut({ form })
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('contra.contra_settlement') })">
    <FormPageToolbar back-route="contra-settlements.index" module="contra_settlement" />

    <form ref="saveFormRef" @submit.prevent="submit">
      <div class="mb-5 rounded-xl border border-primary p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-violet-500">
          {{ t('general.create', { name: t('contra.contra_settlement') }) }}
        </div>

        <p class="mt-3 text-sm text-muted-foreground">{{ t('contra.what_it_is') }}</p>
        <p class="mt-1 text-xs text-muted-foreground">{{ t('contra.two_ledgers_hint') }}</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
          <NextSelect
            autofocus
            :options="ledgers"
            v-model="form.selected_customer"
            @update:modelValue="chooseCustomer"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('contra.customer_account')"
            is-required
            :error="form.errors?.customer_ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-options="{ types: ['customer'] }"
            :search-fields="['name', 'email', 'phone_no']"
          />

          <NextSelect
            :options="ledgers"
            v-model="form.selected_supplier"
            @update:modelValue="chooseSupplier"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('contra.supplier_account')"
            is-required
            :error="form.errors?.supplier_ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-options="{ types: ['supplier'] }"
            :search-fields="['name', 'email', 'phone_no']"
          />

          <NextInput
            is-required
            v-model="form.number"
            type="text"
            :error="form.errors?.number"
            :label="t('general.number')"
            :placeholder="t('general.enter', { text: t('general.number') })"
          />

          <NextDate
            v-model="form.date"
            :current-date="true"
            :error="form.errors?.date"
            :label="t('general.date')"
            :placeholder="t('general.enter', { text: t('general.date') })"
          />

          <NextSelect
            :options="currencies"
            v-model="form.selected_currency"
            label-key="display_name"
            value-key="id"
            @update:modelValue="(v) => handleSelectChange('currency_id', v?.id ?? '')"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            is-required
            :error="form.errors?.currency_id"
            :searchable="true"
            resource-type="currencies"
            :search-fields="['name', 'code', 'symbol']"
          />

          <NextInput
            is-required
            type="number"
            step="any"
            v-model="form.rate"
            :disabled="form.selected_currency?.is_base_currency === true"
            :error="form.errors?.rate"
            :label="t('general.rate')"
            :placeholder="t('general.enter', { text: t('general.rate') })"
          />
        </div>

        <p class="mt-3 text-xs text-muted-foreground">{{ t('contra.one_currency_only') }}</p>
      </div>

      <!-- What each side is holding. Shown before the amount box because the
           amount only makes sense once both are on screen. -->
      <div class="mb-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="rounded-xl border p-4">
          <div class="text-sm font-semibold text-violet-500">{{ t('contra.customer_side') }}</div>
          <div class="mt-1 text-xs text-muted-foreground">{{ t('contra.open_receivable') }}</div>
          <div class="mt-2 text-2xl font-bold">
            <span v-if="loadingSides">…</span>
            <span v-else-if="openReceivable === null">—</span>
            <span v-else>{{ money(openReceivable) }} {{ currencyCode }}</span>
          </div>
          <button
            type="button"
            class="mt-3 inline-flex items-center justify-center rounded-md border px-3 py-2 text-sm font-medium disabled:opacity-50"
            :disabled="!form.customer_ledger_id"
            @click="showCustomerDialog = true"
          >
            {{ t('contra.select_invoices') }}
            <span v-if="form.customer_allocations.length" class="ltr:ml-2 rtl:mr-2 text-xs text-muted-foreground">
              ({{ form.customer_allocations.length }})
            </span>
          </button>
        </div>

        <div class="rounded-xl border p-4">
          <div class="text-sm font-semibold text-violet-500">{{ t('contra.supplier_side') }}</div>
          <div class="mt-1 text-xs text-muted-foreground">{{ t('contra.open_payable') }}</div>
          <div class="mt-2 text-2xl font-bold">
            <span v-if="loadingSides">…</span>
            <span v-else-if="openPayable === null">—</span>
            <span v-else>{{ money(openPayable) }} {{ currencyCode }}</span>
          </div>
          <button
            type="button"
            class="mt-3 inline-flex items-center justify-center rounded-md border px-3 py-2 text-sm font-medium disabled:opacity-50"
            :disabled="!form.supplier_ledger_id"
            @click="showSupplierDialog = true"
          >
            {{ t('contra.select_bills') }}
            <span v-if="form.supplier_allocations.length" class="ltr:ml-2 rtl:mr-2 text-xs text-muted-foreground">
              ({{ form.supplier_allocations.length }})
            </span>
          </button>
        </div>
      </div>

      <div class="mb-5 rounded-xl border p-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <NextInput
            is-required
            type="number"
            step="any"
            v-model="form.amount"
            :error="form.errors?.amount"
            :label="t('contra.offset_amount')"
            :placeholder="t('general.enter', { text: t('contra.offset_amount') })"
          />

          <div class="md:col-span-2 flex flex-col justify-center gap-1">
            <p v-if="maxOffset === null" class="text-xs text-muted-foreground">
              {{ t('contra.pick_both_accounts') }}
            </p>
            <p v-else-if="maxOffset <= 0" class="text-xs text-amber-600">
              {{ t('contra.nothing_to_offset') }}
            </p>
            <template v-else>
              <p class="text-xs text-muted-foreground">
                {{ t('contra.most_that_can_be_offset', { amount: `${money(maxOffset)} ${currencyCode}` }) }}
                <button type="button" class="ltr:ml-2 rtl:mr-2 underline" @click="useTheMaximum">
                  {{ t('contra.offset_amount') }}
                </button>
              </p>
              <p v-if="overTheOverlap" class="text-xs text-red-600">{{ t('contra.exceeds_overlap') }}</p>
            </template>
            <p class="text-xs text-muted-foreground">{{ t('contra.leave_empty_for_oldest_first') }}</p>
          </div>
        </div>

        <div class="mt-4">
          <NextTextarea
            v-model="form.narration"
            :error="form.errors?.narration"
            :label="t('general.narration')"
            :placeholder="t('general.enter', { text: t('general.narration') })"
          />
        </div>
      </div>

      <SubmitButtons
        module="contra_settlement"
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('contra.contra_settlement') })"
        :create-loading="createLoading"
        :show-create-and-new="false"
        :show-save-and-print="false"
        @cancel="() => $inertia.visit(route('contra-settlements.index'))"
      />

      <SettlementDialog
        :open="showCustomerDialog"
        direction="in"
        :ledger-id="form.customer_ledger_id"
        :currency-id="form.currency_id"
        :currency-code="currencyCode"
        :amount="Number(form.amount || 0)"
        :rate="Number(form.rate || 1)"
        :allocations="form.customer_allocations"
        :applied-cash="[]"
        @update:open="showCustomerDialog = $event"
        @update:allocations="(value) => form.customer_allocations = value"
        @save="({ allocations }) => form.customer_allocations = allocations"
      />

      <SettlementDialog
        :open="showSupplierDialog"
        direction="out"
        :ledger-id="form.supplier_ledger_id"
        :currency-id="form.currency_id"
        :currency-code="currencyCode"
        :amount="Number(form.amount || 0)"
        :rate="Number(form.rate || 1)"
        :allocations="form.supplier_allocations"
        :applied-cash="[]"
        @update:open="showSupplierDialog = $event"
        @update:allocations="(value) => form.supplier_allocations = value"
        @save="({ allocations }) => form.supplier_allocations = allocations"
      />
    </form>
  </AppLayout>
</template>
