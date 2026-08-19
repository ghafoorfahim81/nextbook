<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useFormGuard } from '@/composables/useFormGuard'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, computed, reactive, onMounted } from 'vue'
import axios from 'axios'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import SettlementDialog from '@/Components/next/SettlementDialog.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import AttachmentUploader from '@/Components/AttachmentUploader.vue'
import FormPageToolbar from '@/Components/FormPageToolbar.vue'
import FormPreferencesPanel from '@/Components/FormPreferencesPanel.vue'
import { formatLedgerBalance } from '@/utils/balanceNature'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { todayValueForCalendar } from '@/utils/dateDefaults'
const { t } = useI18n()

const page = usePage()
const balanceNatureFormat = computed(() => page.props.balanceNatureFormat || 'with_nature')
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')
// Every party, not just suppliers. Refunding a customer's overpayment is a
// payment, and a party who both buys and sells is one name in one list. What
// makes the entry correct is the DIRECTION of the cash, which the module
// fixes — not a restriction on who can appear here.
const ledgers = computed(() => page.props.ledgers?.data || [])
// Money in and out of a voucher always lands on a cash or bank account. The
// shared `accounts` prop is the whole chart, so the box would otherwise offer
// revenue and payable accounts that would post a nonsense entry if picked.
const accounts = computed(() => (page.props.accounts?.data || [])
  .filter((account) => account.account_type?.slug === 'cash-or-bank'))
const currencies = computed(() => page.props.currencies?.data || [])
const paymentModes = computed(() => page.props.paymentModes || [])
// Single reactive copy of the receipt/payment preferences so the panel and form stay in sync live.
const rpPrefs = reactive(JSON.parse(JSON.stringify(page.props.user_preferences?.receipt_payment ?? {})))
if (!rpPrefs.visible_fields || typeof rpPrefs.visible_fields !== 'object') rpPrefs.visible_fields = {}
const rpFields = computed(() => rpPrefs.visible_fields)
const showPreferencesPanel = ref(false)

const { loading: lazyLoading } = useLazyProps(page.props, ['ledgers', 'accounts'])
const billLoading = ref(false)
useLazyProps(page.props, ['ledgers', 'accounts'])
const showBillDialog = ref(false)
const initialized = ref(false)

const form = useForm({
  number: page.props.latestNumber ?? '',
  date: '',
  ledger_id: '',
  selected_ledger: null,
  payment_mode: 'on_account',
  amount: '',
  bank_account_id: '',
  selected_bank_account: null,
  currency_id: '',
  selected_currency: null,
  rate: '',
  cheque_no: '',
  narration: '',
  allocations: [],
  attachments: [],
  // Only sent when the cash and the claim are in different currencies. The
  // server refuses to guess the conversion the two parties agreed on.
  applied_cash: [],
})

const submitAction = ref(null)
const createLoading = computed(() => form.processing && submitAction.value === 'create')
const createAndNewLoading = computed(() => form.processing && submitAction.value === 'create_and_new')
const saveAndPrintLoading = computed(() => form.processing && submitAction.value === 'create_and_print')
const pendingPrintWindow = ref(null)

const submitActionHandler = (action = 'create') => {
  submitAction.value = action

  if (action === 'create_and_print') {
    pendingPrintWindow.value = window.open('about:blank', '_blank')
  }

  submit({
    createAndNew: action === 'create_and_new',
    createAndPrint: action === 'create_and_print',
  })
}

watch(currencies, (list) => {
  if (list && list.length && !form.currency_id) {
    const base = list.find(c => c.is_base_currency)
    if (base) {
      form.selected_currency = base
      form.currency_id = base.id
      form.rate = base.exchange_rate
    }
  }
}, { immediate: true })

const applyCreateDefaults = ({ number = page.props.latestNumber ?? form.number } = {}) => {
  form.number = number
  form.date = todayValueForCalendar(calendarType.value)

  const base = currencies.value.find(c => c.is_base_currency)
  if (base) {
    form.selected_currency = base
    form.currency_id = base.id
    form.rate = base.exchange_rate
  }

  // Ledger passed via the URL (e.g. from a customer/supplier page) is preselected.
  const presetLedger = page.props.preselectedLedger?.data ?? page.props.preselectedLedger ?? null
  if (presetLedger?.id) {
    form.selected_ledger = presetLedger
    form.ledger_id = presetLedger.id
  }
}

