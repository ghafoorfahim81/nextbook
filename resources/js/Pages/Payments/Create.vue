<script setup>
import AppLayout from '@/Layouts/Layout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted } from 'vue'
import axios from 'axios'
import { useLazyProps } from '@/composables/useLazyProps'
import NextInput from '@/Components/next/NextInput.vue'
import NextSelect from '@/Components/next/NextSelect.vue'
import NextTextarea from '@/Components/next/NextTextarea.vue'
import NextDate from '@/Components/next/NextDatePicker.vue'
import BillAllocationDialog from '@/Components/next/BillAllocationDialog.vue'
import SubmitButtons from '@/Components/SubmitButtons.vue'
import ModuleHelpButton from '@/Components/ModuleHelpButton.vue'
import { useI18n } from 'vue-i18n'
import { toast } from 'vue-sonner'
import { todayValueForCalendar } from '@/utils/dateDefaults'
const { t } = useI18n()

const page = usePage()
const calendarType = computed(() => page.props.auth?.user?.calendar_type || 'gregorian')
const ledgers = computed(() => page.props.ledgers?.data || [])
const accounts = computed(() => page.props.accounts?.data || [])
const currencies = computed(() => page.props.currencies?.data || [])
const paymentModes = computed(() => page.props.paymentModes || [])

useLazyProps(page.props, ['ledgers', 'accounts'])
const billLoading = ref(false)
const showBillDialog = ref(false)
const billOptions = ref([])
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
}

function handleSelectChange(field, value) {
  form[field] = value
  if (field === 'currency_id') {
    const chosen = currencies.value.find(c => c.id === value)
    if (chosen) form.rate = chosen.exchange_rate
  }
}

const loadBills = async () => {
  if (!form.ledger_id) {
    billOptions.value = []
    return
  }

  billLoading.value = true
  try {
    const { data } = await axios.get('/purchases/open-bills', {
      params: { ledger_id: form.ledger_id },
    })
    billOptions.value = data?.data || []
  } finally {
    billLoading.value = false
  }
}

const openBillDialog = async () => {
  if (form.payment_mode !== 'bill_by_bill' || !form.ledger_id) {
    return
  }

  await loadBills()
  showBillDialog.value = true
}

const handleBillAllocationsSave = (allocations) => {
  form.allocations = allocations
}

watch([() => form.ledger_id, () => form.payment_mode], async ([ledgerId, paymentMode], [prevLedgerId, prevPaymentMode]) => {
  if (!initialized.value) {
    return
  }

  if (paymentMode !== 'bill_by_bill') {
    form.allocations = []
    showBillDialog.value = false
    return
  }

  if (ledgerId && (ledgerId !== prevLedgerId || paymentMode !== prevPaymentMode)) {
    await openBillDialog()
  }
})

function oldBalanceText() {
  const s = form.selected_ledger?.statement
  if (!s) return ''
  return s.balance > 0
    ? `${s.balance} ${String(s.balance_nature || '').toUpperCase()}`
    : `${s.balance}`
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
      const latest = Number(form.number || 0)
      if (createAndNew) {
        form.reset('date', 'amount', 'cheque_no', 'narration')
        form.payment_mode = 'on_account'
        form.allocations = []
        showBillDialog.value = false
        billOptions.value = []
        applyCreateDefaults({ number: String((isNaN(latest) ? 0 : latest) + 1) })
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
</script>

<template>
  <AppLayout :title="t('general.create', { name: t('payment.payment') })">
    <form @submit.prevent="submitActionHandler('create')">
      <div class="mb-5 rounded-xl border border-primary p-4 shadow-sm relative">
        <div class="absolute -top-3 ltr:left-3 rtl:right-3 bg-card px-2 text-sm font-semibold text-muted-foreground text-violet-500">
          {{ t('general.create', { name: t('payment.payment') }) }}
        </div>
        <ModuleHelpButton module="payments" />
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-3">
          <NextSelect
            autofocus
            :options="ledgers"
            v-model="form.selected_ledger"
            @update:modelValue="(v) => handleSelectChange('ledger_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="ledger => ledger"
            :floating-text="t('ledger.supplier.supplier')"
            :error="form.errors?.ledger_id"
            :searchable="true"
            resource-type="ledgers"
            :search-fields="['name', 'email', 'phone_no']"
          />

          <NextInput placeholder="Number" :error="form.errors?.number" v-model="form.number" type="text" :label="t('general.number')" />
          <NextDate v-model="form.date" :current-date="true" :error="form.errors?.date" :placeholder="t('general.enter', { text: t('general.date') })" :label="t('general.date')" />
          <NextInput :placeholder="t('general.enter', { text: t('general.amount') })" :error="form.errors?.amount" type="number" step="any" v-model="form.amount" :label="t('general.amount')" />
          <NextSelect
            :options="currencies"
            v-model="form.selected_currency"
            label-key="code"
            value-key="id"
            @update:modelValue="(value) => handleSelectChange('currency_id', value.id)"
            :reduce="currency => currency"
            :floating-text="t('admin.currency.currency')"
            :error="form.errors?.currency_id"
            :searchable="true"
            resource-type="currencies"
            :search-fields="['name', 'code', 'symbol']"
          />
          <NextInput :placeholder="t('general.enter', { text: t('general.rate') })" :error="form.errors?.rate" :disabled="form.selected_currency?.is_base_currency === true" type="number" step="any" v-model="form.rate" :label="t('general.rate')" />
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
            :options="accounts"
            v-model="form.selected_bank_account"
            @update:modelValue="(v) => handleSelectChange('bank_account_id', v.id)"
            label-key="name"
            value-key="id"
            :reduce="acc => acc"
            :floating-text="t('payment.pay_from_account')"
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
          <NextInput :placeholder="t('general.enter', { text: t('general.cheque_no') })" :error="form.errors?.cheque_no" v-model="form.cheque_no" :label="t('general.cheque_no')" />
          <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <NextTextarea :placeholder="t('general.enter', { text: t('general.narration') })" :error="form.errors?.narration" v-model="form.narration" :label="t('general.narration')" />
            </div>
            <div class="md:col-span-1">
              <div class="rounded-xl border p-4 w-full md:w-64 ml-auto">
                <div class="text-sm font-semibold mb-2 text-violet-500">{{ t('general.old_balance') }}</div>
                <div class="text-lg font-bold">{{ oldBalanceText() }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <SubmitButtons
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
      <BillAllocationDialog
        :open="showBillDialog"
        :title="t('general.allocate_bills') || 'Allocate bills'"
        bill-label="Purchase"
        :amount="Number(form.amount || 0)"
        :bills="billOptions"
        :loading="billLoading"
        :allocations="form.allocations"
        @update:open="showBillDialog = $event"
        @update:allocations="(value) => form.allocations = value"
        @save="handleBillAllocationsSave"
      />
    </form>
  </AppLayout>
</template>