function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = currencies.value.find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

const openBillDialog = () => {
  if (form.payment_mode !== 'bill_by_bill' || !form.ledger_id) {
    return
  }

  // The dialog loads its own open items — it needs each bill's booking rate and
  // remaining amount, which only the settlement endpoint knows.
  showBillDialog.value = true
}

const handleSettlementSave = ({ allocations, applied_cash }) => {
  form.allocations = allocations
  form.applied_cash = applied_cash
}

watch([() => form.ledger_id, () => form.payment_mode], async ([ledgerId, paymentMode], [prevLedgerId, prevPaymentMode]) => {
  if (!initialized.value) {
    return
  }

  if (paymentMode !== 'bill_by_bill') {
    form.allocations = []
    form.applied_cash = []
    showBillDialog.value = false
    return
  }

  if (ledgerId && (ledgerId !== prevLedgerId || paymentMode !== prevPaymentMode)) {
    openBillDialog()
  }
})

function oldBalanceText() {
  return formatLedgerBalance(form.selected_ledger?.statement, balanceNatureFormat.value, t)
}

function finalizePrint(page) {
  const printUrl = page?.props?.flash?.print_url

  if (!printUrl) {
    if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
      pendingPrintWindow.value.close()
    }
    pendingPrintWindow.value = null
    return
  }

  if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
    pendingPrintWindow.value.location = printUrl
    pendingPrintWindow.value.focus?.()
  } else {
    window.open(printUrl, '_blank')
  }

  pendingPrintWindow.value = null
}

// The store redirects back to the create page, so the response carries a
// freshly computed latestNumber. Prefer it over counting up locally: it also
// accounts for payments other users saved while this form was open.
function nextNumberAfterSave(page) {
  const fromServer = Number(page?.props?.latestNumber)
  if (Number.isFinite(fromServer) && fromServer > 0) return fromServer

  const current = Number(form.number)
  return (Number.isFinite(current) ? current : 0) + 1
}

function cleanupPrintWindow() {
  if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
    pendingPrintWindow.value.close()
  }

  pendingPrintWindow.value = null
}

function submit({ createAndNew = false, createAndPrint = false } = {}) {
  const payload = {
    create_and_new: createAndNew,
    create_and_print: createAndPrint,
  }
  form.transform(data => ({ ...data, ...payload })).post('/payments', {
    onSuccess: (page) => {
      if (createAndNew) {
        form.reset('date', 'amount', 'cheque_no', 'narration')
        form.payment_mode = 'on_account'
        form.allocations = []
        form.attachments = []
        form.applied_cash = []
        showBillDialog.value = false
        applyCreateDefaults({ number: String(nextNumberAfterSave(page)) })
      }
      if (createAndPrint) {
        finalizePrint(page)
      }
      toast.success(t('general.success'), {
        description: t('general.create_success', { name: 'Payment' }),
        class: 'bg-green-600 text-white',
      })
    },
    onError: () => {
      cleanupPrintWindow()
    }
  })
}

onMounted(() => {
  applyCreateDefaults()
  initialized.value = true
})

useFormGuard(form)
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('payment.payment') })">
    <FormPageToolbar
      back-route="payments.index"
      module="payments"
      :show-preferences="true"
      @preferences="showPreferencesPanel = true"
    />
    <FormPreferencesPanel module="payment"
      v-model:open="showPreferencesPanel"
      pref-group="receipt_payment"
      :prefs="rpPrefs"
      :title="t('preferences.tabs.receipt_payment')"
    />
    <form @submit.prevent="submitActionHandler('create')">
      <div class="mb-5 rounded-xl border border-primary p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('payment.payment') }) }}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextSelect
            autofocus
            :loading="lazyLoading"
            :options="ledgers"
            v-model="form.selected_ledger"
            @update:modelValue="(v) => handleSelectChange('ledger_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('ledger.supplier.supplier')"
            is-required
            :error="form.errors?.ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-options="{ types: ['customer', 'supplier'] }"
            :search-fields="['name', 'email', 'phone_no']"
          />

          <NextInput is-required v-if="rpFields.number" placeholder="Number" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
          <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextInput is-required :placeholder="t('general.enter', { text: t('general.amount') })" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
          <NextSelect
            v-if="rpFields.currency"
            :options="currencies"
            v-model="form.selected_currency"
            label-key="code"
            value-key="id"
            @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            is-required
            :error="form.errors?.currency_id"
            :searchable="true"
            resource-type="currencies"
            :search-fields="['name', 'code', 'symbol']"
          />
          <NextInput is-required v-if="rpFields.currency" :placeholder="t('general.enter', { text: t('general.rate') })" :error="form.errors?.rate" :disabled="form.selected_currency?.is_base_currency === true" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
          <NextSelect
            :options="paymentModes"
            v-model="form.payment_mode"
            label-key="name"
            value-key="id"
            :reduce="mode => mode.id"
            :floating-text="t('general.payment_mode')"
            :searchable="false"
            :clearable="false"
            :error="form.errors?.payment_mode"
          />
          <NextSelect
            v-if="rpFields.debit_account"
            :loading="lazyLoading"
            :options="accounts"
            v-model="form.selected_bank_account"
            @update:modelValue="(v) => handleSelectChange('bank_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="t('payment.pay_from_account')"
            is-required
            :error="form.errors?.bank_account_id"
            :searchable="true"
            resource-type="accounts"
            :search-fields="['name', 'number', 'slug']"
          />
          <div class="flex flex-col gap-2">
            <button
              v-if="form.payment_mode === 'bill_by_bill'"
              type="button"
              class="inline-flex items-center justify-center rounded-md border px-3 py-2 text-sm font-medium"
              @click="openBillDialog"
            >
              {{ t('general.allocate_bills') || 'Allocate bills' }}
            </button>
            <p v-if="form.allocations.length" class="text-xs text-muted-foreground">
              {{ form.allocations.length }} {{ t('general.bills_selected') || 'bills selected' }}
            </p>
          </div>
          <NextInput v-if="rpFields.cheque_number" :placeholder="t('general.enter', { text: t('general.cheque_no') })" :error="form.errors?.cheque_no" v-model="form.cheque_no" :label="t('general.cheque_no')" />
          <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <NextTextarea :placeholder="t('general.enter', { text: t('general.narration') })" :error="form.errors?.narration" v-model="form.narration" :label="t('general.narration')" />
            </div>
            <div class="md:col-span-1" v-if="rpFields.ledger_old_balance">
              <div class="rounded-xl border p-4 w-full md:w-64 ml-auto">
                <div class="text-sm font-semibold mb-2 text-violet-500">{{ t('general.old_balance') }}</div>
                <div class="text-lg font-bold">{{ oldBalanceText() }}</div>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-4">
          <AttachmentUploader v-model="form.attachments" :label="t('general.attachments')" :error="form.errors['attachments.0']" />
        </div>
      </div>

      <SubmitButtons module="payment"
        :create-label="t('general.create')"
        :create-and-new-label="t('general.create_and_new')"
        :save-and-print-label="t('general.save_and_print')"
        :cancel-label="t('general.cancel')"
        :creating-label="t('general.creating', { name: t('payment.payment') })"
        :create-loading="createLoading"
        :create-and-new-loading="createAndNewLoading"
        :save-and-print-loading="saveAndPrintLoading"
        :show-save-and-print="true"
        @create-and-new="submitActionHandler('create_and_new')"
        @save-and-print="submitActionHandler('create_and_print')"
        @cancel="() => $inertia.visit('/payments')"
      />
      <SettlementDialog
        :open="showBillDialog"
        direction="out"
        :ledger-id="form.ledger_id"
        :currency-id="form.currency_id"
        :currency-code="form.selected_currency?.code || ''"
        :amount="Number(form.amount || 0)"
        :rate="Number(form.rate || 1)"
        :allocations="form.allocations"
        :applied-cash="form.applied_cash"
        @update:open="showBillDialog = $event"
        @update:allocations="(value) => form.allocations = value"
        @save="handleSettlementSave"
      />
    </form>
  </AppLayout>
</template>
